<?php

namespace App\Services;

use App\Models\User;

/**
 * Streak bookkeeping.
 *
 * This service only records *that* the student showed up today; the day-count
 * itself is delegated to {@see StreakRestoreService::repairStreak()}, which is
 * the single writer of `current_streak`.
 *
 * ⚠️ Streaks currently advance on *dashboard visit*, not login. With Fortify +
 * "remember me", a returning user may not fire a Login event for weeks. For now
 * this is called from the dashboard render path (DashboardController). A future
 * change should switch the trigger to a login/session listener.
 *
 * ⚠️ Do not reintroduce day-delta arithmetic here (increment on yesterday,
 * reset on a gap). That algorithm only looked at `users.last_login_at`, so it
 * disagreed with the activity-based recount and silently reset a restored
 * streak back to 1 on the next dashboard load. Streaks have to be derived from
 * activity history or not at all.
 */
class StreakService
{
    public function __construct(
        protected StreakRestoreService $streakRestoreService,
    ) {}

    /**
     * Stamp today's visit and recount the streak from activity history.
     *
     * - Already stamped today: no-op (idempotent).
     * - Otherwise: records the visit, then recomputes the consecutive run of
     *   active days ending today (or yesterday, if today has no activity yet).
     */
    public function touch(User $user): void
    {
        if (session()->has('impersonated_by')) {
            return;
        }

        if ($user->last_login_at && $user->last_login_at->isToday()) {
            return;
        }

        // Stamp first: repairStreak() reads last_login_at to decide whether
        // today counts as active.
        $user->forceFill(['last_login_at' => now()])->save();

        $this->streakRestoreService->repairStreak($user);
    }
}
