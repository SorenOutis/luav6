<?php

use App\Ai\Agents\DailyWorkspaceAnalyst;
use App\Filament\Pages\AiDailyReports;
use App\Models\AiDailyReport;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiSdkProviderService;
use App\Support\AiDailyReportSettings;
use App\Support\WorkspaceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Settings form tests keep report history assertions in a separate case. */
class AiDailyReportsSettingsPage extends AiDailyReports
{
    public function getRecentReports(): Collection
    {
        abort_unless(static::canAccess(), 403);

        return collect();
    }
}

beforeEach(function () {
    Setting::flushAllCaches();

    foreach (array_merge(['gemini', 'groq'], array_keys(AiSdkProviderService::DEFAULT_MODELS)) as $provider) {
        config(["ai.providers.{$provider}.env_key" => null]);
        config(["ai.providers.{$provider}.key" => null]);
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dailyReportSettingsData(array $overrides = []): array
{
    return array_replace([
        'enabled' => false,
        'time' => '00:15',
        'workspace_ids' => [],
        'recipient_id' => null,
        'provider' => 'gemini',
        'max_cost_usd' => 0.25,
    ], $overrides);
}

it('defaults to disabled without a recipient or selected workspaces', function () {
    expect(AiDailyReportSettings::state())->toBe(dailyReportSettingsData())
        ->and(AiDailyReportSettings::providerOptions())->toBe(['ollama' => 'Ollama (Local)']);

    expect(config('ai_reports.queue'))->toBe('ai')
        ->and(config('ai_reports.max_output_tokens'))->toBe(2000)
        ->and(config('ai_reports.max_prompt_characters'))->toBe(50000)
        ->and(config('ai_reports.timeout'))->toBe(45)
        ->and(config('ai_reports'))->not->toHaveKey('timezone');
});

it('uses only a supported global default provider', function (string $provider, string $expected) {
    Setting::setGlobal('ai_provider', $provider);

    expect(AiDailyReportSettings::state()['provider'])->toBe($expected)
        ->and(AiDailyReportSettings::state()['enabled'])->toBeFalse();
})->with([
    'gemini' => ['gemini', 'gemini'],
    'SDK provider without credentials' => ['openai', 'openai'],
    'local provider' => ['ollama', 'ollama'],
    'groq excluded' => ['groq', 'gemini'],
    'cloudflare excluded' => ['cloudflare', 'gemini'],
    'unknown provider' => ['unknown', 'gemini'],
]);

it('fails closed for malformed saved settings', function (string $stored) {
    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, $stored);

    expect(AiDailyReportSettings::state())->toBe(dailyReportSettingsData());
})->with([
    'invalid JSON' => ['{"enabled":true'],
    'scalar' => ['true'],
    'list' => ['[true,1]'],
    'enabled only' => ['{"enabled":true}'],
    'string boolean' => [json_encode(dailyReportSettingsData(['enabled' => 'true']), JSON_THROW_ON_ERROR)],
    'empty workspaces when enabled' => [json_encode(dailyReportSettingsData(['enabled' => true, 'recipient_id' => 1]), JSON_THROW_ON_ERROR)],
    'bad workspaces' => [json_encode(dailyReportSettingsData(['workspace_ids' => 'all']), JSON_THROW_ON_ERROR)],
    'blank workspace ID' => [json_encode(dailyReportSettingsData(['workspace_ids' => ['']]), JSON_THROW_ON_ERROR)],
    'duplicate workspaces' => [json_encode(dailyReportSettingsData(['workspace_ids' => [1, '1']]), JSON_THROW_ON_ERROR)],
    'bad recipient' => [json_encode(dailyReportSettingsData(['recipient_id' => []]), JSON_THROW_ON_ERROR)],
    'bad time' => [json_encode(dailyReportSettingsData(['time' => 'invalid-time']), JSON_THROW_ON_ERROR)],
    'bad provider' => [json_encode(dailyReportSettingsData(['provider' => 'groq']), JSON_THROW_ON_ERROR)],
    'bad cost' => [json_encode(dailyReportSettingsData(['max_cost_usd' => -1]), JSON_THROW_ON_ERROR)],
]);

it('never reads workspace overrides and restores the inspected workspace after saving', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);
    $context = app(WorkspaceContext::class);
    $context->inspect($workspace);

    Setting::set('ai_provider', 'openai');
    Setting::set('gemini_api_key', 'workspace-only-key');
    Setting::query()->create([
        'key' => AiDailyReportSettings::SETTINGS_KEY,
        'workspace_id' => $workspace->id,
        'admin_id' => null,
        'value' => json_encode(dailyReportSettingsData([
            'enabled' => true,
            'workspace_ids' => [$workspace->id],
            'recipient_id' => $admin->id,
            'provider' => 'ollama',
        ]), JSON_THROW_ON_ERROR),
    ]);

    expect(AiDailyReportSettings::state())->toBe(dailyReportSettingsData())
        ->and(AiDailyReportSettings::providerOptions())->not->toHaveKey('gemini');

    $data = dailyReportSettingsData([
        'enabled' => true,
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $admin->id,
        'provider' => 'ollama',
        'max_cost_usd' => 0.5,
    ]);
    AiDailyReportSettings::save($data);

    expect(AiDailyReportSettings::state())->toBe($data)
        ->and($context->id())->toBe($workspace->id)
        ->and($context->isInspecting())->toBeTrue()
        ->and(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->whereNull('workspace_id')->count())->toBe(1)
        ->and(Setting::get(AiDailyReportSettings::SETTINGS_KEY))->not->toBe(Setting::query()
        ->where('key', AiDailyReportSettings::SETTINGS_KEY)->whereNull('workspace_id')->value('value'));
});

