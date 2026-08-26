# --- Frontend assets ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# --- PHP app ---
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        git unzip libzip-dev libpng-dev icu-dev oniguruma-dev sqlite sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql zip gd intl bcmath \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build public/build

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs database \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
