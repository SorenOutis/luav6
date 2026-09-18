<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\TowerDefense\TdLevel;
use App\Models\TowerDefense\TdPlayerProgress;
use App\Models\TowerDefense\TdRun;

class GamesController extends Controller
{
    /**
     * Games hub — lists all available games.
     */
    public function index()
    {
        $userId = auth()->id();

        // Tower Defense summary
        $tdLevelsTotal = TdLevel::where('is_published', true)->count();
        $tdProgress = TdPlayerProgress::where('user_id', $userId)->get();
        $tdStars = (int) $tdProgress->sum('stars');
        $tdCleared = $tdProgress->where('wins', '>', 0)->count();
        $tdBestScore = (int) TdRun::where('user_id', $userId)->max('score');
        $tdRuns = (int) TdRun::where('user_id', $userId)->count();

        $games = [
            [
                'slug' => 'tower-defense',
                'name' => 'Tower Defense',
                'tagline' => 'Hold the line. Build towers. Survive the waves.',
                'description' => 'Deploy turrets along a fortified corridor and fend off escalating enemy waves across handcrafted levels.',
                'status' => 'live',
                'href' => '/games/tower-defense',
                'accent' => 'primary',
                'tags' => ['Strategy', 'Real-time', 'Solo'],
                'stats' => [
                    ['label' => 'Levels', 'value' => (string) $tdLevelsTotal],
                    ['label' => 'Cleared', 'value' => $tdCleared.' / '.$tdLevelsTotal],
                    ['label' => 'Stars', 'value' => $tdStars.' / '.($tdLevelsTotal * 3)],
                    ['label' => 'Best Score', 'value' => number_format($tdBestScore)],
                    ['label' => 'Total Runs', 'value' => (string) $tdRuns],
                ],
            ],
        ];

        return inertia('Games/Index', [
            'games' => $games,
        ]);
    }
}
