#!/bin/sh
set -e

# Aynı imaj iki Railway servisinde çalışıyor; görevi CONTAINER_ROLE belirliyor:
#   web       (varsayılan) — migration, önbellekler, FrankenPHP; RUN_SCHEDULER=true (varsayılan) ise
#             zamanlanmış görevler de arka planda burada
#   scheduler — sadece zamanlanmış görevler (ileride ayrı servis istenirse; planlanan yayınlar,
#             defter görseli temizliği)

if [ -z "$APP_KEY" ]; then
    echo "HATA: APP_KEY tanımlı değil. Railway → Variables'a kalıcı bir APP_KEY ekleyin (bkz. DEPLOYMENT.md)." >&2
    exit 1
fi

# optimize:clear burada YOK: içindeki cache:clear CACHE_STORE=database iken veritabanına gidiyor,
# ilk açılışta `cache` tablosu henüz yokken container'ı düşürüyordu. Yeni container'da temizlenecek
# önbellek de yok (bootstrap/cache imaja girmiyor, .dockerignore). optimize veritabanına dokunmuyor.
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
        # Tek replica'lı demo: zamanlayıcı ayrı servis yerine bu container'da arka planda.
        # Ayrı bir scheduler servisi kurulursa web'de RUN_SCHEDULER=false yapılmalı (görevler iki kez çalışmasın).
        if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
            (while true; do php artisan schedule:work; echo "schedule:work durdu, 5 sn sonra yeniden başlıyor" >&2; sleep 5; done) &
        fi
        exec frankenphp php-server --listen ":${PORT:-8080}" --root /app/public
        ;;
    *)
        echo "HATA: bilinmeyen CONTAINER_ROLE: ${CONTAINER_ROLE}" >&2
        exit 1
        ;;
esac
