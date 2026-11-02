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
        if (! Schema::hasColumn('community_comments', 'parent_id')) {
            Schema::table('community_comments', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('post_id')
                    ->constrained('community_comments')
                    ->cascadeOnDelete();

                $table->index(['parent_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('community_comments', 'parent_id')) {
            Schema::table('community_comments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }
};
