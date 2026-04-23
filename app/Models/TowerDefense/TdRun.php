<?php

namespace App\Models\TowerDefense;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TdRun extends Model
{
    protected $table = 'td_runs';

    protected $fillable = [
        'user_id',
        'td_level_id',
        'status',
        'waves_completed',
        'score',
        'gold_spent',
        'lives_remaining',
        'duration_ms',
        'seed',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function level()
    {
        return $this->belongsTo(TdLevel::class, 'td_level_id');
    }
}
