<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdEnemy extends Model
{
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
    ];

    protected $casts = [
        'abilities' => 'array',
        'speed' => 'float',
    ];
}
