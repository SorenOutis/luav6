<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_user', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete()->after('section_id');
        });

        // Backfill existing pivot records with the season from their section
        // Uses a subquery instead of JOIN for SQLite compatibility
        DB::statement('UPDATE section_user SET season_id = (SELECT season_id FROM sections WHERE sections.id = section_user.section_id)');
    }

    public function down(): void
    {
        Schema::table('section_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('season_id');
        });
    }
};
