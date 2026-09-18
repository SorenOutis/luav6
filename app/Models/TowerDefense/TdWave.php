<?php

namespace App\Models\TowerDefense;

use Illuminate\Database\Eloquent\Model;

class TdWave extends Model
{
    protected $table = 'td_waves';

    protected $fillable = [
        'td_level_id',
        'order',
        'spawn_delay_ms',
        'bonus_gold',
    ];

    public function level()
    {
        return $this->belongsTo(TdLevel::class, 'td_level_id');
    }

    public function spawns()
    {
        return $this->hasMany(TdWaveSpawn::class)->orderBy('order');
    }
}
