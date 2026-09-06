#!/bin/sh
set -eu

if [ -z "${BASIC_AUTH_USERNAME:-}" ] || [ -z "${BASIC_AUTH_PASSWORD:-}" ]; then
    echo "BASIC_AUTH_USERNAME and BASIC_AUTH_PASSWORD must be set in production." >&2
    exit 1
fi

mkdir -p /run/book-keeper
export BASIC_AUTH_USERNAME BASIC_AUTH_PASSWORD
php -r '
    $username = getenv("BASIC_AUTH_USERNAME");
    $password = getenv("BASIC_AUTH_PASSWORD");

    if (!preg_match("/^[A-Za-z0-9._@-]+$/", $username)) {
        fwrite(STDERR, "BASIC_AUTH_USERNAME may contain only letters, numbers, dots, underscores, @, and hyphens.\\n");
        exit(1);
    }

    echo $username, ":", password_hash($password, PASSWORD_BCRYPT), PHP_EOL;
' > /run/book-keeper/.htpasswd
chown www-data:www-data /run/book-keeper/.htpasswd
chmod 600 /run/book-keeper/.htpasswd
unset BASIC_AUTH_PASSWORD

printf '%s\n' \
    '<Location />' \
    '    AuthType Basic' \
    '    AuthName "Restricted Access"' \
    '    AuthBasicProvider file' \
    '    AuthUserFile /run/book-keeper/.htpasswd' \
    '    Require valid-user' \
    '</Location>' \
    > /etc/apache2/conf-available/basic-auth.conf
a2enconf basic-auth >/dev/null

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
