<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentGroupInvite;
use App\Models\User;
use App\Services\AssignmentInviteService;
use Illuminate\Http\Request;

class AssignmentInviteController extends Controller
{
    public function __construct(
        private readonly AssignmentInviteService $invites,
    ) {}

    /**
     * Send group invites (creates the group on first send).
     */
    public function store(Request $request, Assignment $assignment)
    {
        $this->authorizeVisibility($assignment, $request->user());

        // No new group activity once the assignment is closed.
        if (! $assignment->status()?->acceptsSubmissions()) {
            abort(403, 'This assignment is closed and no longer accepts submissions.');
        }

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->invites->invite($assignment, $request->user(), $data['user_ids']);

        return back()->with('success', 'Invites sent. We will notify you as they respond.');
    }

    /**
     * Invitee accepts or declines.
     */
    public function respond(Request $request, Assignment $assignment, AssignmentGroupInvite $invite)
    {
        $this->authorizeVisibility($assignment, $request->user());

        $data = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        if ($data['action'] === 'accept') {
            $this->invites->accept($assignment, $invite, $request->user());

            return back()->with('success', 'You joined the group.');
        }

        $this->invites->decline($assignment, $invite, $request->user());

        return back()->with('success', 'Invite declined.');
    }

    /**
     * Creator withdraws a pending invite.
     */
    public function destroy(Request $request, Assignment $assignment, AssignmentGroupInvite $invite)
    {
        $this->authorizeVisibility($assignment, $request->user());

        $this->invites->cancel($assignment, $invite, $request->user());

        return back()->with('success', 'Invite cancelled.');
    }

    private function authorizeVisibility(Assignment $assignment, User $user): void
    {
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment was not assigned to your section.');
        }
    }
}
