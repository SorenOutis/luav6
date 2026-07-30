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

        $availableSeasonModels = Season::query()
            ->whereIn('id', $user->sections()
                ->wherePivotNotNull('season_id')
                ->pluck('section_user.season_id')
                ->unique()
            )
            ->orderBy('start_date', 'desc')
            ->get();

        $initialSeason = $availableSeasonModels->first() ?? $currentSeason;

        return inertia('Leaderboard', [
            'sectionLeaderboards' => $this->leaderboardService->forUserSections($user, $initialSeason),
            'activeSeasonName' => $initialSeason?->name ?? $currentSeason?->name,
            'availableSeasons' => $availableSeasonModels
                ->map(fn ($season) => [
                    'id' => $season->id,
                    'name' => $season->name,
                ])->values()->all(),
        ]);
    }
}
