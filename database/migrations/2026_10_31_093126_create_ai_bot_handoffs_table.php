<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('from_bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained('chat_sessions')->nullOnDelete();
            $table->foreignId('parent_handoff_id')->nullable()->constrained('ai_bot_handoffs')->nullOnDelete();
            $table->text('task');
            $table->text('result')->nullable();
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['to_bot_id', 'status']);
            $table->index(['requested_by_user_id', 'status']);
            $table->index(['chat_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_handoffs');
    }
};
