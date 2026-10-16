<?php

/**
 * Admin chat operations tool tests.
 *
 * These write tools can only stage immutable, nonce-protected actions; they
 * never trust model-provided confirmation and only a human approval executes
 * the change. Every flow below stages -> asserts nothing happened -> approves
 * -> asserts the exact write.
 */

use App\Ai\Tools\ManageBadgesRewardsTool;
use App\Ai\Tools\ManageExamBlocksTool;
use App\Ai\Tools\ManageLessonQuizTool;
use App\Ai\Tools\ManageLessonTool;
use App\Ai\Tools\ReplySupportTicketTool;
use App\Ai\Tools\SeasonAdminTool;
use App\Ai\Tools\SendNotificationTool;
use App\Models\Badge;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\ExamUserBlock;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\PendingAiAction;
use App\Models\Reward;
use App\Models\Season;
use App\Models\Section;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\StudentActivityNotification;
use App\Services\ExamBlockService;
use App\Services\PendingAiActionService;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Tools\Request;

function adminOpsNonce(PendingAiAction $action): string
{
    return app(PendingAiActionService::class)->present($action)['nonce'];
}

function approveAdminOpsAction(PendingAiAction $action): void
{
    test()->postJson(route('ai-actions.approve', $action), ['nonce' => adminOpsNonce($action)])
        ->assertOk()
        ->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);
}

