#!/usr/bin/env sh

set -e

cd /var/www/html

# docker-compose mounts vendor as a named volume so local dependencies survive
# container recreation without being committed to the repository.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p storage/app/public \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${APP_ENV:-}" = "production" ]; then
    # Production configuration must come exclusively from the container
    # environment supplied by HostForge. Never create or modify .env here.
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY must be supplied through the production environment." >&2
        exit 1
    fi
else
    # Local development can continue to bootstrap from the example file.
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        php artisan key:generate --force --ansi
    fi
fi

# Never let a cache generated under an older environment take precedence over
# the variables injected into this container at startup.
php artisan config:clear --ansi

if [ ! -L public/storage ]; then
    ln -s ../storage/app/public public/storage
fi

exec "$@"
