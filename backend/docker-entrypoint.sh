#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

# `php artisan serve` ne transmet qu'une whitelist de variables d'environnement
# au serveur PHP embarqué : le process web ne lit que le fichier .env. En
# production (pas de .env monté), on y matérialise donc l'environnement réel.
if [ "${APP_ENV:-local}" = "production" ]; then
    env | grep -E '^(APP_|DB_|REDIS_|CACHE_|SESSION_|QUEUE_|LOG_|MISTRAL_|RESEND_|FRONTEND_|OPENWEATHER_|WEATHER_|TAN_)' \
        | while IFS='=' read -r key value; do
            printf '%s="%s"\n' "$key" "$value"
        done > .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force --ansi
fi

until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);"; do
    echo "En attente de la base de données ${DB_HOST}:${DB_PORT}..."
    sleep 2
done

php artisan migrate --force --ansi

exec "$@"
