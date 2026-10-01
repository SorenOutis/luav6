<?php

use App\Ai\Tools\ActivityTasksAdminTool;
use App\Ai\Tools\AdminGradesTool;
use App\Ai\Tools\AnnouncementsAdminTool;
use App\Ai\Tools\AssignmentsAdminTool;
use App\Ai\Tools\AwardStudentXpTool;
use App\Ai\Tools\CoursesAdminTool;
use App\Ai\Tools\CreateActivityTaskTool;
use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateCourseTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\CreateLearningMaterialTool;
use App\Ai\Tools\CreateSectionTool;
use App\Ai\Tools\CreateUserTool;
use App\Ai\Tools\DeleteActivityTaskTool;
use App\Ai\Tools\DeleteAnnouncementTool;
use App\Ai\Tools\DeleteAssignmentTool;
use App\Ai\Tools\DeleteCourseTool;
use App\Ai\Tools\DeleteExamTool;
use App\Ai\Tools\DeleteGradeTool;
use App\Ai\Tools\DeleteLearningMaterialTool;
use App\Ai\Tools\DeleteSectionTool;
use App\Ai\Tools\DeleteUserTool;
use App\Ai\Tools\GradeSubmissionTool;
use App\Ai\Tools\LearningMaterialsAdminTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\RecordGradeTool;
use App\Ai\Tools\ResetUserPasswordTool;
use App\Ai\Tools\SectionsAdminTool;
use App\Ai\Tools\UpdateAnnouncementTool;
use App\Ai\Tools\UpdateAssignmentTool;
use App\Ai\Tools\UpdateCourseTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\UpdateGradeTool;
use App\Ai\Tools\UpdateSectionTool;
use App\Ai\Tools\UpdateUserTool;
use App\Filament\Pages\AdminAiChat;
use App\Models\ActivityTask;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\ChatSession;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Grade;
use App\Models\LearningMaterial;
use App\Models\PendingAiAction;
use App\Models\Section;
use App\Models\User;
use App\Services\PendingAiActionService;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

function pendingActionNonce(PendingAiAction $action): string
{
    return app(PendingAiActionService::class)->present($action)['nonce'];
}

it('allows only admins to access the AI chat page', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $this->actingAs($admin);
    expect(AdminAiChat::canAccess())->toBeTrue();

    Livewire::test(AdminAiChat::class)
        ->assertOk();

    $this->actingAs($student);
    expect(AdminAiChat::canAccess())->toBeFalse();
});

it('opens AI assistant in a new tab via navigation item', function () {
    $items = AdminAiChat::getNavigationItems();

    expect($items)->toHaveCount(1)
        ->and($items[0]->shouldOpenUrlInNewTab())->toBeTrue();
});

it('renders the welcome screen with greeting data and fox mascot on initial load', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Professor Albus Dumbledore',
    ]);

    $this->actingAs($admin);

    Livewire::test(AdminAiChat::class)
        ->assertOk()
        ->assertSee('fox-welcome.webp')
        ->assertSee('wolf-persona')
        ->assertSee('data-wolf-mark')
        ->assertSee('greetingLine')
        ->assertSee('claudeTimeGreeting')
        ->assertSee('greetingSubtext')
        ->assertSee('filteredPromptStarters')
        ->assertSee('Professor')
        ->assertSee('welcome-input')
        ->assertSee('welcome-suggestions');
});

it('isolates chat session history per admin user', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();

    $session1 = $admin1->chatSessions()->create([
        'title' => 'Admin 1 Exam Planning',
    ]);

    $session2 = $admin2->chatSessions()->create([
        'title' => 'Admin 2 Curriculum Review',
    ]);

    $this->actingAs($admin1);
    Livewire::test(AdminAiChat::class)
        ->assertOk()
        ->assertSee('Admin 1 Exam Planning')
        ->assertDontSee('Admin 2 Curriculum Review');

    $this->actingAs($admin2);
    Livewire::test(AdminAiChat::class)
        ->assertOk()
        ->assertSee('Admin 2 Curriculum Review')
        ->assertDontSee('Admin 1 Exam Planning');
});

it('stages and executes exam deletion with admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $exam = Exam::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Biology Midterm to Remove',
        'status' => 'draft',
    ]);

    $tool = new DeleteExamTool;
    $result = (string) $tool->handle(new Request(['exam_id' => $exam->id]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_exam')->firstOrFail();
    expect($action->status)->toBe(PendingAiAction::STATUS_PENDING);

    // Approve the deletion
    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Exam::query()->whereKey($exam->id)->exists())->toBeFalse();
});

