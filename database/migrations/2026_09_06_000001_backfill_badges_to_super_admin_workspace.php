<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The BadgeSeeder creates the 100 level badges without an authenticated
     * user, so they land at workspace_id = NULL. Every badge read goes through
     * the BelongsToWorkspace global scope, which hides unscoped rows from workspaces
     * and made the super admin workspace look empty even though the badges existed.
     *
     * This mirrors the section/season backfills: any still-global badge belongs
     * to the default super admin workspace. It also links the bundled
     * level badge artwork shipped under public/images/badges.
     */
    public function up(): void
    {
        $superAdmin = DB::table('users')
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->first();

        if (! $superAdmin) {
            return;
        }

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

        // Any global (unscoped) seed badges belong to the super admin workspace.
        DB::table('badges')
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => $workspaceId,
                'admin_id' => $superAdmin->id,
                'updated_at' => now(),
            ]);

        // Link level 1-100 badges to the bundled SVG artwork. Only touch the
        // default/legacy image paths so a custom uploaded image is preserved.
        $levels = DB::table('badges')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('required_level')
            ->whereBetween('required_level', [1, 100])
            ->where(function ($query) {
                $query->whereNull('image_path')
                    ->orWhere('image_path', 'like', 'badges/level-%.svg')
                    ->orWhere('image_path', 'like', 'images/badges/level-%.svg');
            })
            ->pluck('required_level')
            ->unique();

        foreach ($levels as $level) {
            DB::table('badges')
                ->where('workspace_id', $workspaceId)
                ->where('required_level', (int) $level)
                ->update([
                    'image_path' => sprintf('images/badges/level-%03d.svg', (int) $level),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Intentionally irreversible: we no longer know which badges were
     * originally platform-global vs. intentionally unscoped.
     */
    public function down(): void
    {
        //
    }
};
