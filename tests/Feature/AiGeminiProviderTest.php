<?php

/**
 * Google Gemini provider configuration tests.
 *
 * Phase: "implement the google ai provider" — the admin pastes their Gemini
 * key in Platform Settings (Setting model) instead of editing .env. These
 * tests lock in that the DB-stored key/model flow into both the Laravel AI
 * SDK (chat + question generation) and the raw HTTP grading path.
 */

use App\Models\Setting;
use App\Services\AIService;
use App\Services\GeminiAIService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;

beforeEach(function () {
    Setting::flushAllCaches();

    // Give the SDK's gemini provider a base URL pointing at the real API so
    // applyToSdk() always has something to write (like config/ai.php does).
    config(['ai.providers.gemini' => [
        'driver' => 'gemini',
        'key' => 'env-fallback-key',
        'env_key' => 'env-fallback-key',
        'url' => 'https://generativelanguage.googleapis.com/v1beta/',
        'models' => ['text' => ['default' => 'gemini-3.5-flash']],
    ]]);
});

it('resolves the API key from Settings when one is pasted', function () {
    Setting::set('gemini_api_key', 'db-key');

    expect(app(GeminiAIService::class)->apiKey())->toBe('db-key');
});

it('falls back to the env/config key when no key is pasted', function () {
    expect(app(GeminiAIService::class)->apiKey())->toBe('env-fallback-key');
});

it('falls back to the pristine env key even after applyToSdk ran with a DB key', function () {
    // Simulate an Octane worker where a previous request (workspace A) pasted
    // its key and mutated the runtime config. Workspace B has no key of its
    // own, so it must still resolve the pristine env key — never A's leaked
    // key from the mutated config.
    Setting::set('gemini_api_key', 'workspace-a-key');
    app(GeminiAIService::class)->applyToSdk();
    expect(config('ai.providers.gemini.key'))->toBe('workspace-a-key');

    Setting::set('gemini_api_key', null);
    expect(app(GeminiAIService::class)->apiKey())->toBe('env-fallback-key');
});

it('resolves chat and grading models from Settings with defaults', function () {
    Setting::set('gemini_chat_model', 'gemini-3.1-flash-lite');
    Setting::set('gemini_grading_model', 'gemini-2.5-pro');

    $service = app(GeminiAIService::class);

    expect($service->chatModel())->toBe('gemini-3.1-flash-lite')
        ->and($service->gradingModel())->toBe('gemini-2.5-pro');
});

it('defaults to gemini-3.5-flash when models are not configured', function () {
    $service = app(GeminiAIService::class);

    expect($service->chatModel())->toBe('gemini-3.5-flash')
        ->and($service->gradingModel())->toBe('gemini-3.5-flash');
});

it('applyToSdk writes the DB key and model into the SDK provider config', function () {
    Setting::set('gemini_api_key', 'db-key');
    Setting::set('gemini_chat_model', 'gemini-3.5-flash-lite');

    app(GeminiAIService::class)->applyToSdk();

    expect(config('ai.providers.gemini.key'))->toBe('db-key')
        ->and(config('ai.providers.gemini.models.text.default'))->toBe('gemini-3.5-flash-lite');
});

it('applyToSdk with an explicit model overrides the chat model', function () {
    Setting::set('gemini_api_key', 'db-key');

    app(GeminiAIService::class)->applyToSdk('gemini-2.5-pro');

    expect(config('ai.providers.gemini.models.text.default'))->toBe('gemini-2.5-pro');
});

it('applyToSdk forgets the cached SDK provider instance and the next one uses the DB key', function () {
    Setting::set('gemini_api_key', 'db-key');

    $manager = app(AiManager::class);
    $manager->forgetInstance('gemini');
    $manager->instance('gemini'); // cache an instance with the old config

    app(GeminiAIService::class)->applyToSdk();

    // The cached instance must be dropped so the next resolution picks up the
    // newly written config (the SDK caches provider instances per name).
    expect($manager->instance('gemini')->providerCredentials()['key'])->toBe('db-key');
});

it('grading sends the DB-stored Gemini key to the Google API', function () {
    Setting::set('ai_provider', 'gemini');
    Setting::set('gemini_api_key', 'db-key');
    Setting::set('gemini_grading_model', 'gemini-2.5-pro');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => '{"score": 80}']]]],
            ],
        ]),
    ]);

    $result = app(AIService::class)->assessEssay('Some essay.', 'What is X?', 10);

    expect($result['score'])->toBe(8);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'key=db-key')
            && str_contains($request->url(), 'gemini-2.5-pro');
    });
});
