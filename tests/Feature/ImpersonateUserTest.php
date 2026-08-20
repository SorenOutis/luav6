<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Section;
use App\Models\User;
use App\Services\StreakService;
use App\Support\Impersonation;
use Livewire\Livewire;

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

it('loads the admin user list and edit pages', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)->assertSuccessful();
    Livewire::test(EditUser::class, ['record' => $student->id])->assertSuccessful();
});

it('enters and leaves impersonation', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(Impersonation::enter($admin, $student))->toBeTrue()
        ->and(Impersonation::isImpersonating())->toBeTrue();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('impersonate-banner', false);

    expect(Impersonation::leave())->toBeTrue()
        ->and(Impersonation::isImpersonating())->toBeFalse()
        ->and(auth()->id())->toBe($admin->id);
});

it('does not advance a student streak while an admin is impersonating them', function () {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->create([
        'last_login_at' => now()->subDays(3),
        'current_streak' => 4,
        'longest_streak' => 4,
    ]);

    $this->actingAs($admin);
    Impersonation::enter($admin, $student);

    app(StreakService::class)->touch($student->fresh());

    $student->refresh();

    expect((int) $student->current_streak)->toBe(4)
        ->and($student->last_login_at?->isToday())->toBeFalse();
});
