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
        $examPage = $this->examPage($request->user(), $request->query('cursor'));
        $allExams = collect($examPage['data'])->flatMap(fn ($group) => $group['exams']);
        $pendingExams = $allExams->filter(fn ($exam) => ! ($exam['is_locked'] ?? false))->count();
        $completedExams = $allExams
            ->filter(fn ($exam) => ($exam['is_locked'] ?? false) && ($exam['has_submissions'] ?? false))
            ->count();

        $sectionNames = $allExams->pluck('section_name')->filter()->unique()->sort()->values();
        $sectionTabs = collect([
            ['key' => 'all', 'label' => 'All sections', 'count' => $allExams->count()],
        ])->merge($sectionNames->map(fn ($name) => [
            'key' => $name,
            'label' => $name,
            'count' => $allExams->filter(fn ($exam) => ($exam['section_name'] ?? '') === $name)->count(),
        ]))->values()->all();

        return Inertia::render('Activities/Index', [
            'examsBySeason' => $examPage['data'],
            'examPagination' => $examPage['meta'],
            'sectionTabs' => $sectionTabs,
            'hubStats' => [
                'exams' => [
                    'total' => $allExams->count(),
                    'pending' => $pendingExams,
                    'completed' => $completedExams,
                ],
            ],
        ]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array{hasMore: bool, nextCursor: string|null}}
     */
    private function examPage(User $user, ?string $cursor = null): array
    {
        $paginator = Exam::query()
            ->with([
                'section.season',
                'parts' => fn ($query) => $query
                    ->select(['id', 'exam_id', 'title', 'instructions', 'type', 'sort_order', 'points'])
                    ->orderBy('sort_order'),
            ])
            ->where('status', '!=', 'draft')
            ->visibleTo($user)
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

        $seasonRank = Season::query()
            ->whereIn('id', $exams->pluck('section.season_id')->filter()->unique())
            ->orderBy('start_date', 'desc')
            ->pluck('id', 'name');

        $groups = $examsData
            ->groupBy(fn ($exam) => $exam['season_name'] ?? 'Other')
            ->map(fn ($group, $seasonName) => [
                'seasonName' => $seasonName,
                'exams' => $group->values()->all(),
            ])
            ->sortBy(fn ($group) => $seasonRank->keys()->search(
                fn ($name) => $name === $group['seasonName']
            ) ?? 999)
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
