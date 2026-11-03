<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bot_memories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('source', 16)->default('manual');
            $table->unsignedTinyInteger('importance')->default(2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bot_id', 'user_id', 'importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bot_memories');
    }
};
