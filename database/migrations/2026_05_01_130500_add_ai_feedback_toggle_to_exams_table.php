<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->boolean('ai_feedback_enabled')->default(true)->after('status');
            $table->timestamp('ai_feedback_enabled_at')->nullable()->after('ai_feedback_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn(['ai_feedback_enabled', 'ai_feedback_enabled_at']);
        });
    }
};
