<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce single-attempt-per-part at the database level.
 *
 * ExamController::submitPart() checks `exists()` before creating, but that
 * check-then-create is a TOCTOU race: two concurrent requests (double-click,
 * retry after a dropped connection) can both pass the check and insert two
 * rows, double-awarding XP. A unique index makes the second insert fail, and
 * the controller turns that failure into the same 409 the guard produces.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Portable de-dup (SQLite + MySQL + Postgres): keep the earliest row
        // per attempt group so the unique index can be created. Duplicates
        // should not exist in practice; if they do, the first submission is
        // the authoritative one.
        $duplicates = DB::table('exam_submissions')
            ->select('user_id', 'exam_id', 'exam_part_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'exam_id', 'exam_part_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('exam_submissions')
                ->where('user_id', $duplicate->user_id)
                ->where('exam_id', $duplicate->exam_id)
                ->where('exam_part_id', $duplicate->exam_part_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->unique(['user_id', 'exam_id', 'exam_part_id'], 'exam_submissions_user_exam_part_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->dropUnique('exam_submissions_user_exam_part_unique');
        });
    }
};
