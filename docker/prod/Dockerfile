FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-req=ext-gd

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-apache

WORKDIR /var/www/html

RUN savedAptMark="$(apt-mark showmanual)" \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ghostscript \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        pkg-config \
        $PHPIZE_DEPS \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_sqlite zip \
    && a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n    Options FollowSymLinks\n</Directory>\n<Directory /var/www/html/storage/app/public>\n    Require all granted\n    Options FollowSymLinks\n</Directory>\n' > /etc/apache2/conf-available/book-keeper.conf \
    && a2enconf book-keeper \
    && apt-mark auto '.*' > /dev/null \
    && apt-mark manual $savedAptMark ghostscript \
    && ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
        | awk '/=> \/usr\// { print $3 }' \
        | xargs -r dpkg-query --search \
        | cut -d: -f1 \
        | sort -u \
        | xargs -r apt-mark manual \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/prod/entrypoint.sh /usr/local/bin/book-keeper-entrypoint

RUN chmod +x /usr/local/bin/book-keeper-entrypoint \
    && rm -f /var/www/html/bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && mkdir -p /var/www/html/bootstrap/cache /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage

ENTRYPOINT ["book-keeper-entrypoint"]
CMD ["apache2-foreground"]