it('updates exam blocked students end to end and scopes exams to the workspace', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $this->actingAs($adminA);

    $section = Section::factory()->forSeason($season)->create();
    $student1 = User::factory()->create(['name' => 'Linda Cruz']);
    $student2 = User::factory()->create(['name' => 'Bob Zephyr']);
    $section->users()->attach([$student1->id, $student2->id]);
    $exam = Exam::factory()->create(['section_id' => $section->id, 'status' => 'published']);

    $result = (string) (new ManageExamBlocksTool)->handle(new Request([
        'exam_id' => $exam->id,
        'block_student_ids' => "{$student1->id},{$student2->id}",
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and(PendingAiAction::count())->toBe(1);

    $action = PendingAiAction::query()->where('action_type', 'manage_exam_blocks')->firstOrFail();

    expect($action->payload['blocked_user_ids'])->toEqualCanonicalizing([$student1->id, $student2->id])
        ->and(app(ExamBlockService::class)->blockedUserIds($exam))->toBe([]);

    approveAdminOpsAction($action);

    expect(app(ExamBlockService::class)->blockedUserIds($exam->refresh()))->toEqualCanonicalizing([$student1->id, $student2->id])
        ->and(ExamUserBlock::query()->where('exam_id', $exam->id)->where('user_id', $student1->id)->value('blocked_by'))->toBe($adminA->id);

    $unblock = (string) (new ManageExamBlocksTool)->handle(new Request([
        'exam_id' => $exam->id,
        'unblock_students' => 'Bob Zephyr',
    ]));

    expect($unblock)->toContain('PENDING HUMAN APPROVAL');

    $unblockAction = PendingAiAction::query()->where('action_type', 'manage_exam_blocks')->latest('id')->firstOrFail();
    approveAdminOpsAction($unblockAction);

    expect(app(ExamBlockService::class)->blockedUserIds($exam->refresh()))->toBe([$student1->id]);

    expect((string) (new ManageExamBlocksTool)->handle(new Request([
        'exam_id' => $exam->id,
        'block_student_ids' => (string) $student1->id,
    ])))->toContain('No changes were specified');

    $adminB = User::factory()->admin()->create();
    $this->actingAs($adminB);
    $foreignExam = Exam::factory()->create(['status' => 'published']);

    $this->actingAs($adminA);

    expect((string) (new ManageExamBlocksTool)->handle(new Request(['exam_id' => $foreignExam->id])))
        ->toContain('exam not found in this workspace');
});

it('sends notifications to a section only after human approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create(['name' => 'Section One']);
    $student1 = User::factory()->create(['name' => 'Nina One']);
    $student2 = User::factory()->create(['name' => 'Nina Two']);
    $section->users()->attach([$student1->id, $student2->id]);
    $outsider = User::factory()->create(['name' => 'Oscar Outside']);
    Section::factory()->create(['name' => 'Section Two'])->users()->attach($outsider->id);

    $tool = new SendNotificationTool;

    expect((string) $tool->handle(new Request(['message' => 'Hello'])))
        ->toContain('title is required')
        ->and((string) $tool->handle(new Request(['title' => str_repeat('x', 121), 'message' => 'Body'])))
        ->toContain('title is too long')
        ->and((string) $tool->handle(new Request(['title' => 'Hi', 'message' => 'Body', 'href' => 'dashboard'])))
        ->toContain('relative path')
        ->and((string) $tool->handle(new Request(['title' => 'Hi', 'message' => 'Body'])))
        ->toContain('specify section_id')
        ->and(PendingAiAction::count())->toBe(0);

    $emptySection = Section::factory()->create(['name' => 'Empty Section']);

    expect((string) $tool->handle(new Request([
        'title' => 'Hi',
        'message' => 'Body',
        'section_id' => $emptySection->id,
    ])))->toContain('no students matched');

    Notification::fake();

    $result = (string) $tool->handle(new Request([
        'title' => 'Enrollment closes Friday',
        'message' => 'Submit your requirements before the deadline.',
        'section_id' => $section->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'send_notification')->firstOrFail();

    expect($action->payload['student_ids'])->toEqualCanonicalizing([$student1->id, $student2->id])
        ->and($action->payload['title'])->toBe('Enrollment closes Friday')
        ->and($action->payload['href'])->toBe('/dashboard');

    approveAdminOpsAction($action);

    Notification::assertSentTo($student1, StudentActivityNotification::class);
    Notification::assertSentTo($student2, StudentActivityNotification::class);
    Notification::assertNotSentTo($outsider, StudentActivityNotification::class);
});

it('replies to support tickets and resolves them on approval', function () {
    $adminA = User::factory()->admin()->create();
    $this->actingAs($adminA);
    $workspaceId = app(WorkspaceContext::class)->id();

    $student = User::factory()->create(['name' => 'Tina Ticket']);
    $ticket = SupportTicket::query()->create([
        'user_id' => $student->id,
        'workspace_id' => $workspaceId,
        'subject' => 'Broken upload',
        'category' => 'technical',
        'priority' => 'high',
        'message' => 'Uploads fail.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $tool = new ReplySupportTicketTool;

    expect((string) $tool->handle(new Request([
        'ticket_id' => $ticket->id,
        'message' => 'Fix',
        'status' => 'bogus',
    ])))->toContain('status must be one of')
        ->and(PendingAiAction::count())->toBe(0);

    Notification::fake();

    $result = (string) $tool->handle(new Request([
        'ticket_id' => $ticket->id,
        'message' => 'We fixed the uploader. Please try again.',
        'status' => SupportTicket::STATUS_RESOLVED,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and($ticket->refresh()->status)->toBe(SupportTicket::STATUS_OPEN)
        ->and(SupportTicketReply::count())->toBe(0);

    $action = PendingAiAction::query()->where('action_type', 'reply_support_ticket')->firstOrFail();

    expect($action->payload['new_status'])->toBe(SupportTicket::STATUS_RESOLVED);

    approveAdminOpsAction($action);

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicket::STATUS_RESOLVED)
        ->and($ticket->resolved_by)->toBe($adminA->id)
        ->and($ticket->resolved_at)->not->toBeNull();

    $reply = SupportTicketReply::query()->where('support_ticket_id', $ticket->id)->firstOrFail();
    expect($reply->message)->toBe('We fixed the uploader. Please try again.')
        ->and($reply->user_id)->toBe($adminA->id);

    Notification::assertSentTo($student, StudentActivityNotification::class);

    $adminB = User::factory()->admin()->create();
    $this->actingAs($adminB);
    $foreignTicket = SupportTicket::query()->create([
        'user_id' => User::factory()->create()->id,
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'subject' => 'Foreign ticket',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Another workspace.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($adminA);

    expect((string) $tool->handle(new Request(['ticket_id' => $foreignTicket->id, 'message' => 'Sneaky reply'])))
        ->toContain('not found in this workspace');
});

it('creates and activates seasons through the approval flow', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $activeSeason = Season::factory()->active()->create(['name' => 'Current Season']);

    $tool = new SeasonAdminTool;

    expect((string) $tool->handle(new Request([
        'mode' => 'create',
        'name' => 'Bad Dates',
        'start_date' => '2026-05-01',
        'end_date' => '2026-04-01',
    ])))->toContain('end_date must be on or after start_date');

    expect((string) $tool->handle(new Request([
        'mode' => 'create',
        'name' => 'Conflicting Season',
        'start_date' => '2026-05-01',
        'is_active' => true,
    ])))->toContain('is already the active season');

    $result = (string) $tool->handle(new Request([
        'mode' => 'create',
        'name' => 'Season Alpha',
        'start_date' => '2026-06-01',
        'end_date' => '2026-08-01',
        'show_countdown_on_welcome' => true,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'season_admin')->firstOrFail();
    approveAdminOpsAction($action);

    $created = Season::query()->withoutGlobalScope('workspace')->where('name', 'Season Alpha')->firstOrFail();
    expect($created->workspace_id)->toBe($workspaceId)
        ->and($created->admin_id)->toBe($admin->id)
        ->and((bool) $created->is_active)->toBeFalse()
        ->and((bool) $created->show_countdown_on_welcome)->toBeTrue()
        ->and($created->start_date->toDateString())->toBe('2026-06-01');

    $inactive = Season::query()->withoutGlobalScope('workspace')->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'name' => 'Season Beta',
        'start_date' => now(),
        'end_date' => null,
        'is_active' => false,
    ]);

    $activate = (string) $tool->handle(new Request(['mode' => 'activate', 'season_id' => $inactive->id]));
    expect($activate)->toContain('PENDING HUMAN APPROVAL');

    $activateAction = PendingAiAction::query()->where('action_type', 'season_admin')->latest('id')->firstOrFail();
    approveAdminOpsAction($activateAction);

    expect((bool) $inactive->refresh()->is_active)->toBeTrue()
        ->and((bool) $activeSeason->refresh()->is_active)->toBeFalse();

    $deactivate = (string) $tool->handle(new Request(['mode' => 'deactivate', 'season_id' => $inactive->id]));
    expect($deactivate)->toContain('PENDING HUMAN APPROVAL');

    $deactivateAction = PendingAiAction::query()->where('action_type', 'season_admin')->latest('id')->firstOrFail();
    approveAdminOpsAction($deactivateAction);

    expect((bool) $inactive->refresh()->is_active)->toBeFalse();
});

it('manages badges and rewards through the approval flow', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $tool = new ManageBadgesRewardsTool;

    expect((string) $tool->handle(new Request([
        'entity' => 'badge',
        'operation' => 'create',
        'name' => 'Broken Badge',
        'required_level' => 0,
    ])))->toContain('required_level must be at least 1');

    $result = (string) $tool->handle(new Request([
        'entity' => 'badge',
        'operation' => 'create',
        'name' => 'Star Reader',
        'description' => 'Read ten books.',
        'required_level' => 3,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'manage_badges_rewards')->firstOrFail();
    approveAdminOpsAction($action);

    $badge = Badge::query()->where('name', 'Star Reader')->firstOrFail();
    expect($badge->workspace_id)->toBe($workspaceId)
        ->and($badge->admin_id)->toBe($admin->id)
        ->and($badge->required_level)->toBe(3);

    $student = User::factory()->create();
    $reward = Reward::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'name' => 'Movie Pass',
        'description' => 'Redeem a movie pass.',
        'points_cost' => 100,
    ]);
    $reward->users()->attach($student->id);

    $update = (string) $tool->handle(new Request([
        'entity' => 'reward',
        'operation' => 'update',
        'record_id' => $reward->id,
        'points_cost' => 50,
        'description' => 'Redeem two movie passes.',
    ]));

    expect($update)->toContain('PENDING HUMAN APPROVAL');

    $updateAction = PendingAiAction::query()->where('action_type', 'manage_badges_rewards')->latest('id')->firstOrFail();
    approveAdminOpsAction($updateAction);

    $reward->refresh();
    expect((int) $reward->points_cost)->toBe(50)
        ->and($reward->description)->toBe('Redeem two movie passes.');

    $delete = (string) $tool->handle(new Request([
        'entity' => 'reward',
        'operation' => 'delete',
        'record_id' => $reward->id,
    ]));

    expect($delete)->toContain('PENDING HUMAN APPROVAL');

    $deleteAction = PendingAiAction::query()->where('action_type', 'manage_badges_rewards')->latest('id')->firstOrFail();
    approveAdminOpsAction($deleteAction);

    expect(Reward::query()->count())->toBe(0)
        ->and(DB::table('reward_user')->count())->toBe(0);

    $adminB = User::factory()->admin()->create();
    $this->actingAs($adminB);
    $foreignBadge = Badge::query()->create([
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'admin_id' => $adminB->id,
        'name' => 'Foreign Badge',
        'required_level' => 1,
    ]);

    $this->actingAs($admin);

    expect((string) $tool->handle(new Request([
        'entity' => 'badge',
        'operation' => 'update',
        'record_id' => $foreignBadge->id,
        'required_level' => 2,
    ])))->toContain('Badge not found in this workspace');
});

it('creates lessons and authors lesson quizzes through the approval flow', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $course = Course::query()->create([
        'name' => 'Science 101',
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
    ]);
    $module = CourseModule::query()->create([
        'course_id' => $course->id,
        'title' => 'Module 1',
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
    ]);

    $lessonTool = new ManageLessonTool;

    expect((string) $lessonTool->handle(new Request([
        'operation' => 'create',
        'module_title' => 'Missing Module',
        'title' => 'Nope',
    ])))->toContain('course module matches');

    $result = (string) $lessonTool->handle(new Request([
        'operation' => 'create',
        'module_id' => $module->id,
        'title' => 'Photosynthesis',
        'content' => 'Leaves convert light into energy.',
        'video_url' => 'https://example.com/photosynthesis',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');

    $action = PendingAiAction::query()->where('action_type', 'manage_lesson')->firstOrFail();
    approveAdminOpsAction($action);

    $lesson = Lesson::query()->where('title', 'Photosynthesis')->firstOrFail();
    expect($lesson->workspace_id)->toBe($workspaceId)
        ->and($lesson->admin_id)->toBe($admin->id)
        ->and($lesson->course_module_id)->toBe($module->id)
        ->and($lesson->sort_order)->toBe(1);

    $update = (string) $lessonTool->handle(new Request([
        'operation' => 'update',
        'lesson_id' => $lesson->id,
        'title' => 'Photosynthesis 101',
        'sort_order' => 5,
    ]));

    expect($update)->toContain('PENDING HUMAN APPROVAL');

    $updateAction = PendingAiAction::query()->where('action_type', 'manage_lesson')->latest('id')->firstOrFail();
    approveAdminOpsAction($updateAction);

    $lesson->refresh();
    expect($lesson->title)->toBe('Photosynthesis 101')
        ->and($lesson->sort_order)->toBe(5);

    $quizTool = new ManageLessonQuizTool;

    expect((string) $quizTool->handle(new Request([
        'lesson_id' => $lesson->id,
        'questions' => 'not-json',
    ])))->toContain('must be a JSON array');

    expect((string) $quizTool->handle(new Request([
        'lesson_id' => $lesson->id,
        'questions' => json_encode([
            ['question' => 'Bad question', 'options' => [
                ['text' => 'A', 'is_correct' => true],
                ['text' => 'B', 'is_correct' => true],
            ]],
        ]),
    ])))->toContain('mark exactly one as correct');

    $quiz = (string) $quizTool->handle(new Request([
        'lesson_id' => $lesson->id,
        'questions' => json_encode([
            ['question' => 'What gas do plants release?', 'options' => [
                ['text' => 'Oxygen', 'is_correct' => true],
                ['text' => 'Helium', 'is_correct' => false],
            ]],
        ]),
    ]));

    expect($quiz)->toContain('PENDING HUMAN APPROVAL');

    $quizAction = PendingAiAction::query()->where('action_type', 'manage_lesson_quiz')->firstOrFail();
    approveAdminOpsAction($quizAction);

    $lessonQuiz = LessonQuiz::query()->where('lesson_id', $lesson->id)->firstOrFail();
    expect($lessonQuiz->questions)->toHaveCount(1)
        ->and($lessonQuiz->pass_score)->toBe(75)
        ->and($lessonQuiz->allowed_attempts)->toBe(0);

    $replace = (string) $quizTool->handle(new Request([
        'lesson_id' => $lesson->id,
        'questions' => json_encode([
            ['question' => 'Q1', 'options' => [['text' => 'A', 'is_correct' => true], ['text' => 'B', 'is_correct' => false]]],
            ['question' => 'Q2', 'options' => [['text' => 'C', 'is_correct' => false], ['text' => 'D', 'is_correct' => true]]],
        ]),
        'pass_score' => 80,
        'allowed_attempts' => 2,
    ]));

    expect($replace)->toContain('PENDING HUMAN APPROVAL');

    $replaceAction = PendingAiAction::query()->where('action_type', 'manage_lesson_quiz')->latest('id')->firstOrFail();
    approveAdminOpsAction($replaceAction);

    expect(LessonQuiz::query()->where('lesson_id', $lesson->id)->count())->toBe(1);

    $lessonQuiz->refresh();
    expect($lessonQuiz->questions)->toHaveCount(2)
        ->and($lessonQuiz->pass_score)->toBe(80)
        ->and($lessonQuiz->allowed_attempts)->toBe(2);

    $adminB = User::factory()->admin()->create();
    $this->actingAs($adminB);
    $foreignCourse = Course::query()->create([
        'name' => 'Foreign Course',
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'admin_id' => $adminB->id,
    ]);
    $foreignModule = CourseModule::query()->create([
        'course_id' => $foreignCourse->id,
        'title' => 'Foreign Module',
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'admin_id' => $adminB->id,
    ]);
    $foreignLesson = Lesson::query()->create([
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'admin_id' => $adminB->id,
        'course_module_id' => $foreignModule->id,
        'title' => 'Foreign Lesson',
        'sort_order' => 1,
    ]);

    $this->actingAs($admin);

    expect((string) $quizTool->handle(new Request([
        'lesson_id' => $foreignLesson->id,
        'questions' => json_encode([
            ['question' => 'Q', 'options' => [
                ['text' => 'A', 'is_correct' => true],
                ['text' => 'B', 'is_correct' => false],
            ]],
        ]),
    ])))->toContain('lesson not found in this workspace');
});

it('refuses ops write tools for non-admins', function () {
    $this->actingAs(User::factory()->create());

    expect((string) (new ManageExamBlocksTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new SendNotificationTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new ReplySupportTicketTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new SeasonAdminTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new ManageBadgesRewardsTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new ManageLessonTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new ManageLessonQuizTool)->handle(new Request([])))->toContain('Only admins')
        ->and(PendingAiAction::count())->toBe(0);
});
