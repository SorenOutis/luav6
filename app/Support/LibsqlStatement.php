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
 *
 * The package's `fetchAll()`/`fetch()` re-bind `$this->bindings` to the
 * underlying statement, but Laravel already bound them (via `bindValue()`)
 * before calling `execute()`. Binding them a second time sends every
 * placeholder's value twice to the driver — the embedded SQLite build
 * silently ignores the duplicates, but remote Turso databases (Hrana
 * protocol) reject the statement with "Number of arguments mismatch:
 * expected N, got 2N". Fetch without re-binding.
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
     *
     * Unlike the parent, the bindings are NOT re-applied here: they were
     * already bound by execute(). Re-binding them sends every placeholder
     * twice to the driver, which remote Turso databases reject.
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, ...$args): array
    {
        $mode = $mode === \PDO::FETCH_DEFAULT ? \PDO::FETCH_ASSOC : $mode;

        $rows = $this->statement->query()->fetchArray();

        // The parent returns a single object (not an array of rows) for
        // FETCH_OBJ; normalize it so the declared array return type holds.
        if ($mode === \PDO::FETCH_OBJ) {
            return [(object) $rows];
        }

        // FETCH_NUM already returns arrays; leave positional access intact.
        if ($mode === \PDO::FETCH_NUM) {
            return array_map('array_values', $rows);
        }

        return array_map(fn ($row) => (object) $row, $rows);
    }

    /**
     * Fetch the next row from the result set.
     *
     * Same as {@see fetchAll()}: bindings were already applied by execute(),
     * so do not re-bind them like the parent does (double-binds break remote
     * Turso databases).
     */
    #[\ReturnTypeWillChange]
    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($mode === \PDO::FETCH_DEFAULT) {
            $mode = $this->mode;
        }

        $rows = $this->statement->query()->fetchArray();

        $row = $rows[$cursorOffset] ?? null;

        // Terminate iteration like the parent: the query is re-executed on
        // every fetch() call, so returning the same row again means there is
        // nothing new left to yield.
        if ($row === null || $this->response === $row) {
            return false;
        }

        $this->response = $row;

        $rowValues = array_values($row);

        // Keep the parent's declared `array|false` return type: the connection
        // always fetches with FETCH_ASSOC, so rows are returned as assoc arrays.
        return match ($mode) {
            \PDO::FETCH_BOTH => array_merge($row, $rowValues),
            \PDO::FETCH_ASSOC, \PDO::FETCH_NAMED, \PDO::FETCH_OBJ => $row,
            \PDO::FETCH_NUM => $rowValues,
            default => throw new \PDOException('Unsupported fetch mode.'),
        };
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
