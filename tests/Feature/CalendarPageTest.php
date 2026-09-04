<?php

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
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
        ->where('events.0.href', "/activities?exam={$midterm->id}")
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

it('deep-links every exam event to its Activities Hub card', function () {
    $quiz = Exam::factory()->published()->forSection($this->mySection)->create([
        'title' => 'Quiz One',
        'exam_date' => now()->addDays(2),
    ]);
    $midterm = Exam::factory()->published()->forSection($this->mySection)->create([
        'title' => 'Midterm Exam',
        'exam_date' => now()->addDays(5),
    ]);

    // The hub is the student's entry point for starting an activity. Linking
    // the calendar straight to /exams/{id} dropped them into the paper view,
    // which lays out every part before anything has been started.
    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)
            ->where('events.0.href', "/activities?exam={$quiz->id}")
            ->where('events.1.href', "/activities?exam={$midterm->id}")
            ->etc());
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

it('hides closed exams the student never answered', function () {
    Exam::factory()->closed()->forSection($this->mySection)->create([
        'title' => 'Missed Closed Exam',
        'exam_date' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($this->student)->get(route('calendar'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('events', 0)
        ->etc());

    expect($response->getContent())->not->toContain('Missed Closed Exam');
});

it('keeps closed exams the student answered on the calendar', function () {
    $exam = Exam::factory()->closed()->forSection($this->mySection)->create([
        'title' => 'Answered Closed Exam',
        'exam_date' => now()->subWeeks(2),
    ]);
    $part = ExamPart::factory()->forExam($exam)->create();
    ExamSubmission::factory()->forSubmission($this->student, $exam, $part)->create();

    $this->actingAs($this->student)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Answered Closed Exam')
            ->where('events.0.status', 'closed')
            ->where('events.0.isCompleted', true)
            ->etc());
});

it('lets admins see closed exams even without submissions', function () {
    $admin = User::factory()->admin()->create();

    // The factory auto-creates the admin's workspace, and the Exam workspace
    // scope filters the calendar query to it — so the exam must live there.
    Exam::factory()->closed()->forSection($this->mySection)->create([
        'title' => 'Closed Exam For Review',
        'exam_date' => now()->subWeeks(2),
        'workspace_id' => $admin->current_workspace_id,
    ]);

    $this->actingAs($admin)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Closed Exam For Review')
            ->where('events.0.isCompleted', false)
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
