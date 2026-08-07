# Production Deployment (Render + TursoDB)

## Architecture

```
Cloudflare (CDN/DNS) → Render Web Service (Docker) → TursoDB (SQLite)
                                                      Cloudflare R2 (files)
```

- **Render** runs the Docker container: Laravel + RoadRunner + queue worker in one service.
- **TursoDB** is the remote SQLite database (no PostgreSQL or Redis needed).
- **Cloudflare** proxies DNS, caches assets, and provides R2 for file uploads.
- Queue jobs and cache are database/file-backed — no Redis.

## Pre-flight

You need these from Turso:

```
turso db show --url <database-name>
turso db tokens create <database-name>
```

## Render Setup

1. Select **Web Service** (not Static Site)
2. Connect your GitHub repo
3. Keep build method as **Docker** (auto-detected from Dockerfile)
4. Configure:

| Field | Value |
|---|---|
| Name | `lsi` (or your app name) |
| Region | Singapore |
| Instance | Free (512 MB / shared CPU) |
| Port | `8000` |
| Health Check Path | `/up` |

5. Add environment variables from `.env.production.example`:

```
APP_NAME=LSI
APP_ENV=production
APP_KEY=generate-a-unique-key
APP_DEBUG=false
APP_URL=https://your-domain.com
LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=libsql
TURSO_DATABASE_URL=libsql://your-db-name-org.turso.io
TURSO_AUTH_TOKEN=your-turso-auth-token

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=file

FILESYSTEM_DISK=s3
PUBLIC_DISK=s3
AWS_ACCESS_KEY_ID=your-r2-access-key
AWS_SECRET_ACCESS_KEY=your-r2-secret
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-r2-bucket
AWS_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=https://pub-<bucket-id>.r2.dev

> **R2 public bucket**: enable **Public access** on the bucket in the Cloudflare
> dashboard (R2 → bucket → Settings → Public access → Enable). `AWS_URL` must be
> the bucket's public URL — the `https://pub-<id>.r2.dev` URL shown there, or a
> custom domain you attach to the bucket. This is what `Storage::url()` returns
> for avatars, badges, course covers and the school logo.
> `PUBLIC_DISK=s3` redirects the `public` disk (used by all uploads) to R2; the
> old local-disk `public/storage` symlink remains as a fallback for local dev.

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

6. Deploy — Render builds the Docker image and starts the container.

## Notes

- **512 MB is tight for 50+ concurrent users.** Monitor memory usage and consider upgrading to Render's paid tier or switching to a VPS if performance degrades.
- A persistent **AI queue worker** runs inside the same container alongside Octane (see the Dockerfile CMD): `queue:work --queue=ai` in a self-restarting loop, recycled hourly via `--max-time=3600`. All queued jobs (essay grading, AI question/source generation) live on the `ai` queue, so no separate worker service is needed.
- If an essay submission ever shows "Reviewing your essay..." forever, first check that an AI queue worker is running (`ps aux | grep queue`), then confirm `ai_provider` is set to `cloudflare` in Platform Settings. Jobs still sitting in the `jobs` table are recovered automatically once a worker is present — the database driver also re-queues reserved jobs after `retry_after` (90s). Submissions whose job was already marked failed need a manual re-run from the admin's exam-submission AI feedback page (`AiEssayFeedbackProgress` / "Generate AI feedback").
- Database queue driver stores jobs in TursoDB's `jobs` table; migrations are run manually (or by your deploy pipeline), not on container start.
- File cache is ephemeral (reset on deploy), which is fine — cache is disposable.
- For a custom domain, add a CNAME record in Cloudflare pointing to Render's `*.onrender.com` URL. Enable Cloudflare proxy (orange cloud) for CDN/DDoS protection.
