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
        # Laravel 12 removed queue:work/queue:listen --processes, so N parallel
        # worker processes are spawned here directly (all AI work — essay
        # grading, question/source generation — lives on the "ai" queue). Each
        # worker self-restarts after a crash and is recycled hourly via
        # --max-time to bound memory. The on-demand spawner (AiQueueWorker) is
        # a local-dev fallback — a duplicate worker is harmless because the
        # database queue driver atomically reserves jobs.
        COUNT="${QUEUE_WORKER_PROCESSES:-4}"
        [ "$COUNT" -lt 1 ] && COUNT=1
        echo "[start] role=queue — starting ${COUNT} AI queue worker process(es)..."

        WORKERS=
        stop() {
            echo "[start] role=queue — shutting down..."
            for pid in $WORKERS; do kill -TERM "$pid" 2>/dev/null || true; done
            exit 0
        }
        trap 'stop' TERM INT

        i=1
        while [ "$i" -le "$COUNT" ]; do
            (
                while true; do
                    php artisan queue:work \
                        --queue="${QUEUE_NAME:-ai}" \
                        --sleep=2 \
                        --tries="${QUEUE_TRIES:-3}" \
                        --timeout="${QUEUE_TIMEOUT:-300}" \
                        --memory="${QUEUE_MEMORY:-128}" \
                        --max-time=3600
                    echo "[start] queue worker exited; restarting in 2s..." >&2
                    sleep 2
                done
            ) &
            WORKERS="$WORKERS $!"
            i=$((i + 1))
        done

        # Workers run forever (rebooting internally), so this never returns.
        wait
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
        echo "[start] Valid roles: octane | queue | scheduler | migrate" >&2
        exit 1
        ;;
esac
