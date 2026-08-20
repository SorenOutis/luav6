<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\ImpersonateUser;
use App\Models\Section;
use App\Models\User;
use App\Services\StreakService;
use Filament\Actions\Testing\TestAction;
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

it('redirects impersonation into the student dashboard without spa navigation', function () {
    $action = ImpersonateUser::action();

    expect($action->getRedirectTo())->toBe(route('dashboard'))
        ->and($action->getRedirectSpa())->toBeFalse()
        ->and($action->getBackTo())->toBe(UserResource::getUrl('index'));
});

it('shows impersonate on the users table for a student and hides it for a banned account', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();
    $banned = User::factory()->banned()->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('impersonate')->table($student))
        ->assertActionHidden(TestAction::make('impersonate')->table($banned));
});

it('impersonates a student from the users table and lands on the dashboard', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('impersonate')->table($student))
        ->assertRedirect(route('dashboard'));

    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and((int) Impersonation::getImpersonatorId())->toBe($admin->id);
});

it('exposes impersonate on the user view and edit pages', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ViewUser::class, ['record' => $student->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('impersonate');

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('impersonate');
});

it('shows the leave banner on the student dashboard while impersonating', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Impersonation::enter($admin, $student, 'web'))->toBeTrue();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="impersonate-banner"', false)
        ->assertSee(route('filament-impersonate.leave'), false);
});

it('restores the admin when leaving impersonation', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Impersonation::enter($admin, $student, 'web'))->toBeTrue();
    session()->put('impersonate.back_to', UserResource::getUrl('index'));

    $this->get(route('filament-impersonate.leave'))
        ->assertRedirect(UserResource::getUrl('index'));

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
