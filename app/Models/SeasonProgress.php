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

        static::updated(function (SeasonProgress $progress) {
            if (SectionProgress::$isSyncing) {
                return;
            }

            if ($progress->wasChanged('exp') || $progress->wasChanged('points')) {
                $expDelta = (float) $progress->exp - (float) $progress->getOriginal('exp');
                $pointsDelta = (float) $progress->points - (float) $progress->getOriginal('points');
                
                if (abs($expDelta) > 0.001 || abs($pointsDelta) > 0.001) {
                    $user = $progress->user;
                    if ($user) {
                        $user->increment('exp', $expDelta);
                        $user->increment('points', $pointsDelta);
                        $user->level = floor($user->exp / 100) + 1;
                        $user->save();

                        $user->recordGamificationHistory(
                            $expDelta,
                            $pointsDelta,
                            'Admin Adjustment',
                            "Manual adjustment for Season: " . ($progress->season?->name ?? 'Unknown'),
                            null,
                            $progress->season_id
                        );
                    }
                }
            }
        });

        static::created(function (SeasonProgress $progress) {
            if (SectionProgress::$isSyncing) {
                return;
            }

            $expDelta = (float) $progress->exp;
            $pointsDelta = (float) $progress->points;
            
            if ($expDelta > 0 || $pointsDelta > 0) {
                $user = $progress->user;
                if ($user) {
                    $user->increment('exp', $expDelta);
                    $user->increment('points', $pointsDelta);
                    $user->level = floor($user->exp / 100) + 1;
                    $user->save();

                    $user->recordGamificationHistory(
                        $expDelta,
                        $pointsDelta,
                        'Season Reward',
                        "Initial progress for Season: " . ($progress->season?->name ?? 'Unknown'),
                        null,
                        $progress->season_id
                    );
                }
            }
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
