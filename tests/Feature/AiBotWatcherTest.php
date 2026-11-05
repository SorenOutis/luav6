<?php

/**
 * Bot watchers: scheduled read-only domain patrols.
 *
 * Every 30 minutes each seeded specialist runs its domain check inside its
 * workspace. Findings are deduped by fingerprint, posted to the super-admin
 * bot team room, and emailed instantly when actionable. Private details
 * (ticket subjects/bodies, student identities, message contents) must never
 * surface in any watcher output.
 */

use App\Ai\Support\AiBotRoster;
use App\Ai\Watchers\AiBotWatcherService;
use App\Ai\Watchers\WatcherThinker;
use App\Console\Commands\DispatchAiBotWatchers;
use App\Filament\Pages\AiSettings;
use App\Filament\Resources\PlatformAiBots\PlatformAiBotResource;
use App\Jobs\RunAiBotWatchers;
use App\Mail\AiBotFindingAlertMail;
use App\Models\AiBot;
use App\Models\AiBotFinding;
use App\Models\AiBotTeamMessage;
use App\Models\Announcement;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use App\Support\AiBotsGate;
use App\Support\AiBotWatchers;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();

    config(['ai.providers.openai' => [
        'driver' => 'openai',
        'key' => null,
        'env_key' => null,
        'url' => 'https://api.openai.com/v1',
        'env_url' => 'https://api.openai.com/v1',
    ]]);

    Setting::setGlobal('ai_provider', 'openai');
    Setting::setGlobal('openai_api_key', 'db-key');

    $this->superAdmin = User::factory()->superAdmin()->create();

    $this->workspace = Workspace::factory()->create();
    $this->superAdmin->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    AiBotsGate::setEnabled(true);
    AiBotWatchers::setEnabled(true);

    AiBotRoster::seedForWorkspace($this->workspace, $this->superAdmin);

    // A logged-in student plus a fresh announcement keep the registrar and
    // communications fields quiet on a clean workspace, where a pass posts
    // exactly eight all-clear heartbeats (one per seeded specialist).
    $this->student = User::factory()->create(['last_login_at' => now()]);
    $this->student->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    Announcement::forceCreate([
        'workspace_id' => $this->workspace->id,
        'admin_id' => $this->superAdmin->id,
        'title' => 'Welcome to the course platform',
        'description' => 'Keeps the communications watcher quiet on a clean workspace.',
        'is_active' => true,
    ]);
});

afterEach(function () {
    AiBotsGate::setEnabled(true);
    AiBotWatchers::setEnabled(true);
    Setting::flushAllCaches();
});

function botFor($workspace, string $slug): AiBot
{
    return AiBot::query()->withoutGlobalScope('workspace')
        ->where('workspace_id', $workspace->id)
        ->where('slug', $slug)
        ->firstOrFail();
}

function runWatcherPass($workspace): void
{
    (new RunAiBotWatchers($workspace->id))->handle(app(AiBotWatcherService::class));
}

function staleTicket($workspace, $student, array $overrides = []): SupportTicket
{
    return SupportTicket::forceCreate(array_merge([
        'workspace_id' => $workspace->id,
        'user_id' => $student->id,
        'admin_id' => null,
        'subject' => 'Secret subject that must never leak',
        'category' => 'technical',
        'priority' => 'high',
        'message' => 'MESSAGE-CONTENT-MARKER the student wrote',
        'status' => SupportTicket::STATUS_OPEN,
        'created_at' => now()->subHours(30),
    ], $overrides));
}

it('dispatches one watcher pass per non-archived workspace', function () {
    Queue::fake();

    Workspace::factory()->create(['archived_at' => now()]);

    $count = Workspace::query()->whereNull('archived_at')->count();

    $this->artisan(DispatchAiBotWatchers::class)
        ->expectsOutput("Queued {$count} bot watcher pass(es).")
        ->assertExitCode(0);

    Queue::assertPushed(RunAiBotWatchers::class, fn (RunAiBotWatchers $job): bool => $job->workspaceId === $this->workspace->id);

    Queue::assertPushed(RunAiBotWatchers::class, $count);
});

