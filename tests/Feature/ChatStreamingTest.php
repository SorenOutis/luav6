<?php

/**
 * Streaming Echo responses (POST /api/chat/stream) return a Laravel AI SDK
 * SSE stream. These tests lock in the happy path, the guardrails (toxicity,
 * daily cap), attachment handling, and that the user turn is persisted even
 * though the assistant reply is streamed.
 */

use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

it('streams an echo response as server-sent events', function () {
    AssistantAgent::fake(['Streamed reply']);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('chat.stream'), ['message' => 'Hello']);

    $response->assertSuccessful();
    $content = $response->streamedContent();

    expect($content)->toContain('text_delta')
        ->and($content)->toContain('Streamed')
        ->and($content)->toContain('[DONE]');
});

it('persists the user turn for a streamed exchange', function () {
    AssistantAgent::fake(['Streamed reply']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('chat.stream'), ['message' => 'What exams do I have?'])
        ->assertSuccessful();

    $session = $user->chatSessions()->first();

    expect($session)->not->toBeNull();
    expect($session->messages()->where('role', 'user')->where('content', 'What exams do I have?')->exists())->toBeTrue();
});

it('persists attachments metadata on the streamed user turn', function () {
    AssistantAgent::fake(['Got it']);

    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('notes.txt', 'study notes');

    $response = $this->actingAs($user)
        ->post(route('chat.stream'), [
            'message' => 'Review this',
            'attachments' => [
                $file,
            ],
        ]);

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('[DONE]');

    $session = $user->chatSessions()->first();
    $userMessage = $session->messages()->where('role', 'user')->first();

    expect($userMessage->attachments)->toBe([
        ['name' => 'notes.txt', 'size' => 11, 'mime' => 'text/plain', 'kind' => 'document'],
    ]);
});

it('rejects too many attachments on a streamed message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('chat.stream'), [
            'message' => 'Too many',
            'attachments' => collect(range(1, 5))->map(fn () => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))->all(),
        ])
        ->assertSessionHasErrors('attachments');
});

it('streams the toxicity guardrail response instead of calling the AI', function () {
    AssistantAgent::fake(['Should not appear']);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('chat.stream'), ['message' => 'fuck this']);

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('stay respectful');
    expect($response->streamedContent())->not->toContain('Should not appear');

    AssistantAgent::assertNeverPrompted();
});

it('streams the daily limit message for capped students', function () {
    Setting::set('ai_chat_daily_limit', 1);
    AssistantAgent::fake(['First reply']);

    $student = User::factory()->create();

    $this->actingAs($student)
        ->postJson(route('chat.stream'), ['message' => 'One'])
        ->assertSuccessful();

    $response = $this->actingAs($student)
        ->postJson(route('chat.stream'), ['message' => 'Two']);

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('limit resets at midnight');
});

it('streams a reply on the Chats history page as server-sent events', function () {
    AssistantAgent::fake(['Chats page reply']);

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'About exams']);

    $response = $this->actingAs($user)
        ->postJson(route('chats.stream', $session), ['message' => 'Hello']);

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('text_delta')
        ->and($response->streamedContent())->toContain('Chats')
        ->and($response->streamedContent())->toContain('[DONE]');
});

it('persists user and assistant turns when streaming on the Chats page', function () {
    AssistantAgent::fake(['Chats page reply']);

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'About exams']);

    $response = $this->actingAs($user)
        ->postJson(route('chats.stream', $session), ['message' => 'What exams?']);

    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('[DONE]');

    $session->refresh()->load('messages');

    expect($session->messages->where('role', 'user')->where('content', 'What exams?')->count())->toBe(1);

    // The assistant turn is persisted once the stream completes.
    expect($session->messages->where('role', 'assistant')->where('content', 'Chats page reply')->count())->toBe(1);
});

it('persists attachments metadata on a Chats page message', function () {
    AssistantAgent::fake(['Got it']);

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'About exams']);

    $response = $this->actingAs($user)
        ->post(route('chats.message', $session), [
            'message' => 'Review this',
            'attachments' => [
                UploadedFile::fake()->createWithContent('notes.txt', 'study notes'),
            ],
        ]);

    $response->assertOk();

    $session->refresh()->load('messages');
    $userMessage = $session->messages->where('role', 'user')->first();

    expect($userMessage->attachments)->toBe([
        ['name' => 'notes.txt', 'size' => 11, 'mime' => 'text/plain', 'kind' => 'document'],
    ]);
});
