<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Exceptions\AiBudgetExceededException;
use App\Models\AiBudgetEvent;
use App\Models\AiBudgetPeriod;
use App\Models\AiBudgetReservation;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiBudgetManager;
use App\Services\AiBudgetReportingService;
use App\Services\AiCostEstimator;
use App\Services\AiProviderFallbackPolicy;
use App\Services\AiUsageTracker;
use App\Support\WorkspaceContext;

beforeEach(function () {
    Setting::flushAllCaches();
});

function enableTestBudget(array $overrides = []): void
{
    $settings = [
        'ai_budget_enabled' => '1',
        'ai_budget_daily_tokens' => '1000',
        'ai_budget_monthly_tokens' => '10000',
        'ai_budget_daily_cost' => '0',
        'ai_budget_monthly_cost' => '0',
        'ai_budget_warning_percent' => '80',
        ...$overrides,
    ];

    foreach ($settings as $key => $value) {
        Setting::set($key, $value);
    }
}

it('atomically counts active reservations before allowing another request', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget(['ai_budget_daily_tokens' => '100']);
    $budgets = app(AiBudgetManager::class);

    $first = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 30, 40);

    expect($first)->toBeInstanceOf(AiBudgetReservation::class)
        ->and(AiBudgetPeriod::where('period_type', 'daily')->first()->reserved_tokens)->toBe(70)
        ->and(AiBudgetPeriod::where('period_type', 'monthly')->first()->reserved_tokens)->toBe(70);

    expect(fn () => $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 20, 20))
        ->toThrow(AiBudgetExceededException::class);

    expect(AiBudgetReservation::count())->toBe(1)
        ->and(AiBudgetEvent::where('event', 'blocked')->count())->toBe(1)
        ->and(AiBudgetPeriod::where('period_type', 'daily')->first()->blocked_count)->toBe(1);
});

it('settles actual usage once and reconciles the reservation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget();
    $budgets = app(AiBudgetManager::class);
    $reservation = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 100, 200);

    $budgets->settle($reservation, 90, 40);
    $budgets->settle($reservation, 90, 40);

    $daily = AiBudgetPeriod::where('period_type', 'daily')->firstOrFail();
    expect($reservation->refresh()->status)->toBe(AiBudgetReservation::STATUS_SETTLED)
        ->and($daily->reserved_tokens)->toBe(0)
        ->and($daily->used_input_tokens)->toBe(90)
        ->and($daily->used_output_tokens)->toBe(40)
        ->and($daily->request_count)->toBe(1)
        ->and(AiUsageLog::count())->toBe(1)
        ->and(AiUsageLog::first()->ai_budget_reservation_id)->toBe($reservation->id)
        ->and(AiUsageLog::first()->estimated_cost_micros)->toBeGreaterThan(0);
});

it('emits one warning when a period crosses its configured threshold', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget([
        'ai_budget_daily_tokens' => '1000',
        'ai_budget_warning_percent' => '80',
    ]);
    $budgets = app(AiBudgetManager::class);

    $reservation = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 100, 700);
    $budgets->settle($reservation, 100, 700);

    expect(AiBudgetEvent::where('event', 'warning')->count())->toBe(1)
        ->and(AiBudgetPeriod::where('period_type', 'daily')->first()->warning_emitted_at)->not->toBeNull();
});

it('releases failed and expired reservations without consuming the budget', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget(['ai_budget_daily_tokens' => '100']);
    $budgets = app(AiBudgetManager::class);
    $failed = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 30, 40);

    $budgets->release($failed, 'Provider unavailable');
    expect($failed->refresh()->status)->toBe(AiBudgetReservation::STATUS_RELEASED)
        ->and(AiBudgetPeriod::where('period_type', 'daily')->first()->reserved_tokens)->toBe(0);

    $expired = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 30, 40);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $next = $budgets->reserve('openai', 'gpt-4o-mini', 'chat', 30, 40);

    expect($expired->refresh()->status)->toBe(AiBudgetReservation::STATUS_RELEASED)
        ->and($next)->toBeInstanceOf(AiBudgetReservation::class)
        ->and(AiBudgetPeriod::where('period_type', 'daily')->first()->reserved_tokens)->toBe(70);
});

