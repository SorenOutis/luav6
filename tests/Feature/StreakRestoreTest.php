<?php

/**
 * Streak Restore.
 *
 * Students can spend seasonal XP to backfill a missed day from the streak
 * calendar. Pricing escalates per use within the calendar month
 * (25 / 50 / 100 XP by default, configurable in Platform Settings), at most
 * 3 restores per month. Restores are blocked — never clamped — when the
 * balance cannot cover the cost, so XP can never go negative.
 */

use App\Models\Season;
use App\Models\Section;
use App\Models\Setting;
use App\Models\StreakRestore;
use App\Models\User;
use App\Services\StreakRestoreService;
use App\Services\StreakService;

use function Pest\Laravel\actingAs;

function restoreContext(float $seasonXp = 200): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create([
        'current_streak' => 1,
        'longest_streak' => 1,
        'last_login_at' => now(),
    ]);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $student->seasonProgress()->create([
        'season_id' => $season->id,
        'exp' => $seasonXp,
        'level' => 1,
        'points' => 0,
    ]);

    return [$student, $season];
}

/** Mark a past date active via a backdated activity row (no XP impact). */
function markActiveDay(User $student, string $date, int $seasonId): void
{
    $history = $student->recordGamificationHistory(
        1, 0, 'Daily Claim', 'test activity', null, $seasonId,
    );
    $history->forceFill([
        'created_at' => $date.' 12:00:00',
        'updated_at' => $date.' 12:00:00',
    ])->save();
}

function daysAgo(int $days): string
{
    return now()->subDays($days)->toDateString();
}

function seasonalExp(User $student): float
{
    return (float) ($student->fresh()->activeSeasonProgress()?->exp ?? 0);
}

// ─────────────────────────────────────────────
//  Happy path
// ─────────────────────────────────────────────

it('restores a missed yesterday and repairs the streak', function () {
    [$student, $season] = restoreContext(200);

    markActiveDay($student, daysAgo(4), $season->id);
    markActiveDay($student, daysAgo(3), $season->id);
    markActiveDay($student, daysAgo(2), $season->id);

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($result['restored'])->toBeTrue()
        ->and($result['cost'])->toBe(25)
        ->and($result['remaining'])->toBe(2)
        ->and(seasonalExp($student))->toBe(175.0)
        ->and($student->fresh()->current_streak)->toBe(5)
        ->and($student->fresh()->longest_streak)->toBe(5);
});

it('deducts escalating costs and enforces the monthly limit', function () {
    [$student] = restoreContext(500);
    $service = app(StreakRestoreService::class);

    expect($service->restore($student, daysAgo(1)))->toMatchArray([
        'restored' => true, 'cost' => 25,
    ]);
    expect($service->restore($student, daysAgo(2)))->toMatchArray([
        'restored' => true, 'cost' => 50,
    ]);
    expect($service->restore($student, daysAgo(3)))->toMatchArray([
        'restored' => true, 'cost' => 100,
    ]);

    expect(seasonalExp($student))->toBe(325.0);

    $fourth = $service->restore($student, daysAgo(4));

    expect($fourth['restored'])->toBeFalse()
        ->and($fourth['remaining'])->toBe(0)
        ->and(seasonalExp($student))->toBe(325.0);
});

it('writes a negative XP history entry for the deduction', function () {
    [$student] = restoreContext(200);

    app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($student->gamificationHistories()
        ->where('reason', 'Streak Restore')
        ->where('amount_xp', -25)
        ->exists())->toBeTrue();
});

it('allows a level drop but never a negative balance', function () {
    // 110 XP → Level 2. Two restores (25 + 50) leave 35 XP → Level 1.
    [$student] = restoreContext(110);
    $service = app(StreakRestoreService::class);

    $service->restore($student, daysAgo(1));
    $service->restore($student, daysAgo(2));

    $progress = $student->fresh()->activeSeasonProgress();

    expect((float) $progress->exp)->toBe(35.0)
        ->and((int) $progress->level)->toBe(1);
});

