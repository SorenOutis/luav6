<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
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

        $availableSeasonModels = $this->availableSeasons($user);

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

    /**
     * Seasons selectable on the leaderboard.
     *
     * Students pick from the seasons their own enrollments point to. Super
     * admins pick from every season with enrollments — the Section and Season
     * workspace global scopes confine that to the inspected workspace while
     * inspection is active, and leave it platform-wide otherwise.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Season>
     */
    protected function availableSeasons(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Season::query()
                ->whereIn('id', Section::query()
                    ->join('section_user', 'section_user.section_id', '=', 'sections.id')
                    ->whereNotNull('section_user.season_id')
                    ->distinct()
                    ->select('section_user.season_id'))
                ->orderBy('start_date', 'desc')
                ->get();
        }

        return Season::query()
            ->whereIn('id', $user->sections()
                ->wherePivotNotNull('season_id')
                ->pluck('section_user.season_id')
                ->unique()
            )
            ->orderBy('start_date', 'desc')
            ->get();
    }
}
