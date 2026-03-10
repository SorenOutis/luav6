<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->string('file_path')->after('grade')->nullable();
            $table->timestamp('submitted_at')->after('file_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'submitted_at']);
        });
    }
};
