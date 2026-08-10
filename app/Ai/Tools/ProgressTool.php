<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProgressTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current student\'s gamification progress — LSI system level, XP, points, streak, badges, and enrolled sections.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();

        if (! $user) {
            return 'No user is currently authenticated.';
        }

        $progress = $user->activeSeasonProgress();

        return json_encode([
            'system_level' => $progress?->level ?? 1,
            'total_xp' => $progress?->exp ?? 0,
            'points' => $progress?->points ?? 0,
            'streak_days' => (int) ($user->current_streak ?? 0),
            'badges' => $user->badges()->latest('badge_user.created_at')->limit(10)->pluck('badges.name'),
            'sections' => $user->sections()->pluck('sections.name'),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
