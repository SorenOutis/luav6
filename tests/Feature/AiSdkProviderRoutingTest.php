<?php

/**
 * SDK provider routing tests.
 *
 * When the default provider is an SDK-routed provider (OpenAI, Anthropic,
 * Mistral, DeepSeek, xAI, OpenRouter, Azure, Ollama), the chat widget, essay
 * grading, and AI question generation all go through the Laravel AI SDK with
 * the credentials and model saved in Platform Settings.
 */

use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;

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

it('routes chat through the SDK provider marked as default', function () {
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'db-key');
    Setting::set('openai_model', 'gpt-4o');

    AssistantAgent::fake(['Hello from OpenAI!']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Hi there'])
        ->assertOk()
        ->assertJsonPath('response', 'Hello from OpenAI!');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai'
        && $prompt->model === 'gpt-4o'
        && $prompt->provider->providerCredentials()['key'] === 'db-key');
});

it('keeps chat on Gemini when Gemini is the default', function () {
    Setting::set('ai_provider', 'gemini');
    Setting::set('gemini_api_key', 'gemini-key');

    AssistantAgent::fake(['Hello from Gemini!']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Hi there'])
        ->assertOk()
        ->assertJsonPath('response', 'Hello from Gemini!');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'gemini');
});

it('routes essay grading through the SDK provider marked as default', function () {
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'db-key');

    AnonymousAgent::fake(['{"score": 80}']);

    $result = app(AIService::class)->assessEssay('Some essay.', 'What is X?', 10);

    expect($result['score'])->toBe(8);

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai'
        && $prompt->model === 'gpt-4o-mini');
});

it('routes question generation through the SDK provider marked as default', function () {
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'db-key');

    AnonymousAgent::fake(['{"questions": []}']);

    $service = app(AiQuestionGeneratorService::class);
    $questions = $service->generate('Photosynthesis source text.', ['multiple_choice' => 1]);

    expect($questions)->toBe([])
        ->and($service->lastRawResponse)->toBe('{"questions": []}');

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai'
        && $prompt->model === 'gpt-4o-mini');
});

it('keeps question generation on the raw Ollama path when Ollama is the default', function () {
    Setting::set('ai_provider', 'ollama');
    Setting::set('ollama_url', 'http://localhost:11434');
    Setting::set('ollama_model', 'llama3.2:1b');

    Http::fake([
        'localhost:11434/*' => Http::response(['response' => '{"questions": []}']),
    ]);

    $questions = app(AiQuestionGeneratorService::class)->generate('Source text.', ['multiple_choice' => 1]);

    expect($questions)->toBe([]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'localhost:11434/api/generate'));
});

it('fails with a descriptive error when the default SDK provider is not configured', function () {
    Setting::set('ai_provider', 'openai');
    Setting::set('ollama_enabled', '0');

    app(AiQuestionGeneratorService::class)->generate('Source text.', ['multiple_choice' => 1]);
})->throws(RuntimeException::class, 'not configured');
