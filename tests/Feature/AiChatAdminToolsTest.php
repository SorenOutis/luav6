<?php

/**
 * Admin chat tool tests.
 *
 * Read tools are workspace-scoped. Write tools can only stage immutable,
 * nonce-protected actions; they never trust model-provided confirmation and
 * only a human approval endpoint can execute a write.
 */

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Tools\CreateActivityTaskTool;
use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\CreateLearningMaterialTool;
use App\Ai\Tools\ExamsAdminTool;
use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\RecordActivityTaskScoreTool;
use App\Ai\Tools\RecordGradeTool;
use App\Ai\Tools\StudentsTool;
use App\Ai\Tools\SubmissionsToGradeTool;
use App\Ai\Tools\UpdateActivityTaskTool;
use App\Ai\Tools\UpdateAssignmentTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\UpdateLearningMaterialTool;
use App\Ai\Tools\UserActivityTool;
use App\Ai\Tools\WorkspaceOverviewTool;
use App\Models\ActivityTask;
use App\Models\AiActionAudit;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\LearningMaterial;
use App\Models\PendingAiAction;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use App\Services\PendingAiActionService;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
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
        ->and((new UserActivityTool)->handle(new Request([])))->toContain('Only admins')
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

it('finds students by partial first, middle, or last name in any word order', function () {
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->forSeason($season)->create();
    $angeline = User::factory()->create(['name' => 'Angeline Marquez Cabrera']);
    $angeline->sections()->attach($section->id, ['season_id' => $season->id]);
    $other = User::factory()->create(['name' => 'Benedict Cruz']);
    $other->sections()->attach($section->id, ['season_id' => $season->id]);

    $find = fn (string $search): string => (string) (new StudentsTool)->handle(new Request(['search' => $search]));

    expect($find('angeline'))->toContain('Angeline Marquez Cabrera')
        ->and($find('ANGELINE'))->toContain('Angeline Marquez Cabrera')
        ->and($find('cabrera'))->toContain('Angeline Marquez Cabrera')
        ->and($find('marquez'))->toContain('Angeline Marquez Cabrera')
        ->and($find('cabrera angeline'))->toContain('Angeline Marquez Cabrera')
        ->and($find('"angeline"'))->toContain('Angeline Marquez Cabrera')
        ->and($find('angeline'))->not->toContain('Benedict Cruz')
        ->and($find('benedict'))->toContain('Benedict Cruz')->not->toContain('Angeline Marquez Cabrera');
});

