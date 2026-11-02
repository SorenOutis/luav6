<?php

use App\Ai\Agents\BotAgent;
use App\Ai\Tools\DelegateToBot;
use App\Jobs\RunAiBotHandoff;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiInputGuardrailService;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use App\Services\EchoReplyGuard;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;

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

    $this->coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);

    $this->specialist = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'slug' => 'registrar',
        'created_by' => $this->admin->id,
    ]);

    $this->session = ChatSession::query()->create([
        'user_id' => $this->admin->id,
        'mode' => ChatSession::MODE_GROUP,
        'source' => 'echo',
        'participant_bot_ids' => [$this->specialist->id],
    ]);
});

function runAiBotHandoffJob(int $handoffId): void
{
    (new RunAiBotHandoff($handoffId))->handle(
        app(AiProviderConfigurator::class),
        app(AiInputGuardrailService::class),
        app(AiOutputSanitizerService::class),
        app(EchoReplyGuard::class),
    );
}

function queuedHandoff(AiBot $coordinator, AiBot $specialist, User $admin, ChatSession $session, array $overrides = []): AiBotHandoff
{
    return AiBotHandoff::factory()->create([
        'workspace_id' => $coordinator->workspace_id,
        'from_bot_id' => $coordinator->id,
        'requested_by_user_id' => $admin->id,
        'to_bot_id' => $specialist->id,
        'chat_session_id' => $session->id,
        'task' => 'Draft the term calendar.',
        'status' => AiBotHandoff::STATUS_QUEUED,
        'depth' => 1,
        ...$overrides,
    ]);
}

it('queues a handoff for an active specialist', function () {
    Queue::fake();

    $tool = new DelegateToBot(
        $this->coordinator,
        $this->admin,
        $this->session,
        allowedTargets: ['registrar'],
    );

    $result = (string) $tool->handle(new Request([
        'bot' => 'registrar',
        'task' => 'Draft the term calendar.',
    ]));

    expect($result)->toContain('Handoff queued to');

    $handoff = AiBotHandoff::query()->withoutGlobalScope('workspace')->sole();

    expect($handoff->workspace_id)->toBe($this->workspace->id)
        ->and($handoff->from_bot_id)->toBe($this->coordinator->id)
        ->and($handoff->to_bot_id)->toBe($this->specialist->id)
        ->and($handoff->requested_by_user_id)->toBe($this->admin->id)
        ->and($handoff->chat_session_id)->toBe($this->session->id)
        ->and($handoff->task)->toBe('Draft the term calendar.')
        ->and($handoff->status)->toBe(AiBotHandoff::STATUS_QUEUED)
        ->and($handoff->depth)->toBe(1);

    Queue::assertPushed(
        RunAiBotHandoff::class,
        fn (RunAiBotHandoff $job): bool => $job->handoffId === $handoff->id,
    );
});

it('rejects delegation from a specialist or a non-admin actor', function () {
    Queue::fake();

    $specialistTool = new DelegateToBot($this->specialist, $this->admin, $this->session);
    expect((string) $specialistTool->handle(new Request(['bot' => 'registrar', 'task' => 'Do work'])))
        ->toContain('only the Chief of Staff');

    $student = User::factory()->create();
    $studentTool = new DelegateToBot($this->coordinator, $student, $this->session);
    expect((string) $studentTool->handle(new Request(['bot' => 'registrar', 'task' => 'Do work'])))
        ->toContain('requires an administrator');

    Queue::assertNothingPushed();
    expect(AiBotHandoff::query()->withoutGlobalScope('workspace')->count())->toBe(0);
});

it('rejects unknown, inactive, foreign-workspace, and off-roster targets', function () {
    Queue::fake();

    $tool = new DelegateToBot(
        $this->coordinator,
        $this->admin,
        $this->session,
        allowedTargets: ['registrar'],
    );

    expect((string) $tool->handle(new Request(['bot' => 'unknown', 'task' => 'Do work'])))
        ->toContain('not in this coordinator');

    $inactive = AiBot::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
        'slug' => 'inactive-bot',
    ]);

    expect((string) $tool->handle(new Request(['bot' => $inactive->slug, 'task' => 'Do work'])))
        ->toContain('not in this coordinator');

    $foreign = AiBot::factory()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'slug' => 'foreign-bot',
    ]);

    expect((string) $tool->handle(new Request(['bot' => $foreign->slug, 'task' => 'Do work'])))
        ->toContain('not in this coordinator');

    $offRosterTool = new DelegateToBot(
        $this->coordinator,
        $this->admin,
        $this->session,
        allowedTargets: ['someone-else'],
    );

    expect((string) $offRosterTool->handle(new Request(['bot' => 'registrar', 'task' => 'Do work'])))
        ->toContain('not in this coordinator');

    Queue::assertNothingPushed();
    expect(AiBotHandoff::query()->withoutGlobalScope('workspace')->count())->toBe(0);
});

