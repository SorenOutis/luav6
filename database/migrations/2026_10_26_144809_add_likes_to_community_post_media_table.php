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
        Schema::table('community_post_media', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('sort_order');
        });

        Schema::create('community_post_media_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_media_id')->constrained('community_post_media')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_media_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_post_media_likes');

        Schema::table('community_post_media', function (Blueprint $table) {
            $table->dropColumn('likes_count');
        });
    }
};