it('lists only globally configured supported providers including compatible gateways', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $workspace = Workspace::factory()->create();
    app(WorkspaceContext::class)->inspect($workspace);
    Setting::set('anthropic_api_key', 'workspace-key');
    Setting::setGlobal('gemini_api_key', 'global-gemini-key');
    Setting::setGlobal('openai_api_key', 'global-openai-key');
    Setting::setGlobal('groq_api_key', 'global-groq-key');
    Setting::setGlobal('cloudflare_account_id', 'account');
    Setting::setGlobal('cloudflare_api_token', 'token');
    config(['ai.providers.deepseek.env_key' => 'pristine-global-key']);
    config(['ai.providers.mistral.key' => 'leaked-runtime-workspace-key']);
    $id = '1f1b5e48-58c5-4c83-94c9-cce63fb827d8';
    $provider = "openai-compatible-{$id}";
    Setting::setGlobal(AiSdkProviderService::OPENAI_COMPATIBLE_SETTINGS_KEY, json_encode([[
        'id' => $id,
        'name' => 'Campus Gateway',
        'url' => 'https://gateway.example.test/v1',
        'model' => 'campus-model',
        'api_key' => null,
        'headers' => [],
    ]], JSON_THROW_ON_ERROR));
    Setting::setGlobal('ai_provider', $provider);

    expect(AiDailyReportSettings::providerOptions())->toBe([
        'gemini' => 'Gemini (Google)',
        'openai' => 'OpenAI',
        'deepseek' => 'DeepSeek',
        'ollama' => 'Ollama (Local)',
        $provider => 'Campus Gateway (OpenAI-compatible)',
    ])->and(AiDailyReportSettings::state()['provider'])->toBe($provider);

    AiDailyReportSettings::save(dailyReportSettingsData([
        'enabled' => true,
        'workspace_ids' => [$workspace->id],
        'recipient_id' => auth()->id(),
        'provider' => $provider,
    ]));

    expect(AiDailyReportSettings::state()['provider'])->toBe($provider);
});

it('rejects unsupported or unconfigured providers before enabling reports', function (string $provider) {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $workspace = Workspace::factory()->create();
    Setting::setGlobal('groq_api_key', 'configured-but-unsupported');
    Setting::setGlobal('cloudflare_account_id', 'account');
    Setting::setGlobal('cloudflare_api_token', 'token');

    try {
        AiDailyReportSettings::save(dailyReportSettingsData([
            'enabled' => true,
            'workspace_ids' => [$workspace->id],
            'recipient_id' => $admin->id,
            'provider' => $provider,
        ]));
        $this->fail('An unsupported or unconfigured provider was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('provider');
    }

    expect(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->exists())->toBeFalse();
})->with(['gemini', 'openai', 'unknown', 'groq', 'cloudflare']);

it('allows saving disabled settings without credentials and persists only canonical fields', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    AiDailyReportSettings::save(dailyReportSettingsData(['recipient_id' => '', 'max_cost_usd' => '0.25']));

    $stored = Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->whereNull('workspace_id')->value('value');

    expect(json_decode($stored, true, flags: JSON_THROW_ON_ERROR))->toBe(dailyReportSettingsData())
        ->and(AiDailyReportSettings::state())->toBe(dailyReportSettingsData());
});

