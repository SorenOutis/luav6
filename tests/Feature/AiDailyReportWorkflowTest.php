<?php

use App\Ai\Agents\DailyWorkspaceAnalyst;
use App\Console\Commands\DispatchAiDailyReport;
use App\Jobs\DeliverAiDailyReport;
use App\Jobs\GenerateAiDailyReport;
use App\Mail\DailyAiReport;
use App\Models\AiDailyReport;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiDailyReportAnalystService;
use App\Services\AiDailyReportService;
use App\Services\AiDailyReportSnapshotService;
use App\Services\AiUsageTracker;
use App\Support\AiDailyReportSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\QueryException;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Support\Testing\Fakes\PendingMailFake;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;

class AcceptedDailyReportPendingMailFake extends PendingMailFake
{
    public function sendNow(Mailable $mailable): ?SentMessage
    {
        return $this->mailer->sendNow($this->fill($mailable));
    }
}

/** Mail::fake() returns void; this fake also simulates transport acceptance. */
class AcceptedDailyReportMailFake extends MailFake
{
    public function to(mixed $users): PendingMailFake
    {
        return (new AcceptedDailyReportPendingMailFake($this))->to($users);
    }

    public function sendNow(mixed $mailable, array $data = [], mixed $callback = null): ?SentMessage
    {
        parent::sendNow($mailable, $data, $callback);
        $message = (new Email)->from('reports@example.test')->to('admin@example.test')->text('Report accepted.');

        return new SentMessage(new SymfonySentMessage($message, Envelope::create($message)));
    }
}

beforeEach(function () {
    config(['app.timezone' => 'Asia/Manila', 'ai_reports.time' => '00:15', 'mail.default' => 'smtp']);
    Setting::flushAllCaches();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:15:00', 'Asia/Manila'));
    Bus::fake();
    Mail::swap(new AcceptedDailyReportMailFake(app('mail.manager')));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array{0: User, 1: Workspace} */
function configureNightlyReport(): array
{
    $recipient = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();
    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, json_encode([
        'enabled' => true,
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $recipient->id,
        'provider' => 'gemini',
        'max_cost_usd' => 0.25,
    ], JSON_THROW_ON_ERROR));

    return [$recipient, $workspace];
}

function queuedNightlyReport(): AiDailyReport
{
    configureNightlyReport();

    return app(AiDailyReportService::class)->dispatch();
}

function generateNightlyReport(AiDailyReport $report): void
{
    (new GenerateAiDailyReport($report->id))->handle(
        app(AiDailyReportService::class),
        app(AiDailyReportSnapshotService::class),
        app(AiDailyReportAnalystService::class),
    );
}

it('defaults to disabled without creating reports or mail', function () {
    expect(app(AiDailyReportService::class)->dispatch())->toBeNull();
    $this->assertDatabaseCount('ai_daily_reports', 0);
    Bus::assertNothingDispatched();
    Mail::assertNothingOutgoing();
});

it('waits until the env-backed local run time', function () {
    configureNightlyReport();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:14:59', 'Asia/Manila'));

    expect(app(AiDailyReportService::class)->dispatch())->toBeNull();
    Bus::assertNothingDispatched();
});

it('waits until the admin-configured run time when set in settings', function () {
    [$recipient, $workspace] = configureNightlyReport();
    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, json_encode([
        'enabled' => true,
        'time' => '02:30',
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $recipient->id,
        'provider' => 'gemini',
        'max_cost_usd' => 0.25,
    ], JSON_THROW_ON_ERROR));

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 02:29:59', 'Asia/Manila'));
    expect(app(AiDailyReportService::class)->dispatch())->toBeNull();
    Bus::assertNothingDispatched();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 02:30:00', 'Asia/Manila'));
    $report = app(AiDailyReportService::class)->dispatch();
    expect($report)->not->toBeNull()
        ->and($report->report_date->toDateString())->toBe('2026-10-10');
    Bus::assertDispatched(GenerateAiDailyReport::class);
});

it('queues the previous complete Manila day even while UTC is the previous date', function () {
    [$recipient, $workspace] = configureNightlyReport();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 16:15:00', 'UTC'));

    $report = app(AiDailyReportService::class)->dispatch();

    expect($report->report_date->toDateString())->toBe('2026-10-10')
        ->and($report->timezone)->toBe('Asia/Manila')
        ->and($report->recipient_id)->toBe($recipient->id)
        ->and($report->workspace_ids)->toBe([$workspace->id])
        ->and($report->status)->toBe(AiDailyReport::STATUS_PENDING);
    Bus::assertDispatched(GenerateAiDailyReport::class, fn ($job) => $job->reportId === $report->id && $job->queue === 'ai' && $job->afterCommit);
});

