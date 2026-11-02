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
        Schema::table('community_comments', function (Blueprint $table) {
            $table->foreignId('post_media_id')
                ->nullable()
                ->after('post_id')
                ->constrained('community_post_media')
                ->cascadeOnDelete();

            $table->index(['post_media_id', 'created_at']);
        });

        Schema::table('community_post_media', function (Blueprint $table) {
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_media_id');
        });

        Schema::table('community_post_media', function (Blueprint $table) {
            $table->dropColumn('comments_count');
        });
    }
};
