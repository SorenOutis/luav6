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
use App\Jobs\GradeExamSubmissionEssays;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Support\Facades\Queue;

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
                // Only include the essay in the response when a score was
                // explicitly defined — an empty mapping simulates a provider
                // that returned nothing for this question (Phase 1.0.7).
                if (array_key_exists($questionNumber, $scoresByQuestionNumber)) {
                    $out[$questionNumber] = ['score' => $scoresByQuestionNumber[$questionNumber]];
                }
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
    // Queue::fake() prevents the sync driver from running the queued job
    // during the web request — we want to verify the score is 0 until the
    // job is manually processed below.
    Queue::fake();

    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 7.5]);

    submitAnswers($student, $exam, $part, [1 => 'A thoughtful essay answer.']);

    // Phase 1.0.2: the request persists a 0 score, the queued job adds the marks.
    $submission = ExamSubmission::first();
    expect($submission->score)->toEqual('0.00');

    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('7.50');
});

it('persists the submission before contacting the AI provider', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    // Any call to the provider during the web request is a regression: it would
    // block a worker and risk losing the student's answers.
    $mock = Mockery::mock(AIService::class);
    $mock->shouldNotReceive('batchAssessEssays');
    app()->instance(AIService::class, $mock);

    Queue::fake();

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);

    expect(ExamSubmission::count())->toBe(1);
    Queue::assertPushed(GradeExamSubmissionEssays::class);
});

it('leaves an essay submission pending_review for the teachers feedback pass', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    // Scoring is automatic; written feedback is triggered manually from the
    // admin panel, and that pass owns the move to 'graded'.
    expect($submission->fresh()->status)->toBe('pending_review');
});

it('does not double-score essays when the grading job runs twice', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();

    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('6.00');
});

it('preserves the answer fields the manual feedback pass depends on', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    // GenerateExamEssayFeedback reads question_type / question_text / points.
    $answer = $submission->fresh()->answers[0];

    expect($answer)->toHaveKeys(['question_number', 'question_type', 'question_text', 'points', 'ai_score']);
});

it('does not queue a grading job when the part has no essays', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    Queue::fake();

    submitAnswers($student, $exam, $part, [1 => 0]);

    Queue::assertNothingPushed();
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
it('flags the submission when the AI provider returns nothing', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([]); // provider returned nothing for question 1

    submitAnswers($student, $exam, $part, [1 => 'Essay text.']);

    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    // Phase 1.0.7: a provider failure is no longer indistinguishable from a
    // genuine zero — the teacher can see and re-run it.
    expect($submission->fresh()->score)->toEqual('0.00')
        ->and($submission->fresh()->grading_failed)->toBeTrue();
});

// ─────────────────────────────────────────────
//  Mixed + edge cases
// ─────────────────────────────────────────────

it('sums scores across mixed question types', function () {
    Queue::fake();

    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->mixed()->create();

    fakeEssayScores([3 => 8.0]);

    // q1 MC correct (2) + q2 identification correct (3) = 5 synchronously
    submitAnswers($student, $exam, $part, [
        1 => 1,
        2 => 'Manila',
        3 => 'Essay body.',
    ]);

    $submission = ExamSubmission::first();
    expect($submission->score)->toEqual('5.00');

    // + q3 essay (8) once the queued job runs = 13
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('13.00');
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
