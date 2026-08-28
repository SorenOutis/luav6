<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One exam can ship as several interchangeable "sets" (Set A, Set B, …).
     *
     * Each set owns its own copy of the exam parts/questions, and students are
     * rotated through the sets in order when they start the exam: the first
     * student gets Set A, the second Set B, and so on, wrapping around once the
     * last set is handed out.
     */
    public function up(): void
    {
        Schema::create('exam_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['exam_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sets');
    }
};
