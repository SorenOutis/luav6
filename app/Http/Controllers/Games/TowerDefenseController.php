<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\TowerDefense\TdLevel;
use App\Models\TowerDefense\TdPlayerProgress;
use App\Models\TowerDefense\TdRun;
use App\Models\TowerDefense\TdTower;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TowerDefenseController extends Controller
{
    /**
     * Level select screen.
     */
    public function index()
    {
        $userId = auth()->id();

        $levels = TdLevel::with(['map:id,name,slug', 'difficulty:id,name,slug'])
            ->where('is_published', true)
            ->orderBy('order')
            ->get()
            ->map(function (TdLevel $level) use ($userId) {
                $progress = TdPlayerProgress::where('user_id', $userId)
                    ->where('td_level_id', $level->id)
                    ->first();

                return [
                    'id' => $level->id,
                    'slug' => $level->slug,
                    'name' => $level->name,
                    'description' => $level->description,
                    'order' => $level->order,
                    'map' => ['name' => $level->map->name],
                    'difficulty' => ['name' => $level->difficulty->name, 'slug' => $level->difficulty->slug],
                    'waves_count' => $level->waves()->count(),
                    'progress' => $progress ? [
                        'best_score' => $progress->best_score,
                        'best_waves' => $progress->best_waves,
                        'stars' => $progress->stars,
                        'plays' => $progress->plays,
                        'wins' => $progress->wins,
                    ] : null,
                ];
            });

        return inertia('Games/TowerDefense/Index', [
            'levels' => $levels,
        ]);
    }

    /**
     * Play screen — returns the full level payload for the engine.
     */
    public function play(string $slug)
    {
        $level = TdLevel::with([
            'map',
            'difficulty',
            'waves.spawns.enemy',
        ])->where('slug', $slug)->firstOrFail();

        if (! $level->is_published && ! auth()->user()?->is_admin) {
            abort(404);
        }

        $towers = $level->allowedTowers()->map(fn (TdTower $t) => [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->name,
            'cost' => $t->cost,
            'damage' => $t->damage,
            'range' => $t->range,
            'fire_rate' => $t->fire_rate,
            'projectile_type' => $t->projectile_type,
            'splash_radius' => $t->splash_radius,
            'projectile_speed' => $t->projectile_speed,
            'color' => $t->color,
            'upgrades' => $t->upgrades ?? [],
        ]);

        $diff = $level->difficulty;

        $payload = [
            'id' => $level->id,
            'slug' => $level->slug,
            'name' => $level->name,
            'description' => $level->description,
            'reward_score' => $level->reward_score,
            'starting_gold' => $level->starting_gold_override ?? $diff->starting_gold,
            'starting_lives' => $level->starting_lives_override ?? $diff->starting_lives,
            'difficulty' => [
                'name' => $diff->name,
                'slug' => $diff->slug,
                'enemy_hp_mult' => $diff->enemy_hp_mult,
                'enemy_speed_mult' => $diff->enemy_speed_mult,
                'gold_mult' => $diff->gold_mult,
                'score_mult' => $diff->score_mult,
            ],
            'map' => [
                'name' => $level->map->name,
                'grid_width' => $level->map->grid_width,
                'grid_height' => $level->map->grid_height,
                'tile_size' => $level->map->tile_size,
                'path_waypoints' => $level->map->path_waypoints,
                'background_color' => $level->map->background_color,
            ],
            'towers' => $towers->values(),
            'waves' => $level->waves->map(fn ($w) => [
                'order' => $w->order,
                'spawn_delay_ms' => $w->spawn_delay_ms,
                'bonus_gold' => $w->bonus_gold,
                'spawns' => $w->spawns->map(fn ($s) => [
                    'enemy' => [
                        'id' => $s->enemy->id,
                        'slug' => $s->enemy->slug,
                        'name' => $s->enemy->name,
                        'hp' => $s->enemy->hp,
                        'speed' => $s->enemy->speed,
                        'armor' => $s->enemy->armor,
                        'damage' => $s->enemy->damage,
                        'bounty' => $s->enemy->bounty,
                        'score' => $s->enemy->score,
                        'color' => $s->enemy->color,
                        'radius' => $s->enemy->radius,
                    ],
                    'count' => $s->count,
                    'interval_ms' => $s->interval_ms,
                    'offset_ms' => $s->offset_ms,
                ])->values(),
            ])->values(),
        ];

        return inertia('Games/TowerDefense/Playfield', [
            'level' => $payload,
        ]);
    }

    /**
     * Start a run. Returns run_id + seed.
     */
    public function startRun(Request $request)
    {
        $data = $request->validate([
            'level_id' => 'required|integer|exists:td_levels,id',
        ]);

        $run = TdRun::create([
            'user_id' => auth()->id(),
            'td_level_id' => $data['level_id'],
            'status' => 'in_progress',
            'seed' => Str::random(16),
            'started_at' => now(),
        ]);

        return response()->json([
            'run_id' => $run->id,
            'seed' => $run->seed,
        ]);
    }

    /**
     * Finish a run. Performs basic sanity checks and updates player progress.
     */
    public function finishRun(Request $request, TdRun $run)
    {
        abort_unless($run->user_id === auth()->id(), 403);
        abort_if($run->status !== 'in_progress', 409, 'Run already finalized');

        $data = $request->validate([
            'status' => 'required|in:win,lose,abandoned',
            'waves_completed' => 'required|integer|min:0',
            'score' => 'required|integer|min:0',
            'gold_spent' => 'required|integer|min:0',
            'lives_remaining' => 'required|integer|min:0',
            'duration_ms' => 'required|integer|min:0',
        ]);

        // ---- Sanity checks (anti-cheat) ----
        $level = TdLevel::with('waves.spawns.enemy', 'difficulty')->findOrFail($run->td_level_id);
        $totalWaves = $level->waves->count();
        $data['waves_completed'] = min($data['waves_completed'], $totalWaves);

        // Compute max theoretical score: sum all enemy scores * difficulty.score_mult + reward_score.
        $maxEnemyScore = 0;
        foreach ($level->waves as $wave) {
            foreach ($wave->spawns as $spawn) {
                $maxEnemyScore += $spawn->count * $spawn->enemy->score;
            }
        }
        $mult = (float) $level->difficulty->score_mult;
        $maxPossible = (int) ceil($maxEnemyScore * $mult) + $level->reward_score + 1000; // headroom
        if ($data['score'] > $maxPossible) {
            $data['score'] = $maxPossible;
        }

        // Duration floor: at least 2s per wave completed (prevents instant-win forging).
        $minDuration = max(2000, $data['waves_completed'] * 2000);
        if ($data['duration_ms'] < $minDuration && $data['status'] === 'win') {
            return response()->json(['error' => 'Run duration below minimum'], 422);
        }

        $run->update([
            'status' => $data['status'],
            'waves_completed' => $data['waves_completed'],
            'score' => $data['score'],
            'gold_spent' => $data['gold_spent'],
            'lives_remaining' => $data['lives_remaining'],
            'duration_ms' => $data['duration_ms'],
            'finished_at' => now(),
        ]);

        // ---- Player progress ----
        $progress = TdPlayerProgress::firstOrCreate(
            ['user_id' => $run->user_id, 'td_level_id' => $run->td_level_id],
            ['best_score' => 0, 'best_waves' => 0, 'stars' => 0, 'plays' => 0, 'wins' => 0]
        );
        $progress->plays++;
        if ($data['status'] === 'win') {
            $progress->wins++;
            $startingLives = $level->starting_lives_override ?? $level->difficulty->starting_lives;
            $livesPct = $startingLives > 0 ? $data['lives_remaining'] / $startingLives : 0;
            $stars = 1;
            if ($livesPct >= 0.5) {
                $stars = 2;
            }
            if ($livesPct >= 0.9) {
                $stars = 3;
            }
            $progress->stars = max($progress->stars, $stars);
        }
        $progress->best_score = max($progress->best_score, $data['score']);
        $progress->best_waves = max($progress->best_waves, $data['waves_completed']);
        $progress->save();

        return response()->json([
            'ok' => true,
            'run' => $run->only(['id', 'status', 'score', 'waves_completed', 'duration_ms']),
            'progress' => $progress->only(['best_score', 'best_waves', 'stars', 'plays', 'wins']),
        ]);
    }

    /**
     * Leaderboard for a level.
     */
    public function leaderboard(string $slug)
    {
        $level = TdLevel::where('slug', $slug)->firstOrFail();

        $rows = TdRun::with('user:id,name,avatar')
            ->where('td_level_id', $level->id)
            ->where('status', 'win')
            ->orderByDesc('score')
            ->orderBy('duration_ms')
            ->limit(50)
            ->get()
            ->map(fn (TdRun $r) => [
                'id' => $r->id,
                'user' => ['id' => $r->user->id, 'name' => $r->user->name, 'avatar' => $r->user->avatar],
                'score' => $r->score,
                'waves_completed' => $r->waves_completed,
                'duration_ms' => $r->duration_ms,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['leaderboard' => $rows]);
    }
}
