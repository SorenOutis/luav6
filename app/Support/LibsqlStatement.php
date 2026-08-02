<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Libsql\Blob;
use Libsql\Laravel\Database\LibsqlStatement as BaseLibsqlStatement;
use Libsql\Statement;

/**
 * Laravel 12 compatibility layer for the libsql statement wrapper.
 *
 * The package's `execute()` only routes statements starting with "select"
 * through `query()`. PRAGMA (`pragma foreign_keys`, `pragma table_info`, ...),
 * EXPLAIN and WITH...SELECT statements also return result rows, which makes
 * the underlying FFI `execute()` throw a "Execute returned rows" exception.
 * Route every row-returning statement through `query()` instead.
 */
class LibsqlStatement extends BaseLibsqlStatement
{
    /**
     * The underlying libsql statement.
     */
    protected Statement $statement;

    /**
     * Create a new libsql statement wrapper.
     */
    public function __construct(Statement $statement, string $query)
    {
        $this->statement = $statement;

        parent::__construct($statement, $query);
    }

    /**
     * Execute the statement.
     */
    public function execute(array $parameters = []): bool
    {
        if (empty($parameters)) {
            $parameters = $this->parameterCasting($this->bindings);
        }

        $this->statement->bind($parameters);

        if (preg_match('/^\s*(select|pragma|explain|with)\b/i', $this->query)) {
            $queryRows = $this->statement->query()->fetchArray();
            $this->affectedRows = count($queryRows);
        } else {
            $this->affectedRows = $this->statement->execute();
        }

        return true;
    }

    /**
     * Fetch all rows from the result set.
     *
     * Return rows as stdClass objects (like PDO/SQLite does) instead of raw
     * arrays, so query-builder results behave identically to the sqlite
     * driver. The package only returns objects for FETCH_OBJ, where it
     * returns a single object instead of an array of rows.
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, ...$args): array
    {
        $mode = $mode === \PDO::FETCH_DEFAULT ? \PDO::FETCH_ASSOC : $mode;

        $rows = parent::fetchAll($mode, ...$args);

        // The parent returns a single object (not an array of rows) for
        // FETCH_OBJ; normalize it so the declared array return type holds.
        if ($mode === \PDO::FETCH_OBJ) {
            return [$rows];
        }

        // FETCH_NUM already returns arrays; leave positional access intact.
        if ($mode === \PDO::FETCH_NUM) {
            return $rows;
        }

        return array_map(fn ($row) => (object) $row, $rows);
    }

    /**
     * Cast the given parameters to the types expected by the libsql driver.
     *
     * Replicated from the parent class, which declares this helper private.
     */
    private function parameterCasting(array $parameters): array
    {
        $parameters = collect(array_values($parameters))->map(function ($value) {
            $type = match (true) {
                is_string($value) && (! ctype_print($value) || ! mb_check_encoding($value, 'UTF-8')) => 'blob',
                is_float($value) => 'float',
                is_int($value) => 'integer',
                is_bool($value) => 'boolean',
                $value === null => 'null',
                $value instanceof Carbon => 'datetime',
                is_vector($value) => 'vector',
                default => 'text',
            };

            if ($type === 'blob') {
                $value = new Blob($value);
            }

            if ($type === 'boolean') {
                $value = (int) $value;
            }

            if ($type === 'datetime') {
                $value = $value->toDateTimeString();
            }

            if ($type === 'vector') {
                $value = json_encode($value);
            }

            return $value;
        })->toArray();

        return $parameters;
    }
}
