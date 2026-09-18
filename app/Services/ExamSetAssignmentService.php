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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands out exam sets and resolves which parts a student may see.
 *
 * An exam can ship as several interchangeable sets (Set A, Set B, …). A
 * student is given a set the first time they open the exam, drawn from a
 * shuffled deck of the exam's sets, and keeps that set for the whole attempt,
 * so reloading or resuming never swaps their questions.
 *
 * The deck holds only the sets that actually contain questions, shuffled once
 * per exam and section, and each student is dealt the least-used set of that
 * deck — so the order is scrambled (not the predictable "first opener always
 * gets Set A") and a section is split evenly across the sets. See dealOrder()
 * and dealableSets().
 *
 * Students who have not answered anything yet are re-dealt whenever the exam's
 * set list changes (see redealUnstarted()), so an exam that was published with
 * one set can still be turned into a multi-set exam afterwards.
 */
class ExamSetAssignmentService
{
    /**
     * Sets in stored order (sort_order, then id).
     *
     * ⚠️ Nothing here is memoised on the instance. Under Octane the controller
     * that injects this service is cached on the Route object and lives for the
     * whole worker, so an instance property would survive across requests: a
     * worker that first saw the exam while it shipped a single set would keep
     * dealing that one set to every later student — and a memoised assignment
     * would make the service skip the database write entirely. The queries
     * below are single indexed lookups; correctness wins.
     *
     * @return Collection<int, ExamSet>
     */
    public function sets(Exam $exam): Collection
    {
        // Callers that page through exams eager-load `sets`; honour that so a
        // listing does not fan out into one query per exam.
        return $exam->relationLoaded('sets')
            ? $exam->sets
            : $exam->sets()->get();
    }

    /**
     * The sets that may actually be dealt: the ones that own at least one part.
     *
     * A set with no questions is a shell the teacher has not filled in yet
     * (raising "Number of sets" creates them empty, and every bulk write path —
     * CSV import, AI drafts, parts created without a set — defaults to the
     * first set). Dealing one of those hands a student a blank exam, so the
     * deck is drawn from the sets that are genuinely available.
     *
     * If no set has any parts yet the exam has no questions at all; the full
     * list is returned so the labelling behaves exactly as before.
     *
     * @return Collection<int, ExamSet>
     */
    public function dealableSets(Exam $exam): Collection
    {
        $sets = $this->sets($exam);

        if ($sets->count() < 2) {
            return $sets;
        }

        $withParts = ExamPart::query()
            ->where('exam_id', $exam->getKey())
            ->whereNotNull('exam_set_id')
            ->distinct()
            ->pluck('exam_set_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        // filterParts() shows parts that carry no set at all on the first set,
        // so legacy rows keep that set dealable too.
        $hasOrphanParts = ExamPart::query()
            ->where('exam_id', $exam->getKey())
            ->whereNull('exam_set_id')
            ->exists();

        if ($hasOrphanParts) {
            $withParts[] = (int) $sets->first()->id;
        }

        $filtered = $sets->filter(
            fn (ExamSet $set): bool => in_array((int) $set->id, $withParts, true)
        )->values();

        return $filtered->isEmpty() ? $sets : $filtered;
    }

    /**
     * Per-exam set + part totals for a listing, in three queries.
     *
     * `set` is only populated once the student has actually been handed a set,
     * so browsing the list never reserves a deal slot. Until then the totals
     * describe the first set, which matches the shuffled deck's first slot
     * whenever the sets are parallel (equal part counts).
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
            // An unassigned student is previewed against the first set that
            // actually holds questions — an empty shell set would otherwise
            // report "0 parts" on their card.
            $previewSet = $examSets->first(
                fn (ExamSet $set): bool => (int) ($examCounts[(int) $set->id] ?? 0) > 0
            ) ?? $firstSet;
            $assignment = $assignments->get($examId);
            $set = $assignment !== null ? $examSets->firstWhere('id', (int) $assignment->exam_set_id) : null;

            if ($set === null) {
                $set = $previewSet;
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
     * cannot consume a deal slot.
     */
    public function assignedSet(Exam $exam, User $user): ?ExamSet
    {
        return $this->resolve($exam, $user, assign: false);
    }

    /**
     * The set this student should work on, dealing them one from the shuffled
     * deck the first time they open the exam.
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

    /**
     * The order the exam's sets are dealt in.
     *
     * Only sets that actually hold questions take part: the deck follows the
     * sets that are available, so a half-built exam never hands a student a
     * blank set.
     *
     * The order is a deterministic, unbiased shuffle: each set is keyed by a
     * hash of "{seed}:{set id}" and the deck is sorted by that key. Every
     * request for the same exam therefore yields the same deal (a student's set
     * never changes) while the order is scrambled rather than the predictable
     * Set A, Set B, … the students could otherwise guess. A single-set exam
     * simply deals that one set.
     *
     * The previous implementation used the low bits of a linear congruential
     * generator, which is heavily biased: with four sets the fourth one landed
     * in the first slot roughly four times less often than the first. Hashing
     * each set independently removes that skew.
     *
     * Kept public so read-only screens (and tests) can show or assert the same
     * order the assignment uses without triggering an assignment themselves.
     *
     * @return Collection<int, ExamSet>
     */
    public function dealOrder(Exam $exam, ?Collection $sets = null): Collection
    {
        $items = ($sets ?? $this->dealableSets($exam))->values();

        if ($items->count() < 2) {
            return $items;
        }

        $seed = $this->rotationSeed($exam);

        return $items
            ->sortBy(fn (ExamSet $set): string => hash('sha256', $seed.':'.$set->id), SORT_STRING)
            ->values();
    }

    /**
     * Forget the set handed to every student who has not started this exam yet.
     *
     * The set is handed out the first time a student *opens* the exam, which is
     * often days before they answer anything — so a class that peeked while the
     * exam still had a single set stays pinned to that set forever, even after
     * the teacher adds Set B and Set C. Dropping the untouched rows lets those
     * students be re-dealt from the current deck on their next visit.
     *
     * Students with a submission, a saved draft or a running timer are never
     * touched: resuming an attempt must always show the same questions.
     *
     * @return int Number of students who will be re-dealt.
     */
    public function redealUnstarted(Exam $exam): int
    {
        $examId = $exam->getKey();

        $started = ExamSubmission::query()
            ->where('exam_id', $examId)
            ->distinct()
            ->pluck('user_id')
            ->merge(
                ExamAnswerDraft::query()->where('exam_id', $examId)->distinct()->pluck('user_id')
            )
            ->merge(
                ExamLiveSession::query()->where('exam_id', $examId)->distinct()->pluck('user_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $deleted = ExamSetAssignment::query()
            ->where('exam_id', $examId)
            ->when($started !== [], fn ($query) => $query->whereNotIn('user_id', $started))
            ->delete();

        return (int) $deleted;
    }

    private function resolve(Exam $exam, User $user, bool $assign): ?ExamSet
    {
        $sets = $this->sets($exam);

        if ($sets->isEmpty() || $user->is_admin) {
            return null;
        }

        $assignment = $this->assignmentFor($exam, $user);

        if ($assignment !== null) {
            $set = $sets->firstWhere('id', (int) $assignment->exam_set_id);

            if ($set !== null) {
                return $set;
            }

            // The set was deleted underneath the student — hand them a new one.
        }

        // Backfill for work that predates the assignment row (or for an exam
        // that gained its sets after students had already started): keep the
        // student on the set they already answered.
        $derived = $this->deriveFromExistingWork($exam, $user, $sets);

        if ($derived !== null) {
            $this->persist($exam, $user, $derived, $assignment);

            return $derived;
        }

        if (! $assign) {
            return null;
        }

        return $this->assignNext($exam, $user, $sets);
    }

    /**
     * Deal a set to a student: the least-used set of the exam's shuffled deck,
     * ties broken by the deck order.
     *
     * Counting what each set already holds (instead of "number of assignments
     * modulo number of sets") keeps the split even and self-healing: it stays
     * balanced when a set is added mid-flight, when assignments are re-dealt,
     * or when a student is removed.
     *
     * The read-modify-write runs inside a transaction and the unique
     * (exam_id, user_id) index is the real guard, so two students starting at
     * the same instant can never end up sharing a row — the loser of the race
     * simply re-reads the winner's assignment.
     */
    private function assignNext(Exam $exam, User $user, Collection $sets): ?ExamSet
    {
        $deck = $this->dealOrder($exam);

        if ($deck->isEmpty()) {
            return $sets->first();
        }

        try {
            return DB::transaction(function () use ($exam, $user, $sets, $deck): ?ExamSet {
                $existing = ExamSetAssignment::query()
                    ->where('exam_id', $exam->getKey())
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $set = $sets->firstWhere('id', (int) $existing->exam_set_id) ?? $deck->first();

                    // The set was deleted (and its questions with it): move the
                    // student to a set that still exists so the stale id is not
                    // handed out again on the next request.
                    if ((int) $existing->exam_set_id !== (int) $set->id) {
                        $existing->forceFill(['exam_set_id' => $set->getKey()])->save();
                    }

                    return $set;
                }

                $set = $this->leastUsed($exam, $deck);

                $this->persist($exam, $user, $set, null);

                return $set;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests for the same student raced: the winner's row is the
            // truth.
            $existing = $this->assignmentFor($exam, $user);

            return $existing !== null
                ? ($sets->firstWhere('id', (int) $existing->exam_set_id) ?? $deck->first())
                : $deck->first();
        } catch (QueryException $exception) {
            // Anything else (missing migration, deadlock, lock timeout) used to
            // degrade silently to the first set for every student, which is
            // indistinguishable from "sets are not working". Make the noise,
            // and spread the fallback across the deck so a failing write does
            // not put the whole class on Set A.
            Log::error('Exam set assignment failed; falling back to a deterministic set.', [
                'exam_id' => $exam->getKey(),
                'user_id' => $user->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $existing = $this->assignmentFor($exam, $user);

            if ($existing !== null) {
                return $sets->firstWhere('id', (int) $existing->exam_set_id) ?? $deck->first();
            }

            return $deck->get(crc32('user:'.$user->getKey()) % $deck->count()) ?? $deck->first();
        }
    }

    /**
     * The deck set that has been handed out the fewest times so far.
     *
     * @param  Collection<int, ExamSet>  $deck
     */
    private function leastUsed(Exam $exam, Collection $deck): ExamSet
    {
        $counts = [];

        ExamSetAssignment::query()
            ->where('exam_id', $exam->getKey())
            ->whereIn('exam_set_id', $deck->pluck('id')->all())
            ->selectRaw('exam_set_id, COUNT(*) as total')
            ->groupBy('exam_set_id')
            ->get()
            ->each(function (object $row) use (&$counts): void {
                $counts[(int) $row->exam_set_id] = (int) $row->total;
            });

        // sortBy() is stable, so sets that are tied keep their deck order and
        // the first N students still walk the shuffled deck slot by slot.
        return $deck
            ->sortBy(fn (ExamSet $set): int => $counts[(int) $set->id] ?? 0)
            ->first();
    }

    /**
     * One stable shuffle seed per exam + section.
     *
     * Basing the seed on the section keeps each section's deal independent
     * (the same exam always shuffles the same way for that section), while a
     * global exam without a section falls back to a single shuffle shared by
     * everyone taking it.
     */
    private function rotationSeed(Exam $exam): int
    {
        $pool = $exam->section_id !== null ? 'section:'.$exam->section_id : 'exam';

        return crc32($pool.':'.$exam->getKey()) & 0x7FFFFFFF;
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
