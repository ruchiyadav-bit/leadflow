FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl unzip icu-dev oniguruma-dev libzip-dev \
    autoconf g++ make linux-headers \
 && docker-php-ext-install pdo_mysql mbstring intl zip bcmath opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

COPY . .

RUN chown -R www-data:www-data /var/www/html/storage

EXPOSE 9000
CMD ["php-fpm"]
