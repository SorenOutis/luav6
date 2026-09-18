<?php

use App\Enums\AssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the exam-style lifecycle (draft / published / closed) to
     * assignments. The legacy `is_active` column stays for now but is
     * superseded: hidden rows were the draft state, everything else was
     * open for work.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('status')->default(AssignmentStatus::Published->value)->index()->after('is_active');
        });

        DB::table('assignments')
            ->where('is_active', false)
            ->update(['status' => AssignmentStatus::Draft->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
