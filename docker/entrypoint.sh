#!/bin/sh
set -e

# Aynı imaj iki Railway servisinde çalışıyor; görevi CONTAINER_ROLE belirliyor:
#   web       (varsayılan) — migration, önbellekler, FrankenPHP
#   scheduler — zamanlanmış görevler (planlanan yayınlar, defter görseli temizliği)

if [ -z "$APP_KEY" ]; then
    echo "HATA: APP_KEY tanımlı değil. Railway → Variables'a kalıcı bir APP_KEY ekleyin (bkz. DEPLOYMENT.md)." >&2
    exit 1
fi

php artisan optimize:clear
php artisan optimize

case "${CONTAINER_ROLE:-web}" in
    scheduler)
        exec php artisan schedule:work
        ;;
    web)
        php artisan migrate --force
        if [ "${SEED_DEMO:-false}" = "true" ]; then
            php artisan demo:seed-if-empty
        fi
        php artisan storage:link || true
        exec frankenphp php-server --listen ":${PORT:-8080}" --root /app/public
        ;;
    *)
        echo "HATA: bilinmeyen CONTAINER_ROLE: ${CONTAINER_ROLE}" >&2
        exit 1
        ;;
esac
