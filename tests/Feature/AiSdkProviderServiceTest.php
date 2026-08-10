<?php

/**
 * Laravel AI SDK provider configuration tests.
 *
 * The admin pastes credentials and picks a model for any text-capable SDK
 * provider (OpenAI, Anthropic, Mistral, DeepSeek, xAI, OpenRouter, Azure,
 * Ollama) in Platform Settings instead of editing .env. These tests lock in
 * the Settings → SDK runtime flow, including the Octane-safe pristine env
 * fallback (mirrors AiGeminiProviderTest for the Gemini-specific service).
 */

use App\Models\Setting;
use App\Services\AiSdkProviderService;
use Laravel\Ai\AiManager;

beforeEach(function () {
    Setting::flushAllCaches();

    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => 'env-fallback-key',
        'env_key' => 'env-fallback-key',
        'url' => 'https://api.openai.com/v1',
        'env_url' => 'https://api.openai.com/v1',
    ]]);
});

it('resolves the API key from Settings when one is pasted', function () {
    Setting::set('openai_api_key', 'db-key');

    expect(AiSdkProviderService::for('openai')->apiKey())->toBe('db-key');
});

it('falls back to the pristine env key when no key is pasted', function () {
    expect(AiSdkProviderService::for('openai')->apiKey())->toBe('env-fallback-key');
});

it('falls back to the pristine env key even after applyToSdk ran with a DB key', function () {
    // Simulate an Octane worker where a previous request (workspace A) pasted
    // its key and mutated the runtime config. Workspace B must still resolve
    // the pristine env key — never A's leaked key.
    Setting::set('openai_api_key', 'workspace-a-key');
    AiSdkProviderService::for('openai')->applyToSdk();
    expect(config('ai.providers.openai.key'))->toBe('workspace-a-key');

    Setting::set('openai_api_key', null);
    expect(AiSdkProviderService::for('openai')->apiKey())->toBe('env-fallback-key');
});

it('resolves the custom base URL from Settings with the env URL as fallback', function () {
    expect(AiSdkProviderService::for('openai')->url())->toBe('https://api.openai.com/v1');

    Setting::set('openai_url', 'https://proxy.example.com/v1');
    expect(AiSdkProviderService::for('openai')->url())->toBe('https://proxy.example.com/v1');
});

it('resolves the configured model with a per-provider default', function () {
    expect(AiSdkProviderService::for('openai')->model())->toBe('gpt-4o-mini');

    Setting::set('openai_model', 'gpt-4o');
    expect(AiSdkProviderService::for('openai')->model())->toBe('gpt-4o');
});

it('uses the Azure deployment as the model', function () {
    config(['ai.providers.azure' => [
        'driver' => 'azure',
        'key' => 'env-key',
        'env_key' => 'env-key',
        'deployment' => 'gpt-4o',
    ]]);

    expect(AiSdkProviderService::for('azure')->model())->toBe('gpt-4o');

    Setting::set('azure_deployment', 'my-deployment');
    expect(AiSdkProviderService::for('azure')->model())->toBe('my-deployment');
});

it('knows which providers are SDK routed and which require keys', function () {
    expect(AiSdkProviderService::isSdkRouted('openai'))->toBeTrue()
        ->and(AiSdkProviderService::isSdkRouted('ollama'))->toBeTrue()
        ->and(AiSdkProviderService::isSdkRouted('gemini'))->toBeFalse()
        ->and(AiSdkProviderService::isSdkRouted('groq'))->toBeFalse()
        ->and(AiSdkProviderService::isSdkRouted('cloudflare'))->toBeFalse()
        ->and(AiSdkProviderService::isSdkRouted('cohere'))->toBeFalse()
        ->and(AiSdkProviderService::isSdkRouted(null))->toBeFalse()
        ->and(AiSdkProviderService::for('ollama')->requiresApiKey())->toBeFalse()
        ->and(AiSdkProviderService::for('openai')->requiresApiKey())->toBeTrue();
});

it('is configured when a key resolves, while Ollama never needs one', function () {
    expect(AiSdkProviderService::for('openai')->isConfigured())->toBeTrue();
    expect(AiSdkProviderService::for('ollama')->isConfigured())->toBeTrue();

    config(['ai.providers.anthropic' => ['driver' => 'anthropic', 'key' => null, 'env_key' => null]]);
    expect(AiSdkProviderService::for('anthropic')->isConfigured())->toBeFalse();
});

it('applyToSdk writes the DB key, URL, and model into the SDK provider config', function () {
    Setting::set('openai_api_key', 'db-key');
    Setting::set('openai_url', 'https://proxy.example.com/v1');
    Setting::set('openai_model', 'gpt-4o');

    AiSdkProviderService::for('openai')->applyToSdk();

    expect(config('ai.providers.openai.key'))->toBe('db-key')
        ->and(config('ai.providers.openai.url'))->toBe('https://proxy.example.com/v1')
        ->and(config('ai.providers.openai.models.text.default'))->toBe('gpt-4o');
});

it('applyToSdk forgets the cached SDK provider instance and the next one uses the DB key', function () {
    Setting::set('openai_api_key', 'db-key');

    $manager = app(AiManager::class);
    $manager->forgetInstance('openai');
    $manager->instance('openai'); // cache an instance with the old config

    AiSdkProviderService::for('openai')->applyToSdk();

    // The cached instance must be dropped so the next resolution picks up the
    // newly written config (the SDK caches provider instances per name).
    expect($manager->instance('openai')->providerCredentials()['key'])->toBe('db-key');
});

it('applyToSdk writes the Azure deployment configuration', function () {
    config(['ai.providers.azure' => [
        'driver' => 'azure',
        'key' => 'env-key',
        'env_key' => 'env-key',
        'url' => 'https://resource.openai.azure.com',
        'env_url' => 'https://resource.openai.azure.com',
        'api_version' => '2024-10-21',
        'deployment' => 'gpt-4o',
        'embedding_deployment' => 'text-embedding-3-small',
    ]]);

    Setting::set('azure_deployment', 'my-chat-deployment');
    Setting::set('azure_api_version', '2025-01-01');

    AiSdkProviderService::for('azure')->applyToSdk();

    expect(config('ai.providers.azure.deployment'))->toBe('my-chat-deployment')
        ->and(config('ai.providers.azure.api_version'))->toBe('2025-01-01')
        ->and(config('ai.providers.azure.models.text.default'))->toBe('my-chat-deployment');
});
