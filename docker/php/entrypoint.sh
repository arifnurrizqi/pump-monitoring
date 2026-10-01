#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is empty. Set it in .env.docker before starting the stack." >&2
    exit 1
fi

if [ "${BROADCAST_CONNECTION:-}" = "reverb" ]; then
    if [ -z "${REVERB_APP_ID:-}" ] || [ -z "${REVERB_APP_KEY:-}" ] || [ -z "${REVERB_APP_SECRET:-}" ] || [ -z "${REVERB_HOST:-}" ]; then
        echo "ERROR: Reverb runtime configuration is incomplete in .env.docker." >&2
        exit 1
    fi

fi

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
su-exec www-data php artisan config:cache --no-ansi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"
