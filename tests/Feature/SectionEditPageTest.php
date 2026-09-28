<?php

use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\RelationManagers\UsersRelationManager;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
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

test('admin can open the add students modal', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->assertSuccessful()
        ->mountTableAction('attach')
        ->assertSuccessful()
        ->assertTableActionMounted('attach');
});

test('users onboarding tours uses jsonb on postgres', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Postgres-only assertion.');
    }

    $type = DB::selectOne(
        "SELECT data_type FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'onboarding_tours'"
    )->data_type;

    // Plain `json` has no equality operator, so Filament AttachAction's
    // SELECT DISTINCT users.* 500s. jsonb supports equality.
    expect($type)->toBe('jsonb');
});

test('sections activity record terms uses jsonb on postgres', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Postgres-only assertion.');
    }

    $type = DB::selectOne(
        "SELECT data_type FROM information_schema.columns WHERE table_name = 'sections' AND column_name = 'activity_record_terms'"
    )->data_type;

    // Plain `json` has no equality operator, so Filament SelectFilter's
    // SELECT DISTINCT sections.* 500s. jsonb supports equality.
    expect($type)->toBe('jsonb');
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

test('super admin cannot save a section with another workspace season', function (bool $editing) {
    $workspace = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();
    $admin = User::factory()->superAdmin()->create(['current_workspace_id' => $workspace->id]);
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $foreignSeason = Season::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $section = Section::factory()->forSeason($season)->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin);

    Livewire::test($editing ? EditSection::class : CreateSection::class, $editing ? ['record' => $section->id] : [])
        ->fillForm([
            'name' => $editing ? $section->name : 'New Hardware Section',
            'season_id' => $foreignSeason->id,
            'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        ])
        ->call($editing ? 'save' : 'create')
        ->assertHasFormErrors(['season_id']);

    expect($section->fresh()->season_id)->toBe($season->id);
    expect(Section::withoutGlobalScope('workspace')->where('season_id', $foreignSeason->id)->exists())->toBeFalse();
})->with([false, true]);

test('bulk attached students both see their section on the dashboard', function () {
    $workspace = Workspace::factory()->create();
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id, 'start_date' => now()->subMonth()]);
    $section = Section::factory()->forSeason($season)->create([
        'workspace_id' => $workspace->id,
        'name' => 'Hardware & Software Installation',
    ]);
    $students = User::factory()->count(2)->create();
    $newerSeason = Season::factory()->create(['workspace_id' => $workspace->id, 'start_date' => now()->addMonth()]);
    $hidden = Section::factory()->forSeason($newerSeason)->leaderboardHidden()->create(['workspace_id' => $workspace->id]);
    $hidden->users()->attach($students->last()->id);

    $this->actingAs(User::factory()->superAdmin()->create(['current_workspace_id' => $workspace->id]));

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $section,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('attach', data: ['recordId' => $students->modelKeys()])
        ->assertHasNoTableActionErrors()
        ->assertCanSeeTableRecords($students);

    foreach ($students as $student) {
        expect((int) $section->users()->find($student->id)->pivot->season_id)->toBe($season->id);
        expect($student->fresh()->workspaces()->whereKey($workspace->id)->exists())->toBeTrue();

        $this->actingAs($student->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sectionLeaderboards', 1)
                ->where('sectionLeaderboards.0.sectionId', $section->id)
                ->has('availableSeasons', 1)
                ->where('availableSeasons.0.id', $season->id));
    }
});

test('attaching a student preserves their selected workspace and its dashboard isolation', function () {
    $currentWorkspace = Workspace::factory()->create();
    $hardwareWorkspace = Workspace::factory()->create();
    $currentSeason = Season::factory()->active()->create(['workspace_id' => $currentWorkspace->id]);
    $hardwareSeason = Season::factory()->active()->create(['workspace_id' => $hardwareWorkspace->id]);
    $web = Section::factory()->forSeason($currentSeason)->create([
        'workspace_id' => $currentWorkspace->id,
        'name' => 'Web Analytics & SEO',
    ]);
    $hardware = Section::factory()->forSeason($hardwareSeason)->create([
        'workspace_id' => $hardwareWorkspace->id,
        'name' => 'Hardware & Software Installation',
    ]);
    $student = User::factory()->create();
    $web->users()->attach($student->id);

    $this->actingAs(User::factory()->superAdmin()->create());
    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $hardware,
        'pageClass' => EditSection::class,
    ])
        ->callTableAction('attach', data: ['recordId' => [$student->id]])
        ->assertHasNoTableActionErrors();

    expect($student->fresh()->current_workspace_id)->toBe($currentWorkspace->id);
    expect($student->workspaces()->whereKey($hardwareWorkspace->id)->exists())->toBeTrue();

    $this->actingAs($student->fresh())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sectionLeaderboards', 1)
            ->where('sectionLeaderboards.0.sectionId', $web->id)
            ->has('availableSeasons', 1)
            ->where('availableSeasons.0.id', $currentSeason->id));

    $student->joinWorkspace($hardwareWorkspace->id);

    $this->actingAs($student->fresh())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sectionLeaderboards', 1)
            ->where('sectionLeaderboards.0.sectionId', $hardware->id)
            ->has('availableSeasons', 1)
            ->where('availableSeasons.0.id', $hardwareSeason->id));
});

test('super admin edits a section using its workspace rather than the admins workspace', function () {
    $workspace = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();
    $season = Season::factory()->active()->create(['workspace_id' => $otherWorkspace->id]);
    $section = Section::factory()->forSeason($season)->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs(User::factory()->superAdmin()->create(['current_workspace_id' => $workspace->id]));

    Livewire::test(EditSection::class, ['record' => $section->id])
        ->fillForm(['name' => 'Updated Hardware Section', 'season_id' => $season->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($section->fresh()->name)->toBe('Updated Hardware Section')
        ->and($section->fresh()->workspace_id)->toBe($otherWorkspace->id)
        ->and($section->fresh()->season_id)->toBe($season->id);
});

test('saving empty activity record terms stores empty array', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $season = Season::factory()->create();
    $section = Section::factory()->create([
        'season_id' => $season->id,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'activity_record_terms' => ['Prelim'],
    ]);

    Livewire::test(EditSection::class, [
        'record' => $section->id,
    ])
        ->fillForm([
            'name' => $section->name,
            'season_id' => $season->id,
            'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
            'activity_record_enabled' => true,
            'activity_record_terms' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $section->refresh();
    expect($section->activity_record_terms)->toBe([]);
});

test('unconfigured section activity record terms formats with all options checked', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $season = Season::factory()->create();
    $section = Section::factory()->create([
        'season_id' => $season->id,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'activity_record_terms' => null,
    ]);

    $component = Livewire::test(EditSection::class, [
        'record' => $section->id,
    ]);

    expect($component->get('data.activity_record_terms'))->toBe(['Prelim', 'Midterm', 'Final']);
});
