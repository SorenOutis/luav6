<?php

namespace App\Services;

use App\Models\Assignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the `assignment_user` roster in sync with section targeting.
 *
 * Assignments are given to sections, but grading, submissions and every
 * "X submitted / Y assigned" statistic in the admin panel read the
 * `assignment_user` pivot. Materialising a Pending row for each targeted
 * student keeps those numbers honest and lets an admin see who has not
 * handed anything in yet.
 *
 * Rows are only ever added, or removed when the student is no longer
 * targeted *and* has nothing to lose (no submission, no grade).
 */
class AssignmentRosterService
{
    /**
     * Sync the roster for a single assignment after its sections changed.
     */
    public function syncAssignment(Assignment $assignment): void
    {
        $sectionIds = $assignment->sections()->pluck('sections.id');

        $targetUserIds = $sectionIds->isEmpty()
            ? collect()
            : DB::table('section_user')
                ->whereIn('section_id', $sectionIds)
                ->distinct()
                ->pluck('user_id');

        $existing = DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->pluck('user_id');

        $this->insertPendingRows($assignment->id, $targetUserIds->diff($existing));
        $this->pruneUntouchedRows($assignment->id, $existing->diff($targetUserIds));
    }

    /**
     * Give a newly enrolled student every assignment already targeted at the
     * section they just joined.
     */
    public function syncNewMembership(int $userId, int $sectionId): void
    {
        $assignmentIds = DB::table('assignment_section')
            ->where('section_id', $sectionId)
            ->pluck('assignment_id');

        if ($assignmentIds->isEmpty()) {
            return;
        }

        $alreadyOn = DB::table('assignment_user')
            ->where('user_id', $userId)
            ->whereIn('assignment_id', $assignmentIds)
            ->pluck('assignment_id');

        $now = now();
        $rows = $assignmentIds->diff($alreadyOn)->map(fn ($assignmentId) => [
            'assignment_id' => $assignmentId,
            'user_id' => $userId,
            'submitted' => false,
            'status' => 'Pending',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('assignment_user')->insertOrIgnore($chunk->all());
        }
    }

    /**
     * Drop roster rows for a student who left a section, unless the same
     * assignment still reaches them through another section.
     */
    public function syncRemovedMembership(int $userId, int $sectionId): void
    {
        $assignmentIds = DB::table('assignment_section')
            ->where('section_id', $sectionId)
            ->pluck('assignment_id');

        if ($assignmentIds->isEmpty()) {
            return;
        }

        $remainingSectionIds = DB::table('section_user')
            ->where('user_id', $userId)
            ->pluck('section_id');

        $stillTargeted = $remainingSectionIds->isEmpty()
            ? collect()
            : DB::table('assignment_section')
                ->whereIn('assignment_id', $assignmentIds)
                ->whereIn('section_id', $remainingSectionIds)
                ->distinct()
                ->pluck('assignment_id');

        foreach ($assignmentIds->diff($stillTargeted) as $assignmentId) {
            $this->pruneUntouchedRows((int) $assignmentId, collect([$userId]));
        }
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function insertPendingRows(int $assignmentId, $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($userIds->chunk(500) as $chunk) {
            DB::table('assignment_user')->insertOrIgnore(
                $chunk->map(fn ($userId) => [
                    'assignment_id' => $assignmentId,
                    'user_id' => $userId,
                    'submitted' => false,
                    'status' => 'Pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all()
            );
        }
    }

    /**
     * Remove roster rows for users who are no longer targeted, but never
     * destroy work: rows with a submission, grade, points or feedback stay.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function pruneUntouchedRows(int $assignmentId, $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        foreach ($userIds->chunk(500) as $chunk) {
            DB::table('assignment_user')
                ->where('assignment_id', $assignmentId)
                ->whereIn('user_id', $chunk->all())
                ->where('submitted', false)
                ->whereNull('file_path')
                ->whereNull('grade')
                ->whereNull('feedback')
                ->whereNull('graded_at')
                ->where(fn ($query) => $query->whereNull('points')->orWhere('points', 0))
                ->where(fn ($query) => $query->whereNull('xp_earned')->orWhere('xp_earned', 0))
                ->delete();
        }
    }
}
