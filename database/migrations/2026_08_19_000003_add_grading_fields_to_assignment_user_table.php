<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->decimal('points', 8, 2)->default(0)->after('grade');
            $table->decimal('xp_earned', 8, 2)->default(0)->after('points');
            $table->text('feedback')->nullable()->after('xp_earned');
            $table->timestamp('graded_at')->nullable()->after('feedback');
            $table->foreignId('graded_by')->nullable()->after('graded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->dropColumn(['points', 'xp_earned', 'feedback', 'graded_at', 'graded_by']);
        });
    }
};
