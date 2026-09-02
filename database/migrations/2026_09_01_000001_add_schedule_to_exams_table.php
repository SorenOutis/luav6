<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('exam_date');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->index(['starts_at', 'ends_at'], 'exams_schedule_idx');
        });

        // Existing exams keep their old behaviour: an exam starts as soon as it
        // is published (exam_date was the display date) and stays open until a
        // teacher closes it, so ends_at stays NULL. New exams put both values in
        // through the admin form.
        DB::table('exams')
            ->whereNull('starts_at')
            ->update(['starts_at' => DB::raw('exam_date')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_schedule_idx');
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
