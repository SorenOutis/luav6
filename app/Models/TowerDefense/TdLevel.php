<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdLevel extends Model
{
    protected $table = 'td_levels';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'td_map_id',
        'td_difficulty_id',
        'starting_gold_override',
        'starting_lives_override',
        'allowed_tower_ids',
        'reward_score',
        'order',
        'is_published',
    ];

    protected $casts = [
        'allowed_tower_ids' => 'array',
        'is_published' => 'boolean',
    ];

    public function map()
    {
        return $this->belongsTo(TdMap::class, 'td_map_id');
    }

    public function difficulty()
    {
        return $this->belongsTo(TdDifficulty::class, 'td_difficulty_id');
    }

    public function waves()
    {
        return $this->hasMany(TdWave::class)->orderBy('order');
    }

    public function runs()
    {
        return $this->hasMany(TdRun::class);
    }

    public function allowedTowers()
    {
        $ids = $this->allowed_tower_ids;
        if (empty($ids)) {
            return TdTower::query()->get();
        }

        return TdTower::query()->whereIn('id', $ids)->get();
    }
}
