<?php

namespace App\Http\Controllers;

use App\Models\ActivityTask;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamSetAssignmentService;
use App\Support\ExamPartSerializer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActivityHubController extends Controller
{
    public function __construct(protected ExamSetAssignmentService $examSets) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $examPage = $this->examPage($user, $request->query('cursor'));

        // The card grid is cursor-paginated, but the overview tiles and the
        // section tab bar describe the *whole* catalogue. Deriving them from
        // `$examPage` capped both at the first 24 exams: the counters froze at
        // 24 and any section whose exams all sat past the first page never got
        // a tab at all, so those exams were unreachable from the hub.
        $summary = $this->hubSummary($user);
        $activityScores = $this->activityScores($user);
        $userSections = $user->sections()->withoutGlobalScope('workspace')->get(['sections.id', 'sections.activity_record_enabled']);
        $activityRecordEnabled = $user->is_admin
            || ($userSections->isEmpty() ? true : $userSections->contains(fn ($s) => (bool) $s->activity_record_enabled));

        $sectionTabs = collect([
            ['key' => 'all', 'label' => 'All sections', 'count' => $summary['total']],
        ])->merge(collect($summary['sections'])->map(fn (int $count, string $name) => [
            'key' => $name,
            'label' => $name,
            'count' => $count,
        ])->values())->values()->all();

        return Inertia::render('Activities/Index', [
            'examsBySeason' => $examPage['data'],
            'examPagination' => $examPage['meta'],
            'sectionTabs' => $sectionTabs,
            'hubStats' => [
                'exams' => [
                    'total' => $summary['total'],
                    'pending' => $summary['pending'],
                    'completed' => $summary['completed'],
                ],
            ],
            'activityScores' => $activityScores,
            'activityRecordEnabled' => $activityRecordEnabled,
        ]);
    }

    /**
     * Totals + per-section counts across every exam the student can see, not
     * just the page currently rendered.
     *
     * `is_locked` / `has_submissions` are recomputed here with the exact same
     * rule `examPage()` applies per card, so the overview tiles can never
     * disagree with the cards underneath them. That rule goes through
     * `isEffectivelyClosed()`, which needs `ends_at`: an exam whose scheduled
     * window has ended is closed on the card even if the teacher never
     * flipped the status, and must not be counted as "Pending" up top.
     *
     * @return array{total: int, pending: int, completed: int, sections: array<string, int>}
     */
    private function hubSummary(User $user): array
    {
        $exams = $this->visibleExams($user)
            ->select(['exams.id', 'exams.status', 'exams.section_id', 'exams.ends_at'])
            ->with('section:id,name')
            ->get();

        // An exam can ship as several interchangeable sets, so a student's
        // progress is measured against the set they were handed — not against
        // every set the teacher wrote.
        $summaries = $this->examSets->summariesFor($user, $exams->pluck('id')->all());

        // One row per exam the student has attempted. `submission_rows` mirrors
        // the `isNotEmpty()` check `examPage()` uses for `has_submissions`, and
        // `submitted_parts` mirrors its `unique('exam_part_id')->count()`, so
        // the tiles and the cards cannot disagree.
        $submissionTotals = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->groupBy('exam_id')
            ->selectRaw('exam_id, COUNT(*) as submission_rows, COUNT(DISTINCT exam_part_id) as submitted_parts')
            ->get()
            ->keyBy('exam_id');

        $pending = 0;
        $completed = 0;

        foreach ($exams as $exam) {
            $totalParts = (int) ($summaries[$exam->id]['total_parts'] ?? 0);
            $totals = $submissionTotals->get($exam->id);
            $submitted = (int) ($totals->submitted_parts ?? 0);
            $hasSubmissions = (int) ($totals->submission_rows ?? 0) > 0;
            $isLocked = ($submitted === $totalParts && $totalParts > 0) || $exam->isEffectivelyClosed();

            if (! $isLocked) {
                $pending++;
            }

            if ($isLocked && $hasSubmissions) {
                $completed++;
            }
        }

        return [
            'total' => $exams->count(),
            'pending' => $pending,
            'completed' => $completed,
            'sections' => $exams
                ->map(fn (Exam $exam) => $exam->section?->name)
                ->filter()
                ->countBy()
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * Per-activity scores for the "My Scores" drawer.
     *
     * Spans the student's whole visible catalogue (not just the current
     * cursor page) and is grouped by season in the same order `examPage()`
     * renders the grid, so the drawer reads top-to-bottom like the cards.
     *
     * Each row's `state` mirrors the exam card's status badge
     * (`getStatusBadgeInfo()` in `resources/js/pages/Activities/Index.vue`),
     * and `score` is the sum of the student's part scores — the same figure
     * the card's score pill shows. Exams the student has not submitted yet
     * carry `score: null` and render a placeholder.
     *
     * @return array<int, array{seasonName: string, exams: array<int, array<string, mixed>>}>
     */
    private function activityScores(User $user): array
    {
        $exams = $this->visibleExams($user)
            ->select(['exams.id', 'exams.title', 'exams.status', 'exams.section_id', 'exams.term', 'exams.created_at', 'exams.ends_at', 'exams.starts_at'])
            ->with(['section:id,name,school_level,activity_record_enabled,activity_record_terms', 'section.season:id,name,start_date'])
            ->get();

        $userSectionIds = DB::table('section_user')
            ->where('user_id', $user->id)
            ->pluck('section_id')
            ->all();

        $tasks = ! empty($userSectionIds)
            ? ActivityTask::withoutGlobalScope('workspace')
                ->whereIn('section_id', $userSectionIds)
                ->with([
                    'section' => fn ($q) => $q->withoutGlobalScope('workspace'),
                    'section.season' => fn ($q) => $q->withoutGlobalScope('workspace'),
                    'scores' => fn ($q) => $q->where('user_id', $user->id),
                ])
                ->get()
            : collect();

        if ($exams->isEmpty() && $tasks->isEmpty()) {
            return [];
        }

        $allSubmissions = $exams->isNotEmpty()
            ? ExamSubmission::query()
                ->where('user_id', $user->id)
                ->whereIn('exam_id', $exams->pluck('id'))
                ->get(['id', 'exam_id', 'exam_part_id', 'status', 'score', 'is_late', 'grading_failed'])
                ->groupBy('exam_id')
            : collect();

        $allParts = $exams->isNotEmpty()
            ? ExamPart::query()
                ->whereIn('exam_id', $exams->pluck('id'))
                ->get(['id', 'exam_id', 'exam_set_id', 'points', 'questions', 'sort_order'])
                ->groupBy('exam_id')
            : collect();

        $summaries = $exams->isNotEmpty()
            ? $this->examSets->summariesFor($user, $exams->pluck('id')->all())
            : [];

        $examRows = $exams
            ->filter(function (Exam $exam) {
                if (! $exam->section) {
                    return true;
                }

                return $exam->section->isTermAllowedForActivityRecord($exam->term);
            })
            ->map(function (Exam $exam) use ($allSubmissions, $allParts, $summaries) {
                $submissions = $allSubmissions->get($exam->id, collect());
                $submittedPartsCount = $submissions->unique('exam_part_id')->count();
                $totalParts = (int) ($summaries[$exam->id]['total_parts'] ?? 0);
                $allDone = $totalParts > 0 && $submittedPartsCount >= $totalParts;

                $closedNow = $exam->isEffectivelyClosed();
                $hasSubmissions = $submissions->isNotEmpty();
                $hasLate = $submissions->contains(fn ($s) => (bool) $s->is_late);
                $isPendingReview = $submissions->contains(fn ($s) => in_array($s->status, ['pending_review', 'pending_ai'], true));

                $set = $summaries[$exam->id]['set'] ?? null;
                $parts = $this->examSets->filterParts($exam, $allParts->get($exam->id, collect()), $set);
                $totalPoints = round((float) $parts->sum(fn (ExamPart $part) => $part->totalPoints()), 2);

                $rawTotalScore = $hasSubmissions ? $submissions->sum('score') : null;
                $score = $hasSubmissions && $rawTotalScore !== null ? round((float) $rawTotalScore, 2) : null;

                if ($allDone) {
                    $state = 'completed';
                } elseif ($closedNow) {
                    $state = 'closed';
                } elseif ($hasSubmissions) {
                    $state = 'in_progress';
                } else {
                    $state = $exam->status === 'published' ? 'open' : 'draft';
                }

                $percentage = null;
                if ($totalPoints > 0 && $score !== null) {
                    $percentage = round(($score / $totalPoints) * 100, 1);
                }

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'activity_type' => 'Written Work',
                    'term' => $exam->term ?: 'General',
                    'section_name' => $exam->section?->name,
                    'school_level' => $exam->section?->school_level ?? Section::SCHOOL_LEVEL_COLLEGE,
                    'season_name' => $exam->section?->season?->name ?? 'Other',
                    'season_start' => $exam->section?->season?->start_date?->getTimestamp() ?? 0,
                    'created_at' => $exam->created_at?->getTimestamp() ?? 0,
                    'ends_at_iso' => $exam->ends_at?->toIso8601String(),
                    'score' => $score,
                    'total_points' => $totalPoints,
                    'percentage' => $percentage,
                    'submitted' => $hasSubmissions,
                    'is_missed' => $closedNow && ! $hasSubmissions,
                    'is_incomplete' => $closedNow && $hasSubmissions && ! $allDone,
                    'is_pending_review' => $hasSubmissions && $isPendingReview,
                    'submitted_parts' => $submittedPartsCount,
                    'total_parts' => $totalParts,
                    'is_late' => $hasLate,
                    'state' => $state,
                ];
            });

        $taskRows = $tasks
            ->filter(function (ActivityTask $task) {
                if (! $task->section) {
                    return true;
                }

                return $task->section->isTermAllowedForActivityRecord($task->term);
            })
            ->map(function (ActivityTask $task) {
                $userScore = $task->scores->first();
                $hasScore = $userScore !== null && $userScore->score !== null;
                $score = $hasScore ? (float) $userScore->score : null;
                $isMissed = (bool) ($userScore?->is_missed ?? false);
                $totalPoints = round((float) $task->max_points, 2);
                $pct = ($score !== null && $totalPoints > 0) ? round(($score / $totalPoints) * 100, 1) : null;

                $state = $hasScore ? 'completed' : ($isMissed ? 'closed' : 'open');

                return [
                    'id' => 'task_'.$task->id,
                    'title' => $task->title,
                    'activity_type' => $task->task_type ?: 'Performance Task',
                    'term' => $task->term ?: 'General',
                    'section_name' => $task->section?->name,
                    'school_level' => $task->section?->school_level ?? Section::SCHOOL_LEVEL_COLLEGE,
                    'season_name' => $task->section?->season?->name ?? 'Other',
                    'season_start' => $task->section?->season?->start_date?->getTimestamp() ?? 0,
                    'created_at' => $task->created_at?->getTimestamp() ?? 0,
                    'ends_at_iso' => $task->due_date?->toIso8601String(),
                    'score' => $score,
                    'total_points' => $totalPoints,
                    'percentage' => $pct,
                    'submitted' => $hasScore,
                    'is_missed' => $isMissed,
                    'is_incomplete' => false,
                    'is_pending_review' => false,
                    'submitted_parts' => $hasScore ? 1 : 0,
                    'total_parts' => 1,
                    'is_late' => false,
                    'state' => $state,
                ];
            });

        $rows = $examRows->concat($taskRows);

        $seasonStarts = $rows
            ->groupBy('season_name')
            ->map(fn ($group) => $group->max('season_start'));

        return $rows
            ->groupBy('season_name')
            ->map(fn ($group, $seasonName) => [
                'seasonName' => (string) $seasonName,
                'exams' => $group
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (array $row) => Arr::except($row, ['season_start', 'created_at']))
                    ->all(),
            ])
            ->sortByDesc(fn (array $group) => $seasonStarts[$group['seasonName']] ?? PHP_INT_MIN)
            ->values()
            ->all();
    }

    /**
     * Shared visibility filter for the paginated grid and the hub totals, so
     * the two can never drift apart.
     *
     * @return Builder<Exam>
     */
    private function visibleExams(User $user): Builder
    {
        return Exam::query()
            ->where('status', '!=', 'draft')
            ->visibleTo($user);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array{hasMore: bool, nextCursor: string|null}}
     */
    private function examPage(User $user, ?string $cursor = null): array
    {
        $paginator = $this->visibleExams($user)
            ->with([
                'section.season',
                'sets',
                'parts' => fn ($query) => $query
                    ->select(['id', 'exam_id', 'exam_set_id', 'title', 'instructions', 'type', 'sort_order', 'points'])
                    ->orderBy('sort_order'),
            ])
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate(24, ['*'], 'cursor', Cursor::fromEncoded($cursor));

        $exams = collect($paginator->items());
        $examIds = $exams->pluck('id');

        $allSubmissions = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->get(['id', 'exam_id', 'exam_part_id', 'status', 'score', 'is_late', 'grading_failed'])
            ->groupBy('exam_id');

        $summaries = $this->examSets->summariesFor($user, $examIds->all());

        $examsData = $exams->map(function (Exam $exam) use ($allSubmissions, $summaries) {
            $submissions = $allSubmissions->get($exam->id, collect());
            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            // Cards describe the set this student will actually take: the set
            // they were handed, or the first set until they open the exam.
            $set = $summaries[$exam->id]['set'] ?? null;
            $parts = $this->examSets->filterParts($exam, $exam->parts, $set);

            $closedNow = $exam->isEffectivelyClosed();
            $scheduleState = $exam->scheduleState();

            return array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($parts, false, false),
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $parts->count(),
                'set' => $set !== null ? ['id' => $set->id, 'title' => $set->title] : null,
                'is_locked' => ($submittedPartsCount === $parts->count() && $parts->isNotEmpty())
                    || $closedNow,
                'has_submissions' => $submissions->isNotEmpty(),
                'results_available' => $closedNow && $submissions->isNotEmpty(),
                'submissions' => $submissions->values()->all(),
                'section_name' => $exam->section?->name,
                'season_name' => $exam->section?->season?->name,
                'exam_date_iso' => $exam->exam_date?->toIso8601String(),
                'starts_at_iso' => $exam->starts_at?->toIso8601String(),
                'ends_at_iso' => $exam->ends_at?->toIso8601String(),
                'is_open_now' => $exam->acceptsSubmissions(),
                'is_upcoming' => $scheduleState === 'upcoming',
                'has_ended' => $scheduleState === 'ended',
            ]);
        });

        // Newest season first. Read the ordering off the seasons already
        // eager-loaded on the exams instead of a fresh `Season::query()`: that
        // query is workspace-scoped, so a student with no active tenant got an
        // empty rank map and — because `Collection::search()` returns `false`,
        // not null, so `?? 999` never fired — every group sorted by `false`.
        $seasonStarts = $exams
            ->map(fn (Exam $exam) => $exam->section?->season)
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (Season $season) => [
                $season->name => $season->start_date?->getTimestamp() ?? 0,
            ]);

        $groups = $examsData
            ->groupBy(fn ($exam) => $exam['season_name'] ?? 'Other')
            ->map(fn ($group, $seasonName) => [
                'seasonName' => $seasonName,
                'exams' => $group->values()->all(),
            ])
            ->sortByDesc(fn ($group) => $seasonStarts[$group['seasonName']] ?? PHP_INT_MIN)
            ->values()
            ->all();

        return [
            'data' => $groups,
            'meta' => [
                'hasMore' => $paginator->hasMorePages(),
                'nextCursor' => $paginator->nextCursor()?->encode(),
            ],
        ];
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->examPage(
            $request->user(),
            $request->query('cursor'),
        ));
    }
}
