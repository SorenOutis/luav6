<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UserInfoTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current user\'s profile information, including their name, XP, level, and streak.';
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
            'name' => $user->name,
            'email' => $user->email,
            'current_streak' => $user->current_streak,
            'total_xp' => $progress?->exp ?? 0,
            'level' => $progress?->level ?? 1,
            'points' => $progress?->points ?? 0,
            'joined_at' => $user->created_at->format('M Y'),
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
