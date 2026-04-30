<?php

use App\Models\User;
use App\Notifications\StudentActivityNotification;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard shares unread notifications for authenticated users', function () {
    $user = User::factory()->create();

    $user->notify(new StudentActivityNotification([
        'type' => 'xp',
        'icon' => 'zap',
        'title' => '+50 XP earned',
        'message' => 'Earned from Exam: Quiz 1',
        'meta' => 'Exam Submission',
        'href' => '/dashboard',
    ]));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1)
            ->where('notifications.items.0.title', '+50 XP earned')
        );
});

test('users can mark their notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new StudentActivityNotification([
        'type' => 'badge',
        'icon' => 'shield',
        'title' => 'Badge unlocked',
        'message' => 'Level 5 Badge',
        'meta' => 'Earned in Season 1',
        'href' => '/dashboard',
    ]));

    $notification = $user->unreadNotifications()->firstOrFail();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not()->toBeNull();
});
