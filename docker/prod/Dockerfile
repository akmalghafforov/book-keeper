FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/prod/entrypoint.sh /usr/local/bin/book-keeper-entrypoint

RUN chmod +x /usr/local/bin/book-keeper-entrypoint \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage

ENTRYPOINT ["book-keeper-entrypoint"]
CMD ["apache2-foreground"]