it('validates workspaces recipient cost and readonly fields', function (array $overrides, string $errorKey) {
    $this->actingAs(User::factory()->superAdmin()->create());

    try {
        AiDailyReportSettings::save(dailyReportSettingsData($overrides));
        $this->fail('Invalid settings were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey);
    }

    expect(AiDailyReportSettings::state()['enabled'])->toBeFalse()
        ->and(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->exists())->toBeFalse();
})->with([
    'enabled needs workspaces' => [['enabled' => true, 'provider' => 'ollama'], 'workspace_ids'],
    'enabled needs recipient' => [['enabled' => true, 'provider' => 'ollama'], 'recipient_id'],
    'workspace must exist' => [['workspace_ids' => [999999]], 'workspace_ids.0'],
    'workspace must be integer' => [['workspace_ids' => ['not-an-id']], 'workspace_ids.0'],
    'workspace ID cannot be blank' => [['workspace_ids' => ['']], 'workspace_ids.0'],
    'workspaces must be distinct' => [['workspace_ids' => [999999, '999999']], 'workspace_ids.0'],
    'workspaces max 25' => [['workspace_ids' => range(1000, 1025)], 'workspace_ids'],
    'recipient must exist' => [['recipient_id' => 999999], 'recipient_id'],
    'cost zero' => [['max_cost_usd' => 0], 'max_cost_usd'],
    'cost too small' => [['max_cost_usd' => 0.0001], 'max_cost_usd'],
    'cost too large' => [['max_cost_usd' => 10.001], 'max_cost_usd'],
    'cost not numeric' => [['max_cost_usd' => 'cheap'], 'max_cost_usd'],
    'invalid time format' => [['time' => 'noon'], 'time'],
    'invalid time range' => [['time' => '25:00'], 'time'],
    'timezone readonly' => [['timezone' => 'UTC'], 'settings'],
    'arbitrary extra field' => [['unexpected' => true], 'settings'],
]);

it('rejects recipients without super admin rights', function (array $attributes) {
    $admin = User::factory()->superAdmin()->create();
    $recipient = User::factory()->create($attributes);
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);

    try {
        AiDailyReportSettings::save(dailyReportSettingsData([
            'enabled' => true,
            'workspace_ids' => [$workspace->id],
            'recipient_id' => $recipient->id,
            'provider' => 'ollama',
        ]));
        $this->fail('An ineligible recipient was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('recipient_id');
    }
})->with([
    'student' => [['is_admin' => false, 'is_super_admin' => false]],
    'workspace admin' => [['is_admin' => true, 'is_super_admin' => false]],
]);

it('accepts unverified super admins as report recipient', function () {
    $admin = User::factory()->superAdmin()->unverified()->create();
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);

    AiDailyReportSettings::save(dailyReportSettingsData([
        'enabled' => true,
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $admin->id,
        'provider' => 'ollama',
    ]));

    expect(AiDailyReportSettings::state()['recipient_id'])->toBe($admin->id);
});

it('accepts archived workspaces and normalizes valid IDs and cost', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create(['archived_at' => now()]);
    $this->actingAs($admin);

    AiDailyReportSettings::save(dailyReportSettingsData([
        'enabled' => '1',
        'workspace_ids' => [(string) $workspace->id],
        'recipient_id' => (string) $admin->id,
        'provider' => 'ollama',
        'max_cost_usd' => '0.001',
    ]));

    expect(AiDailyReportSettings::state())->toBe(dailyReportSettingsData([
        'enabled' => true,
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $admin->id,
        'provider' => 'ollama',
        'max_cost_usd' => 0.001,
    ]));
});

it('blocks unauthorized page mounts and helper writes', function (string $role) {
    if ($role !== 'guest') {
        $user = match ($role) {
            'admin' => User::factory()->admin()->create(),
            'student' => User::factory()->create(),
            'super flag only' => User::factory()->create(['is_admin' => false, 'is_super_admin' => true]),
        };
        $this->actingAs($user);
    }

    expect(AiDailyReports::canAccess())->toBeFalse();
    Livewire::test(AiDailyReports::class)->assertForbidden();
    expect(fn () => AiDailyReportSettings::save(dailyReportSettingsData()))->toThrow(HttpException::class);
    expect(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->exists())->toBeFalse();
})->with(['guest', 'admin', 'student', 'super flag only']);

