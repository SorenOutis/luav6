<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->student = User::factory()->create();
});

it('records a finished tour on the account', function () {
    $this->actingAs($this->student)
        ->post('/onboarding/dashboard', ['status' => 'done'])
        ->assertRedirect();

    expect($this->student->fresh()->onboardingTours())
        ->toBe(['dashboard' => 'done']);
});

it('records a skipped tour on the account', function () {
    $this->actingAs($this->student)
        ->post('/onboarding/dashboard', ['status' => 'skipped'])
        ->assertRedirect();

    expect($this->student->fresh()->onboardingTours())
        ->toBe(['dashboard' => 'skipped']);
});

it('records the activities hub tour instead of 404ing', function () {
    // Regression: Activities/Index.vue posts tour id 'activities-hub', which
    // was missing from the controller's allowlist. The POST aborted with 404
    // and Inertia hard-navigated the student to the "404 Not Found" page
    // whenever they skipped or finished the hub tour.
    $this->actingAs($this->student)
        ->post('/onboarding/activities-hub', ['status' => 'skipped'])
        ->assertRedirect();

    expect($this->student->fresh()->onboardingTours())
        ->toBe(['activities-hub' => 'skipped']);
});

it('keeps the first resolution when the tour is reported twice', function () {
    $this->actingAs($this->student)->post('/onboarding/grades', ['status' => 'done']);
    $this->actingAs($this->student)->post('/onboarding/grades', ['status' => 'skipped']);

    expect($this->student->fresh()->onboardingTours())->toBe(['grades' => 'done']);
});

it('tracks tours independently', function () {
    $this->actingAs($this->student)->post('/onboarding/dashboard', ['status' => 'done']);
    $this->actingAs($this->student)->post('/onboarding/chats', ['status' => 'skipped']);

    expect($this->student->fresh()->onboardingTours())->toBe([
        'dashboard' => 'done',
        'chats' => 'skipped',
    ]);
});

it('rejects unknown tours and invalid statuses', function () {
    $this->actingAs($this->student)
        ->post('/onboarding/not-a-tour', ['status' => 'done'])
        ->assertNotFound();

    $this->actingAs($this->student)
        ->postJson('/onboarding/dashboard', ['status' => 'maybe'])
        ->assertStatus(422);

    expect($this->student->fresh()->onboardingTours())->toBe([]);
});

it('requires authentication', function () {
    $this->post('/onboarding/dashboard', ['status' => 'done'])
        ->assertRedirect('/login');
});

it('shares resolved tours with every inertia response', function () {
    $this->student->markOnboardingTour('dashboard', 'skipped');

    $this->actingAs($this->student)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.tours.dashboard', 'skipped')
        );
});

it('does not leak another users onboarding state', function () {
    $other = User::factory()->create();
    $other->markOnboardingTour('dashboard', 'done');

    // `where()` wraps array props in a Collection before handing them to a
    // closure, so assert on the key itself rather than on emptiness.
    $this->actingAs($this->student)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->missing('onboarding.tours.dashboard'));

    expect($this->student->fresh()->onboardingTours())->toBe([]);
});

it('lets a tour be replayed after a reset', function () {
    $this->student->markOnboardingTour('dashboard', 'done');

    $this->actingAs($this->student)
        ->delete('/onboarding/dashboard')
        ->assertRedirect();

    expect($this->student->fresh()->onboardingTours())->toBe([]);
});

it('ignores malformed stored values', function () {
    $this->student->forceFill(['onboarding_tours' => ['dashboard' => 'nonsense', 'grades' => 'done']])->save();

    expect($this->student->fresh()->onboardingTours())->toBe(['grades' => 'done']);
});
