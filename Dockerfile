# A8: PHP 8.4 in every environment. Base image and tools are pinned (P2).
# Debian, not Alpine: Playwright (browser tests, W11) supports Debian and Ubuntu only, and
# tests should run on the same OS as production.
FROM php:8.4.26-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/

# GD encodes every uploaded image again on the server, dropping camera metadata (E8, O7).
RUN install-php-extensions pdo_mysql bcmath intl zip pcntl opcache gd

COPY docker/php/conf.d/pentahoot.ini /usr/local/etc/php/conf.d/pentahoot.ini

WORKDIR /var/www/html

# Local development and CI: Composer, PCOV for coverage and mutation tests (K2), the
# sockets extension required by the Pest browser plugin.
FROM base AS dev

RUN install-php-extensions pcov sockets

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

# ---------------------------------------------------------------------------------------------
# Production (P1, P2): built once in CI, pushed to the registry and only pulled on the server.
# ---------------------------------------------------------------------------------------------

# Composer packages without dev dependencies, and an optimized class map.
FROM dev AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && rm -rf tests

# Front-end assets, built with the same Node as local development and CI.
FROM node:24.15.0-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# The PHP image for php-fpm (service app) and Reverb (service reverb). Runs as www-data.
FROM base AS production

# cgi-fcgi asks php-fpm for /ping in the health check.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libfcgi-bin \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pentahoot.conf
COPY docker/php/entrypoint.sh /usr/local/bin/pentahoot-entrypoint
# Git on Windows does not keep the executable bit.
RUN chmod 0755 /usr/local/bin/pentahoot-entrypoint

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# F16: public/media points at the media disk, which is a volume on the server.
RUN php artisan storage:link \
    && mkdir -p storage/app/media/public storage/app/media/private storage/logs \
        storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/media

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stack LOG_STACK=daily

USER www-data

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["pentahoot-entrypoint"]
CMD ["php-fpm"]

# nginx in front of php-fpm (service web). Serves public files and the media volume, hands
# signed private images over with X-Accel-Redirect (F16), and runs as a non-root user.
# The nginx images moved from Debian bookworm to trixie after 1.29.1; still Debian (P2).
FROM nginxinc/nginx-unprivileged:1.29.8-trixie AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/nginx-health || exit 1
