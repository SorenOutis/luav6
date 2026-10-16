<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AtRiskStudentsTool implements Tool
{
    public function name(): string
    {
        return 'at_risk_students';
    }

    public function description(): Stringable|string
    {
        return 'Find students who may need help: those who never logged in or have gone inactive, have no graded exams, or have a low exam average. Read-only; pairs well with the students and user_activity tools.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $days = min(max((int) ($request['days'] ?? 14), 1), 365);
        $minAverage = min(max((float) ($request['min_exam_average'] ?? 60), 0), 100);
        $limit = min(max((int) ($request['limit'] ?? 20), 1), 50);
        $sectionId = ! empty($request['section_id']) ? (int) $request['section_id'] : null;
        $search = ! empty($request['search']) ? trim((string) $request['search']) : null;

        $section = null;
        if ($sectionId) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('sections.workspace_id', $workspaceId)
                ->whereKey($sectionId)
                ->first();

            if (! $section) {
                return 'Error: section not found in this workspace. Use sections_admin for valid section IDs.';
            }
        }

        $cutoff = now()->subDays($days);

        $students = User::query()
            ->where(function ($query) use ($workspaceId) {
                $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                    ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                    ->orWhere('current_workspace_id', $workspaceId);
            })
            ->where('is_admin', false)
            ->when($section, fn ($query) => $query->whereHas('sections', fn ($sub) => $sub->where('sections.id', $section->id)))
            ->when($search, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withAvg(['examSubmissions as graded_average' => fn ($q) => $q->where('status', 'graded')], 'score')
            ->withCount(['examSubmissions as graded_count' => fn ($q) => $q->where('status', 'graded')])
            ->get();

        $flagged = $students
            ->map(function (User $student) use ($minAverage, $cutoff) {
                $reasons = [];

                if (! $student->last_login_at) {
                    $reasons[] = 'Never logged in';
                } elseif ($student->last_login_at->lt($cutoff)) {
                    $reasons[] = 'Inactive for '.(int) $student->last_login_at->diffInDays(now()).' days';
                }

                $gradedCount = (int) $student->graded_count;
                $average = $student->graded_average !== null ? round((float) $student->graded_average, 1) : null;

                if ($gradedCount === 0) {
                    $reasons[] = 'No graded exam submissions';
                } elseif ($average !== null && $average < $minAverage) {
                    $reasons[] = "Low exam average ({$average})";
                }

                if (($student->current_streak ?? 0) === 0 && $student->last_login_at && $student->last_login_at->gte($cutoff)) {
                    $reasons[] = 'No active streak';
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'id' => $student->id,
                    'name' => (string) $student->name,
                    'email' => (string) $student->email,
                    'risk_score' => count($reasons),
                    'reasons' => $reasons,
                    'last_login_at' => $student->last_login_at?->format('M d, Y g:i A'),
                    'days_since_login' => $student->last_login_at ? (int) $student->last_login_at->diffInDays(now()) : null,
                    'current_streak' => (int) ($student->current_streak ?? 0),
                    'graded_exam_count' => $gradedCount,
                    'exam_average' => $average,
                ];
            })
            ->filter()
            ->sortByDesc('risk_score')
            ->values()
            ->take($limit)
            ->values();

        return json_encode([
            'window_days' => $days,
            'min_exam_average' => $minAverage,
            'section' => $section ? ['id' => $section->id, 'name' => (string) $section->name] : null,
            'candidates_checked' => $students->count(),
            'flagged_count' => $flagged->count(),
            'students' => $flagged,
            'note' => 'Risk score counts how many signals apply (inactivity, no graded exams, low exam average, no streak). Exam average is the raw average score of graded submissions.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Inactivity threshold in days. Defaults to 14.'),
            'min_exam_average' => $schema->number()->description('Students below this exam average are flagged (0-100, default 60).'),
            'section_id' => $schema->integer()->description('Optional section ID to restrict results to one class section.'),
            'search' => $schema->string()->description('Optional name or email search filter.'),
            'limit' => $schema->integer()->description('Maximum number of flagged students to return (1-50, default 20).'),
        ];
    }
}
