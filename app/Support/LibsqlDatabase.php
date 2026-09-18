<?php

namespace App\Support;

use Libsql\Laravel\Database\LibsqlDatabase as BaseLibsqlDatabase;
use PDO;
use PDOException;

/**
 * Laravel 12 compatibility layer for the libsql database wrapper.
 *
 * Fixes two incompatibilities with the turso/libsql-laravel v0.2.0 driver:
 *
 *  - Produces {@see LibsqlStatement} wrappers so row-returning statements
 *    (PRAGMA, WITH...SELECT, EXPLAIN) execute correctly.
 *
 *  - Tracks transactions at the SQL level. Laravel starts transactions with
 *    `exec("BEGIN ... TRANSACTION")` and checks `$pdo->inTransaction()`
 *    before rolling back — the package only tracks transactions it started
 *    itself, so Laravel's BEGIN would be invisible and rollback/commit would
 *    be skipped or throw. We mirror Laravel's SQL-level BEGIN/COMMIT/ROLLBACK
 *    so the connection state stays consistent.
 */
class LibsqlDatabase extends BaseLibsqlDatabase
{
    /**
     * The number of SQL-level transactions currently open.
     */
    private int $transactionDepth = 0;

    /**
     * Prepare an SQL statement for execution.
     */
    public function prepare(string $sql): LibsqlStatement
    {
        return new LibsqlStatement(
            $this->db->prepare($sql),
            $sql
        );
    }

    /**
     * Get a driver attribute.
     *
     * Laravel reads these from the PDO object (e.g. the database queue uses
     * them to pick its locking strategy); the libsql wrapper does not
     * implement them.
     */
    public function getAttribute(int $attribute): mixed
    {
        return match ($attribute) {
            PDO::ATTR_DRIVER_NAME => 'libsql',
            PDO::ATTR_SERVER_VERSION => $this->version(),
            default => null,
        };
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     */
    public function exec(string $queryStatement): int
    {
        $statement = $this->prepare($queryStatement);
        $statement->execute();

        if (preg_match('/^\s*begin\b/i', $queryStatement)) {
            $this->transactionDepth++;
        } elseif (preg_match('/^\s*(commit|rollback)\b/i', $queryStatement)) {
            $this->transactionDepth = 0;
        }

        return $statement->rowCount();
    }

    /**
     * Determine if the connection is inside a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * Commit the active transaction.
     */
    public function commit(): bool
    {
        if (! $this->inTransaction()) {
            throw new PDOException('No active transaction');
        }

        $this->prepare('COMMIT')->execute();
        $this->transactionDepth = 0;

        return true;
    }

    /**
     * Roll back the active transaction.
     */
    public function rollBack(): bool
    {
        if (! $this->inTransaction()) {
            throw new PDOException('No active transaction');
        }

        $this->prepare('ROLLBACK')->execute();
        $this->transactionDepth = 0;

        return true;
    }
}