// ─────────────────────────────────────────────
//  Guardrails
// ─────────────────────────────────────────────

it('blocks a restore when XP is insufficient and keeps the balance', function () {
    [$student] = restoreContext(10);

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($result['restored'])->toBeFalse()
        ->and($result['reason'])->toBe('Not enough XP for this restore.')
        ->and(seasonalExp($student))->toBe(10.0)
        ->and(StreakRestore::query()->count())->toBe(0);
});

it('blocks restoring an already-active day', function () {
    [$student, $season] = restoreContext(200);
    markActiveDay($student, daysAgo(1), $season->id);

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($result['restored'])->toBeFalse()
        ->and(seasonalExp($student))->toBe(200.0);
});

it('blocks restoring the same date twice', function () {
    [$student] = restoreContext(200);
    $service = app(StreakRestoreService::class);

    expect($service->restore($student, daysAgo(1))['restored'])->toBeTrue();

    $again = $service->restore($student, daysAgo(1));

    expect($again['restored'])->toBeFalse()
        ->and(seasonalExp($student))->toBe(175.0);
});

it('blocks restoring when the feature is disabled', function () {
    Setting::set('streak_restore_enabled', '0');
    [$student] = restoreContext(200);

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($result['restored'])->toBeFalse()
        ->and(seasonalExp($student))->toBe(200.0);
});

// ─────────────────────────────────────────────
//  Platform Settings — configurable pricing
// ─────────────────────────────────────────────

it('honors admin-configured pricing and monthly limit', function () {
    Setting::set('streak_restore_cost_1', '40');
    Setting::set('streak_restore_cost_2', '60');
    Setting::set('streak_restore_cost_3', '90');
    Setting::set('streak_restore_monthly_limit', '1');

    [$student] = restoreContext(500);
    $service = app(StreakRestoreService::class);

    expect($service->costForNextRestore($student))->toBe(40);

    $first = $service->restore($student, daysAgo(1));

    expect($first)->toMatchArray(['restored' => true, 'cost' => 40, 'remaining' => 0]);
    expect(seasonalExp($student))->toBe(460.0);

    expect($service->restore($student, daysAgo(2))['restored'])->toBeFalse();
});

// ─────────────────────────────────────────────
//  API
// ─────────────────────────────────────────────

it('restores via the API and returns the new totals', function () {
    [$student] = restoreContext(200);

    actingAs($student)
        ->postJson('/api/streak-restore', ['date' => daysAgo(1)])
        ->assertOk()
        ->assertJson([
            'restored' => true,
            'cost' => 25,
            'remaining' => 2,
            'total_xp' => 175,
        ]);
});

it('rejects API restores without enough XP', function () {
    [$student] = restoreContext(10);

    actingAs($student)
        ->postJson('/api/streak-restore', ['date' => daysAgo(1)])
        ->assertUnprocessable()
        ->assertJson(['restored' => false]);
});

it('rejects API restores for today or future dates', function () {
    [$student] = restoreContext(200);

    actingAs($student)
        ->postJson('/api/streak-restore', ['date' => now()->toDateString()])
        ->assertUnprocessable();

    actingAs($student)
        ->postJson('/api/streak-restore', ['date' => now()->addDay()->toDateString()])
        ->assertUnprocessable();

    expect(StreakRestore::query()->count())->toBe(0);
});

it('shares streak restore data with the dashboard', function () {
    [$student] = restoreContext(200);

    actingAs($student)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('streakRestore')
            ->where('streakRestore.enabled', true)
            ->where('streakRestore.limit', 3)
            ->where('streakRestore.remaining', 3)
            ->where('streakRestore.nextCost', 25));
});

// ─────────────────────────────────────────────
//  Month boundaries & interaction with StreakService
// ─────────────────────────────────────────────

