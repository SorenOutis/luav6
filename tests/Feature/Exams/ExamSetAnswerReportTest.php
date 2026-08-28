<?php

/**
 * The admin "View Answer" report is per set: a set-scoped report shows only
 * that set's questions and only the students who were handed that set, and a
 * report over every set says which set each part belongs to.
 */

use App\Filament\Resources\Exams\Pages\EditExam;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamAnswerReportService;
use Illuminate\Support\Collection;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * A published exam with `$sets` sets that deliberately share one part title
 * ("Part I"), plus one student who submitted each set — exactly what the
 * rotation produces, and exactly why the report has to name the set.
 *
 * @return array{0: Exam, 1: Collection<int, ExamSet>, 2: User, 3: User}
 */
function setReportContext(int $sets = 2): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $exam = Exam::factory()->published()->forSection($section)->withSets($sets)->create(['title' => 'Midterm']);
    $examSets = $exam->sets()->orderBy('sort_order')->get();

    $students = [
        User::factory()->create(['name' => 'Ana Cruz']),
        User::factory()->create(['name' => 'Ben Santos']),
    ];

    foreach ($examSets as $index => $set) {
        $part = ExamPart::factory()
            ->forSet($set)
            ->multipleChoice(count: 1, correctIndex: 1, points: 2)
            ->create([
                'title' => 'Part I',
                'sort_order' => 0,
            ]);

        // Every set gets its own student, the way the rotation hands them out.
        $student = $students[$index % count($students)];

        ExamSubmission::factory()
            ->forSubmission($student, $exam, $part)
            ->graded(2)
            ->create([
                'answers' => [[
                    'question_number' => 1,
                    'question_type' => 'multiple_choice',
                    'question_text' => 'Multiple choice question 1?',
                    'points' => 2,
                    'answer' => 1, // option 1 is the correct one
                ]],
            ]);
    }

    return [$exam, $examSets, $students[0], $students[1]];
}

function setReportAdmin(): User
{
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    return $admin;
}

it('scopes the answer key to the questions of one set', function () {
    setReportAdmin();
    [$exam, $examSets] = setReportContext(2);
    [, $setB] = $examSets->all();

    $report = app(ExamAnswerReportService::class)->build(
        exam: $exam,
        mode: ExamAnswerReportService::MODE_KEY,
        studentIds: [],
        includeKey: true,
        set: $setB,
    );

    expect($report['parts'])->toHaveCount(1)
        ->and($report['parts'][0]['set'])->toBe('Set B')
        ->and($report['exam']['set'])->toBe('Set B')
        ->and($report['exam']['set_id'])->toBe($setB->id)
        ->and($report['exam']['set_count'])->toBe(2)
        // Only this set's questions count towards the report's totals.
        ->and($report['exam']['question_count'])->toBe(1)
        ->and($report['exam']['total_points'])->toBe(2)
        ->and($report['exam']['part_count'])->toBe(1);
});

it('names the set of every part when the report covers all of them', function () {
    setReportAdmin();
    [$exam] = setReportContext(2);

    $report = app(ExamAnswerReportService::class)->build(
        exam: $exam,
        mode: ExamAnswerReportService::MODE_KEY,
    );

    expect($report['parts'])->toHaveCount(2)
        ->and(collect($report['parts'])->pluck('set')->all())->toBe(['Set A', 'Set B'])
        // Titles are identical across sets — the set is what tells them apart.
        ->and(collect($report['parts'])->pluck('title')->all())->toBe(['Part I', 'Part I'])
        ->and($report['exam']['set'])->toBeNull()
        ->and($report['exam']['set_id'])->toBeNull()
        ->and($report['exam']['set_count'])->toBe(2)
        ->and($report['exam']['question_count'])->toBe(2)
        ->and($report['exam']['total_points'])->toBe(4);
});

it('only reports the students who were handed that set', function () {
    setReportAdmin();
    [$exam, $examSets, $ana, $ben] = setReportContext(2);
    [$setA] = $examSets->all();

    $reports = app(ExamAnswerReportService::class);

    $everything = $reports->build($exam, ExamAnswerReportService::MODE_STUDENTS);
    $setOnly = $reports->build($exam, ExamAnswerReportService::MODE_STUDENTS, [], true, $setA);

    expect($everything['students'])->toHaveCount(2)
        ->and($everything['students'][0]['student']['set'])->toBe('Set A')
        ->and($everything['students'][0]['parts'])->toHaveCount(1)
        ->and($everything['students'][0]['parts'][0]['part']['set'])->toBe('Set A')
        ->and($everything['students'][1]['student']['set'])->toBe('Set B')
        ->and($everything['students'][1]['parts'])->toHaveCount(1)
        ->and($everything['students'][1]['parts'][0]['part']['set'])->toBe('Set B');

    expect($setOnly['students'])->toHaveCount(1)
        ->and($setOnly['students'][0]['student']['name'])->toBe('Ana Cruz')
        ->and($setOnly['students'][0]['parts'])->toHaveCount(1)
        ->and($setOnly['students'][0]['parts'][0]['part']['set'])->toBe('Set A')
        // Graded against Set A alone, so the totals are that set's totals.
        ->and($setOnly['students'][0]['summary']['total_points'])->toBe(2)
        ->and($setOnly['students'][0]['summary']['score'])->toBe(2.0)
        ->and($setOnly['students'][0]['summary']['correct'])->toBe(1);

    expect(collect($setOnly['students'])->pluck('student.name')->all())->not->toContain($ben->name);
});

