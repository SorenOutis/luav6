<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_nodes', function (Blueprint $table) {
            // Minimum exam score (0..100) required to count the node as passed.
            // Null = use the configured default (config('gamification.map_node_default_pass_score')).
            $table->unsignedTinyInteger('pass_score')->nullable()->after('y');
        });
    }

    public function down(): void
    {
        Schema::table('map_nodes', function (Blueprint $table) {
            $table->dropColumn('pass_score');
        });
    }
};
