<?php

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create();
    $this->sectionA = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);
    $this->sectionB = Section::factory()->forSeason($this->season)->create(['name' => 'Beta']);
});

function makeSectionAssignment(array $sectionIds, array $attributes = []): Assignment
{
    $assignment = Assignment::create(array_merge([
        'title' => 'Untitled assignment',
        'due_date' => now()->addWeek(),
    ], $attributes));

    $assignment->sections()->sync($sectionIds);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

it('shows an assignment only to students in a targeted section', function () {
    makeSectionAssignment([$this->sectionA->id], ['title' => 'Alpha only task']);
    makeSectionAssignment([$this->sectionB->id], ['title' => 'Beta only task']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assignments')
            ->has('assignments', 1)
            ->where('assignments.0.title', 'Alpha only task')
            ->where('assignments.0.sections.0.name', 'Alpha'));
});

it('shows an assignment targeted at several sections to each of them', function () {
    makeSectionAssignment([$this->sectionA->id, $this->sectionB->id], ['title' => 'Shared task']);

    foreach ([$this->sectionA, $this->sectionB] as $section) {
        $student = User::factory()->create();
        $student->sections()->attach($section->id, ['season_id' => $this->season->id]);

        $this->actingAs($student)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('assignments', 1));
    }
});

it('hides an unassigned assignment from everyone', function () {
    makeSectionAssignment([], ['title' => 'Draft with no sections']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('assignments', 0));
});

it('hides draft assignments and blocks student actions', function () {
    Storage::fake('public');

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $publishedAssignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Open task']);
    $draftAssignment = makeSectionAssignment([
        $this->sectionA->id,
    ], [
        'title' => 'Hidden task',
        'status' => 'draft',
    ]);

    expect($publishedAssignment->status())->toBe(AssignmentStatus::Published)
        ->and($draftAssignment->status())->toBe(AssignmentStatus::Draft);

    $this->actingAs($student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.id', $publishedAssignment->id)
            ->where('assignments.0.title', 'Open task'));

    $this->post(route('assignments.submit', $draftAssignment), [
        'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    $this->post(route('assignments.feedback.seen', $draftAssignment))
        ->assertForbidden();
});

it('shows closed assignments but blocks new submissions', function () {
    Storage::fake('public');

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $closedAssignment = makeSectionAssignment([
        $this->sectionA->id,
    ], [
        'title' => 'Finished task',
        'status' => 'closed',
    ]);

    $this->actingAs($student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.id', $closedAssignment->id)
            ->where('assignments.0.status', 'closed'));

    $this->post(route('assignments.submit', $closedAssignment), [
        'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    expect(DB::table('assignment_user')
        ->where('assignment_id', $closedAssignment->id)
        ->where('user_id', $student->id)
        ->where('submitted', true)
        ->exists())->toBeFalse();

    // Closed work stays viewable: acknowledging feedback is still allowed.
    $this->post(route('assignments.feedback.seen', $closedAssignment))
        ->assertRedirect();
});

it('accepts a submission for a published assignment', function () {
    Storage::fake('public');

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $assignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Open task']);

    $this->actingAs($student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $student->id)
        ->where('submitted', true)
        ->exists())->toBeTrue();
});

it('rejects a submission for an assignment from another section', function () {
    Storage::fake('public');

    $assignment = makeSectionAssignment([$this->sectionB->id], ['title' => 'Beta only task']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $student->id)
        ->where('submitted', true)
        ->exists())->toBeFalse();
});

it('gives every student in the targeted section a pending roster row', function () {
    $studentA = User::factory()->create();
    $studentA->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);
    $studentB = User::factory()->create();
    $studentB->sections()->attach($this->sectionB->id, ['season_id' => $this->season->id]);

    $assignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Alpha only task']);

    expect(DB::table('assignment_user')->where('assignment_id', $assignment->id)->pluck('user_id')->all())
        ->toBe([$studentA->id])
        ->and(DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $studentA->id)
            ->value('status'))->toBe('Pending');
});

it('adds the roster row for a student who joins the section later', function () {
    $assignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Alpha only task']);

    $latecomer = User::factory()->create();
    $latecomer->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $latecomer->id)
        ->exists())->toBeTrue();
});

it('keeps submitted work when the section is untargeted', function () {
    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $assignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Alpha only task']);

    DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $student->id)
        ->update(['submitted' => true, 'status' => 'Submitted', 'file_path' => 'assignments/1/work.pdf']);

    $assignment->sections()->sync([$this->sectionB->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $student->id)
        ->exists())->toBeTrue();
});

it('drops untouched roster rows when the section is untargeted', function () {
    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $assignment = makeSectionAssignment([$this->sectionA->id], ['title' => 'Alpha only task']);

    $assignment->sections()->sync([$this->sectionB->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $student->id)
        ->exists())->toBeFalse();
});
