<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $currentSeason = Season::current();

        $availableSeasonModels = $this->leaderboardService->availableSeasons($user);

        $initialSeason = $availableSeasonModels->first() ?? $currentSeason;

        return inertia('Leaderboard', [
            'sectionLeaderboards' => $this->leaderboardService->forViewer($user, $initialSeason),
            'activeSeasonName' => $initialSeason?->name ?? $currentSeason?->name,
            'availableSeasons' => $availableSeasonModels
                ->map(fn ($season) => [
                    'id' => $season->id,
                    'name' => $season->name,
                ])->values()->all(),
        ]);
    }
}
