<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Season;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\AdminUserGamificationService;
use Livewire\Livewire;

function adminGamificationContext(int $sectionCount = 2): array
{
    $season = Season::factory()->active()->create();
    $student = User::factory()->create([
        'is_admin' => false,
        'exp' => 0,
        'points' => 0,
        'level' => 1,
    ]);

    $sections = collect();
    for ($i = 0; $i < $sectionCount; $i++) {
        $section = Section::factory()->forSeason($season)->create([
            'name' => 'Section '.chr(65 + $i),
        ]);
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
        $sections->push($section);
    }

    return [$student->fresh(), $sections, $season];
}

function adminProgressFor(User $user, Section $section): SectionProgress
{
    return $user->sectionProgress()->where('section_id', $section->id)->firstOrFail();
}

it('derives level from xp using the 100 xp curve', function () {
    expect(SectionProgress::levelFromExp(0))->toBe(1)
        ->and(SectionProgress::levelFromExp(99))->toBe(1)
        ->and(SectionProgress::levelFromExp(100))->toBe(2)
        ->and(SectionProgress::levelFromExp(250))->toBe(3)
        ->and(SectionProgress::expFloorForLevel(1))->toBe(0.0)
        ->and(SectionProgress::expFloorForLevel(5))->toBe(400.0);
});

it('builds one form row per enrolled section', function () {
    [$student, $sections] = adminGamificationContext(2);

    $student->sectionProgress()->create([
        'section_id' => $sections[0]->id,
        'exp' => 250,
        'points' => 40,
        'level' => 3,
    ]);

    $rows = app(AdminUserGamificationService::class)->rowsFor($student->fresh());

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['section_id'])->toBe($sections[0]->id)
        ->and($rows[0]['section_name'])->toBe('Section A')
        ->and($rows[0]['level'])->toBe(3)
        ->and($rows[0]['exp'])->toBe(250.0)
        ->and($rows[0]['points'])->toBe(40.0)
        ->and($rows[1]['section_id'])->toBe($sections[1]->id)
        ->and($rows[1]['level'])->toBe(1)
        ->and($rows[1]['exp'])->toBe(0.0)
        ->and($rows[1]['points'])->toBe(0.0);
});

it('returns no rows when the student has not joined a section', function () {
    $student = User::factory()->create(['is_admin' => false]);

    expect(app(AdminUserGamificationService::class)->rowsFor($student))->toBe([]);
});

it('saves per-section xp and points and derives level', function () {
    [$student, $sections] = adminGamificationContext(2);

    app(AdminUserGamificationService::class)->apply($student, [
        [
            'section_id' => $sections[0]->id,
            'level' => 1,
            'exp' => 250,
            'points' => 80,
        ],
        [
            'section_id' => $sections[1]->id,
            'level' => 1,
            'exp' => 50,
            'points' => 10,
        ],
    ]);

    $first = adminProgressFor($student, $sections[0]);
    $second = adminProgressFor($student, $sections[1]);

    expect((float) $first->exp)->toBe(250.0)
        ->and((float) $first->points)->toBe(80.0)
        ->and((int) $first->level)->toBe(3)
        ->and((float) $second->exp)->toBe(50.0)
        ->and((float) $second->points)->toBe(10.0)
        ->and((int) $second->level)->toBe(1);
});

it('applies a level-only edit by setting xp to that level floor', function () {
    [$student, $sections] = adminGamificationContext(1);

    $student->sectionProgress()->create([
        'section_id' => $sections[0]->id,
        'exp' => 10,
        'points' => 5,
        'level' => 1,
    ]);

    app(AdminUserGamificationService::class)->apply($student->fresh(), [
        [
            'section_id' => $sections[0]->id,
            'level' => 5,
            'exp' => 10,
            'points' => 5,
        ],
    ]);

    $progress = adminProgressFor($student, $sections[0]);

    expect((int) $progress->level)->toBe(5)
        ->and((float) $progress->exp)->toBe(400.0)
        ->and((float) $progress->points)->toBe(5.0);
});

it('syncs section edits into the user totals and active season', function () {
    [$student, $sections] = adminGamificationContext(1);

    app(AdminUserGamificationService::class)->apply($student, [
        [
            'section_id' => $sections[0]->id,
            'level' => 1,
            'exp' => 150,
            'points' => 25,
        ],
    ]);

    $student->refresh();
    $seasonProgress = $student->activeSeasonProgress();

    expect((float) $student->exp)->toBe(150.0)
        ->and((float) $student->points)->toBe(25.0)
        ->and((int) $student->level)->toBe(2)
        ->and((float) $seasonProgress->exp)->toBe(150.0)
        ->and((float) $seasonProgress->points)->toBe(25.0);
});

