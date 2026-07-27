<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $seasonId = $request->integer('season_id');
        $season = $seasonId ? Season::find($seasonId) : Season::current();

        if (! $season) {
            return response()->json(['leaderboards' => [], 'selectedSeason' => null]);
        }

        $sectionLeaderboards = $this->leaderboardService->forUserSections($user, $season);

        return response()->json([
            'leaderboards' => $sectionLeaderboards,
            'selectedSeason' => [
                'id' => $season->id,
                'name' => $season->name,
            ],
        ]);
    }
}
