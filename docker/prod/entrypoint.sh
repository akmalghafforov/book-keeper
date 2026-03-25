#!/bin/sh
set -eu

mkdir -p \
    /var/www/html/database \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

rm -rf /var/www/html/public/storage
php artisan storage:link

if [ -f /var/www/html/database/database.sqlite ]; then
    chown www-data:www-data /var/www/html/database /var/www/html/database/database.sqlite
    chmod 775 /var/www/html/database
    chmod 664 /var/www/html/database/database.sqlite
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php artisan migrate --force

exec "$@"
