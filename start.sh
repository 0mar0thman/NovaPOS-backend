#!/bin/sh
set -e

echo "⏳ Waiting for database..."
sleep 5

echo "🚀 Running migrations and seeders..."
php artisan migrate:fresh --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=DatabaseSeeder --force

echo "✅ Starting PHP-FPM and Nginx..."
php-fpm -D
nginx -g 'daemon off;'