it('renders settings with current super admin recipient and config-only schedule', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    config(['ai_reports.time' => '01:35', 'app.timezone' => 'Asia/Ho_Chi_Minh']);

    Livewire::test(AiDailyReportsSettingsPage::class)
        ->assertOk()
        ->assertSee('Nightly AI report settings')
        ->assertSee('01:35')
        ->assertSee('Asia/Ho_Chi_Minh')
        ->assertSee('previous complete local day')
        ->assertSee('Email arrives later')
        ->assertSee('Groq and Cloudflare')
        ->assertSee('No daily reports yet.');

    Livewire::test(AiDailyReportsSettingsPage::class)
        ->assertSet('data.enabled', false)
        ->assertSet('data.time', '01:35')
        ->assertSet('data.workspace_ids', [])
        ->assertSet('data.recipient_id', $admin->id)
        ->assertSet('data.max_cost_usd', 0.25)
        ->assertSchemaComponentExists('provider', 'form')
        ->set('data.time', '02:45')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiDailyReportSettings::state()['enabled'])->toBeFalse()
        ->and(AiDailyReportSettings::state()['time'])->toBe('02:45')
        ->and(config('ai_reports.time'))->toBe('01:35')
        ->and(config('app.timezone'))->toBe('Asia/Ho_Chi_Minh');
});

it('keeps a saved null recipient instead of silently replacing it on mount', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    AiDailyReportSettings::save(dailyReportSettingsData());

    Livewire::test(AiDailyReportsSettingsPage::class)->assertSet('data.recipient_id', null);
});

it('saves selected workspaces globally from the page while inspecting another workspace', function () {
    $admin = User::factory()->superAdmin()->create();
    $selected = Workspace::factory()->create();
    $inspected = Workspace::factory()->create();
    $this->actingAs($admin);
    app(WorkspaceContext::class)->inspect($inspected);
    Setting::set('ai_provider', 'groq');

    Livewire::test(AiDailyReportsSettingsPage::class)
        ->set('data.enabled', true)
        ->set('data.workspace_ids', [$selected->id])
        ->set('data.provider', 'ollama')
        ->call('save')
        ->assertHasNoErrors();

    expect(AiDailyReportSettings::state()['workspace_ids'])->toBe([$selected->id])
        ->and(AiDailyReportSettings::state()['recipient_id'])->toBe($admin->id)
        ->and(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->whereNotNull('workspace_id')->exists())->toBeFalse();
});

it('shows validation errors for unconfigured providers and ineligible recipients on the page', function () {
    $admin = User::factory()->superAdmin()->create();
    $recipient = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);

    Livewire::test(AiDailyReportsSettingsPage::class)
        ->set('data.enabled', true)
        ->set('data.workspace_ids', [$workspace->id])
        ->set('data.provider', 'openai')
        ->call('save')
        ->assertHasErrors(['data.provider' => 'in'])
        ->set('data.provider', 'ollama')
        ->set('data.recipient_id', $recipient->id)
        ->call('save')
        ->assertHasErrors(['data.recipient_id' => 'in']);

    expect(AiDailyReportSettings::state()['enabled'])->toBeFalse();
});

it('allows searching and saving an unverified super admin recipient when reports are enabled', function () {
    $admin = User::factory()->superAdmin()->unverified()->create([
        'first_name' => 'Custom',
        'last_name' => 'Admin',
        'name' => 'Custom Admin',
        'email' => 'custom.admin@example.test',
    ]);
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);

    Livewire::test(AiDailyReportsSettingsPage::class)
        ->assertSet('data.recipient_id', $admin->id)
        ->set('data.workspace_ids', [$workspace->id])
        ->set('data.provider', 'ollama')
        ->set('data.enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(AiDailyReportSettings::state()['recipient_id'])->toBe($admin->id)
        ->and(AiDailyReportSettings::state()['enabled'])->toBeTrue();
});

it('checks fresh super admin rights after page mount and before every helper write or report read', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $page = Livewire::test(AiDailyReportsSettingsPage::class);
    DB::table('users')->where('id', $admin->id)->update(['is_super_admin' => false]);

    expect($admin->isSuperAdmin())->toBeTrue()
        ->and(AiDailyReports::canAccess())->toBeFalse();
    $page->call('save')->assertForbidden();
    expect(fn () => AiDailyReportSettings::save(dailyReportSettingsData()))->toThrow(HttpException::class);
    expect(fn () => (new AiDailyReports)->getRecentReports())->toThrow(HttpException::class);
    expect(Setting::query()->where('key', AiDailyReportSettings::SETTINGS_KEY)->exists())->toBeFalse();
});

