# A8: PHP 8.4 in every environment. Base image and tools are pinned (P2).
FROM php:8.4.26-fpm-alpine3.24 AS base

COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql bcmath intl zip pcntl opcache

COPY docker/php/conf.d/pentahoot.ini /usr/local/etc/php/conf.d/pentahoot.ini

WORKDIR /var/www/html

# Local development and CI: Composer plus PCOV for coverage and mutation tests (K2).
FROM base AS dev

RUN install-php-extensions pcov

COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer
