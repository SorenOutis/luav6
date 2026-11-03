<?php

use App\Filament\Resources\PlatformAiBots\Pages\BotOperations;
use App\Filament\Resources\PlatformAiBots\Pages\ListPlatformAiBots;
use App\Filament\Resources\PlatformAiBots\PlatformAiBotResource;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\AiBotRoutine;
use App\Models\AiUsageLog;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\PendingAiAction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiBotOperationsDashboardService;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['name' => 'Alpha Academy']);
    $this->otherWorkspace = Workspace::factory()->create(['name' => 'Beta Academy']);

    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->superAdmin->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->chief = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Chief of Staff',
    ]);

    $this->registrar = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Registrar',
    ]);

    $this->otherBot = AiBot::factory()->create([
        'workspace_id' => $this->otherWorkspace->id,
        'name' => 'Other Bot',
    ]);

    $this->makeSession = function (array $attributes = []): ChatSession {
        return ChatSession::query()->create(array_merge([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $this->superAdmin->id,
            'bot_id' => $this->registrar->id,
            'mode' => ChatSession::MODE_SOLO,
            'title' => 'Enrollment questions',
            'source' => 'echo',
        ], $attributes));
    };

    $this->makeApproval = function (array $attributes = []): PendingAiAction {
        return PendingAiAction::query()->create(array_merge([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->superAdmin->id,
            'bot_id' => $this->registrar->id,
            'action_type' => 'create_announcement',
            'title' => 'Publish enrollment reminder',
            'summary' => 'Announcement for continuing students.',
            'payload' => ['title' => 'Reminder'],
            'payload_hash' => str_repeat('a', 64),
            'preview' => [],
            'nonce_ciphertext' => 'nonce',
            'status' => PendingAiAction::STATUS_PENDING,
            'expires_at' => now()->addHours(2),
        ], $attributes));
    };
});

it('lets super admins open the read-only operations page and blocks everyone else', function () {
    $this->actingAs($this->superAdmin)
        ->get(PlatformAiBotResource::getUrl('operations'))
        ->assertOk()
        ->assertSee('Bot Operations')
        ->assertSee('Read-only');

    $this->actingAs(User::factory()->admin()->create())
        ->get(PlatformAiBotResource::getUrl('operations'))
        ->assertForbidden();

    auth()->logout();

    $this->get(PlatformAiBotResource::getUrl('operations'))
        ->assertRedirect();
});

it('defaults to the current workspace and can switch to all workspaces', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertOk()
        ->assertSet('workspaceFilter', '')
        ->assertSee('Chief of Staff')
        ->assertSee('Registrar')
        ->assertDontSee('Other Bot')
        ->set('workspaceFilter', AiBotOperationsDashboardService::ALL_WORKSPACES)
        ->assertSee('Workspace pulse')
        ->assertSee('Beta Academy')
        ->assertSee('Other Bot')
        ->set('workspaceFilter', (string) $this->otherWorkspace->id)
        ->assertSee('Other Bot')
        ->assertDontSee('Registrar');
});

it('summarizes handoffs, approvals, routines, activity, and usage', function () {
    $session = ($this->makeSession)();

    ChatMessage::query()->create([
        'session_id' => $session->id,
        'bot_id' => $this->registrar->id,
        'role' => 'assistant',
        'content' => 'Enrollment closes on Friday.',
    ]);

    AiBotHandoff::factory()->create([
        'workspace_id' => $this->workspace->id,
        'from_bot_id' => $this->chief->id,
        'to_bot_id' => $this->registrar->id,
        'requested_by_user_id' => $this->superAdmin->id,
        'status' => AiBotHandoff::STATUS_QUEUED,
    ]);

    AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->registrar->id,
        'name' => 'Morning enrollment digest',
        'last_run_at' => now()->subHour(),
        'last_status' => AiBotRoutine::STATUS_FAILED,
        'last_error' => 'Provider timed out.',
    ]);

    $approval = ($this->makeApproval)();

    AiUsageLog::query()->create([
        'date' => now()->toDateString(),
        'provider' => 'openai',
        'model' => 'gpt-test',
        'source' => 'ai_bot_chat',
        'bot_id' => $this->registrar->id,
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'estimated_cost_micros' => 2500,
        'workspace_id' => $this->workspace->id,
    ]);

    ChatSession::query()->create([
        'uuid' => (string) Str::uuid7(),
        'user_id' => $this->superAdmin->id,
        'bot_id' => $this->registrar->id,
        'mode' => ChatSession::MODE_SOLO,
        'title' => 'Morning enrollment digest',
        'source' => 'routine',
    ]);

    $this->actingAs($this->superAdmin);

    $dashboard = app(AiBotOperationsDashboardService::class)->dashboard();
    $kpis = collect($dashboard['kpis'])->keyBy('key');

    expect($kpis['active_bots']['raw'])->toBe(2)
        ->and($kpis['live_sessions']['raw'])->toBe(2)
        ->and($kpis['pending_handoffs']['raw'])->toBe(1)
        ->and($kpis['pending_approvals']['raw'])->toBe(1)
        ->and($kpis['failed_handoffs']['raw'])->toBe(0)
        ->and($kpis['routine_failures']['raw'])->toBe(1)
        ->and($kpis['messages']['raw'])->toBe(1)
        ->and($kpis['tokens']['raw'])->toBe(1500)
        ->and($dashboard['usage']['total_tokens'])->toBe(1500)
        ->and($dashboard['usage']['total_cost_micros'])->toBe(2500)
        ->and($dashboard['approvals']['counts'][PendingAiAction::STATUS_PENDING])->toBe(1)
        ->and($dashboard['routines'][0]['runs_today'])->toBe(1)
        ->and($dashboard['approvals']['items'][0]['id'])->toBe($approval->id);
});

it('shows assistant excerpts without leaking user messages or approval payloads', function () {
    $session = ($this->makeSession)();

    ChatMessage::query()->create([
        'session_id' => $session->id,
        'bot_id' => $this->registrar->id,
        'role' => 'user',
        'content' => 'My private student question',
    ]);

    ChatMessage::query()->create([
        'session_id' => $session->id,
        'bot_id' => $this->registrar->id,
        'role' => 'assistant',
        'content' => 'Enrollment closes on Friday.',
    ]);

    ($this->makeApproval)([
        'summary' => 'Approval summary is safe to show.',
        'payload' => ['private' => 'raw approval payload'],
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertSee('Enrollment closes on Friday.')
        ->assertSee('Approval summary is safe to show.')
        ->assertDontSee('My private student question')
        ->assertDontSee('raw approval payload')
        ->set('activityFilter', AiBotOperationsDashboardService::ACTIVITY_MESSAGES)
        ->assertSee('Reply from Registrar');

    $items = app(AiBotOperationsDashboardService::class)
        ->dashboard('', AiBotOperationsDashboardService::PERIOD_7_DAYS, AiBotOperationsDashboardService::ACTIVITY_MESSAGES)['activity']['items'];

    expect(collect($items)->pluck('type')->unique()->all())->toBe(['message']);
});

it('filters the activity feed by event type', function () {
    $session = ($this->makeSession)();

    ChatMessage::query()->create([
        'session_id' => $session->id,
        'bot_id' => $this->registrar->id,
        'role' => 'assistant',
        'content' => 'Enrollment closes on Friday.',
    ]);

    AiBotHandoff::factory()->create([
        'workspace_id' => $this->workspace->id,
        'from_bot_id' => $this->chief->id,
        'to_bot_id' => $this->registrar->id,
        'requested_by_user_id' => $this->superAdmin->id,
        'status' => AiBotHandoff::STATUS_COMPLETED,
    ]);

    ChatSession::query()->create([
        'uuid' => (string) Str::uuid7(),
        'user_id' => $this->superAdmin->id,
        'bot_id' => $this->registrar->id,
        'mode' => ChatSession::MODE_SOLO,
        'title' => 'Scheduled check-in',
        'source' => 'routine',
    ]);

    ($this->makeApproval)();

    $this->actingAs($this->superAdmin);

    $service = app(AiBotOperationsDashboardService::class);

    foreach ([
        AiBotOperationsDashboardService::ACTIVITY_MESSAGES => 'message',
        AiBotOperationsDashboardService::ACTIVITY_HANDOFFS => 'handoff',
        AiBotOperationsDashboardService::ACTIVITY_ROUTINES => 'routine',
        AiBotOperationsDashboardService::ACTIVITY_APPROVALS => 'approval',
    ] as $filter => $type) {
        $items = $service->dashboard('', AiBotOperationsDashboardService::PERIOD_7_DAYS, $filter)['activity']['items'];

        expect($items)->not->toBeEmpty()
            ->and(collect($items)->pluck('type')->unique()->all())->toBe([$type]);
    }

    $policyItems = $service->dashboard('', AiBotOperationsDashboardService::PERIOD_7_DAYS, AiBotOperationsDashboardService::ACTIVITY_POLICIES)['activity']['items'];

    expect($policyItems)->not->toBeEmpty()
        ->and(collect($policyItems)->pluck('type')->unique()->all())->toBe(['policy']);
});

it('surfaces operational warnings across workspaces', function () {
    Workspace::factory()->create(['name' => 'Gamma Academy']);

    AiBot::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Inactive Coach',
    ]);

    AiBotHandoff::factory()->create([
        'workspace_id' => $this->workspace->id,
        'from_bot_id' => $this->chief->id,
        'to_bot_id' => $this->registrar->id,
        'requested_by_user_id' => $this->superAdmin->id,
        'status' => AiBotHandoff::STATUS_QUEUED,
        'created_at' => now()->subMinutes(30),
    ]);

    AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->registrar->id,
        'name' => 'Nightly sync',
        'last_run_at' => now()->subHour(),
        'last_status' => AiBotRoutine::STATUS_FAILED,
        'last_error' => 'Provider timed out.',
    ]);

    ($this->makeApproval)(['expires_at' => now()->addHours(2)]);

    $this->actingAs($this->superAdmin);

    $warnings = collect(
        app(AiBotOperationsDashboardService::class)
            ->dashboard(AiBotOperationsDashboardService::ALL_WORKSPACES)['health']
    );

    expect($warnings->pluck('title')->all())
        ->toContain('Gamma Academy has no active Chief of Staff')
        ->toContain('Inactive Coach is inactive')
        ->toContain('Handoff waiting too long')
        ->toContain('Routine failed · Nightly sync')
        ->toContain('Approval expires soon');
});

