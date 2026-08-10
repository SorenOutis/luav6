<?php

/**
 * Follow-up instructions for AI question drafts.
 *
 * A ready draft accepts a free-text follow-up ("add 5 more about X", "make
 * them harder") with an add/replace mode and an optional provider override.
 * The RefineAiQuestions job applies it and the edit page polling refreshes
 * the question list. A failed follow-up never destroys existing questions.
 */

use App\Filament\Resources\AiQuestionDrafts\Pages\EditAiQuestionDraft;
use App\Jobs\RefineAiQuestions;
use App\Models\AiQuestionDraft;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\AnonymousAgent;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();

    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => null,
        'env_key' => null,
        'url' => 'https://api.openai.com/v1',
        'env_url' => 'https://api.openai.com/v1',
    ]]);
});

function createReadyAiDraft(User $admin, array $overrides = []): AiQuestionDraft
{
    return AiQuestionDraft::create(array_merge([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Cell Biology',
        'source_text' => 'Cells are the basic unit of life. The nucleus controls the cell.',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'provider' => 'openai',
        'status' => 'ready',
        'questions' => [[
            'text' => 'Old question about cells?',
            'type' => 'multiple_choice',
            'points' => 1,
            'options' => [['text' => 'Yes', 'is_correct' => true], ['text' => 'No', 'is_correct' => false]],
        ]],
    ], $overrides));
}

it('appends new questions in add mode', function () {
    Setting::set('openai_api_key', 'db-key');

    $admin = User::factory()->admin()->create();
    $draft = createReadyAiDraft($admin);

    AnonymousAgent::fake(['{"questions":[{"type":"true_false","text":"Cells have nuclei.","options":[{"text":"True","is_correct":true},{"text":"False","is_correct":false}]}]}']);

    (new RefineAiQuestions($draft->id, 'Add one true/false question', 'add'))
        ->handle(app(AiQuestionGeneratorService::class));

    $draft->refresh();

    expect($draft->status)->toBe('ready')
        ->and($draft->questions)->toHaveCount(2)
        ->and($draft->questions[1]['text'])->toBe('Cells have nuclei.');

    // The prompt carries the instruction AND the existing questions (so the
    // AI can avoid repeats), via the draft's provider.
    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->contains('Add one true/false question')
        && $prompt->contains('Old question about cells?')
        && $prompt->provider->name() === 'openai');
});

it('replaces the whole question set in replace mode', function () {
    Setting::set('openai_api_key', 'db-key');

    $admin = User::factory()->admin()->create();
    $draft = createReadyAiDraft($admin);

    AnonymousAgent::fake(['{"questions":[{"type":"essay","text":"Explain how the nucleus controls the cell."},{"type":"essay","text":"Compare plant and animal cells."}]}']);

    (new RefineAiQuestions($draft->id, 'Replace with two harder essay questions', 'replace'))
        ->handle(app(AiQuestionGeneratorService::class));

    $draft->refresh();

    expect($draft->status)->toBe('ready')
        ->and($draft->questions)->toHaveCount(2)
        ->and($draft->questions[0]['text'])->toBe('Explain how the nucleus controls the cell.')
        ->and($draft->questions[0]['type'])->toBe('essay');

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->contains('Replace with two harder essay questions'));
});

it('keeps the existing questions when the follow-up fails', function () {
    Setting::set('openai_api_key', 'db-key');

    $admin = User::factory()->admin()->create();
    $draft = createReadyAiDraft($admin);

    AnonymousAgent::fake(['This is not JSON at all.']);

    try {
        (new RefineAiQuestions($draft->id, 'Add more', 'add'))
            ->handle(app(AiQuestionGeneratorService::class));
        $this->fail('Expected a RuntimeException because the follow-up produced nothing usable.');
    } catch (RuntimeException) {
        // Expected — the job records the failure and rethrows.
    }

    $draft->refresh();

    expect($draft->status)->toBe('ready')
        ->and($draft->questions)->toHaveCount(1)
        ->and($draft->questions[0]['text'])->toBe('Old question about cells?')
        ->and($draft->last_error)->toContain('no usable questions')
        ->and($draft->ai_response)->toBe('This is not JSON at all.');
});

it('queues a follow-up from the edit page and stores the provider', function () {
    Queue::fake();
    Setting::set('openai_api_key', 'db-key');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $draft = createReadyAiDraft($admin, ['provider' => null]);

    $component = Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->mountAction('followUp');

    foreach ([
        'instruction' => 'Add 2 more about osmosis',
        'mode' => 'add',
        'provider' => 'openai',
    ] as $key => $value) {
        $component->set("mountedActions.0.data.{$key}", $value);
    }

    $component->callMountedAction()->assertHasNoActionErrors();

    $draft->refresh();

    expect($draft->provider)->toBe('openai')
        ->and($draft->status)->toBe('pending');

    Queue::assertPushed(RefineAiQuestions::class, fn ($job) => $job->draftId === $draft->id
        && $job->instruction === 'Add 2 more about osmosis'
        && $job->mode === 'add');
});

it('requires an instruction for the follow-up', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $draft = createReadyAiDraft($admin);

    Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->mountAction('followUp')
        ->callMountedAction()
        ->assertHasActionErrors(['instruction' => 'required']);
});

it('hides the follow-up action until the draft is ready', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $draft = createReadyAiDraft($admin, ['status' => 'pending', 'questions' => null]);

    Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->assertActionHidden('followUp');
});