it('instructs the assistant to search students when the message is a bare name', function () {
    $instructions = (string) (new AdminAssistantAgent)->instructions();

    expect($instructions)->toContain('is a student search')
        ->and($instructions)->toContain('call `students` immediately with only that name as `search`')
        ->and((string) (new StudentsTool)->description())->toContain("just a person's name or part of a name");
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

function pendingAiActionNonce(PendingAiAction $action): string
{
    return app(PendingAiActionService::class)->present($action)['nonce'];
}

it('stages an exam action even when the model injects confirm true and writes only after a human approval', function () {
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $section = Section::factory()->forSeason($season)->create(['name' => 'Biology A']);
    $session = $admin->chatSessions()->create(['title' => 'Exam setup']);

    $toolResult = (new CreateExamTool(chatSessionId: $session->id))->handle(new Request([
        'title' => 'Cell Biology Quiz',
        'exam_date' => '2026-09-01 09:00',
        'duration_minutes' => 45,
        'section_id' => $section->id,
        // A prompt-injected legacy flag has no authority and is not in the schema.
        'confirm' => true,
    ]));

    $action = PendingAiAction::firstOrFail();

    expect($toolResult)->toContain('PENDING HUMAN APPROVAL')
        ->toContain($action->public_id)
        ->not->toContain(pendingAiActionNonce($action))
        ->and(Exam::count())->toBe(0)
        ->and($action->status)->toBe(PendingAiAction::STATUS_PENDING)
        ->and($action->chat_session_id)->toBe($session->id)
        ->and($action->payload['title'])->toBe('Cell Biology Quiz')
        ->and($action->preview['changes'])->toContain([
            'field' => 'Status',
            'before' => null,
            'after' => 'draft',
        ]);

    $nonce = pendingAiActionNonce($action);

    $this->postJson(route('ai-actions.approve', $action), ['nonce' => $nonce])
        ->assertOk()
        ->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    $exam = Exam::firstOrFail();
    expect($exam->title)->toBe('Cell Biology Quiz')
        ->and($exam->status)->toBe('draft')
        ->and($exam->workspace_id)->toBe($admin->current_workspace_id)
        ->and($exam->admin_id)->toBe($admin->id);

    // Replaying the same human request is idempotent.
    $this->postJson(route('ai-actions.approve', $action), ['nonce' => $nonce])
        ->assertOk()
        ->assertJsonPath('data.status', PendingAiAction::STATUS_EXECUTED);

    expect(Exam::count())->toBe(1)
        ->and(AiActionAudit::where('pending_ai_action_id', $action->id)->pluck('event')->all())
        ->toContain('created', 'approved', 'executed');
});

it('deduplicates identical pending tool calls in the same conversation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $session = $admin->chatSessions()->create(['title' => 'Announcement']);
    $request = new Request([
        'title' => 'Enrollment Week',
        'description' => 'Enrollment closes Friday.',
    ]);

    (new PostAnnouncementTool(chatSessionId: $session->id))->handle($request);
    (new PostAnnouncementTool(chatSessionId: $session->id))->handle($request);

    expect(PendingAiAction::count())->toBe(1)
        ->and(Announcement::count())->toBe(0)
        ->and(AiActionAudit::where('event', 'duplicate_request_deduplicated')->count())->toBe(1);
});

it('stages exam updates and rejects stale previews', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create(['title' => 'Midterm', 'status' => 'published']);

    $result = (new UpdateExamTool)->handle(new Request([
        'exam_id' => $exam->id,
        'status' => 'closed',
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and($exam->refresh()->status)->toBe('published');

    $action = PendingAiAction::firstOrFail();
    $nonce = pendingAiActionNonce($action);

    Exam::query()->withoutGlobalScope('workspace')->whereKey($exam->id)->update([
        'description' => 'Changed after preview',
        'updated_at' => now()->addMinute(),
    ]);

    $this->postJson(route('ai-actions.approve', $action), ['nonce' => $nonce])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This record changed after the preview was created. Ask Echo to prepare a fresh action before approving it.');

    expect($exam->refresh()->status)->toBe('published')
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_FAILED);
});

it('requires a valid server nonce and the action owner', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Enrollment Week',
        'description' => 'Enrollment closes Friday.',
    ]));
    $action = PendingAiAction::firstOrFail();

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => str_repeat('x', 64),
    ])->assertForbidden();

    expect(Announcement::count())->toBe(0)
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_PENDING)
        ->and(AiActionAudit::where('event', 'invalid_nonce')->count())->toBe(1);

    $nonce = pendingAiActionNonce($action);
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($otherAdmin)
        ->postJson(route('ai-actions.approve', $action), [
            'nonce' => $nonce,
        ])->assertNotFound();

    expect(Announcement::count())->toBe(0);
});

it('rejects or expires actions without applying writes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'First notice',
        'description' => 'This one will be rejected.',
    ]));
    $rejected = PendingAiAction::firstOrFail();

    $this->postJson(route('ai-actions.reject', $rejected), [
        'nonce' => pendingAiActionNonce($rejected),
    ])->assertOk()->assertJsonPath('data.status', PendingAiAction::STATUS_REJECTED);

    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Second notice',
        'description' => 'This one will expire.',
    ]));
    $expired = PendingAiAction::latest('id')->firstOrFail();
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->postJson(route('ai-actions.approve', $expired), [
        'nonce' => pendingAiActionNonce($expired),
    ])->assertStatus(410);

    expect(Announcement::count())->toBe(0)
        ->and($expired->refresh()->status)->toBe(PendingAiAction::STATUS_EXPIRED);
});

