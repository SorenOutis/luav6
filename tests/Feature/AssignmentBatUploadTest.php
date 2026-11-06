<?php

use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $season = Season::factory()->active()->create();
    $this->section = Section::factory()->forSeason($season)->create();
    $this->student = User::factory()->create();
    $this->student->sections()->attach($this->section->id, ['season_id' => $season->id]);
});

function makeBatAssignment(Section $section, bool $allowBatFiles): Assignment
{
    $assignment = Assignment::create([
        'title' => 'Batch scripting task',
        'due_date' => now()->addWeek(),
        'allow_bat_files' => $allowBatFiles,
    ]);

    $assignment->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

function fakeBatFile(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('script.bat', "@echo off\r\necho Hello World\r\npause\r\n");
}

it('does not accept bat files by default', function () {
    Storage::fake('public');

    $assignment = makeBatAssignment($this->section, false);

    expect($assignment->fresh()->allow_bat_files)->toBeFalse();

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), ['file' => fakeBatFile()])
        ->assertSessionHasErrors('file');

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->where('submitted', true)
        ->exists())->toBeFalse();
});

it('accepts a bat file when the assignment allows it and keeps the extension', function () {
    Storage::fake('public');

    $assignment = makeBatAssignment($this->section, true);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), ['file' => fakeBatFile()])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $path = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->where('submitted', true)
        ->value('file_path');

    expect($path)->toEndWith('.bat');
    Storage::disk('public')->assertExists($path);
});

it('still accepts regular documents when bat files are allowed', function () {
    $assignment = makeBatAssignment($this->section, true);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('rejects unsupported files even when bat files are allowed', function () {
    $assignment = makeBatAssignment($this->section, true);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('archive.zip', 10, 'application/zip'),
        ])
        ->assertSessionHasErrors('file');
});

it('rejects an invalid file disguised as bat', function () {
    $assignment = makeBatAssignment($this->section, true);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('archive.bat', 10, 'application/zip'),
        ])
        ->assertSessionHasErrors('file');
});

it('exposes the bat toggle to the student assignments page', function () {
    makeBatAssignment($this->section, true);

    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assignments')
            ->where('assignments.0.allow_bat_files', true));
});
