<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile')->middleware('student.page:profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->middleware('student.page:profile')->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->middleware('student.page:profile')->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->middleware('student.page:profile')->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->middleware('student.page:profile')->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware(['student.page:profile', 'throttle:6,1'])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->middleware('student.page:profile')->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->middleware('student.page:profile')
        ->name('two-factor.show');
});
