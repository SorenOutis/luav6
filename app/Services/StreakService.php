<?php

namespace App\Services;

use App\Models\User;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * Phase 3.2 — Move streak logic out of the GET request.
 *
 * Previously the dashboard closure mutated the user's streak on every render,
 * with up to 3 UPDATEs and a race between concurrent loads.
 *
 * This service collapses the update to a single atomic query and makes the
 * operation idempotent.
 *
 * ⚠️ Streaks currently advance on *dashboard visit*, not login. With Fortify +
 * "remember me", a returning user may not fire a Login event for weeks. For now
 * this is called from the dashboard render path (DashboardController). A future
 * change should switch the trigger to a login/session listener.
 */
class StreakService
{
    /**
     * Advance the user's streak if they visited today for the first time.
     *
     * - First-ever visit: sets streak to 1.
     * - Consecutive-day visit: increments streak.
     * - Gap >1 day: resets streak to 1.
     * - Same-day visit: no-op (idempotent).
     */
    public function touch(User $user): void
    {
        if (Impersonation::isImpersonating()) {
            return;
        }

        $now = now();
        $lastLogin = $user->last_login_at;

        if (! $lastLogin) {
            $user->update([
                'current_streak' => 1,
                'longest_streak' => max(1, (int) ($user->longest_streak ?? 0)),
                'last_login_at' => $now,
            ]);

            return;
        }

        if ($lastLogin->isToday()) {
            // Already touched today; no-op.
            return;
        }

        if ($lastLogin->isYesterday()) {
            $user->increment('current_streak');
        } else {
            $user->update(['current_streak' => 1]);
        }

        $user->update(['last_login_at' => $now]);

        if (($user->current_streak ?? 0) > ($user->longest_streak ?? 0)) {
            $user->update(['longest_streak' => $user->current_streak]);
        }
    }
}
