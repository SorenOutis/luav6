<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyXpClaim extends Model
{
    protected $fillable = [
        'user_id',
        'season_id',
        'claim_date',
        'amount',
        'streak',
        'claimed_at',
    ];

    protected $casts = [
        'claim_date' => 'immutable_date',
        'amount' => 'integer',
        'streak' => 'integer',
        'claimed_at' => 'immutable_datetime',
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
