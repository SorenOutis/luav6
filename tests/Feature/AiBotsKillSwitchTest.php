<?php

use App\Ai\Tools\DelegateToBot;
use App\Filament\Pages\AdminAiChat;
use App\Filament\Pages\AiSettings;
use App\Filament\Resources\PlatformAiBots\PlatformAiBotResource;
use App\Jobs\RunAiBotHandoff;
use App\Jobs\RunAiBotRoutine;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\AiBotRoutine;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiInputGuardrailService;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use App\Services\EchoReplyGuard;
use App\Support\AiBotsGate;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'db-key');

    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->admin->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->superAdmin->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->bot = AiBot::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->handoff = AiBotHandoff::factory()->create([
        'workspace_id' => $this->workspace->id,
        'from_bot_id' => null,
        'to_bot_id' => $this->bot->id,
        'requested_by_user_id' => $this->admin->id,
        'status' => AiBotHandoff::STATUS_QUEUED,
        'depth' => 1,
    ]);

    AiBotsGate::setEnabled(true);
});

afterEach(function () {
    AiBotsGate::setEnabled(true);
});

it('defaults the kill switch to enabled', function () {
    expect(AiBotsGate::enabled())->toBeTrue();
});

it('blocks solo and team bot conversations when disabled', function () {
    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), ['bot_id' => $this->bot->id])
        ->assertStatus(503)
        ->assertJsonPath('response', AiBotsGate::disabledMessage());

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [
            'mode' => 'group',
            'participant_bot_ids' => [$this->bot->id],
        ])
        ->assertStatus(503)
        ->assertJsonPath('response', AiBotsGate::disabledMessage());

    expect(ChatSession::count())->toBe(0);
});

it('blocks messages to an existing bot conversation when disabled', function () {
    $session = ChatSession::query()->create([
        'user_id' => $this->admin->id,
        'bot_id' => $this->bot->id,
        'mode' => 'solo',
        'title' => 'Existing bot chat',
        'source' => 'echo',
    ]);

    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    $this->actingAs($this->admin)
        ->postJson(route('chats.message', $session), ['message' => 'Enrolment question'])
        ->assertStatus(503)
        ->assertJsonPath('response', AiBotsGate::disabledMessage());
});

it('still allows ordinary Echo chat when disabled', function () {
    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    $this->actingAs($this->admin)
        ->postJson(route('chats.store'), [])
        ->assertOk()
        ->assertJsonPath('session.mode', 'solo');
});

it('does not queue routines when disabled', function () {
    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'is_active' => true,
        'cron' => '* * * * *',
        'last_run_at' => null,
    ]);

    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    Artisan::call('ai:bot-routines');

    expect($routine->refresh()->last_status)->not->toBe(AiBotRoutine::STATUS_COMPLETED)
        ->and($routine->refresh()->last_status)->toBeNull();
});

it('marks an already dispatched routine as skipped when disabled', function () {
    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'is_active' => true,
        'cron' => '* * * * *',
        'last_run_at' => null,
    ]);

    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    (new RunAiBotRoutine($routine->id))->handle(
        app(AiProviderConfigurator::class),
        app(AiOutputSanitizerService::class),
        app(EchoReplyGuard::class),
    );

    expect($routine->refresh()->last_status)->toBe(AiBotRoutine::STATUS_SKIPPED)
        ->and($routine->last_error)->toContain('disabled');
});

it('fails queued handoffs instead of executing them when disabled', function () {
    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    (new RunAiBotHandoff($this->handoff->id))->handle(
        app(AiProviderConfigurator::class),
        app(AiInputGuardrailService::class),
        app(AiOutputSanitizerService::class),
        app(EchoReplyGuard::class),
    );

    expect($this->handoff->refresh()->status)->toBe(AiBotHandoff::STATUS_FAILED)
        ->and($this->handoff->error)->toContain('disabled');
});

it('stops the coordinator from delegating when disabled', function () {
    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    $tool = new DelegateToBot($coordinator, $this->admin);

    expect($tool->handle(new Request([
        'bot' => $this->bot->slug,
        'task' => 'Something the bot cannot do',
    ])))->toContain('disabled');
});

it('round-trips the toggle through platform settings', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AiSettings::class)
        ->assertSet('data.ai_bots_enabled', true)
        ->set('data.ai_bots_enabled', false)
        ->set('data.ai_bots_maintenance_message', 'Paused for model migration.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('ai_bots_enabled'))->toBe('0')
        ->and(Setting::get('ai_bots_maintenance_message'))->toBe('Paused for model migration.');

    AiBotsGate::setEnabled(true);
});

it('exposes the disabled state in admin Echo and the operations page', function () {
    AiBotsGate::setEnabled(false);
    Setting::flushAllCaches();

    $this->actingAs($this->superAdmin);

    $this->get(AiSettings::getUrl())
        ->assertOk()
        ->assertSee('AI Bots Kill Switch');

    $this->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('AI bots are disabled');

    $this->get(PlatformAiBotResource::getUrl('operations'))
        ->assertOk()
        ->assertSee('AI bots are disabled');
});
