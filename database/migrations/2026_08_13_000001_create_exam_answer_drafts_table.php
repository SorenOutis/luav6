<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_answer_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_part_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'exam_id', 'exam_part_id'],
                'exam_answer_drafts_user_exam_part_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answer_drafts');
    }
};
