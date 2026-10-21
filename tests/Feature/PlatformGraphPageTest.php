<?php

use App\Filament\Pages\PlatformGraph;
use App\Models\User;
use App\Support\GraphifyService;
use Livewire\Livewire;

it('allows super admins to access the platform graph page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(PlatformGraph::getUrl())
        ->assertOk()
        ->assertSee('Platform Architecture Graph')
        ->assertSee('Interactive Network')
        ->assertSee('Architecture Subsystems')
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

it('allows super admin to browse organized architecture subsystems', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    Livewire::actingAs($superAdmin)
        ->test(PlatformGraph::class)
        ->call('setTab', 'subsystems')
        ->assertSet('activeTab', 'subsystems')
        ->assertSee('Platform Architecture Domains')
        ->assertSee('AI Studio & Echo Agent')
        ->assertSee('Academic & Assessment Engine')
        ->assertSee('Gamification & Season Economy')
        ->assertSee('Filament Admin Studio');
});

it('filters architecture subsystems by domain and search term', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    Livewire::actingAs($superAdmin)
        ->test(PlatformGraph::class)
        ->call('setTab', 'subsystems')
        ->call('filterDomain', 'ai_studio')
        ->assertSet('selectedDomain', 'ai_studio')
        ->set('search', 'Tool')
        ->assertSet('search', 'Tool')
        ->call('inspectSubsystem', 6)
        ->assertSet('inspectSubsystemId', 6)
        ->call('closeInspection')
        ->assertSet('inspectSubsystemId', null);
});

it('organizes communities into structured architectural subsystems', function () {
    $subsystems = GraphifyService::organizeCommunities();

    expect($subsystems)->toBeArray()->not->toBeEmpty();
    expect($subsystems[0])->toHaveKeys(['id', 'name', 'domain', 'domain_label', 'node_count', 'hub']);

    $summary = GraphifyService::getDomainSummary();
    expect($summary)->toHaveKeys(['ai_studio', 'academic_assessments', 'gamification', 'admin_filament']);
    expect($summary['ai_studio']['subsystems_count'])->toBeGreaterThan(0);
});
