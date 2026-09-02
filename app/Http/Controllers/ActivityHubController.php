<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\User;
use App\Services\ExamSetAssignmentService;
use App\Support\ExamPartSerializer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Arr;
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
        ]);
    }

    /**
     * Totals + per-section counts across every exam the student can see, not
     * just the page currently rendered.
     *
     * `is_locked` / `has_submissions` are recomputed here with the exact same
     * rule `examPage()` applies per card, so the overview tiles can never
     * disagree with the cards underneath them.
     *
     * @return array{total: int, pending: int, completed: int, sections: array<string, int>}
     */
    private function hubSummary(User $user): array
    {
        $exams = $this->visibleExams($user)
            ->select(['exams.id', 'exams.status', 'exams.section_id'])
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
            $isLocked = ($submitted === $totalParts && $totalParts > 0) || $exam->status === 'closed';

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
            ->select(['exams.id', 'exams.title', 'exams.status', 'exams.section_id', 'exams.created_at'])
            ->with(['section:id,name', 'section.season:id,name,start_date'])
            ->get();

        if ($exams->isEmpty()) {
            return [];
        }

        // One row per attempted exam: the summed score for the drawer, plus
        // the distinct-part count the "all parts submitted" check needs.
        $submissionTotals = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->groupBy('exam_id')
            ->selectRaw('exam_id, SUM(score) as total_score, COUNT(*) as submission_rows, COUNT(DISTINCT exam_part_id) as submitted_parts')
            ->get()
            ->keyBy('exam_id');

        $summaries = $this->examSets->summariesFor($user, $exams->pluck('id')->all());

        $rows = $exams->map(function (Exam $exam) use ($submissionTotals, $summaries) {
            $totals = $submissionTotals->get($exam->id);
            $submittedParts = (int) ($totals?->submitted_parts ?? 0);
            $totalParts = (int) ($summaries[$exam->id]['total_parts'] ?? 0);
            $allDone = $totalParts > 0 && $submittedParts >= $totalParts;
            $isLocked = $allDone || $exam->status === 'closed';
            $hasSubmissions = (int) ($totals?->submission_rows ?? 0) > 0;

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'section_name' => $exam->section?->name,
                'season_name' => $exam->section?->season?->name ?? 'Other',
                'season_start' => $exam->section?->season?->start_date?->getTimestamp() ?? 0,
                'created_at' => $exam->created_at?->getTimestamp() ?? 0,
                'score' => $hasSubmissions && $totals?->total_score !== null
                    ? round((float) $totals->total_score, 2)
                    : null,
                'submitted' => $hasSubmissions,
                'state' => $allDone
                    ? 'completed'
                    : ($isLocked && $exam->status === 'closed'
                        ? 'closed'
                        : ($isLocked
                            ? 'in_progress'
                            : ($exam->status === 'published' ? 'open' : 'draft'))),
            ];
        });

        $seasonStarts = $rows
            ->groupBy('season_name')
            ->map(fn ($group) => $group->max('season_start'));

        return $rows
            ->groupBy('season_name')
            ->map(fn ($group) => [
                'seasonName' => $group->keys()->first(),
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
