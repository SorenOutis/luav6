<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['blur_leaderboard' => false]);
});

test('unauthenticated user cannot toggle blur', function () {
    $this->postJson('/api/leaderboard/toggle-blur')
        ->assertUnauthorized();
});

test('authenticated user can toggle blur on', function () {
    $this->actingAs($this->user)
        ->postJson('/api/leaderboard/toggle-blur')
        ->assertOk()
        ->assertJson([
            'blur_leaderboard' => true,
        ]);

    expect($this->user->fresh()->blur_leaderboard)->toBeTrue();
});

test('toggling blur again turns it off', function () {
    $this->user->update(['blur_leaderboard' => true]);

    $this->actingAs($this->user)
        ->postJson('/api/leaderboard/toggle-blur')
        ->assertOk()
        ->assertJson([
            'blur_leaderboard' => false,
        ]);

    expect($this->user->fresh()->blur_leaderboard)->toBeFalse();
});
