<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAnswerDraft;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSetAssignment;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hands out exam sets and resolves which parts a student may see.
 *
 * An exam can ship as several interchangeable sets (Set A, Set B, …). A
 * student is given a set the first time they open the exam — the first student
 * gets Set A, the second Set B, the third Set A again when there are only two
 * — and keeps that set for the whole attempt, so reloading or resuming never
 * swaps their questions.
 */
class ExamSetAssignmentService
{
    /** @var array<int, Collection<int, ExamSet>> Sets memoised per exam id. */
    private array $sets = [];

    /** @var array<string, ?ExamSet> Resolved sets, keyed by "{exam}:{user}". */
    private array $resolved = [];

    /**
     * Sets in rotation order.
     *
     * @return Collection<int, ExamSet>
     */
    public function sets(Exam $exam): Collection
    {
        // Callers that page through exams eager-load `sets`; honour that so a
        // listing does not fan out into one query per exam.
        return $this->sets[$exam->getKey()] ??= $exam->relationLoaded('sets')
            ? $exam->sets
            : $exam->sets()->get();
    }

    /**
     * Per-exam set + part totals for a listing, in three queries.
     *
     * `set` is only populated once the student has actually been handed a set,
     * so browsing the list never reserves a rotation slot. Until then the
     * totals describe the first set, which is the set they will be given.
     *
     * @param  array<int, int>  $examIds
     * @return array<int, array{set: ?ExamSet, total_parts: int}>
     */
    public function summariesFor(User $user, array $examIds): array
    {
        $examIds = array_values(array_unique(array_map('intval', $examIds)));

        if ($examIds === []) {
            return [];
        }

        $sets = ExamSet::query()
            ->whereIn('exam_id', $examIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('exam_id');

        // exam_id => [set_id => part count]. Parts orphaned without a set are
        // bucketed under 0 and counted with the first set, matching
        // filterParts() so a listing can never disagree with the exam page.
        $counts = [];

        ExamPart::query()
            ->whereIn('exam_id', $examIds)
            ->selectRaw('exam_id, exam_set_id, COUNT(*) as total')
            ->groupBy('exam_id', 'exam_set_id')
            ->get()
            ->each(function (object $row) use (&$counts): void {
                $counts[(int) $row->exam_id][(int) ($row->exam_set_id ?? 0)] = (int) $row->total;
            });

        $assignments = $user->is_admin
            ? collect()
            : ExamSetAssignment::query()
                ->where('user_id', $user->getKey())
                ->whereIn('exam_id', $examIds)
                ->get()
                ->keyBy('exam_id');

        $summaries = [];

        foreach ($examIds as $examId) {
            $examSets = $sets->get($examId, collect());
            $examCounts = $counts[$examId] ?? [];

            if ($examSets->isEmpty() || $user->is_admin) {
                $summaries[$examId] = ['set' => null, 'total_parts' => (int) array_sum($examCounts)];

                continue;
            }

            $firstSet = $examSets->first();
            $assignment = $assignments->get($examId);
            $set = $assignment !== null ? $examSets->firstWhere('id', (int) $assignment->exam_set_id) : null;

            if ($set === null) {
                $set = $firstSet;
            }

            $total = (int) ($examCounts[(int) $set->id] ?? 0);

            if ((int) $set->id === (int) $firstSet->id) {
                $total += (int) ($examCounts[0] ?? 0);
            }

            $summaries[$examId] = [
                'set' => $assignment !== null && $examSets->contains('id', (int) $assignment->exam_set_id) ? $set : null,
                'total_parts' => $total,
            ];
        }

        return $summaries;
    }

    /**
     * The set this student was already given, without handing out a new one.
     *
     * Use this for read-only screens (exam listings) so that merely browsing
     * cannot consume a rotation slot.
     */
    public function assignedSet(Exam $exam, User $user): ?ExamSet
    {
        return $this->resolve($exam, $user, assign: false);
    }

    /**
     * The set this student should work on, assigning one in rotation the first
     * time they open the exam.
     */
    public function resolveSet(Exam $exam, User $user): ?ExamSet
    {
        return $this->resolve($exam, $user, assign: true);
    }

    /**
     * Every part of the exam this student may see.
     *
     * @return Collection<int, ExamPart>
     */
    public function partsFor(Exam $exam, User $user, bool $assign = true): Collection
    {
        // Admins audit every set, so nothing is filtered for them.
        if ($user->is_admin) {
            return $this->structure($exam);
        }

        $set = $this->resolve($exam, $user, assign: $assign);

        return $this->filterParts($exam, $this->structure($exam), $set);
    }

    /**
     * Narrow a full exam structure down to one set.
     *
     * A null `$set` falls back to the first set so progress counters stay
     * meaningful before a student has been assigned, and parts orphaned
     * without a set stay visible on the first set instead of disappearing.
     *
     * @param  Collection<int, ExamPart>  $parts
     * @return Collection<int, ExamPart>
     */
    public function filterParts(Exam $exam, Collection $parts, ?ExamSet $set): Collection
    {
        $sets = $this->sets($exam);

        if ($sets->isEmpty()) {
            return $parts->values();
        }

        $set ??= $sets->first();
        $firstSetId = (int) $sets->first()->id;

        return $parts
            ->filter(function (ExamPart $part) use ($set, $firstSetId): bool {
                if ($part->exam_set_id === null) {
                    return (int) $set->id === $firstSetId;
                }

                return (int) $part->exam_set_id === (int) $set->id;
            })
            ->values();
    }

    /**
     * The raw (cached) exam structure: every part of every set.
     *
     * @return Collection<int, ExamPart>
     */
    public function structure(Exam $exam): Collection
    {
        return Cache::remember("exam_structure_{$exam->getKey()}", 3600, function () use ($exam): Collection {
            return $exam->parts()->orderBy('sort_order')->get();
        });
    }

    private function resolve(Exam $exam, User $user, bool $assign): ?ExamSet
    {
        // Assigning is idempotent, so a request that asks for the set several
        // times (show page → XP award) only runs the lookup once.
        $key = $exam->getKey().':'.$user->getKey();

        if ($assign && array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $sets = $this->sets($exam);

        if ($sets->isEmpty() || $user->is_admin) {
            return $this->resolved[$key] ??= null;
        }

        $assignment = $this->assignmentFor($exam, $user);

        if ($assignment !== null) {
            $set = $sets->firstWhere('id', (int) $assignment->exam_set_id);

            if ($set !== null) {
                return $this->resolved[$key] = $set;
            }

            // The set was deleted underneath the student — hand them a new one.
        }

        // Backfill for work that predates the assignment row (or for an exam
        // that gained its sets after students had already started): keep the
        // student on the set they already answered.
        $derived = $this->deriveFromExistingWork($exam, $user, $sets);

        if ($derived !== null) {
            $this->persist($exam, $user, $derived, $assignment);

            return $this->resolved[$key] = $derived;
        }

        if (! $assign) {
            return null;
        }

        return $this->resolved[$key] = $this->assignNext($exam, $user, $sets);
    }

    /**
     * Rotate to the next set: the Nth student to start gets set N (modulo the
     * number of sets).
     *
     * The read-modify-write runs inside a transaction and the unique
     * (exam_id, user_id) index is the real guard, so two students starting at
     * the same instant can never end up sharing a row — the loser of the race
     * simply re-reads the winner's assignment.
     */
    private function assignNext(Exam $exam, User $user, Collection $sets): ?ExamSet
    {
        try {
            return DB::transaction(function () use ($exam, $user, $sets): ?ExamSet {
                $existing = ExamSetAssignment::query()
                    ->where('exam_id', $exam->getKey())
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $set = $sets->firstWhere('id', (int) $existing->exam_set_id) ?? $sets->first();

                    // The set was deleted (and its questions with it): move the
                    // student to a set that still exists so the stale id is not
                    // handed out again on the next request.
                    if ((int) $existing->exam_set_id !== (int) $set->id) {
                        $existing->forceFill(['exam_set_id' => $set->getKey()])->save();
                    }

                    return $set;
                }

                $handedOut = ExamSetAssignment::query()
                    ->where('exam_id', $exam->getKey())
                    ->lockForUpdate()
                    ->count();

                $set = $sets->values()->get($handedOut % $sets->count()) ?? $sets->first();

                $this->persist($exam, $user, $set, null);

                return $set;
            });
        } catch (QueryException) {
            $existing = $this->assignmentFor($exam, $user);

            return $existing !== null
                ? $sets->firstWhere('id', (int) $existing->exam_set_id)
                : $sets->first();
        }
    }

    /**
     * The set implied by work the student already did (submissions, saved
     * drafts or a running timer).
     *
     * @param  Collection<int, ExamSet>  $sets
     */
    private function deriveFromExistingWork(Exam $exam, User $user, Collection $sets): ?ExamSet
    {
        $partIds = ExamSubmission::query()
            ->where('user_id', $user->getKey())
            ->where('exam_id', $exam->getKey())
            ->pluck('exam_part_id')
            ->merge(
                ExamAnswerDraft::query()
                    ->where('user_id', $user->getKey())
                    ->where('exam_id', $exam->getKey())
                    ->pluck('exam_part_id')
            )
            ->merge(
                ExamLiveSession::query()
                    ->where('user_id', $user->getKey())
                    ->where('exam_id', $exam->getKey())
                    ->pluck('exam_part_id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($partIds->isEmpty()) {
            return null;
        }

        $setId = ExamPart::query()
            ->whereIn('id', $partIds)
            ->whereNotNull('exam_set_id')
            ->value('exam_set_id');

        if ($setId === null) {
            return null;
        }

        return $sets->firstWhere('id', (int) $setId);
    }

    private function persist(Exam $exam, User $user, ExamSet $set, ?ExamSetAssignment $assignment): ExamSetAssignment
    {
        if ($assignment !== null) {
            $assignment->forceFill(['exam_set_id' => $set->getKey()])->save();

            return $assignment;
        }

        return ExamSetAssignment::query()->create([
            'exam_id' => $exam->getKey(),
            'exam_set_id' => $set->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    private function assignmentFor(Exam $exam, User $user): ?ExamSetAssignment
    {
        return ExamSetAssignment::query()
            ->where('exam_id', $exam->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }
}
