<?php

/**
 * Bonus XP Claim — the second daily reward.
 *
 * A flat, admin-configured XP amount that students can claim once per calendar
 * day from inside the Level → "Your XP history" modal, independent of the
 * streak-scaled Daily Claim. Configured in Platform Settings → Daily XP Claim
 * → Bonus XP (keys `daily_claim_bonus_enabled` / `daily_claim_bonus_xp`).
 */

use App\Models\BonusXpClaim;
use App\Models\Season;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Services\BonusXpService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

function bonusClaimContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$student, $section, $season];
}

// ─────────────────────────────────────────────
//  BonusXpService unit-level
// ─────────────────────────────────────────────

it('is disabled by default', function () {
    $user = User::factory()->create();

    expect(app(BonusXpService::class)->isEnabled())->toBeFalse();
    expect(app(BonusXpService::class)->canClaim($user))->toBeFalse();
});

it('becomes claimable when enabled in platform settings', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    $user = User::factory()->create();

    expect(app(BonusXpService::class)->isEnabled())->toBeTrue();
    expect(app(BonusXpService::class)->canClaim($user))->toBeTrue();
});

it('cannot claim twice on the same day', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    [$student] = bonusClaimContext();

    // Seed the ledger exactly the way BonusXpService::claim() writes it
    // (plain `Y-m-d` string via the query builder) so the same-day
    // `claim_date` comparison in canClaim() matches on sqlite too.
    DB::table('bonus_xp_claims')->insert([
        'user_id' => $student->id,
        'season_id' => Season::current()?->id,
        'claim_date' => now()->toDateString(),
        'amount' => 5,
        'streak' => 0,
        'claimed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(BonusXpService::class)->canClaim($student))->toBeFalse();
});

it('uses the flat amount from platform settings', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    Setting::set('daily_claim_bonus_xp', '7');

    expect(app(BonusXpService::class)->claimAmount(User::factory()->create()))->toBe(7);
});

it('clamps a non-positive configured amount to 1', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    Setting::set('daily_claim_bonus_xp', '0');

    expect(app(BonusXpService::class)->claimAmount(User::factory()->create()))->toBe(1);
});

// ─────────────────────────────────────────────
//  API integration
// ─────────────────────────────────────────────

it('awards the configured amount when claiming via the API', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    Setting::set('daily_claim_bonus_xp', '7');

    [$student] = bonusClaimContext();

    actingAs($student)->postJson('/api/claim-bonus-xp')
        ->assertOk()
        ->assertJson([
            'claimed' => true,
            'amount' => 7,
        ]);
});

it('rejects a second bonus claim on the same day', function () {
    Setting::set('daily_claim_bonus_enabled', '1');

    [$student] = bonusClaimContext();

    actingAs($student)->postJson('/api/claim-bonus-xp')->assertJsonPath('claimed', true);

    actingAs($student)->postJson('/api/claim-bonus-xp')
        ->assertOk()
        ->assertJsonPath('claimed', false);

    expect(BonusXpClaim::where('user_id', $student->id)->count())->toBe(1);
});

it('does nothing when the bonus claim feature is disabled', function () {
    [$student] = bonusClaimContext();

    actingAs($student)->postJson('/api/claim-bonus-xp')
        ->assertOk()
        ->assertJson([
            'claimed' => false,
        ]);

    expect($student->gamificationHistories()->where('reason', 'Bonus Claim')->count())->toBe(0)
        ->and(BonusXpClaim::where('user_id', $student->id)->count())->toBe(0);
});

it('creates a gamification history entry on claim', function () {
    Setting::set('daily_claim_bonus_enabled', '1');

    [$student] = bonusClaimContext();

    actingAs($student)->postJson('/api/claim-bonus-xp')->assertOk();

    expect($student->gamificationHistories()->where('reason', 'Bonus Claim')->count())->toBe(1);
});

it('creates one durable claim ledger row', function () {
    Setting::set('daily_claim_bonus_enabled', '1');
    Setting::set('daily_claim_bonus_xp', '7');

    [$student, , $season] = bonusClaimContext();

    actingAs($student)->postJson('/api/claim-bonus-xp')->assertOk();

    $claim = BonusXpClaim::where('user_id', $student->id)->firstOrFail();

    expect($claim->claim_date->isToday())->toBeTrue()
        ->and($claim->amount)->toBe(7)
        ->and($claim->streak)->toBe(0)
        ->and($claim->season_id)->toBe($season->id);
});

it('is independent from the daily claim on the same day', function () {
    Setting::set('daily_claim_enabled', '1');
    Setting::set('daily_claim_bonus_enabled', '1');

    [$student] = bonusClaimContext();

    // Claiming the daily reward must NOT consume the bonus claim.
    actingAs($student)->postJson('/api/claim-xp')->assertJsonPath('claimed', true);
    actingAs($student)->postJson('/api/claim-bonus-xp')->assertJsonPath('claimed', true);

    expect($student->gamificationHistories()->where('reason', 'Daily Claim')->count())->toBe(1)
        ->and($student->gamificationHistories()->where('reason', 'Bonus Claim')->count())->toBe(1)
        ->and(BonusXpClaim::where('user_id', $student->id)->count())->toBe(1);
});

it('mirrors a bonus claim to every section but increments global and season XP once', function () {
    Setting::set('daily_claim_bonus_enabled', '1');

    $season = Season::factory()->active()->create();
    $student = User::factory()->create(['exp' => 0]);
    $sections = Section::factory()->count(3)->forSeason($season)->create();

    foreach ($sections as $section) {
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
        // Initialize at zero so this assertion isolates the bonus reward.
        $student->sectionProgress()->create([
            'section_id' => $section->id,
            'exp' => 0,
            'points' => 0,
            'level' => 1,
        ]);
    }

    actingAs($student)->postJson('/api/claim-bonus-xp')->assertJson([
        'claimed' => true,
        'amount' => 5,
        'total_xp' => 5,
    ]);

    expect($student->sectionProgress()->orderBy('section_id')->pluck('exp')->map(fn ($xp) => (float) $xp)->all())
        ->toBe([5.0, 5.0, 5.0])
        ->and((float) $student->fresh()->exp)->toBe(5.0)
        ->and((float) $student->activeSeasonProgress()->exp)->toBe(5.0)
        ->and($student->gamificationHistories()->where('reason', 'Bonus Claim')->count())->toBe(1);
});

it('passes bonus claim data to the dashboard', function () {
    Setting::set('daily_claim_bonus_enabled', '1');

    [$student] = bonusClaimContext();

    $response = actingAs($student)->get('/dashboard');

    $response->assertOk();
    expect($response->getContent())->toContain('bonusXp');
});
