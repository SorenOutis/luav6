<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Support\ImpersonateUser;
use App\Models\Section;
use App\Models\User;
use App\Services\StreakService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use STS\FilamentImpersonate\Facades\Impersonation;

function impersonateSectionFor(User $admin, string $name, string $joinCode): Section
{
    return Section::withoutGlobalScope('workspace')->create([
        'name' => $name,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => $joinCode,
        'admin_id' => $admin->id,
        'workspace_id' => $admin->current_workspace_id,
    ]);
}

it('lets admins impersonate and keeps students from impersonating', function () {
    expect(User::factory()->admin()->create()->canImpersonate())->toBeTrue()
        ->and(User::factory()->superAdmin()->create()->canImpersonate())->toBeTrue()
        ->and(User::factory()->create()->canImpersonate())->toBeFalse();
});

it('lets a super admin impersonate any non-banned student', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $student = User::factory()->create();
    $banned = User::factory()->banned()->create();
    $admin = User::factory()->admin()->create();

    expect($student->canBeImpersonated())->toBeTrue()
        ->and($banned->canBeImpersonated())->toBeFalse()
        ->and($admin->canBeImpersonated())->toBeFalse();
});

it('lets a workspace admin impersonate only students in their tenant', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $ownSection = impersonateSectionFor($admin, 'Own', 'OWNSEC01');
    $otherSection = impersonateSectionFor($otherAdmin, 'Other', 'OTHSEC01');

    $ownStudent = User::factory()->create();
    $otherStudent = User::factory()->create();
    $unenrolled = User::factory()->create();
    $ownStudent->sections()->attach($ownSection->id);
    $otherStudent->sections()->attach($otherSection->id);

    $this->actingAs($admin);

    expect($ownStudent->canBeImpersonated())->toBeTrue()
        ->and($otherStudent->canBeImpersonated())->toBeFalse()
        ->and($unenrolled->canBeImpersonated())->toBeFalse();
});

it('sends impersonation to the student dashboard without spa navigation', function () {
    $source = file_get_contents(app_path('Filament/Support/ImpersonateUser.php'));

    expect($source)->toContain('withoutSpa()')
        ->and($source)->toContain("route('dashboard')");

    $action = ImpersonateUser::action();

    expect($action->getRedirectTo())->toEndWith('/dashboard')
        ->and($action->getRedirectSpa())->toBeFalse();
});

it('loads the users pages with impersonate available', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('impersonate')->table($student));

    Livewire::test(ViewUser::class, ['record' => $student->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('impersonate');

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('impersonate');
});

it('impersonates a student from the users table', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('impersonate')->table($student));

    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and((int) Impersonation::getImpersonatorId())->toBe($admin->id);
});

it('registers the leave route and banner view', function () {
    expect(Route::has('filament-impersonate.leave'))->toBeTrue()
        ->and(view()->exists('impersonate::components.banner'))->toBeTrue();
});

it('shows the leave banner on the student dashboard while impersonating', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Impersonation::enter($admin, $student, 'web'))->toBeTrue();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('impersonate-banner', false)
        ->assertSee('filament-impersonate/leave', false);
});

it('restores the admin when leaving impersonation', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Impersonation::enter($admin, $student, 'web'))->toBeTrue();

    $this->get(route('filament-impersonate.leave'))
        ->assertRedirect();

    expect(auth()->id())->toBe($admin->id)
        ->and(Impersonation::isImpersonating())->toBeFalse();
});

it('does not advance a student streak while an admin is impersonating them', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create([
        'last_login_at' => now()->subDays(3),
        'current_streak' => 4,
        'longest_streak' => 4,
    ]);

    $this->actingAs($admin);
    Impersonation::enter($admin, $student, 'web');

    app(StreakService::class)->touch($student->fresh());

    $student->refresh();

    expect((int) $student->current_streak)->toBe(4)
        ->and($student->last_login_at?->isToday())->toBeFalse();
});
