<?php

/**
 * Covers the admin "View Answer" report: the printable questions + answers +
 * correct/wrong + overall score page reached from the exam edit screen.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ExamAnswerReportService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{0: User, 1: Exam, 2: ExamPart, 3: User}
 */
function answerReportContext(): array
{
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['name' => 'Ana Cruz']);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $exam = Exam::factory()->published()->forSection($section)->create(['title' => 'Quiz 3']);
    $part = ExamPart::factory()->forExam($exam)->mixed()->create(['title' => 'Part I']);

    return [$admin, $exam, $part, $student];
}

function submissionFor(User $student, Exam $exam, ExamPart $part): ExamSubmission
{
    return ExamSubmission::factory()->forSubmission($student, $exam, $part)->create([
        'status' => 'graded',
        'score' => 5,
        'answers' => [
            // Q1 multiple choice — index 1 is correct.
            ['question_number' => 1, 'question_type' => 'multiple_choice', 'question_text' => 'Mixed multiple choice?', 'points' => 2, 'answer' => 1],
            // Q2 identification — key is "Manila".
            ['question_number' => 2, 'question_type' => 'identification', 'question_text' => 'Mixed identification?', 'points' => 3, 'answer' => 'Cebu'],
            // Q3 essay — AI graded.
            ['question_number' => 3, 'question_type' => 'essay', 'question_text' => 'Mixed essay?', 'points' => 10, 'answer' => 'My essay body.', 'ai_score' => 7, 'ai_feedback' => 'Good structure.'],
        ],
    ]);
}

it('renders the answer key without student data', function () {
    [$admin, $exam] = answerReportContext();

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id, 'mode' => 'key']))
        ->assertOk()
        ->assertSee('Answer key')
        ->assertSee('Quiz 3')
        ->assertSee('Manila')
        ->assertDontSee('graded answers');
});

it('renders a graded report for a selected student', function () {
    [$admin, $exam, $part, $student] = answerReportContext();
    submissionFor($student, $exam, $part);

    $response = actingAs($admin)->get(route('admin.exams.answer-report', [
        'exam' => $exam->id,
        'mode' => 'students',
        'students' => [$student->id],
    ]));

    $response->assertOk()
        ->assertSee('Ana Cruz')
        ->assertSee('Correct')
        ->assertSee('Wrong')
        ->assertSee('Good structure.')
        ->assertSee('5 / 15'); // recorded score over the exam total
});

it('marks answers correct, wrong and scores essays exactly like the grader does', function () {
    [, $exam, $part, $student] = answerReportContext();
    submissionFor($student, $exam, $part);

    $report = app(ExamAnswerReportService::class)->build($exam, 'students', [$student->id]);

    $items = $report['students'][0]['parts'][0]['items'];

    expect($items[0]['result'])->toBe('correct')          // MCQ index 1
        ->and($items[0]['earned'])->toBe(2.0)
        ->and($items[1]['result'])->toBe('wrong')          // "Cebu" != "Manila"
        ->and($items[2]['result'])->toBe('scored')         // essays are score-only
        ->and($items[2]['earned'])->toBe(7.0)
        ->and($items[2]['feedback'])->toBe('Good structure.')
        ->and($items[2]['feedback_source'])->toBe('ai');

    $summary = $report['students'][0]['summary'];

    expect($summary['correct'])->toBe(1)
        ->and($summary['wrong'])->toBe(1)
        ->and($summary['essays_scored'])->toBe(1)
        ->and($summary['essay_points'])->toBe(7.0)
        ->and($summary['score'])->toBe(5.0)
        ->and($summary['total_points'])->toBe(15)
        ->and($summary['percentage'])->toBe(33.3);
});

it('prints the teacher comment under the essay instead of twice', function () {
    [, $exam, $part, $student] = answerReportContext();
    submissionFor($student, $exam, $part)->update(['feedback' => 'Well argued, expand your conclusion.']);

    $report = app(ExamAnswerReportService::class)->build($exam, 'students', [$student->id]);
    $partReport = $report['students'][0]['parts'][0];

    expect($partReport['items'][2]['teacher_feedback'])->toBe('Well argued, expand your conclusion.')
        ->and($partReport['items'][2]['feedback'])->toBe('Good structure.')
        ->and($partReport['feedback'])->toBeNull();
});

it('includes every student who submitted when none are selected', function () {
    [$admin, $exam, $part, $student] = answerReportContext();
    submissionFor($student, $exam, $part);

    $other = User::factory()->create(['name' => 'Ben Reyes']);
    ExamSubmission::factory()->forSubmission($other, $exam, $part)->create([
        'status' => 'graded',
        'score' => 2,
        'answers' => [
            ['question_number' => 1, 'question_type' => 'multiple_choice', 'question_text' => 'Mixed multiple choice?', 'points' => 2, 'answer' => 1],
        ],
    ]);

    $report = app(ExamAnswerReportService::class)->build($exam, 'students');

    expect($report['students'])->toHaveCount(2)
        ->and($report['class_summary']['count'])->toBe(2);

    actingAs($admin)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id]))
        ->assertOk()
        ->assertSee('Class summary')
        ->assertSee('Ana Cruz')
        ->assertSee('Ben Reyes');
});

it('is not reachable by students', function () {
    [, $exam, , $student] = answerReportContext();

    actingAs($student)
        ->get(route('admin.exams.answer-report', ['exam' => $exam->id]))
        ->assertForbidden();
});

it('requires authentication', function () {
    [, $exam] = answerReportContext();

    auth()->logout();

    get(route('admin.exams.answer-report', ['exam' => $exam->id]))
        ->assertRedirect();
});
