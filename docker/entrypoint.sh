#!/bin/sh
set -eu

APP_ENV="${APP_ENV:-prod}"
APP_DEBUG="${APP_DEBUG:-0}"

if [ "$APP_ENV" = "prod" ] && [ "$APP_DEBUG" != "0" ]; then
    echo "Refusing to start with APP_DEBUG enabled in production."
    exit 1
fi

if [ -z "${APP_SECRET:-}" ]; then
    echo "APP_SECRET is required."
    exit 1
fi

if [ -z "${DATABASE_URL:-}" ]; then
    echo "DATABASE_URL is required."
    exit 1
fi

mkdir -p var/cache var/log var/share

# Only the web server process needs a warm cache; one-off commands build theirs on demand.
if [ "${1:-}" = "apache2-foreground" ]; then
    php bin/console cache:clear --no-interaction
    php bin/console assets:install public --no-interaction
fi

chown -R www-data:www-data var

exec "$@"
