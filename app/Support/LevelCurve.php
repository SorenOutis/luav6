<?php

namespace App\Support;

class LevelCurve
{
    /**
     * Derive the current level for the given total XP using the
     * thresholds defined in config/gamification.php.
     */
    public static function levelForXp(int $xp): int
    {
        $xp = max(0, $xp);
        $thresholds = config('gamification.level_thresholds', []);

        if (empty($thresholds)) {
            // Fallback to the legacy formula if no config is present.
            return (int) floor($xp / 100) + 1;
        }

        $level = 1;
        foreach ($thresholds as $threshold) {
            if ($xp >= (int) $threshold) {
                $level++;
            } else {
                return $level;
            }
        }

        // Beyond the last threshold: extrapolate using the last delta.
        $last = (int) end($thresholds);
        $prev = count($thresholds) >= 2
            ? (int) $thresholds[count($thresholds) - 2]
            : 0;
        $delta = max(1, $last - $prev);

        while ($xp >= $last + $delta) {
            $level++;
            $last += $delta;
        }

        return $level;
    }

    /**
     * XP required to reach a given level (inclusive floor).
     */
    public static function xpForLevel(int $level): int
    {
        if ($level <= 1) return 0;

        $thresholds = config('gamification.level_thresholds', []);
        $idx = $level - 2;

        if ($idx < count($thresholds)) {
            return (int) $thresholds[$idx];
        }

        // Extrapolate.
        $last = (int) end($thresholds);
        $prev = count($thresholds) >= 2
            ? (int) $thresholds[count($thresholds) - 2]
            : 0;
        $delta = max(1, $last - $prev);

        $extra = $idx - (count($thresholds) - 1);
        return $last + ($delta * $extra);
    }
}
