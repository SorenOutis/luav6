#!/bin/sh
# ── Container entrypoint ───────────────────────────────────────────────
# A single image serves every runtime role. The role is resolved from the
# CONTAINER_ROLE environment variable (or the first CLI argument) so one
# compose stack can run the Octane/FrankenPHP web server, the AI queue
# worker, and the scheduler as separate, independently restarted processes.
set -e

ROLE="${CONTAINER_ROLE:-${1:-octane}}"

# The runtime user is non-root, so make sure Laravel's writable directories
# actually exist before any artisan command runs.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
touch database/database.sqlite 2>/dev/null || true

case "$ROLE" in
    octane|web|server|app)
        echo "[start] role=octane — warming caches and starting FrankenPHP..."
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

        # exec so Octane becomes PID 1 and receives SIGTERM cleanly on deploy.
        exec php artisan octane:start \
            --server=frankenphp \
            --host=0.0.0.0 \
            --port="${PORT:-8000}" \
            --admin-port="${OCTANE_ADMIN_PORT:-2019}" \
            --workers="${OCTANE_WORKERS:-4}" \
            --max-requests="${OCTANE_MAX_REQUESTS:-500}"
        ;;

    queue|worker|queue-worker)
        # Laravel 12 removed the --processes flag on queue:work/queue:listen, so
        # N parallel workers are run under the Supervisor process manager, which
        # restarts them on crash, reaps them gracefully on shutdown, and the
        # hourly --max-time recycle keeps memory bounded. The on-demand spawner
        # (AiQueueWorker) is a local-dev fallback — a duplicate worker is
        # harmless because the database queue driver atomically reserves jobs.
        COUNT="${QUEUE_WORKER_PROCESSES:-4}"
        [ "$COUNT" -lt 1 ] && COUNT=1
        NAME="${QUEUE_NAME:-ai}"
        TRIES="${QUEUE_TRIES:-3}"
        TIMEOUT="${QUEUE_TIMEOUT:-300}"
        MEMORY="${QUEUE_MEMORY:-128}"
        echo "[start] role=queue — starting ${COUNT} AI queue worker process(es) via Supervisor..."

        SUPERVISORD_CONF=/tmp/supervisord-worker.conf
        cat > "$SUPERVISORD_CONF" <<EOF
[supervisord]
nodaemon=true
logfile=/dev/fd/2
logfile_maxbytes=0
logfile_backups=0
loglevel=info
pidfile=/tmp/supervisord.pid
childlogdir=/tmp

[unix_http_server]
file=/tmp/supervisord.sock

[supervisorctl]
serverurl=unix:///tmp/supervisord.sock

[rpcinterface:supervisor]
supervisor.rpcinterface_factory=supervisor.rpcinterface:make_main_rpcinterface

[program:queue-worker]
command=php artisan queue:work --queue=$NAME --sleep=2 --tries=$TRIES --timeout=$TIMEOUT --memory=$MEMORY --max-time=3600
directory=/app
user=www-data
process_name=%(program_name)s_%(process_num)02d
numprocs=$COUNT
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

        # exec so Supervisor becomes PID 1 and forwards SIGTERM to the workers,
        # shutting them down gracefully on deploy.
        exec supervisord -n -c "$SUPERVISORD_CONF"
        ;;

    horizon)
        # Laravel Horizon supervises the queue workers itself — no Supervisor
        # setup needed; the process stays in the foreground as PID 1 and
        # gracefully stops its workers on SIGTERM.
        echo "[start] role=horizon — starting Laravel Horizon (Redis queue supervisor)..."
        exec php artisan horizon
        ;;

    scheduler|schedule|cron)
        echo "[start] role=scheduler — starting the Laravel scheduler..."
        exec php artisan schedule:work
        ;;

    migrate|migration)
        echo "[start] role=migrate — running database migrations..."
        exec php artisan migrate --force
        ;;

    *)
        echo "[start] ERROR: unknown CONTAINER_ROLE '${ROLE}'." >&2
        echo "[start] Valid roles: octane | queue | horizon | scheduler | migrate" >&2
        exit 1
        ;;
esac
