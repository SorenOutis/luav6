<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students a teacher has barred from a single exam.
     *
     * An exam targets a whole section (or every student when `section_id` is
     * null); this table subtracts named students from that audience. A block
     * hides the exam in *every* state — draft, scheduled, open, ended or
     * closed — and deliberately leaves the student's submissions, drafts and
     * XP alone, so work they already handed in stays on the teacher's reports.
     */
    public function up(): void
    {
        Schema::create('exam_user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            // One row per student per exam, so re-saving the admin picker is
            // idempotent.
            $table->unique(['exam_id', 'user_id']);

            // Every student-facing exam listing asks "which exams is this
            // student blocked from?", so the lookup is keyed on the student.
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_user_blocks');
    }
};
