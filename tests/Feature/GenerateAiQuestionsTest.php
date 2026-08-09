<?php

use App\Jobs\GenerateAiQuestions;
use App\Models\AiQuestionDraft;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;

/**
 * Test-only generator that stubs the network call and records the raw response,
 * so we can assert the queue job persists exactly what the AI returned.
 */
class JobTestableGenerator extends AiQuestionGeneratorService
{
    public string $stubbedRaw = '';

    public bool $shouldReturnEmpty = false;

    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $this->lastRawResponse = $this->stubbedRaw;

        if ($this->shouldReturnEmpty) {
            return [];
        }

        return [
            [
                'text' => 'Which layer is Layer 1?',
                'type' => 'multiple_choice',
                'points' => 1,
                'options' => [
                    ['text' => 'Physical', 'is_correct' => true],
                    ['text' => 'Application', 'is_correct' => false],
                ],
            ],
        ];
    }
}

/**
 * Test-only generator that stubs only the provider call (ask()) and lets
 * generate() run its real parsing, so we can assert lastRawResponse capture.
 */
class StubAskGenerator extends AiQuestionGeneratorService
{
    public function __construct(private readonly string $rawResponse)
    {
        parent::__construct();
    }

    protected function ask(string $prompt, bool $jsonMode, int $maxTokens, float $temperature): string
    {
        return $this->rawResponse;
    }
}

test('generate exposes the raw provider response alongside parsed questions', function () {
    $raw = '{"questions":[{"type":"multiple_choice","text":"Which layer is Layer 1?","options":[{"text":"Physical","is_correct":true},{"text":"Application","is_correct":false}]}]}';
    $service = new StubAskGenerator($raw);

    $questions = $service->generate('source text', ['multiple_choice' => 1], 'medium', 'OSI layers');

    expect($service->lastRawResponse)->toBe($raw);
    expect($questions)->toHaveCount(1);
    expect($questions[0]['type'])->toBe('multiple_choice');
});

test('generate still exposes the raw response when it cannot be parsed', function () {
    $raw = 'Sorry, I cannot generate questions from this material.';
    $service = new StubAskGenerator($raw);

    $questions = $service->generate('source text', ['multiple_choice' => 1]);

    expect($questions)->toHaveCount(0);
    // The raw response is preserved so the caller can persist it for debugging.
    expect($service->lastRawResponse)->toBe($raw);
});

test('the generate job persists the raw AI response on success', function () {
    $admin = User::factory()->admin()->create();

    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Networking quiz',
        'source_text' => 'The OSI model has seven layers.',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'status' => 'pending',
    ]);

    $raw = '{"questions":[{"type":"multiple_choice","text":"Which layer is Layer 1?","options":[{"text":"Physical","is_correct":true}]}]}';
    $generator = new JobTestableGenerator;
    $generator->stubbedRaw = $raw;

    (new GenerateAiQuestions($draft->id))->handle($generator);

    $draft->refresh();

    expect($draft->status)->toBe('ready');
    expect($draft->questions)->toHaveCount(1);
    expect($draft->ai_response)->toBe($raw);
});

test('the generate job persists the raw AI response even when it fails', function () {
    $admin = User::factory()->admin()->create();

    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Empty result quiz',
        'source_text' => 'Some source material.',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'status' => 'pending',
    ]);

    // AI "responded" but with text that contains no usable questions.
    $raw = 'Sorry, I cannot generate questions from this material.';
    $generator = new JobTestableGenerator;
    $generator->stubbedRaw = $raw;
    $generator->shouldReturnEmpty = true;

    try {
        (new GenerateAiQuestions($draft->id))->handle($generator);
        $this->fail('Expected a RuntimeException because no questions were generated.');
    } catch (RuntimeException) {
        // Expected — the job marks the draft failed and rethrows.
    }

    $draft->refresh();

    expect($draft->status)->toBe('failed');
    expect($draft->last_error)->toContain('no usable questions');
    // The raw response is retained so the admin can see what the AI actually said.
    expect($draft->ai_response)->toBe($raw);
});
