<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StreakRestore extends Model
{
    protected $fillable = [
        'user_id',
        'season_id',
        'restored_date',
        'cost_xp',
        'sequence_in_month',
        'restored_at',
    ];

    protected $casts = [
        'restored_date' => 'immutable_date',
        'cost_xp' => 'integer',
        'sequence_in_month' => 'integer',
        'restored_at' => 'immutable_datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
