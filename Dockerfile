# 🐘 الصورة الأساسية
FROM php:8.3-fpm

# 📦 تثبيت الاعتماديات الأساسية
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

# ⚙️ تثبيت إضافات PHP المطلوبة للـ Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# 🎼 تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 📁 مجلد العمل داخل الكونتينر
WORKDIR /var/www

# 📂 نسخ ملفات المشروع
COPY . .

# ⚙️ نسخ إعدادات Nginx المعدلة للـ Railway
COPY nginx.conf /etc/nginx/conf.d/default.conf

# 📦 تثبيت مكتبات المشروع (بدون dev)
RUN composer install --no-dev --optimize-autoloader

# 🛠️ إعداد صلاحيات Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 🔑 إنشاء ملف env وتوليد APP_KEY (لو مش موجود)
RUN cp .env.example .env && php artisan key:generate

# 🚀 نسخ سكريبت التشغيل
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# 🌐 فتح المنفذ الافتراضي (Railway بيستخدم PORT متغير)
EXPOSE 8080
ENV PORT=8080

# 🏁 تشغيل المشروع
CMD ["sh", "/usr/local/bin/start.sh"]
