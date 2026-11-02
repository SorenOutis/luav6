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
        if (! Schema::hasTable('community_stories')) {
            Schema::create('community_stories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
                $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('media_url');
                $table->string('media_type', 16); // image, video
                $table->unsignedSmallInteger('duration_seconds')->default(15);
                $table->string('caption', 255)->nullable();
                $table->unsignedInteger('views_count')->default(0);
                $table->timestamp('expires_at')->index();
                $table->timestamps();

                $table->index(['workspace_id', 'expires_at']);
                $table->index(['user_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('community_story_views')) {
            Schema::create('community_story_views', function (Blueprint $table) {
                $table->id();
                $table->foreignId('story_id')->constrained('community_stories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['story_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('community_profile_featured')) {
            Schema::create('community_profile_featured', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('media_url');
                $table->string('media_type', 16); // image, video
                $table->string('thumbnail_url')->nullable();
                $table->string('title', 100)->nullable();
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'sort_order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_profile_featured');
        Schema::dropIfExists('community_story_views');
        Schema::dropIfExists('community_stories');
    }
};
