<?php

use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Models\AiQuestionDraft;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\PendingAiAction;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use App\Services\PendingAiActionService;
use Laravel\Ai\Tools\Request;

class StubQuestionGenerator extends AiQuestionGeneratorService
{
    public array $lastCall = [];

    public bool $shouldReturnEmpty = false;

    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $this->lastCall = compact('sourceText', 'typeCounts', 'difficulty', 'topic');

        if ($this->shouldReturnEmpty) {
            return [];
        }

        $questions = [];
        foreach ([
            'multiple_choice' => ['options' => true],
            'true_false' => ['options' => true],
            'identification' => ['correct_answer' => true],
            'essay' => [],
        ] as $type => $shape) {
            for ($i = 0; $i < (int) ($typeCounts[$type] ?? 0); $i++) {
                $question = [
                    'type' => $type,
                    'text' => ucfirst(str_replace('_', ' ', $type))." question {$i}",
                ];
                if (($shape['options'] ?? false) === true) {
                    $question['options'] = [
                        ['text' => 'Option A', 'is_correct' => true],
                        ['text' => 'Option B', 'is_correct' => false],
                    ];
                }
                if (($shape['correct_answer'] ?? false) === true) {
                    $question['correct_answer'] = 'Answer';
                }
                $questions[] = $question;
            }
        }

        return $questions;
    }
}

function generatedQuestionActionNonce(PendingAiAction $action): string
{
    return app(PendingAiActionService::class)->present($action)['nonce'];
}

it('generates a private teacher-review draft after browser approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create(['title' => 'Cell Biology Midterm']);

    $fake = new StubQuestionGenerator;
    $this->app->instance(AiQuestionGeneratorService::class, $fake);

    $result = (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'The mitochondrion produces ATP.',
        'topic' => 'Cellular respiration',
        'difficulty' => 'hard',
        'multiple_choice' => 2,
        'true_false' => 1,
        'identification' => 1,
        'essay' => 1,
        'points' => 2,
    ]));

    $action = PendingAiAction::firstOrFail();

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and($fake->lastCall)->toBe([])
        ->and(ExamPart::count())->toBe(0)
        ->and($action->preview['changes'][1]['after'])->toContain('Multiple choice: 2');

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => generatedQuestionActionNonce($action),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect($fake->lastCall['sourceText'])->toBe('The mitochondrion produces ATP.')
        ->and($fake->lastCall['difficulty'])->toBe('hard')
        ->and($fake->lastCall['topic'])->toBe('Cellular respiration')
        ->and($fake->lastCall['typeCounts'])->toBe([
            'multiple_choice' => 2,
            'true_false' => 1,
            'identification' => 1,
            'essay' => 1,
        ]);

    $draft = AiQuestionDraft::firstOrFail();
    expect(ExamPart::where('exam_id', $exam->id)->count())->toBe(0)
        ->and($draft->target_exam_id)->toBe($exam->id)
        ->and($draft->review_status)->toBe(AiQuestionDraft::REVIEW_AWAITING)
        ->and($draft->review_version)->toBe(1)
        ->and($draft->questions)->toHaveCount(5)
        ->and($draft->questions[0]['points'])->toBe(2);
});

it('refuses non-admins before creating an action', function () {
    $this->actingAs(User::factory()->create());

    $result = (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => 1,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
    ]));

    expect($result)->toContain('Only admins')
        ->and(PendingAiAction::count())->toBe(0)
        ->and(ExamPart::count())->toBe(0);
});

it('validates source and question counts before staging', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create();

    expect((new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => '  ',
        'multiple_choice' => 1,
    ])))->toContain('source_text is required')
        ->and((new GenerateExamQuestionsTool)->handle(new Request([
            'exam_id' => $exam->id,
            'source_text' => 'Some material.',
        ])))->toContain('at least one question')
        ->and((new GenerateExamQuestionsTool)->handle(new Request([
            'exam_id' => $exam->id,
            'source_text' => 'Some material.',
            'multiple_choice' => 31,
        ])))->toContain('per-type limits')
        ->and(PendingAiAction::count())->toBe(0);
});

it('rejects an exam from another workspace before staging', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignExam = Exam::factory()->create();

    $this->actingAs($adminA);
    $result = (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $foreignExam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
    ]));

    expect($result)->toContain('not found')
        ->and(PendingAiAction::count())->toBe(0)
        ->and(ExamPart::count())->toBe(0);
});

it('marks the approved action failed when generation returns no questions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create();

    $fake = new StubQuestionGenerator;
    $fake->shouldReturnEmpty = true;
    $this->app->instance(AiQuestionGeneratorService::class, $fake);

    (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
    ]));
    $action = PendingAiAction::firstOrFail();

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => generatedQuestionActionNonce($action),
    ])->assertStatus(422)->assertJsonPath(
        'message',
        'The AI returned no usable questions. Try a shorter source or reduce the requested counts.',
    );

    expect(ExamPart::count())->toBe(0)
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_FAILED);
});

it('never attaches generated questions even when the target exam already has parts', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create();

    $fake = new StubQuestionGenerator;
    $this->app->instance(AiQuestionGeneratorService::class, $fake);

    (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
    ]));
    $action = PendingAiAction::firstOrFail();
    ExamPart::factory()->create(['exam_id' => $exam->id, 'sort_order' => 1]);

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => generatedQuestionActionNonce($action),
    ])->assertOk();

    expect(ExamPart::where('exam_id', $exam->id)->count())->toBe(1)
        ->and(AiQuestionDraft::count())->toBe(1)
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_EXECUTED);
});
