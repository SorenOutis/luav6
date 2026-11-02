<?php

use App\Filament\Resources\ActivityTasks\Pages\CreateActivityTask;
use App\Filament\Resources\ActivityTasks\Pages\EditActivityTask;
use App\Filament\Resources\ActivityTasks\Pages\ListActivityTasks;
use App\Filament\Resources\ActivityTasks\RelationManagers\ScoresRelationManager;
use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows admin to list activity tasks', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(ListActivityTasks::class)
        ->assertSuccessful();
});

it('allows admin to create senior high performance task', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $shsSection = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
    ]);

    Livewire::test(CreateActivityTask::class)
        ->fillForm([
            'section_id' => $shsSection->id,
            'title' => 'PT 1: Science Exhibit',
            'task_type' => 'Performance Task',
            'term' => 'First Semester - 1st Quarter',
            'max_points' => 50,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = ActivityTask::where('title', 'PT 1: Science Exhibit')->first();
    expect($task)->not->toBeNull()
        ->and($task->task_type)->toBe('Performance Task')
        ->and((float) $task->max_points)->toBe(50.0);
});

it('allows admin to create college task with custom activity name', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $collegeSection = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);

    Livewire::test(CreateActivityTask::class)
        ->fillForm([
            'section_id' => $collegeSection->id,
            'title' => 'Lab 1: Logic Gates',
            'task_type' => 'Laboratory',
            'term' => 'Prelim',
            'max_points' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = ActivityTask::where('title', 'Lab 1: Logic Gates')->first();
    expect($task)->not->toBeNull()
        ->and($task->task_type)->toBe('Laboratory')
        ->and($task->term)->toBe('Prelim');
});

it('allows admin to grade students via activity tasks table action', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create();
    $student = User::factory()->create(['is_admin' => false]);
    $student->sections()->attach($section->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'PT 2: Presentation',
        'term' => 'First Semester - 1st Quarter',
        'task_type' => 'Performance Task',
        'max_points' => 50,
    ]);

    Livewire::test(ListActivityTasks::class)
        ->callTableAction('gradeStudents', $task, [
            'student_scores' => [
                [
                    'user_id' => $student->id,
                    'score' => 48,
                    'is_missed' => false,
                    'remarks' => 'Great delivery',
                ],
            ],
        ])
        ->assertHasNoTableActionErrors();

    $score = ActivityTaskScore::where('activity_task_id', $task->id)
        ->where('user_id', $student->id)
        ->first();

    expect($score)->not->toBeNull()
        ->and((float) $score->score)->toBe(48.0)
        ->and($score->is_missed)->toBeFalse()
        ->and($score->remarks)->toBe('Great delivery');
});

it('allows admin to export all scores via table header action in performance tasks', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create([
        'name' => 'Computer Networking 1',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'is_admin' => false,
    ]);
    $student->sections()->attach($section->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'Cross-Over Cable Crimping',
        'term' => 'Midterm',
        'task_type' => 'Laboratory',
        'max_points' => 50,
    ]);

    ActivityTaskScore::create([
        'activity_task_id' => $task->id,
        'user_id' => $student->id,
        'score' => 45,
        'is_missed' => false,
        'remarks' => 'Clean RJ45 crimp',
        'graded_by' => $admin->id,
    ]);

    $response = Livewire::test(ListActivityTasks::class)
        ->callTableAction('exportScores');

    $response->assertFileDownloaded();
});

it('allows admin to export single task grade sheet via record action', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create([
        'name' => 'Graphics Designing',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'is_admin' => false,
    ]);
    $student->sections()->attach($section->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'Midterm Examination',
        'term' => 'Midterm',
        'task_type' => 'Laboratory',
        'max_points' => 100,
    ]);

    ActivityTaskScore::create([
        'activity_task_id' => $task->id,
        'user_id' => $student->id,
        'score' => 92,
        'is_missed' => false,
        'remarks' => 'Excellent work',
        'graded_by' => $admin->id,
    ]);

    $response = Livewire::test(ListActivityTasks::class)
        ->callTableAction('exportGradeSheet', $task);

    $response->assertFileDownloaded();
});

it('allows admin to export scores in relation manager', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create([
        'name' => 'Computer Networking 1',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create(['name' => 'Mark Cruz', 'is_admin' => false]);
    $student->sections()->attach($section->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'Cross-Over Cable Crimping',
        'term' => 'Midterm',
        'task_type' => 'Laboratory',
        'max_points' => 50,
    ]);

    Livewire::test(ScoresRelationManager::class, [
        'ownerRecord' => $task,
        'pageClass' => EditActivityTask::class,
    ])
        ->callTableAction('exportScores')
        ->assertFileDownloaded();
});
