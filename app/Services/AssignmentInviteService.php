<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\AssignmentGroupInvite;
use App\Models\User;
use App\Notifications\AssignmentGroupInviteNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Group formation through invites (GROUP_INVITE_FLOW_DESIGN.md).
 *
 * The creator sends invites; each invitee accepts or declines; only an
 * acceptance writes group membership (via AssignmentGroupService::joinGroup,
 * so late joiners inherit the shared file exactly like before). Submission is
 * never blocked by group state — a student can always submit with or without
 * members (product decision: no deadlocks).
 */
class AssignmentInviteService
{
    public function __construct(
        private readonly AssignmentGroupService $groups,
    ) {}

    /**
     * Send invites to classmates. The first send creates the group with the
     * sender as creator; later sends invite into the sender's existing group.
     * Validated + written atomically: a bad invitee aborts the whole batch.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, AssignmentGroupInvite>
     */
    public function invite(Assignment $assignment, User $inviter, array $userIds): Collection
    {
        $userIds = collect($userIds)->unique()->values()->all();
        abort_if(count($userIds) === 0, 422, 'Select at least one classmate to invite.');

        if (! $assignment->isVisibleTo($inviter)) {
            abort(403, 'This assignment was not assigned to your section.');
        }

        // Graded work is final: no new groups, no new invites.
        $this->groups->assertSubmittable($assignment, $inviter);

        AssignmentGroupInvite::expireOverdue($assignment);

        /** @var Collection<int, AssignmentGroupInvite> $created */
        $created = Collection::make();

        DB::transaction(function () use ($assignment, $inviter, $userIds, &$created): void {
            $group = $this->groups->groupFor($assignment, $inviter);

            if (! $group) {
                $group = $this->groups->createGroup($assignment, $inviter);
            } elseif ($group->created_by !== $inviter->id) {
                abort(403, 'Only the group creator can invite members.');
            }

            $this->assertWithinMax($assignment, $group, count($userIds));

            $invitees = User::query()->whereIn('id', $userIds)->orderBy('name')->get();

            foreach ($invitees as $invitee) {
                $this->assertInvitable($assignment, $invitee);

                $invite = AssignmentGroupInvite::create([
                    'assignment_id' => $assignment->id,
                    'group_id' => $group->id,
                    'inviter_id' => $inviter->id,
                    'invitee_id' => $invitee->id,
                    'status' => AssignmentGroupInvite::STATUS_PENDING,
                    'expires_at' => $assignment->due_date,
                ]);

                $created->push($invite);

                $invitee->notify(new AssignmentGroupInviteNotification([
                    'type' => 'assignment_invite',
                    'icon' => 'users',
                    'title' => "{$inviter->name} invited you to a group",
                    'message' => $assignment->title,
                    'meta' => 'Group activity',
                    'href' => '/assignments',
                    'invite_id' => $invite->id,
                    'assignment_id' => $assignment->id,
                ]));
            }
        });

        return $created;
    }

