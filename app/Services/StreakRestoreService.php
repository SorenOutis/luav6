<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Setting;
use App\Models\StreakRestore;
use App\Models\User;
use App\Support\GamificationSyncContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Streak Restore — spend seasonal XP to backfill a missed day.
 *
 * Restoring a date fills the calendar checkmark and repairs the current
 * streak when the restored day bridges a gap. Pricing escalates per use
 * within the calendar month and is configured in Platform Settings.
 *
 * XP guardrails: a restore is blocked (never clamped) when the student's
 * seasonal XP cannot cover the cost, so balances can never go negative.
 * Level drops from crossing a 100-XP boundary are allowed.
 */
class StreakRestoreService
{
    public function isEnabled(): bool
    {
        return (bool) Setting::get('streak_restore_enabled', true);
    }

    public function monthlyLimit(): int
    {
        return max(1, (int) Setting::get('streak_restore_monthly_limit', 3));
    }

    /**
     * @return array{cost_1: int, cost_2: int, cost_3: int}
     */
    public function defaultCosts(): array
    {
        return [
            'cost_1' => max(1, (int) Setting::get('streak_restore_cost_1', 25)),
            'cost_2' => max(1, (int) Setting::get('streak_restore_cost_2', 50)),
            'cost_3' => max(1, (int) Setting::get('streak_restore_cost_3', 100)),
        ];
    }

    /**
     * @return int[]
     */
    public function costs(): array
    {
        $defaults = $this->defaultCosts();

        return array_values($defaults);
    }

    public function costForNextRestore(User $user): int
    {
        $costs = $this->costs();
        $used = $this->usedThisMonth($user);

        return $costs[min($used, count($costs) - 1)];
    }

