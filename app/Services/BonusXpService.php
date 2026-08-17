<?php

namespace App\Services;

use App\Models\BonusXpClaim;
use App\Models\Season;
use App\Models\SectionProgress;
use App\Models\Setting;
use App\Models\User;
use App\Support\GamificationSyncContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Bonus XP Claim — second daily reward shown inside the Level → XP History modal.
 *
 * Independent from the streak-scaled Daily Claim. Flat amount configured in
 * Platform Settings → Daily XP Claim → Bonus XP. One ledger row per user/calendar
 * day, mirrored to every enrolled section but global/season XP incremented once.
 */
class BonusXpService
{
    public function isEnabled(): bool
    {
        return (bool) Setting::get('daily_claim_bonus_enabled', false);
    }

    public function bonusXp(): int
    {
        return max(1, (int) Setting::get('daily_claim_bonus_xp', 5));
    }

    public function canClaim(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return ! BonusXpClaim::query()
            ->where('user_id', $user->id)
            ->where('claim_date', now()->toDateString())
            ->exists();
    }

    public function claimAmount(User $user): int
    {
        return $this->bonusXp();
    }

    public function nextClaimAt(User $user): ?CarbonInterface
    {
        $latest = BonusXpClaim::query()
            ->where('user_id', $user->id)
            ->latest('claim_date')
            ->first();

        if (! $latest || ! $latest->claimed_at) {
            return null;
        }

        return $latest->claimed_at->copy()->addDay()->startOfDay();
    }

    public function lastClaimedAt(User $user): ?CarbonInterface
    {
        $latest = BonusXpClaim::query()
            ->where('user_id', $user->id)
            ->latest('claim_date')
            ->first();

        return $latest?->claimed_at;
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

            $inserted = DB::table('bonus_xp_claims')->insertOrIgnore([
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
                'Bonus Claim',
                'Bonus daily claim',
                null,
                $season?->id,
            );

            $fresh = $claimingUser->fresh();

            return [
                'claimed' => true,
                'amount' => $amount,
                'total_xp' => (float) ($fresh->exp ?? 0),
                'streak' => $streak,
            ];
        }, 3);
    }

    private function mirrorIntoSections(User $user, int $amount): void
    {
        $context = app(GamificationSyncContext::class);

        foreach ($user->sections()->get(['sections.id']) as $section) {
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
