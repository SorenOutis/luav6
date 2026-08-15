<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationHistory extends Model
{
    protected $fillable = [
        'user_id',
        'awarded_by',
        'section_id',
        'season_id',
        'amount_xp',
        'amount_points',
        'reason',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
