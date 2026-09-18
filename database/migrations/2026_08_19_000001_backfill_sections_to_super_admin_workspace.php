<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the default super admin
        $superAdmin = DB::table('users')
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->first();

        if (! $superAdmin) {
            return;
        }

        // Get their primary workspace
        $workspaceId = $superAdmin->current_workspace_id;

        if (! $workspaceId) {
            $workspaceId = DB::table('workspace_user')
                ->where('user_id', $superAdmin->id)
                ->where('role', 'owner')
                ->orderBy('workspace_id')
                ->value('workspace_id');
        }

        if (! $workspaceId) {
            return;
        }

        // Assign all "platform global" sections (those without a workspace)
        // to the super admin's workspace.
        $affected = DB::table('sections')
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => $workspaceId,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            // Also backfill related records that might have been left orphaned
            // by the section backfill in previous migrations.
            $relations = [
                ['exams', 'section_id', 'sections'],
                ['announcements', 'section_id', 'sections'],
                ['grades', 'section_id', 'sections'],
            ];

            foreach ($relations as [$table, $foreignKey, $parent]) {
                DB::statement(
                    "UPDATE {$table} SET workspace_id = {$workspaceId} WHERE workspace_id IS NULL AND {$foreignKey} IN (SELECT id FROM sections WHERE workspace_id = {$workspaceId})"
                );
            }

            // Backfill workspace memberships for students in these sections
            $studentIds = DB::table('section_user')
                ->whereIn('section_id', function ($query) use ($workspaceId) {
                    $query->select('id')->from('sections')->where('workspace_id', $workspaceId);
                })
                ->pluck('user_id')
                ->unique();

            foreach ($studentIds as $userId) {
                DB::table('workspace_user')->insertOrIgnore([
                    'workspace_id' => $workspaceId,
                    'user_id' => $userId,
                    'role' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('users')
                    ->where('id', $userId)
                    ->whereNull('current_workspace_id')
                    ->update(['current_workspace_id' => $workspaceId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversing this is potentially destructive as we don't know
        // which sections were originally NULL.
    }
};