    /**
     * Invitee accepts: writes membership (inheriting any already-submitted
     * shared file) and tells the creator.
     */
    public function accept(Assignment $assignment, AssignmentGroupInvite $invite, User $user): void
    {
        $this->assertBelongsToAssignment($assignment, $invite);
        abort_unless($invite->invitee_id === $user->id, 403, 'This invite was not sent to you.');
        $this->assertActionable($invite);

        // Graded work is final: the group can no longer grow.
        $this->groups->assertSubmittable($assignment, $user);

        if ($this->groups->groupFor($assignment, $user)) {
            abort(422, 'You are already in a group for this assignment.');
        }

        $group = $invite->group;
        abort_if($group === null, 422, 'This group no longer exists.');

        DB::transaction(function () use ($assignment, $invite, $group, $user): void {
            $invite->forceFill([
                'status' => AssignmentGroupInvite::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            // Cap check runs AFTER the invite left the pending pool — the
            // accepting invite must not count against its own slot.
            $this->assertWithinMax($assignment, $group, 1);

            $this->groups->joinGroup($assignment, $group, $user);
        });

        $this->notifyCreator($group, $assignment, $invite, $user, true);
    }

    /**
     * Invitee declines: frees the slot (and the candidate list re-shows
     * them). Declining does not block future invites.
     */
    public function decline(Assignment $assignment, AssignmentGroupInvite $invite, User $user): void
    {
        $this->assertBelongsToAssignment($assignment, $invite);
        abort_unless($invite->invitee_id === $user->id, 403, 'This invite was not sent to you.');
        $this->assertActionable($invite);

        $invite->forceFill([
            'status' => AssignmentGroupInvite::STATUS_DECLINED,
            'responded_at' => now(),
        ])->save();

        if ($group = $invite->group) {
            $this->notifyCreator($group, $assignment, $invite, $user, false);
        }
    }

    /**
     * Creator withdraws a pending invite.
     */
    public function cancel(Assignment $assignment, AssignmentGroupInvite $invite, User $user): void
    {
        $this->assertBelongsToAssignment($assignment, $invite);

        $isInviter = $invite->inviter_id === $user->id || $invite->group?->created_by === $user->id;
        abort_unless($isInviter, 403, 'Only the group creator can cancel invites.');
        $this->assertActionable($invite);

        $invite->forceFill([
            'status' => AssignmentGroupInvite::STATUS_CANCELLED,
            'responded_at' => now(),
        ])->save();
    }

    /** Max counts accepted members AND pending invites against the cap. */
    private function assertWithinMax(Assignment $assignment, AssignmentGroup $group, int $additional): void
    {
        $max = $assignment->max_group_size;

        if ($max === null) {
            return;
        }

        $members = DB::table('assignment_user')
            ->where('group_id', $group->id)
            ->count();

        $pending = AssignmentGroupInvite::query()
            ->where('group_id', $group->id)
            ->where('status', AssignmentGroupInvite::STATUS_PENDING)
            ->count();

        $open = $max - $members - $pending;

        if ($additional > $open) {
            $open = max($open, 0);

            if ($open === 0) {
                abort(422, "Your group is full (max {$max} members).");
            }

            $plural = $open === 1 ? '' : 's';
            abort(422, "Only {$open} more invite{$plural} fit — the limit is {$max} members.");
        }
    }

    private function assertInvitable(Assignment $assignment, User $invitee): void
    {
        if (! $assignment->isVisibleTo($invitee)) {
            abort(422, "{$invitee->name} is not assigned to this activity.");
        }
        if ($this->groups->groupFor($assignment, $invitee)) {
            abort(422, "{$invitee->name} is already in a group for this assignment.");
        }

        // One live invite per student per assignment (service-level invariant).
        $hasPending = AssignmentGroupInvite::query()
            ->where('assignment_id', $assignment->id)
            ->where('invitee_id', $invitee->id)
            ->where('status', AssignmentGroupInvite::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            abort(422, "{$invitee->name} already has a pending invite for this assignment.");
        }
    }

    private function assertActionable(AssignmentGroupInvite $invite): void
    {
        if ($invite->status === AssignmentGroupInvite::STATUS_PENDING && $invite->expires_at?->isPast()) {
            $invite->forceFill([
                'status' => AssignmentGroupInvite::STATUS_EXPIRED,
                'responded_at' => now(),
            ])->save();

            abort(422, 'This invite expired when the due date passed.');
        }

        abort_unless($invite->status === AssignmentGroupInvite::STATUS_PENDING, 422, 'This invite has already been handled.');
    }

    private function assertBelongsToAssignment(Assignment $assignment, AssignmentGroupInvite $invite): void
    {
        abort_unless($invite->assignment_id === $assignment->id, 404);
    }

    private function notifyCreator(AssignmentGroup $group, Assignment $assignment, AssignmentGroupInvite $invite, User $member, bool $accepted): void
    {
        $creator = $group->creator;

        if (! $creator || $creator->id === $member->id) {
            return;
        }

        $creator->notify(new AssignmentGroupInviteNotification([
            'type' => $accepted ? 'invite_accepted' : 'invite_declined',
            'icon' => 'users',
            'title' => "{$member->name} ".($accepted ? 'accepted' : 'declined').' your group invite',
            'message' => $assignment->title,
            'meta' => 'Group activity',
            'href' => '/assignments',
            'invite_id' => $invite->id,
            'assignment_id' => $assignment->id,
        ]));
    }
}
