#!/bin/sh
set -e

# لو Railway مديش PORT، استخدم 8080 افتراضيًا
export PORT=${PORT:-8080}

echo "⏳ Waiting for database to be ready..."

# تأكد من الاتصال بالداتابيس قبل الميجريشن
until php -r "try { new PDO(getenv('DB_CONNECTION').':host='.getenv('DB_HOST').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo '✅ Database connected'; exit(0); } catch (Exception \$e) { echo '.'; sleep(3); }"; do
  sleep 3
done

echo ""
echo "🚀 Running migrations..."
php artisan migrate --force || echo "⚠️ Migrations failed, continuing anyway..."

# php artisan db:seed --class=RolePermissionSeeder --force
# php artisan db:seed --class=DatabaseSeeder --force

echo "✅ Starting PHP-FPM and Nginx on port $PORT..."
php-fpm -D
nginx -g 'daemon off;'