it('inspects student grades scoped to the workspace via AdminGradesTool', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create(['name' => 'Maria Clara']);
    $section = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'Grade 10 - Rizal']);

    Grade::factory()->create([
        'workspace_id' => $workspaceId,
        'user_id' => $student->id,
        'section_id' => $section->id,
        'subject' => 'Science',
        'period' => '1st Quarter',
        'score' => 92.5,
        'max_score' => 100,
        'remarks' => 'Outstanding performance',
        'recorded_by' => $admin->id,
    ]);

    $tool = new AdminGradesTool;
    $response = (string) $tool->handle(new Request(['student_name' => 'Maria Clara']));
    $data = json_decode($response, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveCount(1)
        ->and($data[0]['student_name'])->toBe('Maria Clara')
        ->and($data[0]['subject'])->toBe('Science')
        ->and($data[0]['score'])->toBe(92.5);
});

it('stages and records a new student grade with admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create(['name' => 'Crisostomo Ibarra']);
    $section = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'History 101']);

    $tool = new RecordGradeTool;
    $result = (string) $tool->handle(new Request([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'subject' => 'Philippine History',
        'period' => 'Midterm',
        'score' => 88.0,
        'max_score' => 100.0,
        'remarks' => 'Good analytical essay',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'record_grade')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $grade = Grade::query()->where('user_id', $student->id)->first();
    expect($grade)->not->toBeNull()
        ->and((float) $grade->score)->toBe(88.0)
        ->and($grade->subject)->toBe('Philippine History')
        ->and($grade->period)->toBe('Midterm');
});

it('stages and updates an existing student grade with admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create();
    $section = Section::factory()->create(['workspace_id' => $workspaceId]);

    $grade = Grade::factory()->create([
        'workspace_id' => $workspaceId,
        'user_id' => $student->id,
        'section_id' => $section->id,
        'subject' => 'Mathematics',
        'period' => 'Finals',
        'score' => 75.0,
        'max_score' => 100.0,
        'recorded_by' => $admin->id,
    ]);

    $tool = new UpdateGradeTool;
    $result = (string) $tool->handle(new Request([
        'grade_id' => $grade->id,
        'score' => 85.5,
        'remarks' => 'Grade corrected after recalculation',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'update_grade')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $grade->refresh();
    expect((float) $grade->score)->toBe(85.5)
        ->and($grade->remarks)->toBe('Grade corrected after recalculation');
});

it('stages and deletes a student grade with admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create();
    $section = Section::factory()->create(['workspace_id' => $workspaceId]);

    $grade = Grade::factory()->create([
        'workspace_id' => $workspaceId,
        'user_id' => $student->id,
        'section_id' => $section->id,
        'subject' => 'Physical Education',
        'period' => 'Prelim',
        'score' => 90.0,
        'max_score' => 100.0,
    ]);

    $tool = new DeleteGradeTool;
    $result = (string) $tool->handle(new Request([
        'grade_id' => $grade->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_grade')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Grade::query()->whereKey($grade->id)->exists())->toBeFalse();
});

it('stages and grades an exam submission with admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create(['name' => 'Basilio']);
    $exam = Exam::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Chemistry Practical',
    ]);
    $part = ExamPart::factory()->create(['exam_id' => $exam->id]);

    $submission = ExamSubmission::factory()->create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'status' => 'pending_review',
        'score' => null,
    ]);

    $tool = new GradeSubmissionTool;
    $result = (string) $tool->handle(new Request([
        'submission_id' => $submission->id,
        'score' => 95.0,
        'feedback' => 'Excellent work on chemical equations.',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'grade_submission')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $submission->refresh();
    expect((float) $submission->score)->toBe(95.0)
        ->and($submission->status)->toBe('graded')
        ->and($submission->feedback)->toBe('Excellent work on chemical equations.');
});

