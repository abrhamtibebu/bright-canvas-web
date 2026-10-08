FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        libpq-dev \
        unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x scripts/start.sh \
    && cp docker/nginx.conf /etc/nginx/sites-available/default

EXPOSE 8080

CMD ["scripts/start.sh"]
