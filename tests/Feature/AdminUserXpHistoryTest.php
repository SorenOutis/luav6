<?php

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ExamSubmissionsRelationManager;
use App\Filament\Resources\Users\RelationManagers\XpHistoryRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\GamificationHistory;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

function xpHistoryStudent(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'exp' => 0,
        'points' => 0,
        'level' => 1,
    ]);
}

function xpHistoryManager(User $student)
{
    return Livewire::test(XpHistoryRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewUser::class,
    ]);
}

it('registers the xp history relation manager alongside exam submissions', function () {
    expect(UserResource::getRelations())
        ->toContain(XpHistoryRelationManager::class)
        ->toContain(ExamSubmissionsRelationManager::class);
});

it('lists the xp a student has earned', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Section A']);
    $student = xpHistoryStudent();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $exam = $student->recordGamificationHistory(50, 0, 'Exam Completion XP', 'Midterm Part 1', $section->id, $season->id, null, false);
    $claim = $student->recordGamificationHistory(5, 0, 'Daily Claim', 'Daily login claim bonus', null, $season->id, null, false);

    xpHistoryManager($student)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$exam, $claim])
        ->assertSee('Exam Completion XP')
        ->assertSee('Daily Claim');
});

it('only shows the xp history belonging to the viewed student', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $student = xpHistoryStudent();
    $other = xpHistoryStudent();

    $mine = $student->recordGamificationHistory(20, 0, 'Teacher Award', 'Great recitation', null, null, null, false);
    $theirs = $other->recordGamificationHistory(20, 0, 'Teacher Award', 'Not mine', null, null, null, false);

    xpHistoryManager($student)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('hides point-only rows behind the default xp entries filter', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $student = xpHistoryStudent();

    $xpRow = $student->recordGamificationHistory(15, 0, 'Exam Accuracy XP', 'Accuracy bonus', null, null, null, false);
    $pointsRow = $student->recordGamificationHistory(0, 30, 'Section Reward', 'Points only', null, null, null, false);

    xpHistoryManager($student)
        ->assertCanSeeTableRecords([$xpRow])
        ->assertCanNotSeeTableRecords([$pointsRow])
        ->removeTableFilter('xp_only')
        ->assertCanSeeTableRecords([$xpRow, $pointsRow]);
});

it('sorts the xp ledger newest first', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $student = xpHistoryStudent();

    $older = $student->recordGamificationHistory(10, 0, 'Daily Claim', 'Older claim', null, null, null, false);
    $older->forceFill(['created_at' => now()->subDays(3)])->save();

    $newer = $student->recordGamificationHistory(10, 0, 'Daily Claim', 'Newer claim', null, null, null, false);
    $newer->forceFill(['created_at' => now()])->save();

    xpHistoryManager($student)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('badges the tab with the total xp earned', function () {
    $student = xpHistoryStudent();

    expect(XpHistoryRelationManager::getBadge($student, ViewUser::class))->toBeNull();

    $student->recordGamificationHistory(50, 0, 'Exam Completion XP', null, null, null, null, false);
    $student->recordGamificationHistory(25.5, 0, 'Teacher Award', null, null, null, null, false);

    expect(XpHistoryRelationManager::getBadge($student->fresh(), ViewUser::class))->toBe('76 XP');
});

it('keeps the xp ledger read only', function () {
    $source = file_get_contents(base_path(
        'app/Filament/Resources/Users/RelationManagers/XpHistoryRelationManager.php'
    ));

    expect($source)
        ->not->toContain('CreateAction')
        ->not->toContain('EditAction')
        ->not->toContain('DeleteAction')
        ->not->toContain('use Filament\\Tables\\Actions\\');

    $this->actingAs(User::factory()->superAdmin()->create());

    $student = xpHistoryStudent();
    $student->recordGamificationHistory(10, 0, 'Daily Claim', 'Daily login claim bonus', null, null, null, false);

    expect(GamificationHistory::query()->where('user_id', $student->id)->count())->toBe(1);
});
