<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure every admin workspace has the bundled level 1-100 badge set.
     *
     * The BadgeSeeder creates the 100 level badges without an authenticated
     * user, so legacy rows land at workspace_id = NULL. The BelongsToWorkspace
     * global scope hides unscoped rows, which made workspaces look empty.
     *
     * This mirrors the section/season backfills:
     *  - Legacy rows with an admin_id are returned to that admin's workspace.
     *  - Any remaining global rows belong to the default super admin workspace.
     *  - Every admin workspace is then guaranteed to have its own 1-100 set,
     *    linked to the shipped SVG artwork under public/images/badges.
     */
    public function up(): void
    {
        $admins = DB::table('users')
            ->where('is_admin', true)
            ->orderBy('id')
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        // Resolve each admin's workspace and owner, de-duplicated by workspace.
        $workspaceOwnerIds = [];
        foreach ($admins as $admin) {
            $workspaceId = $this->workspaceIdForAdmin($admin);

            if (! $workspaceId || isset($workspaceOwnerIds[$workspaceId])) {
                continue;
            }

            $ownerId = DB::table('workspace_user')
                ->where('workspace_id', $workspaceId)
                ->where('role', 'owner')
                ->orderBy('user_id')
                ->value('user_id') ?? $admin->id;

            $workspaceOwnerIds[$workspaceId] = (int) $ownerId;
        }

        if ($workspaceOwnerIds === []) {
            return;
        }

        // Put legacy rows back where their original admin's workspace lives.
        foreach ($admins as $admin) {
            $workspaceId = $this->workspaceIdForAdmin($admin);

            if (! $workspaceId) {
                continue;
            }

            DB::table('badges')
                ->whereNull('workspace_id')
                ->where('admin_id', $admin->id)
                ->update([
                    'workspace_id' => $workspaceId,
                    'updated_at' => now(),
                ]);
        }

        // Remaining unscoped seed rows go to the default super admin workspace.
        $superAdmin = $admins->first(fn (object $admin): bool => (bool) $admin->is_super_admin) ?? $admins->first();
        $superWorkspaceId = $this->workspaceIdForAdmin($superAdmin);

        if ($superWorkspaceId) {
            DB::table('badges')
                ->whereNull('workspace_id')
                ->update([
                    'workspace_id' => $superWorkspaceId,
                    'admin_id' => $superAdmin->id,
                    'updated_at' => now(),
                ]);
        }

        // Guarantee a complete 1-100 badge set for every admin workspace and
        // link the bundled SVG artwork. Custom uploaded images are preserved.
        foreach ($workspaceOwnerIds as $workspaceId => $adminId) {
            foreach (range(1, 100) as $level) {
                $existingBadge = DB::table('badges')
                    ->where('workspace_id', $workspaceId)
                    ->where('required_level', $level)
                    ->first();

                if ($existingBadge) {
                    if ($this->usesBundledOrLegacyImage($existingBadge->image_path)) {
                        DB::table('badges')->where('id', $existingBadge->id)->update([
                            'image_path' => sprintf('images/badges/level-%03d.svg', $level),
                            'updated_at' => now(),
                        ]);
                    }

                    continue;
                }

                DB::table('badges')->insert([
                    'name' => "Level {$level} Badge",
                    'description' => "Awarded for reaching Level {$level}.",
                    'image_path' => sprintf('images/badges/level-%03d.svg', $level),
                    'required_level' => $level,
                    'workspace_id' => $workspaceId,
                    'admin_id' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function workspaceIdForAdmin(object $admin): ?int
    {
        if (filled($admin->current_workspace_id)) {
            return (int) $admin->current_workspace_id;
        }

        $workspaceId = DB::table('workspace_user')
            ->where('user_id', $admin->id)
            ->whereIn('role', ['owner', 'admin'])
            ->orderByRaw("CASE role WHEN 'owner' THEN 0 ELSE 1 END")
            ->orderBy('workspace_id')
            ->value('workspace_id');

        return $workspaceId ? (int) $workspaceId : null;
    }

    private function usesBundledOrLegacyImage(?string $imagePath): bool
    {
        if ($imagePath === null || $imagePath === '') {
            return true;
        }

        return str_starts_with($imagePath, 'badges/level-')
            || str_starts_with($imagePath, 'images/badges/level-');
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