    public function usedThisMonth(User $user): int
    {
        return StreakRestore::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function remainingThisMonth(User $user): int
    {
        return max(0, $this->monthlyLimit() - $this->usedThisMonth($user));
    }

    /**
     * @return Collection<int, string>
     */
    public function restoredDates(User $user): Collection
    {
        return StreakRestore::query()
            ->where('user_id', $user->id)
            ->where('restored_date', '>=', now()->subDays(90)->toDateString())
            ->pluck('restored_date')
            // The immutable_date cast stringifies with a time suffix, so
            // format explicitly to match the Y-m-d activity dates.
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->values();
    }

    public function seasonalExp(User $user): float
    {
        return (float) ($user->activeSeasonProgress()?->exp ?? 0);
    }

    /**
     * Check whether a date can be restored, without mutating anything.
     *
     * @return array{ok: bool, reason: string}
     */
    public function check(User $user, string $date): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'reason' => 'Streak restore is currently disabled.'];
        }

        if ($date >= now()->toDateString()) {
            return ['ok' => false, 'reason' => 'Only past days can be restored.'];
        }

        if ($this->remainingThisMonth($user) <= 0) {
            return ['ok' => false, 'reason' => 'No restores left this month.'];
        }

        $alreadyRestored = StreakRestore::query()
            ->where('user_id', $user->id)
            ->where('restored_date', $date)
            ->exists();

        if ($alreadyRestored) {
            return ['ok' => false, 'reason' => 'This day has already been restored.'];
        }

        $alreadyActive = DB::table('gamification_histories')
            ->where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->exists();

        if ($alreadyActive) {
            return ['ok' => false, 'reason' => 'This day is already active.'];
        }

        $cost = $this->costForNextRestore($user);

        if ($this->seasonalExp($user->fresh() ?? $user) < $cost) {
            return ['ok' => false, 'reason' => 'Not enough XP for this restore.'];
        }

        return ['ok' => true, 'reason' => ''];
    }

    /**
     * @return array{restored: bool, reason: string, cost: int, remaining: int, total_xp: float, current_streak: int, restored_dates: array<int, string>}
     */
    public function restore(User $user, string $date): array
    {
        if (! $this->isEnabled()) {
            return $this->notRestored($user, 'Streak restore is currently disabled.');
        }

        return DB::transaction(function () use ($user, $date): array {
            /** @var User $restoringUser */
            $restoringUser = User::query()->findOrFail($user->id);

            $check = $this->check($restoringUser, $date);

            if (! $check['ok']) {
                return $this->notRestored($restoringUser, $check['reason']);
            }

            $cost = $this->costForNextRestore($restoringUser);
            $sequence = $this->usedThisMonth($restoringUser) + 1;
            $season = Season::current();
            $timestamp = now();

            // Lock the season ledger row so concurrent restores cannot
            // overdraw the balance past zero.
            $progress = $restoringUser->seasonProgress()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['season_id' => $season?->id],
                    ['exp' => 0, 'level' => 1, 'points' => 0],
                );

            if ((float) $progress->exp < $cost) {
                return $this->notRestored($restoringUser, 'Not enough XP for this restore.');
            }

            // insertOrIgnore keeps a concurrent worker from turning the
            // transaction into an aborted one on the unique-key race.
            $inserted = DB::table('streak_restores')->insertOrIgnore([
                'user_id' => $restoringUser->id,
                'season_id' => $season?->id,
                'restored_date' => $date,
                'cost_xp' => $cost,
                'sequence_in_month' => $sequence,
                'restored_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($inserted !== 1) {
                return $this->notRestored($restoringUser, 'This day has already been restored.');
            }

            app(GamificationSyncContext::class)->withoutAutomaticHistory(function () use ($progress, $cost): void {
                $progress->decrement('exp', $cost);
                // decrement() is atomic but bypasses the saving hook; save
                // once more so the derived level follows the new XP total.
                $progress->save();
            });

            // Negative amounts are audit-visible in XP history; the XP
            // notification service ignores non-positive amounts.
            $restoringUser->recordGamificationHistory(
                -$cost,
                0,
                'Streak Restore',
                "Restored streak for {$date} (-{$cost} XP)",
                null,
                $season?->id,
            );

            $this->repairStreak($restoringUser);

            $fresh = $restoringUser->fresh();

            return [
                'restored' => true,
                'reason' => '',
                'cost' => $cost,
                'remaining' => $this->remainingThisMonth($fresh),
                'total_xp' => (float) ($fresh->activeSeasonProgress()?->exp ?? 0),
                'current_streak' => (int) ($fresh->current_streak ?? 0),
                'restored_dates' => $this->restoredDates($fresh)->all(),
            ];
        }, 3);
    }

    /**
     * Recompute the current streak from consecutive active days ending
     * today (or yesterday when today is not active yet). Backfilling an
     * isolated old date fills the calendar without inflating the streak;
     * bridging a gap repairs it.
     */
    public function repairStreak(User $user): void
    {
        $active = DB::table('gamification_histories')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(370))
            ->selectRaw('DATE(created_at) as d')
            ->distinct()
            ->pluck('d')
            ->map(fn ($d) => (string) $d)
            ->merge($this->restoredDates($user))
            ->unique()
            ->flip();

        if ($user->last_login_at && $user->last_login_at->isToday()) {
            $active[now()->toDateString()] = true;
        }

        $cursor = now()->startOfDay();
        if (! isset($active[$cursor->toDateString()])) {
            // Dates are CarbonImmutable app-wide (see AppServiceProvider),
            // so every step must reassign — bare subDay() is a no-op.
            $cursor = $cursor->subDay();
        }

        $streak = 0;
        while (isset($active[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        $user->forceFill([
            'current_streak' => $streak,
            'longest_streak' => max($streak, (int) ($user->longest_streak ?? 0)),
        ])->save();
    }

    /**
     * @return array{restored: false, reason: string, cost: 0, remaining: int, total_xp: float, current_streak: int, restored_dates: array<int, string>}
     */
    private function notRestored(User $user, string $reason): array
    {
        $fresh = $user->fresh() ?? $user;

        return [
            'restored' => false,
            'reason' => $reason,
            'cost' => 0,
            'remaining' => $this->remainingThisMonth($fresh),
            'total_xp' => (float) ($fresh->activeSeasonProgress()?->exp ?? 0),
            'current_streak' => (int) ($fresh->current_streak ?? 0),
            'restored_dates' => $this->restoredDates($fresh)->all(),
        ];
    }
}