it('counts consecutive active days across a month boundary', function () {
    [$student, $season] = restoreContext(200);

    // 40 consecutive days ending today — guaranteed to straddle a month edge.
    foreach (range(0, 39) as $daysAgo) {
        markActiveDay($student, daysAgo($daysAgo), $season->id);
    }

    app(StreakRestoreService::class)->repairStreak($student);

    expect((int) $student->fresh()->current_streak)->toBe(40);
});

it('survives the next dashboard visit after a restore', function () {
    [$student, $season] = restoreContext(200);
    // Stale login stamp, as if the student claimed XP without opening the
    // dashboard for a few days.
    $student->update(['last_login_at' => now()->subDays(4)]);

    foreach (range(0, 3) as $daysAgo) {
        markActiveDay($student, daysAgo($daysAgo), $season->id);
    }

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(4));
    expect($result['restored'])->toBeTrue();

    // Previously this reset the streak to 1, discarding the repair.
    app(StreakService::class)->touch($student);

    expect((int) $student->fresh()->current_streak)->toBe(5);
});

it('bridges a missed day in the previous month', function () {
    [$student, $season] = restoreContext(500);
    $student->update(['last_login_at' => now()]);

    // 20 consecutive days ending yesterday, all of them previous month or
    // earlier, with a single missing day 10 days back that we restore.
    foreach (range(1, 20) as $daysAgo) {
        if ($daysAgo === 10) {
            continue;
        }

        markActiveDay($student, daysAgo($daysAgo), $season->id);
    }

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(10));

    expect($result['restored'])->toBeTrue()
        ->and((int) $student->fresh()->current_streak)->toBe(21);
});

it('does not count its own audit row as an active day', function () {
    [$student, $season] = restoreContext(500);

    // Active only through yesterday; today has no activity of its own beyond
    // the fixture's own 'Season Reward' row.
    foreach (range(1, 3) as $daysAgo) {
        markActiveDay($student, daysAgo($daysAgo), $season->id);
    }

    $service = app(StreakRestoreService::class);
    $before = $service->activityDates($student)->all();

    // Restoring an unrelated old day writes a "Streak Restore" history row
    // stamped now(), which must not make today look active.
    $service->restore($student, daysAgo(20));

    // The audit row is XP bookkeeping, not activity — the active-day set must
    // be byte-for-byte identical before and after.
    expect($service->activityDates($student)->all())->toBe($before);
});

it('still counts a restored day that is outside the calendar window', function () {
    [$student, $season] = restoreContext(500);
    $student->update(['last_login_at' => now()]);

    // 100 consecutive days ending yesterday, missing day 95 restored. 95 days
    // back is outside the 90-day calendar window, so the calendar will not
    // show it — but the streak must still see it.
    foreach (range(1, 100) as $daysAgo) {
        if ($daysAgo === 95) {
            continue;
        }

        markActiveDay($student, daysAgo($daysAgo), $season->id);
    }

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(95));

    expect($result['restored'])->toBeTrue()
        ->and((int) $student->fresh()->current_streak)->toBe(101)
        // The windowed list omits it, but the echoed date lets the calendar
        // mark the cell as restored instead of leaving it restorable.
        ->and($result['restored_dates'])->not->toContain(daysAgo(95))
        ->and($result['restored_date'])->toBe(daysAgo(95));
});

it('echoes no restored date when the restore is refused', function () {
    [$student] = restoreContext(10);

    $result = app(StreakRestoreService::class)->restore($student, daysAgo(1));

    expect($result['restored'])->toBeFalse()
        ->and($result['restored_date'])->toBe('');
});

it('refuses a restore older than the streak lookback window', function () {
    [$student] = restoreContext(500);

    $result = app(StreakRestoreService::class)->restore(
        $student,
        now()->subDays(StreakRestoreService::LOOKBACK_DAYS + 5)->toDateString(),
    );

    expect($result['restored'])->toBeFalse()
        ->and($result['reason'])->toBe('This day is too far back to restore.')
        ->and(StreakRestore::query()->count())->toBe(0)
        ->and(seasonalExp($student))->toBe(500.0);
});
