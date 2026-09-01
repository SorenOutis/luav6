<?php

/**
 * Exam sets: an exam can ship as several interchangeable versions and each
 * student is dealt one of them from a shuffled deck the first time they open
 * it, keeping that set for the whole attempt.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSetAssignment;
use App\Models\ExamSubmission;
use App\Models\ExamXpAward;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamSetAssignmentService;
use App\Services\ExamXpAwardService;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * A published exam with `$sets` sets, each holding one part whose title names
 * the set ("Set A part", "Set B part", …).
 *
 * Part titles are used as the marker because they are serialized for a
 * published exam, whereas the answer key is deliberately not.
 *
 * @return array{0: Exam, 1: Collection<int, ExamSet>, 2: Section}
 */
function examSetsContext(int $sets = 2): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $exam = Exam::factory()->published()->forSection($section)->withSets($sets)->create();
    $examSets = $exam->sets()->orderBy('sort_order')->get();

    foreach ($examSets as $set) {
        ExamPart::factory()
            ->forSet($set)
            ->multipleChoice(count: 1, correctIndex: 1, points: 2)
            ->create([
                'title' => $set->title.' part',
                'sort_order' => 0,
            ]);
    }

    return [$exam, $examSets, $section];
}

function examSetsStudent(Section $section): User
{
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $section->season_id]);

    return $student;
}

function assignedSetId(Exam $exam, User $student): ?int
{
    $assignment = ExamSetAssignment::query()
        ->where('exam_id', $exam->id)
        ->where('user_id', $student->id)
        ->first();

    return $assignment?->exam_set_id;
}

/**
 * The shuffled deal order for an exam, so tests can assert the Nth student
 * receives the Nth slot without hard-coding Set A / Set B.
 *
 * @return Collection<int, ExamSet>
 */
function dealtSets(Exam $exam): Collection
{
    return app(ExamSetAssignmentService::class)->dealOrder($exam);
}

it('deals every set once before the deck repeats, in the shuffled order', function () {
    [$exam, $examSets, $section] = examSetsContext(3);
    $deck = dealtSets($exam);

    // The deal is a permutation of the exam's sets and is stable across calls
    // (the Nth student always draws the same slot).
    expect($deck->count())->toBe(3)
        ->and($deck->pluck('id')->sort()->values()->all())->toBe($examSets->pluck('id')->sort()->values()->all())
        ->and(dealtSets($exam)->pluck('id')->all())->toBe($deck->pluck('id')->all());

    $students = [
        examSetsStudent($section),
        examSetsStudent($section),
        examSetsStudent($section),
        examSetsStudent($section),
    ];

    foreach ($students as $index => $student) {
        actingAs($student)->get("/exams/{$exam->id}")->assertOk();

        expect(assignedSetId($exam, $student))->toBe($deck->get($index % 3)->id);
    }
});

it('keeps the set a student was handed on every later visit', function () {
    [$exam, $examSets, $section] = examSetsContext(3);
    $student = examSetsStudent($section);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();
    $first = assignedSetId($exam, $student);

    // A second student takes a slot in between: reloading must not reshuffle.
    actingAs(examSetsStudent($section))->get("/exams/{$exam->id}")->assertOk();
    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    expect($first)->not->toBeNull()
        ->and(assignedSetId($exam, $student))->toBe($first)
        ->and(ExamSetAssignment::query()->where('user_id', $student->id)->count())->toBe(1);
});

it('only shows the questions of the set the student was handed', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $deck = dealtSets($exam);

    $first = examSetsStudent($section);
    $second = examSetsStudent($section);

    $firstPage = actingAs($first)->get("/exams/{$exam->id}")->getContent();
    $secondPage = actingAs($second)->get("/exams/{$exam->id}")->getContent();

    $firstSet = $deck->get(0);
    $secondSet = $deck->get(1);

    expect($firstPage)->toContain($firstSet->title.' part')
        ->and($firstPage)->not->toContain($secondSet->title.' part')
        ->and($secondPage)->toContain($secondSet->title.' part')
        ->and($secondPage)->not->toContain($firstSet->title.' part');
});

