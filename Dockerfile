# syntax=docker/dockerfile:1.4

# ── Base runtime: FrankenPHP (Caddy + PHP 8.5 worker) ───────────────────
# dunglas/frankenphp ships the `frankenphp` binary (Caddy with the PHP
# worker SAPI) plus the `install-php-extensions` helper. Laravel Octane uses
# this binary as its `frankenphp` server (`--server=frankenphp`), so no
# separate web server or RoadRunner binary needs to be fetched.
FROM dunglas/frankenphp:1.12-php8.5 AS base

WORKDIR /app

# The FrankenPHP image does not ship Composer, so copy it in for the install
# stages.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP extensions required by the production dependencies (Filament→intl,
# PHPWord→gd), Octane's FrankenPHP server (pcntl/opcache), the optional
# Postgres driver, and Redis (Horizon's queue backend). `curl` is used by the
# container HEALTHCHECK; `supervisor` manages the persistent AI queue worker
# processes when the legacy `queue` role is used instead of Horizon.
# `memory_limit` is capped so a long-lived FrankenPHP worker that leaks is
# killed by Octane's max-requests recycling instead of OOM-ing the container.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl supervisor \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        redis \
        sockets \
        zip \
    && printf 'memory_limit = 128M\n' > /usr/local/etc/php/conf.d/zz-memory-limit.ini \
    && rm -rf /var/lib/apt/lists/*

# ── Composer dependencies ────────────────────────────────────────────────
# `--no-scripts` keeps the post-autoload-dump scripts from booting the app
# before autoload is fully assembled; package discovery re-runs on deploy via
# `php artisan package:discover`. `--optimize-autoloader` is NOT
# classmap-authoritative so that first-party `App\` classes (added in later
# stages where app/ is present) stay reachable via PSR-4.
FROM base AS vendor
WORKDIR /app
ARG QUEUE_CONNECTION=redis
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --optimize-autoloader --no-scripts

# Laravel Horizon is intentionally NOT in the repo's composer.json: it is
# installed only into Redis-backed Docker images, so local/non-Docker and
# non-Redis environments never load it. `composer require --no-update` changes
# only this disposable build stage; the repository manifests remain untouched.
# The follow-up update supports --no-dev, preventing development dependencies
# from being pulled back into the production image.
RUN if [ "$QUEUE_CONNECTION" = "redis" ]; then \
        composer require laravel/horizon:^5.36 --no-update \
            --no-interaction --no-progress --no-scripts \
        && composer update laravel/horizon --with-all-dependencies --no-dev \
            --no-interaction --no-progress --prefer-dist \
            --no-scripts --optimize-autoloader; \
    else \
        echo "Skipping Laravel Horizon (QUEUE_CONNECTION=$QUEUE_CONNECTION)"; \
    fi

# ── Wayfinder routes ─────────────────────────────────────────────────────
# resources/js/routes_temp is gitignored, so a fresh clone can't build the
# frontend without regenerating it. Generate it here on a bootable PHP image.
FROM base AS routes
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY artisan .env.example ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs \
    && touch database/database.sqlite \
    && cp .env.example .env \
    && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && php artisan wayfinder:generate --path=resources/js/routes_temp

# ── Frontend assets ──────────────────────────────────────────────────────
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

# ── Final application image ──────────────────────────────────────────────
# All roles (octane web server, queue worker, scheduler, migrations) share
# this image; the container entrypoint (start.sh) dispatches on CONTAINER_ROLE.
FROM base AS app
WORKDIR /app

# Ensure build steps below (chown of runtime dirs) run as root even if a later
# base revision changes its default user; the final USER www-data is set below.
USER root

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Octane creates public/frankenphp-worker.php at server start if it's missing.
# Bake it in so the production container can run with a read-only public/ dir.
# Copied from the vendor build stage (the repo's vendor/ is gitignored/excluded).
COPY --from=vendor /app/vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php ./public/frankenphp-worker.php

# Package discovery cache (bootstrap/cache/packages.php + services.php) lets
# the image-only Horizon provider register without running Composer at runtime.
# Current Horizon releases serve dashboard assets directly from their `dist`
# directory, so there is intentionally no public/vendor/horizon copy here.
COPY --from=routes /app/bootstrap/cache ./bootstrap/cache

# Create the Laravel runtime directories, the public storage symlink, and make
# everything Laravel writes to owned by the non-root www-data user.
RUN set -eux; \
    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache; \
    touch database/database.sqlite; \
    ln -sfn storage/app/public public/storage; \
    chown -R www-data:www-data storage bootstrap/cache database; \
    chmod +x start.sh

# Keep Caddy/FrankenPHP administrative data in the tmpfs-mounted /tmp so the
# container can run with a read-only root filesystem.
ENV XDG_DATA_HOME=/tmp \
    XDG_CONFIG_HOME=/tmp \
    OCTANE_SERVER=frankenphp

USER www-data

# EXPOSE is cosmetic — the host proxy routes traffic to $PORT (default 8000).
EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=5 \
    CMD curl -f --silent http://127.0.0.1:8000/up || exit 1

# Container entrypoint: dispatch on CONTAINER_ROLE (octane|queue|scheduler).
CMD ["sh", "start.sh"]