it('recovers abandoned executions for a safe nonce-backed retry', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Recovered notice',
        'description' => 'Run exactly once after recovery.',
    ]));
    $action = PendingAiAction::firstOrFail();
    $action->forceFill([
        'status' => PendingAiAction::STATUS_EXECUTING,
        'approved_at' => now()->subMinutes(11),
        'execution_token' => (string) str()->uuid(),
        'execution_started_at' => now()->subMinutes(11),
    ])->save();

    $response = $this->getJson(route('ai-actions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.status', PendingAiAction::STATUS_PENDING);

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => $response->json('data.0.nonce'),
    ])->assertOk();

    expect(Announcement::count())->toBe(1)
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_EXECUTED)
        ->and(AiActionAudit::where('event', 'stale_execution_recovered')->count())->toBe(1);
});

it('detects payload tampering before execution', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Safe title',
        'description' => 'Safe body',
    ]));
    $action = PendingAiAction::firstOrFail();
    $nonce = pendingAiActionNonce($action);
    $action->forceFill(['payload' => [
        ...$action->payload,
        'title' => 'Tampered title',
    ]])->save();

    $this->postJson(route('ai-actions.approve', $action), ['nonce' => $nonce])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The action payload failed its integrity check.');

    expect(Announcement::count())->toBe(0)
        ->and($action->refresh()->status)->toBe(PendingAiAction::STATUS_PENDING);
});

it('creates announcements and assignments only through approved cards in the active workspace', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $course = Course::query()->create(['name' => 'Biology 101']);
    $section = Section::factory()->create();

    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Enrollment Week',
        'description' => 'Enrollment is open until Friday.',
    ]));
    (new CreateAssignmentTool)->handle(new Request([
        'title' => 'Cell Model Project',
        'section_ids' => (string) $section->id,
        'course_id' => $course->id,
        'due_date' => '2026-08-25 23:59',
    ]));

    expect(Announcement::count())->toBe(0)
        ->and(Assignment::count())->toBe(0)
        ->and(PendingAiAction::count())->toBe(2);

    foreach (PendingAiAction::orderBy('id')->get() as $action) {
        $this->postJson(route('ai-actions.approve', $action), [
            'nonce' => pendingAiActionNonce($action),
        ])->assertOk();
    }

    expect(Announcement::count())->toBe(1)
        ->and(Assignment::count())->toBe(1)
        ->and(Announcement::first()->workspace_id)->toBe($admin->current_workspace_id)
        ->and(Assignment::first()->course_id)->toBe($course->id)
        ->and(Assignment::first()->sections->pluck('id')->all())->toBe([$section->id]);
});

it('refuses to stage an assignment with no target sections', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect((new CreateAssignmentTool)->handle(new Request([
        'title' => 'Nowhere Assignment',
        'due_date' => '2026-09-01',
    ])))->toContain('no sections exist in this workspace')
        ->and(PendingAiAction::count())->toBe(0);
});

it('rejects foreign workspace references while preparing actions', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->create();
    $foreignCourse = Course::query()->create(['name' => 'Foreign Course']);
    $foreignExam = Exam::factory()->create();

    $this->actingAs($adminA);

    expect((new CreateExamTool)->handle(new Request([
        'title' => 'Sneaky Exam',
        'exam_date' => '2026-09-01',
        'section_id' => $foreignSection->id,
    ])))->toContain('does not exist')
        ->and((new CreateAssignmentTool)->handle(new Request([
            'title' => 'Sneaky Assignment',
            'due_date' => '2026-09-01',
            'section_ids' => (string) $foreignSection->id,
            'course_id' => $foreignCourse->id,
        ])))->toContain('do not exist')
        ->and((new UpdateExamTool)->handle(new Request([
            'exam_id' => $foreignExam->id,
            'status' => 'closed',
        ])))->toContain('not found')
        ->and(PendingAiAction::count())->toBe(0);
});

