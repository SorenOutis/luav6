<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which set each student was handed for an exam.
     *
     * The row is written the first time a student opens the exam and is never
     * rotated afterwards, so reloading or resuming always yields the same
     * questions. The insertion order drives the rotation: the Nth student to
     * start gets set N (modulo the number of sets).
     */
    public function up(): void
    {
        Schema::create('exam_set_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_set_id')->constrained('exam_sets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One set per student per exam — resuming must not reshuffle.
            $table->unique(['exam_id', 'user_id']);
            $table->index(['exam_id', 'exam_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_set_assignments');
    }
};
