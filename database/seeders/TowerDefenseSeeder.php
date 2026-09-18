<?php

namespace Database\Seeders;

use App\Models\TowerDefense\TdDifficulty;
use App\Models\TowerDefense\TdEnemy;
use App\Models\TowerDefense\TdLevel;
use App\Models\TowerDefense\TdMap;
use App\Models\TowerDefense\TdTower;
use App\Models\TowerDefense\TdWave;
use App\Models\TowerDefense\TdWaveSpawn;
use Illuminate\Database\Seeder;

class TowerDefenseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------------- MAP ----------------
        // Simple S-shaped path on a 20x12 grid. Waypoints are [x,y] in grid coords.
        $map = TdMap::updateOrCreate(
            ['slug' => 'core-corridor'],
            [
                'name' => 'Core Corridor',
                'grid_width' => 20,
                'grid_height' => 12,
                'tile_size' => 48,
                'tiles' => null,
                'path_waypoints' => [
                    [0, 2], [6, 2], [6, 6], [13, 6], [13, 9], [19, 9],
                ],
                'background_color' => '#0a0a0a',
            ]
        );

        // ---------------- ENEMIES ----------------
        $grunt = TdEnemy::updateOrCreate(['slug' => 'grunt'], [
            'name' => 'Grunt',
            'hp' => 80,
            'speed' => 1.8,
            'armor' => 0,
            'damage' => 1,
            'bounty' => 8,
            'score' => 10,
            'color' => '#ef4444',
            'radius' => 14,
        ]);
        $runner = TdEnemy::updateOrCreate(['slug' => 'runner'], [
            'name' => 'Runner',
            'hp' => 50,
            'speed' => 3.2,
            'armor' => 0,
            'damage' => 1,
            'bounty' => 10,
            'score' => 15,
            'color' => '#f97316',
            'radius' => 12,
        ]);
        $brute = TdEnemy::updateOrCreate(['slug' => 'brute'], [
            'name' => 'Brute',
            'hp' => 260,
            'speed' => 1.1,
            'armor' => 2,
            'damage' => 3,
            'bounty' => 25,
            'score' => 40,
            'color' => '#a855f7',
            'radius' => 18,
        ]);

        // ---------------- TOWERS ----------------
        TdTower::updateOrCreate(['slug' => 'cannon'], [
            'name' => 'Cannon',
            'cost' => 50,
            'damage' => 18,
            'range' => 3.2,
            'fire_rate' => 1.1,
            'projectile_type' => 'bullet',
            'splash_radius' => 0,
            'projectile_speed' => 9,
            'color' => '#38bdf8',
            'upgrades' => [
                ['cost' => 60, 'damage' => 30, 'range' => 3.5, 'fire_rate' => 1.2],
                ['cost' => 120, 'damage' => 55, 'range' => 4.0, 'fire_rate' => 1.4],
            ],
        ]);
        TdTower::updateOrCreate(['slug' => 'rapid'], [
            'name' => 'Rapid',
            'cost' => 75,
            'damage' => 7,
            'range' => 2.8,
            'fire_rate' => 4.0,
            'projectile_type' => 'bullet',
            'splash_radius' => 0,
            'projectile_speed' => 12,
            'color' => '#22c55e',
            'upgrades' => [
                ['cost' => 80, 'damage' => 11, 'range' => 3.0, 'fire_rate' => 5.0],
                ['cost' => 160, 'damage' => 18, 'range' => 3.2, 'fire_rate' => 6.5],
            ],
        ]);
        TdTower::updateOrCreate(['slug' => 'mortar'], [
            'name' => 'Mortar',
            'cost' => 125,
            'damage' => 40,
            'range' => 4.0,
            'fire_rate' => 0.5,
            'projectile_type' => 'splash',
            'splash_radius' => 1.2,
            'projectile_speed' => 6,
            'color' => '#eab308',
            'upgrades' => [
                ['cost' => 140, 'damage' => 65, 'range' => 4.5, 'fire_rate' => 0.6, 'splash_radius' => 1.4],
                ['cost' => 260, 'damage' => 110, 'range' => 5.0, 'fire_rate' => 0.75, 'splash_radius' => 1.8],
            ],
        ]);

        // ---------------- DIFFICULTIES ----------------
        $easy = TdDifficulty::updateOrCreate(['slug' => 'easy'], [
            'name' => 'Easy',
            'starting_gold' => 200,
            'starting_lives' => 25,
            'enemy_hp_mult' => 0.85,
            'enemy_speed_mult' => 0.95,
            'gold_mult' => 1.1,
            'score_mult' => 0.8,
            'order' => 1,
        ]);
        $normal = TdDifficulty::updateOrCreate(['slug' => 'normal'], [
            'name' => 'Normal',
            'starting_gold' => 150,
            'starting_lives' => 20,
            'enemy_hp_mult' => 1.0,
            'enemy_speed_mult' => 1.0,
            'gold_mult' => 1.0,
            'score_mult' => 1.0,
            'order' => 2,
        ]);
        TdDifficulty::updateOrCreate(['slug' => 'hard'], [
            'name' => 'Hard',
            'starting_gold' => 120,
            'starting_lives' => 15,
            'enemy_hp_mult' => 1.25,
            'enemy_speed_mult' => 1.1,
            'gold_mult' => 0.9,
            'score_mult' => 1.5,
            'order' => 3,
        ]);
        TdDifficulty::updateOrCreate(['slug' => 'nightmare'], [
            'name' => 'Nightmare',
            'starting_gold' => 100,
            'starting_lives' => 10,
            'enemy_hp_mult' => 1.6,
            'enemy_speed_mult' => 1.2,
            'gold_mult' => 0.85,
            'score_mult' => 2.0,
            'order' => 4,
        ]);

        // ---------------- LEVELS ----------------
        $level1 = TdLevel::updateOrCreate(
            ['slug' => 'corridor-initiation'],
            [
                'name' => 'Corridor Initiation',
                'description' => 'Hold the corridor. Simple waves, perfect for learning the ropes.',
                'td_map_id' => $map->id,
                'td_difficulty_id' => $easy->id,
                'reward_score' => 100,
                'order' => 1,
                'is_published' => true,
            ]
        );

        $level2 = TdLevel::updateOrCreate(
            ['slug' => 'system-breach'],
            [
                'name' => 'System Breach',
                'description' => 'The enemy escalates. Mixed waves and a brute at the end.',
                'td_map_id' => $map->id,
                'td_difficulty_id' => $normal->id,
                'reward_score' => 200,
                'order' => 2,
                'is_published' => true,
            ]
        );

        // ---------------- WAVES ----------------
        // Level 1 — 5 waves, grunts + runners
        $this->seedWaves($level1->id, [
            [['enemy' => $grunt->id, 'count' => 8, 'interval_ms' => 900]],
            [['enemy' => $grunt->id, 'count' => 12, 'interval_ms' => 800]],
            [
                ['enemy' => $grunt->id, 'count' => 10, 'interval_ms' => 800],
                ['enemy' => $runner->id, 'count' => 4, 'interval_ms' => 1200, 'offset_ms' => 2000],
            ],
            [['enemy' => $runner->id, 'count' => 14, 'interval_ms' => 600]],
            [
                ['enemy' => $grunt->id, 'count' => 20, 'interval_ms' => 600],
                ['enemy' => $runner->id, 'count' => 8, 'interval_ms' => 900, 'offset_ms' => 3000],
            ],
        ]);

        // Level 2 — 8 waves with brutes
        $this->seedWaves($level2->id, [
            [['enemy' => $grunt->id, 'count' => 12, 'interval_ms' => 800]],
            [['enemy' => $runner->id, 'count' => 10, 'interval_ms' => 700]],
            [
                ['enemy' => $grunt->id, 'count' => 15, 'interval_ms' => 700],
                ['enemy' => $runner->id, 'count' => 6, 'interval_ms' => 900, 'offset_ms' => 2000],
            ],
            [['enemy' => $brute->id, 'count' => 2, 'interval_ms' => 3500]],
            [
                ['enemy' => $grunt->id, 'count' => 18, 'interval_ms' => 600],
                ['enemy' => $brute->id, 'count' => 2, 'interval_ms' => 4000, 'offset_ms' => 4000],
            ],
            [['enemy' => $runner->id, 'count' => 25, 'interval_ms' => 500]],
            [
                ['enemy' => $grunt->id, 'count' => 20, 'interval_ms' => 500],
                ['enemy' => $runner->id, 'count' => 15, 'interval_ms' => 600, 'offset_ms' => 2000],
                ['enemy' => $brute->id, 'count' => 3, 'interval_ms' => 3500, 'offset_ms' => 5000],
            ],
            [['enemy' => $brute->id, 'count' => 6, 'interval_ms' => 2500]],
        ]);
    }

    private function seedWaves(int $levelId, array $waves): void
    {
        // wipe existing for idempotent reseed
        TdWave::where('td_level_id', $levelId)->delete();

        foreach ($waves as $i => $spawns) {
            $wave = TdWave::create([
                'td_level_id' => $levelId,
                'order' => $i + 1,
                'spawn_delay_ms' => 3000,
                'bonus_gold' => 25 + $i * 10,
            ]);
            foreach ($spawns as $j => $spawn) {
                TdWaveSpawn::create([
                    'td_wave_id' => $wave->id,
                    'td_enemy_id' => $spawn['enemy'],
                    'count' => $spawn['count'],
                    'interval_ms' => $spawn['interval_ms'] ?? 800,
                    'offset_ms' => $spawn['offset_ms'] ?? 0,
                    'order' => $j + 1,
                ]);
            }
        }
    }
}
