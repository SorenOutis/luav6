<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gamification_histories', function (Blueprint $table) {
            $table->foreignId('awarded_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('exams', function (Blueprint $table) {
            // Existing exams stay disabled to avoid granting retroactive bonus
            // XP on top of the score-based XP students already received.
            $table->boolean('xp_rewards_enabled')->default(false)->after('duration_minutes');
            $table->unsignedInteger('completion_xp')->default(10)->after('xp_rewards_enabled');
            $table->unsignedInteger('on_time_xp')->default(5)->after('completion_xp');
            $table->boolean('accuracy_xp_enabled')->default(true)->after('on_time_xp');
        });

        Schema::create('exam_xp_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('completion_xp')->default(0);
            $table->unsignedInteger('accuracy_xp')->default(0);
            $table->unsignedInteger('on_time_xp')->default(0);
            $table->decimal('accuracy_percentage', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('accuracy_finalized_at')->nullable();
            $table->timestamps();

            // The award service can safely be called by the submit request,
            // essay worker, and status poll without granting XP twice.
            $table->unique(['user_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_xp_awards');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['xp_rewards_enabled', 'completion_xp', 'on_time_xp', 'accuracy_xp_enabled']);
        });

        Schema::table('gamification_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_by');
        });
    }
};
