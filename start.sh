#!/bin/sh
# ── Container entrypoint ───────────────────────────────────────────────
# A single image serves every runtime role. The role is resolved from the
# CONTAINER_ROLE environment variable (or the first CLI argument). The
# `octane` role runs the FrankenPHP web server, the queue consumer
# (Laravel Horizon on Redis, otherwise queue:work), and — when NIGHTWATCH_TOKEN
# is set — the Nightwatch agent together under Supervisor; the `scheduler` and
# `migrate` roles run as separate, independently restarted processes.
set -e

ROLE="${1:-${CONTAINER_ROLE:-octane}}"

# The runtime user is non-root, so make sure Laravel's writable directories
# actually exist before any artisan command runs.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
touch database/database.sqlite 2>/dev/null || true

# ── Deprecation log channel ──────────────────────────────────────────────
# Production was throwing "EMERGENCY: Unable to create configured logger. ...
# Log [deprecations] is not defined." every time a PHP/library deprecation
# fired (e.g. the guzzlehttp/guzzle "Add-Padding" warning during the pwned-
# password check). Laravel's HandleExceptions forwards those to the channel
# named by LOG_DEPRECATIONS_CHANNEL, and if that name isn't a configured
# channel under logging.channels.* it falls back to the emergency logger.
# Force it to the built-in "null" (NullHandler) channel so deprecations are
# silently discarded instead of crashing the logger. Must be exported BEFORE
# `php artisan config:cache` bakes the value into the cached config.
export LOG_DEPRECATIONS_CHANNEL=null

case "$ROLE" in
    octane|web|server|app)
        echo "[start] role=octane — warming caches and starting FrankenPHP + queue worker..."
        # Refresh package discovery first because production may persist the
        # bootstrap/cache volume across deploys. This adds/removes Horizon's
        # provider when QUEUE_CONNECTION changes between image builds.
        php artisan package:discover --ansi

        # Apply pending schema changes before Octane begins accepting traffic.
        # The shared cache lock prevents multiple replicas from running the
        # same migration batch concurrently during rolling deployments.
        echo "[start] applying pending database migrations..."
        php artisan migrate --force --isolated

        # Rebuild Laravel's caches so the container boots with the exact
        # config/routes/views baked in for this deploy.
        php artisan config:cache
        php artisan view:cache
        # Route caches can only be built if no route uses a Closure; skip it
        # gracefully so bootstrapping never fails on that account.
        php artisan route:cache || echo "[start] warning: route cache skipped" >&2

        # Keep Caddy/FrankenPHP admin + cert data in the tmpfs-mounted /tmp
        # so the container can run with a read-only root filesystem.
        export XDG_DATA_HOME="${XDG_DATA_HOME:-/tmp}"
        export XDG_CONFIG_HOME="${XDG_CONFIG_HOME:-/tmp}"

        # ── Queue-consumer selection ───────────────────────────────────────────
        # The web server and the queue consumer now share this one container.
        #   * QUEUE_CONNECTION=redis  -> Laravel Horizon supervises the workers
        #     (queues come from config/horizon.php; HORIZON_* env tunables).
        #   * any other connection    -> a persistent queue:work worker for the
        #     $QUEUE_NAME queue, restarted by Supervisor. The hourly --max-time
        #     recycle keeps memory bounded.
        # If Redis is configured but this image was built without Horizon
        # (shouldn't happen with Compose, which forwards the build arg), degrade
        # to queue:work rather than crash-looping on Horizon's Redis error.
        QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
        if [ "$QUEUE_CONNECTION" = "redis" ] && [ -d vendor/laravel/horizon ]; then
            QUEUE_PROGRAM=horizon
        else
            QUEUE_PROGRAM=queue-worker
            if [ "$QUEUE_CONNECTION" = "redis" ]; then
                echo "[start] WARNING: QUEUE_CONNECTION=redis but Horizon is not installed in this image; falling back to queue:work." >&2
            fi
        fi

        NAME="${QUEUE_NAME:-ai}"
        TRIES="${QUEUE_TRIES:-3}"
        TIMEOUT="${QUEUE_TIMEOUT:-300}"
        MEMORY="${QUEUE_MEMORY:-128}"

        # ── Supervisor config: run Octane + the queue consumer together ───────
        # Supervisor becomes PID 1 and forwards SIGTERM to both programs
        # (stopasgroup/killasgroup + stopwaitsecs) so the container shuts down
        # gracefully on deploy.
        SUPERVISORD_CONF=/tmp/supervisord-octane.conf
        cat > "$SUPERVISORD_CONF" <<EOF
[supervisord]
nodaemon=true
logfile=/dev/fd/2
logfile_maxbytes=0
logfile_backups=0
loglevel=info
pidfile=/tmp/supervisord-octane.pid
childlogdir=/tmp

[unix_http_server]
file=/tmp/supervisord-octane.sock

[supervisorctl]
serverurl=unix:///tmp/supervisord-octane.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory=supervisor.rpcinterface:make_main_rpcinterface

[program:octane]
command=php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000} --admin-port=${OCTANE_ADMIN_PORT:-2019} --workers=${OCTANE_WORKERS:-4} --max-requests=${OCTANE_MAX_REQUESTS:-500}
directory=/app
user=www-data
autostart=true
autorestart=true
startretries=10
startsecs=1
stopasgroup=true
killasgroup=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
EOF

        if [ "$QUEUE_PROGRAM" = "horizon" ]; then
            cat >> "$SUPERVISORD_CONF" <<EOF

[program:horizon]
command=php artisan horizon
directory=/app
user=www-data
autostart=true
autorestart=true
startretries=10
startsecs=1
stopasgroup=true
killasgroup=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
EOF
        else
            cat >> "$SUPERVISORD_CONF" <<EOF

[program:queue-worker]
command=php artisan queue:work --queue=$NAME --sleep=2 --tries=$TRIES --timeout=$TIMEOUT --memory=$MEMORY --max-time=3600
directory=/app
user=www-data
autostart=true
autorestart=true
startretries=10
startsecs=1
stopasgroup=true
killasgroup=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
EOF
        fi

        # ── Nightwatch agent ────────────────────────────────────────────────────
        # The Nightwatch agent must run alongside the app to collect and forward
        # telemetry to Laravel Nightwatch. Run it under Supervisor as a background
        # process (the "single container per pod" pattern) so it stays alive and is
        # restarted on crash. Only start it when a token is configured, so this
        # container doesn't crash-loop in environments without Nightwatch.
        if [ -n "${NIGHTWATCH_TOKEN:-}" ]; then
            cat >> "$SUPERVISORD_CONF" <<'EOF'

[program:nightwatch]
command=php artisan nightwatch:agent
directory=/app
user=www-data
autostart=true
autorestart=true
startretries=10
startsecs=1
stopasgroup=true
killasgroup=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
EOF
        fi

        echo "[start] queue program=$QUEUE_PROGRAM — web server and queue consumer starting under Supervisor..."
        exec supervisord -n -c "$SUPERVISORD_CONF"
        ;;

    scheduler|schedule|cron)
        echo "[start] role=scheduler — starting the Laravel scheduler..."
        exec php artisan schedule:work
        ;;

    migrate|migration)
        echo "[start] role=migrate — running database migrations..."
        exec php artisan migrate --force --isolated
        ;;

    *)
        echo "[start] ERROR: unknown CONTAINER_ROLE '${ROLE}'." >&2
        echo "[start] Valid roles: octane | scheduler | migrate" >&2
        exit 1
        ;;
esac