it('stages and creates a new user account with password and section enrollment upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'Grade 11 - Newton']);

    $tool = new CreateUserTool;
    $result = (string) $tool->handle(new Request([
        'name' => 'Elias Salome',
        'email' => 'elias.salome@example.com',
        'password' => 'secretP@ssword123',
        'is_admin' => false,
        'section_ids' => (string) $section->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_user')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $created = User::query()->where('email', 'elias.salome@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Elias Salome')
        ->and(Hash::check('secretP@ssword123', $created->password))->toBeTrue()
        ->and($created->is_admin)->toBeFalse()
        ->and($created->workspaces()->whereKey($workspaceId)->exists())->toBeTrue()
        ->and($created->sections()->whereKey($section->id)->exists())->toBeTrue();
});

it('stages and updates an existing user account upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create(['name' => 'Crispin']);
    $student->joinWorkspace($workspaceId);

    $tool = new UpdateUserTool;
    $result = (string) $tool->handle(new Request([
        'user_id' => $student->id,
        'name' => 'Crispin De Los Santos',
        'is_banned' => true,
        'ban_reason' => 'Academic dishonesty investigation',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'update_user')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $student->refresh();
    expect($student->name)->toBe('Crispin De Los Santos')
        ->and($student->is_banned)->toBeTrue()
        ->and($student->ban_reason)->toBe('Academic dishonesty investigation');
});

it('stages and resets a user password upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create(['password' => 'oldPassword123']);
    $student->joinWorkspace($workspaceId);

    $tool = new ResetUserPasswordTool;
    $result = (string) $tool->handle(new Request([
        'user_id' => $student->id,
        'new_password' => 'brandNewSecurePass456',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'reset_user_password')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $student->refresh();
    expect(Hash::check('brandNewSecurePass456', $student->password))->toBeTrue();
});

it('stages and deletes a user account upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $student = User::factory()->create();
    $student->joinWorkspace($workspaceId);

    $tool = new DeleteUserTool;
    $result = (string) $tool->handle(new Request([
        'user_id' => $student->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_user')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(User::query()->whereKey($student->id)->exists())->toBeFalse();
});

it('stages and creates a class section upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new CreateSectionTool;
    $result = (string) $tool->handle(new Request([
        'name' => 'Grade 12 - STEM Diamond',
        'school_level' => 'senior_high',
        'leaderboard_enabled' => true,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_section')->firstOrFail();
    expect($action->preview['changes'])->toContain([
        'field' => 'Workspace',
        'before' => null,
        'after' => $admin->currentWorkspace->name,
    ]);

    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $section = Section::query()->where('name', 'Grade 12 - STEM Diamond')->first();
    expect($section)->not->toBeNull()
        ->and($section->school_level)->toBe('senior_high')
        ->and($section->leaderboard_enabled)->toBeTrue()
        ->and($section->join_code)->not->toBeEmpty()
        ->and($section->workspace_id)->toBe($admin->currentWorkspace->id);
});

it('stages and deletes a class section upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'To Be Removed Section']);

    $tool = new DeleteSectionTool;
    $result = (string) $tool->handle(new Request([
        'section_id' => $section->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_section')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Section::query()->whereKey($section->id)->exists())->toBeFalse();
});

it('stages and deletes an assignment upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $assignment = Assignment::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Deprecated Project',
        'description' => 'Remove this assignment.',
        'due_date' => now()->addWeek(),
    ]);

    $tool = new DeleteAssignmentTool;
    $result = (string) $tool->handle(new Request([
        'assignment_id' => $assignment->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_assignment')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Assignment::query()->whereKey($assignment->id)->exists())->toBeFalse();
});

it('stages and deletes an announcement upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $announcement = Announcement::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Old Announcement',
        'description' => 'No longer relevant.',
    ]);

    $tool = new DeleteAnnouncementTool;
    $result = (string) $tool->handle(new Request([
        'announcement_id' => $announcement->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'delete_announcement')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeFalse();
});

it('provides comprehensive workspace inspection tools for admin', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $course = Course::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'name' => 'Data Science 101',
        'total_lessons' => 12,
    ]);

    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Section Gamma',
    ]);

    $assignment = Assignment::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Midterm Essay',
        'due_date' => now()->addDays(5),
    ]);

    $announcement = Announcement::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Campus Closed Tomorrow',
        'description' => 'Due to typhoon.',
    ]);

    $material = LearningMaterial::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Lecture Slide 1',
        'status' => LearningMaterial::STATUS_PUBLISHED,
    ]);

    $task = ActivityTask::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'section_id' => $section->id,
        'title' => 'Lab Experiment 1',
        'term' => 'Midterm',
        'max_points' => 50,
    ]);

    $coursesOut = (string) (new CoursesAdminTool)->handle(new Request([]));
    $sectionsOut = (string) (new SectionsAdminTool)->handle(new Request([]));
    $assignmentsOut = (string) (new AssignmentsAdminTool)->handle(new Request([]));
    $announcementsOut = (string) (new AnnouncementsAdminTool)->handle(new Request([]));
    $materialsOut = (string) (new LearningMaterialsAdminTool)->handle(new Request([]));
    $tasksOut = (string) (new ActivityTasksAdminTool)->handle(new Request([]));

    expect($coursesOut)->toContain('Data Science 101')
        ->and($sectionsOut)->toContain('Section Gamma')
        ->and($assignmentsOut)->toContain('Midterm Essay')
        ->and($announcementsOut)->toContain('Campus Closed Tomorrow')
        ->and($materialsOut)->toContain('Lecture Slide 1')
        ->and($tasksOut)->toContain('Lab Experiment 1');
});

