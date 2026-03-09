<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['badge_user', 'reward_user', 'course_user', 'assignment_user'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();

                // If it was a simple pivot with a unique constraint on [user_id, item_id], we may want to allow the same item in different seasons.
                // However, let's keep it simple for now and just add the column.
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['badge_user', 'reward_user', 'course_user', 'assignment_user'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('season_id');
            });
        }
    }
};
