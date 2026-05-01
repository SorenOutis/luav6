<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_ai_feedback_runs', function (Blueprint $table): void {
            $table->unsignedInteger('skipped_essays')->default(0)->after('processed_essays');
        });
    }

    public function down(): void
    {
        Schema::table('exam_ai_feedback_runs', function (Blueprint $table): void {
            $table->dropColumn('skipped_essays');
        });
    }
};
