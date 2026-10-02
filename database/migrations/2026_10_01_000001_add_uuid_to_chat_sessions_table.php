<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** PostgreSQL concurrent indexes cannot be created inside a transaction. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('chat_sessions', 'uuid')) {
            Schema::table('chat_sessions', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table('chat_sessions')
            ->whereNull('uuid')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($sessions): void {
                foreach ($sessions as $session) {
                    DB::table('chat_sessions')
                        ->where('id', $session->id)
                        ->update(['uuid' => (string) Str::uuid7()]);
                }
            });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS chat_sessions_uuid_unique ON chat_sessions (uuid)');
        } elseif (! $this->indexExists('chat_sessions_uuid_unique')) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->unique('uuid'));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS chat_sessions_uuid_unique');
        } elseif ($this->indexExists('chat_sessions_uuid_unique')) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropUnique('chat_sessions_uuid_unique'));
        }

        if (Schema::hasColumn('chat_sessions', 'uuid')) {
            Schema::table('chat_sessions', function (Blueprint $table): void {
                $table->dropColumn('uuid');
            });
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(Schema::getIndexes('chat_sessions'))
            ->contains(fn (array $index): bool => strcasecmp((string) ($index['name'] ?? ''), $name) === 0);
    }
};
