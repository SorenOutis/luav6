<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres has no equality operator for the plain `json` type, so
     * `SELECT DISTINCT users.*` fails with "could not identify an equality
     * operator for type json". Filament's AttachAction issues exactly that
     * query (RelationshipJoiner adds ->distinct() for the BelongsToMany
     * left join), which 500'd the admin Sections > Add Students modal in
     * production while SQLite development was unaffected. `jsonb` supports
     * equality, matching the earlier notifications.data cast.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ALTER COLUMN onboarding_tours TYPE jsonb
            USING onboarding_tours::jsonb
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
            ALTER TABLE users
            ALTER COLUMN onboarding_tours TYPE json
            USING onboarding_tours::json
        SQL);
    }
};
