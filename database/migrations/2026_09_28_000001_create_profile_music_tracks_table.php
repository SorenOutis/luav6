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
        Schema::create('profile_music_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('artist');
            $table->string('audio_path');
            $table->decimal('duration_seconds', 5, 2)->default(30.00);
            $table->string('cover_image_path')->nullable();
            $table->string('source_url')->nullable();
            $table->string('license_name')->nullable();
            $table->text('attribution_text')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_music_track_id')
                ->nullable()
                ->after('cover_photo')
                ->constrained('profile_music_tracks')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_music_track_id');
        });

        Schema::dropIfExists('profile_music_tracks');
    }
};
