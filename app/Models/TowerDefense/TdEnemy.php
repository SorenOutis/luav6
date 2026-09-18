<?php

namespace App\Models\TowerDefense;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TdEnemy extends Model
{
    use BelongsToWorkspace;

    protected $table = 'td_enemies';

    protected $fillable = [
        'name',
        'slug',
        'hp',
        'speed',
        'armor',
        'damage',
        'bounty',
        'score',
        'color',
        'radius',
        'abilities',
        'admin_id',
    ];

    protected $casts = [
        'abilities' => 'array',
        'speed' => 'float',
    ];
}
