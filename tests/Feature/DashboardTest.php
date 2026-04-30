<?php

use App\Models\Badge;
use App\Models\Season;
use App\Models\SeasonProgress;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard awards eligible lifetime badges and keeps the earning season', function () {
    $season = Season::create([
        'name' => 'Season Alpha',
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    $badge = Badge::create([
        'name' => 'Level 3 Achiever',
        'required_level' => 3,
    ]);

    SeasonProgress::create([
        'user_id' => $user->id,
        'season_id' => $season->id,
        'exp' => 250,
        'points' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->assertDatabaseHas('badge_user', [
        'user_id' => $user->id,
        'badge_id' => $badge->id,
        'season_id' => $season->id,
    ]);
});
