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
        Schema::table('ai_question_drafts', function (Blueprint $table) {
            // Per-draft provider override chosen in the generator UI.
            // Null = use the platform default from Platform Settings.
            $table->string('provider')->nullable()->after('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_question_drafts', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
