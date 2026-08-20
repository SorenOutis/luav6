<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\User;
use App\Services\AssignmentGroupService;
use Illuminate\Http\Request;

class AssignmentGroupController extends Controller
{
    public function __construct(
        private readonly AssignmentGroupService $groups,
    ) {}

    /**
     * Searchable list of classmates the actor can add to their group.
     */
    public function candidates(Request $request, Assignment $assignment)
    {
        $this->authorizeVisibility($assignment, $request->user());

        return response()->json([
            'candidates' => $this->groups->candidates(
                $assignment,
                $request->user(),
                $request->query('q'),
            ),
        ]);
    }

    /**
     * Create a group for the current student.
     */
    public function store(Request $request, Assignment $assignment)
    {
        $this->authorizeVisibility($assignment, $request->user());

        $this->groups->createGroup($assignment, $request->user());

        return back()->with('success', 'Group created. You can now add members.');
    }

    /**
     * Add a member to the current student's group.
     */
    public function addMember(Request $request, Assignment $assignment)
    {
        $this->authorizeVisibility($assignment, $request->user());

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->groups->addMember($assignment, $request->user(), User::findOrFail($data['user_id']));

        return back()->with('success', 'Member added to your group.');
    }

    /**
     * Remove a member (creator only) or let a member leave the group.
     */
    public function removeMember(Request $request, Assignment $assignment, User $user)
    {
        $this->authorizeVisibility($assignment, $request->user());

        $this->groups->removeMember($assignment, $request->user(), $user);

        return back()->with('success', 'Member removed from the group.');
    }

    private function authorizeVisibility(Assignment $assignment, User $user): void
    {
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment was not assigned to your section.');
        }
    }
}