it('includes usage recorded earlier in the same period when enforcement is enabled', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Setting::set('ai_budget_enabled', '0');
    app(AiUsageTracker::class)->record('openai', 'gpt-4o-mini', 'chat', 60, 20);

    expect(AiUsageLog::withoutGlobalScope('workspace')->count())->toBe(1)
        ->and(AiUsageLog::withoutGlobalScope('workspace')->first()->workspace_id)->toBe($admin->current_workspace_id);

    enableTestBudget(['ai_budget_daily_tokens' => '100']);

    expect(fn () => app(AiBudgetManager::class)->reserve('openai', 'gpt-4o-mini', 'chat', 10, 20))
        ->toThrow(AiBudgetExceededException::class);
});

it('enforces estimated cost ceilings using workspace rate overrides', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget([
        'ai_budget_daily_tokens' => '0',
        'ai_budget_daily_cost' => '0.0001',
    ]);
    Setting::set('ai_budget_cost_rates', json_encode([[
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'input' => 1,
        'output' => 1,
    ]], JSON_THROW_ON_ERROR));

    $budgets = app(AiBudgetManager::class);
    $budgets->reserve('openai', 'gpt-4o-mini', 'generation', 40, 50);

    try {
        $budgets->reserve('openai', 'gpt-4o-mini', 'generation', 10, 10);
        $this->fail('Expected the daily cost budget to block the request.');
    } catch (AiBudgetExceededException $exception) {
        expect($exception->metric)->toBe('cost')
            ->and($exception->period)->toBe('daily');
    }
});

it('isolates counters and settings between workspaces', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    enableTestBudget(['ai_budget_daily_tokens' => '100']);
    app(AiBudgetManager::class)->reserve('openai', 'gpt-4o-mini', 'chat', 40, 50);

    $this->actingAs($adminB);
    enableTestBudget(['ai_budget_daily_tokens' => '100']);
    $reservationB = app(AiBudgetManager::class)->reserve('openai', 'gpt-4o-mini', 'chat', 40, 50);

    expect($reservationB->workspace_id)->toBe($adminB->current_workspace_id)
        ->and(AiBudgetReservation::query()->count())->toBe(1)
        ->and(AiBudgetReservation::withoutGlobalScope('workspace')->count())->toBe(2);
});

it('uses fallback rules for provider failures and optionally budget blocks', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $policy = app(AiProviderFallbackPolicy::class);
    Setting::set('ai_fallback_provider', 'ollama');
    Setting::set('ai_fallback_mode', AiProviderFallbackPolicy::MODE_PROVIDER_FAILURE);

    expect($policy->fallbackFor('openai', new RuntimeException('offline')))->toBe('ollama')
        ->and($policy->fallbackFor('openai', new AiBudgetExceededException('daily', 'tokens', 100, 90, 20)))->toBeNull();

    Setting::set('ai_fallback_mode', AiProviderFallbackPolicy::MODE_PROVIDER_FAILURE_OR_BUDGET);

    expect($policy->fallbackFor('openai', new AiBudgetExceededException('daily', 'cost', 100, 90, 20)))->toBe('ollama');
});

it('records regular usage without reservations while enforcement is disabled', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Setting::set('ai_budget_enabled', '0');

    $tracker = app(AiUsageTracker::class);
    $reservation = $tracker->start('gemini', 'gemini-3.5-flash', 'chat', 100, 200);
    $tracker->complete($reservation, 'gemini', 'gemini-3.5-flash', 'chat', 100, 50);

    expect($reservation)->toBeNull()
        ->and(AiBudgetReservation::count())->toBe(0)
        ->and(AiUsageLog::count())->toBe(1)
        ->and(AiUsageLog::first()->estimated_cost_micros)->toBeGreaterThan(0);
});

