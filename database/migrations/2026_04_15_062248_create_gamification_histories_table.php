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
        Schema::create('gamification_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('season_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount_xp', 10, 2)->default(0);
            $table->decimal('amount_points', 10, 2)->default(0);
            $table->string('reason'); // e.g., "Exam Submission", "Section Reward", "Admin Adjustment"
            $table->string('description')->nullable(); // e.g., "Earned 50 XP for Exam: Midterm Part 1"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gamification_histories');
    }
};
