<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdDifficulty extends Model
{
    protected $table = 'td_difficulties';

    protected $fillable = [
        'name',
        'slug',
        'starting_gold',
        'starting_lives',
        'enemy_hp_mult',
        'enemy_speed_mult',
        'gold_mult',
        'score_mult',
        'order',
    ];

    protected $casts = [
        'enemy_hp_mult' => 'float',
        'enemy_speed_mult' => 'float',
        'gold_mult' => 'float',
        'score_mult' => 'float',
    ];
}
