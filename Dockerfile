FROM composer:2.10 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY bin ./bin
COPY routes ./routes
COPY app ./app
COPY artisan composer.json composer.lock vite.config.ts tsconfig.json components.json .prettierignore .prettierrc ./
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
RUN npm run build

FROM php:8.4-cli-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libonig-dev libxml2-dev \
    && docker-php-ext-install bcmath dom intl mbstring opcache xml xmlwriter zip \
    && { echo 'ffi.enable = true'; } > /usr/local/etc/php/conf.d/ffi.ini \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8000
USER www-data
CMD ["sh", "-c", "php artisan migrate --force && php artisan queue:work --queue=ai,default --tries=3 --sleep=1 & php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000"]