it('does not dispatch watcher passes when a gate is off', function () {
    Queue::fake();

    AiBotsGate::setEnabled(false);
    $this->artisan(DispatchAiBotWatchers::class)->assertExitCode(0);
    Queue::assertNotPushed(RunAiBotWatchers::class);

    AiBotsGate::setEnabled(true);
    AiBotWatchers::setEnabled(false);
    Setting::flushAllCaches();
    $this->artisan(DispatchAiBotWatchers::class)->assertExitCode(0);
    Queue::assertNotPushed(RunAiBotWatchers::class);
});

it('finds stale support tickets and alerts without leaking private content', function () {
    Mail::fake();

    WatcherThinker::fake(['Two tickets waited a day; I will draft replies and wait for approval.']);

    $bot = botFor($this->workspace, 'communications-lead');
    $expectedRecipient = AiBotWatchers::alertEmail();

    staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')->where('bot_id', $bot->id)->firstOrFail();

    expect($finding->domain)->toBe('communications')
        ->and($finding->fingerprint)->toBe('tickets_without_response')
        ->and($finding->severity)->toBe('warning')
        ->and($finding->status)->toBe(AiBotFinding::STATUS_OPEN)
        ->and($finding->suggested_action)->not->toBeNull();

    $dump = json_encode([
        $finding->title, $finding->summary, $finding->evidence, $finding->suggested_action,
        AiBotTeamMessage::query()->withoutGlobalScope('workspace')->get()->pluck('body'),
    ], JSON_THROW_ON_ERROR);

    expect($dump)->not->toContain('MESSAGE-CONTENT-MARKER')
        ->and($dump)->not->toContain('Secret subject');

    $message = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('bot_id', $bot->id)
        ->where('kind', AiBotTeamMessage::KIND_SUGGESTION)
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->finding_id)->toBe($finding->id);

    Mail::assertSent(AiBotFindingAlertMail::class, function (AiBotFindingAlertMail $mail) use ($expectedRecipient): bool {
        $rendered = $mail->render();
        $emails = collect($mail->to)->pluck('address')->all();

        $findings = json_encode($mail->findings, JSON_THROW_ON_ERROR);

        return $emails === [$expectedRecipient]
            && ! str_contains($rendered, 'MESSAGE-CONTENT-MARKER')
            && ! str_contains($findings, 'MESSAGE-CONTENT-MARKER')
            && str_contains($rendered, 'Support tickets waiting for a response');
    });

    expect($finding->refresh()->notified_at)->not->toBeNull();
});

it('starts a finding with ai reasoning when the provider answers', function () {
    Mail::fake();

    WatcherThinker::fake(['I counted two unanswered support tickets; I will draft a reply and wait for your approval.']);

    staleTicket($this->workspace, $this->student);
    staleTicket($this->workspace, $this->student, ['created_at' => now()->subHours(40)]);

    runWatcherPass($this->workspace);

    $message = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_SUGGESTION)
        ->firstOrFail();

    expect($message->body)->toContain('I counted two unanswered support tickets');
});

it('falls back to deterministic reasoning when the provider is unavailable', function () {
    Mail::fake();

    // No configured provider: the thinker throws and the pipeline keeps going.
    Setting::setGlobal('ai_provider', 'unsupported-provider');
    Setting::flushAllCaches();

    staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);

    $message = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_SUGGESTION)
        ->firstOrFail();

    expect($message->body)->toContain('I found: Support tickets waiting for a response')
        ->and($message->body)->toContain('I will not post, send, or change anything before you approve it.');
});

