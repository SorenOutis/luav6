<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamUserBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Blocks individual students from a single exam.
 *
 * An exam's audience is its section (or every student when it is unassigned);
 * the block list subtracts named students from that audience. A block is a
 * visibility rule, not a grading one: the student's submissions, drafts and XP
 * are untouched, so the teacher's reports and the answer key stay intact.
 *
 * Enforcement lives in `Exam::scopeNotBlockedBy()` / `Exam::isBlockedFor()`;
 * this service only owns the admin side — who may be picked, and writing the
 * list.
 */
class ExamBlockService
{
    /**
     * Replace the exam's block list, stamping who wrote each block.
     *
     * `blocked_by` is an audit field, so it is only written for rows that have
     * never been attributed: `sync()` would otherwise push the attributes
     * through `updateExistingPivot()` and rewrite the original blocker every
     * time anyone re-saved the form.
     *
     * @param  array<int, int|string>  $userIds
     */
    public function sync(Exam $exam, array $userIds): void
    {
        $userIds = collect($userIds)
            ->map(fn (int|string $userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => $userId > 0)
            ->unique()
            ->values()
            ->all();

        $exam->blockedUsers()->sync($userIds);

        ExamUserBlock::query()
            ->where('exam_id', $exam->getKey())
            ->whereIn('user_id', $userIds)
            ->whereNull('blocked_by')
            ->update(['blocked_by' => auth()->id()]);
    }

    /**
     * Students that may be blocked from an exam.
     *
     * Mirrors the student picker in Grades: the enrollees of the exam's
     * section when one is chosen, otherwise every student the admin manages.
     * Admins are never offered — blocking an admin is meaningless, and they are
     * exempt from every block anyway.
     *
     * @return array<int, string> student id => name
     */
    public function optionsFor(?int $sectionId): array
    {
        return User::query()
            ->where('is_admin', false)
            ->when(
                $sectionId,
                fn (Builder $query): Builder => $query->whereHas(
                    'sections',
                    fn (Builder $query): Builder => $query->where('sections.id', $sectionId),
                ),
                fn (Builder $query): Builder => $query->forWorkspace(),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
