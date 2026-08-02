<?php

namespace App\Support;

use Illuminate\Database\Events\StatementPrepared;
use Libsql\Laravel\Database\LibsqlStatement;
use Libsql\Laravel\LibsqlConnection as BaseLibsqlConnection;
use PDO;

/**
 * Laravel 12 compatibility layer for the turso/libsql-laravel driver.
 *
 * The v0.2.0 package was written against older Laravel releases and breaks on
 * Laravel 12 in two ways:
 *
 *  - `Illuminate\Database\Connection::prepared()` is type-hinted as
 *    `prepared(PDOStatement $statement)`, but the libsql driver returns its own
 *    `LibsqlStatement` (a PDO-compatible duck type that cannot extend
 *    `PDOStatement`). Every select/insert/update/delete would throw a
 *    `TypeError` without this override.
 *
 *  - The package's `LibsqlStatement::fetchAll()` only returns an array for
 *    `FETCH_ASSOC` / `FETCH_NUM` / `FETCH_BOTH`. Laravel's default fetch mode
 *    is `FETCH_OBJ`, which makes `fetchAll()` return a single object and
 *    violate its declared `array` return type. We default to `FETCH_ASSOC`,
 *    which Eloquent hydrates without issue.
 */
class LibsqlConnection extends BaseLibsqlConnection
{
    /**
     * Configure the connection for the libsql driver.
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $this->fetchMode = PDO::FETCH_ASSOC;
    }

    /**
     * Prepare the statement for execution.
     *
     * @param  LibsqlStatement  $statement
     */
    protected function prepared($statement)
    {
        $statement->setFetchMode($this->fetchMode);

        $this->event(new StatementPrepared($this, $statement));

        return $statement;
    }

    /**
     * Get the server version for the connection.
     *
     * Laravel calls `getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION)`, which
     * the libsql driver's `LibsqlDatabase` object does not implement. Use the
     * version reported by the driver instead.
     */
    public function getServerVersion(): string
    {
        return (string) $this->getPdo()->version();
    }
}
