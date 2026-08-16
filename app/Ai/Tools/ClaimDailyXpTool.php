<?php

namespace App\Ai\Tools;

use App\Services\ClaimXpService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ClaimDailyXpTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Claim the current student\'s daily login XP reward. Only succeeds once per day — report the outcome to the student.';
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

        $service = app(ClaimXpService::class);

        if (! $service->isEnabled()) {
            return json_encode([
                'claimed' => false,
                'message' => 'The daily XP claim feature is currently disabled by the administrators.',
            ]);
        }

        if (! $service->canClaim($user)) {
            return json_encode([
                'claimed' => false,
                'message' => 'Daily XP was already claimed.',
                'next_claim_at' => $service->nextClaimAt($user)?->diffForHumans(),
            ]);
        }

        $result = $service->claim($user);

        return json_encode([
            'claimed' => true,
            'amount' => $result['amount'] ?? 0,
            'streak_days' => $result['streak'] ?? null,
            'total_xp' => $result['total_xp'] ?? null,
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
