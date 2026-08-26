#!/bin/sh
set -e

# SQLite veritabanı dosyası yoksa oluştur (Railway volume /app/database'e bağlıysa kalıcı olur)
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
fi

# APP_KEY yoksa üret (Railway env değişkeninde kalıcı tutulmalı, aşağıya bakın)
if [ -z "$APP_KEY" ]; then
    echo "UYARI: APP_KEY tanımlı değil, geçici bir key üretiliyor. Kalıcı olması için Railway'de APP_KEY env değişkenini elle set edin."
    export APP_KEY=$(php artisan key:generate --show)
fi

php artisan config:clear
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
