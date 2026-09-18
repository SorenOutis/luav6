<?php

/**
 * Role-aware Echo routing tests.
 *
 * ChatController picks the agent by role: students get AssistantAgent
 * (read-only own-data tools + daily XP claim), admins get
 * AdminAssistantAgent (workspace management tools). Students have a daily
 * message cap; admins are exempt. Groq chat goes through the Laravel AI
 * SDK so tool calling works.
 */

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;

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

it('routes students to the student assistant agent', function () {
    AssistantAgent::fake(['Student reply']);
    AdminAssistantAgent::fake(['Admin reply']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('response', 'Student reply');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai');
    AdminAssistantAgent::assertNeverPrompted();
});

it('routes admins to the admin assistant agent', function () {
    AssistantAgent::fake(['Student reply']);
    AdminAssistantAgent::fake(['Admin reply']);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('chat'), ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('response', 'Admin reply');

    AdminAssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'openai');
    AssistantAgent::assertNeverPrompted();
});

it('caps students at the daily message limit', function () {
    Setting::set('ai_chat_daily_limit', 1);
    AssistantAgent::fake(['First reply']);

    $student = User::factory()->create();

    $this->actingAs($student)
        ->postJson(route('chat'), ['message' => 'One'])
        ->assertOk()
        ->assertJsonPath('response', 'First reply');

    $this->actingAs($student)
        ->postJson(route('chat'), ['message' => 'Two'])
        ->assertOk()
        ->assertJsonPath('response', fn (string $response) => str_contains($response, 'limit resets at midnight'));
});

it('does not cap admins at the daily message limit', function () {
    Setting::set('ai_chat_daily_limit', 1);
    AdminAssistantAgent::fake(['One', 'Two']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson(route('chat'), ['message' => 'One'])
        ->assertJsonPath('response', 'One');

    $this->actingAs($admin)
        ->postJson(route('chat'), ['message' => 'Two'])
        ->assertJsonPath('response', 'Two');
});

it('routes groq chat through the SDK so tool calling works', function () {
    Setting::set('ai_provider', 'groq');
    Setting::set('groq_api_key', 'groq-key');
    Setting::set('groq_model', 'llama-3.3-70b-versatile');

    AssistantAgent::fake(['Groq reply']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('response', 'Groq reply');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === 'groq'
        && $prompt->model === 'llama-3.3-70b-versatile'
        && $prompt->provider->providerCredentials()['key'] === 'groq-key');
});

it('clears the chat history', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['echo_chat_history' => [['role' => 'user', 'content' => 'hi']]])
        ->postJson(route('chat.clear'))
        ->assertOk()
        ->assertSessionMissing('echo_chat_history');
});

it('shares student chat props on the dashboard for students', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('aiChat.isAdmin', false)
            ->has('aiChat.suggestions', 4));
});

it('shares admin chat props on the dashboard for admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('aiChat.isAdmin', true)
            ->where('aiChat.suggestions.0.label', '📋 Needs grading'));
});
