<?php

use App\Ai\Agents\BotAgent;
use App\Ai\Agents\CoordinatorAgent;
use App\Models\AiBot;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;

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

    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->admin->forceFill(['current_workspace_id' => $this->workspace->id])->save();
});

it('creates a solo bot session and attributes the reply to the bot', function () {
    BotAgent::fake(['Registrar reply']);

    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('chats.store'), ['bot_id' => $bot->id])
        ->assertOk()
        ->assertJsonPath('session.mode', ChatSession::MODE_SOLO)
        ->assertJsonPath('session.bot.id', $bot->id);

    $session = ChatSession::findOrFail($response->json('session.id'));

    expect($session->bot_id)->toBe($bot->id)
        ->and($session->participant_bot_ids)->toBeNull();

    $this->actingAs($this->admin)
        ->postJson(route('chats.message', $session), ['message' => 'Who handles enrolment?'])
        ->assertOk()
        ->assertJsonPath('response', 'Registrar reply');

    $this->assertDatabaseHas('chat_messages', [
        'session_id' => $session->id,
        'role' => 'assistant',
        'bot_id' => $bot->id,
    ]);
});

it('rejects bot sessions for non-admins', function () {
    $bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);
    $student = User::factory()->create();
    $student->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->actingAs($student)
        ->postJson(route('chats.store'), ['bot_id' => $bot->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bot_id');

    expect(ChatSession::count())->toBe(0);
});

it('does not allow chatting with the chief of staff directly', function () {
    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), ['bot_id' => $coordinator->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bot_id');
});

it('rejects an inactive or foreign workspace bot', function () {
    $inactive = AiBot::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $foreign = AiBot::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), ['bot_id' => $inactive->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bot_id');

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), ['bot_id' => $foreign->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bot_id');

    expect(ChatSession::count())->toBe(0);
});

it('creates a team chat with the active chief of staff and attributes the reply to it', function () {
    CoordinatorAgent::fake(['Team reply']);

    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $specialists = AiBot::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [
            'mode' => ChatSession::MODE_GROUP,
            'participant_bot_ids' => $specialists->modelKeys(),
        ])
        ->assertOk()
        ->assertJsonPath('session.mode', ChatSession::MODE_GROUP)
        ->assertJsonPath('session.participantBotIds', $specialists->modelKeys());

    $session = ChatSession::findOrFail($response->json('session.id'));

    expect($session->bot_id)->toBeNull()
        ->and($session->participant_bot_ids)->toBe($specialists->modelKeys());

    $this->actingAs($this->admin)
        ->postJson(route('chats.message', $session), ['message' => 'Plan next term'])
        ->assertOk()
        ->assertJsonPath('response', 'Team reply');

    $this->assertDatabaseHas('chat_messages', [
        'session_id' => $session->id,
        'role' => 'assistant',
        'bot_id' => $coordinator->id,
    ]);
});

it('uses the bot model override for solo chats', function () {
    BotAgent::fake(['Registrar reply']);

    $bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'model' => 'solo-override-model',
    ]);

    $session = ChatSession::query()->create([
        'user_id' => $this->admin->id,
        'bot_id' => $bot->id,
        'mode' => ChatSession::MODE_SOLO,
        'source' => 'echo',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.message', $session), ['message' => 'Who handles enrolment?'])
        ->assertOk();

    BotAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'solo-override-model');
});

it('uses the coordinator model override for team chats', function () {
    CoordinatorAgent::fake(['Team reply']);

    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
        'model' => 'team-override-model',
    ]);
    $specialists = AiBot::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $session = ChatSession::query()->create([
        'user_id' => $this->admin->id,
        'mode' => ChatSession::MODE_GROUP,
        'source' => 'echo',
        'participant_bot_ids' => $specialists->modelKeys(),
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.message', $session), ['message' => 'Plan next term'])
        ->assertOk();

    CoordinatorAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'team-override-model');
});

it('requires an active chief of staff for a team chat', function () {
    $specialists = AiBot::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [
            'mode' => ChatSession::MODE_GROUP,
            'participant_bot_ids' => $specialists->modelKeys(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('participant_bot_ids');

    expect(ChatSession::count())->toBe(0);
});

it('rejects a team chat that includes the coordinator or more than six specialists', function () {
    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $specialists = AiBot::factory()->count(7)->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [
            'mode' => ChatSession::MODE_GROUP,
            'participant_bot_ids' => [$coordinator->id, ...$specialists->take(2)->modelKeys()],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('participant_bot_ids');

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [
            'mode' => ChatSession::MODE_GROUP,
            'participant_bot_ids' => $specialists->modelKeys(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('participant_bot_ids');

    expect(ChatSession::count())->toBe(0);
});
