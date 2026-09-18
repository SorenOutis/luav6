<?php

use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Setting::flushAllCaches();

    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => null,
        'env_key' => null,
        'url' => 'https://api.openai.com/v1',
        'env_url' => 'https://api.openai.com/v1',
    ]]);

    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'db-key');
});

it('records a correlated, privacy-safe lifecycle for a widget chat', function () {
    $message = 'Please review my private assignment draft.';
    AssistantAgent::fake(['Here is structured feedback.']);
    Log::spy();

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => $message])
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.request.received'
            && $context['surface'] === 'widget'
            && $context['transport'] === 'sync'
            && $context['message'] === [
                'length' => mb_strlen($message),
                'sha256' => hash('sha256', $message),
            ]
            && ! str_contains(json_encode($context), $message))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.started'
            && $context['provider'] === 'openai'
            && $context['model'] === 'gpt-4o-mini'
            && $context['agent'] === 'AssistantAgent'
            && filled($context['chat_id']))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.completed'
            && $context['response'] === [
                'length' => mb_strlen('Here is structured feedback.'),
                'sha256' => hash('sha256', 'Here is structured feedback.'),
            ]
            && is_int($context['duration_ms']))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.response.persisted'
            && $context['session_id'] !== null)
        ->once();
});

it('records streamed completion after the SSE response is consumed', function () {
    AssistantAgent::fake(['A streamed answer.']);
    Log::spy();

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('chat.stream'), ['message' => 'Stream this answer']);

    $response->assertSuccessful();
    $response->streamedContent();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.stream_started'
            && $context['transport'] === 'stream'
            && $context['provider'] === 'openai')
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.stream_completed'
            && $context['response']['sha256'] === hash('sha256', 'A streamed answer.')
            && is_int($context['duration_ms']))
        ->once();
});

it('uses the same lifecycle events for chats continued from the history page', function () {
    AssistantAgent::fake(['History page answer.']);
    Log::spy();

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'New chat']);

    $this->actingAs($user)
        ->postJson(route('chats.message', $session), ['message' => 'Continue this chat'])
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.request.dispatched'
            && $context['surface'] === 'history'
            && $context['session_id'] === $session->id)
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.completed'
            && $context['surface'] === 'history'
            && $context['response']['sha256'] === hash('sha256', 'History page answer.'))
        ->once();
});

it('records guardrail blocks without sending a prompt to the provider', function () {
    AssistantAgent::fake(['Never used']);
    Log::spy();

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'fuck this'])
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.request.blocked'
            && $context['blocked_reason'] === 'toxicity_guardrail'
            && $context['message']['sha256'] === hash('sha256', 'fuck this'))
        ->once();

    AssistantAgent::assertNeverPrompted();
});

it('records the provider and request failure with the same structured context', function () {
    config(['ai.providers.gemini.env_key' => null]);
    Setting::set('ai_provider', 'gemini');
    Setting::set('gemini_api_key', '');
    Setting::set('ollama_enabled', '0');
    Log::spy();

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Why did this provider fail?'])
        ->assertServerError();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.provider.failed'
            && $context['provider'] === 'gemini'
            && $context['fallback_enabled'] === false
            && isset($context['exception']))
        ->once();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $logMessage, array $context): bool => $logMessage === 'AI chat lifecycle'
            && $context['event'] === 'ai_chat.request.failed'
            && $context['failure_stage'] === 'Chat Controller Error'
            && filled($context['error_id'])
            && isset($context['exception']))
        ->once();
});
