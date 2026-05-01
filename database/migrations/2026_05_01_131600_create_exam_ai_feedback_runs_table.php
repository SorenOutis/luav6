<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_ai_feedback_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued|running|finished|failed
            $table->unsignedInteger('total_essays')->default(0);
            $table->unsignedInteger('processed_essays')->default(0);
            $table->string('current_user_name')->nullable();
            $table->string('current_part_title')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_ai_feedback_runs');
    }
};

