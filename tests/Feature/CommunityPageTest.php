<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\StudentPageRegistry;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->student = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
});

it('redirects unauthenticated guests to the login page', function () {
    $this->get(route('community.index'))->assertRedirect(route('login'));
});

it('allows authenticated students to visit the community lounge', function () {
    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->has('workspace')
        );
});

it('returns a locked response when community page is disabled in registry', function () {
    StudentPageRegistry::setControls([
        'community' => [
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Community is temporarily paused.',
        ],
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentPageUnavailable')
            ->where('pageTitle', 'Community Lounge')
            ->where('message', 'Community is temporarily paused.')
        );
});

it('allows admins to access community even when disabled', function () {
    StudentPageRegistry::setControls([
        'community' => [
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Community is temporarily paused.',
        ],
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Community'));
});