it('stages, updates, and deletes courses upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    // 1. Create Course
    $createTool = new CreateCourseTool;
    $result = (string) $createTool->handle(new Request([
        'name' => 'Machine Learning Specialization',
        'description' => 'Hands on neural networks.',
        'total_lessons' => 20,
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $createAction = PendingAiAction::query()->where('action_type', 'create_course')->firstOrFail();
    $this->postJson("/api/ai-actions/{$createAction->public_id}/approve", [
        'nonce' => pendingActionNonce($createAction),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $course = Course::query()->where('workspace_id', $workspaceId)->where('name', 'Machine Learning Specialization')->firstOrFail();
    expect($course->total_lessons)->toBe(20);

    // 2. Update Course
    $updateTool = new UpdateCourseTool;
    $result = (string) $updateTool->handle(new Request([
        'course_id' => $course->id,
        'name' => 'Advanced Machine Learning',
        'total_lessons' => 25,
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $updateAction = PendingAiAction::query()->where('action_type', 'update_course')->latest('id')->firstOrFail();
    $this->postJson("/api/ai-actions/{$updateAction->public_id}/approve", [
        'nonce' => pendingActionNonce($updateAction),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $course->refresh();
    expect($course->name)->toBe('Advanced Machine Learning')
        ->and($course->total_lessons)->toBe(25);

    // 3. Delete Course
    $deleteTool = new DeleteCourseTool;
    $result = (string) $deleteTool->handle(new Request([
        'course_id' => $course->id,
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $deleteAction = PendingAiAction::query()->where('action_type', 'delete_course')->latest('id')->firstOrFail();
    $this->postJson("/api/ai-actions/{$deleteAction->public_id}/approve", [
        'nonce' => pendingActionNonce($deleteAction),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Course::query()->whereKey($course->id)->exists())->toBeFalse();
});

it('stages and updates class section upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Old Section Name',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'leaderboard_enabled' => false,
    ]);

    $tool = new UpdateSectionTool;
    $result = (string) $tool->handle(new Request([
        'section_id' => $section->id,
        'name' => 'Renamed Section A',
        'leaderboard_enabled' => true,
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'update_section')->firstOrFail();
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => pendingActionNonce($action),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $section->refresh();
    expect($section->name)->toBe('Renamed Section A')
        ->and((bool) $section->leaderboard_enabled)->toBeTrue();
});

it('stages and updates assignments and announcements upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $assignment = Assignment::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Draft Essay',
        'due_date' => now()->addDays(2),
    ]);

    $announcement = Announcement::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Initial Title',
        'description' => 'Initial body.',
    ]);

    // Update assignment
    $assignTool = new UpdateAssignmentTool;
    $assignTool->handle(new Request([
        'assignment_id' => $assignment->id,
        'title' => 'Final Term Paper',
    ]));
    $assignAction = PendingAiAction::query()->where('action_type', 'update_assignment')->firstOrFail();
    $this->postJson("/api/ai-actions/{$assignAction->public_id}/approve", [
        'nonce' => pendingActionNonce($assignAction),
    ])->assertOk();
    $assignment->refresh();
    expect($assignment->title)->toBe('Final Term Paper');

    // Update announcement
    $annTool = new UpdateAnnouncementTool;
    $annTool->handle(new Request([
        'announcement_id' => $announcement->id,
        'title' => 'Updated Notice Title',
    ]));
    $annAction = PendingAiAction::query()->where('action_type', 'update_announcement')->firstOrFail();
    $this->postJson("/api/ai-actions/{$annAction->public_id}/approve", [
        'nonce' => pendingActionNonce($annAction),
    ])->assertOk();
    $announcement->refresh();
    expect($announcement->title)->toBe('Updated Notice Title');
});

it('stages, creates, and deletes learning materials upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $createTool = new CreateLearningMaterialTool;
    $result = (string) $createTool->handle(new Request([
        'title' => 'Chemistry Syllabus',
        'description' => 'Comprehensive guide.',
        'status' => 'published',
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $createAction = PendingAiAction::query()->where('action_type', 'create_learning_material')->firstOrFail();
    $this->postJson("/api/ai-actions/{$createAction->public_id}/approve", [
        'nonce' => pendingActionNonce($createAction),
    ])->assertOk();

    $material = LearningMaterial::query()->where('workspace_id', $workspaceId)->where('title', 'Chemistry Syllabus')->firstOrFail();
    expect($material->isPublished())->toBeTrue();

    $deleteTool = new DeleteLearningMaterialTool;
    $deleteTool->handle(new Request(['material_id' => $material->id]));
    $deleteAction = PendingAiAction::query()->where('action_type', 'delete_learning_material')->firstOrFail();
    $this->postJson("/api/ai-actions/{$deleteAction->public_id}/approve", [
        'nonce' => pendingActionNonce($deleteAction),
    ])->assertOk();

    expect(LearningMaterial::query()->whereKey($material->id)->exists())->toBeFalse();
});

it('stages, creates, and deletes activity tasks upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $section = Section::factory()->create(['workspace_id' => $workspaceId]);

    $createTool = new CreateActivityTaskTool;
    $result = (string) $createTool->handle(new Request([
        'section_id' => $section->id,
        'title' => 'Weekly Quiz 1',
        'term' => 'Midterm',
        'max_points' => 25,
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $createAction = PendingAiAction::query()->where('action_type', 'create_activity_task')->firstOrFail();
    $this->postJson("/api/ai-actions/{$createAction->public_id}/approve", [
        'nonce' => pendingActionNonce($createAction),
    ])->assertOk();

    $task = ActivityTask::query()->where('workspace_id', $workspaceId)->where('title', 'Weekly Quiz 1')->firstOrFail();
    expect((float) $task->max_points)->toBe(25.0);

    $deleteTool = new DeleteActivityTaskTool;
    $deleteTool->handle(new Request(['task_id' => $task->id]));
    $deleteAction = PendingAiAction::query()->where('action_type', 'delete_activity_task')->firstOrFail();
    $this->postJson("/api/ai-actions/{$deleteAction->public_id}/approve", [
        'nonce' => pendingActionNonce($deleteAction),
    ])->assertOk();

    expect(ActivityTask::query()->whereKey($task->id)->exists())->toBeFalse();
});

it('stages and awards bonus student XP upon admin approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $student = User::factory()->create();
    $student->workspaces()->attach($workspaceId, ['role' => 'student']);

    $tool = new AwardStudentXpTool;
    $result = (string) $tool->handle(new Request([
        'student_id' => $student->id,
        'amount_xp' => 75,
        'amount_points' => 30,
        'reason' => 'Outstanding laboratory leadership',
    ]));
    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'award_student_xp')->firstOrFail();
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => pendingActionNonce($action),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $history = $student->gamificationHistories()->latest('id')->firstOrFail();
    expect((float) $history->amount_xp)->toBe(75.0)
        ->and((float) $history->amount_points)->toBe(30.0)
        ->and($history->description)->toBe('Outstanding laboratory leadership')
        ->and($history->awarded_by)->toBe($admin->id);
});

it('renders the real-time agent activity console and inline adjustment action handlers', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(AdminAiChat::class)
        ->assertOk()
        ->assertSee('msg.activity')
        ->assertSee('sendAdjustment(action)')
        ->assertSee('handleToolCallEvent')
        ->assertSee('handleToolResultEvent')
        ->assertSee('Echo Guard Active')
        ->assertSee('Needs you')
        ->assertSee('action._loading')
        ->assertSee('Approval window expired');
});

it('approves draft exam creation even when description and duration are omitted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $service = app(PendingAiActionService::class);
    $action = $service->stage(
        'create_exam',
        'Minimal Exam Title',
        'Staged minimal exam without description',
        [
            'title' => 'Minimal Exam Title',
            'exam_date' => now()->addDays(2)->toIso8601String(),
        ],
        [['field' => 'Title', 'before' => null, 'after' => 'Minimal Exam Title']],
    );

    $indexRes = $this->getJson('/api/ai-actions')->assertOk();
    $nonce = $indexRes->json('data.0.nonce');
    expect($nonce)->toBeString()->toHaveLength(64);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $exam = Exam::query()->where('title', 'Minimal Exam Title')->firstOrFail();
    expect($exam->workspace_id)->toBe($workspaceId)
        ->and($exam->description)->toBeNull()
        ->and($exam->duration_minutes)->toBe(60);
});

it('approves draft exam creation with section_name fallback and bundled questions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Section Z',
    ]);

    $service = app(PendingAiActionService::class);
    $action = $service->stage(
        'create_exam',
        'Create draft exam',
        'Create the draft exam "Computer Networking" for Section Z (new section).',
        [
            'title' => 'Computer Networking',
            'section_name' => 'Section Z',
            'exam_date' => now()->addDays(1)->toIso8601String(),
            'questions' => [
                [
                    'text' => 'What layer is IP in the OSI model?',
                    'type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [
                        ['text' => 'Network Layer', 'is_correct' => true],
                        ['text' => 'Transport Layer', 'is_correct' => false],
                    ],
                ],
                [
                    'text' => 'Which protocol is connection-oriented?',
                    'type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [
                        ['text' => 'TCP', 'is_correct' => true],
                        ['text' => 'UDP', 'is_correct' => false],
                    ],
                ],
            ],
        ],
        [['field' => 'Title', 'before' => null, 'after' => 'Computer Networking']],
    );

    $nonce = pendingActionNonce($action);

    $approveRes = $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect($approveRes->json('data.result'))->toContain('2 question(s) attached');

    $exam = Exam::query()->where('title', 'Computer Networking')->firstOrFail();
    expect($exam->workspace_id)->toBe($workspaceId)
        ->and($exam->section_id)->toBe($section->id)
        ->and($exam->parts)->toHaveCount(1);

    $part = $exam->parts->first();
    expect($part->questions)->toHaveCount(2)
        ->and($part->questions[0]['text'])->toBe('What layer is IP in the OSI model?')
        ->and($part->questions[0]['options'][0]['text'])->toBe('Network Layer')
        ->and($part->questions[0]['options'][0]['is_correct'])->toBeTrue();
});

it('approves draft exam creation with mixed question types creating distinct exam parts', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $service = app(PendingAiActionService::class);
    $action = $service->stage(
        'create_exam',
        'Create draft exam',
        'Create mixed exam with multiple choice and essay.',
        [
            'title' => 'Mixed Midterm Exam',
            'exam_date' => now()->addDays(1)->toIso8601String(),
            'questions' => [
                [
                    'text' => 'What does HTTP stand for?',
                    'type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [
                        ['text' => 'Hypertext Transfer Protocol', 'is_correct' => true],
                        ['text' => 'High Text Transfer Protocol', 'is_correct' => false],
                    ],
                ],
                [
                    'text' => 'Explain the handshake mechanism in TCP.',
                    'type' => 'essay',
                    'points' => 5,
                ],
            ],
        ],
        [['field' => 'Title', 'before' => null, 'after' => 'Mixed Midterm Exam']],
    );

    $nonce = pendingActionNonce($action);

    $approveRes = $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect($approveRes->json('data.result'))->toContain('2 question(s) across 2 parts attached');

    $exam = Exam::query()->where('title', 'Mixed Midterm Exam')->firstOrFail();
    expect($exam->parts)->toHaveCount(2);

    $part1 = $exam->parts->firstWhere('sort_order', 1);
    expect($part1->type)->toBe('multiple_choice')
        ->and($part1->title)->toBe('Part 1: Multiple Choice')
        ->and($part1->questions)->toHaveCount(1);

    $part2 = $exam->parts->firstWhere('sort_order', 2);
    expect($part2->type)->toBe('essay')
        ->and($part2->title)->toBe('Part 2: Essay')
        ->and($part2->points)->toBe(5)
        ->and($part2->questions)->toHaveCount(1);
});

