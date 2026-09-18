<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BonusXpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusClaimController extends Controller
{
    public function __construct(
        protected BonusXpService $bonusXpService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->bonusXpService->claim($user);

        return response()->json($result);
    }
}
