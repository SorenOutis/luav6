<?php

use App\Filament\Pages\PlatformGraph;
use App\Models\User;
use App\Support\GraphifyService;

it('allows super admins to access the platform graph page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(PlatformGraph::getUrl())
        ->assertOk()
        ->assertSee('Platform Architecture Graph')
        ->assertSee('Interactive Network')
        ->assertSee('Community Tree');
});

it('forbids standard workspace admins from accessing the platform graph page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(PlatformGraph::getUrl())
        ->assertForbidden();
});

it('allows super admin to view graphify raw html view with sameorigin header', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($superAdmin)
        ->get(route('admin.graphify.view', ['type' => 'network']));

    if (GraphifyService::hasGraph()) {
        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('hud-zoom-in')
            ->assertSee('hud-zoom-out');
    } else {
        $response->assertNotFound();
    }
});

it('forbids non-super-admins from accessing the graphify raw view', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.graphify.view', ['type' => 'network']))
        ->assertForbidden();

    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('admin.graphify.view', ['type' => 'network']))
        ->assertForbidden();
});

it('redirects unauthenticated users requesting the graphify raw view', function () {
    $this->get(route('admin.graphify.view', ['type' => 'network']))
        ->assertRedirect(route('login'));
});