it('tells the student which set they are taking', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $student = examSetsStudent($section);

    $expected = dealtSets($exam)->first()->title;

    actingAs($student)
        ->get("/exams/{$exam->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exams/Show')
            ->where('exam.set.title', $expected)
            ->has('exam.parts', 1));
});

it('shows the set on the exams page and counts only that set’s parts', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $student = examSetsStudent($section);

    // Before starting, nothing is assigned and no rotation slot is consumed…
    actingAs($student)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activities/Index')
            ->where('examsBySeason.0.exams.0.total_parts', 1)
            ->where('examsBySeason.0.exams.0.set', null));

    expect(ExamSetAssignment::query()->count())->toBe(0);

    // …and once the exam is opened, the card names the set and stays on it.
    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    $expected = dealtSets($exam)->first()->title;

    actingAs($student)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            ->where('examsBySeason.0.exams.0.set.title', $expected)
            ->where('examsBySeason.0.exams.0.total_parts', 1));
});

it('keeps a student on the set they already answered', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $setB = $examSets->last();
    $student = examSetsStudent($section);

    // Work that predates the assignment row (or an exam that gained sets after
    // students had already started) must not be reassigned.
    ExamSubmission::factory()
        ->forSubmission($student, $exam, $setB->parts()->firstOrFail())
        ->create();

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    expect(assignedSetId($exam, $student))->toBe($setB->id);
});

it('parks parts created without a set in the exam’s first set', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $setA = $examSets->first();

    // CSV imports, AI drafts and older write paths create parts with only an
    // exam id; they belong to the first set so that set's students can reach
    // them.
    $part = ExamPart::factory()
        ->forExam($exam)
        ->identification(['Manila'])
        ->create(['title' => 'Imported part']);

    expect($part->exam_set_id)->toBe($setA->id)
        ->and($exam->sets()->count())->toBe(2);

    // The imported part lives on the first set, so whichever student the
    // shuffled deck deals that set to is the one who can reach it — not
    // necessarily the first to open the exam.
    $slot = dealtSets($exam)->pluck('id')->search($setA->id);

    $students = [];
    for ($i = 0; $i <= $slot; $i++) {
        $students[] = examSetsStudent($section);
    }

    foreach ($students as $student) {
        actingAs($student)->get("/exams/{$exam->id}")->assertOk();
    }

    expect(actingAs($students[$slot])->get("/exams/{$exam->id}")->getContent())
        ->toContain('Imported part');
});

it('names sets automatically in rotation order', function () {
    $exam = Exam::factory()->published()->create();

    // The admin panel adds sets without a title; they are named in order.
    ExamSet::factory()->forExam($exam)->count(3)->create();

    expect($exam->sets()->orderBy('sort_order')->pluck('title')->all())
        ->toBe(['Set A', 'Set B', 'Set C']);
});

it('awards XP once the student has finished every part of their own set', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $assigned = dealtSets($exam)->first();
    $student = examSetsStudent($section);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    expect(assignedSetId($exam, $student))->toBe($assigned->id);

    actingAs($student)->post("/exams/{$exam->id}/parts/{$assigned->parts()->firstOrFail()->id}/submit", [
        'answers' => [['question_number' => 1, 'answer' => 1]],
    ])->assertRedirect();

    // One part of two sets is enough — the other set is not this student's.
    expect(ExamXpAward::query()->where('user_id', $student->id)->where('exam_id', $exam->id)->exists())
        ->toBeTrue();
});

it('does not count the other sets’ parts towards a student’s completion', function () {
    [$exam, $examSets, $section] = examSetsContext(2);
    $assigned = dealtSets($exam)->first();
    $other = dealtSets($exam)->last();
    $student = examSetsStudent($section);

    actingAs($student)->get("/exams/{$exam->id}")->assertOk();

    expect(assignedSetId($exam, $student))->toBe($assigned->id);

    // The student's own set is untouched, so answering a part that belongs to
    // another student's set must not complete the exam for them.
    ExamSubmission::factory()
        ->forSubmission($student, $exam, $other->parts()->firstOrFail())
        ->create(['status' => 'submitted']);

    expect(app(ExamXpAwardService::class)->awardIfEligible($student, $exam))->toBeNull()
        ->and(ExamXpAward::query()->where('user_id', $student->id)->exists())->toBeFalse();
});
