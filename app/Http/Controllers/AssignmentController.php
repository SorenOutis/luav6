<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\AssignmentGroupInvite;
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

        // Invites drive the group-formation UI: pending invites sent by the
        // student's group (creator view) and pending invites received by the
        // student (invitee banner). Stale ones expire lazily first.
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

        return Inertia::render('Assignments', [
            'assignments' => $assignments->map(function ($assignment) use ($submissions, $uploaders, $groups, $pendingByGroup, $incomingInvites) {
                $submission = $submissions->get($assignment->id);
                $pivot = $submission?->pivot ?? $submission;
                $filePath = $pivot?->file_path;
                $group = $groups->get($pivot?->group_id);

                $incoming = $incomingInvites->get($assignment->id);

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'due_date' => $assignment->dueDateForClient(),
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
            })->values(),
        ]);
    }

    /**
     * Feedback the student has not opened yet: posted after they last
     * acknowledged it, or never acknowledged at all.
     */
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

    /**
     * The student expanded the grade details: acknowledge the feedback so
     * the "New feedback" flag clears. Mass update on purpose — it must not
     * re-trigger the grading/award hooks on the submission row.
     */
    public function markFeedbackSeen(Request $request, Assignment $assignment)
    {
        if (! $assignment->isVisibleTo($request->user())) {
            abort(403, 'This assignment is not available to you.');
        }

        Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->update(['feedback_seen_at' => now()]);

        return back();
    }

    public function store(Request $request, Assignment $assignment)
    {
        $request->validate([
            'file' => 'required|file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
        ]);

        $user = auth()->user();

        // Never accept work for an assignment the student was not given.
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment is not available to you.');
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
