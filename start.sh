#!/bin/sh
set -e

# لو Railway مديش PORT، استخدم 8080 افتراضيًا
export PORT=${PORT:-8080}

echo "⏳ Waiting for database..."
sleep 5

echo "🚀 Running migrations and seeders..."
php artisan migrate:fresh --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=DatabaseSeeder --force

echo "✅ Starting PHP-FPM and Nginx on port $PORT..."
php-fpm -D
nginx -g 'daemon off;'
