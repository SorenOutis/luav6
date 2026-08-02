# ── PHP runtime base (glibc — same platform as production/CI) ──────────
# turso/libsql bundles a glibc-built liblibsql.so loaded via FFI, so any PHP
# that boots the app (wayfinder route generation, the app itself) must be
# glibc-based. The app stage and the route-generation stage both build on this.
FROM php:8.4-cli-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev libxml2-dev \
        libpng-dev libjpeg-dev libfreetype6-dev libffi-dev pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath dom intl mbstring opcache xml xmlwriter zip gd sockets ffi \
    && { echo 'ffi.enable = true'; } > /usr/local/etc/php/conf.d/ffi.ini \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

# ── Composer dependencies ──────────────────────────────────────────────
# composer:2.10 ships a minimal Alpine PHP without intl/gd/sockets/ffi, but the
# production dependencies hard-require them (Filament->intl, phpword->gd,
# RoadRunner goridge->sockets, turso/libsql->ffi). Install them so composer's
# platform check passes. `composer install` only writes the autoloader — it
# never executes the app, so the Alpine/musl image is fine here.
FROM composer:2.10 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN apk add --no-cache --virtual .build-deps \
        autoconf gcc g++ make musl-dev \
        icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libffi-dev pkgconf \
    && docker-php-source extract \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl gd sockets ffi \
    && docker-php-source delete
# Keep composer install separate so dependency-lock changes don't force the
# ~4 min extension recompilation above to re-run.
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
    && apk del .build-deps

# ── Wayfinder routes ───────────────────────────────────────────────────
# resources/js/routes_temp is gitignored, so a fresh clone can't build the
# frontend without regenerating it. Generate here on glibc PHP (the Alpine
# composer image cannot boot the app: turso/libsql needs the glibc loader).
FROM php-base AS routes
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
# composer.json is required by PackageManifest so that the broken vendor
# turso/libsql-laravel provider stays excluded via extra.laravel.dont-discover.
COPY composer.json composer.lock ./
COPY artisan .env.example ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
# storage/ and the sqlite file are gitignored, so create what the app boot needs.
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs \
    && touch database/database.sqlite \
    && cp .env.example .env \
    && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env \
    && php artisan key:generate --force \
    && php artisan wayfinder:generate --path=resources/js/routes_temp

# ── Frontend assets ────────────────────────────────────────────────────
FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY --from=routes /app/resources/js/routes_temp ./resources/js/routes_temp
COPY bin ./bin
COPY routes ./routes
COPY app ./app
COPY artisan composer.json composer.lock vite.config.ts tsconfig.json components.json .prettierignore .prettierrc ./
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
RUN npm run build

# ── Final app image ────────────────────────────────────────────────────
FROM php-base AS app
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
