# syntax=docker/dockerfile:1.7

FROM composer:2.8.12 AS composer-bin

FROM php:8.4-cli-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PATH="/app/vendor/bin:${PATH}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

# Project-independent quality tools. Exact top-level versions are pinned here
# so application composer.json/composer.lock remain untouched.
RUN mkdir -p /opt/php-tools \
    && cd /opt/php-tools \
    && composer init --no-interaction --name=local/quality-tools \
    && composer require --no-interaction --no-progress --prefer-dist \
        phpstan/phpstan:2.2.12 \
        friendsofphp/php-cs-fixer:3.95.24 \
        deptrac/deptrac:4.7.1 \
    && composer clear-cache

ENV PATH="/opt/php-tools/vendor/bin:/app/vendor/bin:${PATH}"

WORKDIR /app

FROM base AS dev
COPY infrastructure/docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-app.ini
CMD ["sh", "-lc", "while [ ! -f vendor/autoload.php ]; do echo 'waiting for vendor/autoload.php'; sleep 1; done; exec php -S 0.0.0.0:8000 -t public"]

FROM base AS test
COPY infrastructure/docker/php/php.test.ini /usr/local/etc/php/conf.d/99-app.ini
CMD ["sh", "-lc", "tail -f /dev/null"]
