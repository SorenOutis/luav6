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
        Schema::table('profile_music_tracks', function (Blueprint $table) {
            $table->boolean('is_global')->default(true)->after('is_active')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_music_tracks', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
