<?php

namespace App\Models;

use App\Services\BadgeAwardService;
use App\Services\StudentNotificationService;
use App\Support\GamificationSyncContext;
use Illuminate\Database\Eloquent\Model;

class SeasonProgress extends Model
{
    protected $table = 'season_progress';

    protected $fillable = ['user_id', 'season_id', 'exp', 'level', 'points'];

    protected static function booted(): void
    {
        static::creating(function (SeasonProgress $progress): void {
            if ($progress->season_id) {
                return;
            }

            $season = Season::where('is_active', true)->first() ?? Season::first();

            if (! $season) {
                $season = Season::create([
                    'name' => 'Season 1',
                    'start_date' => now(),
                    'end_date' => now()->addMonths(3),
                    'is_active' => true,
                ]);
            }

            $progress->season_id = $season->id;
        });

        static::saving(function (SeasonProgress $progress): void {
            $progress->level = SectionProgress::levelFromExp($progress->exp);
        });

        static::updated(function (SeasonProgress $progress): void {
            if (! $progress->wasChanged('exp') && ! $progress->wasChanged('points')) {
                return;
            }

            self::syncDelta(
                $progress,
                (float) $progress->exp - (float) $progress->getOriginal('exp'),
                (float) $progress->points - (float) $progress->getOriginal('points'),
                created: false,
            );
        });

        static::created(function (SeasonProgress $progress): void {
            self::syncDelta(
                $progress,
                (float) $progress->exp,
                (float) $progress->points,
                created: true,
            );
        });
    }

    private static function syncDelta(
        SeasonProgress $progress,
        float $expDelta,
        float $pointsDelta,
        bool $created,
    ): void {
        if (abs($expDelta) <= 0.001 && abs($pointsDelta) <= 0.001) {
            return;
        }

        $context = app(GamificationSyncContext::class);
        if ($context->seasonPropagationSuppressed()) {
            return;
        }

        $user = $progress->user;
        if (! $user) {
            return;
        }

        $user->increment('exp', $expDelta);
        $user->increment('points', $pointsDelta);
        $user->level = SectionProgress::levelFromExp($user->exp);
        $user->save();

        app(BadgeAwardService::class)->awardEligibleBadges(
            $user,
            (int) $progress->level,
            $progress->season_id,
        );

        $levelIncreased = $created
            ? (int) $progress->level > 1
            : $progress->wasChanged('level')
                && (int) $progress->level > (int) $progress->getOriginal('level');

        if ($levelIncreased) {
            app(StudentNotificationService::class)->sendLevelUp($user, (int) $progress->level);
        }

        if ($context->automaticHistorySuppressed()) {
            return;
        }

        $user->recordGamificationHistory(
            $expDelta,
            $pointsDelta,
            $created ? 'Season Reward' : 'Admin Adjustment',
            ($created ? 'Initial progress for Season: ' : 'Manual adjustment for Season: ')
                .($progress->season?->name ?? 'Unknown'),
            null,
            $progress->season_id,
            null,
            $created,
        );
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