it('lists only ten recent persisted reports and escapes failure fields without showing raw AI content', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    foreach (range(1, 11) as $day) {
        DB::table('ai_daily_reports')->insert([
            'report_date' => sprintf('2026-09-%02d', $day),
            'timezone' => 'UTC',
            'status' => 'failed',
            'recipient_id' => $admin->id,
            'workspace_ids' => '[]',
            'max_cost_usd' => 0.25,
            'snapshot' => '{}',
            'analysis' => json_encode(['summary' => '<script>raw-ai-secret</script>'], JSON_THROW_ON_ERROR),
            'provider' => '<script>provider</script>',
            'model' => 'test-model',
            'error_code' => '<script>report-failure</script>',
            'delivery_failed_at' => now(),
            'delivery_error_code' => '<script>delivery-failure</script>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $reports = (new AiDailyReports)->getRecentReports();

    expect($reports)->toHaveCount(10)
        ->and($reports->first())->toBeInstanceOf(AiDailyReport::class)
        ->and($reports->first()->report_date->format('Y-m-d'))->toBe('2026-09-11')
        ->and($reports->last()->report_date->format('Y-m-d'))->toBe('2026-09-02');

    $this->get(AiDailyReports::getUrl())
        ->assertOk()
        ->assertSee('2026-09-11')
        ->assertDontSee('2026-09-01')
        ->assertSee('Delivery failed')
        ->assertSee('&lt;script&gt;report-failure&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;delivery-failure&lt;/script&gt;', false)
        ->assertDontSee('<script>provider</script>', false)
        ->assertDontSee('raw-ai-secret');
});

it('renders the preview button and AI summary for completed reports', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    DB::table('ai_daily_reports')->insert([
        'report_date' => '2026-10-10',
        'timezone' => 'Asia/Manila',
        'status' => 'completed',
        'recipient_id' => $admin->id,
        'workspace_ids' => '[1]',
        'max_cost_usd' => 0.25,
        'snapshot' => '{}',
        'analysis' => json_encode([
            'summary' => 'Workspace preview summary text for test',
            'findings' => [
                [
                    'severity' => 'warning',
                    'title' => 'Finding title test',
                    'detail' => 'Finding detail test',
                    'evidence_keys' => ['workspace:1:activity_completions'],
                ],
            ],
            'recommendations' => ['Recommendation test'],
        ], JSON_THROW_ON_ERROR),
        'provider' => 'test-provider',
        'model' => 'test-model',
        'input_tokens' => 1500,
        'output_tokens' => 200,
        'estimated_cost_micros' => 1234,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(AiDailyReports::getUrl())
        ->assertOk()
        ->assertSee('2026-10-10')
        ->assertSee('Completed')
        ->assertSee('Summary')
        ->assertSee('Resend')
        ->assertSee('Workspace preview summary text for test')
        ->assertSee('Finding title test');
});

it('allows a super admin to re-run and resend a report from the page', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();
    $this->actingAs($admin);

    Setting::setGlobal(AiDailyReportSettings::SETTINGS_KEY, json_encode([
        'enabled' => true,
        'time' => '00:15',
        'workspace_ids' => [$workspace->id],
        'recipient_id' => $admin->id,
        'provider' => 'gemini',
        'max_cost_usd' => 0.25,
    ], JSON_THROW_ON_ERROR));

    $report = AiDailyReport::factory()->create([
        'status' => 'incomplete',
        'recipient_id' => $admin->id,
        'workspace_ids' => [$workspace->id],
        'provider' => 'gemini',
        'max_cost_usd' => 0.25,
    ]);

    DailyWorkspaceAnalyst::fake([[
        'summary' => 'Re-analyzed executive summary test.',
        'findings' => [],
        'recommendations' => [],
    ]])->preventStrayPrompts();

    config(['mail.default' => 'smtp', 'ai.providers.gemini.env_key' => 'test-key']);
    Mail::fake();

    Livewire::test(AiDailyReports::class)
        ->call('rerunAndSend', $report->id)
        ->assertNotified('Report re-analyzed and delivered!');

    $report->refresh();
    expect($report->status)->toBe(AiDailyReport::STATUS_COMPLETED)
        ->and($report->analysis['summary'])->toBe('Re-analyzed executive summary test.')
        ->and($report->email_sent_at)->not->toBeNull();
});
