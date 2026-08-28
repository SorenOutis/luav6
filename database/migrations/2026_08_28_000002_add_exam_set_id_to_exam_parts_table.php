<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exam parts now belong to a set.
     *
     * The column is nullable and backfilled so every existing exam keeps
     * working: each exam gets a single "Set A" that owns all of its current
     * parts. Exams created before this migration therefore behave exactly as
     * they did — one set, every student gets it.
     */
    public function up(): void
    {
        Schema::table('exam_parts', function (Blueprint $table) {
            $table->foreignId('exam_set_id')
                ->nullable()
                ->after('exam_id')
                ->constrained('exam_sets')
                ->cascadeOnDelete();
        });

        $now = now();

        DB::table('exams')->orderBy('id')->chunkById(200, function ($exams) use ($now): void {
            foreach ($exams as $exam) {
                $setId = DB::table('exam_sets')->insertGetId([
                    'exam_id' => $exam->id,
                    'title' => 'Set A',
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('exam_parts')
                    ->where('exam_id', $exam->id)
                    ->whereNull('exam_set_id')
                    ->update(['exam_set_id' => $setId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_set_id');
        });
    }
};
