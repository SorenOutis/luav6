<?php

use App\Models\CommunityInvite;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Setting::flushAllCaches();
});

it('serves the community lounge pwa manifest with configured school name and icons', function () {
    Setting::setGlobal('school_name', 'Springfield Academy');

    $response = $this->get(route('community-app-manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = $response->json();

    expect($manifest['id'])->toBe('/community');
    expect($manifest['start_url'])->toBe('/community');
    expect($manifest['scope'])->toBe('/');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['name'])->toBe('Springfield Academy Community Lounge');
    expect($manifest['short_name'])->toBe('Lounge');
    expect($manifest['background_color'])->toBe('#f5f0e8');
    expect($manifest['theme_color'])->toBe('#f5f0e8');
    expect($manifest['icons'])->toBeArray()->toHaveCount(3);
    expect($manifest['shortcuts'])->toBeArray()->toHaveCount(3);
    expect($manifest['shortcuts'][0]['url'])->toBe('/community');
    expect($manifest['shortcuts'][1]['url'])->toBe('/community?tab=section');
    expect($manifest['shortcuts'][2]['url'])->toBe('/community?tab=saved');
});

it('keeps the community pwa manifest reachable during platform maintenance', function () {
    Setting::setGlobal('platform_maintenance_enabled', true);

    $this->get(route('community-app-manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json');
});

it('arms post-login redirect to community lounge when visiting login with app=community', function () {
    $this->get(route('login', ['app' => 'community']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('pwaApp', 'community')
        );

    expect(session('url.intended'))->toBe(route('community.index'));
});

it('arms post-login redirect when visiting register with app=community', function () {
    $this->get(route('register', ['app' => 'community']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('pwaApp', 'community')
        );

    expect(session('url.intended'))->toBe(route('community.index'));
});

it('redirects to login with app=community when logging out from the standalone community lounge app', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('logout'), ['app' => 'community']);

    $response->assertRedirect(route('login', ['app' => 'community']));
    expect(auth()->check())->toBeFalse();
});

it('redirects to root on regular logout without app marker', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('logout'));

    $response->assertRedirect('/');
    expect(auth()->check())->toBeFalse();
});

it('renders community lounge manifest link on the lounge page for students', function () {
    $workspace = Workspace::factory()->create();
    $student = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $student->workspaces()->attach($workspace->id, ['role' => 'student']);
    CommunityInvite::create([
        'workspace_id' => $workspace->id,
        'user_id' => $student->id,
        'token' => 'token-'.uniqid(),
        'accepted_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertSee('data-community-manifest', escape: false)
        ->assertSee('community.webmanifest', escape: false);
});

it('hides the community lounge manifest and returns 404 when pwa is disabled', function () {
    Setting::setGlobal('community_pwa_enabled', '0');

    $this->get(route('community-app-manifest'))
        ->assertNotFound()
        ->assertJson(['message' => 'Community Lounge PWA is disabled.']);

    $workspace = Workspace::factory()->create();
    $student = User::factory()->create(['current_workspace_id' => $workspace->id]);
    $student->workspaces()->attach($workspace->id, ['role' => 'student']);
    CommunityInvite::create([
        'workspace_id' => $workspace->id,
        'user_id' => $student->id,
        'token' => 'token-'.uniqid(),
        'accepted_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertDontSee('data-community-manifest', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->where('pwaEnabled', false)
        );
});

it('does not arm community redirect on login when pwa is disabled in settings', function () {
    Setting::setGlobal('community_pwa_enabled', '0');

    $this->get(route('login', ['app' => 'community']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('pwaApp', null)
        );

    expect(session('url.intended'))->toBeNull();
});
