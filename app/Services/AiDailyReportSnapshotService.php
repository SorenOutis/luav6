<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiDailyReportSnapshotService
{
    private const MAX_WORKSPACES = 25;

    /** @var array<string, array{label: string, unit: string}> */
    private const METRICS = [
        'active_student_logins' => ['label' => 'Active student logins', 'unit' => 'students'],
        'activity_completions' => ['label' => 'Activity completions (student submissions)', 'unit' => 'submissions'],
        'exam_submissions' => ['label' => 'Exam part submissions', 'unit' => 'submissions'],
        'new_student_memberships' => ['label' => 'New student workspace memberships', 'unit' => 'memberships'],
        'course_enrollments' => ['label' => 'New course enrollments', 'unit' => 'enrollments'],
        'ai_requests' => ['label' => 'Recorded AI requests', 'unit' => 'requests'],
        'ai_input_tokens' => ['label' => 'Recorded AI input tokens', 'unit' => 'tokens'],
        'ai_output_tokens' => ['label' => 'Recorded AI output tokens', 'unit' => 'tokens'],
        'ai_estimated_cost_usd' => ['label' => 'Recorded estimated AI cost', 'unit' => 'USD'],
    ];

    /**
     * Caller authorizes the explicit selection. No request or tenant context is read.
     * Workspace names are display labels only and must be excluded from AI prompts.
     *
     * @param  list<int>  $workspaceIds
     * @return array{
     *     date: string,
     *     comparison_date: string,
     *     timezone: string,
     *     workspace_ids: list<int>,
     *     workspace_names: array<int, string>,
     *     section_leaderboards: list<array{workspace_id: int, section_id: int, section_name: string, top_student_name: ?string, exp: int, level: int}>,
     *     metrics: list<array{key: string, label: string, workspace_id: int, current: int|float, previous: int|float, unit: string}>,
     *     limitations: list<string>
     * }
     *
     * @throws ValidationException
     */
    public function collect(array $workspaceIds, string $date, string $timezone): array
    {
        Validator::make([
            'workspace_ids' => $workspaceIds,
            'date' => $date,
            'timezone' => $timezone,
        ], [
            'workspace_ids' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_WORKSPACES],
            'workspace_ids.*' => ['bail', 'required', 'integer:strict', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'timezone' => ['required', 'timezone:all'],
        ])->validate();

        $workspaceIds = array_values(array_unique($workspaceIds));
        sort($workspaceIds, SORT_NUMERIC);

        $workspaceNames = DB::table('workspaces')
            ->whereIn('id', $workspaceIds)
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
        $missingIds = array_values(array_diff($workspaceIds, array_keys($workspaceNames)));

        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                'workspace_ids' => 'Selected workspaces do not exist: '.implode(', ', $missingIds).'.',
            ]);
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($day->toDateString() !== $date) {
            throw ValidationException::withMessages([
                'date' => 'The selected calendar day does not exist in this timezone.',
            ]);
        }

        /** A skipped midnight normalizes $day to 01:00; adjacent days need their own starts. */
        $comparisonDay = $day->subDay()->startOfDay();
        $end = $day->addDay()->startOfDay();
        $storageTimezone = config('app.timezone');

        /** Eloquent stores now() as an app-timezone wall clock without an offset. */
        $boundaries = [
            'previous' => $comparisonDay->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            'current' => $day->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
            'end' => $end->setTimezone($storageTimezone)->format('Y-m-d H:i:s'),
        ];

        $totals = [
            'active_student_logins' => $this->countsByWorkspace(
                DB::table('workspace_user')
                    ->join('users', 'users.id', '=', 'workspace_user.user_id')
                    ->where('workspace_user.role', 'student')
                    ->where('users.is_admin', false),
                'workspace_user.workspace_id',
                'users.last_login_at',
                $workspaceIds,
                $boundaries,
            ),
            'activity_completions' => $this->countsByWorkspace(
                DB::table('assignment_user')
                    ->join('assignments', 'assignments.id', '=', 'assignment_user.assignment_id')
                    ->where('assignment_user.submitted', true),
                'assignments.workspace_id',
                'assignment_user.submitted_at',
                $workspaceIds,
                $boundaries,
            ),
            'exam_submissions' => $this->countsByWorkspace(
                DB::table('exam_submissions')
                    ->join('exams', 'exams.id', '=', 'exam_submissions.exam_id')
                    ->whereIn('exam_submissions.status', ['submitted', 'pending_ai', 'pending_review', 'graded']),
                'exams.workspace_id',
                'exam_submissions.created_at',
                $workspaceIds,
                $boundaries,
            ),
            'new_student_memberships' => $this->countsByWorkspace(
                DB::table('workspace_user')->where('role', 'student'),
                'workspace_user.workspace_id',
                'workspace_user.created_at',
                $workspaceIds,
                $boundaries,
            ),
            'course_enrollments' => $this->countsByWorkspace(
                DB::table('course_user')->join('courses', 'courses.id', '=', 'course_user.course_id'),
                'courses.workspace_id',
                'course_user.created_at',
                $workspaceIds,
                $boundaries,
            ),
        ];

        $limitations = [
            'Timestamp metrics interpret stored wall-clock timestamps in the configured app timezone; historical timezone configuration changes cannot be reconstructed.',
            'Active student logins count distinct enrolled students with last_login_at in the calendar window; students who logged in multiple times are counted once.',
            'Activity completions count retained submitted student rows at submitted_at, not grading events; rows without submitted_at are excluded. Group work counts per student and resubmissions overwrite that timestamp.',
            'Exam submissions count submitted parts at created_at, not fully completed exams; no full-exam completion timestamp is recorded.',
            'Memberships and enrollments count retained rows at created_at; deleted memberships and historical role changes are not reconstructed. Global user registrations cannot be attributed reliably to a workspace.',
            'Historical pending grading backlog is unavailable: current statuses and updated_at do not record past backlog snapshots.',
            'AI metrics cover recorded workspace usage only. Unlogged requests and failures are unavailable, not zero; logs without a workspace are excluded.',
            'AI tokens and USD costs may be estimates, not provider invoices; stored zero cost can reflect missing legacy pricing rather than free usage.',
        ];

        $definitions = self::METRICS;

        if ($timezone === $storageTimezone) {
            $totals = array_merge($totals, $this->aiUsageByWorkspace(
                $workspaceIds,
                $comparisonDay->toDateString(),
                $date,
                $end->toDateString(),
            ));
            $limitations[] = 'AI usage follows the recorded app-calendar date: reserved requests use their reservation date and other requests use their logging date.';
        } else {
            foreach (['ai_requests', 'ai_input_tokens', 'ai_output_tokens', 'ai_estimated_cost_usd'] as $key) {
                unset($definitions[$key]);
            }

            $limitations[] = 'AI daily metrics are unavailable for a timezone different from app.timezone: date-only usage buckets cannot be rebucketed reliably.';
        }

        $metrics = [];

        foreach ($workspaceIds as $workspaceId) {
            foreach ($definitions as $key => $definition) {
                $zero = $definition['unit'] === 'USD' ? 0.0 : 0;
                $metrics[] = [
                    'key' => "workspace:{$workspaceId}:{$key}",
                    'label' => $definition['label'],
                    'workspace_id' => $workspaceId,
                    'current' => $totals[$key][$workspaceId]['current'] ?? $zero,
                    'previous' => $totals[$key][$workspaceId]['previous'] ?? $zero,
                    'unit' => $definition['unit'],
                ];
            }
        }

        $sectionLeaderboards = $this->sectionLeaderboards($workspaceIds);

        return [
            'date' => $date,
            'comparison_date' => $comparisonDay->toDateString(),
            'timezone' => $timezone,
            'workspace_ids' => $workspaceIds,
            'workspace_names' => $workspaceNames,
            'section_leaderboards' => $sectionLeaderboards,
            'metrics' => $metrics,
            'limitations' => $limitations,
        ];
    }

    /**
     * Column names are internal constants, never caller input. Each query returns
     * at most one aggregate row per selected workspace for both calendar days.
     *
     * @param  list<int>  $workspaceIds
     * @param  array{previous: string, current: string, end: string}  $boundaries
     * @return array<int, array{current: int, previous: int}>
     */
    private function countsByWorkspace(Builder $query, string $workspaceColumn, string $timestampColumn, array $workspaceIds, array $boundaries): array
    {
        $rows = $query
            ->select("{$workspaceColumn} as workspace_id")
            ->selectRaw("COUNT(CASE WHEN {$timestampColumn} >= ? AND {$timestampColumn} < ? THEN 1 END) as current", [
                $boundaries['current'], $boundaries['end'],
            ])
            ->selectRaw("COUNT(CASE WHEN {$timestampColumn} >= ? AND {$timestampColumn} < ? THEN 1 END) as previous", [
                $boundaries['previous'], $boundaries['current'],
            ])
            ->whereIn($workspaceColumn, $workspaceIds)
            ->where($timestampColumn, '>=', $boundaries['previous'])
            ->where($timestampColumn, '<', $boundaries['end'])
            ->groupBy($workspaceColumn)
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->workspace_id] = [
                'current' => (int) $row->current,
                'previous' => (int) $row->previous,
            ];
        }

        return $totals;
    }

    /**
     * @param  list<int>  $workspaceIds
     * @return array<string, array<int, array{current: int|float, previous: int|float}>>
     */
    private function aiUsageByWorkspace(array $workspaceIds, string $comparisonDate, string $date, string $endDate): array
    {
        $query = DB::table('ai_usage_logs')
            ->select('workspace_id')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('date', '>=', $comparisonDate)
            ->where('date', '<', $endDate)
            ->groupBy('workspace_id');
        $columns = [
            'ai_requests' => '1',
            'ai_input_tokens' => 'input_tokens',
            'ai_output_tokens' => 'output_tokens',
            'ai_estimated_cost_usd' => 'estimated_cost_micros',
        ];
        $periods = [
            'current' => [$date, $endDate],
            'previous' => [$comparisonDate, $date],
        ];

        foreach ($columns as $key => $column) {
            foreach ($periods as $period => $boundaries) {
                $query->selectRaw("SUM(CASE WHEN date >= ? AND date < ? THEN {$column} ELSE 0 END) as {$period}_{$key}", $boundaries);
            }
        }

        $totals = [];

        foreach ($query->get() as $row) {
            foreach ($columns as $key => $column) {
                foreach ($periods as $period => $boundaries) {
                    $value = (int) $row->{"{$period}_{$key}"};
                    $totals[$key][(int) $row->workspace_id][$period] = $key === 'ai_estimated_cost_usd'
                        ? AiCostEstimator::microsToDollars($value)
                        : $value;
                }
            }
        }

        return $totals;
    }

    /**
     * @param  list<int>  $workspaceIds
     * @return list<array{workspace_id: int, section_id: int, section_name: string, top_student_name: ?string, exp: int, level: int}>
     */
    private function sectionLeaderboards(array $workspaceIds): array
    {
        $sections = DB::table('sections')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('leaderboard_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'workspace_id']);

        if ($sections->isEmpty()) {
            return [];
        }

        $sub = DB::table('sections')
            ->join('section_user as membership', 'membership.section_id', '=', 'sections.id')
            ->join('users', 'users.id', '=', 'membership.user_id')
            ->leftJoin('section_progress as progress', function ($join): void {
                $join->on('progress.user_id', '=', 'users.id')
                    ->on('progress.section_id', '=', 'membership.section_id');
            })
            ->whereIn('sections.workspace_id', $workspaceIds)
            ->where('sections.leaderboard_enabled', true)
            ->where('users.is_admin', false)
            ->select([
                'sections.id as section_id',
                'users.name as user_name',
                DB::raw('COALESCE(progress.exp, 0) as exp'),
                DB::raw('COALESCE(progress.level, 1) as level'),
                DB::raw('ROW_NUMBER() OVER (PARTITION BY sections.id ORDER BY COALESCE(progress.exp, 0) DESC, users.id ASC) as rank_pos'),
            ]);

        $leaders = DB::query()
            ->fromSub($sub, 'top_students')
            ->where('rank_pos', 1)
            ->get()
            ->keyBy('section_id');

        $result = [];

        foreach ($sections as $section) {
            $leader = $leaders->get($section->id);
            $result[] = [
                'workspace_id' => (int) $section->workspace_id,
                'section_id' => (int) $section->id,
                'section_name' => (string) $section->name,
                'top_student_name' => $leader ? (string) $leader->user_name : null,
                'exp' => $leader ? (int) $leader->exp : 0,
                'level' => $leader ? (int) $leader->level : 1,
            ];
        }

        return $result;
    }
}