it('opens a read-only bot drawer and links back to the control center', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertDontSee('Recent assistant messages')
        ->call('openBot', $this->registrar->id)
        ->assertSet('botFilter', (string) $this->registrar->id)
        ->assertSee('Recent assistant messages')
        ->assertSee('Policy changes stay on the control-center editor.')
        ->assertSee('Edit bot')
        ->call('closeBot')
        ->assertSet('botFilter', '')
        ->assertDontSee('Recent assistant messages');

    $this->get(PlatformAiBotResource::getUrl('operations', ['bot' => $this->registrar->id]))
        ->assertOk()
        ->assertSee('Recent assistant messages')
        ->assertSee(PlatformAiBotResource::getUrl('edit', ['record' => $this->registrar->id]));
});

it('scopes group sessions by participant bots', function () {
    $groupSession = ($this->makeSession)([
        'bot_id' => null,
        'mode' => ChatSession::MODE_GROUP,
        'participant_bot_ids' => [$this->chief->id, $this->registrar->id],
        'title' => 'Team sync',
    ]);

    $this->actingAs($this->superAdmin);

    $service = app(AiBotOperationsDashboardService::class);

    $currentSessions = collect($service->dashboard()['sessions'])->pluck('id');
    $otherSessions = collect($service->dashboard((string) $this->otherWorkspace->id)['sessions'])->pluck('id');
    $allSessions = collect($service->dashboard(AiBotOperationsDashboardService::ALL_WORKSPACES)['sessions'])->pluck('id');

    expect($currentSessions)->toContain($groupSession->id)
        ->and($otherSessions)->not->toContain($groupSession->id)
        ->and($allSessions)->toContain($groupSession->id);
});

