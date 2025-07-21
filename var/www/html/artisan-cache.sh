#!/bin/bash
set -e

cd /var/www/html

# Clear old caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Build new caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Laravel caches cleared and rebuilt."
