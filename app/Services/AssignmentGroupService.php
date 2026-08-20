<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Student-formed groups for group activities.
 *
 * One group per assignment; the shared submission lives on each member's
 * `assignment_user` row (mirrored by the `Submission` model hooks and by
 * {@see submitFile()}), so the student page and the admin grading UI keep
 * working without group awareness.
 *
 * A student can be in at most one group per assignment — enforced by the
 * unique (assignment_id, user_id) index on `assignment_user`.
 */
class AssignmentGroupService
{
    /**
     * Create a group for the given student (who becomes the creator).
     */
    public function createGroup(Assignment $assignment, User $creator): AssignmentGroup
    {
        $this->authorizeAssignment($assignment, $creator);

        if ($this->groupFor($assignment, $creator)) {
            abort(422, 'You are already in a group for this assignment.');
        }

        $this->ensureNotGraded($assignment, $creator);

        $group = AssignmentGroup::create([
            'assignment_id' => $assignment->id,
            'created_by' => $creator->id,
        ]);

        $this->ensureRosterRow($assignment, $creator);

        DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $creator->id)
            ->update(['group_id' => $group->id]);

        return $group;
    }

    /**
     * Add a member to the actor's group. Late joiners (group already
     * submitted) immediately see the shared file.
     */
    public function addMember(Assignment $assignment, User $actor, User $member): void
    {
        $group = $this->groupFor($assignment, $actor);

        if (! $group) {
            abort(403, 'You are not in a group for this assignment.');
        }
        if ($group->created_by !== $actor->id) {
            abort(403, 'Only the group creator can add members.');
        }

        $this->ensureNotGraded($assignment, $actor);

        if (! $assignment->isVisibleTo($member)) {
            abort(422, 'This student is not assigned to this activity.');
        }
        if ($this->groupFor($assignment, $member)) {
            abort(422, 'This student is already in a group for this assignment.');
        }

        $this->ensureRosterRow($assignment, $member);

        $shared = DB::table('assignment_user')
            ->where('group_id', $group->id)
            ->whereNotNull('file_path')
            ->orderByDesc('submitted_at')
            ->first();

        DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $member->id)
            ->update([
                'group_id' => $group->id,
                'submitted' => $shared ? true : false,
                'status' => $shared ? 'Submitted' : 'Pending',
                'file_path' => $shared?->file_path,
                'submitted_at' => $shared?->submitted_at,
            ]);
    }

    /**
     * Remove a member from the group (creator may remove anyone; any member
     * may leave). If the creator leaves, the role transfers to the earliest
     * remaining member; an emptied group is deleted.
     */
    public function removeMember(Assignment $assignment, User $actor, User $member): void
    {
        $group = $this->groupFor($assignment, $actor);

        if (! $group) {
            abort(403, 'You are not in a group for this assignment.');
        }

        $isCreator = $group->created_by === $actor->id;
        $isSelf = $actor->id === $member->id;

        if (! $isCreator && ! $isSelf) {
            abort(403, 'Only the group creator can remove members.');
        }

        $this->ensureNotGraded($assignment, $actor);

        $memberRow = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $member->id)
            ->where('group_id', $group->id)
            ->first();

        if (! $memberRow) {
            abort(422, 'This student is not in your group.');
        }

        // Reset the leaving member's row to a plain pending row.
        $memberRow->forceFill([
            'group_id' => null,
            'submitted' => false,
            'status' => 'Pending',
            'file_path' => null,
            'submitted_at' => null,
            'submitted_by' => null,
        ])->save();

        if ($group->created_by === $member->id) {
            $nextCreator = DB::table('assignment_user')
                ->where('group_id', $group->id)
                ->where('user_id', '!=', $member->id)
                ->orderBy('id')
                ->value('user_id');

            if ($nextCreator) {
                $group->update(['created_by' => $nextCreator]);
            } else {
                $group->delete();
            }
        }
    }

    /**
     * Reject a submit when the user's own row (or their group) is graded.
     * Must run BEFORE the file is written so a rejected request leaves no
     * partial state behind.
     */
    public function assertSubmittable(Assignment $assignment, User $user): void
    {
        $this->ensureNotGraded($assignment, $user);
    }

    /**
     * Share a freshly uploaded file with every member of the user's group.
     * Called by AssignmentController::store() after the file is stored.
     */
    public function submitFile(Assignment $assignment, User $user, string $path): void
    {
        $group = $this->groupFor($assignment, $user);

        if (! $group) {
            return;
        }

        $this->ensureNotGraded($assignment, $user);

        $now = now();

        DB::table('assignment_user')
            ->where('group_id', $group->id)
            ->update([
                'submitted' => true,
                'status' => 'Submitted',
                'file_path' => $path,
                'submitted_at' => $now,
                'submitted_by' => $user->id,
                'updated_at' => $now,
            ]);
    }

    /**
     * Students the actor can still add: members of the assignment's targeted
     * sections (same workspace context), excluding the actor and anyone who
     * already belongs to a group for this assignment.
     *
     * @return Collection<int, array{id: int, name: string, avatar: ?string, sections: array<int, string>}>
     */
    public function candidates(Assignment $assignment, User $user, ?string $query): Collection
    {
        $sectionIds = $assignment->sections()->pluck('sections.id');

        if ($sectionIds->isEmpty()) {
            return Collection::make();
        }

        $sectionMemberIds = DB::table('section_user')
            ->whereIn('section_id', $sectionIds)
            ->distinct()
            ->pluck('user_id');

        $alreadyGrouped = DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->whereNotNull('group_id')
            ->pluck('user_id');

        $eligibleIds = $sectionMemberIds
            ->diff([$user->id])
            ->diff($alreadyGrouped)
            ->values();

        if ($eligibleIds->isEmpty()) {
            return Collection::make();
        }

        $query = trim((string) $query);

        return User::query()
            ->whereIn('id', $eligibleIds)
            ->where('is_admin', false)
            ->where('is_banned', false)
            ->when($query !== '', fn (Builder $builder) => $builder->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%');
            }))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'avatar'])
            ->map(fn (User $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'avatar' => $candidate->avatar,
                'sections' => $candidate->sections()
                    ->whereIn('sections.id', $sectionIds)
                    ->pluck('sections.name')
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * The user's group for this assignment, if any.
     */
    public function groupFor(Assignment $assignment, User $user): ?AssignmentGroup
    {
        $groupId = DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->value('group_id');

        if (! $groupId) {
            return null;
        }

        return AssignmentGroup::query()->find($groupId);
    }

    private function authorizeAssignment(Assignment $assignment, User $user): void
    {
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment was not assigned to your section.');
        }
    }

    /**
     * Groups are locked once the user's own submission (or their group's
     * submission) has been graded.
     */
    private function ensureNotGraded(Assignment $assignment, User $user): void
    {
        $groupId = DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->value('group_id');

        $graded = DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where(function ($query) use ($user, $groupId) {
                $query->where('user_id', $user->id);

                if ($groupId) {
                    $query->orWhere('group_id', $groupId);
                }
            })
            ->where('status', 'Graded')
            ->exists();

        if ($graded) {
            abort(403, 'This assignment has already been graded and the group is locked.');
        }
    }

    private function ensureRosterRow(Assignment $assignment, User $user): void
    {
        $now = now();

        DB::table('assignment_user')->insertOrIgnore([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'submitted' => false,
            'status' => 'Pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
