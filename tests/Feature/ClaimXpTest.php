<?php

/**
 * Phase 2 — Daily Claim XP.
 *
 * Students can click a button on the dashboard once per day to claim
 * streak-scaled XP (1 + floor(streak / 5)).
 */

use App\Models\DailyXpClaim;
use App\Models\Season;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClaimXpService;
use App\Support\GamificationSyncContext;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

function claimContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$student, $section, $season];
}

// ─────────────────────────────────────────────
//  ClaimXpService unit-level
// ─────────────────────────────────────────────

it('can claim when never claimed before', function () {
    $user = User::factory()->create(['last_claimed_at' => null]);

    expect(app(ClaimXpService::class)->canClaim($user))->toBeTrue();
});

it('can claim when last claimed yesterday', function () {
    $user = User::factory()->create(['last_claimed_at' => now()->subDay()]);

    expect(app(ClaimXpService::class)->canClaim($user))->toBeTrue();
});

it('cannot claim twice on the same day', function () {
    $user = User::factory()->create(['last_claimed_at' => now()]);

    expect(app(ClaimXpService::class)->canClaim($user))->toBeFalse();
});

it('calculates base claim amount for streak 1-4', function () {
    $user = User::factory()->create(['current_streak' => 1]);

    expect(app(ClaimXpService::class)->claimAmount($user))->toBe(1);

    $user->current_streak = 4;
    expect(app(ClaimXpService::class)->claimAmount($user))->toBe(1);
});

it('scales claim amount at streak milestones', function () {
    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 5])
    ))->toBe(2);

    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 10])
    ))->toBe(3);

    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 15])
    ))->toBe(4);

    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 20])
    ))->toBe(5);
});

it('caps at 5 XP for very high streaks', function () {
    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 100])
    ))->toBe(5);
});

// ─────────────────────────────────────────────
//  Platform Settings — configurable daily claim
// ─────────────────────────────────────────────

it('uses the configurable base XP from platform settings', function () {
    Setting::set('daily_claim_base_xp', '10');

    // Streak 0 → just the base
    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 0])
    ))->toBe(10);

    // Streak 7 → base + floor(7/5) = 11
    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 7])
    ))->toBe(11);

    // Streak 100 → base + max bonus of 4 = 14
    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 100])
    ))->toBe(14);
});

it('clamps a non-positive configured base XP to 1', function () {
    Setting::set('daily_claim_base_xp', '0');

    expect(app(ClaimXpService::class)->claimAmount(
        User::factory()->create(['current_streak' => 0])
    ))->toBe(1);
});

it('awards the configured base XP when claiming via the API', function () {
    Setting::set('daily_claim_base_xp', '3');

    [$student] = claimContext();

    actingAs($student)->postJson('/api/claim-xp')
        ->assertOk()
        ->assertJson([
            'claimed' => true,
            'amount' => 3,
        ]);
});

it('blocks claiming when the daily claim feature is disabled', function () {
    Setting::set('daily_claim_enabled', '0');

    [$student] = claimContext();

    expect(app(ClaimXpService::class)->canClaim($student))->toBeFalse();

    actingAs($student)->postJson('/api/claim-xp')
        ->assertOk()
        ->assertJson([
            'claimed' => false,
        ]);

    expect($student->gamificationHistories()
        ->where('reason', 'Daily Claim')
        ->count()
    )->toBe(0);
});

it('keeps claiming enabled by default when the setting is absent', function () {
    [$student] = claimContext();

    expect(app(ClaimXpService::class)->isEnabled())->toBeTrue();
    expect(app(ClaimXpService::class)->canClaim($student))->toBeTrue();
});

// ─────────────────────────────────────────────
//  API integration
// ─────────────────────────────────────────────

it('returns claimed true with amount on first claim', function () {
    [$student] = claimContext();

    $response = actingAs($student)->postJson('/api/claim-xp');

    $response->assertOk()
        ->assertJson([
            'claimed' => true,
            'amount' => 1,
        ]);
});

it('rejects a second claim on the same day', function () {
    [$student] = claimContext();

    actingAs($student)->postJson('/api/claim-xp');
    $response = actingAs($student)->postJson('/api/claim-xp');

    $response->assertOk()
        ->assertJson([
            'claimed' => false,
        ]);
});

it('creates a gamification history entry on claim', function () {
    [$student] = claimContext();

    actingAs($student)->postJson('/api/claim-xp');

    expect($student->gamificationHistories()
        ->where('reason', 'Daily Claim')
        ->count()
    )->toBe(1);
});

it('updates last_claimed_at on the user', function () {
    [$student] = claimContext();

    expect($student->fresh()->last_claimed_at)->toBeNull();

    actingAs($student)->postJson('/api/claim-xp');

    expect($student->fresh()->last_claimed_at)->not->toBeNull();
});

it('awards the correct streak-scaled amount', function () {
    [$student] = claimContext();

    // Update the streak directly
    $student->update(['current_streak' => 7]);

    $response = actingAs($student)->postJson('/api/claim-xp');

    // Streak 7: 1 + floor(7/5) = 2
    $response->assertOk()
        ->assertJsonPath('amount', 2);
});

it('is idempotent under rapid double-clicks', function () {
    [$student] = claimContext();

    // Fire two requests in quick succession
    actingAs($student)->postJson('/api/claim-xp');
    actingAs($student)->postJson('/api/claim-xp');

    expect($student->gamificationHistories()
        ->where('reason', 'Daily Claim')
        ->count()
    )->toBe(1);
});

