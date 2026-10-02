#!/bin/sh
set -e

# Jalankan migrasi database
echo "Running migrations..."
php artisan migrate --force

# Cache config untuk production
echo "Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Buat storage link
echo "Creating storage link..."
php artisan storage:link || true

# Start supervisor (nginx + php-fpm)
echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
