<?php

namespace App\Providers;

use App\Support\HttpLibsqlDatabase;
use App\Support\LibsqlConnection;
use App\Support\LibsqlDatabase;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "libsql" database driver with Laravel.
 *
 * This is a replacement for `Libsql\Laravel\LibsqlServiceProvider`, which is
 * incompatible with Laravel 12:
 *
 *  - It replaces the global `db.factory` binding with `LibsqlConnectionFactory`,
 *    whose `createConnection()` has no driver guard and unconditionally reads
 *    `$config['host']` — crashing with "Undefined array key host" for *every*
 *    other connection (sqlite, mysql, ...) as soon as the provider is loaded.
 *  - Its connection resolver calls the removed `Connection::createReadPdo()`,
 *    which no longer exists in Laravel 12 and fatals on first use.
 *
 * Instead we register the driver through the standard
 * `DatabaseManager::extend()` mechanism, which only affects the "libsql"
 * driver and leaves every other connection untouched.
 */
class LibsqlServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $resolver = function (array $config, $name) {
            // Re-read the raw connection config: Laravel's
            // DatabaseManager::configuration() parses/strips the "url" key,
            // but LibsqlDatabase expects the original url + password.
            $config = config('database.connections.libsql');
            $config['name'] = $name;

            if (! isset($config['driver'])) {
                $config['driver'] = 'libsql';
            }

            $useHttp = $config['use_http'] ?? false;
            $database = $useHttp
                ? new HttpLibsqlDatabase($config)
                : new LibsqlDatabase($config);

            return new LibsqlConnection(
                $database,
                $config['database'] ?? ':memory:',
                $config['prefix'] ?? '',
                $config
            );
        };

        $this->app->resolving('db', function (DatabaseManager $db) use ($resolver) {
            $db->extend('libsql', $resolver);
        });

        // If the database manager was already resolved before this provider
        // was registered, register the extension immediately instead.
        if ($this->app->resolved('db')) {
            $this->app->make('db')->extend('libsql', $resolver);
        }
    }
}
