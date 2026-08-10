<?php

/**
 * Admin chat tool tests.
 *
 * Admin tools are workspace-scoped (BelongsToWorkspace global scope plus
 * explicit ownership checks) and every write tool refuses to run without
 * confirm=true. Students must never reach these tools.
 */

use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\ExamsAdminTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\StudentsTool;
use App\Ai\Tools\SubmissionsToGradeTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\WorkspaceOverviewTool;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('scopes the workspace overview to the admin\'s own workspace', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $sectionA = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $studentA = User::factory()->create();
    $studentA->sections()->attach($sectionA->id, ['season_id' => $season->id]);
    Exam::factory()->create(['section_id' => $sectionA->id, 'status' => 'published']);

    $this->actingAs($adminB);
    $sectionB = Section::factory()->forSeason($season)->create(['name' => 'Beta']);
    $studentB = User::factory()->create();
    $studentB->sections()->attach($sectionB->id, ['season_id' => $season->id]);
    Exam::factory()->count(2)->create(['section_id' => $sectionB->id, 'status' => 'draft']);

    $this->actingAs($adminA);

    $data = json_decode((new WorkspaceOverviewTool)->handle(new Request([])), true);

    expect($data['students'])->toBe(1)
        ->and($data['exams']['published'])->toBe(1)
        ->and($data['exams']['draft'])->toBe(0)
        ->and(collect($data['sections'])->pluck('name')->all())->toBe(['Alpha']);
});

it('refuses workspace tools for non-admins', function () {
    $this->actingAs(User::factory()->create());

    expect((new WorkspaceOverviewTool)->handle(new Request([])))->toContain('Only admins')
        ->and((new StudentsTool)->handle(new Request([])))->toContain('Only admins')
        ->and((new ExamsAdminTool)->handle(new Request([])))->toContain('Only admins')
        ->and((new SubmissionsToGradeTool)->handle(new Request([])))->toContain('Only admins');
});

it('searches only students in the admin\'s workspace', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $sectionA = Section::factory()->forSeason($season)->create();
    $alice = User::factory()->create(['name' => 'Alice Santos']);
    $alice->sections()->attach($sectionA->id, ['season_id' => $season->id]);

    $this->actingAs($adminB);
    $sectionB = Section::factory()->forSeason($season)->create();
    $bob = User::factory()->create(['name' => 'Bob Cruz']);
    $bob->sections()->attach($sectionB->id, ['season_id' => $season->id]);

    $this->actingAs($adminA);

    $all = (new StudentsTool)->handle(new Request([]));
    $searched = (new StudentsTool)->handle(new Request(['search' => 'alice']));

    expect($all)->toContain('Alice Santos')->not->toContain('Bob Cruz')
        ->and($searched)->toContain('Alice Santos');
});

it('lists only the admin\'s own exams with submission stats', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    Exam::factory()->create(['title' => 'Alpha Midterm', 'status' => 'published', 'section_id' => Section::factory()->forSeason($season)->create()->id]);

    $this->actingAs($adminB);
    Exam::factory()->create(['title' => 'Beta Final', 'status' => 'published', 'section_id' => Section::factory()->forSeason($season)->create()->id]);

    $this->actingAs($adminA);

    $result = (new ExamsAdminTool)->handle(new Request([]));

    expect($result)->toContain('Alpha Midterm')->not->toContain('Beta Final');
});

it('lists only pending submissions from the admin\'s own exams', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $examA = Exam::factory()->create(['status' => 'published']);
    $partA = ExamPart::factory()->create(['exam_id' => $examA->id]);
    $studentA = User::factory()->create(['name' => 'Alice Santos']);
    ExamSubmission::create(['user_id' => $studentA->id, 'exam_id' => $examA->id, 'exam_part_id' => $partA->id, 'answers' => [], 'status' => 'pending_review', 'score' => 0]);
    ExamSubmission::create(['user_id' => User::factory()->create()->id, 'exam_id' => $examA->id, 'exam_part_id' => $partA->id, 'answers' => [], 'status' => 'graded', 'score' => 90]);

    $this->actingAs($adminB);
    $examB = Exam::factory()->create(['status' => 'published']);
    $partB = ExamPart::factory()->create(['exam_id' => $examB->id]);
    ExamSubmission::create(['user_id' => User::factory()->create(['name' => 'Bob Cruz'])->id, 'exam_id' => $examB->id, 'exam_part_id' => $partB->id, 'answers' => [], 'status' => 'pending_review', 'score' => 0]);

    $this->actingAs($adminA);

    $result = (new SubmissionsToGradeTool)->handle(new Request([]));

    expect($result)->toContain('Alice Santos')
        ->not->toContain('Bob Cruz')
        ->not->toContain('"status":"graded"');
});

