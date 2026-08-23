<?php

use App\Events\AssignmentGraded;
use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create();
    $this->section = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);

    $this->student = User::factory()->create();
    $this->student->sections()->attach($this->section->id, ['season_id' => $this->season->id]);

    $this->assignment = Assignment::create([
        'title' => 'Lab Report',
        'description' => 'Pendulum write-up.',
        'due_date' => now()->addWeek(),
        'points_possible' => 100,
    ]);
    $this->assignment->sections()->sync([$this->section->id]);
    app(AssignmentRosterService::class)->syncAssignment($this->assignment);
});

it('ships iso submitted_at and graded_at to the student assignments page', function () {
    $row = Submission::where('assignment_id', $this->assignment->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    $row->update([
        'submitted' => true,
        'status' => 'Graded',
        'grade' => 'A',
        'file_path' => 'assignments/'.$this->student->id.'/lab.pdf',
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
    ]);
    $row->refresh();

    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.submitted_at', $row->submitted_at->toIso8601String())
            ->where('assignments.0.submission.graded_at', $row->graded_at->toIso8601String()));
});

it('ships points_possible to the student assignments page', function () {
    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.points_possible', '100.00'));
});

it('flags feedback as unseen until the student opens the grade details', function () {
    $row = Submission::where('assignment_id', $this->assignment->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    // Pending roster row: no feedback, nothing unseen.
    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.has_unseen_feedback', false));

    $row->update([
        'submitted' => true,
        'status' => 'Graded',
        'grade' => 'A',
        'points' => 85,
        'xp_earned' => 40,
        'feedback' => 'Solid error analysis.',
        'file_path' => 'assignments/'.$this->student->id.'/lab.pdf',
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
    ]);

    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.has_unseen_feedback', true));

    // Acknowledging via the endpoint stamps feedback_seen_at and clears the flag.
    $this->post(route('assignments.feedback.seen', $this->assignment))
        ->assertRedirect();

    expect(
        Submission::where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student->id)
            ->value('feedback_seen_at')
    )->not->toBeNull();

    $this->get(route('assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.has_unseen_feedback', false));
});

it('re-flags feedback as unseen when the teacher revises it', function () {
    $row = Submission::where('assignment_id', $this->assignment->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    $row->update([
        'submitted' => true,
        'status' => 'Graded',
        'grade' => 'A',
        'points' => 85,
        'feedback' => 'Solid error analysis.',
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($this->student)
        ->post(route('assignments.feedback.seen', $this->assignment));

    $this->get(route('assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.has_unseen_feedback', false));

    // Timestamps truncate to seconds — travel past the acknowledgement so
    // the revision's graded_at is strictly newer than feedback_seen_at.
    $this->travel(10)->minutes();

    // A feedback revision refreshes graded_at, so the flag comes back.
    $row->update(['feedback' => 'Updated: add uncertainty bounds.']);

    $this->get(route('assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.has_unseen_feedback', true));
});

it('broadcasts AssignmentGraded when work is graded but not when feedback is acknowledged', function () {
    Event::fake([AssignmentGraded::class]);

    $row = Submission::where('assignment_id', $this->assignment->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    $row->update([
        'submitted' => true,
        'status' => 'Graded',
        'grade' => 'A',
        'points' => 85,
        'feedback' => 'Solid error analysis.',
        'submitted_at' => now()->subDay(),
    ]);

    Event::assertDispatched(AssignmentGraded::class, fn (AssignmentGraded $event) => $event->userId === $this->student->id
        && $event->assignmentId === $this->assignment->id
        && $event->hasFeedback
    );
    Event::assertDispatchedTimes(AssignmentGraded::class, 1);

    // Acknowledgement is a silent mass update — nothing hits the wire.
    $this->actingAs($this->student)
        ->post(route('assignments.feedback.seen', $this->assignment));

    Event::assertDispatchedTimes(AssignmentGraded::class, 1);
});