it('never exposes admin write tools to students', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        new CreateAssignmentTool,
        new CreateExamTool,
        new CreateActivityTaskTool,
        new CreateLearningMaterialTool,
        new GenerateExamQuestionsTool,
        new PostAnnouncementTool,
        new RecordGradeTool,
        new UpdateActivityTaskTool,
        new UpdateAssignmentTool,
        new UpdateExamTool,
        new UpdateLearningMaterialTool,
        new RecordActivityTaskScoreTool,
    ] as $tool) {
        expect($tool->handle(new Request([])))->toContain('Only admins');
    }

    expect(PendingAiAction::count())->toBe(0);
});

it('binds super-admin actions to the active or inspected workspace shown in the preview', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenantAdmin = User::factory()->admin()->create();
    $ownWorkspaceId = $superAdmin->current_workspace_id;
    $tenantWorkspace = $tenantAdmin->currentWorkspace;
    $this->actingAs($superAdmin);

    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Platform team notice',
        'description' => 'Visible only in the super-admin workspace.',
    ]));
    $ownAction = PendingAiAction::firstOrFail();

    app(WorkspaceContext::class)->inspect($tenantWorkspace);
    (new PostAnnouncementTool)->handle(new Request([
        'title' => 'Tenant notice',
        'description' => 'Visible only in the inspected workspace.',
    ]));
    $tenantAction = PendingAiAction::latest('id')->firstOrFail();

    expect($ownAction->workspace_id)->toBe($ownWorkspaceId)
        ->and($tenantAction->workspace_id)->toBe($tenantWorkspace->id);

    foreach ([$ownAction, $tenantAction] as $action) {
        $this->postJson(route('ai-actions.approve', $action), [
            'nonce' => pendingAiActionNonce($action),
        ])->assertOk();
    }

    $announcements = Announcement::query()
        ->withoutGlobalScope('workspace')
        ->orderBy('id')
        ->get();
    expect($announcements->pluck('workspace_id')->all())->toBe([
        $ownWorkspaceId,
        $tenantWorkspace->id,
    ]);
});

