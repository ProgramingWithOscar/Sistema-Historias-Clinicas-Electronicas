#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Espera a la base de datos (solo si no es sqlite)
if [ "${DB_CONNECTION}" != "sqlite" ]; then
    echo "Esperando a ${DB_HOST}:${DB_PORT}..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);"; do
        sleep 2
    done
else
    touch database/database.sqlite
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
