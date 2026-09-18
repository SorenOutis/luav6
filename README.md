# Luav6

A modern full-stack web application built with **Laravel 12** and **Vue 3**, featuring Inertia.js for seamless component-based development.

## Tech Stack

### Backend
- **Framework**: Laravel 12
- **Server**: Laravel Octane (with FrankenPHP)
- **Authentication**: Laravel Fortify
- **Admin Panel**: Filament
- **AI Integration**: Laravel AI
- **Database**: SQLite (default, configurable)
- **PDF/Document Processing**: PHPWord, PDF Parser
- **Testing**: Pest, PHPUnit

### Frontend
- **Framework**: Vue 3
- **UI Framework**: Inertia.js
- **Styling**: Tailwind CSS 4.3
- **Component Library**: Reka UI
- **Icons**: Lucide Vue Next
- **Animation**: Framer Motion, Motion One, GSAP
- **Build Tool**: Vite
- **Graphics**: Pixi.js
- **Input Utilities**: Vue Input OTP

### Development Tools
- **Package Managers**: npm, Composer, Bun
- **Code Quality**:
  - ESLint for JavaScript/TypeScript
  - Prettier for code formatting
  - Laravel Pint for PHP formatting
  - TypeScript for type safety
- **Monitoring**: Laravel Pail

## Getting Started

### Prerequisites
- PHP 8.2+
- Node.js 18+
- Composer
- npm or Bun

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd luav6
   ```

2. **Run the setup script**
   ```bash
   composer run setup
   ```
   
   This will:
   - Install PHP dependencies
   - Create `.env` file (from `.env.example`)
   - Generate application key
   - Run database migrations
   - Install npm dependencies
   - Build frontend assets

### Development

#### Local Development Server
```bash
composer run dev
```

This starts:
- Laravel development server (port 8000)
- Queue listener
- Vite dev server

#### SSR Development (with Server-Side Rendering)
```bash
composer run dev:ssr
```

This adds:
- Inertia SSR service
- Pail logging

### Building

#### Production Build
```bash
npm run build
npm run build:ssr  # For SSR
```

#### Linting & Formatting

**Check code quality:**
```bash
composer run lint:check
npm run lint:check
npm run format:check
npm run types:check
```

**Auto-fix issues:**
```bash
composer run lint
npm run lint
npm run format
```

## Project Structure

```
luav6/
├── app/              # Laravel application code
├── config/           # Laravel configuration
├── database/         # Migrations and seeders
├── public/           # Public assets
├── resources/        # Vue components and assets
├── routes/           # Application routes
├── storage/          # Logs and file storage
├── tests/            # Test files
├── bootstrap/        # Bootstrap files
├── composer.json     # PHP dependencies
├── package.json      # Node dependencies
├── vite.config.ts    # Vite configuration
├── tsconfig.json     # TypeScript configuration
└── phpunit.xml       # PHPUnit configuration
```

## Key Features

### Admin Panel
Powered by Filament for managing application data and settings.

### Inertia.js Integration
Seamless React-style Vue components with Laravel backend routing.

### Real-time Capabilities
Queue system with Laravel Octane for high-performance async operations.

### Document Handling
PDF parsing and Word document generation with PHPWord.

### AI Integration
Built-in Laravel AI support for intelligent features.

### Modern Frontend Stack
- Component-based Vue 3 development
- Responsive Tailwind CSS styling
- Smooth animations with Framer Motion
- Type-safe development with TypeScript

## Configuration

### Environment Variables
Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
php artisan key:generate
```

Key variables to configure:
- `APP_NAME` - Application name
- `DB_CONNECTION` - Database type (sqlite, mysql, etc.)
- `QUEUE_CONNECTION` - Queue driver
- Database credentials (if not using SQLite)

## Testing

Run the test suite:
```bash
composer run test
```

Run specific tests:
```bash
php artisan test
php artisan test --filter=PagePerformanceTest
```

### `php artisan test` not working?

The suite needs PHP 8.2+, `vendor/`, and an `APP_KEY`. This checks all three
and prints the exact fix for whatever is missing, then runs the tests:

```bash
composer run test:preflight
# or directly:
sh bin/test-preflight.sh
```

Typical first-time setup:
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

Notes:
- Tests do **not** touch your real database. `phpunit.xml` forces
  `DB_CONNECTION=sqlite` with `DB_DATABASE=:memory:`, plus the `array` cache
  and session drivers, so no migration runs against `database/database.sqlite`.
- Required PHP extensions: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`,
  `xml`, `curl`.
- The JS tests are separate: `npm run test:js`.

## Production Deployment (Docker)

The repo ships a `docker-compose.yml` (base/local stack) plus a
`docker-compose.production.yml` overlay. The production stack runs three
services — `app`, `scheduler`, and `db` (Postgres) — all built from the same
image and differentiated by the `CONTAINER_ROLE` environment variable (see
`start.sh`). The `app` service runs Laravel Octane on **FrankenPHP** and the
queue consumer together: **Laravel Horizon** when `QUEUE_CONNECTION=redis`,
otherwise a `queue:work` worker.

1. **Copy the production env template and fill in secrets:**
   ```bash
   cp .env.production.example .env.production
   ```

2. **Run migrations** (once, and again after each deploy):
   ```bash
   docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml \
     run --rm app php artisan migrate --force
   ```

3. **Build and start the stack:**
   ```bash
   docker compose --env-file .env.production -f docker-compose.yml -f docker-compose.production.yml \
     up -d --build
   ```

The web port (`8000`) is exposed only on loopback (`127.0.0.1:8000`); terminate
TLS at your reverse proxy / load balancer and forward to `localhost:8000`.
See `PRODUCTION.md` for the full runbook.


## Project Notes

- **Form Method Analysis**: See `FORM_METHOD_ANALYSIS.md` for form handling documentation
- **Tower Defense**: See `TOWER_DEFENSE.md` for tower defense feature details

## License

MIT

## Contributing

Contributions are welcome! Please follow the coding standards:
- Run `npm run lint` and `npm run format` before committing
- Run `composer run lint` for PHP code
- Ensure all tests pass with `composer run test`

## Support

For issues and questions, please open a GitHub issue in the repository.
