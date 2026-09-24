#!/usr/bin/env sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

php artisan config:clear --ansi

# Sync .env DB settings with Docker environment (host .env is mounted)
if [ -n "$DB_HOST" ]; then sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" .env; fi
if [ -n "$DB_PORT" ]; then sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" .env; fi
if [ -n "$DB_DATABASE" ]; then sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" .env; fi
if [ -n "$DB_USERNAME" ]; then sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env; fi
if [ -n "$DB_PASSWORD" ]; then sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env; fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --ansi
fi

php artisan storage:link --ansi || true
php artisan migrate --force --ansi

exec "$@"
