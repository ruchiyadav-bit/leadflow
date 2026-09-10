# Render deploy image: runs nginx + php-fpm together in one container,
# and auto-runs DB migrations/seed on startup (no shell access on free plan).
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl unzip icu-dev oniguruma-dev libzip-dev \
    autoconf g++ make linux-headers nginx supervisor gettext \
 && docker-php-ext-install pdo_mysql mbstring intl zip bcmath opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

RUN chown -R www-data:www-data /var/www/html/storage

COPY docker/render/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/render/supervisord.conf /etc/supervisord.conf
COPY docker/render/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
