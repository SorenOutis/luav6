<?php

use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $season = Season::factory()->active()->create();
    $this->section = Section::factory()->forSeason($season)->create();
    $this->student = User::factory()->create();
    $this->student->sections()->attach($this->section->id, ['season_id' => $season->id]);
});

function makeZipAssignment(Section $section): Assignment
{
    $assignment = Assignment::create([
        'title' => 'Batch scripting task',
        'due_date' => now()->addWeek(),
    ]);

    $assignment->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

function fakeZipFile(): UploadedFile
{
    // Minimal valid empty ZIP (end-of-central-directory record).
    return UploadedFile::fake()->createWithContent('archive.zip', "PK\x05\x06".str_repeat("\x00", 18));
}

function fakeBatFile(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('script.bat', "@echo off\r\necho Hello World\r\npause\r\n");
}

it('accepts a zip file in addition to the usual documents', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), ['file' => fakeZipFile()])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $path = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->where('submitted', true)
        ->value('file_path');

    expect($path)->toEndWith('.zip');
    Storage::disk('public')->assertExists($path);
});

it('stores a zip with batch files inside without inspecting its contents', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    // 152-byte zip fixture: a single `script.bat` entry.
    $zipBytes = base64_decode('UEsDBBQAAgAIACBmZl1k4EklIgAAACQAAAAKAAAAc2NyaXB0LmJhdHNITc7IV8hPS+PlArM8UnNy8hXSivJzFUoyUhWqMgt4uQBQSwECPwMUAAIACAAgZmZdZOBJJSIAAAAkAAAACgAAAAAAAAAAAAAAtoEAAAAAc2NyaXB0LmJhdFBLBQYAAAAAAQABADgAAABKAAAAAAA=', true);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->createWithContent('scripts.zip', $zipBytes),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->where('submitted', true)
        ->exists())->toBeTrue();
});

it('does not accept raw bat files', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), ['file' => fakeBatFile()])
        ->assertSessionHasErrors('file');

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->student->id)
        ->where('submitted', true)
        ->exists())->toBeFalse();
});

it('rejects a file disguised as zip', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->createWithContent('archive.zip', "@echo off\r\necho Hello World\r\n"),
        ])
        ->assertSessionHasErrors('file');
});

it('still accepts regular documents alongside zips', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('rejects unsupported files', function () {
    Storage::fake('public');

    $assignment = makeZipAssignment($this->section);

    $this->actingAs($this->student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->createWithContent('run.ps1', "Write-Host 'hello'\r\n"),
        ])
        ->assertSessionHasErrors('file');
});
