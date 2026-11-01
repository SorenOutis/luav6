<?php

use App\Ai\Agents\BotAgent;
use App\Jobs\RunAiBotRoutine;
use App\Models\AiBot;
use App\Models\AiBotRoutine;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use App\Services\EchoReplyGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

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

    $this->admin = User::factory()->admin()->create();
    $this->workspace = $this->admin->workspaces()->firstOrFail();

    $this->bot = AiBot::factory()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);
});

function runAiBotRoutineJob(int $routineId): void
{
    (new RunAiBotRoutine($routineId))->handle(
        app(AiProviderConfigurator::class),
        app(AiOutputSanitizerService::class),
        app(EchoReplyGuard::class),
    );
}

it('queues due routines and claims them before dispatch', function () {
    Queue::fake();

    $past = now()->subMinutes(2);

    $due = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'cron' => '* * * * *',
        'last_run_at' => $past,
    ]);

    $inactive = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'cron' => '* * * * *',
        'last_run_at' => $past,
        'is_active' => false,
    ]);

    $this->artisan('ai:bot-routines')->assertSuccessful();

    Queue::assertPushed(RunAiBotRoutine::class, 1);
    Queue::assertPushed(fn (RunAiBotRoutine $job): bool => $job->routineId === $due->id);

    expect($due->fresh()->last_run_at)->not->toBeNull()
        ->and($due->fresh()->last_run_at?->getTimestamp())->toBeGreaterThan($past->getTimestamp())
        ->and($inactive->fresh()->last_run_at?->getTimestamp())->toBe($past->getTimestamp());
});

it('does not queue a routine that is not due yet', function () {
    Queue::fake();

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'cron' => '0 7 * * *',
        'last_run_at' => now(),
    ]);

    $this->artisan('ai:bot-routines')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($routine->fresh()->last_run_at?->getTimestamp())->toBe($routine->last_run_at->getTimestamp());
});

it('records invalid cron expressions without queueing work', function () {
    Queue::fake();

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'cron' => 'not-a-cron',
    ]);

    $this->artisan('ai:bot-routines')->assertSuccessful();

    Queue::assertNothingPushed();
    expect($routine->fresh()->last_status)->toBe(AiBotRoutine::STATUS_FAILED)
        ->and($routine->fresh()->last_error)->toContain('Invalid cron expression');
});

it('lists due routines without claiming them during a dry run', function () {
    Queue::fake();

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'cron' => '* * * * *',
        'last_run_at' => now()->subMinutes(2),
    ]);

    $this->artisan('ai:bot-routines', ['--dry-run' => true])
        ->expectsOutputToContain("Due: #{$routine->id}")
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect($routine->fresh()->last_run_at?->getTimestamp())->toBe($routine->last_run_at->getTimestamp());
});

it('runs a due routine as the bot creator and stores the transcript', function () {
    BotAgent::fake(['Routine reply']);

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
        'name' => 'Morning briefing',
        'cron' => '* * * * *',
        'prompt' => 'Summarize yesterday for the admin.',
    ]);

    runAiBotRoutineJob($routine->id);

    $routine->refresh();

    expect($routine->last_status)->toBe(AiBotRoutine::STATUS_COMPLETED)
        ->and($routine->last_run_at)->not->toBeNull()
        ->and($routine->last_error)->toBeNull();

    $session = ChatSession::query()->where('source', RunAiBotRoutine::SOURCE_ROUTINE)->sole();

    expect($session->user_id)->toBe($this->admin->id)
        ->and($session->bot_id)->toBe($this->bot->id)
        ->and($session->mode)->toBe(ChatSession::MODE_SOLO)
        ->and($session->title)->toContain('Morning briefing');

    $messages = $session->messages()->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe('user')
        ->and($messages[0]->content)->toBe('Summarize yesterday for the admin.')
        ->and($messages[1]->role)->toBe('assistant')
        ->and($messages[1]->bot_id)->toBe($this->bot->id)
        ->and($messages[1]->content)->toBe('Routine reply');
});

it('skips a routine whose creator is missing or no longer an administrator', function () {
    BotAgent::fake(['Routine reply']);

    $this->bot->update(['created_by' => null]);

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
    ]);

    runAiBotRoutineJob($routine->id);

    expect($routine->fresh()->last_status)->toBe(AiBotRoutine::STATUS_SKIPPED)
        ->and($routine->fresh()->last_error)->toContain('creator')
        ->and(ChatSession::query()->where('source', RunAiBotRoutine::SOURCE_ROUTINE)->count())->toBe(0);
});

it('skips a routine after the daily run cap is reached', function () {
    BotAgent::fake(['Routine reply']);

    foreach (range(1, RunAiBotRoutine::MAX_DAILY_RUNS) as $index) {
        ChatSession::query()->create([
            'user_id' => $this->admin->id,
            'bot_id' => $this->bot->id,
            'mode' => ChatSession::MODE_SOLO,
            'source' => RunAiBotRoutine::SOURCE_ROUTINE,
            'title' => "Routine run {$index}",
        ]);
    }

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
    ]);

    runAiBotRoutineJob($routine->id);

    expect($routine->fresh()->last_status)->toBe(AiBotRoutine::STATUS_SKIPPED)
        ->and($routine->fresh()->last_error)->toContain('Daily routine limit')
        ->and(ChatSession::query()->where('source', RunAiBotRoutine::SOURCE_ROUTINE)->count())
        ->toBe(RunAiBotRoutine::MAX_DAILY_RUNS);
});

it('marks the routine failed when the model call throws', function () {
    BotAgent::fake(fn (): never => throw new RuntimeException('provider exploded'));

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $this->bot->id,
    ]);

    runAiBotRoutineJob($routine->id);

    expect($routine->fresh()->last_status)->toBe(AiBotRoutine::STATUS_FAILED)
        ->and($routine->fresh()->last_error)->toContain('provider exploded');
});

it('skips routines that target the chief of staff', function () {
    BotAgent::fake(['Routine reply']);

    $coordinator = AiBot::factory()->coordinator()->create([
        'workspace_id' => $this->workspace->id,
        'created_by' => $this->admin->id,
    ]);

    $routine = AiBotRoutine::factory()->create([
        'workspace_id' => $this->workspace->id,
        'bot_id' => $coordinator->id,
    ]);

    runAiBotRoutineJob($routine->id);

    expect($routine->fresh()->last_status)->toBe(AiBotRoutine::STATUS_SKIPPED)
        ->and($routine->fresh()->last_error)->toContain('specialist');
});

it('registers a nonoverlapping minute scheduler for bot routines', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'ai:bot-routines'));

    expect($events)->toHaveCount(1);

    $event = $events->first();

    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
