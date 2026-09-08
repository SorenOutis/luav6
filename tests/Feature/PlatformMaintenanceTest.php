<?php

use App\Filament\Pages\MaintenanceSettings;
use App\Http\Middleware\EnsurePlatformMaintenance;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformMaintenance;
use Filament\Auth\Pages\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();
});

function enablePlatformMaintenance(): void
{
    Setting::setGlobal('maintenance_enabled', '1');
    Setting::setGlobal('maintenance_title', 'Upgrading classrooms');
    Setting::setGlobal('maintenance_message', 'Back in an hour.');
    Setting::setGlobal('maintenance_image', 'maintenance');
}

it('only allows super admins to access the maintenance settings page', function () {
    $this->actingAs(User::factory()->admin()->create());
    expect(MaintenanceSettings::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create());
    expect(MaintenanceSettings::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->superAdmin()->create());
    expect(MaintenanceSettings::canAccess())->toBeTrue();
});

it('saves maintenance settings to the global scope', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(MaintenanceSettings::class)
        ->set('data.maintenance_enabled', true)
        ->set('data.maintenance_title', 'Upgrading classrooms')
        ->set('data.maintenance_message', 'Back in an hour.')
        ->set('data.maintenance_image', 'maintenance')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('settings')->where('key', 'maintenance_enabled')->whereNull('workspace_id')->value('value'))->toBe('1')
        ->and(DB::table('settings')->where('key', 'maintenance_title')->whereNull('workspace_id')->value('value'))->toBe('Upgrading classrooms')
        ->and(DB::table('settings')->where('key', 'maintenance_message')->whereNull('workspace_id')->value('value'))->toBe('Back in an hour.')
        ->and(DB::table('settings')->where('key', 'maintenance_enabled')->whereNotNull('workspace_id')->exists())->toBeFalse()
        ->and(PlatformMaintenance::isEnabled())->toBeTrue();
});

it('shows the maintenance page to students on the dashboard and direct urls', function () {
    enablePlatformMaintenance();

    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Maintenance')
            ->where('title', 'Upgrading classrooms')
            ->where('message', 'Back in an hour.')
            ->where('image', 'maintenance'));

    $this->get('/assignments')->assertStatus(503);
    $this->get('/grades')->assertStatus(503);
});

it('blocks workspace admins from student pages during maintenance', function () {
    enablePlatformMaintenance();

    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertStatus(503);
});

it('lets super admins browse normally during maintenance', function () {
    enablePlatformMaintenance();

    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('dashboard'))->assertOk();
});

it('blocks student logins during maintenance but allows super admins', function () {
    enablePlatformMaintenance();

    $student = User::factory()->create();

    $this->post('/login', [
        'email' => $student->email,
        'password' => 'password',
    ])->assertSessionHasErrors(Fortify::username());
    $this->assertGuest();

    $superAdmin = User::factory()->superAdmin()->create();

    $this->post('/login', [
        'email' => $superAdmin->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

it('sends guests to the login page, which shows the maintenance screen during maintenance', function () {
    enablePlatformMaintenance();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertStatus(503)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Maintenance')
            ->where('title', 'Upgrading classrooms')
            ->where('message', 'Back in an hour.'));
});

it('returns 503 json for api requests during maintenance', function () {
    enablePlatformMaintenance();

    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.grades'))
        ->assertStatus(503)
        ->assertJsonPath('message', 'Back in an hour.');
});

it('keeps the admin panel login reachable for guests during maintenance', function () {
    enablePlatformMaintenance();

    $this->get('/admin/login')->assertOk();
});

it('lets workspace admins use the admin panel during maintenance', function () {
    enablePlatformMaintenance();

    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin')->assertOk();
});

it('lets panel, livewire and session endpoints through the middleware during maintenance', function () {
    enablePlatformMaintenance();

    $middleware = new EnsurePlatformMaintenance;

    foreach (['admin/login', 'livewire/update', 'livewire-b29bd793/update', 'filament/assets', 'up', 'logout', 'impersonation/leave', 'api/maintenance-status'] as $uri) {
        $request = Request::create('/'.$uri, 'POST');
        $response = $middleware->handle($request, fn () => response('passthrough'));

        expect($response->getContent())->toBe('passthrough');
    }
});

it('lets admins authenticate through the filament login form during maintenance', function () {
    enablePlatformMaintenance();

    $admin = User::factory()->admin()->create();

    Livewire::test(Login::class)
        ->set('data.email', $admin->email)
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($admin);
});

it('exposes the maintenance status to guests and students during maintenance', function () {
    enablePlatformMaintenance();

    // Guests (logged-out students staring at the Maintenance page) and
    // authenticated students alike must reach this endpoint — it is what
    // the page polls to bring them back automatically.
    $this->getJson(route('api.maintenance-status'))
        ->assertOk()
        ->assertJsonPath('enabled', true);

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.maintenance-status'))
        ->assertOk()
        ->assertJsonPath('enabled', true);
});

it('reports maintenance as off once disabled', function () {
    $this->getJson(route('api.maintenance-status'))
        ->assertOk()
        ->assertJsonPath('enabled', false);
});

it('polls the status endpoint instead of showing a refresh button', function () {
    $source = file_get_contents(resource_path('js/pages/Maintenance.vue'));

    expect($source)->toContain('/api/maintenance-status')
        ->and($source)->toContain('setInterval')
        ->and($source)->not->toContain('Refresh page');
});

it('lets students through when maintenance is off', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});
