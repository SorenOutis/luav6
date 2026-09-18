<?php

use App\Models\GamificationHistory;
use App\Models\SeasonProgress;
use App\Models\SectionProgress;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sectionProgress = SectionProgress::with(['user', 'section'])->get();

        foreach ($sectionProgress as $progress) {
            if ($progress->exp > 0 || $progress->points > 0) {
                GamificationHistory::firstOrCreate(
                    [
                        'user_id' => $progress->user_id,
                        'section_id' => $progress->section_id,
                        'reason' => 'Section Reward',
                        'amount_xp' => $progress->exp,
                        'amount_points' => $progress->points,
                    ],
                    [
                        'description' => 'Initial progress for Section: '.($progress->section?->name ?? 'Unknown'),
                        'created_at' => $progress->created_at,
                        'updated_at' => $progress->updated_at,
                    ]
                );
            }
        }

        $seasonProgress = SeasonProgress::with(['user', 'season'])->get();
        foreach ($seasonProgress as $progress) {
            // Check if this season progress has more XP/points than sum of section progresses
            $sectionXp = SectionProgress::where('user_id', $progress->user_id)->sum('exp');
            $sectionPoints = SectionProgress::where('user_id', $progress->user_id)->sum('points');

            $extraXp = $progress->exp - $sectionXp;
            $extraPoints = $progress->points - $sectionPoints;

            if ($extraXp > 0.1 || $extraPoints > 0.1) {
                GamificationHistory::firstOrCreate(
                    [
                        'user_id' => $progress->user_id,
                        'season_id' => $progress->season_id,
                        'reason' => 'Season Reward',
                        'amount_xp' => $extraXp,
                        'amount_points' => $extraPoints,
                    ],
                    [
                        'description' => 'Initial progress for Season: '.($progress->season?->name ?? 'Unknown'),
                        'created_at' => $progress->created_at,
                        'updated_at' => $progress->updated_at,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        GamificationHistory::truncate();
    }
};
