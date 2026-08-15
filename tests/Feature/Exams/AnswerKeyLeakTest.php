<?php

/**
 * Answer-key exposure tests (Phase 1.1).
 *
 * ⚠️ THESE TESTS ARE EXPECTED TO FAIL BEFORE THE PHASE 1.1 FIX.
 * They encode the target behaviour, not current behaviour:
 *
 *   - Students must never receive is_correct / correct_answer for an exam
 *     they have not completed.
 *   - Answer keys are revealed ONLY once the exam status is 'closed' AND the
 *     student has actually participated (review after close, not after
 *     submit). A student who never answered a closed exam must not be able to
 *     open the review and read the questions at all.
 *
 * The leak exists on BOTH:
 *   ExamController::index() — eager-loads parts for every visible exam
 *   ExamController::show()  — sends the full ExamPart model
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function leakContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$student, $section, $season];
}

it('does not leak the answer key on the exam list page', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    $response = actingAs($student)->get('/exams');

    // Assert on the raw payload — field-by-field checks miss nested shapes.
    expect($response->getContent())
        ->not->toContain('is_correct')
        ->not->toContain('correct_answer')
        ->not->toContain('Manila');
})->group('security');

it('does not leak the answer key when taking an exam', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    $response = actingAs($student)->get("/exams/{$exam->id}");

    expect($response->getContent())
        ->not->toContain('is_correct')
        ->not->toContain('correct_answer')
        ->not->toContain('Manila');
})->group('security');

it('still sends the option text students need to answer', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1)->create();

    $response = actingAs($student)->get("/exams/{$exam->id}");

    // Stripping the key must not strip the question itself.
    expect($response->getContent())
        ->toContain('Option 0')
        ->toContain('Option 1');
})->group('security');

it('does not reveal a closed exam the student never answered', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    $response = actingAs($student)->get('/exams');

    // No submission → no answer key AND no question text/options in the payload.
    expect($response->getContent())
        ->not->toContain('is_correct')
        ->not->toContain('correct_answer')
        ->not->toContain('Manila')
        ->not->toContain('Identification question');
})->group('security');

it('does not reveal a closed exam the student never answered on the show page', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    $response = actingAs($student)->get("/exams/{$exam->id}");

    expect($response->getContent())
        ->not->toContain('is_correct')
        ->not->toContain('correct_answer')
        ->not->toContain('Manila')
        ->not->toContain('Identification question');
})->group('security');

it('reveals the answer key once the exam is closed and the student has submitted', function () {
    [$student, $section] = leakContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->for($student, $exam, $part)->create();

    $response = actingAs($student)->get('/exams');

    // Review mode: Exam.vue needs the key to render "correct answer" feedback.
    expect($response->getContent())->toContain('Manila');
})->group('security');
