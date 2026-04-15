<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionProgress extends Model
{
    public static $isSyncing = false;

    protected $table = 'section_progress';

    protected $fillable = ['user_id', 'section_id', 'exp', 'points', 'level'];

    protected static function booted()
    {
        static::saving(function (SectionProgress $progress) {
            // Level 1: 0-99 XP
            // Level 2: 100-199 XP
            $progress->level = floor($progress->exp / 100) + 1;
        });

        static::updated(function (SectionProgress $progress) {
            if ($progress->wasChanged('exp') || $progress->wasChanged('points')) {
                $expDelta = (float) $progress->exp - (float) $progress->getOriginal('exp');
                $pointsDelta = (float) $progress->points - (float) $progress->getOriginal('points');
                
                if (abs($expDelta) > 0.001 || abs($pointsDelta) > 0.001) {
                    $user = $progress->user;
                    if ($user) {
                        self::$isSyncing = true;
                        
                        $user->increment('exp', $expDelta);
                        $user->increment('points', $pointsDelta);
                        $user->level = floor($user->exp / 100) + 1;
                        $user->save();

                        $seasonProgress = $user->activeSeasonProgress();
                        if ($seasonProgress) {
                            $seasonProgress->increment('exp', $expDelta);
                            $seasonProgress->increment('points', $pointsDelta);
                            $seasonProgress->save();
                        }

                        self::$isSyncing = false;
                    }
                }
            }
        });

        static::created(function (SectionProgress $progress) {
            $expDelta = (float) $progress->exp;
            $pointsDelta = (float) $progress->points;
            
            if ($expDelta > 0 || $pointsDelta > 0) {
                $user = $progress->user;
                if ($user) {
                    self::$isSyncing = true;

                    $user->increment('exp', $expDelta);
                    $user->increment('points', $pointsDelta);
                    $user->level = floor($user->exp / 100) + 1;
                    $user->save();

                    $seasonProgress = $user->activeSeasonProgress();
                    if ($seasonProgress) {
                        $seasonProgress->increment('exp', $expDelta);
                        $seasonProgress->increment('points', $pointsDelta);
                        $seasonProgress->save();
                    }

                    self::$isSyncing = false;
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