it('automatically assigns a valid UUIDv7 to a chat session upon creation', function () {
    $admin = User::factory()->admin()->create();
    $session = $admin->chatSessions()->create([
        'title' => 'Biology Lesson Plan',
    ]);

    expect($session->uuid)->not->toBeNull()
        ->and(Str::isUuid($session->uuid))->toBeTrue();
});

it('returns both id and uuid when creating a new chat via api/chats', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/chats')
        ->assertOk()
        ->assertJsonStructure([
            'session' => ['id', 'uuid'],
        ]);

    $uuid = $response->json('session.uuid');
    expect(Str::isUuid($uuid))->toBeTrue();

    $session = ChatSession::query()->where('uuid', $uuid)->first();
    expect($session)->not->toBeNull()
        ->and($session->user_id)->toBe($admin->id);
});

it('resolves chat sessions via route model binding by both UUID and integer ID', function () {
    $admin = User::factory()->admin()->create();
    $session = $admin->chatSessions()->create([
        'title' => 'Physics Calculations',
    ]);
    $session->messages()->create([
        'role' => 'user',
        'content' => 'What is the speed of light?',
    ]);

    // Resolved by UUID
    $this->actingAs($admin)
        ->getJson("/api/chats/{$session->uuid}/messages")
        ->assertOk()
        ->assertJsonPath('session.id', $session->id)
        ->assertJsonPath('session.uuid', $session->uuid)
        ->assertJsonPath('session.title', 'Physics Calculations');

    // Resolved by legacy integer ID
    $this->actingAs($admin)
        ->getJson("/api/chats/{$session->id}/messages")
        ->assertOk()
        ->assertJsonPath('session.id', $session->id)
        ->assertJsonPath('session.uuid', $session->uuid);
});

