<?php

/**
 * Persisted Chats history tests.
 *
 * The Echo widget conversations are saved into chat_sessions /
 * chat_messages so users can reopen, continue, or delete them from the
 * /chats page. The widget's POST /api/chat auto-creates a session (and
 * migrates legacy PHP-session history into it), while the Chats page
 * continues conversations through POST /api/chats/{session}/messages.
 */

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
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

it('renders the chats page with the user sessions', function () {
    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'About exams']);
    $session->messages()->createMany([
        ['role' => 'user', 'content' => 'What exams do I have?'],
        ['role' => 'assistant', 'content' => 'You have Math tomorrow.'],
    ]);

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chats')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id)
            ->where('sessions.0.title', 'About exams')
            ->where('sessions.0.messageCount', 2));
});

it('keeps chat history accessible while AI chat is under maintenance', function () {
    Setting::set('ai_chat_enabled', '0');
    Setting::set('ai_chat_maintenance_message', 'Echo is getting an upgrade. Please try again soon.');

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'Readable history']);
    $session->messages()->createMany([
        ['role' => 'user', 'content' => 'An earlier question'],
        ['role' => 'assistant', 'content' => 'An earlier answer'],
    ]);

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chats')
            ->has('sessions', 1)
            ->where('aiChat.enabled', false)
            ->where('aiChat.maintenanceMessage', 'Echo is getting an upgrade. Please try again soon.'));

    $this->actingAs($user)
        ->get(route('chats.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chats')
            ->where('activeSession.id', $session->id)
            ->has('activeSession.messages', 2));
});

it('blocks only chat composition while AI chat is under maintenance', function () {
    Setting::set('ai_chat_enabled', '0');
    Setting::set('ai_chat_maintenance_message', 'Echo is getting an upgrade. Please try again soon.');

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'Existing chat']);

    $this->actingAs($user)
        ->postJson(route('chats.store'))
        ->assertStatus(503)
        ->assertJsonPath('response', 'Echo is getting an upgrade. Please try again soon.');

    $this->actingAs($user)
        ->postJson(route('chats.message', $session), ['message' => 'Should not be sent'])
        ->assertStatus(503)
        ->assertJsonPath('response', 'Echo is getting an upgrade. Please try again soon.');

    $this->assertDatabaseCount('chat_sessions', 1);
    $this->assertDatabaseCount('chat_messages', 0);

    // Reading and managing saved history remain available during AI downtime.
    $this->actingAs($user)
        ->deleteJson(route('chats.destroy', $session))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('lists only the authenticated users sessions on the chats page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->chatSessions()->create(['title' => 'Mine']);
    $otherUser->chatSessions()->create(['title' => 'Someone elses']);

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Mine'));
});

it('loads a session with its messages on the chats show page', function () {
    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'My chat']);
    $session->messages()->createMany([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there'],
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chats')
            ->where('activeSession.id', $session->id)
            ->has('activeSession.messages', 2));
});

it('hides another users session from the show page', function () {
    $otherUser = User::factory()->create();
    $session = $otherUser->chatSessions()->create(['title' => 'Secret']);

    $this->actingAs(User::factory()->create())
        ->get(route('chats.show', $session))
        ->assertNotFound();
});

it('creates an empty session via the new chat endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('chats.store'))
        ->assertOk();

    $this->assertDatabaseHas('chat_sessions', [
        'id' => $response->json('session.id'),
        'user_id' => $user->id,
        'title' => 'New chat',
    ]);
});

it('persists the widget conversation into a new DB session', function () {
    AssistantAgent::fake(['Student reply']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('chat'), ['message' => 'Which exams do I have?'])
        ->assertOk()
        ->assertJsonPath('response', 'Student reply');

    $sessionId = $response->json('session_id');
    $this->assertNotNull($sessionId);

    $this->assertDatabaseHas('chat_sessions', [
        'id' => $sessionId,
        'user_id' => $user->id,
        'title' => 'Which exams do I have?',
    ]);

    $this->assertDatabaseCount('chat_messages', 2);
    $this->assertDatabaseHas('chat_messages', [
        'session_id' => $sessionId,
        'role' => 'user',
        'content' => 'Which exams do I have?',
    ]);
    $this->assertDatabaseHas('chat_messages', [
        'session_id' => $sessionId,
        'role' => 'assistant',
        'content' => 'Student reply',
    ]);
});

