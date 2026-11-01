<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_routines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->string('name');
            $table->string('cron', 64);
            $table->text('prompt');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_routines');
    }
};
