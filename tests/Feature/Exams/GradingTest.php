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

use App\Jobs\GradeExamSubmissionEssays;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
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
    $admin = User::factory()->admin()->create();
    actingAs($admin);
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();

    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $exam = Exam::factory()->published()->forSection($section)->create();

    return [$student, $exam, $section, $admin];
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
                    $out[$questionNumber] = [
                        'score' => $scoresByQuestionNumber[$questionNumber],
                        'feedback' => 'Automatic AI feedback for this essay.',
                    ];
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

it('accepts any configured Identification alternative for full credit', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->withQuestions([[
        'text' => 'What general term describes software designed to disrupt or gain unauthorized access to a computer system?',
        'type' => 'identification',
        'points' => 3,
        'correct_answer' => 'Virus',
        'accepted_answers' => [['answer' => 'Malware']],
    ]])->create();

    submitAnswers($student, $exam, $part, [1 => ' malware! ']);

    expect(ExamSubmission::first()->score)->toEqual('3.00');
});

// ─────────────────────────────────────────────
//  Essay
// ─────────────────────────────────────────────

it('takes the essay score from the AI service and applies it immediately', function () {
    Queue::fake();

    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 7.5]);

    submitAnswers($student, $exam, $part, [1 => 'A thoughtful essay answer.']);

    $submission = ExamSubmission::first();
    expect($submission->score)->toEqual('0.00')
        ->and($submission->status)->toBe('pending_ai');

    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('7.50')
        ->and($submission->fresh()->status)->toBe('graded');
});

it('persists the submission before contacting the AI provider', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    $mock = Mockery::mock(AIService::class);
    $mock->shouldNotReceive('batchAssessEssays');
    app()->instance(AIService::class, $mock);

    Queue::fake();

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);

    expect(ExamSubmission::count())->toBe(1);
    Queue::assertPushed(GradeExamSubmissionEssays::class);
});

it('automatically publishes AI essay feedback without teacher approval', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->status)->toBe('graded')
        ->and($submission->fresh()->answers[0]['ai_score'])->toEqual(6.0)
        ->and($submission->fresh()->answers[0]['ai_feedback'])->toBe('Automatic AI feedback for this essay.')
        ->and($submission->fresh()->answers[0]['ai_feedback_source'])->toBe('automatic');
});

it('does not double-score essays when the automatic grading job runs twice', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();

    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('6.00')
        ->and($submission->fresh()->status)->toBe('graded');
});

it('replaces rather than duplicates the score when an essay is automatically regraded', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);
    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    fakeEssayScores([1 => 8.0]);
    (new GradeExamSubmissionEssays(
        submissionId: $submission->id,
        forceRegenerate: true,
        onlyQuestionNumber: 1,
    ))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('8.00')
        ->and($submission->fresh()->answers[0]['ai_score'])->toEqual(8.0);
});

it('preserves the answer metadata used by automatic feedback', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->essay(count: 1, points: 10)->create();

    fakeEssayScores([1 => 6.0]);

    submitAnswers($student, $exam, $part, [1 => 'Essay body.']);
    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    $answer = $submission->fresh()->answers[0];

    expect($answer)->toHaveKeys([
        'question_number',
        'question_type',
        'question_text',
        'points',
        'grading_method',
        'ai_score',
        'ai_feedback',
    ])->and($answer['grading_method'])->toBe('ai');
});

it('leaves manually graded essays for the teacher without calling AI', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()
        ->forExam($exam)
        ->essay(count: 1, points: 10, gradingMethod: 'manual')
        ->create();

    $mock = Mockery::mock(AIService::class);
    $mock->shouldNotReceive('batchAssessEssays');
    app()->instance(AIService::class, $mock);
    Queue::fake();

    submitAnswers($student, $exam, $part, [1 => 'Essay body for teacher review.']);

    $submission = ExamSubmission::first();
    expect($submission->status)->toBe('pending_review')
        ->and($submission->score)->toEqual('0.00')
        ->and($submission->answers[0]['grading_method'])->toBe('manual')
        ->and(array_key_exists('ai_score', $submission->answers[0]))->toBeFalse();
    Queue::assertNotPushed(GradeExamSubmissionEssays::class);

    actingAs($student)
        ->getJson("/exams/{$exam->id}/parts/{$part->id}/status")
        ->assertSuccessful()
        ->assertJsonPath('awaiting_teacher_review', true)
        ->assertJsonPath('scored', false);
    Queue::assertNotPushed(GradeExamSubmissionEssays::class);
});

