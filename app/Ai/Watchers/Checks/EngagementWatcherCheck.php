<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\User;
use App\Models\Workspace;

/**
 * Engagement Manager: watches momentum, not individual students.
 *
 * Compares distinct members active in the last window against the window
 * before it and warns when activity halves.
 */
class EngagementWatcherCheck implements BotWatcherCheck
{
    public const WINDOW_DAYS = 7;

    public const DROP_RATIO = 0.5;

    public const MIN_PREVIOUS_ACTIVE = 3;

    public function domain(): string
    {
        return 'engagement';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];

        $base = function () use ($workspace) {
            return User::query()
                ->where(function ($query) use ($workspace) {
                    $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspace->id))
                        ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspace->id))
                        ->orWhere('current_workspace_id', $workspace->id);
                })
                ->where('is_admin', false)
                ->whereNotNull('last_login_at');
        };

        $now = now();

        $currentActive = $base()
            ->where('last_login_at', '>=', $now->copy()->subDays(self::WINDOW_DAYS))
            ->count();

        $previousActive = $base()
            ->where('last_login_at', '>=', $now->copy()->subDays(self::WINDOW_DAYS * 2))
            ->where('last_login_at', '<', $now->copy()->subDays(self::WINDOW_DAYS))
            ->count();

        if (
            $previousActive >= self::MIN_PREVIOUS_ACTIVE
            && $currentActive < $previousActive * self::DROP_RATIO
        ) {
            $dropRatio = $previousActive > 0 ? round(1 - ($currentActive / $previousActive), 2) : null;

            $findings[] = new BotFinding(
                fingerprint: 'login_activity_drop',
                severity: $currentActive === 0 ? 'critical' : 'warning',
                title: 'Student activity is cooling off',
                summary: sprintf(
                    '%d students were active in the last %d days versus %d in the %d days before that.',
                    $currentActive,
                    self::WINDOW_DAYS,
                    $previousActive,
                    self::WINDOW_DAYS,
                ),
                evidence: [
                    'Active last '.self::WINDOW_DAYS.'d: '.$currentActive,
                    'Active window before: '.$previousActive,
                    'Drop ratio: '.($dropRatio === null ? 'n/a' : $dropRatio),
                ],
                suggestedAction: 'Plan a light re-engagement beat, such as a leaderboard nudge or a fun activity; anything sent to students needs approval.',
            );
        }

        return $findings;
    }
}
