<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardToggleBlurController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'blur_leaderboard' => ! $user->blur_leaderboard,
        ]);

        return response()->json([
            'blur_leaderboard' => $user->fresh()->blur_leaderboard,
        ]);
    }
}
