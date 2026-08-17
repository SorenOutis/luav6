<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cover the stable cursor order used by bounded student-facing collections.
 *
 * PostgreSQL builds these concurrently so deploying the pagination change does
 * not block writes on already-large history and chat tables.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private array $indexes = [
        'chat_sessions' => [
            'chat_sessions_user_updated_id_idx' => ['user_id', 'updated_at', 'id'],
        ],
        'chat_messages' => [
            'chat_messages_session_id_idx' => ['session_id', 'id'],
        ],
        'gamification_histories' => [
            'gam_hist_user_created_id_idx' => ['user_id', 'created_at', 'id'],
        ],
        'anonymous_messages' => [
            'anonymous_messages_feed_idx' => ['is_approved', 'created_at', 'id'],
        ],
        'exams' => [
            'exams_created_id_idx' => ['created_at', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                $this->createIndex($table, $name, $columns);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! $this->indexExists($table, $name)) {
                    continue;
                }

                if ($this->isPostgres()) {
                    DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$this->quote($name));
                } else {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createIndex(string $table, string $name, array $columns): void
    {
        if ($this->isPostgres()) {
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)',
                $this->quote($name),
                $this->quote($table),
                collect($columns)->map(fn (string $column): string => $this->quote($column))->join(', '),
            ));

            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => strcasecmp((string) ($index['name'] ?? ''), $name) === 0);
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
