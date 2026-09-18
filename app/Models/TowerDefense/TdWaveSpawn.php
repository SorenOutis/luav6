<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdWaveSpawn extends Model
{
    protected $table = 'td_wave_spawns';

    protected $fillable = [
        'td_wave_id',
        'td_enemy_id',
        'count',
        'interval_ms',
        'offset_ms',
        'order',
    ];

    public function wave()
    {
        return $this->belongsTo(TdWave::class, 'td_wave_id');
    }

    public function enemy()
    {
        return $this->belongsTo(TdEnemy::class, 'td_enemy_id');
    }
}
