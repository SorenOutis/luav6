<?php

use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\RelationManagers\UsersRelationManager;
use App\Models\Section;
use App\Models\Season;
use App\Models\User;
use Livewire\Livewire;

test('section edit page loads with users relation manager', function () {
    $admin = User::factory()->superAdmin()->create();
    $section = Section::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/sections/{$section->id}/edit")
        ->assertOk();
});

test('users relation manager uses Filament v4+ action namespace', function () {
    expect(class_exists(UsersRelationManager::class))->toBeTrue()
        ->and(class_exists('Filament\Actions\AttachAction'))->toBeTrue()
        ->and(class_exists('Filament\Actions\DetachAction'))->toBeTrue();

    $source = file_get_contents(app_path('Filament/Resources/Sections/RelationManagers/UsersRelationManager.php'));

    expect($source)->toContain('Filament\Actions\AttachAction')
        ->and($source)->not->toContain('Filament\Tables\Actions');
});

test('users relation manager table renders section students', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $section = Section::factory()->create();
    $student = User::factory()->create(['is_admin' => false]);
    $section->users()->attach($student->id, ['season_id' => $section->season_id]);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$student]);
});

test('admin can attach a student to a section with the season pivot', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['is_admin' => false]);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->assertSuccessful()
        ->callTableAction('attach', data: [
            'recordId' => $student->id,
        ])
        ->assertHasNoTableActionErrors();

    $pivot = $section->users()->find($student->id)?->pivot;

    expect($pivot)->not->toBeNull()
        ->and((int) $pivot->season_id)->toBe((int) $season->id);
});
