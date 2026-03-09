<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $user->exp,
                'level' => $user->level,
                'currentXP' => $user->exp % 1000, // Assuming 1000 XP per level for simplicity
                'maxXPForLevel' => 1000,
                'rank' => 'Player',
                'achievements' => $user->badges()->count(),
                'points' => $user->points,
            ]
        ]);
    })->name('dashboard');
});

require __DIR__ . '/settings.php';
