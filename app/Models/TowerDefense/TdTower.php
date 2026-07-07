<?php

namespace App\Models\TowerDefense;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TdTower extends Model
{
    use BelongsToWorkspace;

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
        'admin_id',
    ];

    protected $casts = [
        'upgrades' => 'array',
        'range' => 'float',
        'fire_rate' => 'float',
        'splash_radius' => 'float',
        'projectile_speed' => 'float',
    ];
}
