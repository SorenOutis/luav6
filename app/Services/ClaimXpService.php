<?php

namespace App\Services;

use App\Models\DailyXpClaim;
use App\Models\Season;
use App\Models\SectionProgress;
use App\Models\Setting;
use App\Models\User;
use App\Support\GamificationSyncContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Daily Claim XP — streak-scaled rewards for checking in each day.
 *
 * One durable ledger row is created per user/calendar day. The reward is
 * mirrored to every enrolled section so section leaderboards remain fair, but
 * user and active-season totals are increased exactly once.
 */
class ClaimXpService
{
    /** Max streak bonus on top of the base XP (streak 20+). */
    private const MAX_STREAK_BONUS = 4;

    public function isEnabled(): bool
    {
        return (bool) Setting::get('daily_claim_enabled', true);
    }

    public function baseXp(): int
    {
        return max(1, (int) Setting::get('daily_claim_base_xp', 1));
    }

    public function canClaim(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        // Preserve the pre-ledger guard for users who claimed before this table
        // was introduced. New claims are protected by both fields.
        if ($user->last_claimed_at?->isToday()) {
            return false;
        }

        return ! DailyXpClaim::query()
            ->where('user_id', $user->id)
            ->where('claim_date', now()->toDateString())
            ->exists();
    }

    public function claimAmount(User $user): int
    {
        $streak = max(0, (int) ($user->current_streak ?? 0));
        $bonus = min(self::MAX_STREAK_BONUS, (int) floor($streak / 5));

        return $this->baseXp() + $bonus;
    }

    public function nextClaimAt(User $user): ?CarbonInterface
    {
        if (! $user->last_claimed_at) {
            return null;
        }

        return $user->last_claimed_at->copy()->addDay()->startOfDay();
    }

    /**
     * @return array{claimed: bool, amount: int, total_xp: float, streak: int}
     */
    public function claim(User $user): array
    {
        if (! $this->isEnabled()) {
            return $this->notClaimed($user);
        }

        $claimDate = now()->toDateString();

        return DB::transaction(function () use ($user, $claimDate): array {
            /** @var User $claimingUser */
            $claimingUser = User::query()->findOrFail($user->id);

            if (! $this->canClaim($claimingUser)) {
                return $this->notClaimed($claimingUser);
            }

            $amount = $this->claimAmount($claimingUser);
            $streak = max(0, (int) ($claimingUser->current_streak ?? 0));
            $season = Season::current();
            $timestamp = now();

            // insertOrIgnore avoids turning the PostgreSQL transaction into an
            // aborted transaction if another worker wins the unique-key race.
            $inserted = DB::table('daily_xp_claims')->insertOrIgnore([
                'user_id' => $claimingUser->id,
                'season_id' => $season?->id,
                'claim_date' => $claimDate,
                'amount' => $amount,
                'streak' => $streak,
                'claimed_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($inserted !== 1) {
                return $this->notClaimed($claimingUser);
            }

            $this->mirrorIntoSections($claimingUser, $amount);
            $this->awardGlobalAndSeasonProgress($claimingUser, $season, $amount);

            $claimingUser->recordGamificationHistory(
                $amount,
                0,
                'Daily Claim',
                'Daily login claim bonus',
                null,
                $season?->id,
            );

            $claimingUser->forceFill(['last_claimed_at' => $timestamp])->save();
            $fresh = $claimingUser->fresh();

            return [
                'claimed' => true,
                'amount' => $amount,
                'total_xp' => (float) ($fresh->exp ?? 0),
                'streak' => $streak,
            ];
        }, 3);
    }

    /**
     * Mirror daily activity into each section without re-applying the same XP
     * to the user's global and season aggregates for every enrollment.
     */
    private function mirrorIntoSections(User $user, int $amount): void
    {
        $context = app(GamificationSyncContext::class);

        foreach ($user->sections()->get(['sections.id']) as $section) {
            // Keep delayed section-enrollment rewards intact: initialization is
            // allowed to propagate; only the daily-claim increment is mirrored.
            $progress = $user->activeSectionProgress($section->id);

            $context->withoutSectionPropagation(function () use ($progress, $amount): void {
                $progress->exp = (float) $progress->exp + $amount;
                $progress->save();
            });
        }
    }

    private function awardGlobalAndSeasonProgress(User $user, ?Season $season, int $amount): void
    {
        if ($season) {
            $progress = $user->seasonProgress()->firstOrCreate(
                ['season_id' => $season->id],
                ['exp' => 0, 'level' => 1, 'points' => 0],
            );

            app(GamificationSyncContext::class)->withoutAutomaticHistory(function () use ($progress, $amount): void {
                $progress->increment('exp', $amount);
                $progress->save();
            });

            return;
        }

        $user->increment('exp', $amount);
        $user->level = SectionProgress::levelFromExp($user->exp);
        $user->save();
    }

    /**
     * @return array{claimed: false, amount: 0, total_xp: float, streak: int}
     */
    private function notClaimed(User $user): array
    {
        return [
            'claimed' => false,
            'amount' => 0,
            'total_xp' => (float) ($user->fresh()->exp ?? 0),
            'streak' => (int) ($user->current_streak ?? 0),
        ];
    }
}
