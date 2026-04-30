<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Season;
use App\Models\User;

class BadgeAwardService
{
    public function awardEligibleBadges(User $user, ?int $level = null, ?int $seasonId = null): void
    {
        $level ??= (int) ($user->activeSeasonProgress()?->level ?? $user->level ?? 1);
        $seasonId ??= Season::current()?->id;

        if ($level < 1) {
            return;
        }

        $eligibleBadges = Badge::query()
            ->whereNotNull('required_level')
            ->where('required_level', '<=', $level)
            ->orderBy('required_level')
            ->get(['id']);

        if ($eligibleBadges->isEmpty()) {
            return;
        }

        $earnedBadges = $user->badges()
            ->pluck('badges.id')
            ->map(fn ($badgeId) => (int) $badgeId)
            ->all();

        $earnedBadgeIds = array_flip($earnedBadges);

        foreach ($eligibleBadges as $badge) {
            if (isset($earnedBadgeIds[$badge->id])) {
                continue;
            }

            $user->badges()->attach($badge->id, [
                'season_id' => $seasonId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
