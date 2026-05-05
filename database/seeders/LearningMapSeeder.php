<?php

namespace Database\Seeders;

use App\Models\LearningMap\MapNode;
use App\Models\LearningMap\MapNodeRequirement;
use App\Models\LearningMap\MapWorld;
use Illuminate\Database\Seeder;

class LearningMapSeeder extends Seeder
{
    public function run(): void
    {
        $worlds = [
            [
                'slug' => 'origin-springs', 'name' => 'The Origin Springs', 'sort_order' => 1,
                'primary_color' => '#10b981', 'accent_color' => '#34d399', 'background_class' => 'bg-emerald-50/30',
                'nodes' => [
                    ['slug' => 'os-1', 'title' => 'The Call to Logic', 'type' => 'lesson', 'x' => 100, 'y' => 100, 'reward_xp' => 50,  'reqs' => []],
                    ['slug' => 'os-2', 'title' => 'Variable Valley',   'type' => 'lesson', 'x' => 300, 'y' => 150, 'reward_xp' => 50,  'reqs' => [['kind' => 'node', 'target_node_slug' => 'os-1']]],
                    ['slug' => 'os-3', 'title' => 'Conditional Caves', 'type' => 'lesson', 'x' => 500, 'y' => 100, 'reward_xp' => 75,  'reqs' => [['kind' => 'node', 'target_node_slug' => 'os-2']]],
                    ['slug' => 'os-boss', 'title' => 'Guardian of Syntax', 'type' => 'boss', 'x' => 750, 'y' => 200, 'reward_xp' => 200, 'reqs' => [
                        ['kind' => 'node', 'target_node_slug' => 'os-3'],
                        ['kind' => 'xp', 'amount' => 150],
                        ['kind' => 'level', 'level' => 2],
                    ]],
                ],
            ],
            [
                'slug' => 'crystal-peak', 'name' => 'The Crystal Peak', 'sort_order' => 2,
                'primary_color' => '#3b82f6', 'accent_color' => '#60a5fa', 'background_class' => 'bg-blue-50/30',
                'nodes' => [
                    ['slug' => 'cp-1', 'title' => 'Array Ascent',   'type' => 'lesson', 'x' => 100, 'y' => 300, 'reward_xp' => 75, 'reqs' => [['kind' => 'node', 'target_node_slug' => 'os-boss']]],
                    ['slug' => 'cp-2', 'title' => 'Object Overlook', 'type' => 'lesson', 'x' => 350, 'y' => 250, 'reward_xp' => 100, 'reqs' => [['kind' => 'node', 'target_node_slug' => 'cp-1']]],
                    ['slug' => 'cp-boss', 'title' => 'The Loop Master', 'type' => 'boss', 'x' => 600, 'y' => 400, 'reward_xp' => 300, 'reqs' => [
                        ['kind' => 'node', 'target_node_slug' => 'cp-2'],
                        ['kind' => 'level', 'level' => 4],
                    ]],
                ],
            ],
            [
                'slug' => 'sunken-library', 'name' => 'The Sunken Library', 'sort_order' => 3,
                'primary_color' => '#0d9488', 'accent_color' => '#2dd4bf', 'background_class' => 'bg-teal-50/30',
                'nodes' => [
                    ['slug' => 'sl-1', 'title' => 'Data Depths', 'type' => 'lesson', 'x' => 200, 'y' => 100, 'reward_xp' => 100, 'reqs' => [['kind' => 'node', 'target_node_slug' => 'cp-boss']]],
                    ['slug' => 'sl-boss', 'title' => 'The Archivist', 'type' => 'boss', 'x' => 500, 'y' => 300, 'reward_xp' => 400, 'reqs' => [
                        ['kind' => 'node', 'target_node_slug' => 'sl-1'],
                        ['kind' => 'streak', 'amount' => 3],
                    ]],
                ],
            ],
            [
                'slug' => 'celestial-spire', 'name' => 'The Celestial Spire', 'sort_order' => 4,
                'primary_color' => '#8b5cf6', 'accent_color' => '#a78bfa', 'background_class' => 'bg-violet-50/30',
                'nodes' => [
                    ['slug' => 'cs-1', 'title' => 'Async Asteroids', 'type' => 'lesson', 'x' => 150, 'y' => 400, 'reward_xp' => 150, 'reqs' => [['kind' => 'node', 'target_node_slug' => 'sl-boss']]],
                    ['slug' => 'cs-boss', 'title' => 'Event Horizon', 'type' => 'boss', 'x' => 450, 'y' => 150, 'reward_xp' => 500, 'reqs' => [
                        ['kind' => 'node', 'target_node_slug' => 'cs-1'],
                        ['kind' => 'level', 'level' => 8],
                    ]],
                ],
            ],
            [
                'slug' => 'hall-echoes', 'name' => 'The Hall of Echoes', 'sort_order' => 5,
                'primary_color' => '#f43f5e', 'accent_color' => '#fb7185', 'background_class' => 'bg-rose-50/30',
                'nodes' => [
                    ['slug' => 'final-exam', 'title' => 'The Great Synthesis', 'type' => 'boss', 'x' => 400, 'y' => 300, 'reward_xp' => 1000, 'reqs' => [
                        ['kind' => 'node', 'target_node_slug' => 'cs-boss'],
                        ['kind' => 'level', 'level' => 10],
                        ['kind' => 'streak', 'amount' => 7],
                    ]],
                ],
            ],
        ];

        foreach ($worlds as $w) {
            $world = MapWorld::updateOrCreate(
                ['slug' => $w['slug']],
                [
                    'name' => $w['name'], 'sort_order' => $w['sort_order'],
                    'primary_color' => $w['primary_color'], 'accent_color' => $w['accent_color'],
                    'background_class' => $w['background_class'],
                ]
            );

            foreach ($w['nodes'] as $n) {
                $node = MapNode::updateOrCreate(
                    ['slug' => $n['slug']],
                    [
                        'map_world_id' => $world->id,
                        'title' => $n['title'],
                        'type' => $n['type'],
                        'x' => $n['x'], 'y' => $n['y'],
                        'reward_xp' => $n['reward_xp'] ?? 0,
                    ]
                );

                $node->requirements()->delete();
                foreach ($n['reqs'] as $r) {
                    MapNodeRequirement::create(array_merge(
                        ['map_node_id' => $node->id],
                        $r
                    ));
                }
            }
        }
    }
}
