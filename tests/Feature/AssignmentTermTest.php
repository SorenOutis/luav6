<?php

use App\Filament\Resources\Assignments\Pages\CreateAssignment;
use App\Filament\Resources\Assignments\Pages\EditAssignment;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Filament\Resources\Assignments\Schemas\AssignmentForm;
use App\Models\Assignment;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->superAdmin()->create());
});

it('saves and retrieves term on assignment model', function () {
    $assignment = Assignment::create([
        'title' => 'Midterm Essay',
        'term' => 'Midterm',
    ]);

    expect($assignment->fresh()->term)->toBe('Midterm');
    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'term' => 'Midterm',
    ]);
});

it('lists assignments with term and filters by term in admin table', function () {
    $prelim = Assignment::create([
        'title' => 'Prelim Lab',
        'term' => 'Prelim',
    ]);

    $midterm = Assignment::create([
        'title' => 'Midterm Project',
        'term' => 'Midterm',
    ]);

    Livewire::test(ListAssignments::class)
        ->assertCanSeeTableRecords([$prelim, $midterm])
        ->filterTable('term', 'Prelim')
        ->assertCanSeeTableRecords([$prelim])
        ->assertCanNotSeeTableRecords([$midterm]);
});

it('creates an assignment with a term via filament create page', function () {
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);

    Livewire::test(CreateAssignment::class)
        ->fillForm([
            'title' => 'Final Capstone Proposal',
            'sections' => [$section->id],
            'term' => 'Final',
            'status' => 'published',
            'group_size' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('assignments', [
        'title' => 'Final Capstone Proposal',
        'term' => 'Final',
    ]);
});

it('edits an assignment term via filament edit page', function () {
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);

    $assignment = Assignment::create([
        'title' => 'Research Paper',
        'term' => 'Prelim',
    ]);
    $assignment->sections()->attach($section->id);

    Livewire::test(EditAssignment::class, [
        'record' => $assignment->getRouteKey(),
    ])
        ->fillForm([
            'term' => 'Midterm',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($assignment->fresh()->term)->toBe('Midterm');
});

it('scopes term options based on section school level', function () {
    $collegeSection = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $shsSection = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
    ]);

    $collegeOptions = AssignmentForm::termOptionsForSections([$collegeSection->id]);
    expect(array_keys($collegeOptions))->toBe(['Prelim', 'Midterm', 'Final']);
    expect(AssignmentForm::termHelperTextForSections([$collegeSection->id]))
        ->toBe('College grading periods (Prelim, Midterm, Final).');

    $shsOptions = AssignmentForm::termOptionsForSections([$shsSection->id]);
    expect(array_keys($shsOptions))->toBe([
        'First Semester - 1st Quarter',
        'First Semester - 2nd Quarter',
        'Second Semester - 1st Quarter',
        'Second Semester - 2nd Quarter',
    ]);
    expect(AssignmentForm::termHelperTextForSections([$shsSection->id]))
        ->toBe('Senior High School grading quarters (1st / 2nd Quarter).');

    $mixedOptions = AssignmentForm::termOptionsForSections([$collegeSection->id, $shsSection->id]);
    expect(array_keys($mixedOptions))->toBe([
        'Senior High School (Quarters)',
        'College (Periods)',
    ]);
    expect(AssignmentForm::termHelperTextForSections([$collegeSection->id, $shsSection->id]))
        ->toBe('Pick sections to scope options to Senior High quarters or College periods.');

    $emptyOptions = AssignmentForm::termOptionsForSections([]);
    expect(array_keys($emptyOptions))->toBe([
        'Senior High School (Quarters)',
        'College (Periods)',
    ]);
    expect(AssignmentForm::termHelperTextForSections([]))
        ->toBe('Pick sections to scope options to Senior High quarters or College periods.');
});
