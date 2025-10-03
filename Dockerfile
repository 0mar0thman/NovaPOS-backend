FROM php:8.3-fpm

# تثبيت الاعتماديات
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client

# تنظيف cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت اكستنشنات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# إنشاء مجلد العمل
WORKDIR /var/www

# نسخ ملفات المشروع
COPY . .

# تثبيت الاعتماديات
RUN composer install --no-dev --optimize-autoloader

# إعداد صلاحيات المجلدات
RUN chown -R www-data:www-data /var/www/storage
RUN chown -R www-data:www-data /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage
RUN chmod -R 775 /var/www/bootstrap/cache

# نسخ ملف env
COPY .env.example .env

# توليد key
RUN php artisan key:generate

EXPOSE 9000
CMD ["php-fpm"]
