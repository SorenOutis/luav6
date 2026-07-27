<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.4 — late submissions are accepted and flagged, never rejected.
 *
 * A student whose connection drops, or who is still typing when the clock
 * runs out, must not lose their work. The teacher decides what to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->boolean('is_late')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->dropColumn('is_late');
        });
    }
};
