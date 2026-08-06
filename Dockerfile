# ── PHP runtime base (glibc — same platform as production/CI) ──────────
# turso/libsql bundles a glibc-built liblibsql.so loaded via FFI, so any PHP
# that boots the app (wayfinder route generation, the app itself) must be
# glibc-based. The app stage and the route-generation stage both build on this.
FROM php:8.4-cli-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev libxml2-dev \
        libpng-dev libjpeg-dev libfreetype6-dev libffi-dev pkg-config \
        libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath dom intl mbstring opcache xml xmlwriter zip gd sockets ffi pcntl pgsql pdo_pgsql \
    && { echo 'ffi.enable = true'; } > /usr/local/etc/php/conf.d/ffi.ini \
    # PHP CLI default memory_limit is -1 (unlimited). RoadRunner workers are
    # long-lived CLI processes, so a single worker leaking memory can OOM the
    # whole 512MB container. Cap it so the worker is killed and recycled by
    # RoadRunner's supervisor instead.
    && { echo 'memory_limit = 128M'; } > /usr/local/etc/php/conf.d/memory-limit.ini \
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
# Copy enough of the app so octane:install can boot Laravel and download the
# RoadRunner binary. We copy the full app AFTER this step so our custom .rr.yaml
# (with production-appropriate worker/memory limits) overwrites the generated one.
COPY artisan .env.example composer.json composer.lock ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && touch database/database.sqlite \
    && cp .env.example .env \
    && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env \
    && php artisan key:generate --force \
    # v2024.1.0 publishes binaries as a .tar.gz archive (the bare
    # roadrunner-linux-amd64 asset does not exist), so download, extract, and
    # move the rr binary into place.
    && curl -sL -o rr.tar.gz https://github.com/roadrunner-server/roadrunner/releases/download/v2024.1.0/roadrunner-2024.1.0-linux-amd64.tar.gz \
    && tar -xzf rr.tar.gz \
    && mv roadrunner-2024.1.0-linux-amd64/rr rr \
    && rm -rf rr.tar.gz roadrunner-2024.1.0-linux-amd64 \
    && chmod +x rr \
    && ./rr --version
# Now copy the full app — this brings in our custom .rr.yaml, public/, resources/, etc.
COPY . .
COPY --from=frontend /app/public/build ./public/build
# The app stage already copied vendor + octane:install binary above — finish setup.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && touch database/database.sqlite \
    && ln -s ../storage/app/public public/storage \
    && chown www-data:www-data rr .rr.yaml \
    && chown -R www-data:www-data storage bootstrap/cache database

# EXPOSE is cosmetic only — Render ignores it and routes traffic to the
# container on the $PORT env var (default 10000). Bind Octane to $PORT so the
# proxy can reach the app (the local docker-compose maps 8000:8000 explicitly).
EXPOSE 8000
USER www-data
# Start a persistent AI queue worker alongside Octane. All queued work in this
# app is AI work (essay grading, AI question/source generation) and lives on
# the "ai" queue, so one --queue=ai worker is sufficient. The while-loop
# restarts the worker if it ever crashes, and --max-time=3600 recycles it
# hourly to keep memory bounded; the job classes define their own per-job
# timeouts (e.g. 300s for essay grading). The on-demand spawner
# (AiQueueWorker) remains as a local-dev fallback — a duplicate worker is
# harmless because the database queue driver atomically reserves jobs.
# Octane runs in the foreground; if it exits, the container stops and the
# host (Dokploy/Render) restarts it.
CMD ["sh", "-c", "( while true; do php artisan queue:work --queue=ai --sleep=2 --max-time=3600; echo 'AI queue worker exited; restarting in 2s'; sleep 2; done ) & exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=${PORT:-8000} --workers=1 --max-requests=100"]