it('ignores rows for sections the student is not enrolled in', function () {
    [$student, $sections] = adminGamificationContext(1);
    $other = Section::factory()->create(['name' => 'Other']);

    app(AdminUserGamificationService::class)->apply($student, [
        [
            'section_id' => $other->id,
            'level' => 9,
            'exp' => 900,
            'points' => 900,
        ],
        [
            'section_id' => $sections[0]->id,
            'level' => 1,
            'exp' => 20,
            'points' => 4,
        ],
    ]);

    expect($student->sectionProgress()->where('section_id', $other->id)->exists())->toBeFalse()
        ->and((float) adminProgressFor($student, $sections[0])->exp)->toBe(20.0);
});

it('creates missing progress rows for newly assigned sections', function () {
    [$student, $sections] = adminGamificationContext(1);

    expect($student->sectionProgress()->count())->toBe(0);

    app(AdminUserGamificationService::class)->apply($student, []);

    expect($student->sectionProgress()->count())->toBe(1)
        ->and((float) adminProgressFor($student, $sections[0])->exp)->toBe(0.0)
        ->and((int) adminProgressFor($student, $sections[0])->level)->toBe(1);
});

it('fills the edit form with per-section gamification rows', function () {
    [$student, $sections] = adminGamificationContext(2);

    $student->sectionProgress()->create([
        'section_id' => $sections[0]->id,
        'exp' => 120,
        'points' => 15,
        'level' => 2,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create());

    $component = Livewire::test(EditUser::class, ['record' => $student->id]);

    $rows = collect($component->get('data.section_progress_rows'))->values();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows[0]['section_id'])->toBe($sections[0]->id)
        ->and((float) $rows[0]['exp'])->toBe(120.0)
        ->and((float) $rows[0]['points'])->toBe(15.0)
        ->and((int) $rows[0]['level'])->toBe(2)
        ->and((int) $rows[1]['section_id'])->toBe($sections[1]->id)
        ->and((float) $rows[1]['exp'])->toBe(0.0);
});

it('persists per-section level xp and points from the admin edit page', function () {
    [$student, $sections] = adminGamificationContext(2);

    $student->sectionProgress()->create([
        'section_id' => $sections[0]->id,
        'exp' => 10,
        'points' => 2,
        'level' => 1,
    ]);
    $student->sectionProgress()->create([
        'section_id' => $sections[1]->id,
        'exp' => 5,
        'points' => 1,
        'level' => 1,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create());

    $component = Livewire::test(EditUser::class, ['record' => $student->id]);
    $rows = $component->get('data.section_progress_rows');
    $firstKey = collect($rows)->search(fn (array $row): bool => (int) $row['section_id'] === (int) $sections[0]->id);
    $secondKey = collect($rows)->search(fn (array $row): bool => (int) $row['section_id'] === (int) $sections[1]->id);

    expect($firstKey)->not->toBeFalse()
        ->and($secondKey)->not->toBeFalse();

    $component
        ->set("data.section_progress_rows.{$firstKey}.level", 3)
        ->set("data.section_progress_rows.{$firstKey}.exp", 250)
        ->set("data.section_progress_rows.{$firstKey}.points", 80)
        ->set("data.section_progress_rows.{$secondKey}.level", 1)
        ->set("data.section_progress_rows.{$secondKey}.exp", 40)
        ->set("data.section_progress_rows.{$secondKey}.points", 9)
        ->call('save')
        ->assertHasNoErrors();

    $first = adminProgressFor($student, $sections[0]);
    $second = adminProgressFor($student, $sections[1]);
    $student->refresh();

    expect((float) $first->exp)->toBe(250.0)
        ->and((float) $first->points)->toBe(80.0)
        ->and((int) $first->level)->toBe(3)
        ->and((float) $second->exp)->toBe(40.0)
        ->and((float) $second->points)->toBe(9.0)
        ->and((int) $second->level)->toBe(1)
        ->and((float) $student->exp)->toBe(290.0)
        ->and((float) $student->points)->toBe(89.0);
});
