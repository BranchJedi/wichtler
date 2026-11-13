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

if [ -z "${JWT_PASSPHRASE:-}" ]; then
    echo "JWT_PASSPHRASE is required."
    exit 1
fi

mkdir -p var/cache var/log var/share

# Only the web server process needs a warm cache; one-off commands build theirs on demand.
if [ "${1:-}" = "apache2-foreground" ]; then
    # The key pair lives in a volume: created once, reused on every later start.
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    chown -R www-data:www-data config/jwt
    chmod 600 config/jwt/private.pem
    php bin/console cache:clear --no-interaction
    php bin/console assets:install public --no-interaction
fi

chown -R www-data:www-data var

exec "$@"
