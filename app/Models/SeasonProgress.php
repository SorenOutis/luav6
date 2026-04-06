<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonProgress extends Model
{
    protected $table = 'season_progress';

    protected $fillable = ['user_id', 'season_id', 'exp', 'level', 'points'];

    protected static function booted()
    {
        static::creating(function (SeasonProgress $progress) {
            if (!$progress->season_id) {
                $season = Season::where('is_active', true)->first() ?? Season::first();
                
                if (!$season) {
                    $season = Season::create([
                        'name' => 'Season 1',
                        'start_date' => now(),
                        'end_date' => now()->addMonths(3),
                        'is_active' => true,
                    ]);
                }
                
                $progress->season_id = $season->id;
            }
        });

        static::saving(function (SeasonProgress $progress) {
            // Level 1: 0-99 XP
            // Level 2: 100-199 XP
            $progress->level = floor($progress->exp / 100) + 1;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
