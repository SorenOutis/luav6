<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\Submission;
use App\Models\User;
use App\Services\AssignmentGroupService;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public const ALLOWED_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png';

    public function index()
    {
        $user = auth()->user();
        $sectionIds = $user->sections()->pluck('sections.id');

        // Assignments are targeted at sections. A student only sees the ones
        // given to a section they belong to; an assignment with no sections
        // is unassigned and reaches nobody.
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

        return Inertia::render('Assignments', [
            'assignments' => $assignments->map(function ($assignment) use ($submissions, $uploaders, $groups) {
                $submission = $submissions->get($assignment->id);
                $pivot = $submission?->pivot ?? $submission;
                $filePath = $pivot?->file_path;
                $group = $groups->get($pivot?->group_id);

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'due_date' => $assignment->due_date?->toIso8601String(),
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
                    ] : null,
                    'submission' => $submission ? [
                        'submitted' => $pivot->submitted,
                        'status' => $pivot->status,
                        'grade' => $pivot->grade,
                        'file_path' => $filePath,
                        'file_url' => PublicFileUrl::resolve($filePath),
                        'submitted_at' => $pivot->submitted_at,
                        'submitted_by' => $pivot->submitted_by,
                        'submitted_by_name' => $uploaders->get($pivot->submitted_by)?->name,
                        'points' => $pivot->points ?? 0,
                        'xp_earned' => $pivot->xp_earned ?? 0,
                        'feedback' => $pivot->feedback,
                        'graded_at' => $pivot->graded_at,
                        'graded_by' => $pivot->graded_by,
                        'file_extension' => $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : null,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, Assignment $assignment)
    {
        $request->validate([
            'file' => 'required|file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
        ]);

        $user = auth()->user();

        // Never accept work for an assignment the student was not given.
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment was not assigned to your section.');
        }

        $service = app(AssignmentGroupService::class);

        // Already graded work is final: no resubmission, even for groups.
        // Checked before storing so a rejected request leaves no partial state.
        $service->assertSubmittable($assignment, $user);

        $path = $request->file('file')->store('assignments/'.$user->id, 'public');

        $user->assignments()->syncWithoutDetaching([
            $assignment->id => [
                'submitted' => true,
                'status' => 'Submitted',
                'file_path' => $path,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
            ],
        ]);

        // Group activity: share the file with every member.
        $service->submitFile($assignment, $user, $path);

        return back()->with('success', 'Assignment submitted successfully!');
    }
}
