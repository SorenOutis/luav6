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
            $table->decimal('duration_seconds', 8, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_music_tracks', function (Blueprint $table) {
            $table->decimal('duration_seconds', 5, 2)->default(30.00)->change();
        });
    }
};
