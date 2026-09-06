<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdmin = User::query()
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->first();

        $adminId = null;
        $workspaceId = null;

        if ($superAdmin) {
            $adminId = $superAdmin->id;
            $workspaceId = $superAdmin->current_workspace_id;

            if (! $workspaceId) {
                $workspaceId = $superAdmin->workspaces()
                    ->wherePivot('role', 'owner')
                    ->orderBy('workspaces.id')
                    ->value('workspaces.id');
            }
        }

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