it('catches up after a missed schedule tick without duplicating a queued run', function () {
    configureNightlyReport();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 10:00:00', 'Asia/Manila'));

    $first = app(AiDailyReportService::class)->dispatch();
    $second = app(AiDailyReportService::class)->dispatch();

    expect($second->id)->toBe($first->id);
    $this->assertDatabaseCount('ai_daily_reports', 1);
    Bus::assertDispatchedTimes(GenerateAiDailyReport::class, 1);
});

it('has a database unique constraint for a report date', function () {
    $report = queuedNightlyReport();

    expect(fn () => AiDailyReport::factory()->create(['report_date' => $report->report_date]))->toThrow(QueryException::class);
});

it('rejects incomplete or invalid requested report days', function (string $date) {
    configureNightlyReport();

    expect(fn () => app(AiDailyReportService::class)->dispatch($date))->toThrow(ValidationException::class);
    Bus::assertNothingDispatched();
})->with(['2026-10-11', '2026-10-12', '2026-02-30', 'not-a-date']);

it('supports an explicit past day before the normal schedule time', function () {
    configureNightlyReport();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 00:01:00', 'Asia/Manila'));

    expect(app(AiDailyReportService::class)->dispatch('2026-10-08')->report_date->toDateString())->toBe('2026-10-08');
});

it('rejects invalid delivery time configuration', function () {
    config(['ai_reports.time' => '25:70']);

    expect(fn () => app(AiDailyReportService::class)->dispatch())->toThrow(RuntimeException::class, 'AI_DAILY_REPORT_TIME');
});

it('does not queue reports for a revoked or invalid recipient', function (array $attributes) {
    [$recipient] = configureNightlyReport();
    $recipient->forceFill($attributes)->save();

    expect(app(AiDailyReportService::class)->dispatch())->toBeNull();
    Bus::assertNothingDispatched();
})->with([
    'ordinary admin' => [['is_super_admin' => false]],
    'invalid email' => [['email' => 'not-an-email']],
]);

it('queues and delivers reports to an unverified super admin', function () {
    [$recipient] = configureNightlyReport();
    $recipient->forceFill(['email_verified_at' => null])->save();

    $report = app(AiDailyReportService::class)->dispatch();
    expect($report)->not->toBeNull();

    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

    Mail::assertSent(DailyAiReport::class, fn ($mail) => $mail->hasTo($recipient->email));
});

it('recovers a lost pending dispatch after its queue claim expires', function () {
    $report = queuedNightlyReport();
    $this->travel(11)->minutes();

    app(AiDailyReportService::class)->dispatch();

    Bus::assertDispatchedTimes(GenerateAiDailyReport::class, 2);
});

it('persists bounded analysis and queues delivery without relying on authenticated scope', function () {
    $report = queuedNightlyReport();
    config(['ai.providers.gemini.env_key' => 'test-key']);
    DailyWorkspaceAnalyst::fake([[
        'summary' => 'No recorded activity today.',
        'findings' => [],
        'recommendations' => [],
    ]])->preventStrayPrompts();

    generateNightlyReport($report);
    $report->refresh();

    expect(auth()->check())->toBeFalse()
        ->and($report->status)->toBe(AiDailyReport::STATUS_COMPLETED)
        ->and($report->snapshot['date'])->toBe('2026-10-10')
        ->and($report->snapshot['metrics'])->toHaveCount(9)
        ->and($report->analysis['summary'])->toBe('No recorded activity today.')
        ->and($report->completed_at)->not->toBeNull()
        ->and($report->model)->not->toBeNull();
    Bus::assertDispatched(DeliverAiDailyReport::class, fn ($job) => $job->reportId === $report->id && $job->queue === 'ai');
    Mail::assertNothingOutgoing();
});

