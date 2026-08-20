<?php

use App\Filament\Resources\Assignments\AssignmentResource\RelationManagers\SubmissionsRelationManager;
use App\Filament\Resources\Assignments\Pages\EditAssignment;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;

it('uses Filament v5 action classes for assignment submissions', function () {
    $source = file_get_contents(base_path(
        'app/Filament/Resources/Assignments/AssignmentResource/RelationManagers/SubmissionsRelationManager.php'
    ));

    expect($source)
        ->toContain('use Filament\\Actions\\EditAction;')
        ->toContain('->recordActions([')
        ->not->toContain('use Filament\\Tables\\Actions\\');
});

it('loads the admin assignment list', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Assignment::create([
        'title' => 'Lab Report',
        'due_date' => now()->addWeek(),
    ]);

    Livewire::test(ListAssignments::class)
        ->assertSuccessful();
});

it('loads an assignment edit page that includes submitted work', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $assignment = Assignment::create([
        'title' => 'Lab Report',
        'description' => 'Write up the pendulum experiment.',
        'due_date' => now()->addWeek(),
    ]);

    $student = User::factory()->create([
        'name' => 'Mina Cruz',
        'middle_name' => null,
    ]);

    Submission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'submitted' => true,
        'status' => 'Submitted',
        'file_path' => 'assignments/'.$student->id.'/lab.pdf',
        'submitted_at' => now(),
    ]);

    Livewire::test(EditAssignment::class, [
        'record' => $assignment->getRouteKey(),
    ])->assertSuccessful();
});

it('renders submitted assignment rows in the admin submissions table', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $assignment = Assignment::create([
        'title' => 'Lab Report',
        'due_date' => now()->addWeek(),
    ]);

    $student = User::factory()->create([
        'name' => 'Mina Cruz',
        'middle_name' => null,
    ]);

    Submission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'submitted' => true,
        'status' => 'Submitted',
        'file_path' => 'assignments/'.$student->id.'/lab.pdf',
        'submitted_at' => now(),
    ]);

    Livewire::test(SubmissionsRelationManager::class, [
        'ownerRecord' => $assignment,
        'pageClass' => EditAssignment::class,
    ])->assertSuccessful();
});
