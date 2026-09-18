<?php

namespace App\Models;

use App\Support\GamificationSyncContext;
use Illuminate\Database\Eloquent\Model;

class SectionProgress extends Model
{
    protected $table = 'section_progress';

    protected $fillable = ['user_id', 'section_id', 'exp', 'points', 'level'];

    public static function levelFromExp(float|int $exp): int
    {
        return (int) floor(max(0, (float) $exp) / 100) + 1;
    }

    public static function expFloorForLevel(int $level): float
    {
        return (float) ((max(1, $level) - 1) * 100);
    }

    protected static function booted(): void
    {
        static::saving(function (SectionProgress $progress): void {
            $progress->level = self::levelFromExp($progress->exp);
        });

        static::updated(function (SectionProgress $progress): void {
            if (! $progress->wasChanged('exp') && ! $progress->wasChanged('points')) {
                return;
            }

            self::syncDelta(
                $progress,
                (float) $progress->exp - (float) $progress->getOriginal('exp'),
                (float) $progress->points - (float) $progress->getOriginal('points'),
                recordAutomaticHistory: true,
            );
        });

        static::created(function (SectionProgress $progress): void {
            self::syncDelta(
                $progress,
                (float) $progress->exp,
                (float) $progress->points,
                recordAutomaticHistory: false,
            );
        });
    }

    private static function syncDelta(
        SectionProgress $progress,
        float $expDelta,
        float $pointsDelta,
        bool $recordAutomaticHistory,
    ): void {
        if (abs($expDelta) <= 0.001 && abs($pointsDelta) <= 0.001) {
            return;
        }

        $context = app(GamificationSyncContext::class);
        if ($context->sectionPropagationSuppressed()) {
            return;
        }

        $user = $progress->user;
        if (! $user) {
            return;
        }

        $user->increment('exp', $expDelta);
        $user->increment('points', $pointsDelta);
        $user->level = self::levelFromExp($user->exp);
        $user->save();

        $seasonProgress = $user->activeSeasonProgress();
        if ($seasonProgress) {
            // A section delta has already been applied to the user. Updating
            // the season aggregate must not make SeasonProgress apply it again.
            $context->withoutSeasonPropagation(function () use ($seasonProgress, $expDelta, $pointsDelta): void {
                if (abs($expDelta) > 0.001) {
                    $seasonProgress->increment('exp', $expDelta);
                }
                if (abs($pointsDelta) > 0.001) {
                    $seasonProgress->increment('points', $pointsDelta);
                }

                // increment() is atomic but bypasses the saving hook; save once
                // more so the derived level follows the new XP total.
                $seasonProgress->save();
            });
        }

        if ($recordAutomaticHistory && ! $context->automaticHistorySuppressed()) {
            $user->recordGamificationHistory(
                $expDelta,
                $pointsDelta,
                'Admin Adjustment',
                'Manual adjustment for Section: '.($progress->section?->name ?? 'Unknown'),
                $progress->section_id,
                null,
                null,
                false,
            );
        }
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
