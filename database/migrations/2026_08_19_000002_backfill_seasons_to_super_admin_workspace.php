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

        // Get their primary workspace (same resolution as the sections backfill)
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

        // The 2026_08_19_000001 migration moved the legacy "platform global"
        // sections (and their students) into the super admin workspace, but
        // left the seasons those sections reference at workspace_id = NULL.
        //
        // Every season read is workspace-scoped: Season::current() filters by
        // the viewer's resolved workspace, and Season queries go through the
        // BelongsToWorkspace global scope. Students who now resolve to this
        // workspace could no longer see their enrollment season, so the
        // dashboard and /leaderboard both failed to resolve an initial season
        // and rendered an empty leaderboard.
        //
        // Any still-global season is invisible to every user who now belongs
        // to a workspace, so — mirroring the sections backfill — all of them
        // belong to the super admin workspace. Seasons already owned by other
        // workspaces are untouched.
        DB::table('seasons')
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => $workspaceId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: we don't know which seasons were
        // originally platform-global vs. intentionally unscoped.
    }
};