it('keeps unanswered parts from the student\'s own set', function () {
    setReportAdmin();
    [$exam, $examSets, $ana] = setReportContext(2);
    [$setA] = $examSets->all();

    ExamPart::factory()
        ->forSet($setA)
        ->multipleChoice(count: 1, correctIndex: 1, points: 3)
        ->create(['title' => 'Part II', 'sort_order' => 1]);

    $student = app(ExamAnswerReportService::class)->build($exam, ExamAnswerReportService::MODE_STUDENTS)['students'][0];

    expect($student['student']['name'])->toBe($ana->name)
        ->and($student['student']['set'])->toBe('Set A')
        ->and($student['parts'])->toHaveCount(2)
        ->and($student['parts'][1]['submitted'])->toBeFalse()
        ->and($student['parts'][1]['part']['set'])->toBe('Set A');
});

it('offers only the students of the chosen set in the picker', function () {
    setReportAdmin();
    [$exam, $examSets, $ana, $ben] = setReportContext(2);
    [$setA, $setB] = $examSets->all();

    $reports = app(ExamAnswerReportService::class);

    expect($reports->studentOptions($exam))->toBe([
        $ana->id => 'Ana Cruz',
        $ben->id => 'Ben Santos',
    ]);

    expect($reports->studentOptions($exam, $setA))->toBe([$ana->id => 'Ana Cruz'])
        ->and($reports->studentOptions($exam, $setB))->toBe([$ben->id => 'Ben Santos']);
});

it('prints a per-set report with only that set on it', function () {
    $admin = setReportAdmin();
    [$exam, $examSets] = setReportContext(2);
    [$setA] = $examSets->all();

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id, 'set' => $setA->id]))
        ->assertOk()
        ->assertSee('Midterm')
        ->assertSee('Part I')
        ->assertSee('Ana Cruz')
        ->assertDontSee('Ben Santos')
        // The header names the set, so the parts don't repeat it, and the
        // all-sets placeholder is gone.
        ->assertDontSee('All sets')
        ->assertDontSee('Set A · Part I');
});

it('prints every set when the report is not scoped to one', function () {
    $admin = setReportAdmin();
    [$exam] = setReportContext(2);

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id]))
        ->assertOk()
        ->assertSee('All sets')
        ->assertSee('Set A · Part I')
        ->assertSee('Set B · Part I')
        ->assertSee('Ana Cruz')
        ->assertSee('Ben Santos');
});

it('shows each student only their set without a part number prefix', function () {
    $admin = setReportAdmin();
    [$exam] = setReportContext(2);

    actingAs($admin)
        ->get(route('admin.exams.answer-report', [
            'exam' => $exam->id,
            'include_key' => 0,
        ]))
        ->assertOk()
        ->assertSee('Ana Cruz')
        ->assertSee('Set A')
        ->assertSee('Ben Santos')
        ->assertSee('Set B')
        ->assertDontSee('Part 1 — Part I')
        ->assertDontSee('No submission for this part.');
});

it('leaves the set out of a single-set exam report', function () {
    $admin = setReportAdmin();
    [$exam] = setReportContext(1);

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id]))
        ->assertOk()
        ->assertSee('Part I')
        // One set is neither "all sets" nor worth repeating in every heading.
        ->assertDontSee('All sets')
        ->assertDontSee('Set A · Part I');
});

it('ignores a set that belongs to another exam', function () {
    $admin = setReportAdmin();
    [$exam] = setReportContext(2);

    $otherExam = Exam::factory()->published()->withSets(2)->create();
    $foreignSet = $otherExam->sets()->orderBy('sort_order')->first();

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id, 'set' => $foreignSet->id]))
        ->assertOk()
        ->assertSee('All sets')
        ->assertSee('Set A · Part I')
        ->assertSee('Set B · Part I');
});

it('lets the admin pick a set in the View Answer dialog', function () {
    setReportAdmin();
    [$exam, $examSets] = setReportContext(2);
    [$setA, $setB] = $examSets->all();

    Livewire::test(EditExam::class, ['record' => $exam->getRouteKey()])
        ->mountAction('viewAnswer')
        ->assertActionMounted('viewAnswer')
        ->assertActionDataSet(['scope' => 'answer_key'])
        // Filled explicitly rather than trusting the modal's defaults: only
        // the set choice is under test here, not the scope radio.
        ->set('mountedActions.0.data.scope', 'answer_key')
        ->set('mountedActions.0.data.set', $setB->id)
        ->callMountedAction()
        ->assertHasNoActionErrors();
});

it('hides the set picker when the exam has only one set', function () {
    setReportAdmin();
    [$exam] = setReportContext(1);

    Livewire::test(EditExam::class, ['record' => $exam->getRouteKey()])
        ->mountAction('viewAnswer')
        ->assertDontSee('Exam set')
        ->set('mountedActions.0.data.scope', 'answer_key')
        ->callMountedAction()
        ->assertHasNoActionErrors();
});
