FROM php:8.3-fpm

# تثبيت الاعتماديات
RUN apt-get update && apt-get install -y \
    git \
    curl \
    nginx \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت اكستنشنات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# إنشاء مجلد العمل
WORKDIR /var/www

# نسخ ملفات المشروع
COPY . .

# نسخ إعدادات Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# تثبيت الاعتماديات PHP
RUN composer install --no-dev --optimize-autoloader

# إعداد صلاحيات المجلدات
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# إنشاء ملف env وتوليد key (لو مش موجود)
RUN cp .env.example .env && php artisan key:generate

# فتح البورت
EXPOSE 8080

# تشغيل migrations + seeders ثم Nginx و PHP-FPM
CMD sh -c "php artisan migrate:fresh --seed && php-fpm -D && nginx -g 'daemon off;'"
