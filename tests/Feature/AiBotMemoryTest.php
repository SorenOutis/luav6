<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\BotAgent;
use App\Ai\Tools\RememberBotMemory;
use App\Exceptions\PendingAiActionException;
use App\Models\AiBot;
use App\Models\AiBotMemory;
use App\Models\ChatSession;
use App\Models\PendingAiAction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PendingAiActionService;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->workspace = $this->admin->workspaces()->firstOrFail();

    $this->bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
        'allowed_tools' => ['students', 'remember_bot_memory'],
        'memory_scope' => AiBot::MEMORY_BOT,
    ]);

    $this->session = ChatSession::query()->create([
        'user_id' => $this->admin->id,
        'bot_id' => $this->bot->id,
        'mode' => ChatSession::MODE_SOLO,
        'source' => 'echo',
    ]);
});

it('stages a private memory action and writes only after human approval', function () {
    $this->actingAs($this->admin);

    $tool = new RememberBotMemory(
        chatSessionId: $this->session->id,
        botId: $this->bot->id,
    );

    $result = (string) $tool->handle(new Request([
        'content' => '  <b>Grade 8 uses standards-based rubrics.</b>  ',
        'importance' => 2,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'remember_bot_memory')->sole();

    expect($action->status)->toBe(PendingAiAction::STATUS_PENDING)
        ->and($action->bot_id)->toBe($this->bot->id)
        ->and($action->chat_session_id)->toBe($this->session->id)
        ->and($action->payload['bot_id'])->toBe($this->bot->id)
        ->and($action->payload['user_id'])->toBe($this->admin->id)
        ->and($action->payload['content'])->toBe('Grade 8 uses standards-based rubrics.')
        ->and($action->payload['importance'])->toBe(2)
        ->and($action->payload['source'])->toBe(AiBotMemory::SOURCE_APPROVED)
        ->and(AiBotMemory::query()->count())->toBe(0);

    $fields = collect($action->preview['changes'])->pluck('field')->all();

    expect($fields)->toContain('Memory', 'Visibility', 'Importance', 'Workspace');

    $nonce = app(PendingAiActionService::class)->present($action)['nonce'];
    app(PendingAiActionService::class)->approve($action, $this->admin, $nonce);

    $memory = AiBotMemory::query()->sole();

    expect($memory->workspace_id)->toBe($this->workspace->id)
        ->and($memory->bot_id)->toBe($this->bot->id)
        ->and($memory->user_id)->toBe($this->admin->id)
        ->and($memory->content)->toBe('Grade 8 uses standards-based rubrics.')
        ->and($memory->source)->toBe(AiBotMemory::SOURCE_APPROVED)
        ->and($memory->importance)->toBe(2)
        ->and($memory->created_by)->toBe($this->admin->id)
        ->and($action->fresh()->status)->toBe(PendingAiAction::STATUS_EXECUTED);
});

it('never mounts the memory tool on Echo and mounts it only when a bot allowlists it', function () {
    $echoTools = array_map(
        fn ($tool): string => ToolNameResolver::resolve($tool),
        (new AdminAssistantAgent)->tools(),
    );

    expect($echoTools)->not->toContain('remember_bot_memory');

    $botTools = array_map(
        fn ($tool): string => ToolNameResolver::resolve($tool),
        (new BotAgent($this->bot, $this->admin, $this->session))->tools(),
    );

    expect($botTools)->toContain('remember_bot_memory');

    $withoutMemory = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'allowed_tools' => ['students'],
    ]);

    $names = array_map(
        fn ($tool): string => ToolNameResolver::resolve($tool),
        (new BotAgent($withoutMemory, $this->admin, $this->session))->tools(),
    );

    expect($names)->not->toContain('remember_bot_memory');
});