it('dedupes the same condition across runs instead of spamming', function () {
    Mail::fake();

    WatcherThinker::fake(['Still watching the support queue; nothing new for now.']);

    staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);
    runWatcherPass($this->workspace);

    $messages = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', '!=', AiBotTeamMessage::KIND_ALL_CLEAR)
        ->get();

    expect($messages)->toHaveCount(1);

    Mail::assertSent(AiBotFindingAlertMail::class, 1);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')->firstOrFail();

    expect($finding->times_observed)->toBe(2)
        ->and($finding->status)->toBe(AiBotFinding::STATUS_OPEN);
});

it('resolves findings when the condition clears and posts a notice', function () {
    Mail::fake();

    WatcherThinker::fake(['Found it; will wait for instructions.']);

    staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);

    $ticket = SupportTicket::query()->withoutGlobalScope('workspace')->firstOrFail();
    $ticket->update(['status' => SupportTicket::STATUS_RESOLVED]);

    runWatcherPass($this->workspace);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')->firstOrFail();

    expect($finding->status)->toBe(AiBotFinding::STATUS_RESOLVED);

    $notice = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_OBSERVATION)
        ->first();

    expect($notice)->not->toBeNull()
        ->and($notice->body)->toContain('Situation normal again');
});

it('reopens resolved findings and emails the recurrence once', function () {
    Mail::fake();

    WatcherThinker::fake(['Back again; recreating my note.']);

    $ticket = staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);

    $ticket->update(['status' => SupportTicket::STATUS_RESOLVED]);
    runWatcherPass($this->workspace);

    $ticket->update(['status' => SupportTicket::STATUS_OPEN]);
    runWatcherPass($this->workspace);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')->firstOrFail();

    expect($finding->status)->toBe(AiBotFinding::STATUS_OPEN)
        ->and($finding->times_observed)->toBe(2);

    Mail::assertSent(AiBotFindingAlertMail::class, 2);
});

it('escalates severity and re-alerts', function () {
    Mail::fake();

    WatcherThinker::fake(['Escalating; this cannot wait for the next check.']);

    staleTicket($this->workspace, $this->student);

    runWatcherPass($this->workspace);

    expect(AiBotFinding::query()->withoutGlobalScope('workspace')->firstOrFail()->severity)->toBe('warning');

    foreach (range(1, 9) as $n) {
        staleTicket($this->workspace, $this->student, [
            'created_at' => now()->subHours(30)->addMinutes($n),
        ]);
    }

    runWatcherPass($this->workspace);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')->firstOrFail();

    expect($finding->severity)->toBe('critical')
        ->and($finding->times_observed)->toBe(2);

    Mail::assertSent(AiBotFindingAlertMail::class, 2);
});

it('never emails informational findings', function () {
    Mail::fake();

    // Remove the fixture announcement so the communications watcher reports
    // an info-level observation that still carries a suggested action.
    Announcement::query()->withoutGlobalScope('workspace')->forceDelete();

    runWatcherPass($this->workspace);

    $finding = AiBotFinding::query()->withoutGlobalScope('workspace')
        ->where('bot_id', botFor($this->workspace, 'communications-lead')->id)
        ->firstOrFail();

    expect($finding->severity)->toBe('info')
        ->and($finding->notified_at)->toBeNull();

    Mail::assertNotSent(AiBotFindingAlertMail::class);
});

it('posts one daily all-clear heartbeat per specialist and skips repeats', function () {
    runWatcherPass($this->workspace);

    $allClear = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_ALL_CLEAR)
        ->get();

    expect($allClear)->toHaveCount(8);

    runWatcherPass($this->workspace);

    expect(AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_ALL_CLEAR)
        ->count())->toBe(8);
});

