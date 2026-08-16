<?php

namespace App\Services;

use App\Models\GamificationHistory;
use App\Models\SectionProgress;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Daily Claim XP — Streak-scaled rewards for checking in each day.
 *
 * Formula: base + floor(current_streak / 5), capped at base + 4.
 * The base XP is configurable via the `daily_claim_base_xp` platform
 * setting (default 1), and the whole feature can be turned off with
 * `daily_claim_enabled`. With the default base of 1:
 *   Streak 1–4  → 1 XP
 *   Streak 5–9  → 2 XP
 *   Streak 10–14 → 3 XP
 *   Streak 15–19 → 4 XP
 *   Streak 20+   → 5 XP
 */
class ClaimXpService
{
    /** Max streak bonus on top of the base XP (streak 20+). */
    private const MAX_STREAK_BONUS = 4;

    /**
     * Is the daily claim feature enabled? (Platform Settings)
     */
    public function isEnabled(): bool
    {
        return (bool) Setting::get('daily_claim_enabled', true);
    }

    /**
     * Configurable base XP per claim (Platform Settings, default 1).
     */
    public function baseXp(): int
    {
        return max(1, (int) Setting::get('daily_claim_base_xp', 1));
    }

    /**
     * Can the user claim today?
     */
    public function canClaim(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $user->last_claimed_at) {
            return true;
        }

        return ! $user->last_claimed_at->isToday();
    }

    /**
     * How much XP would the user get right now?
     */
    public function claimAmount(User $user): int
    {
        $streak = max(0, (int) ($user->current_streak ?? 0));

        $bonus = min(self::MAX_STREAK_BONUS, (int) floor($streak / 5));

        return $this->baseXp() + $bonus;
    }

    /**
     * When can the user claim next? (Midnight of the day after last claim)
     */
    public function nextClaimAt(User $user): ?CarbonInterface
    {
        if (! $user->last_claimed_at) {
            return null;
        }

        // Next claim available at midnight (start of next day)
        // Use CarbonInterface so both Carbon and CarbonImmutable are accepted.
        return $user->last_claimed_at->copy()->addDay()->startOfDay();
    }

    /**
     * Execute the claim — awards XP to the user's section progress
     * and records a GamificationHistory entry.
     *
     * @return array{claimed: bool, amount: int, total_xp: float, streak: int}
     */
    public function claim(User $user): array
    {
        if (! $this->canClaim($user)) {
            return [
                'claimed' => false,
                'amount' => 0,
                'total_xp' => (float) ($user->exp ?? 0),
                'streak' => (int) ($user->current_streak ?? 0),
            ];
        }

        $amount = $this->claimAmount($user);
        $streak = (int) ($user->current_streak ?? 0);

        DB::transaction(function () use ($user, $amount) {
            // Record gamification history
            $user->recordGamificationHistory(
                $amount,
                0,
                'Daily Claim',
                'Daily login claim bonus',
                null, // section_id — will be inferred from active sections
                null, // season_id — will use current season
            );

            // Update XP in section progress. The SectionProgress::booted()
            // updated hook syncs to $user->exp and $seasonProgress->exp, which
            // we want, but it also calls recordGamificationHistory() with reason
            // 'Admin Adjustment' — creating a duplicate entry since we already
            // record history ourselves above.
            //
            // Setting $isSyncing = true suppresses the duplicate history while
            // preserving the user/season exp sync.
            $sections = $user->sections;
            foreach ($sections as $section) {
                $progress = $user->activeSectionProgress($section->id);
                if ($progress) {
                    $wasAlreadySyncing = SectionProgress::$isSyncing;
                    SectionProgress::$isSyncing = true;
                    $progress->increment('exp', $amount);
                    $progress->save();
                    SectionProgress::$isSyncing = $wasAlreadySyncing;
                }
            }

            // No sections — update user exp directly. The SectionProgress
            // updated hook (which normally syncs exp into $user->exp and the
            // active season progress) never fires here, so keep both in sync
            // manually. $isSyncing suppresses the SeasonProgress updated hook
            // so it doesn't re-increment the user or duplicate history.
            if ($sections->isEmpty()) {
                $user->increment('exp', $amount);
                $user->level = floor($user->exp / 100) + 1;
                $user->save();

                $seasonProgress = $user->activeSeasonProgress();
                if ($seasonProgress) {
                    $wasAlreadySyncing = SectionProgress::$isSyncing;
                    SectionProgress::$isSyncing = true;
                    $seasonProgress->increment('exp', $amount);
                    $seasonProgress->save();
                    SectionProgress::$isSyncing = $wasAlreadySyncing;
                }
            }

            // Mark as claimed
            $user->update(['last_claimed_at' => now()]);
        });

        /** @var User $fresh */
        $fresh = $user->fresh();

        return [
            'claimed' => true,
            'amount' => $amount,
            'total_xp' => (float) ($fresh->exp ?? 0),
            'streak' => $streak,
        ];
    }
}