it('defaults legacy essay questions without a grading method to automatic AI grading', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->withQuestions([[
        'text' => 'Legacy essay question',
        'type' => 'essay',
        'points' => 10,
    ]])->create();

    Queue::fake();
    submitAnswers($student, $exam, $part, [1 => 'Legacy essay answer.']);

    expect(ExamSubmission::first()->answers[0]['grading_method'])->toBe('ai');
    Queue::assertPushed(GradeExamSubmissionEssays::class);
});

it('does not queue a grading job when the part has no essays', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    Queue::fake();

    submitAnswers($student, $exam, $part, [1 => 0]);

    Queue::assertNothingPushed();
});

it('keeps a mixed automatic and manual essay submission pending for the teacher', function () {
    Queue::fake();

    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->withQuestions([
        [
            'text' => 'AI essay',
            'type' => 'essay',
            'points' => 10,
            'grading_method' => 'ai',
        ],
        [
            'text' => 'Manual essay',
            'type' => 'essay',
            'points' => 10,
            'grading_method' => 'manual',
        ],
    ])->create();

    fakeEssayScores([1 => 8.0]);
    submitAnswers($student, $exam, $part, [
        1 => 'Automatically graded response.',
        2 => 'Teacher graded response.',
    ]);

    $submission = ExamSubmission::first();
    (new GradeExamSubmissionEssays($submission->id))->handle(app(AIService::class));

    expect($submission->fresh()->score)->toEqual('8.00')
        ->and($submission->fresh()->status)->toBe('pending_review')
        ->and($submission->fresh()->answers[0]['ai_score'])->toEqual(8.0)
        ->and(array_key_exists('ai_score', $submission->fresh()->answers[1]))->toBeFalse();
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

    // + q3 essay (8) as soon as the automatic grading job finishes = 13.
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

it('scores Enumeration items independently of order and does not award duplicate credit', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->enumeration([
        ['answer' => 'Technical SEO', 'points' => 2],
        ['answer' => 'On-page SEO', 'points' => 3],
        ['answer' => 'Off-page SEO', 'points' => 5],
    ])->create();

    submitAnswers($student, $exam, $part, [1 => [
        ' off-page seo ',
        'Technical SEO',
        'Technical SEO',
    ]]);

    expect(ExamSubmission::first()->score)->toEqual('7.00')
        ->and(ExamSubmission::first()->answers[0]['answer'])->toBe([
            'off-page seo',
            'Technical SEO',
            'Technical SEO',
        ]);
});

it('scores Matching Type pairs with per-pair points and normalization', function () {
    [$student, $exam] = examContext();
    $part = ExamPart::factory()->forExam($exam)->matching([
        ['prompt' => 'Technical SEO', 'answer' => 'Crawlability', 'points' => 2],
        ['prompt' => 'On-page SEO', 'answer' => 'Content and headings', 'points' => 3],
        ['prompt' => 'Off-page SEO', 'answer' => 'External authority signals', 'points' => 5],
    ])->create();

    submitAnswers($student, $exam, $part, [1 => [
        'crawlability',
        ' Content   and headings! ',
        'Wrong answer',
    ]]);

    expect(ExamSubmission::first()->score)->toEqual('5.00')
        ->and(ExamSubmission::first()->answers[0]['answer'])->toBe([
            'crawlability',
            'Content   and headings!',
            'Wrong answer',
        ]);
});

it('rejects malformed Matching Type answer payloads', function () {
    $payloads = [
        ['answers' => 'not-an-array'],
        ['answers' => [[
            'question_number' => 1,
            'answer' => ['Crawlability', 'Content and headings', 'Extra choice'],
        ]]],
        ['answers' => [[
            'question_number' => 1,
            'answer' => ['Crawlability', 'Not a configured choice'],
        ]]],
    ];

    foreach ($payloads as $payload) {
        [$student, $exam] = examContext();
        $part = ExamPart::factory()->forExam($exam)->matching([
            ['prompt' => 'Technical SEO', 'answer' => 'Crawlability', 'points' => 2],
            ['prompt' => 'On-page SEO', 'answer' => 'Content and headings', 'points' => 3],
        ])->create();

        actingAs($student)
            ->post("/exams/{$exam->id}/parts/{$part->id}/submit", $payload)
            ->assertStatus(422);
    }
});