it('persists billed usage before rejecting provider analysis and never repeats its call', function () {
    $report = queuedNightlyReport();
    config(['ai.providers.gemini.env_key' => 'test-key']);
    DailyWorkspaceAnalyst::fake([new StructuredTextResponse(
        ['summary' => '<script>unsafe</script>', 'findings' => [], 'recommendations' => []],
        'invalid analysis',
        new Usage(promptTokens: 120, completionTokens: 80),
        new Meta('gemini', 'test-model'),
    )])->preventStrayPrompts();

    generateNightlyReport($report);
    generateNightlyReport($report);

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_INCOMPLETE)
        ->and($report->analysis)->toBeNull()
        ->and($report->input_tokens)->toBe(120)
        ->and($report->output_tokens)->toBe(80)
        ->and($report->estimated_cost_micros)->not->toBeNull()
        ->and($report->model)->not->toBeNull();
    DailyWorkspaceAnalyst::assertPrompted(fn () => true);
    Bus::assertDispatchedTimes(DeliverAiDailyReport::class, 1);
});

it('keeps received usage when strict accounting fails without repeating the paid call', function () {
    $report = queuedNightlyReport();
    config(['ai.providers.gemini.env_key' => 'test-key']);
    DailyWorkspaceAnalyst::fake([new StructuredTextResponse(
        ['summary' => 'Saved response.', 'findings' => [], 'recommendations' => []],
        'Saved response.',
        new Usage(promptTokens: 120, completionTokens: 80),
        new Meta('gemini', 'test-model'),
    )])->preventStrayPrompts();
    $tracker = Mockery::mock(AiUsageTracker::class);
    $tracker->shouldReceive('recordOrFail')->once()->andThrow(new RuntimeException('accounting unavailable'));
    app()->instance(AiUsageTracker::class, $tracker);

    generateNightlyReport($report);
    generateNightlyReport($report);

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_INCOMPLETE)
        ->and($report->error_code)->toBe('analysis_unavailable')
        ->and($report->input_tokens)->toBe(120)
        ->and($report->output_tokens)->toBe(80)
        ->and($report->estimated_cost_micros)->not->toBeNull();
    Bus::assertDispatchedTimes(DeliverAiDailyReport::class, 1);
});

it('never regenerates an already completed report or repeats its delivery dispatch', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED, 'analysis' => ['summary' => 'Saved', 'findings' => [], 'recommendations' => []]]);
    $collector = Mockery::mock(AiDailyReportSnapshotService::class);
    $collector->shouldNotReceive('collect');
    $analyst = Mockery::mock(AiDailyReportAnalystService::class);
    $analyst->shouldNotReceive('analyze');

    app(AiDailyReportService::class)->generate($report->id, $collector, $analyst);
    app(AiDailyReportService::class)->generate($report->id, $collector, $analyst);

    Bus::assertDispatchedTimes(DeliverAiDailyReport::class, 1);
});

it('does not start a second generation while a report is processing', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_PROCESSING, 'started_at' => now()]);
    $collector = Mockery::mock(AiDailyReportSnapshotService::class);
    $collector->shouldNotReceive('collect');
    $analyst = Mockery::mock(AiDailyReportAnalystService::class);
    $analyst->shouldNotReceive('analyze');

    app(AiDailyReportService::class)->generate($report->id, $collector, $analyst);
    Bus::assertNotDispatched(DeliverAiDailyReport::class);
});

it('cancels queued generation when selected workspaces change', function () {
    $report = queuedNightlyReport();
    $settings = AiDailyReportSettings::state();
    $settings['workspace_ids'] = [Workspace::factory()->create()->id];
    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, json_encode($settings));

    generateNightlyReport($report);

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_CANCELLED);
    Bus::assertNotDispatched(DeliverAiDailyReport::class);
});

it('saves a metrics-only incomplete report when the provider fails without exposing provider errors', function () {
    $report = queuedNightlyReport();
    Log::spy();
    $analyst = Mockery::mock(AiDailyReportAnalystService::class);
    $analyst->shouldReceive('analyze')->once()->andThrow(new RuntimeException('secret-api-key and private response'));

    app(AiDailyReportService::class)->generate($report->id, app(AiDailyReportSnapshotService::class), $analyst);
    $report->refresh();

    expect($report->status)->toBe(AiDailyReport::STATUS_INCOMPLETE)
        ->and($report->error_code)->toBe('analysis_unavailable')
        ->and($report->snapshot['metrics'])->toHaveCount(9)
        ->and($report->analysis)->toBeNull()
        ->and(json_encode($report->toArray()))->not->toContain('secret-api-key');
    Log::shouldHaveReceived('warning')->with('Nightly AI report generation did not complete.', [
        'report_id' => $report->id,
        'error_code' => 'analysis_unavailable',
        'exception_type' => RuntimeException::class,
    ])->once();
    Bus::assertDispatchedTimes(DeliverAiDailyReport::class, 1);
});

