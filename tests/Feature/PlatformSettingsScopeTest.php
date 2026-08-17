<?php

/**
 * Platform Settings write-scope regression tests.
 *
 * Root cause: the tenant-workspaces migration (2026_08_16_000005) made
 * Setting::set() write into the admin's current workspace scope. Platform-wide
 * toggles (registration/login, daily & bonus XP claim, AI chat, branding,
 * student page controls) are consumed from contexts that never see that
 * scope:
 *
 *   - public registration/login pages run UNAUTHENTICATED, so
 *     WorkspaceContext::id() is null and only the global map is read;
 *   - students read settings inside their OWN workspace scope and then fall
 *     back to the global map.
 *
 * The admin's saved values therefore vanished: registration/login toggles had
 * no effect at all, and Bonus XP (default false) silently disappeared while
 * the daily claim (default true) masked the same bug.
 *
 * Fix: platform-wide keys are persisted with Setting::setGlobal()
 * (workspace_id = null); only the Workspace AI Budget section stays
 * workspace-scoped.
 */

use App\Actions\Fortify\CreateNewUser;
use App\Filament\Pages\AiSettings;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BonusXpService;
use App\Services\ClaimXpService;
use App\Support\StudentPageRegistry;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();
});

it('saves platform-wide toggles to the global scope from the admin panel', function () {
    $admin = User::factory()->admin()->create(); // factory gives the admin its own workspace
    expect($admin->current_workspace_id)->not->toBeNull();

    $this->actingAs($admin);

    Livewire::test(AiSettings::class)
        ->set('data.registration_enabled', false)
        ->set('data.registration_disabled_message', 'Sign-ups are closed.')
        ->set('data.login_enabled', false)
        ->set('data.login_disabled_message', 'Login is closed.')
        ->set('data.daily_claim_enabled', true)
        ->set('data.daily_claim_base_xp', 3)
        ->set('data.daily_claim_bonus_enabled', true)
        ->set('data.daily_claim_bonus_xp', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('settings')->where('key', 'registration_enabled')->whereNull('workspace_id')->value('value'))->toBe('0')
        ->and(DB::table('settings')->where('key', 'login_enabled')->whereNull('workspace_id')->value('value'))->toBe('0')
        ->and(DB::table('settings')->where('key', 'daily_claim_bonus_enabled')->whereNull('workspace_id')->value('value'))->toBe('1')
        ->and(DB::table('settings')->where('key', 'daily_claim_bonus_xp')->whereNull('workspace_id')->value('value'))->toBe('25')
        // No row may leak into the admin's workspace scope.
        ->and(DB::table('settings')->where('key', 'registration_enabled')->whereNotNull('workspace_id')->exists())->toBeFalse();
});

it('keeps workspace AI budget keys scoped to the admin workspace', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(AiSettings::class)
        ->set('data.ai_budget_enabled', true)
        ->set('data.ai_budget_daily_tokens', 50000)
        ->set('data.ai_fallback_mode', 'provider_failure')
        ->set('data.ai_fallback_provider', 'ollama')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('settings')->where('key', 'ai_budget_enabled')->whereNull('workspace_id')->exists())->toBeFalse()
        ->and(DB::table('settings')->where('key', 'ai_budget_enabled')->where('workspace_id', $admin->current_workspace_id)->value('value'))->toBe('1')
        ->and(DB::table('settings')->where('key', 'ai_fallback_provider')->where('workspace_id', $admin->current_workspace_id)->value('value'))->toBe('ollama');
});

it('enforces the registration toggle for guests after saving platform settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(AiSettings::class)
        ->set('data.registration_enabled', false)
        ->set('data.registration_disabled_message', 'Sign-ups are closed.')
        ->call('save')
        ->assertHasNoErrors();

    // Guest context — the exact scope the public register page runs in.
    auth()->logout();
    app(WorkspaceContext::class)->clear();

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('registrationEnabled', false));

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'middle_name' => 'Middle',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]);

    $response->assertSessionHasErrors('registration');
    $this->assertGuest();
});

it('blocks registration when disabled even without an existing user', function () {
    // Same enforcement through the CreateNewUser action itself (used by the
    // Fortify POST route) for a completely anonymous request.
    Setting::setGlobal('registration_enabled', '0');
    Setting::setGlobal('registration_disabled_message', 'Closed.');

    expect(fn () => app(CreateNewUser::class)->create([
        'first_name' => 'Test',
        'middle_name' => null,
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
    ]))->toThrow(ValidationException::class);
});

it('makes bonus XP visible to students in a different workspace than the admin', function () {
    $admin = User::factory()->admin()->create(); // workspace A
    $otherWorkspace = Workspace::factory()->create();
    $student = User::factory()->create();
    $student->joinWorkspace($otherWorkspace->id);
    app(WorkspaceContext::class)->clear();

    $this->actingAs($admin);

    Livewire::test(AiSettings::class)
        ->set('data.daily_claim_bonus_enabled', true)
        ->set('data.daily_claim_bonus_xp', 25)
        ->call('save')
        ->assertHasNoErrors();

    // The student's dashboard resolves settings in their own workspace scope.
    $this->actingAs($student);

    expect(app(WorkspaceContext::class)->id())->toBe((int) $otherWorkspace->id)
        ->and(app(BonusXpService::class)->isEnabled())->toBeTrue()
        ->and(app(BonusXpService::class)->canClaim($student))->toBeTrue()
        ->and(app(BonusXpService::class)->claimAmount($student))->toBe(25)
        ->and(app(ClaimXpService::class)->isEnabled())->toBeTrue();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('bonusXp.enabled', true)
            ->where('bonusXp.amount', 25));
});

it('saves student page controls to the global scope', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    StudentPageRegistry::setControls([
        'leaderboard' => [
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Hidden for now.',
        ],
    ]);

    expect(DB::table('settings')
        ->where('key', StudentPageRegistry::SETTING_KEY)
        ->whereNull('workspace_id')
        ->exists())->toBeTrue()
        ->and(StudentPageRegistry::controlFor('leaderboard'))->toBe([
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Hidden for now.',
        ]);
});