it('blocks chat before contacting the provider and returns a clear message', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'test-key');
    Setting::set('ai_fallback_mode', 'disabled');
    enableTestBudget([
        'ai_budget_daily_tokens' => '100',
        'ai_budget_monthly_tokens' => '100',
    ]);
    AdminAssistantAgent::fake(['This must never be generated.']);

    $this->postJson(route('chat'), ['message' => 'Hello'])
        ->assertStatus(429)
        ->assertJsonPath('response', fn (string $message): bool => str_contains($message, 'workspace has reached'));

    AdminAssistantAgent::assertNeverPrompted();
    expect(AiBudgetReservation::count())->toBe(0)
        ->and(AiBudgetEvent::where('event', 'blocked')->count())->toBe(1);
});

it('falls back to a lower-cost provider when the configured rule allows it', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Setting::set('ai_provider', 'openai');
    Setting::set('openai_api_key', 'test-key');
    Setting::set('ai_fallback_mode', 'provider_failure_or_budget');
    Setting::set('ai_fallback_provider', 'ollama');
    enableTestBudget([
        'ai_budget_daily_tokens' => '0',
        'ai_budget_monthly_tokens' => '0',
        'ai_budget_daily_cost' => '0.0001',
        'ai_budget_monthly_cost' => '0.0001',
    ]);
    AdminAssistantAgent::fake(['Local fallback response.']);

    $this->postJson(route('chat'), ['message' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('response', 'Local fallback response.');

    AdminAssistantAgent::assertPrompted(
        fn ($prompt): bool => $prompt->provider->name() === 'ollama',
    );
    expect(AiBudgetEvent::where('event', 'fallback')->count())->toBe(1)
        ->and(AiUsageLog::where('provider', 'ollama')->count())->toBe(1);
});

it('reports bounded feature provider and budget event breakdowns', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    enableTestBudget();
    $tracker = app(AiUsageTracker::class);

    foreach (['chat', 'grading', 'generation'] as $feature) {
        $reservation = $tracker->start('openai', 'gpt-4o-mini', $feature, 20, 40);
        $tracker->complete($reservation, 'openai', 'gpt-4o-mini', $feature, 20, 10);
    }

    $report = app(AiBudgetReportingService::class)->dashboard();

    expect($report['daily']['request_count'])->toBe(3)
        ->and($report['features'])->toHaveCount(3)
        ->and($report['providers'])->toHaveCount(1)
        ->and($report['trend'])->toHaveCount(14)
        ->and($report['platformMode'])->toBeFalse();
});

it('supports platform-wide and isolated super-admin usage views', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    foreach ([$adminA, $adminB] as $admin) {
        $this->actingAs($admin);
        Setting::set('ai_budget_enabled', '0');
        app(AiUsageTracker::class)->record('openai', 'gpt-4o-mini', 'chat', 50, 10);
    }

    $this->actingAs($superAdmin);
    $platform = app(AiBudgetReportingService::class)->dashboard();

    expect($platform['platformMode'])->toBeTrue()
        ->and($platform['workspaces'])->toHaveCount(2);

    app(WorkspaceContext::class)->inspect($adminA->currentWorkspace);
    $isolated = app(AiBudgetReportingService::class)->dashboard();

    expect($isolated['platformMode'])->toBeFalse()
        ->and($isolated['daily']['request_count'])->toBe(1)
        ->and($isolated['workspaces'])->toBe([]);
});

it('supports custom per-model cost estimates', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Setting::set('ai_budget_cost_rates', json_encode([[
        'provider' => 'openai',
        'model' => 'campus-model',
        'input' => 2.5,
        'output' => 7.5,
    ]], JSON_THROW_ON_ERROR));

    expect(app(AiCostEstimator::class)->estimateMicros('openai', 'campus-model-v2', 100, 20))
        ->toBe(400);
});