it('selects initial active session when deep-linked via ?c=<uuid> and ?session=<uuid>', function () {
    $admin = User::factory()->admin()->create();
    $session1 = $admin->chatSessions()->create(['title' => 'History Essay Discussion']);
    $session2 = $admin->chatSessions()->create(['title' => 'Algebra Trigonometry Review']);

    $this->actingAs($admin);

    // Deep-linked with ?c=<uuid>
    $this->get("/admin/ai-chat?c={$session1->uuid}")
        ->assertOk()
        ->assertSee($session1->uuid)
        ->assertSee('History Essay Discussion');

    // Deep-linked with ?session=<uuid> fallback
    $this->get("/admin/ai-chat?session={$session2->uuid}")
        ->assertOk()
        ->assertSee($session2->uuid)
        ->assertSee('Algebra Trigonometry Review');
});

it('prepends an older deep-linked session to initial sessions when not in top 30', function () {
    $admin = User::factory()->admin()->create();

    // Create 35 sessions, the target one is the oldest
    $oldSession = $admin->chatSessions()->create([
        'title' => 'Oldest Important Chat',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    for ($i = 1; $i <= 32; $i++) {
        $admin->chatSessions()->create([
            'title' => "Recent Chat {$i}",
            'created_at' => now()->subMinutes(35 - $i),
            'updated_at' => now()->subMinutes(35 - $i),
        ]);
    }

    $this->actingAs($admin);

    // Without query param, oldest chat is not in top 30
    $this->get('/admin/ai-chat')
        ->assertOk()
        ->assertDontSee('Oldest Important Chat');

    // With ?c=<uuid>, it is fetched and included in initialSessions
    $this->get("/admin/ai-chat?c={$oldSession->uuid}")
        ->assertOk()
        ->assertSee('Oldest Important Chat')
        ->assertSee($oldSession->uuid);
});

it('isolates chat session deep links so users cannot access other users chats', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();

    $session1 = $admin1->chatSessions()->create(['title' => 'Secret Admin 1 Chat']);

    // Admin 2 tries to access Admin 1's chat messages via UUID
    $this->actingAs($admin2)
        ->getJson("/api/chats/{$session1->uuid}/messages")
        ->assertNotFound();

    // Admin 2 opens deep-link: Admin 1's chat should not be loaded into Admin 2's session
    $this->actingAs($admin2)
        ->get("/admin/ai-chat?c={$session1->uuid}")
        ->assertOk()
        ->assertDontSee('Secret Admin 1 Chat');
});

it('filters pending AI actions by session UUID', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $session = $admin->chatSessions()->create(['title' => 'Quiz Creation Chat']);

    $workspaceId = app(WorkspaceContext::class)->id();
    $exam = Exam::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Exam to Delete',
    ]);

    $action = app(PendingAiActionService::class)->stage(
        type: 'delete_exam',
        title: 'Delete Exam',
        summary: 'Delete Exam to Delete',
        payload: ['exam_id' => $exam->id],
        preview: ['field' => 'Title', 'before' => $exam->title, 'after' => null],
        chatSessionId: $session->id,
    );

    // Query pending actions by session UUID
    $this->getJson("/api/ai-actions?session_id={$session->uuid}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $action->public_id);

    // Query by integer ID also works
    $this->getJson("/api/ai-actions?session_id={$session->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('stages and creates an exam with auto-inferred term and blocked students upon approval', function () {
    $admin = User::factory()->admin()->create();
    $student1 = User::factory()->create(['name' => 'Alice Student']);
    $student2 = User::factory()->create(['name' => 'Bob Blocked']);
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'name' => 'BSCS 3-A',
    ]);

    $tool = new CreateExamTool;
    // Test title with "Prelim" -> should auto-infer term 'Prelim'
    // blocked_students passes Bob's name -> should resolve to student2 ID
    $result = (string) $tool->handle(new Request([
        'title' => 'Prelim Algorithms Exam',
        'section_id' => $section->id,
        'blocked_students' => ['Bob Blocked'],
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_exam')->firstOrFail();
    expect($action->payload['term'])->toBe('Prelim');
    expect($action->payload['blocked_user_ids'])->toContain($student2->id);

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $createdExam = Exam::query()->where('title', 'Prelim Algorithms Exam')->firstOrFail();
    expect($createdExam->term)->toBe('Prelim');
    expect($createdExam->duration_minutes)->toBe(60);
    expect($createdExam->blockedUsers->pluck('id')->all())->toContain($student2->id);
    expect($createdExam->blockedUsers->pluck('id')->all())->not->toContain($student1->id);
});

it('updates exam term and blocked students upon approval', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'Charlie Student']);
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $exam = Exam::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Midterm Biology',
        'term' => 'Midterm',
    ]);

    $tool = new UpdateExamTool;
    $result = (string) $tool->handle(new Request([
        'exam_id' => $exam->id,
        'term' => 'Final',
        'blocked_user_ids' => [$student->id],
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'update_exam')->firstOrFail();
    $nonce = pendingActionNonce($action);

    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $exam->refresh();
    expect($exam->term)->toBe('Final');
    expect($exam->blockedUsers->pluck('id')->all())->toContain($student->id);
});

