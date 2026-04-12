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
        Schema::create('section_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Migrate existing data from users.section_id to section_user pivot table
        $users = \Illuminate\Support\Facades\DB::table('users')->whereNotNull('section_id')->get();
        foreach ($users as $user) {
            \Illuminate\Support\Facades\DB::table('section_user')->insert([
                'user_id' => $user->id,
                'section_id' => $user->section_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove section_id from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add section_id to users table (this is tricky as it's many-to-many now)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('set null');
        });

        Schema::dropIfExists('section_user');
    }
};
