<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-member "the student has opened this feedback" marker. Deliberately
     * NOT mirrored by group propagation: each member acknowledges the shared
     * feedback for themselves, so the "New feedback" flag stays personal.
     */
    public function up(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->timestamp('feedback_seen_at')->nullable()->after('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->dropColumn('feedback_seen_at');
        });
    }
};
