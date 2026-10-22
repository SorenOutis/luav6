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
        if (! Schema::hasColumn('community_comments', 'likes_count')) {
            Schema::table('community_comments', function (Blueprint $table) {
                $table->unsignedInteger('likes_count')->default(0)->after('content');
            });
        }

        if (! Schema::hasTable('community_comment_likes')) {
            Schema::create('community_comment_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comment_id')->constrained('community_comments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['comment_id', 'user_id']);
                $table->index(['comment_id', 'created_at']);
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_comment_likes');

        if (Schema::hasColumn('community_comments', 'likes_count')) {
            Schema::table('community_comments', function (Blueprint $table) {
                $table->dropColumn('likes_count');
            });
        }
    }
};
