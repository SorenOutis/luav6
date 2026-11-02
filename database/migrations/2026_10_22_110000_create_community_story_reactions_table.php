<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('community_story_reactions')) {
            Schema::create('community_story_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction', 32)->default('❤️');
                $table->timestamps();

                $table->index(['story_id', 'user_id']);
                $table->index('story_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_story_reactions');
    }
};
