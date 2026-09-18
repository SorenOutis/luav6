#!/bin/sh
# ── Test preflight ────────────────────────────────────────────────────────
# `php artisan test` fails with an unhelpful error when the environment is not
# set up (missing vendor/, missing APP_KEY, missing .env). This checks the
# prerequisites first and prints the exact command to fix each one.
#
# Usage:  sh bin/test-preflight.sh        (or: composer test-setup)
set -e

FAIL=0

say()  { printf '%s\n' "$1"; }
ok()   { printf '  [ok]   %s\n' "$1"; }
bad()  { printf '  [FAIL] %s\n' "$1"; FAIL=1; }

say ''
say 'Checking test prerequisites...'
say ''

# ── 1. PHP ────────────────────────────────────────────────────────────────
if command -v php >/dev/null 2>&1; then
    ok "php $(php -r 'echo PHP_VERSION;')"

    # composer.json requires ^8.2
    php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || \
        bad "PHP 8.2+ required (composer.json: \"php\": \"^8.2\")"

    # Extensions Laravel + this app's test suite need.
    for ext in pdo_sqlite mbstring openssl tokenizer xml curl; do
        php -m | grep -qi "^${ext}$" || bad "missing PHP extension: ${ext}"
    done
else
    bad 'php not found in PATH'
    say ''
    say '        macOS:   brew install php'
    say '        Ubuntu:  sudo apt install php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl'
    say '        Windows: https://laravel.com/docs/installation (Herd)'
fi

# ── 2. Composer dependencies ──────────────────────────────────────────────
if [ -f vendor/autoload.php ]; then
    ok 'vendor/ installed'
else
    bad 'vendor/ missing  ->  composer install'
fi

# ── 3. Environment file + app key ─────────────────────────────────────────
# phpunit.xml forces APP_ENV/DB/CACHE/SESSION for tests, but Laravel still
# boots .env first, and encryption needs a key.
if [ -f .env ]; then
    ok '.env present'

    if grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
        ok 'APP_KEY set'
    else
        bad 'APP_KEY empty  ->  php artisan key:generate'
    fi
else
    bad '.env missing  ->  cp .env.example .env && php artisan key:generate'
fi

say ''

if [ "$FAIL" -ne 0 ]; then
    say 'Preflight failed - fix the items above, then run: php artisan test'
    say ''
    exit 1
fi

say 'All prerequisites satisfied. Running: php artisan test'
say ''
exec php artisan test "$@"
