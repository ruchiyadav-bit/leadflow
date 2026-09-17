# Render deploy image: runs nginx + php-fpm together in one container,
# and auto-runs DB migrations/seed on startup (no shell access on free plan).
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git curl unzip icu-dev oniguruma-dev libzip-dev ca-certificates \
    autoconf g++ make linux-headers nginx supervisor gettext \
 && docker-php-ext-install pdo_mysql mbstring intl zip bcmath opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Form Partners worker (Node + Playwright) runs inside this same container via supervisord.
# Uses Alpine's Chromium (Playwright's bundled browser does not run on Alpine).
RUN apk add --no-cache nodejs npm chromium nss freetype harfbuzz ttf-freefont
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    CHROME_PATH=/usr/bin/chromium-browser \
    WORKER_CONCURRENCY=1

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY worker/package.json worker/
RUN cd worker && npm install --omit=dev --no-audit --no-fund

COPY . .

RUN chown -R www-data:www-data /var/www/html/storage

COPY docker/render/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/render/supervisord.conf /etc/supervisord.conf
COPY docker/render/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
