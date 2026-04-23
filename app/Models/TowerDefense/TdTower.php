<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdTower extends Model
{
    protected $table = 'td_towers';

    protected $fillable = [
        'name',
        'slug',
        'cost',
        'damage',
        'range',
        'fire_rate',
        'projectile_type',
        'splash_radius',
        'projectile_speed',
        'color',
        'upgrades',
    ];

    protected $casts = [
        'upgrades' => 'array',
        'range' => 'float',
        'fire_rate' => 'float',
        'splash_radius' => 'float',
        'projectile_speed' => 'float',
    ];
}
