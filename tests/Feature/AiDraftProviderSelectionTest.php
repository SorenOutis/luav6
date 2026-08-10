<?php

/**
 * Provider selection for AI question drafts.
 *
 * Admins can override the platform default provider per draft — at creation
 * (questions or lesson source) and on regenerate. The choice is stored on
 * the draft and honored by the generation jobs.
 */

use App\Filament\Resources\AiQuestionDrafts\Pages\EditAiQuestionDraft;
use App\Filament\Resources\AiQuestionDrafts\Pages\ListAiQuestionDrafts;
use App\Jobs\GenerateAiQuestions;
use App\Jobs\GenerateAiSource;
use App\Models\AiQuestionDraft;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use App\Services\AiSdkProviderService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\AnonymousAgent;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();

    // Shield the assertions from any real env keys on the dev machine.
    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => null,
        'env_key' => null,
        'url' => 'https://api.openai.com/v1',
        'env_url' => 'https://api.openai.com/v1',
    ]]);
    config(['ai.providers.gemini' => [
        'driver' => 'gemini',
        'key' => null,
        'env_key' => null,
        'url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'models' => ['text' => ['default' => 'gemini-3.5-flash']],
    ]]);
    config(['ai.providers.groq.env_key' => null]);
});

it('lists only providers that have credentials configured', function () {
    // Fresh slate: only Ollama needs no key and is always available.
    $providers = AiSdkProviderService::configuredProviders();
    expect($providers)->toHaveKey('ollama')
        ->not->toHaveKey('openai')
        ->not->toHaveKey('gemini')
        ->not->toHaveKey('groq')
        ->not->toHaveKey('cloudflare');

    Setting::set('openai_api_key', 'db-key');
    Setting::set('gemini_api_key', 'gemini-key');
    Setting::set('cloudflare_account_id', 'cf-account');
    Setting::set('cloudflare_api_token', 'cf-token');

    $providers = AiSdkProviderService::configuredProviders();
    expect($providers)->toHaveKeys(['ollama', 'openai', 'gemini', 'cloudflare']);
});

it('stores the chosen provider on the draft when generating from pasted text', function () {
    Queue::fake();
    Setting::set('openai_api_key', 'db-key');

    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(ListAiQuestionDrafts::class)
        ->mountAction('generate');

    foreach ([
        'title' => 'Cell Biology',
        'generate_source' => false,
        'pasted_text' => 'Cells are the basic unit of life.',
        'counts.multiple_choice' => 2,
        'counts.true_false' => 0,
        'counts.identification' => 0,
        'counts.essay' => 0,
        'difficulty' => 'medium',
        'provider' => 'openai',
    ] as $key => $value) {
        $component->set("mountedActions.0.data.{$key}", $value);
    }

    $component->callMountedAction()->assertHasNoActionErrors();

    $draft = AiQuestionDraft::query()->latest('id')->first();

    expect($draft)->not->toBeNull()
        ->and($draft->provider)->toBe('openai')
        ->and($draft->status)->toBe('pending');

    Queue::assertPushed(GenerateAiQuestions::class, fn ($job) => $job->draftId === $draft->id);
});

it('generates questions with the provider stored on the draft', function () {
    Setting::set('openai_api_key', 'db-key');

    AnonymousAgent::fake(['{"questions":[{"type":"multiple_choice","text":"What is a cell?","options":[{"text":"Basic unit of life","is_correct":true},{"text":"A tissue","is_correct":false},{"text":"An organ","is_correct":false},{"text":"A system","is_correct":false}]}]}']);

    $admin = User::factory()->admin()->create();
    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Cells',
        'source_text' => 'Cells are the basic unit of life.',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'provider' => 'openai',
        'status' => 'pending',
    ]);

    (new GenerateAiQuestions($draft->id))->handle(app(AiQuestionGeneratorService::class));

    $draft->refresh();

    expect($draft->status)->toBe('ready')
        ->and($draft->questions)->toHaveCount(1)
        ->and($draft->questions[0]['text'])->toBe('What is a cell?');

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai'
        && $prompt->model === 'gpt-4o-mini');
});

it('generates the lesson source with the provider stored on the draft', function () {
    // Queue::fake keeps the chained GenerateAiQuestions dispatch from running.
    Queue::fake();
    Setting::set('openai_api_key', 'db-key');

    AnonymousAgent::fake(['A lesson about photosynthesis.']);

    $admin = User::factory()->admin()->create();
    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Photosynthesis',
        'source_text' => '',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'provider' => 'openai',
        'status' => 'generating_source',
    ]);

    (new GenerateAiSource($draft->id, 'Biology', 'senior_high', 'Photosynthesis', 500))
        ->handle(app(AiQuestionGeneratorService::class));

    $draft->refresh();

    expect($draft->status)->toBe('pending')
        ->and($draft->source_text)->toBe('A lesson about photosynthesis.');

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai');
    Queue::assertPushed(GenerateAiQuestions::class);
});

it('routes question generation through the Gemini SDK path when Gemini is the provider', function () {
    Setting::set('ai_provider', 'gemini');
    Setting::set('gemini_api_key', 'gemini-key');
    Setting::set('gemini_grading_model', 'gemini-2.5-pro');

    AnonymousAgent::fake(['{"questions": []}']);

    app(AiQuestionGeneratorService::class)->generate('Plant cells have walls.', ['multiple_choice' => 1]);

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'gemini'
        && $prompt->model === 'gemini-2.5-pro'
        && $prompt->provider->providerCredentials()['key'] === 'gemini-key');
});

it('stores the chosen provider when regenerating from the edit page', function () {
    Queue::fake();
    Setting::set('openai_api_key', 'db-key');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $draft = AiQuestionDraft::create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Cells',
        'source_text' => 'Cells are the basic unit of life.',
        'type_counts' => ['multiple_choice' => 1],
        'difficulty' => 'medium',
        'status' => 'ready',
        'questions' => [[
            'text' => 'Old question?',
            'type' => 'multiple_choice',
            'points' => 1,
            'options' => [['text' => 'a', 'is_correct' => true], ['text' => 'b', 'is_correct' => false]],
        ]],
    ]);

    $component = Livewire::test(EditAiQuestionDraft::class, ['record' => $draft->id])
        ->mountAction('regenerate');

    $component->set('mountedActions.0.data.provider', 'openai');

    $component->callMountedAction()->assertHasNoActionErrors();

    $draft->refresh();

    expect($draft->provider)->toBe('openai')
        ->and($draft->status)->toBe('pending')
        ->and($draft->questions)->toBeNull();

    Queue::assertPushed(GenerateAiQuestions::class, fn ($job) => $job->draftId === $draft->id);
});
