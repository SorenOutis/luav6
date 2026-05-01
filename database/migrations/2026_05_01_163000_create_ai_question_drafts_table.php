<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_question_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('source_filename')->nullable();
            $table->longText('source_text')->nullable();
            $table->string('topic')->nullable();
            $table->json('type_counts')->nullable(); // e.g. {"multiple_choice": 5, "true_false": 3, "identification": 2, "essay": 1}
            $table->string('difficulty')->default('medium'); // easy|medium|hard
            $table->string('status')->default('pending'); // pending|running|ready|failed
            $table->json('questions')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_question_drafts');
    }
};
