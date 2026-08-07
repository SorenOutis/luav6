# Production Deployment (Docker Compose)

## Architecture

```
Reverse proxy / Load Balancer (TLS) → :8000 → Octane (FrankenPHP)
                                                        ↑ shared Postgres "db"
                       queue worker (ai queue) ────────┤
                       scheduler ───────────────────────┤
                              Cloudflare R2 (files/Mail)
```

- **One image, many roles.** All runtime services (`app`, `queue`,
  `scheduler`) are built from the same Docker image and differ only by the
  `CONTAINER_ROLE` env var, which `start.sh` (the container entrypoint)
  dispatches on:
  - `octane` — Laravel Octane on **FrankenPHP** (`dunglas/frankenphp:1.12-php8.5`),
    the foreground web process.
  - `queue` — the persistent **AI queue worker** (`queue:work --queue=ai`),
    self-restarting and recycled hourly.
  - `scheduler` — Laravel's scheduler (`schedule:work`).
  - `migrate` — runs `php artisan migrate --force` once, then exits (used as a
    one-off job, not on container start).
- **Postgres** holds sessions, cache, and the database queue driver — the web,
  queue, and scheduler containers share state without Redis.
- Port `8000` binds only to loopback; your reverse proxy terminates TLS and
  forwards to `localhost:8000` (the app already trusts `X-Forwarded-*` headers).

## Files

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage: PHP extensions → composer deps → wayfinder routes → frontend build → runtime (non-root `www-data`, read-only friendly) |
| `start.sh` | Container entrypoint that dispatches on `CONTAINER_ROLE` |
| `docker-compose.yml` | Base/local stack (app, queue, scheduler, db, mailpit) |
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

# Scale queue workers independently of the web server
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml up -d --scale queue=2

# Tear down (keeps the Postgres volume)
docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml down
```

## Notes

- **Migrations are manual** (run via the `migrate` role / `run --rm`), not on
  container start, so you control when schema changes apply.
- The **AI queue worker** consumes the `ai` queue. Tune concurrency with
  `QUEUE_WORKER_PROCESSES` (default `4`) — a whole class submitting essays
  together is graded concurrently. Workers are recycled hourly (`--max-time=3600`)
  to bound memory. The on-demand spawner (`AiQueueWorker`) remains a local-dev
  fallback; a duplicate worker is harmless because the database queue driver
  atomically reserves jobs.
- If an essay submission shows "Reviewing your essay..." forever, first check a
  queue worker is running (`docker compose ... ps`), then confirm `ai_provider`
  is set to `cloudflare` in Platform Settings. Pending jobs are recovered
  automatically once a worker is present (the database driver also re-queues
  reserved jobs after `retry_after`).
- **Secrets live only in `.env.production`** (gitignored) — never in the image.
- The production overlay runs with `read_only: true`, `no-new-privileges`,
  dropped capabilities, `tmpfs /tmp`, and CPU/memory limits. Laravel writes to
  the mounted `app_storage` (storage/) and `bootstrap_cache` volumes.
- For a custom domain, CNAME your app to the load balancer and proxy through
  Cloudflare for CDN/DDoS protection.
