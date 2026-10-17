#!/bin/sh
set -eu
case "${PORT:-10000}" in *[!0-9]*|'') echo 'PORT must be a number.' >&2; exit 1;; esac
export PORT="${PORT:-10000}"
if [ -z "${APP_KEY:-}" ]; then
    if [ -z "${APP_KEY_SEED:-}" ]; then
        echo 'Configure APP_KEY or a persistent APP_KEY_SEED in hosting settings.' >&2
        exit 1
    fi
    APP_KEY=$(php -r 'echo "base64:".base64_encode(hash("sha256",getenv("APP_KEY_SEED"),true));')
    export APP_KEY
fi
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    APP_URL="$RENDER_EXTERNAL_URL"
    export APP_URL
fi
# Environment-only production config; never bake deployment credentials into the image.
php artisan config:clear
php artisan migrate --force
php artisan society:bootstrap-organizer --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec "$@"
