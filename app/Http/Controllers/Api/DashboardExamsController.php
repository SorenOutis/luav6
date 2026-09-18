<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Services\UpcomingExamsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardExamsController extends Controller
{
    public function __construct(
        protected UpcomingExamsService $upcomingExamsService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $seasonId = $request->integer('season_id');
        $season = $seasonId ? Season::find($seasonId) : Season::current();

        if (! $season) {
            return response()->json(['exams' => []]);
        }

        $sectionIds = $user->sections()
            ->wherePivot('season_id', $season->id)
            ->pluck('sections.id');

        $exams = $this->upcomingExamsService->forUser($user, $sectionIds);

        return response()->json(['exams' => $exams]);
    }
}