it('marks unavailable telemetry as failed rather than sending fabricated zero metrics', function () {
    $report = queuedNightlyReport();
    $collector = Mockery::mock(AiDailyReportSnapshotService::class);
    $collector->shouldReceive('collect')->once()->andThrow(new RuntimeException('database unavailable'));
    $analyst = Mockery::mock(AiDailyReportAnalystService::class);
    $analyst->shouldNotReceive('analyze');

    app(AiDailyReportService::class)->generate($report->id, $collector, $analyst);

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_FAILED)
        ->and($report->error_code)->toBe('metrics_unavailable')
        ->and($report->snapshot)->toBeNull();
    Bus::assertDispatched(DeliverAiDailyReport::class);
});

it('recovers interrupted generation as incomplete without another paid call', function () {
    $report = queuedNightlyReport();
    $report->update([
        'status' => AiDailyReport::STATUS_PROCESSING,
        'started_at' => now()->subMinutes(11),
        'snapshot' => ['metrics' => []],
    ]);

    app(AiDailyReportService::class)->dispatch();

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_INCOMPLETE)
        ->and($report->error_code)->toBe('generation_interrupted');
    Bus::assertDispatchedTimes(GenerateAiDailyReport::class, 1);
    Bus::assertDispatchedTimes(DeliverAiDailyReport::class, 1);
});

it('records hard generation failure and queues a failure digest', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_PROCESSING, 'started_at' => now()]);

    (new GenerateAiDailyReport($report->id))->failed(new RuntimeException('timeout'));

    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_FAILED);
    Bus::assertDispatched(DeliverAiDailyReport::class);
});

it('delivers only to the configured superadmin and records a send once', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_INCOMPLETE, 'snapshot' => ['metrics' => []], 'error_code' => 'analysis_unavailable']);
    $job = new DeliverAiDailyReport($report->id);

    $job->handle(app(AiDailyReportService::class));
    $job->handle(app(AiDailyReportService::class));

    Mail::assertSent(DailyAiReport::class, fn ($mail) => $mail->hasTo($report->recipient->email) && $mail->report->id === $report->id);
    Mail::assertSentTimes(DailyAiReport::class, 1);
    Mail::assertNothingQueued();
    expect($report->refresh()->email_sent_at)->not->toBeNull()
        ->and($report->delivery_started_at)->toBeNull();
});

it('never marks nondelivery transports as sent', function (string $mailer) {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    config(['mail.default' => $mailer]);

    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

    Mail::assertNothingOutgoing();
    expect($report->refresh()->email_sent_at)->toBeNull()
        ->and($report->delivery_started_at)->toBeNull()
        ->and($report->delivery_error_code)->toBe('mail_transport_not_configured');
})->with(['log', 'array', 'failover', 'missing']);

it('does not mark suppressed mail as accepted and releases its delivery claim', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    $pendingMail = Mockery::mock(PendingMailFake::class);
    Mail::shouldReceive('to')->once()->with($report->recipient->email)->andReturn($pendingMail);
    $pendingMail->shouldReceive('sendNow')->once()->andReturnNull();

    expect(fn () => (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class)))
        ->toThrow(RuntimeException::class, 'not accepted');
    expect($report->refresh()->email_sent_at)->toBeNull()
        ->and($report->delivery_started_at)->toBeNull();
});

it('does not deliver a pending report', function () {
    $report = queuedNightlyReport();
    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));
    Mail::assertNothingOutgoing();
});

it('rechecks recipient permission before mail delivery', function (array $attributes) {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    $report->recipient->forceFill($attributes)->save();

    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

    Mail::assertNothingOutgoing();
    expect($report->refresh()->email_sent_at)->toBeNull()
        ->and($report->delivery_error_code)->toBe('delivery_not_authorized');
})->with([
    'revoked' => [['is_super_admin' => false]],
    'invalid email' => [['email' => 'invalid-email']],
]);

it('does not send previously queued mail after reporting is disabled', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    $settings = AiDailyReportSettings::state();
    $settings['enabled'] = false;
    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, json_encode($settings));

    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

    Mail::assertNothingOutgoing();
});

