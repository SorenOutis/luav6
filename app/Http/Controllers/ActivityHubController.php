<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\User;
use App\Support\ExamPartSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Inertia\Inertia;
use Inertia\Response;

class ActivityHubController extends Controller
{
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

        $sectionTabs = collect([
            ['key' => 'all', 'label' => 'All sections', 'count' => $summary['total']],
        ])->merge($summary['sections']->map(fn (int $count, string $name) => [
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
     * @return array{total: int, pending: int, completed: int, sections: \Illuminate\Support\Collection<string, int>}
     */
    private function hubSummary(User $user): array
    {
        $exams = $this->visibleExams($user)
            ->select(['exams.id', 'exams.status', 'exams.section_id'])
            ->with('section:id,name')
            ->withCount('parts')
            ->get();

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
            $totalParts = (int) $exam->parts_count;
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
                ->sortKeys(),
        ];
    }

    /**
     * Shared visibility filter for the paginated grid and the hub totals, so
     * the two can never drift apart.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Exam>
     */
    private function visibleExams(User $user): \Illuminate\Database\Eloquent\Builder
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
                'parts' => fn ($query) => $query
                    ->select(['id', 'exam_id', 'title', 'instructions', 'type', 'sort_order', 'points'])
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

        $examsData = $exams->map(function (Exam $exam) use ($allSubmissions) {
            $submissions = $allSubmissions->get($exam->id, collect());
            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            return array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($exam->parts, false, false),
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $exam->parts->count(),
                'is_locked' => ($submittedPartsCount === $exam->parts->count() && $exam->parts->isNotEmpty())
                    || $exam->status === 'closed',
                'has_submissions' => $submissions->isNotEmpty(),
                'results_available' => $exam->status === 'closed' && $submissions->isNotEmpty(),
                'submissions' => $submissions->values()->all(),
                'section_name' => $exam->section?->name,
                'season_name' => $exam->section?->season?->name,
                'exam_date_iso' => $exam->exam_date?->toIso8601String(),
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
