<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AssignmentGroupService;
use App\Services\StudentAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public const ALLOWED_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png';

    public function index(StudentAssignmentService $studentAssignments)
    {
        $user = auth()->user();

        return Inertia::render('Assignments', [
            'assignments' => $studentAssignments->forUser($user),
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

        // Closed assignments stay visible but final: no new or repeat work.
        if (! $assignment->status()?->acceptsSubmissions()) {
            abort(403, 'This assignment is closed and no longer accepts submissions.');
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
