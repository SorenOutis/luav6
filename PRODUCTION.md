# Production Deployment (Docker Compose)

## Architecture

```
Reverse proxy / Load Balancer (TLS) → :8000 → Octane (FrankenPHP)
                                                        ↑ shared Postgres "db"
                       horizon (queue supervisor) ------+-- Redis "redis" (queue)
                       scheduler -----------------------+
                              Cloudflare R2 (files/Mail)
```

- **One image, many roles.** All runtime services (`app`, `horizon`,
  `scheduler`) are built from the same Docker image and differ only by the
  `CONTAINER_ROLE` env var, which `start.sh` (the container entrypoint)
  dispatches on:
  - `octane` — Laravel Octane on **FrankenPHP** (`dunglas/frankenphp:1.12-php8.5`),
    the foreground web process.
  - `horizon` — **Laravel Horizon**, which supervises the queue workers
    (queues `ai` + `default`) and serves the `/horizon` dashboard.
  - `scheduler` — Laravel's scheduler (`schedule:work`).
  - `queue` — legacy fallback: plain Supervisor-managed `queue:work`
    processes (pre-Horizon behavior).
  - `migrate` — runs `php artisan migrate --force` once, then exits (used as a
    one-off job, not on container start).
- **Laravel Horizon is installed into the Docker image only** (the
  `Dockerfile` runs `composer require laravel/horizon` at build time) — it is
  deliberately absent from the repo's `composer.json`, so non-Docker
  environments never load it.
- **Postgres** holds sessions and cache; **Redis** holds the queue (Horizon
  manages it) — AOF persistence keeps queued jobs across restarts.
- Port `8000` binds only to loopback; your reverse proxy terminates TLS and
  forwards to `localhost:8000` (the app already trusts `X-Forwarded-*` headers).

## Files

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage: PHP extensions → composer deps → wayfinder routes → frontend build → runtime (non-root `www-data`, read-only friendly) |
| `start.sh` | Container entrypoint that dispatches on `CONTAINER_ROLE` |
| `docker-compose.yml` | Base/local stack (app, horizon, scheduler, db, redis, mailpit) |
| `docker-compose.production.yml` | Production overlay (hardening + resource limits) |

## Pre-flight

Create `.env.production` from the template and fill in real values (`APP_KEY`,
`DB_*`, R2 and SMTP credentials):

```bash
cp .env.production.example .env.production
```

## Bring the stack up

First run (build the image and provision the database):

```bash
# Validate the merged config
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml config

# Run migrations as a one-off job
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml \
  run --rm app php artisan migrate --force

# Start everything
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml \
  up -d --build
```

> Every command below uses `--env-file .env.production` so the `${DB_*}`
> placeholders in the compose file resolve from that file (which also feeds
> each service through `env_file`).

## Everyday operations

```bash
# (DC = docker compose ... substitute just once for brevity)
# Watch logs
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml logs -f app

# Run artisan inside the web container
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml exec app php artisan tinker

# Rebuild after a deploy
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml up -d --build

# Scale queue workers independently of the web server (Horizon spawns N
# worker processes per supervisor; run exactly ONE horizon container)
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml up -d -e HORIZON_PROCESSES=8 horizon

# Tear down (keeps the Postgres volume)
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml down
```

## Queue status (Laravel Horizon)

Three ways to know whether the queue workers are running after a deploy:

1. **Dashboard** — open `https://your-domain/horizon`. It shows the
   supervisor state, throughput, pending/failed jobs, and queue wait times.
   Access is gated by the `viewHorizon` gate: **super admins only**, via the
   normal app login session.
2. **CLI** — `docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml exec horizon php artisan horizon:status`
   prints `Horizon is running.` (or `...is not running.`).
3. **Container health** — the `horizon` service has a healthcheck based on
   that command, so `docker compose ... ps` shows `(healthy)` /
   `(unhealthy)` next to it.

## Notes

- **Migrations are manual** (run via the `migrate` role / `run --rm`), not on
  container start, so you control when schema changes apply.
- The **queue runs on Redis and is supervised by Laravel Horizon** (queues
  `ai` + `default`). Tune concurrency with `HORIZON_PROCESSES` (default `4`;
  the `QUEUE_*` vars map onto it) — a whole class submitting essays together
  is graded concurrently. Horizon restarts crashed workers, bounds memory
  (`HORIZON_MEMORY`, default `128` MB), and retries failed jobs
  (`HORIZON_TRIES`, default `3`); failures land on the dashboard's Failed
  Jobs tab. The on-demand spawner (`AiQueueWorker`) is skipped automatically
  on the Redis driver — it remains the local-dev fallback for the database
  queue. If `QUEUE_CONNECTION` is not `redis`, the `horizon` container role
  refuses to start Horizon and **falls back to the Supervisor-based `queue`
  role** (logged as a warning; the container healthcheck stays `unhealthy`
  so the misconfiguration is visible in `docker compose ps`). The old
  Supervisor role is also still available directly via `CONTAINER_ROLE=queue`.
- If an essay submission shows "Reviewing your essay..." forever, first check
  Horizon is running (`docker compose ... ps` — the `horizon` service should
  be `(healthy)`), then confirm `ai_provider` is set to `cloudflare` in
  Platform Settings. Pending jobs sit in Redis until a worker picks them up.
- **Secrets live only in `.env.production`** (gitignored) — never in the image.
- The production overlay runs with `read_only: true`, `no-new-privileges`,
  dropped capabilities, `tmpfs /tmp`, and CPU/memory limits. Laravel writes to
  the mounted `app_storage` (storage/) and `bootstrap_cache` volumes.
- For a custom domain, CNAME your app to the load balancer and proxy through
  Cloudflare for CDN/DDoS protection.
