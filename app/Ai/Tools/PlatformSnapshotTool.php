<?php

namespace App\Ai\Tools;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Platform-wide read. Super Admins only. Workspace tools stay workspace-scoped.
 */
class PlatformSnapshotTool implements Tool
{
    public function name(): string
    {
        return 'platform_snapshot';
    }

    public function description(): Stringable|string
    {
        return 'Super Admin only. List workspaces with student and section counts, plus the failed_jobs count when that table exists. This is the only tool that is platform-wide. Do not use it to claim a workspace-scoped tool searched every school.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only administrators can use this tool.';
        }
        if (! $admin->isSuperAdmin()) {
            return 'PERMISSION DENIED: platform_snapshot is limited to Super Administrators. Workspace administrators only see their active workspace.';
        }

        $limit = max(1, min(50, (int) ($request['limit'] ?? 25)));
        $workspaces = Workspace::query()
            ->withCount(['users', 'sections'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'archived' => $workspace->isArchived(),
                'users' => (int) $workspace->users_count,
                'sections' => (int) $workspace->sections_count,
            ])
            ->values();

        $failedJobs = null;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        return json_encode([
            'scope' => 'platform',
            'workspace_count' => Workspace::query()->count(),
            'workspaces_returned' => $workspaces->count(),
            'workspaces' => $workspaces,
            'failed_jobs' => $failedJobs,
            'failed_jobs_note' => $failedJobs === null ? 'failed_jobs table is not present' : 'count of rows in failed_jobs',
        ], JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum workspaces to list, 1 to 50. Defaults to 25.'),
        ];
    }
}
