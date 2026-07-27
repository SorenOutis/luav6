<?php

/**
 * Characterisation tests for ExamController::submitPart() grading.
 *
 * These lock in CURRENT behaviour before the Phase 1 / 1.0 refactors so we can
 * prove the async-grading change and the answer-key fix don't alter scoring.
 *
 * Where current behaviour is arguably wrong, the test documents it and is
 * marked with a NOTE — do not "fix" these without updating the plan.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AIService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Build a student enrolled in a section, plus a published exam for it.
 *
 * @return array{0: User, 1: Exam, 2: Section}
 */
function examContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();

    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $exam = Exam::factory()->published()->forSection($section)->create();

    return [$student, $exam, $section];
}

/**
 * Post answers to a part. $answers is [questionNumber => answer].
 */
function submitAnswers(User $student, Exam $exam, ExamPart $part, array $answers)
{
    $payload = collect($answers)->map(fn ($answer, $number) => [
        'question_number' => $number,
        'answer' => $answer,
    ])->values()->all();

    return actingAs($student)->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
        'answers' => $payload,
    ]);
}

/** Stop real HTTP calls to the AI provider; return a fixed score per essay. */
function fakeEssayScores(array $scoresByQuestionNumber): void
{
    $mock = Mockery::mock(AIService::class);
    $mock->shouldReceive('batchAssessEssays')
        ->andReturnUsing(function (array $essays) use ($scoresByQuestionNumber) {
            $out = [];
            foreach ($essays as $questionNumber => $essay) {
                $out[$questionNumber] = ['score' => $scoresByQuestionNumber[$questionNumber] ?? 0.0];
            }

            return $out;
        });
    $mock->shouldReceive('preWarm')->andReturnTrue();

    app()->instance(AIService::class, $mock);
}

// ─────────────────────────────────────────────
//  Multiple choice
// ─────────────────────────────────────────────

it('scores a correct multiple choice answer', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    submitAnswers($student, $exam, $part, [1 => 1]);

    expect(ExamSubmission::first()->score)->toEqual('2.00');
});

it('does not score an incorrect multiple choice answer', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    submitAnswers($student, $exam, $part, [1 => 3]);

    expect(ExamSubmission::first()->score)->toEqual('0.00');
});

it('scores true/false questions', function () {
    [$student, $exam] = examContext();
    // answer=true => option index 0 is correct
    $part = ExamPart::factory()->forExam($exam)->trueFalse(count: 2, answer: true, points: 1)->create();

    submitAnswers($student, $exam, $part, [1 => 0, 2 => 1]);

    expect(ExamSubmission::first()->score)->toEqual('1.00');
});

// ─────────────────────────────────────────────
//  Identification — normalization behaviour
// ─────────────────────────────────────────────

it('normalizes case, whitespace and punctuation for identification', function (string $submitted) {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'], points: 3)->create();

    submitAnswers($student, $exam, $part, [1 => $submitted]);

    expect(ExamSubmission::first()->score)->toEqual('3.00');
})->with([
    'exact' => 'Manila',
    'lowercase' => 'manila',
    'uppercase' => 'MANILA',
    'trailing space' => 'Manila ',
    'leading space' => '  Manila',
    'trailing punctuation' => 'Manila!',
    'wrapped in punctuation' => '"Manila."',
]);

it('rejects a genuinely different identification answer', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'], points: 3)->create();

    submitAnswers($student, $exam, $part, [1 => 'Cebu']);

    expect(ExamSubmission::first()->score)->toEqual('0.00');
});

// ─────────────────────────────────────────────
//  Essay
// ─────────────────────────────────────────────

it('takes the essay score from the AI service', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 7.5]);

    submitAnswers($student, $exam, $part, [1 => 'A thoughtful essay answer.']);

    expect(ExamSubmission::first()->score)->toEqual('7.50');
});

it('marks a submission containing an essay as pending_review', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 5.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay text.']);

    expect(ExamSubmission::first()->status)->toBe('pending_review');
});

it('marks an auto-gradable submission as submitted', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    submitAnswers($student, $exam, $part, [1 => 0]);

    expect(ExamSubmission::first()->status)->toBe('submitted');
});

/**
 * NOTE: current behaviour — when the AI provider fails, AIService returns
 * zeroScores() and the student silently receives 0 with no error surfaced.
 * Phase 1.0.7 changes this to flag the submission. Test documents today.
 */
it('silently scores zero when the AI provider returns nothing', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([]); // provider returned nothing for question 1

    submitAnswers($student, $exam, $part, [1 => 'Essay text.']);

    expect(ExamSubmission::first()->score)->toEqual('0.00');
});

// ─────────────────────────────────────────────
//  Mixed + edge cases
// ─────────────────────────────────────────────

it('sums scores across mixed question types', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->mixed()->create();

    fakeEssayScores([3 => 8.0]);

    // q1 MC correct (2) + q2 identification correct (3) + q3 essay (8) = 13
    submitAnswers($student, $exam, $part, [
        1 => 1,
        2 => 'Manila',
        3 => 'Essay body.',
    ]);

    expect(ExamSubmission::first()->score)->toEqual('13.00');
});

it('skips unanswered questions without scoring them', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 3, correctIndex: 1, points: 2)->create();

    // Answer only question 2, correctly.
    submitAnswers($student, $exam, $part, [2 => 1]);

    expect(ExamSubmission::first()->score)->toEqual('2.00');
});

it('stores one submission row per part', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    submitAnswers($student, $exam, $part, [1 => 0]);

    expect(ExamSubmission::count())->toBe(1)
        ->and(ExamSubmission::first()->exam_part_id)->toBe($part->id);
});