it('continues the widget conversation in the same session', function () {
    AssistantAgent::fake(['First reply', 'Second reply']);

    $user = User::factory()->create();

    $first = $this->actingAs($user)
        ->postJson(route('chat'), ['message' => 'Hello'])
        ->assertOk();

    $sessionId = $first->json('session_id');

    $second = $this->actingAs($user)
        ->postJson(route('chat'), ['message' => 'Tell me more', 'session_id' => $sessionId])
        ->assertOk()
        ->assertJsonPath('response', 'Second reply');

    $this->assertSame($first->json('session_id'), $second->json('session_id'));
    $this->assertDatabaseCount('chat_messages', 4);
    $this->assertSame(1, ChatSession::where('user_id', $user->id)->count());
});

it('continues an unknown session only via the stored widget session', function () {
    AssistantAgent::fake(['Reply']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('chat'), ['message' => 'Hi', 'session_id' => 9999])
        ->assertOk();

    $this->assertSame(1, ChatSession::where('user_id', $user->id)->count());
});

it('migrates legacy session history into a persisted session', function () {
    AssistantAgent::fake(['Reply']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['echo_chat_history' => [
            ['role' => 'user', 'content' => 'old question'],
            ['role' => 'assistant', 'content' => 'old answer'],
        ]])
        ->postJson(route('chat'), ['message' => 'New question'])
        ->assertOk();

    $this->assertSame(1, ChatSession::where('user_id', $user->id)->count());
    $this->assertDatabaseHas('chat_sessions', [
        'user_id' => $user->id,
        'title' => 'old question',
    ]);
    $this->assertDatabaseCount('chat_messages', 4);
});

it('continues a saved conversation from the chats page', function () {
    AssistantAgent::fake(['Helpful answer']);

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'New chat']);
    $session->messages()->createMany([
        ['role' => 'user', 'content' => 'First question'],
        ['role' => 'assistant', 'content' => 'First answer'],
    ]);

    $this->actingAs($user)
        ->postJson(route('chats.message', $session), ['message' => 'Second question'])
        ->assertOk()
        ->assertJsonPath('response', 'Helpful answer')
        ->assertJsonPath('session.id', $session->id);

    $this->assertDatabaseCount('chat_messages', 4);
    $this->assertDatabaseHas('chat_messages', [
        'session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Helpful answer',
    ]);

    $this->assertSame('Second question', $session->fresh()->title);
});

it('cannot message or delete another users session', function () {
    AssistantAgent::fake(['Never reached']);

    $otherUser = User::factory()->create();
    $session = $otherUser->chatSessions()->create(['title' => 'Secret']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chats.message', $session), ['message' => 'Hi'])
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('chats.destroy', $session))
        ->assertNotFound();

    $this->assertDatabaseHas('chat_sessions', ['id' => $session->id]);
});

it('deletes a chat and cascades its messages', function () {
    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'To delete']);
    $session->messages()->createMany([
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => 'Hello'],
    ]);

    $this->actingAs($user)
        ->deleteJson(route('chats.destroy', $session))
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseMissing('chat_sessions', ['id' => $session->id]);
    $this->assertDatabaseCount('chat_messages', 0);
    $this->assertSame(0, ChatMessage::where('session_id', $session->id)->count());
});

it('lets students continue chats without exceeding the daily cap separately', function () {
    Setting::set('ai_chat_daily_limit', 2);
    AssistantAgent::fake(['One', 'Two', 'Three']);

    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'New chat']);

    $this->actingAs($user)
        ->postJson(route('chat'), ['message' => 'Widget message'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('chats.message', $session), ['message' => 'History message'])
        ->assertOk()
        ->assertJsonPath('response', 'Two');

    $this->actingAs($user)
        ->postJson(route('chats.message', $session), ['message' => 'Last one'])
        ->assertOk()
        ->assertJsonPath('response', fn (string $response) => str_contains($response, 'limit resets at midnight'));
});

it('routes admin chats on the history page to the admin agent', function () {
    AdminAssistantAgent::fake(['Admin reply']);
    AssistantAgent::fake(['Student reply']);

    $admin = User::factory()->admin()->create();
    $session = $admin->chatSessions()->create(['title' => 'Workspace chat']);

    $this->actingAs($admin)
        ->postJson(route('chats.message', $session), ['message' => 'Overview'])
        ->assertOk()
        ->assertJsonPath('response', 'Admin reply');
});
