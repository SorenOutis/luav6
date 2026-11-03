<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_bots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('specialist');
            $table->string('slug', 64);
            $table->string('name', 80);
            $table->string('title')->nullable();
            $table->string('avatar')->nullable();
            $table->text('instructions')->nullable();
            $table->json('policy');
            $table->json('allowed_tools');
            $table->string('model')->nullable();
            $table->unsignedTinyInteger('max_steps')->default(8);
            $table->string('memory_scope', 16)->default('session');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'is_active', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_bots');
    }
};
