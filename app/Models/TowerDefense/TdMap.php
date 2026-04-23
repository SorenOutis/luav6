<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdMap extends Model
{
    protected $table = 'td_maps';

    protected $fillable = [
        'name',
        'slug',
        'grid_width',
        'grid_height',
        'tile_size',
        'tiles',
        'path_waypoints',
        'background_color',
    ];

    protected $casts = [
        'tiles' => 'array',
        'path_waypoints' => 'array',
    ];

    public function levels()
    {
        return $this->hasMany(TdLevel::class);
    }
}
