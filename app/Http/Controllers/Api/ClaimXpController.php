<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClaimXpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimXpController extends Controller
{
    public function __construct(
        protected ClaimXpService $claimXpService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->claimXpService->claim($user);

        return response()->json($result);
    }

    /**
     * Mark the daily claim prompt as shown for this session.
     *
     * The prompt is deferred for users without a section until after the
     * section-selection modal; the client calls this when the prompt actually
     * opens so it doesn't re-appear on later dashboard visits.
     */
    public function promptShown(Request $request): JsonResponse
    {
        $request->session()->put('daily_claim_prompt_shown', true);

        return response()->json(['ok' => true]);
    }
}
