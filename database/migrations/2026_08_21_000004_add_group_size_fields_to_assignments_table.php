<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teacher-configured group sizes for group activities. Both nullable:
     * null max = unlimited, null min = no advisory minimum. The max is
     * hard-enforced (invite sends + acceptances); the min is advisory only —
     * students are never blocked from submitting (product decision: no
     * deadlocks, they can always submit with or without members).
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_group_size')->nullable()->after('points_possible');
            $table->unsignedTinyInteger('max_group_size')->nullable()->after('min_group_size');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['min_group_size', 'max_group_size']);
        });
    }
};