it('injects only shared and actor-owned low-importance facts into bot instructions', function () {
    AiBotMemory::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'user_id' => null,
        'content' => 'Shared convention: reports run on Mondays.',
        'importance' => 1,
    ]);

    AiBotMemory::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'user_id' => $this->admin->id,
        'content' => 'This admin prefers short summaries.',
        'importance' => 1,
    ]);

    $otherAdmin = User::factory()->admin()->create();

    AiBotMemory::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'user_id' => $otherAdmin->id,
        'content' => 'Another admin private fact.',
        'importance' => 1,
    ]);

    AiBotMemory::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'user_id' => null,
        'content' => 'Low priority archive fact.',
        'importance' => 3,
    ]);

    expect($this->bot->memoryFactsFor($this->admin))
        ->toContain('Shared convention: reports run on Mondays.')
        ->toContain('This admin prefers short summaries.')
        ->not->toContain('Another admin private fact.')
        ->not->toContain('Low priority archive fact.');

    expect($this->bot->memoryFactsFor($otherAdmin))
        ->toContain('Shared convention: reports run on Mondays.')
        ->not->toContain('This admin prefers short summaries.');

    expect($this->bot->memoryFactsFor(null))
        ->toContain('Shared convention: reports run on Mondays.')
        ->not->toContain('This admin prefers short summaries.');

    $instructions = (string) (new BotAgent($this->bot, $this->admin, $this->session))->instructions();

    expect($instructions)->toContain('DURABLE MEMORY')
        ->toContain('Shared convention: reports run on Mondays.')
        ->toContain('This admin prefers short summaries.')
        ->not->toContain('Another admin private fact.')
        ->not->toContain('Low priority archive fact.');
});

it('rejects memory proposals from non-admins, without a bot, and with invalid content', function () {
    $student = User::factory()->create();

    $this->actingAs($student);

    expect((string) (new RememberBotMemory(botId: $this->bot->id))->handle(new Request(['content' => 'A fact'])))
        ->toContain('Only admins');

    $this->actingAs($this->admin);

    expect((string) (new RememberBotMemory)->handle(new Request(['content' => 'A fact'])))
        ->toContain('bot conversation');

    expect((string) (new RememberBotMemory(botId: $this->bot->id))->handle(new Request(['content' => 'x'])))
        ->toContain('content is required');

    expect((string) (new RememberBotMemory(botId: $this->bot->id))->handle(new Request(['content' => str_repeat('a', 2001)])))
        ->toContain('content is too long');

    expect(PendingAiAction::query()->count())->toBe(0);
});

it('rejects memory proposals for inactive or foreign-workspace bots', function () {
    $this->actingAs($this->admin);

    $foreign = AiBot::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'allowed_tools' => ['remember_bot_memory'],
    ]);

    expect((string) (new RememberBotMemory(botId: $foreign->id))->handle(new Request(['content' => 'A foreign fact'])))
        ->toContain('inactive or no longer belongs');

    $this->bot->update(['is_active' => false]);

    expect((string) (new RememberBotMemory(botId: $this->bot->id))->handle(new Request(['content' => 'An inactive fact'])))
        ->toContain('inactive or no longer belongs');

    expect(PendingAiAction::query()->count())->toBe(0);
});

it('refuses to execute a tampered memory action that targets another user', function () {
    $this->actingAs($this->admin);

    $otherAdmin = User::factory()->admin()->create();

    $action = app(PendingAiActionService::class)->stage(
        'remember_bot_memory',
        "Remember for {$this->bot->name}",
        'Tampered fact',
        [
            'bot_id' => $this->bot->id,
            'user_id' => $otherAdmin->id,
            'content' => 'Tampered fact',
            'importance' => 2,
        ],
        ['changes' => [['field' => 'Memory', 'before' => null, 'after' => 'Tampered fact']]],
        $this->session->id,
        $this->workspace->id,
        $this->bot->id,
    );

    $nonce = app(PendingAiActionService::class)->present($action)['nonce'];

    expect(fn () => app(PendingAiActionService::class)->approve($action, $this->admin, $nonce))
        ->toThrow(PendingAiActionException::class, 'cannot target another user');

    expect(AiBotMemory::query()->count())->toBe(0)
        ->and($action->fresh()->status)->toBe(PendingAiAction::STATUS_FAILED);
});
