<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional value of an assignment. Null keeps the legacy display (grades
     * stand on their own); a value lets the student page show what the work
     * is worth and render grades as "earned / possible".
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->decimal('points_possible', 8, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('points_possible');
        });
    }
};
