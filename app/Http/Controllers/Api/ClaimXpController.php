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
}