it('creates one durable claim ledger row', function () {
    [$student, , $season] = claimContext();

    actingAs($student)->postJson('/api/claim-xp')->assertOk();

    $claim = DailyXpClaim::query()->where('user_id', $student->id)->firstOrFail();

    expect($claim->claim_date->isToday())->toBeTrue()
        ->and($claim->amount)->toBe(1)
        ->and($claim->streak)->toBe(0)
        ->and($claim->season_id)->toBe($season->id);
});

it('uses the durable ledger even if the legacy timestamp becomes stale', function () {
    [$student] = claimContext();

    actingAs($student)->postJson('/api/claim-xp')->assertJsonPath('claimed', true);

    // Simulates stale cache/data or another process reverting the legacy field.
    $student->forceFill(['last_claimed_at' => null])->save();

    actingAs($student->fresh())
        ->postJson('/api/claim-xp')
        ->assertOk()
        ->assertJsonPath('claimed', false);

    expect(DailyXpClaim::where('user_id', $student->id)->count())->toBe(1)
        ->and($student->gamificationHistories()->where('reason', 'Daily Claim')->count())->toBe(1)
        ->and((float) $student->fresh()->exp)->toBe(1.0);
});

it('mirrors a claim to every section but increments global and season XP once', function () {
    $season = Season::factory()->active()->create();
    $student = User::factory()->create(['exp' => 0, 'last_claimed_at' => null]);
    $sections = Section::factory()->count(3)->forSeason($season)->create();

    foreach ($sections as $section) {
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
        // Initialize at zero so this assertion isolates the daily reward.
        $student->sectionProgress()->create([
            'section_id' => $section->id,
            'exp' => 0,
            'points' => 0,
            'level' => 1,
        ]);
    }

    actingAs($student)->postJson('/api/claim-xp')->assertJson([
        'claimed' => true,
        'amount' => 1,
        'total_xp' => 1,
    ]);

    expect($student->sectionProgress()->orderBy('section_id')->pluck('exp')->map(fn ($xp) => (float) $xp)->all())
        ->toBe([1.0, 1.0, 1.0])
        ->and((float) $student->fresh()->exp)->toBe(1.0)
        ->and((float) $student->activeSeasonProgress()->exp)->toBe(1.0)
        ->and($student->gamificationHistories()->where('reason', 'Daily Claim')->count())->toBe(1);
});

it('starts each Octane-style request with a fresh synchronization context', function () {
    $first = app(GamificationSyncContext::class);

    $first->withoutAutomaticHistory(function () use ($first): void {
        expect($first->automaticHistorySuppressed())->toBeTrue();
    });

    app()->forgetScopedInstances();
    $second = app(GamificationSyncContext::class);

    expect($second)->not->toBe($first)
        ->and($second->sectionPropagationSuppressed())->toBeFalse()
        ->and($second->seasonPropagationSuppressed())->toBeFalse()
        ->and($second->automaticHistorySuppressed())->toBeFalse();
});

it('passes claim data to the dashboard', function () {
    [$student] = claimContext();

    $response = actingAs($student)->get('/dashboard');

    $response->assertOk();
    expect($response->getContent())->toContain('canClaim');
});

it('shows already claimed state on dashboard after claiming', function () {
    [$student] = claimContext();

    actingAs($student)->post('/api/claim-xp', [], ['Accept' => 'application/json']);

    $response = actingAs($student)->get('/dashboard');

    $response->assertOk();
    expect($response->getContent())->toContain('canClaim');
});

it('syncs claimed XP into the active season progress when the user has no sections', function () {
    $season = Season::factory()->active()->create();
    $user = User::factory()->create(['last_claimed_at' => null]);

    actingAs($user)->postJson('/api/claim-xp')->assertOk();

    // Both the user exp and the season progress (what the dashboard stat
    // cards display) must reflect the claim.
    expect((float) $user->fresh()->exp)->toBe(1.0);
    expect((float) $user->activeSeasonProgress()->exp)->toBe(1.0);
});

function dashboardClaimPrompt(TestResponse $response): bool
{
    $content = $response->getContent();
    preg_match('/data-page="([^"]*)"/', $content, $matches);
    $page = json_decode(html_entity_decode($matches[1] ?? ''), true);

    return (bool) ($page['props']['claimXp']['showPrompt'] ?? false);
}

it('keeps the claim prompt available for section-less users until it is shown', function () {
    $season = Season::factory()->active()->create();
    $user = User::factory()->create(['last_claimed_at' => null]);

    // No section yet — the prompt is deferred, so the once-per-session flag
    // must NOT be consumed server-side on this first visit.
    $first = actingAs($user)->get('/dashboard');
    $first->assertOk();
    expect(dashboardClaimPrompt($first))->toBeTrue();

    // The client marks it as shown when the prompt actually opens.
    actingAs($user)->postJson('/api/claim-xp/prompt-shown')->assertOk();

    // Later dashboard visits no longer auto-open the prompt.
    $second = actingAs($user)->get('/dashboard');
    $second->assertOk();
    expect(dashboardClaimPrompt($second))->toBeFalse();
});

it('consumes the claim prompt flag once for users who already have a section', function () {
    [$student] = claimContext();

    $first = actingAs($student)->get('/dashboard');
    $first->assertOk();
    expect(dashboardClaimPrompt($first))->toBeTrue();

    $second = actingAs($student)->get('/dashboard');
    $second->assertOk();
    expect(dashboardClaimPrompt($second))->toBeFalse();
});
