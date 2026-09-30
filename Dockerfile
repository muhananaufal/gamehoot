# A8: PHP 8.4 in every environment. Base image and tools are pinned (P2).
# Debian, not Alpine: Playwright (browser tests, W11) supports Debian and Ubuntu only, and
# tests should run on the same OS as production.
FROM php:8.4.26-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql bcmath intl zip pcntl opcache

COPY docker/php/conf.d/pentahoot.ini /usr/local/etc/php/conf.d/pentahoot.ini

WORKDIR /var/www/html

# Local development and CI: Composer, PCOV for coverage and mutation tests (K2), the
# sockets extension required by the Pest browser plugin, and GD for the fake images of the
# upload tests (E8). Production needs no GD: images are resized in the host's browser.
FROM base AS dev

RUN install-php-extensions pcov sockets gd

COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer

# Browser tests (W11): the Pest browser plugin starts Playwright from the PHP process, so
# Node, Playwright and its browsers live in the same image. PLAYWRIGHT_VERSION must match
# the playwright package in package.json.
FROM dev AS browser

ARG PLAYWRIGHT_VERSION=1.62.1

COPY --from=node:24.15.0-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24.15.0-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npx --yes "playwright@${PLAYWRIGHT_VERSION}" install --with-deps chromium webkit \
    && rm -rf /root/.npm /var/lib/apt/lists/*

# The browser plugin serves the app from the PHP CLI process; without the opcache every request
# re-reads the source, which is slow on bind mounts and makes navigation waits time out.
RUN echo 'opcache.enable_cli=1' > /usr/local/etc/php/conf.d/zz-browser-tests.ini
