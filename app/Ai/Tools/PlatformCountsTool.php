<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PlatformCountsTool implements Tool
{
    public function name(): string
    {
        return 'platform_counts';
    }

    public function description(): Stringable|string
    {
        return 'Super Administrator only. Cross-workspace platform counts: workspaces, students, admins, exams, pending submissions. Optional workspace_id narrows to one school. Use when asked about the whole platform or another school\'s volume.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user?->isSuperAdmin()) {
            return json_encode([
                'error' => 'platform_tool_required',
                'message' => 'platform_counts is platform-wide and only available to Super Administrators.',
            ]);
        }

        $workspaceId = (int) ($request['workspace_id'] ?? 0);

        if ($workspaceId > 0) {
            $workspace = Workspace::query()->find($workspaceId);
            if (! $workspace) {
                return json_encode(['error' => 'workspace_not_found', 'workspace_id' => $workspaceId]);
            }

            return json_encode([
                'scope' => 'workspace',
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'archived' => $workspace->isArchived(),
                ],
                'students' => $workspace->students()->count(),
                'admins' => $workspace->admins()->count(),
                'sections' => $workspace->sections()->count(),
                'exams' => $workspace->exams()->count(),
                'pending_submissions' => ExamSubmission::query()
                    ->whereHas('exam', fn ($q) => $q->where('workspace_id', $workspaceId))
                    ->whereIn('status', ['submitted', 'pending', 'ai_review'])
                    ->count(),
            ]);
        }

        $activeWorkspaces = Workspace::query()->whereNull('archived_at')->count();
        $archivedWorkspaces = Workspace::query()->whereNotNull('archived_at')->count();

        return json_encode([
            'scope' => 'platform-wide',
            'workspaces_active' => $activeWorkspaces,
            'workspaces_archived' => $archivedWorkspaces,
            'students' => User::query()->where('is_admin', false)->count(),
            'admins' => User::query()->where('is_admin', true)->count(),
            'super_admins' => User::query()->where('is_super_admin', true)->count(),
            'exams' => Exam::query()->withoutGlobalScopes()->count(),
            'pending_submissions' => ExamSubmission::query()
                ->whereIn('status', ['submitted', 'pending', 'ai_review'])
                ->count(),
            'pending_ai_actions' => DB::table('pending_ai_actions')
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()->description('Optional workspace id to scope counts to one school. Omit for platform-wide totals.'),
        ];
    }
}
