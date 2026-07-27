<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.0.7 — surface AI grading failures instead of silently scoring zero.
 *
 * AIService returns score 0.0 when a provider call fails (rate limit, timeout,
 * malformed JSON), which is indistinguishable from a genuinely zero-scoring
 * essay. Students may already have received silent zeros. This flag lets the
 * teacher see and re-run them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->boolean('grading_failed')->default(false)->after('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->dropColumn('grading_failed');
        });
    }
};
