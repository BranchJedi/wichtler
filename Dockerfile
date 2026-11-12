# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-req=php

FROM node:24-alpine AS frontend

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend ./
# Generates the orval client from the committed openapi.json, then type-checks and bundles.
RUN npm run build

FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev ca-certificates \
    && docker-php-ext-install intl pdo_mysql \
    && a2enmod rewrite headers \
    && apt-get clean \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/wichtler.ini

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY backend ./
COPY --from=frontend /app/dist ./public/
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p var/cache var/log var/share \
    && chown -R www-data:www-data var

ENV APP_ENV=prod \
    APP_DEBUG=0

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=5 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/api/health") === false ? 1 : 0);'

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
