<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The roster is now written by the app whenever section targeting or
     * section membership changes, so (assignment_id, user_id) must be unique
     * for those idempotent `insertOrIgnore` writes to be race-safe.
     */
    public function up(): void
    {
        $duplicates = DB::table('assignment_user')
            ->select('assignment_id', 'user_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('assignment_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Keep the most complete row (a real submission beats a placeholder).
            $keepId = DB::table('assignment_user')
                ->where('assignment_id', $duplicate->assignment_id)
                ->where('user_id', $duplicate->user_id)
                ->orderByDesc('submitted')
                ->orderByRaw('CASE WHEN graded_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->value('id') ?? $duplicate->keep_id;

            DB::table('assignment_user')
                ->where('assignment_id', $duplicate->assignment_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('assignment_user', function (Blueprint $table) {
            $table->unique(['assignment_id', 'user_id'], 'assignment_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_user', function (Blueprint $table) {
            $table->dropUnique('assignment_user_unique');
        });
    }
};
