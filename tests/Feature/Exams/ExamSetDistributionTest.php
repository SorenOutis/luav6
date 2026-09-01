<?php

/**
 * Exam set distribution: the deck must follow the sets that are actually
 * available, split a class evenly across them, and stay reachable for an exam
 * that only grew its extra sets after the students had already browsed it.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSetAssignment;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamSetAssignmentService;

use function Pest\Laravel\actingAs;

/**
 * A published, section-scoped exam with no sets of its own yet.
 *
 * @return array{0: Exam, 1: Section}
 */
function distributionExam(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $exam = Exam::factory()->published()->forSection($section)->create();

    return [$exam, $section];
}

function distributionSet(Exam $exam, string $title, bool $withParts = true): ExamSet
{
    $set = ExamSet::factory()->forExam($exam)->titled($title)->create();

    if ($withParts) {
        ExamPart::factory()
            ->forSet($set)
            ->multipleChoice(count: 1, correctIndex: 1, points: 2)
            ->create(['title' => $title.' part', 'sort_order' => 0]);
    }

    return $set->refresh();
}

function distributionStudent(Section $section): User
{
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $section->season_id]);

    return $student;
}

/**
 * @return array<int, int> exam_set_id => number of students holding it
 */
function dealtCounts(Exam $exam): array
{
    return ExamSetAssignment::query()
        ->where('exam_id', $exam->getKey())
        ->get()
        ->countBy('exam_set_id')
        ->map(fn ($count): int => (int) $count)
        ->all();
}

it('splits a class evenly across every set that has questions', function () {
    [$exam, $section] = distributionExam();
    distributionSet($exam, 'Set A');
    distributionSet($exam, 'Set B');
    distributionSet($exam, 'Set C');

    for ($i = 0; $i < 6; $i++) {
        actingAs(distributionStudent($section))->get("/exams/{$exam->id}")->assertOk();
    }

    $counts = dealtCounts($exam);

    expect(array_sum($counts))->toBe(6)
        ->and(count($counts))->toBe(3)
        ->and(array_values($counts))->each->toBe(2);
});

it('never deals a set that has no questions yet', function () {
    [$exam, $section] = distributionExam();
    $filled = [distributionSet($exam, 'Set A')->id, distributionSet($exam, 'Set B')->id];
    $empty = distributionSet($exam, 'Set C', withParts: false);

    for ($i = 0; $i < 6; $i++) {
        actingAs(distributionStudent($section))->get("/exams/{$exam->id}")->assertOk();
    }

    $counts = dealtCounts($exam);

    expect(array_keys($counts))->not->toContain($empty->id)
        ->and(array_sum($counts))->toBe(6);

    foreach ($filled as $setId) {
        expect($counts[$setId] ?? 0)->toBe(3);
    }
});

it('re-deals students who only browsed the exam when a set is added later', function () {
    [$exam, $section] = distributionExam();
    $setA = distributionSet($exam, 'Set A');

    // The whole class peeks at the exam while it still ships a single set, so
    // every one of them is pinned to Set A.
    $students = collect(range(1, 4))->map(fn (): User => distributionStudent($section));

    $students->each(fn (User $student) => actingAs($student)->get("/exams/{$exam->id}")->assertOk());

    expect(dealtCounts($exam))->toBe([$setA->id => 4]);

    // The teacher then builds the second version.
    $setB = distributionSet($exam, 'Set B');

    // Nobody answered anything, so the stale hand-outs are released…
    expect(ExamSetAssignment::query()->where('exam_id', $exam->id)->count())->toBe(0);

    // …and the class is split across both sets on their next visit.
    $students->each(fn (User $student) => actingAs($student)->get("/exams/{$exam->id}")->assertOk());

    $counts = dealtCounts($exam);

    expect($counts[$setA->id] ?? 0)->toBe(2)
        ->and($counts[$setB->id] ?? 0)->toBe(2);
});

it('keeps a student who already started on their set when a set is added', function () {
    [$exam, $section] = distributionExam();
    $setA = distributionSet($exam, 'Set A');
    $student = distributionStudent($section);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    ExamSubmission::factory()
        ->forSubmission($student, $exam, $setA->parts()->firstOrFail())
        ->create();

    distributionSet($exam, 'Set B');

    expect(ExamSetAssignment::query()
        ->where('exam_id', $exam->id)
        ->where('user_id', $student->id)
        ->value('exam_set_id'))->toBe($setA->id);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    expect(app(ExamSetAssignmentService::class)->assignedSet($exam->fresh(), $student)?->id)
        ->toBe($setA->id);
});

it('refuses work on a part that belongs to another set', function () {
    [$exam, $section] = distributionExam();
    $setA = distributionSet($exam, 'Set A');
    $setB = distributionSet($exam, 'Set B');
    $student = distributionStudent($section);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    $assigned = (int) ExamSetAssignment::query()
        ->where('exam_id', $exam->id)
        ->where('user_id', $student->id)
        ->value('exam_set_id');

    $foreign = ($assigned === $setA->id ? $setB : $setA)->parts()->firstOrFail();

    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$foreign->id}/start")
        ->assertForbidden();

    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$foreign->id}/submit", [
            'answers' => [['question_number' => 1, 'answer' => 1]],
        ])
        ->assertForbidden();

    expect(ExamSubmission::query()->where('user_id', $student->id)->count())->toBe(0);
});

it('shuffles the deck instead of following the stored set order', function () {
    // The deal order must not simply be Set A, Set B, … for every exam: over a
    // handful of exams the shuffled deck has to start with something other than
    // the first stored set at least once.
    $service = app(ExamSetAssignmentService::class);
    $startedElsewhere = 0;

    for ($i = 0; $i < 8; $i++) {
        [$exam] = distributionExam();
        $first = distributionSet($exam, 'Set A');
        distributionSet($exam, 'Set B');
        distributionSet($exam, 'Set C');

        if ($service->dealOrder($exam->fresh())->first()->id !== $first->id) {
            $startedElsewhere++;
        }
    }

    expect($startedElsewhere)->toBeGreaterThan(0);
});
it('does not remember a stale set list on a long-lived worker', function () {
    // Under Octane the controller — and the service it injects — is cached on
    // the Route object and outlives the request, so a service that memoised the
    // exam's sets (or a student's assignment) would keep dealing the set list
    // it saw first and stop writing assignment rows altogether.
    [$exam, $section] = distributionExam();
    $setA = distributionSet($exam, 'Set A');
    $student = distributionStudent($section);

    $service = app(ExamSetAssignmentService::class);

    expect($service->resolveSet($exam, $student)?->id)->toBe($setA->id);

    $setB = distributionSet($exam, 'Set B');

    // The untouched hand-out was released…
    expect(ExamSetAssignment::query()->where('exam_id', $exam->id)->count())->toBe(0)
        // …and the very same instance must now see both sets…
        ->and($service->dealableSets($exam->fresh())->pluck('id')->all())
        ->toBe([$setA->id, $setB->id]);

    // …and deal (and persist) again rather than replaying what it remembered.
    expect($service->resolveSet($exam->fresh(), $student))->not->toBeNull()
        ->and(ExamSetAssignment::query()->where('exam_id', $exam->id)->count())->toBe(1);
});
