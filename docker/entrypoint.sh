#!/bin/bash
set -e

# Ensure storage directories exist and are writable
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force || echo "WARNING: Migration failed, continuing startup..."

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