it('creates assignment defaulting due date and workspace sections when omitted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section1 = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'Section Alpha']);
    $section2 = Section::factory()->create(['workspace_id' => $workspaceId, 'name' => 'Section Beta']);

    $tool = new CreateAssignmentTool;
    // Omit section_ids and due_date
    $result = (string) $tool->handle(new Request([
        'title' => 'Research Paper 1',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_assignment')->firstOrFail();
    expect($action->payload['section_ids'])->toContain($section1->id, $section2->id);
    expect($action->payload['due_date'])->not->toBeEmpty();

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $assignment = Assignment::query()->where('title', 'Research Paper 1')->firstOrFail();
    expect($assignment->sections->pluck('id')->all())->toContain($section1->id, $section2->id);
});

it('creates activity task with default term, due date, and workspace section', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'name' => 'BSIT 1-A',
    ]);

    $tool = new CreateActivityTaskTool;
    // Title mentions Prelim, omit section_id and due_date
    $result = (string) $tool->handle(new Request([
        'title' => 'Prelim Lab Exercise',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_activity_task')->firstOrFail();
    expect($action->payload['term'])->toBe('Prelim');
    expect($action->payload['section_id'])->toBe($section->id);
    expect($action->payload['due_date'])->not->toBeEmpty();

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $task = ActivityTask::query()->where('title', 'Prelim Lab Exercise')->firstOrFail();
    expect($task->term)->toBe('Prelim');
    expect($task->section_id)->toBe($section->id);
});

it('records grade auto-resolving section and default term from student enrollment', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'Diana Student']);
    $this->actingAs($admin);

    $workspaceId = app(WorkspaceContext::class)->id();
    $section = Section::factory()->create([
        'workspace_id' => $workspaceId,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'name' => 'Math Section',
    ]);
    $section->users()->attach($student->id);

    $tool = new RecordGradeTool;
    // Omit section_id, period, subject
    $result = (string) $tool->handle(new Request([
        'student_id' => $student->id,
        'score' => 95,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'record_grade')->firstOrFail();
    expect($action->payload['section_id'])->toBe($section->id);
    expect($action->payload['period'])->toBe('Midterm');
    expect($action->payload['subject'])->toBe('Math Section');

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $grade = Grade::query()->where('user_id', $student->id)->firstOrFail();
    expect((float) $grade->score)->toBe(95.0);
    expect($grade->period)->toBe('Midterm');
});

it('creates section auto-inferring senior high school level and configuring activity record terms', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new CreateSectionTool;
    // Section name matches SHS / Grade 11
    $result = (string) $tool->handle(new Request([
        'name' => 'Grade 11 - STEM Einstein',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_section')->firstOrFail();
    expect($action->payload['school_level'])->toBe(Section::SCHOOL_LEVEL_SENIOR_HIGH);

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $createdSection = Section::query()->where('name', 'Grade 11 - STEM Einstein')->firstOrFail();
    expect($createdSection->school_level)->toBe(Section::SCHOOL_LEVEL_SENIOR_HIGH);
    expect($createdSection->activity_record_enabled)->toBeTrue();
    expect($createdSection->activity_record_terms)->toContain('First Semester - 1st Quarter');
});

it('posts announcement defaulting description to title when omitted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new PostAnnouncementTool;
    $result = (string) $tool->handle(new Request([
        'title' => 'Campus closed due to typhoon',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'post_announcement')->firstOrFail();
    expect($action->payload['description'])->toBe('Campus closed due to typhoon');

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $announcement = Announcement::query()->where('title', 'Campus closed due to typhoon')->firstOrFail();
    expect($announcement->description)->toBe('Campus closed due to typhoon');
});

it('creates user account defaulting password when omitted', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new CreateUserTool;
    $result = (string) $tool->handle(new Request([
        'name' => 'New Student Auto',
        'email' => 'autostudent@example.com',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'create_user')->firstOrFail();
    expect($action->payload['password'])->toBe('Student123!');

    $nonce = pendingActionNonce($action);
    $this->postJson("/api/ai-actions/{$action->public_id}/approve", [
        'nonce' => $nonce,
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $created = User::query()->where('email', 'autostudent@example.com')->firstOrFail();
    expect(Hash::check('Student123!', $created->password))->toBeTrue();
});
