#!/bin/sh
set -eu
cd /var/www/html

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache

PORT_VALUE="${PORT:-8080}"
sed "s/LISTEN_PORT/${PORT_VALUE}/" /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/default

php-fpm -D
nginx -g 'daemon off;'
