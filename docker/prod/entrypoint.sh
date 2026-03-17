#!/bin/sh
set -eu

mkdir -p \
    /var/www/html/storage/app-db \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

touch /var/www/html/storage/app-db/database.sqlite

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php artisan migrate --force

exec "$@"
