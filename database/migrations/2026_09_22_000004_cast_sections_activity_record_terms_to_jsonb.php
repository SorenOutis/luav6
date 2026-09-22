<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres has no equality operator for the plain `json` type, so
     * `SELECT DISTINCT sections.*` fails with "could not identify an equality
     * operator for type json". Filament's SelectFilter on BelongsToMany relations
     * (Assignments, Learning Materials, Users) issues `SELECT DISTINCT sections.*`
     * (RelationshipJoiner adds ->distinct() for the BelongsToMany left join),
     * which 500'd those admin pages in production while SQLite development
     * was unaffected. `jsonb` supports equality and B-tree indexing.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE sections
            ALTER COLUMN activity_record_terms TYPE jsonb
            USING activity_record_terms::jsonb
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE sections
            ALTER COLUMN activity_record_terms TYPE json
            USING activity_record_terms::json
        SQL);
    }
};
