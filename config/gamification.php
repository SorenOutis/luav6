<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Level Curve
    |--------------------------------------------------------------------------
    |
    | An array of XP thresholds (inclusive) required to reach each level.
    | The array is 1-indexed conceptually: element at index 0 = XP to hit
    | level 2, element at index 1 = XP to hit level 3, and so on.
    |
    | Example: [100, 250, 500, 1000] means
    |   0-99 XP   → Level 1
    |   100-249  → Level 2
    |   250-499  → Level 3
    |   500-999  → Level 4
    |   1000+    → Level 5 (and beyond)
    |
    | For XP beyond the last threshold the curve extrapolates using the
    | last defined delta.
    |
    */
    'level_thresholds' => [
        100,    // -> Lv 2
        250,    // -> Lv 3
        500,    // -> Lv 4
        900,    // -> Lv 5
        1400,   // -> Lv 6
        2000,   // -> Lv 7
        2800,   // -> Lv 8
        3800,   // -> Lv 9
        5000,   // -> Lv 10
        6500,   // -> Lv 11
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-level Rewards
    |--------------------------------------------------------------------------
    |
    | Optional. Keyed by level number.
    |
    */
    'level_rewards' => [
        // 5 => ['badge_id' => 1],
    ],


];
