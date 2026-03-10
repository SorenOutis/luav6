<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exam_parts', function (Blueprint $table) {
            $table->json('questions')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('exam_parts', function (Blueprint $table) {
            $table->dropColumn('questions');
        });
    }
};
