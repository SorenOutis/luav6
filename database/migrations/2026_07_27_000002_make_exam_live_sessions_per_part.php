<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.4 — per-part exam timer.
 *
 * `exam_live_sessions` was uniquely keyed on (user_id, exam_id), so it could
 * only ever hold one clock per exam. The agreed behaviour is a per-part limit,
 * which requires the key to include exam_part_id, plus a `started_at` column
 * to anchor the countdown server-side.
 *
 * `last_seen_at` tracks liveness (admin monitoring); `started_at` is the
 * authoritative clock start and must never be overwritten by later pings.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stale rows would violate the new unique key (exam_part_id is nullable
        // today, so multiple nulls per exam are possible). These are transient
        // liveness records — safe to clear.
        DB::table('exam_live_sessions')->delete();

        Schema::table('exam_live_sessions', function (Blueprint $table): void {
            $table->dropUnique('exam_live_sessions_user_id_exam_id_unique');
        });

        Schema::table('exam_live_sessions', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->unique(['user_id', 'exam_id', 'exam_part_id'], 'exam_live_sessions_user_exam_part_unique');
        });
    }

    public function down(): void
    {
        DB::table('exam_live_sessions')->delete();

        Schema::table('exam_live_sessions', function (Blueprint $table): void {
            $table->dropUnique('exam_live_sessions_user_exam_part_unique');
            $table->dropColumn('started_at');
        });

        Schema::table('exam_live_sessions', function (Blueprint $table): void {
            $table->unique(['user_id', 'exam_id']);
        });
    }
};