it('lists exact action previews only for the authenticated owner and session', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $session = $admin->chatSessions()->create(['title' => 'Notice']);

    (new PostAnnouncementTool(chatSessionId: $session->id))->handle(new Request([
        'title' => 'Exact title',
        'description' => 'Exact body',
    ]));

    $this->getJson(route('ai-actions.index', ['session_id' => $session->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.changes.1.after', 'Exact title')
        ->assertJsonPath('data.0.changes.2.after', 'Exact body')
        ->assertJsonPath('data.0.workspace.name', $admin->currentWorkspace->name)
        ->assertJsonPath('data.0.status', PendingAiAction::STATUS_PENDING)
        ->assertJsonPath('data.0.nonce', pendingAiActionNonce(PendingAiAction::firstOrFail()));

    $other = User::factory()->admin()->create();
    $this->actingAs($other)
        ->getJson(route('ai-actions.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('validates parameters when recording activity task scores', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new RecordActivityTaskScoreTool;

    expect((string) $tool->handle(new Request([])))
        ->toContain('Error: student not found')
        ->and((string) $tool->handle(new Request(['student_id' => $admin->id])))
        ->toContain('Error: score is required and must be a number')
        ->and((string) $tool->handle(new Request(['student_id' => $admin->id, 'score' => -5])))
        ->toContain('Error: score cannot be negative')
        ->and((string) $tool->handle(new Request(['student_id' => $admin->id, 'score' => 20])))
        ->toContain('Error: task_id, task_ids, or task_title is required')
        ->and((string) $tool->handle(new Request(['all_students' => true, 'score' => 50])))
        ->toContain('Error: specify section_id or section_name to record scores for all section students')
        ->and(PendingAiAction::count())->toBe(0);
});

it('never stages grades for students outside the target section and handles foreign sections safely', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->forSeason($season)->create();

    $this->actingAs($adminA);
    $ownSection = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['name' => 'Linda Cruz']);

    $tool = new RecordGradeTool;

    expect((string) $tool->handle(new Request([
        'student_id' => $student->id,
        'section_id' => $foreignSection->id,
        'score' => 90,
    ])))->toContain('does not exist in this workspace');

    expect((string) $tool->handle(new Request([
        'student_id' => $student->id,
        'section_id' => $ownSection->id,
        'score' => 90,
    ])))->toContain('is not enrolled in section');

    $ownSection->users()->attach($student->id);

    expect((string) $tool->handle(new Request([
        'student_id' => $student->id,
        'section_id' => $ownSection->id,
        'score' => 90,
    ])))->toContain('PENDING HUMAN APPROVAL')
        ->and(PendingAiAction::count())->toBe(1);
});

it('re-syncs the assignment roster when the tool changes target sections', function () {
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $sectionA = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $sectionB = Section::factory()->forSeason($season)->create(['name' => 'Beta']);
    $studentA = User::factory()->create();
    $studentB = User::factory()->create();
    $sectionA->users()->attach($studentA->id);
    $sectionB->users()->attach($studentB->id);

    $assignment = Assignment::query()->create([
        'workspace_id' => $admin->current_workspace_id,
        'admin_id' => $admin->id,
        'title' => 'Roster Move',
        'due_date' => now()->addWeek(),
    ]);
    $assignment->sections()->sync([$sectionA->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    expect(DB::table('assignment_user')->where('assignment_id', $assignment->id)->pluck('user_id')->all())
        ->toBe([$studentA->id]);

    (new UpdateAssignmentTool)->handle(new Request([
        'assignment_id' => $assignment->id,
        'section_ids' => (string) $sectionB->id,
    ]));

    $action = PendingAiAction::query()->where('action_type', 'update_assignment')->firstOrFail();

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => pendingAiActionNonce($action),
    ])->assertOk();

    $roster = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($roster)->toBe([$studentB->id]);
});

it('matches blocked-student names only among students in the workspace', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->forSeason($season)->create();
    $foreignStudent = User::factory()->create(['name' => 'Alice Foreign']);
    $foreignSection->users()->attach($foreignStudent->id);

    $this->actingAs($adminA);
    $section = Section::factory()->forSeason($season)->create();
    $ownStudent = User::factory()->create(['name' => 'Alice Own']);
    $section->users()->attach($ownStudent->id);

    (new CreateExamTool)->handle(new Request([
        'title' => 'Blocked names',
        'exam_date' => '2026-09-01 09:00',
        'section_id' => $section->id,
        'blocked_students' => ['Alice'],
    ]));

    $action = PendingAiAction::firstOrFail();

    expect($action->payload['blocked_user_ids'])->toBe([$ownStudent->id]);
});

it('rejects learning materials targeting sections outside the workspace', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->forSeason($season)->create();

    $this->actingAs($adminA);
    $ownSection = Section::factory()->forSeason($season)->create();

    expect((string) (new CreateLearningMaterialTool)->handle(new Request([
        'title' => 'Sneaky material',
        'section_ids' => "{$ownSection->id},{$foreignSection->id}",
    ])))->toContain('do not exist in this workspace')
        ->and(PendingAiAction::count())->toBe(0);
});

