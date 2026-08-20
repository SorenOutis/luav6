<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links each member's `assignment_user` row to the group it belongs to and
     * records who uploaded the shared file. A student can only be in one group
     * per assignment because (assignment_id, user_id) is already unique.
     */
    public function up(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('assignment_id')
                ->constrained('assignment_groups')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')
                ->constrained('users')->nullOnDelete();

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropIndex(['group_id']);
            $table->dropColumn(['group_id', 'submitted_by']);
        });
    }
};
