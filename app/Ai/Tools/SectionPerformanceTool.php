<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Section;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SectionPerformanceTool implements Tool
{
    public function name(): string
    {
        return 'section_performance';
    }

    public function description(): Stringable|string
    {
        return 'Summarize academic performance for one class section: recorded gradebook averages, recent exam scores, activity task results, and top or lowest performing students. Read-only; use sections_admin for valid section IDs.';
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

        $sectionId = (int) ($request['section_id'] ?? 0);
        $sectionName = trim((string) ($request['section_name'] ?? ''));

        if ($sectionId <= 0 && $sectionName === '') {
            return 'Error: provide section_id or section_name. Use sections_admin for valid section IDs.';
        }

        $section = Section::query()
            ->withoutGlobalScope('workspace')
            ->where('sections.workspace_id', $workspaceId)
            ->when($sectionId > 0, fn ($query) => $query->whereKey($sectionId))
            ->when($sectionId <= 0, fn ($query) => $query->where('name', 'like', "%{$sectionName}%"))
            ->orderBy('name')
            ->first();

        if (! $section) {
            return 'Error: section not found in this workspace. Use sections_admin for valid section IDs.';
        }

        $studentCount = $section->users()->where('users.is_admin', false)->count();

        $gradeStats = Grade::query()
            ->where('section_id', $section->id)
            ->selectRaw('COUNT(*) as records, AVG(score * 100.0 / NULLIF(max_score, 0)) as avg_percent')
            ->first();

        $periodStats = Grade::query()
            ->where('section_id', $section->id)
            ->selectRaw('period, COUNT(*) as records, AVG(score * 100.0 / NULLIF(max_score, 0)) as avg_percent')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'records' => (int) $row->records,
                'average_percent' => $row->avg_percent !== null ? round((float) $row->avg_percent, 1) : null,
            ])
            ->values()
            ->all();

        $exams = Exam::query()
            ->where('section_id', $section->id)
            ->withCount('submissions')
            ->withAvg('submissions', 'score')
            ->orderByDesc('exam_date')
            ->limit(10)
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => (string) $exam->title,
                'exam_date' => $exam->exam_date?->toDateString(),
                'submissions' => (int) $exam->submissions_count,
                'average_score' => $exam->submissions_avg_score !== null ? round((float) $exam->submissions_avg_score, 1) : null,
            ])
            ->values()
            ->all();

        $activityTasks = ActivityTask::query()
            ->where('section_id', $section->id)
            ->withCount('scores')
            ->withAvg('scores', 'score')
            ->orderByDesc('due_date')
            ->limit(10)
            ->get()
            ->map(function (ActivityTask $task) {
                $maxPoints = (float) $task->max_points;
                $avg = $task->scores_avg_score !== null ? round((float) $task->scores_avg_score, 1) : null;

                return [
                    'id' => $task->id,
                    'title' => (string) $task->title,
                    'max_points' => $maxPoints,
                    'scored_students' => (int) $task->scores_count,
                    'average_score' => $avg,
                    'average_percent' => $avg !== null && $maxPoints > 0 ? round($avg / $maxPoints * 100, 1) : null,
                ];
            })
            ->values()
            ->all();

        $topStudents = $this->gradebookExtremes($section->id, 'desc');
        $lowestStudents = $this->gradebookExtremes($section->id, 'asc');

        return json_encode([
            'section' => [
                'id' => $section->id,
                'name' => (string) $section->name,
                'school_level' => $section->school_level,
                'enrolled_students' => $studentCount,
            ],
            'gradebook' => [
                'records' => (int) ($gradeStats->records ?? 0),
                'average_percent' => $gradeStats?->avg_percent !== null ? round((float) $gradeStats->avg_percent, 1) : null,
                'by_period' => $periodStats,
            ],
            'recent_exams' => $exams,
            'recent_activity_tasks' => $activityTasks,
            'top_students' => $topStudents,
            'lowest_students' => $lowestStudents,
            'note' => 'Averages are computed from recorded gradebook entries (score / max_score) and raw exam/activity scores. Students without records are not included.',
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, average_percent: float, records: int}>
     */
    private function gradebookExtremes(int $sectionId, string $direction): array
    {
        $rows = Grade::query()
            ->where('section_id', $sectionId)
            ->selectRaw('user_id, COUNT(*) as records, AVG(score * 100.0 / NULLIF(max_score, 0)) as avg_percent')
            ->groupBy('user_id')
            ->orderBy('avg_percent', $direction)
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()->whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'id' => (int) $row->user_id,
            'name' => (string) ($names[(int) $row->user_id] ?? 'Unknown'),
            'average_percent' => $row->avg_percent !== null ? round((float) $row->avg_percent, 1) : 0.0,
            'records' => (int) $row->records,
        ])->values()->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->description('The ID of the class section to analyze.'),
            'section_name' => $schema->string()->description('The name (or partial name) of the section, alternative to section_id.'),
        ];
    }
}
