#!/bin/sh
# Chuẩn bị môi trường mỗi lần container app khởi động rồi chạy Apache.
set -e
cd /var/www/html

# vendor nằm trong Docker volume (nhanh hơn nhiều so với ổ đĩa Windows); lần đầu thì cài thư viện.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

# Apache chạy bằng user www-data nên cần quyền ghi log, cache, session và ảnh tải lên.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs public/storage bootstrap/cache /tmp/laravel-views
chmod -R 777 storage bootstrap/cache public/storage /tmp/laravel-views
chown -R www-data:www-data /tmp/laravel-views

exec apache2-foreground
