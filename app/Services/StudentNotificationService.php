<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Season;
use App\Models\User;
use App\Notifications\StudentActivityNotification;

class StudentNotificationService
{
    public function sendXpEarned(User $user, float $amountXp, string $reason, ?string $description = null): void
    {
        if ($amountXp <= 0) {
            return;
        }

        $user->notify(new StudentActivityNotification([
            'type' => 'xp',
            'icon' => 'zap',
            'title' => '+'.rtrim(rtrim(number_format($amountXp, 2, '.', ''), '0'), '.').' XP earned',
            'message' => $description ?: $reason,
            'meta' => $reason,
            'href' => '/dashboard',
        ]));
    }

    public function sendLevelUp(User $user, int $level): void
    {
        if ($level < 2) {
            return;
        }

        $user->notify(new StudentActivityNotification([
            'type' => 'level',
            'icon' => 'trending-up',
            'title' => 'Level up!',
            'message' => "You reached Level {$level}.",
            'meta' => 'Season progress milestone',
            'href' => '/dashboard',
        ]));
    }

    public function sendBadgeUnlocked(User $user, Badge $badge, ?int $seasonId = null): void
    {
        $seasonName = $seasonId ? Season::query()->find($seasonId)?->name : null;

        $user->notify(new StudentActivityNotification([
            'type' => 'badge',
            'icon' => 'shield',
            'title' => 'Badge unlocked',
            'message' => $badge->name,
            'meta' => $seasonName ? "Earned in {$seasonName}" : 'Lifetime badge',
            'image' => $badge->image_path ? asset('storage/'.$badge->image_path) : null,
            'href' => "/u/{$user->id}",
        ]));
    }
}