it('does not send concurrent duplicate delivery jobs', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED, 'delivery_started_at' => now()]);

    (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

    Mail::assertNothingOutgoing();
});

it('releases the send claim for retry without rerunning analysis after mail failure', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    $pendingMail = Mockery::mock(PendingMailFake::class);
    Mail::shouldReceive('to')->once()->with($report->recipient->email)->andReturn($pendingMail);
    $pendingMail->shouldReceive('sendNow')->once()->andThrow(new RuntimeException('smtp failed'));

    expect(fn () => (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class)))->toThrow(RuntimeException::class, 'smtp failed');
    expect($report->refresh()->status)->toBe(AiDailyReport::STATUS_COMPLETED)
        ->and($report->delivery_started_at)->toBeNull()
        ->and($report->email_sent_at)->toBeNull()
        ->and($report->delivery_error_code)->toBe('mail_delivery_failed');
});

it('stops automatic rescheduling after exhausted mail retries', function () {
    $report = queuedNightlyReport();
    $report->update(['status' => AiDailyReport::STATUS_COMPLETED]);
    (new DeliverAiDailyReport($report->id))->failed(new RuntimeException('smtp failed'));

    app(AiDailyReportService::class)->dispatch();

    expect($report->refresh()->delivery_failed_at)->not->toBeNull();
    Bus::assertNotDispatched(DeliverAiDailyReport::class);
});

it('renders escaped AI text and workspace labels without turning Markdown into links', function () {
    $report = AiDailyReport::factory()->completed()->create([
        'report_date' => '2026-10-10',
        'analysis' => ['summary' => '<script>alert(1)</script> [link](https://example.test)', 'findings' => [], 'recommendations' => []],
        'snapshot' => ['workspace_names' => [1 => '<img src=x onerror=alert(1)>'], 'metrics' => [], 'limitations' => []],
    ]);
    $html = (new DailyAiReport($report))->render();

    expect($html)->toContain('&lt;script&gt;', '&lt;img', '[link](https://example.test)')
        ->not->toContain('<script>', '<img src=x', '<a href="https://example.test"');
    (new DailyAiReport($report))->assertHasSubject('Daily AI report: 2026-10-10');
});

it('labels missing analysis as incomplete and still renders the available factual metrics', function () {
    $report = AiDailyReport::factory()->completed()->create([
        'status' => AiDailyReport::STATUS_INCOMPLETE,
        'error_code' => 'analysis_unavailable',
        'analysis' => null,
        'snapshot' => [
            'comparison_date' => '2026-10-09',
            'workspace_names' => [1 => 'Selected workspace'],
            'metrics' => [['workspace_id' => 1, 'label' => 'Exam part submissions', 'current' => 12, 'previous' => 7, 'unit' => 'submissions']],
            'limitations' => ['Failures are not recorded.'],
        ],
    ]);
    $mail = new DailyAiReport($report);
    $mail->assertSeeInHtml('Incomplete report.')
        ->assertSeeInHtml('Selected workspace')
        ->assertSeeInHtml('Exam part submissions')
        ->assertSeeInHtml('Failures are not recorded.');
});

it('renders a failure digest without pretending missing telemetry is zero', function () {
    $report = AiDailyReport::factory()->create(['status' => AiDailyReport::STATUS_FAILED, 'error_code' => 'metrics_unavailable']);
    (new DailyAiReport($report))->assertSeeInHtml('Metrics could not be collected. No conclusions are available.');
});

it('registers a short nonoverlapping scheduler with the application timezone', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events())->filter(fn ($event) => str_contains($event->command ?? '', 'ai:daily-report'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('reports invalid dates through the console command without dispatching work', function () {
    $this->artisan('ai:daily-report', ['--date' => 'invalid'])->assertFailed();
    Bus::assertNothingDispatched();
});

it('exposes the explicit date option on the command', function () {
    expect((new DispatchAiDailyReport)->getDefinition()->hasOption('date'))->toBeTrue();
});

it('keeps both job timeouts below the default queue visibility window', function () {
    expect((new GenerateAiDailyReport(1))->timeout)->toBeLessThan(90)
        ->and((new DeliverAiDailyReport(1))->timeout)->toBeLessThan(90)
        ->and((new GenerateAiDailyReport(1))->tries)->toBe(1)
        ->and((new DeliverAiDailyReport(1))->backoff())->toBe([60, 300]);
});
