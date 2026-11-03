<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ai\Support\AiBotRoster;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AiBotRosterSeeder extends Seeder
{
    /**
     * Seed the default team into each workspace. Existing bots are left
     * untouched so operator edits are never overwritten.
     */
    public function run(): void
    {
        $creator = User::query()
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->first();

        Workspace::query()
            ->orderBy('id')
            ->each(function (Workspace $workspace) use ($creator): void {
                AiBotRoster::seedForWorkspace($workspace, $creator);
            });
    }
}
