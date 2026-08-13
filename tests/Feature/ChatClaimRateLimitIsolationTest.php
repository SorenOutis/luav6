<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * Regression coverage for cross-feature rate-limit interference.
 *
 * The chat endpoints, the persisted-chats endpoints and the daily XP claim
 * used to be throttled with the string form `throttle:N,1`, which Laravel
 * keys by the authenticated user ONLY (no route component in the key). All
 * three features therefore shared one per-user counter, and each route
 * checked that shared counter against its own allowance — so a chatty
 * session (the floating widget posts on every message, stream and history
 * fetch) pushed the shared counter past the claim route's 10-per-minute
 * allowance and the daily XP claim intermittently returned 429. They now
 * use named limiters (`throttle:chat`, `throttle:chats`,
 * `throttle:claim-xp`), whose keys hash the limiter name, so each feature
 * has its own bucket.
 */
function chatClaimRateLimitContext(): User
{
    return User::factory()->create();
}

it('does not let chat traffic block the daily xp claim', function () {
    $student = chatClaimRateLimitContext();

    for ($i = 0; $i < 12; $i++) {
        // Invalid payload on purpose: the throttle middleware runs (and
        // counts the hit) before validation rejects the request. This lets
        // the test generate real chat traffic without invoking the AI.
        actingAs($student)
            ->postJson('/api/chat')
            ->assertUnprocessable();
    }

    // 12 chat hits in a minute — over the old shared bucket's 10-per-minute
    // claim allowance. The claim must still go through on its own bucket.
    actingAs($student)
        ->postJson('/api/claim-xp/prompt-shown')
        ->assertSuccessful()
        ->assertJson(['ok' => true]);
});

it('does not let xp claim traffic block the chat', function () {
    $student = chatClaimRateLimitContext();

    for ($i = 0; $i < 10; $i++) {
        actingAs($student)
            ->postJson('/api/claim-xp/prompt-shown')
            ->assertSuccessful();
    }

    // 11th claim request: the claim-xp bucket (10/min) is now exhausted.
    actingAs($student)
        ->postJson('/api/claim-xp/prompt-shown')
        ->assertStatus(429);

    // Chat has its own bucket, so it must not inherit the 429.
    actingAs($student)
        ->postJson('/api/chat')
        ->assertUnprocessable();
});

it('keeps the widget chat and persisted chats buckets independent', function () {
    $student = chatClaimRateLimitContext();

    for ($i = 0; $i < 60; $i++) {
        actingAs($student)
            ->postJson('/api/chat')
            ->assertUnprocessable();
    }

    // Widget chat bucket (60/min) is exhausted…
    actingAs($student)
        ->postJson('/api/chat')
        ->assertStatus(429);

    // …but creating a persisted conversation is a separate bucket.
    actingAs($student)
        ->postJson('/api/chats')
        ->assertSuccessful()
        ->assertJsonStructure(['session' => ['id']]);
});
