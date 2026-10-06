# --- Frontend assets ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
# Tailwind bu klasörü de tarıyor (RichText'in ürettiği HTML'deki sınıflar, bkz. tailwind.config.js).
COPY app/Support app/Support
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# --- PHP app ---
# FrankenPHP: Caddy + PHP tek süreçte (Railway'in Laravel için kullandığı sunucu). Klasik modda
# çalışıyor — her istek ayrı, `php artisan serve` gibi tek iş parçacığına sıkışmıyor.
FROM dunglas/frankenphp:1-php8.3-alpine

RUN apk add --no-cache git unzip \
    && install-php-extensions pdo_mysql pdo_sqlite zip gd intl bcmath opcache pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-evrenkent.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
# Not: --no-dev KULLANILMIYOR — bu demo/gösterim ortamı seeder'lar (fakerphp/faker)
# üzerinden örnek içerik üretiyor, o yüzden dev bağımlılıkları da kuruluyor.
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build public/build

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing \
        storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
