<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_live_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('in_progress');
            $table->unsignedSmallInteger('submitted_parts_count')->default(0);
            $table->unsignedSmallInteger('current_part_answered_count')->default(0);
            $table->unsignedSmallInteger('current_part_total_questions')->default(0);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'exam_id']);
            $table->index(['exam_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_live_sessions');
    }
};
