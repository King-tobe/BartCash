#!/bin/sh

# Migration Command
php artisan migrate --force

# Cache Laravel config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM in background
php-fpm -D

# Start nginx in foreground
nginx -g 'daemon off;'