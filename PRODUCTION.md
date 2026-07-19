# Production deployment

This is a Laravel/PHP origin. Docker runs it on a server or container platform; Cloudflare proxies the public hostname to that origin. It does not run Laravel inside Cloudflare Workers.

## Preserve SQLite

Never delete `database/database.sqlite` during this migration. Make a timestamped copy and compare hashes:

```powershell
Copy-Item database/database.sqlite database/database.sqlite.backup-YYYYMMDD
Get-FileHash database/database.sqlite -Algorithm SHA256
Get-FileHash database/database.sqlite.backup-YYYYMMDD -Algorithm SHA256
```

## SQLite to PostgreSQL

Use a new, empty PostgreSQL database. PostgreSQL receives a copy; SQLite remains untouched.

1. Start PostgreSQL:

```powershell
$env:POSTGRES_PASSWORD = 'use-a-long-random-password'
docker compose -f docker-compose.production.yml up -d postgres
```

2. Migrate the SQLite data with `pgloader` from a one-shot container. Replace credentials as needed:

```powershell
docker run --rm --network luav6_default `
  -v "${PWD}\database:/data:ro" `
  dimitri/pgloader:latest `
  pgloader /data/database.sqlite postgresql://lsi:YOUR_PASSWORD@postgres:5432/lsi
```

For a managed PostgreSQL service, replace `postgres` with its private hostname and keep the database off the public internet.

3. Rehearse this against a disposable database first. Compare row counts and key users before switching the application.

4. Copy `.env.production.example` to `.env.production`, set real secrets, then run the application. Keep SQLite as the rollback source until verification is complete.

## Start the application

```powershell
docker compose -f docker-compose.production.yml build
docker compose -f docker-compose.production.yml up -d
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan storage:link
docker compose -f docker-compose.production.yml exec app php artisan optimize
```

The `app` service runs RoadRunner. The separate `worker` service processes AI and default queue jobs. Do not spawn workers from web requests in production.

## Cloudflare

Point a proxied Cloudflare DNS record at the Docker host. Expose only the HTTP origin through a reverse proxy such as Caddy, Nginx, or Cloudflare Tunnel. Keep PostgreSQL and Redis private. Use R2/S3 for uploads because container-local storage is not durable.
