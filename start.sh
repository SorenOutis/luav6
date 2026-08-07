#!/bin/sh
# ── Container entrypoint ─────────────────────────────────────────────────
# Starts the persistent AI queue worker alongside the Octane web server.
# Extracted from the Dockerfile CMD into a dedicated script so the runtime
# logic is readable, testable, and editable without touching the image's
# build stages.

# ── AI queue worker (background) ─────────────────────────────────────────
# All queued work in this app is AI work (essay grading, AI question/source
# generation) and lives on the "ai" queue. --processes runs N worker
# processes in parallel so a burst of essay submissions (e.g. a whole class
# submitting together) is graded concurrently instead of one-at-a-time; tune
# via AI_WORKER_PROCESSES (default 4). The while-loop restarts the command
# if it ever crashes, and --max-time=3600 recycles the workers hourly to
# keep memory bounded; the job classes define their own per-job timeouts
# (e.g. 300s for essay grading). The on-demand spawner (AiQueueWorker)
# remains a local-dev fallback — a duplicate worker is harmless because the
# database queue driver atomically reserves jobs.
(
    while true; do
        php artisan queue:work --queue=ai --sleep=2 --max-time=3600 --processes=${AI_WORKER_PROCESSES:-4}
        echo 'AI queue worker exited; restarting in 2s' >&2
        sleep 2
    done
) &

# ── Web server (foreground) ──────────────────────────────────────────────
# exec keeps Octane as PID 1 so it receives SIGTERM gracefully on deploy; if
# it exits, the container stops and the host (Dokploy/Render) restarts it.
# Bind to $PORT (default 8000) so the host proxy can reach the app.
exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=${PORT:-8000} --workers=${OCTANE_WORKERS:-4} --max-requests=100
