#!/bin/sh

# Run migrations
php artisan migrate --force

# Clear ALL caches first to avoid stale config
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Re-cache fresh
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Log Groq key presence to confirm env is loaded
php -r "echo 'GROQ_KEY_SET: ' . (empty(getenv('GROQ_API_KEY')) ? 'NO' : 'YES') . PHP_EOL;"

# Start PHP-FPM in background
php-fpm -D

# Start nginx in foreground
nginx -g 'daemon off;'