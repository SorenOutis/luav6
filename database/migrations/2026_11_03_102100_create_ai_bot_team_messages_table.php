<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_team_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
            $table->string('kind', 16)->default('observation');
            $table->string('severity', 16)->nullable();
            $table->text('body');
            $table->foreignId('finding_id')->nullable()->constrained('ai_bot_findings')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['bot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_team_messages');
    }
};