it('flags a grading backlog for the assessment officer', function () {
    Mail::fake();

    WatcherThinker::fake(['Nine hours without a grade is too long; I will triage the oldest.']);

    $exam = Exam::factory()->create(['workspace_id' => $this->workspace->id]);
    $part = ExamPart::factory()->forExam($exam)->create();
    $otherWorkspace = Workspace::factory()->create();

    // updated_at is guarded on ExamSubmission, which create() would silently
    // discard and restamp to now; forceCreate keeps the backlog age intact.
    ExamSubmission::forceCreate([
        'user_id' => $this->student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => [],
        'status' => 'pending_review',
        'updated_at' => now()->subHours(9),
    ]);

    $otherExam = Exam::factory()->create(['workspace_id' => $otherWorkspace->id]);

    ExamSubmission::forceCreate([
        'user_id' => $this->student->id,
        'exam_id' => $otherExam->id,
        'exam_part_id' => ExamPart::factory()->forExam($otherExam)->create()->id,
        'answers' => [],
        'status' => 'pending_review',
        'updated_at' => now()->subHours(9),
    ]);

    runWatcherPass($this->workspace);

    $findings = AiBotFinding::query()
        ->withoutGlobalScope('workspace')
        ->where('workspace_id', $this->workspace->id)
        ->where('domain', 'assessment')
        ->get();

    expect($findings)->toHaveCount(1)
        ->and($findings->first()->summary)->toContain('1 submission(s)')
        ->and(AiBotFinding::query()->withoutGlobalScope('workspace')->where('workspace_id', $otherWorkspace->id)->count())->toBe(0);
});

it('skips custom bots that have no watcher check', function () {
    AiBot::query()->withoutGlobalScope('workspace')->create([
        'workspace_id' => $this->workspace->id,
        'kind' => AiBot::KIND_SPECIALIST,
        'slug' => 'custom-helper',
        'name' => 'Custom Helper',
        'policy' => [],
        'allowed_tools' => [],
        'is_active' => true,
        'created_by' => $this->superAdmin->id,
    ]);

    runWatcherPass($this->workspace);

    expect(AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_ALL_CLEAR)
        ->count())->toBe(8);
});

it('keeps the team room on the operations dashboard and reflects watcher state', function () {
    WatcherThinker::fake(['I will check the support queue every 30 minutes.']);

    staleTicket($this->workspace, $this->student);
    Mail::fake();
    runWatcherPass($this->workspace);

    $body = AiBotTeamMessage::query()->withoutGlobalScope('workspace')
        ->where('kind', AiBotTeamMessage::KIND_SUGGESTION)->value('body');

    $this->actingAs($this->superAdmin)
        ->get(PlatformAiBotResource::getUrl('operations'))
        ->assertOk()
        ->assertSee('Bot team room')
        ->assertSee('Watchers on')
        ->assertSee(e(Str::limit($body, 60)), false);

    AiBotWatchers::setEnabled(false);
    Setting::flushAllCaches();

    $this->actingAs($this->superAdmin)
        ->get(PlatformAiBotResource::getUrl('operations'))
        ->assertOk()
        ->assertSee('Watchers off');
});

it('round-trips watcher settings through platform settings', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(AiSettings::class)
        ->assertSet('data.ai_bot_watchers_enabled', true)
        ->set('data.ai_bot_watchers_alert_email', 'watchers@example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('ai_bot_watchers_alert_email'))->toBe('watchers@example.test');

    // Toggling watchers off must not wipe the stored recipient; the address
    // input is hidden in that state and the handler skips it.
    Livewire::test(AiSettings::class)
        ->assertSet('data.ai_bot_watchers_alert_email', 'watchers@example.test')
        ->set('data.ai_bot_watchers_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('ai_bot_watchers_enabled'))->toBe('0')
        ->and(Setting::get('ai_bot_watchers_alert_email'))->toBe('watchers@example.test')
        ->and(AiBotWatchers::alertEmail())->toBe('watchers@example.test');
});

it('falls back to the first super admin email when no override is set', function () {
    Setting::flushAllCaches();

    expect(AiBotWatchers::alertEmail())->toBe(User::query()
        ->where('is_admin', true)
        ->where('is_super_admin', true)
        ->orderBy('id')
        ->value('email'));
});

it('schedules the watcher command half-hourly without overlap', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'ai:bot-watchers'));

    expect($events)->toHaveCount(1);

    $event = $events->first();

    expect($event->expression)->toBe('*/30 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
