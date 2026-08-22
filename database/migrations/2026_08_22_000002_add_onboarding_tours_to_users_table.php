<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side record of which onboarding tours a user has finished or
     * skipped, e.g. {"dashboard": "done", "grades": "skipped"}.
     *
     * Completion used to live only in localStorage, so clearing site data,
     * using a private window, or logging in from another device replayed
     * every tour. Persisting it on the account makes "done or skipped" final.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('onboarding_tours')->nullable()->after('blur_leaderboard');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_tours');
        });
    }
};
