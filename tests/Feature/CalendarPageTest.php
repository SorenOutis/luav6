<?php

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Season;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use App\Services\AssignmentRosterService;
use App\Support\StudentPageRegistry;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create(['name' => 'S1']);
    $this->mySection = Section::factory()->forSeason($this->season)->create(['name' => 'My Section']);
    $this->otherSection = Section::factory()->forSeason($this->season)->create(['name' => 'Other Section']);

    $this->student = User::factory()->create();
    $this->student->sections()->attach($this->mySection->id, ['season_id' => $this->season->id]);
});

function calendarAssignment(Section $section, array $attributes = []): Assignment
{
    $assignment = Assignment::create(array_merge([
        'title' => 'Untitled assignment',
        'due_date' => now()->addWeek(),
    ], $attributes));

    $assignment->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

it('redirects guests to the login page', function () {
    $this->get(route('calendar'))->assertRedirect(route('login'));
});

it('shows section exams and assignments as calendar events', function () {
    $midterm = Exam::factory()->published()->forSection($this->mySection)->create([
        'title' => 'Midterm Exam',
        'exam_date' => now()->addDays(3),
    ]);
    Exam::factory()->draft()->forSection($this->mySection)->create([
        'title' => 'Draft Exam',
        'exam_date' => now()->addDays(4),
    ]);
    Exam::factory()->published()->forSection($this->otherSection)->create([
        'title' => 'Other Section Exam',
        'exam_date' => now()->addDays(5),
    ]);

    $submitted = calendarAssignment($this->mySection, [
        'title' => 'Lab Report',
        'due_date' => now()->addDays(7),
    ]);
    Submission::query()->updateOrCreate(
        ['user_id' => $this->student->id, 'assignment_id' => $submitted->id],
        ['submitted' => true, 'status' => 'Submitted', 'submitted_at' => now()],
    );

    calendarAssignment($this->otherSection, [
        'title' => 'Other Section Task',
        'due_date' => now()->addDays(8),
    ]);

    $response = $this->actingAs($this->student)->get(route('calendar'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Calendar')
        ->where('todayKey', now()->toDateString())
        ->has('events', 2)
        ->where('events.0.type', 'exam')
        ->where('events.0.title', 'Midterm Exam')
        ->where('events.0.dateKey', now()->addDays(3)->toDateString())
        ->where('events.0.sectionName', 'My Section')
        ->where('events.0.href', "/exams/{$midterm->id}")
        ->where('events.1.type', 'assignment')
        ->where('events.1.title', 'Lab Report')
        ->where('events.1.submitted', true)
        ->where('events.1.isOverdue', false)
        ->has('seasons', 1)
        ->where('seasons.0.name', 'S1')
        ->where('seasons.0.isActive', true)
        ->etc());

    expect($response->getContent())
        ->not->toContain('Draft Exam')
        ->not->toContain('Other Section Exam')
        ->not->toContain('Other Section Task');
});

it('includes global exams visible to every student', function () {
    Exam::factory()->published()->create([
        'title' => 'School-wide Quiz',
        'exam_date' => now()->addDays(2),
    ]);

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.title', 'School-wide Quiz')
            ->where('events.0.sectionName', null)
            ->etc());
});

it('flags past unsubmitted assignments as overdue', function () {
    calendarAssignment($this->mySection, [
        'title' => 'Late Essay',
        'due_date' => now()->subDays(2),
    ]);

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.title', 'Late Essay')
            ->where('events.0.submitted', false)
            ->where('events.0.isOverdue', true)
            ->etc());
});

it('shows closed exams so past work stays visible', function () {
    Exam::factory()->closed()->forSection($this->mySection)->create([
        'title' => 'Closed Exam',
        'exam_date' => now()->subWeeks(2),
    ]);

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.title', 'Closed Exam')
            ->etc());
});

it('keeps events outside the payload window out of the calendar', function () {
    Exam::factory()->published()->forSection($this->mySection)->create([
        'title' => 'Far Future Exam',
        'exam_date' => now()->addMonths(14),
    ]);

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 0)
            ->etc());
});

it('returns a locked response when the calendar page is disabled', function () {
    StudentPageRegistry::setControls([
        'calendar' => ['mode' => StudentPageRegistry::MODE_DISABLED, 'message' => null],
    ]);

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertStatus(423);
});
