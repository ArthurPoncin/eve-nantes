#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/api-docs \
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
    env | grep -E '^(APP_|DB_|REDIS_|CACHE_|SESSION_|QUEUE_|LOG_|MISTRAL_|RESEND_|FRONTEND_|OPENWEATHER_|WEATHER_|TAN_|L5_SWAGGER_)' \
        | while IFS='=' read -r key value; do
            printf '%s="%s"\n' "$key" "$value"
        done > .env
fi

# vendor/ vit dans un volume nommé qui persiste entre les déploiements : on
# réinstalle aussi quand composer.lock a changé (sinon une nouvelle dépendance
# manque au runtime et l'app crash-loop — vécu avec l5-swagger).
LOCK_HASH=$(md5sum composer.lock | cut -d' ' -f1)
STAMP_FILE=vendor/.composer-lock-md5
if [ ! -f vendor/autoload.php ] || [ "$(cat "$STAMP_FILE" 2>/dev/null)" != "$LOCK_HASH" ]; then
    composer install --no-interaction --prefer-dist --no-progress
    echo "$LOCK_HASH" > "$STAMP_FILE"
fi

if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force --ansi
fi

until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);"; do
    echo "En attente de la base de données ${DB_HOST}:${DB_PORT}..."
    sleep 2
done

# RUN_MIGRATIONS=false sur les services secondaires (scheduler) : seul le
# backend migre, sinon les deux conteneurs migrent en même temps au démarrage
# et l'un des deux plante sur une création de table concurrente.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --ansi
fi

# Matérialise la spec OpenAPI servie sur /api/documentation. Non bloquant :
# une annotation cassée ne doit pas empêcher l'API de démarrer.
php artisan l5-swagger:generate --ansi || echo "l5-swagger:generate a échoué (doc indisponible)"

# En production, rafraîchit les données dès le déploiement, en arrière-plan :
# le boot ne doit dépendre ni de l'open-data ni d'Overpass. Seul le conteneur
# backend s'en charge (RUN_MIGRATIONS=false sur le scheduler), et le scheduler
# prend le relais ensuite (events quotidien à 4h, venues hebdo le lundi).
if [ "${APP_ENV:-local}" = "production" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    (php artisan events:import; php artisan venues:import) &
fi

exec "$@"
