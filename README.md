# Luav6

A modern full-stack web application built with **Laravel 12** and **Vue 3**, featuring Inertia.js for seamless component-based development.

## Tech Stack

### Backend
- **Framework**: Laravel 12
- **Server**: Laravel Octane (with RoadRunner)
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
```

## Production Deployment

1. **Set environment to production:**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Build assets:**
   ```bash
   npm run build
   ```

3. **Run migrations:**
   ```bash
   php artisan migrate --force
   ```

4. **Clear caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Start application with Octane:**
   ```bash
   php artisan octane:start
   ```

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
