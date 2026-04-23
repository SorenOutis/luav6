<?php

namespace App\Models\TowerDefense;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TdPlayerProgress extends Model
{
    protected $table = 'td_player_progress';

    protected $fillable = [
        'user_id',
        'td_level_id',
        'best_score',
        'best_waves',
        'stars',
        'plays',
        'wins',
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