it('only creates an exam after explicit confirmation', function () {
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $section = Section::factory()->forSeason($season)->create();

    $refused = (new CreateExamTool)->handle(new Request([
        'title' => 'Cell Biology Quiz',
        'exam_date' => '2026-09-01 09:00',
        'section_id' => $section->id,
        'confirm' => false,
    ]));

    expect($refused)->toContain('NOT EXECUTED')
        ->and(Exam::count())->toBe(0);

    $created = (new CreateExamTool)->handle(new Request([
        'title' => 'Cell Biology Quiz',
        'exam_date' => '2026-09-01 09:00',
        'duration_minutes' => 45,
        'section_id' => $section->id,
        'confirm' => true,
    ]));

    $exam = Exam::first();

    expect($created)->toContain('Draft exam created')
        ->and($exam->status)->toBe('draft')
        ->and($exam->admin_id)->toBe($admin->id)
        ->and($exam->section_id)->toBe($section->id)
        ->and($exam->duration_minutes)->toBe(45);
});

it('rejects a section from another workspace when creating an exam', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->create();

    $this->actingAs($adminA);

    $result = (new CreateExamTool)->handle(new Request([
        'title' => 'Sneaky Exam',
        'exam_date' => '2026-09-01 09:00',
        'section_id' => $foreignSection->id,
        'confirm' => true,
    ]));

    expect($result)->toContain('does not exist')
        ->and(Exam::count())->toBe(0);
});

it('updates an exam only with confirmation and only in the admin\'s workspace', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $exam = Exam::factory()->create(['status' => 'published']);

    // Another admin cannot touch it.
    $this->actingAs($adminB);
    $foreign = (new UpdateExamTool)->handle(new Request([
        'exam_id' => $exam->id,
        'status' => 'closed',
        'confirm' => true,
    ]));

    expect($foreign)->toContain('not found')
        ->and($exam->refresh()->status)->toBe('published');

    // The owner still needs confirm=true.
    $this->actingAs($adminA);
    $unconfirmed = (new UpdateExamTool)->handle(new Request([
        'exam_id' => $exam->id,
        'status' => 'closed',
        'confirm' => false,
    ]));

    expect($unconfirmed)->toContain('NOT EXECUTED')
        ->and($exam->refresh()->status)->toBe('published');

    $confirmed = (new UpdateExamTool)->handle(new Request([
        'exam_id' => $exam->id,
        'status' => 'closed',
        'confirm' => true,
    ]));

    expect($confirmed)->toContain('status → closed')
        ->and($exam->refresh()->status)->toBe('closed');
});

it('posts an announcement only after confirmation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $refused = (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Enrollment Week',
        'description' => 'Enrollment is open until Friday.',
        'confirm' => false,
    ]));

    expect($refused)->toContain('NOT EXECUTED')
        ->and(Announcement::count())->toBe(0);

    $posted = (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Enrollment Week',
        'description' => 'Enrollment is open until Friday.',
        'confirm' => true,
    ]));

    $announcement = Announcement::first();

    expect($posted)->toContain('Announcement posted')
        ->and($announcement->is_active)->toBeTruthy()
        ->and($announcement->admin_id)->toBe($admin->id);
});

it('creates an assignment only for the admin\'s own courses', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $course = Course::create(['name' => 'Biology 101', 'admin_id' => $adminA->id]);

    // Another admin's course is rejected.
    $this->actingAs($adminB);
    $foreign = (new CreateAssignmentTool)->handle(new Request([
        'title' => 'Sneaky Homework',
        'course_id' => $course->id,
        'due_date' => '2026-08-25',
        'confirm' => true,
    ]));

    expect($foreign)->toContain('not found')
        ->and(Assignment::count())->toBe(0);

    // The owner can create after confirming.
    $this->actingAs($adminA);
    $created = (new CreateAssignmentTool)->handle(new Request([
        'title' => 'Cell Model Project',
        'course_id' => $course->id,
        'due_date' => '2026-08-25',
        'confirm' => true,
    ]));

    $assignment = Assignment::first();

    expect($created)->toContain('Assignment created')
        ->and($assignment->course_id)->toBe($course->id)
        ->and($assignment->admin_id)->toBe($adminA->id);
});
