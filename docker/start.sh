#!/bin/sh

# Test R2 connection
php artisan tinker --execute="
try {
    \Storage::disk('r2')->put('test.txt', 'hello');
    echo 'R2 UPLOAD SUCCESS';
} catch (\Exception \$e) {
    echo 'R2 ERROR: ' . \$e->getMessage();
}
"

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