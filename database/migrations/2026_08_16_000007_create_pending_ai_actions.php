<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_ai_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained('chat_sessions')->nullOnDelete();
            $table->string('action_type', 64);
            $table->string('title');
            $table->text('summary');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->json('preview');
            $table->text('nonce_ciphertext');
            $table->string('status', 24)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('execution_token')->nullable();
            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['chat_session_id', 'status', 'created_at']);
            $table->index(['workspace_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('ai_action_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pending_ai_action_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 48);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pending_ai_action_id', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_audits');
        Schema::dropIfExists('pending_ai_actions');
    }
};