it('stages and applies learning material updates upon approval', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $section = Section::factory()->create();
    $material = LearningMaterial::factory()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'title' => 'Old Title',
        'status' => LearningMaterial::STATUS_DRAFT,
    ]);

    $result = (string) (new UpdateLearningMaterialTool)->handle(new Request([
        'material_id' => $material->id,
        'title' => 'New Title',
        'status' => LearningMaterial::STATUS_PUBLISHED,
        'section_ids' => (string) $section->id,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and($material->refresh()->title)->toBe('Old Title');

    $action = PendingAiAction::query()->where('action_type', 'update_learning_material')->firstOrFail();

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => pendingAiActionNonce($action),
    ])->assertOk();

    $material->refresh();
    expect($material->title)->toBe('New Title')
        ->and($material->status)->toBe(LearningMaterial::STATUS_PUBLISHED)
        ->and($material->sections()->pluck('sections.id')->all())->toBe([$section->id]);
});

it('stages and applies activity task updates and rejects invalid max points', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $section = Section::factory()->create();
    $task = ActivityTask::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'section_id' => $section->id,
        'title' => 'Old Task',
        'task_type' => 'Performance Task',
        'term' => 'Midterm',
        'max_points' => 100,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    expect((string) (new CreateActivityTaskTool)->handle(new Request([
        'title' => 'Bad Points Task',
        'section_id' => $section->id,
        'max_points' => -5,
    ])))->toContain('Error: max_points must be greater than 0');

    $tool = new UpdateActivityTaskTool;

    expect((string) $tool->handle(new Request(['task_id' => $task->id, 'max_points' => 0])))
        ->toContain('Error: max_points must be greater than 0')
        ->and(PendingAiAction::count())->toBe(0);

    $result = (string) $tool->handle(new Request([
        'task_id' => $task->id,
        'title' => 'Renamed Task',
        'max_points' => 150,
        'due_date' => now()->addDays(10)->toDateString(),
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL')
        ->and($task->refresh()->title)->toBe('Old Task');

    $action = PendingAiAction::query()->where('action_type', 'update_activity_task')->firstOrFail();

    $this->postJson(route('ai-actions.approve', $action), [
        'nonce' => pendingAiActionNonce($action),
    ])->assertOk();

    $task->refresh();
    expect($task->title)->toBe('Renamed Task')
        ->and((float) $task->max_points)->toBe(150.0)
        ->and($task->due_date?->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('reports recent logins and inactive students within the workspace only', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->forSeason($season)->create();
    $foreignStudent = User::factory()->create(['name' => 'Foreign Fiona', 'last_login_at' => now()->subHour()]);
    $foreignSection->users()->attach($foreignStudent->id);

    $this->actingAs($adminA);
    $section = Section::factory()->forSeason($season)->create();
    $active = User::factory()->create(['name' => 'Active Annie', 'last_login_at' => now()->subHours(2)]);
    $idle = User::factory()->create(['name' => 'Idle Ivan', 'last_login_at' => now()->subDays(20)]);
    $never = User::factory()->create(['name' => 'New Nina', 'last_login_at' => null]);
    $section->users()->attach([$active->id, $idle->id, $never->id]);

    $recent = json_decode((string) (new UserActivityTool)->handle(new Request([])), true);

    expect($recent['mode'])->toBe('recent_logins')
        ->and($recent['summary']['workspace_members'])->toBe(3)
        ->and($recent['summary']['logged_in_within_days'])->toBe(1)
        ->and(collect($recent['users'])->pluck('name')->all())->toBe(['Active Annie']);

    $inactive = json_decode((string) (new UserActivityTool)->handle(new Request(['mode' => 'inactive', 'days' => 7])), true);

    expect(collect($inactive['users'])->pluck('name')->sort()->values()->all())->toBe(['Idle Ivan', 'New Nina'])
        ->and($inactive['summary']['never_logged_in'])->toBe(1)
        ->and(json_encode($inactive))->not->toContain('Foreign Fiona');

    $signups = json_decode((string) (new UserActivityTool)->handle(new Request(['mode' => 'recent_signups', 'days' => 1])), true);

    expect($signups['summary']['new_signups_within_days'])->toBe(3);
});
