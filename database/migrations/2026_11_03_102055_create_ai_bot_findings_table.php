<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->string('domain', 64);
            $table->string('severity', 16)->default('info');
            $table->string('fingerprint', 64);
            $table->string('title', 180);
            $table->text('summary');
            $table->json('evidence');
            $table->text('suggested_action')->nullable();
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('times_observed')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'bot_id', 'fingerprint']);
            $table->index(['workspace_id', 'status']);
            $table->index(['bot_id', 'domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_findings');
    }
};
