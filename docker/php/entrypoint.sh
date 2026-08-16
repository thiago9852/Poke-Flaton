#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f public/assets/manifest.json ]; then
    php bin/console importmap:install || true
fi

php bin/console doctrine:migrations:migrate --no-interaction || true

exec "$@"