it('rejects delegation at the depth limit or when the queue is full', function () {
    Queue::fake();

    $deepTool = new DelegateToBot(
        $this->coordinator,
        $this->admin,
        $this->session,
        depth: 2,
        allowedTargets: ['registrar'],
    );

    expect((string) $deepTool->handle(new Request(['bot' => 'registrar', 'task' => 'Do work'])))
        ->toContain('depth limit');

    foreach (range(1, DelegateToBot::MAX_PENDING_HANDOFFS) as $index) {
        queuedHandoff($this->coordinator, $this->specialist, $this->admin, $this->session, [
            'task' => "Pending task {$index}",
        ]);
    }

    $tool = new DelegateToBot(
        $this->coordinator,
        $this->admin,
        $this->session,
        allowedTargets: ['registrar'],
    );

    expect((string) $tool->handle(new Request(['bot' => 'registrar', 'task' => 'Do work'])))
        ->toContain('queue is full');

    Queue::assertNothingPushed();
    expect(AiBotHandoff::query()->withoutGlobalScope('workspace')->count())->toBe(DelegateToBot::MAX_PENDING_HANDOFFS);
});

it('runs a queued handoff and stores the specialist reply in the transcript', function () {
    BotAgent::fake(['Specialist reply']);

    $handoff = queuedHandoff($this->coordinator, $this->specialist, $this->admin, $this->session);

    runAiBotHandoffJob($handoff->id);

    $handoff->refresh();

    expect($handoff->status)->toBe(AiBotHandoff::STATUS_COMPLETED)
        ->and($handoff->result)->toBe('Specialist reply')
        ->and($handoff->error)->toBeNull();

    $message = $this->session->messages()->where('role', 'assistant')->sole();

    expect($message->bot_id)->toBe($this->specialist->id)
        ->and($message->content)->toBe('Specialist reply');

    BotAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Draft the term calendar.'));
});

it('uses the specialist model override for handoffs', function () {
    BotAgent::fake(['Specialist reply']);

    $this->specialist->update(['model' => 'handoff-override-model']);

    $handoff = queuedHandoff($this->coordinator, $this->specialist, $this->admin, $this->session);

    runAiBotHandoffJob($handoff->id);

    BotAgent::assertPrompted(fn ($prompt): bool => $prompt->model === 'handoff-override-model');

    expect($handoff->fresh()->status)->toBe(AiBotHandoff::STATUS_COMPLETED);
});

it('does not rerun a handoff that already completed', function () {
    BotAgent::fake(['Specialist reply']);

    $handoff = queuedHandoff($this->coordinator, $this->specialist, $this->admin, $this->session, [
        'status' => AiBotHandoff::STATUS_COMPLETED,
        'result' => 'Already done',
    ]);

    runAiBotHandoffJob($handoff->id);

    BotAgent::assertNeverPrompted();
    expect($handoff->fresh()->result)->toBe('Already done');
});

it('fails a handoff whose target is missing, inactive, or the coordinator', function () {
    BotAgent::fake(['Specialist reply']);

    $inactive = AiBot::factory()->inactive()->create([
        'workspace_id' => $this->workspace->id,
        'slug' => 'inactive-specialist',
    ]);

    $inactiveHandoff = queuedHandoff($this->coordinator, $inactive, $this->admin, $this->session);
    runAiBotHandoffJob($inactiveHandoff->id);

    expect($inactiveHandoff->fresh()->status)->toBe(AiBotHandoff::STATUS_FAILED)
        ->and($inactiveHandoff->fresh()->error)->toContain('missing, inactive, or not a specialist');

    $coordinatorHandoff = queuedHandoff($this->coordinator, $this->coordinator, $this->admin, $this->session);
    runAiBotHandoffJob($coordinatorHandoff->id);

    expect($coordinatorHandoff->fresh()->status)->toBe(AiBotHandoff::STATUS_FAILED)
        ->and($coordinatorHandoff->fresh()->error)->toContain('missing, inactive, or not a specialist');

    BotAgent::assertNeverPrompted();
});

it('fails a handoff whose requester is no longer an administrator', function () {
    BotAgent::fake(['Specialist reply']);

    $student = User::factory()->create();

    $handoff = queuedHandoff($this->coordinator, $this->specialist, $student, $this->session);

    runAiBotHandoffJob($handoff->id);

    expect($handoff->fresh()->status)->toBe(AiBotHandoff::STATUS_FAILED)
        ->and($handoff->fresh()->error)->toContain('requesting administrator');

    BotAgent::assertNeverPrompted();
});

it('marks the handoff failed when the model call throws', function () {
    BotAgent::fake(fn (): never => throw new RuntimeException('provider exploded'));

    $handoff = queuedHandoff($this->coordinator, $this->specialist, $this->admin, $this->session);

    runAiBotHandoffJob($handoff->id);

    expect($handoff->fresh()->status)->toBe(AiBotHandoff::STATUS_FAILED)
        ->and($handoff->fresh()->error)->toContain('provider exploded');
});
