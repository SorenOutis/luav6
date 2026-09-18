<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Seed the bundled level 1-100 badge set into every admin workspace.
     *
     * Badges are tenant-scoped, so each admin workspace gets its own complete
     * set. The artwork paths point at the SVG files shipped with the app.
     */
    public function run(): void
    {
        $workspaceOwnerIds = $this->adminWorkspaceOwnerIds();

        if ($workspaceOwnerIds === []) {
            return;
        }

        foreach ($workspaceOwnerIds as $workspaceId => $adminId) {
            foreach (range(1, 100) as $level) {
                Badge::withoutGlobalScopes()->updateOrCreate(
                    [
                        'required_level' => $level,
                        'workspace_id' => $workspaceId,
                    ],
                    [
                        'name' => "Level {$level} Badge",
                        'description' => "Awarded for reaching Level {$level}.",
                        'image_path' => sprintf('images/badges/level-%03d.svg', $level),
                        'admin_id' => $adminId,
                    ]
                );
            }
        }
    }

    /**
     * Resolve the distinct workspace each admin actively manages, mapped to
     * its owner (falling back to the first admin found for that workspace).
     *
     * @return array<int, int>
     */
    private function adminWorkspaceOwnerIds(): array
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orderBy('id')
            ->get();

        $workspaceOwnerIds = [];

        foreach ($admins as $admin) {
            $workspaceId = $admin->current_workspace_id;

            if (! $workspaceId) {
                $workspaceId = $admin->workspaces()
                    ->wherePivotIn('role', ['owner', 'admin'])
                    ->orderByRaw("CASE workspace_user.role WHEN 'owner' THEN 0 ELSE 1 END")
                    ->orderBy('workspaces.id')
                    ->value('workspaces.id');
            }

            if (! $workspaceId || array_key_exists((int) $workspaceId, $workspaceOwnerIds)) {
                continue;
            }

            $workspace = Workspace::query()->find($workspaceId);
            $ownerId = $workspace?->users()
                ->wherePivot('role', 'owner')
                ->orderBy('users.id')
                ->value('users.id') ?? $admin->id;

            $workspaceOwnerIds[(int) $workspaceId] = (int) $ownerId;
        }

        return $workspaceOwnerIds;
    }
}