it('only links chats owned by the signed-in super admin', function () {
    $ownSession = ($this->makeSession)();
    $otherAdmin = User::factory()->admin()->create();
    $otherSession = ($this->makeSession)([
        'user_id' => $otherAdmin->id,
        'title' => 'Another admin session',
    ]);

    $this->actingAs($this->superAdmin);

    $sessions = collect(
        app(AiBotOperationsDashboardService::class)->dashboard()['sessions']
    )->keyBy('id');

    expect($sessions[$ownSession->id]['can_open_chat'])->toBeTrue()
        ->and($sessions[$otherSession->id]['can_open_chat'])->toBeFalse();

    $this->get(PlatformAiBotResource::getUrl('operations'))
        ->assertOk()
        ->assertSee($ownSession->uuid)
        ->assertDontSee($otherSession->uuid);
});

it('renders useful empty states for a workspace without bots', function () {
    $emptyWorkspace = Workspace::factory()->create(['name' => 'Empty Academy']);
    $this->superAdmin->forceFill(['current_workspace_id' => $emptyWorkspace->id])->save();

    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertOk()
        ->assertSee('Empty Academy')
        ->assertSee('No bots configured for this scope yet.')
        ->assertSee('No activity matches this filter.')
        ->assertSee('No routines configured in this scope.')
        ->assertSee('No bot sessions in this period.')
        ->assertSee('No token usage recorded in this period.');
});

it('pauses polling and registers operations in navigation and the control center', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertSet('polling', true)
        ->call('togglePolling')
        ->assertSet('polling', false);

    $navigation = collect(PlatformAiBotResource::getNavigationItems());

    expect($navigation)->toHaveCount(2)
        ->and($navigation->map(fn ($item): string => $item->getLabel())->all())
        ->toContain('Bot Control Center', 'Bot Operations');

    Livewire::test(ListPlatformAiBots::class)
        ->assertSee('Live operations');
});

it('accepts only supported filter values and reads them from the url', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(BotOperations::class)
        ->assertSet('periodFilter', AiBotOperationsDashboardService::PERIOD_7_DAYS)
        ->call('setPeriod', AiBotOperationsDashboardService::PERIOD_30_DAYS)
        ->assertSet('periodFilter', AiBotOperationsDashboardService::PERIOD_30_DAYS)
        ->call('setPeriod', 'bogus')
        ->assertSet('periodFilter', AiBotOperationsDashboardService::PERIOD_30_DAYS)
        ->assertSet('activityFilter', AiBotOperationsDashboardService::ACTIVITY_ALL)
        ->call('setActivity', AiBotOperationsDashboardService::ACTIVITY_HANDOFFS)
        ->assertSet('activityFilter', AiBotOperationsDashboardService::ACTIVITY_HANDOFFS)
        ->call('setActivity', 'bogus')
        ->assertSet('activityFilter', AiBotOperationsDashboardService::ACTIVITY_HANDOFFS);

    Livewire::withQueryParams([
        'period' => AiBotOperationsDashboardService::PERIOD_TODAY,
        'activity' => AiBotOperationsDashboardService::ACTIVITY_POLICIES,
        'workspace' => AiBotOperationsDashboardService::ALL_WORKSPACES,
    ])
        ->test(BotOperations::class)
        ->assertSet('periodFilter', AiBotOperationsDashboardService::PERIOD_TODAY)
        ->assertSet('activityFilter', AiBotOperationsDashboardService::ACTIVITY_POLICIES)
        ->assertSet('workspaceFilter', AiBotOperationsDashboardService::ALL_WORKSPACES)
        ->assertSee('Workspace pulse');
});

it('never opens a bot that is outside the selected workspace', function () {
    $this->actingAs($this->superAdmin);

    Livewire::withQueryParams(['bot' => $this->otherBot->id])
        ->test(BotOperations::class)
        ->assertSet('botFilter', (string) $this->otherBot->id)
        ->assertDontSee('Recent assistant messages');

    $this->get(PlatformAiBotResource::getUrl('operations', ['bot' => $this->otherBot->id]))
        ->assertOk()
        ->assertDontSee('Recent assistant messages');
});
