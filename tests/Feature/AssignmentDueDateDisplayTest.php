<?php

use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create();
    $this->section = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);

    $this->student = User::factory()->create();
    $this->student->sections()->attach($this->section->id, ['season_id' => $this->season->id]);
});

it('ships the due date to the assignments page as a wall-clock value without a timezone offset', function () {
    // The admin's DateTimePicker is timezone-naive: "Aug 25, 2:30 PM" must be
    // shown to every student verbatim. Shipping an offset (+00:00 / Z) makes
    // the browser convert the moment into the student's local zone, shifting
    // the time. The prop must be a bare ISO datetime so the browser parses it
    // as local time and renders exactly what the admin entered.
    $assignment = Assignment::create([
        'title' => 'Wall-clock deadline',
        'due_date' => '2026-08-25 14:30:00',
    ]);
    $assignment->sections()->sync([$this->section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    $this->actingAs($this->student)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assignments')
            ->has('assignments', 1)
            ->where('assignments.0.due_date', '2026-08-25T14:30:00'));
});

it('ships the dashboard due date as a wall-clock value too, so the today/overdue math matches what is shown', function () {
    $assignment = Assignment::create([
        'title' => 'Wall-clock deadline',
        'due_date' => '2026-08-25 14:30:00',
    ]);
    $assignment->sections()->sync([$this->section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    $this->actingAs($this->student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.dueAtIso', '2026-08-25T14:30:00'));
});
