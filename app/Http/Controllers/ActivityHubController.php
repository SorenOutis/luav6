<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\AssignmentGroupInvite;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Submission;
use App\Models\User;
use App\Support\ExamPartSerializer;
use App\Support\PublicFileUrl;
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
        $assignmentsPayload = $this->assignmentsForUser($user);
        $coursesPayload = $this->coursesForUser($user);

        $allExams = collect($examPage['data'])
            ->flatMap(fn ($g) => $g['exams']);

        $pendingExams = $allExams
            ->filter(fn ($e) => ! ($e['is_locked'] ?? false))
            ->count();

        $pendingAssignments = collect($assignmentsPayload)
            ->filter(fn ($a) => ! ($a['submission']['submitted'] ?? false))
            ->count();

        $pendingCourses = collect($coursesPayload)
            ->filter(fn ($c) => ($c['progress'] ?? 0) < 100)
            ->count();

        $totalCount = $allExams->count() + count($assignmentsPayload) + count($coursesPayload);

        $completedExams = $allExams
            ->filter(fn ($e) => ($e['is_locked'] ?? false) && ($e['has_submissions'] ?? false))
            ->count();

        $completedAssignments = collect($assignmentsPayload)
            ->filter(fn ($a) => ($a['submission']['submitted'] ?? false))
            ->count();

        $completedCourses = collect($coursesPayload)
            ->filter(fn ($c) => ($c['progress'] ?? 0) >= 100)
            ->count();

        $sectionNames = collect()
            ->merge($allExams->pluck('section_name')->filter())
            ->merge(
                collect($assignmentsPayload)
                    ->flatMap(fn ($a) => collect($a['sections'] ?? [])->pluck('name'))
                    ->filter()
            )
            ->unique()
            ->sort()
            ->values();

        $sectionTabs = collect([
            ['key' => 'all', 'label' => 'All sections', 'count' => $totalCount],
        ])
            ->merge(
                $sectionNames->map(function ($name) use ($allExams, $assignmentsPayload) {
                    $examCount = $allExams
                        ->filter(fn ($e) => ($e['section_name'] ?? '') === $name)
                        ->count();

                    $assignCount = collect($assignmentsPayload)
                        ->filter(fn ($a) => collect($a['sections'] ?? [])->pluck('name')->contains($name))
                        ->count();

                    return [
                        'key' => $name,
                        'label' => $name,
                        'count' => $examCount + $assignCount,
                    ];
                })
            )
            ->values()
            ->all();

        $unified = $this->buildUnifiedTimeline(
            $allExams,
            collect($assignmentsPayload),
            collect($coursesPayload)
        );

        return Inertia::render('Activities/Index', [
            'examsBySeason' => $examPage['data'],
            'examPagination' => $examPage['meta'],
            'assignments' => $assignmentsPayload,
            'courses' => $coursesPayload,
            'sectionTabs' => $sectionTabs,
            'unifiedTimeline' => $unified,
            'hubStats' => [
                'total' => $totalCount,
                'pending' => $pendingExams + $pendingAssignments + $pendingCourses,
                'completed' => $completedExams + $completedAssignments + $completedCourses,
                'exams' => [
                    'total' => $allExams->count(),
                    'pending' => $pendingExams,
                    'completed' => $completedExams,
                ],
                'assignments' => [
                    'total' => count($assignmentsPayload),
                    'pending' => $pendingAssignments,
                    'completed' => $completedAssignments,
                ],
                'courses' => [
                    'total' => count($coursesPayload),
                    'pending' => $pendingCourses,
                    'completed' => $completedCourses,
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
            ->when(! $user->is_admin, function ($query) use ($user): void {
                $sectionIds = $user->sections()->pluck('sections.id');

                $query->where(function ($query) use ($sectionIds): void {
                    $query->whereNull('section_id')
                        ->orWhereIn('section_id', $sectionIds);
                });
            })
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

    private function assignmentsForUser(User $user): array
    {
        $sectionIds = $user->sections()->pluck('sections.id');

        $assignments = Assignment::query()
            ->visibleToSections($sectionIds)
            ->with(['course:id,name', 'sections:id,name'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        $ids = $assignments->pluck('id');

        $submissions = $ids->isEmpty()
            ? collect()
            : Submission::query()
                ->where('user_id', $user->id)
                ->whereIn('assignment_id', $ids)
                ->get()
                ->keyBy('assignment_id');

        $uploaderIds = $submissions->pluck('submitted_by')->filter()->unique()->values();
        $uploaders = $uploaderIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $uploaderIds)->get(['id', 'name'])->keyBy('id');

        $groupIds = $submissions->pluck('group_id')->filter()->unique()->values();
        $groups = $groupIds->isEmpty()
            ? collect()
            : AssignmentGroup::with('members.user:id,name,avatar')
                ->whereIn('id', $groupIds)
                ->get()
                ->keyBy('id');

        AssignmentGroupInvite::expireOverdue();

        $pendingByGroup = $groupIds->isEmpty()
            ? collect()
            : AssignmentGroupInvite::query()
                ->whereIn('group_id', $groupIds)
                ->where('status', AssignmentGroupInvite::STATUS_PENDING)
                ->with('invitee:id,name,avatar')
                ->get()
                ->groupBy('group_id');

        $incomingInvites = $ids->isEmpty()
            ? collect()
            : AssignmentGroupInvite::query()
                ->where('invitee_id', $user->id)
                ->whereIn('assignment_id', $ids)
                ->where('status', AssignmentGroupInvite::STATUS_PENDING)
                ->with('inviter:id,name,avatar')
                ->get()
                ->keyBy('assignment_id');

        return $assignments->map(function ($assignment) use ($submissions, $uploaders, $groups, $pendingByGroup, $incomingInvites) {
            $submission = $submissions->get($assignment->id);
            $pivot = $submission;
            $filePath = $pivot?->file_path;
            $group = $groups->get($pivot?->group_id);
            $incoming = $incomingInvites->get($assignment->id);

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'due_date' => $assignment->dueDateForClient(),
                'due_date_iso' => $assignment->due_date?->toIso8601String(),
                'points_possible' => $assignment->points_possible,
                'group_rules' => [
                    'min' => $assignment->min_group_size,
                    'max' => $assignment->max_group_size,
                ],
                'incoming_invite' => $incoming ? [
                    'id' => $incoming->id,
                    'inviter' => [
                        'id' => $incoming->inviter_id,
                        'name' => $incoming->inviter?->name,
                        'avatar' => $incoming->inviter?->avatar,
                    ],
                    'expires_at' => $incoming->expires_at?->toIso8601String(),
                ] : null,
                'course' => $assignment->course,
                'sections' => $assignment->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                ])->values(),
                'group' => $group ? [
                    'id' => $group->id,
                    'created_by' => $group->created_by,
                    'members' => $group->members
                        ->map(fn (Submission $member) => [
                            'id' => $member->user_id,
                            'name' => $member->user?->name,
                            'avatar' => $member->user?->avatar,
                        ])
                        ->values(),
                    'pending_invites' => $pendingByGroup->has($group->id)
                        ? $pendingByGroup->get($group->id)
                            ->map(fn (AssignmentGroupInvite $invite) => [
                                'id' => $invite->id,
                                'user' => [
                                    'id' => $invite->invitee_id,
                                    'name' => $invite->invitee?->name,
                                    'avatar' => $invite->invitee?->avatar,
                                ],
                                'expires_at' => $invite->expires_at?->toIso8601String(),
                            ])
                            ->values()
                            ->all()
                        : [],
                ] : null,
                'submission' => $submission ? [
                    'submitted' => $pivot->submitted,
                    'status' => $pivot->status,
                    'grade' => $pivot->grade,
                    'file_path' => $filePath,
                    'file_url' => PublicFileUrl::resolve($filePath),
                    'submitted_at' => $pivot->submitted_at?->toIso8601String(),
                    'submitted_by' => $pivot->submitted_by,
                    'submitted_by_name' => $uploaders->get($pivot->submitted_by)?->name,
                    'points' => $pivot->points ?? 0,
                    'xp_earned' => $pivot->xp_earned ?? 0,
                    'feedback' => $pivot->feedback,
                    'graded_at' => $pivot->graded_at?->toIso8601String(),
                    'graded_by' => $pivot->graded_by,
                    'feedback_seen_at' => $pivot->feedback_seen_at?->toIso8601String(),
                    'has_unseen_feedback' => $this->hasUnseenFeedback($pivot),
                    'file_extension' => $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : null,
                ] : null,
            ];
        })->values()->all();
    }

    private function hasUnseenFeedback(?Submission $submission): bool
    {
        if ($submission === null || blank($submission->feedback)) {
            return false;
        }

        if ($submission->feedback_seen_at === null) {
            return true;
        }

        return $submission->graded_at !== null && $submission->feedback_seen_at->lt($submission->graded_at);
    }

    private function coursesForUser(User $user): array
    {
        return $user->courses()
            ->withCount('modules')
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'description' => $course->description,
                    'cover_photo' => $course->cover_photo_url,
                    'totalLessons' => $course->total_lessons,
                    'completedLessons' => $course->pivot->completed_lessons ?? 0,
                    'progress' => $course->total_lessons > 0
                        ? round((($course->pivot->completed_lessons ?? 0) / $course->total_lessons) * 100)
                        : 0,
                    'xpEarned' => $course->pivot->xp_earned ?? 0,
                    'modulesCount' => (int) $course->modules_count,
                ];
            })->values()->all();
    }

    private function buildUnifiedTimeline($exams, $assignments, $courses): array
    {
        $items = collect();

        foreach ($exams as $exam) {
            $items->push([
                'kind' => 'exam',
                'id' => $exam['id'],
                'title' => $exam['title'],
                'href' => '/exams/' . $exam['id'],
            ]);
        }

        foreach ($assignments as $assignment) {
            $items->push([
                'kind' => 'assignment',
                'id' => $assignment['id'],
                'title' => $assignment['title'],
                'href' => '/assignments',
            ]);
        }

        foreach ($courses as $course) {
            $items->push([
                'kind' => 'course',
                'id' => $course['id'],
                'title' => $course['name'],
                'href' => '/courses/' . $course['id'],
            ]);
        }

        return $items->values()->all();
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->examPage(
            $request->user(),
            $request->query('cursor'),
        ));
    }
}
