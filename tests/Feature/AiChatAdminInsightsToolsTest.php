<?php

/**
 * Admin chat insight tool tests.
 *
 * The insight tools are read-only and strictly workspace-scoped: section
 * performance, at-risk students, leaderboards, support tickets and the AI
 * action audit log. The nightly AI report is the sole exception — it is
 * limited to Super Administrators.
 */

use App\Ai\Tools\AiActionLogTool;
use App\Ai\Tools\AtRiskStudentsTool;
use App\Ai\Tools\DailyReportTool;
use App\Ai\Tools\LeaderboardTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\SectionPerformanceTool;
use App\Ai\Tools\SupportTicketsTool;
use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\AiDailyReport;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Grade;
use App\Models\PendingAiAction;
use App\Models\Season;
use App\Models\Section;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\WorkspaceContext;
use Laravel\Ai\Tools\Request;

it('summarizes section performance from grades, exams, and activity tasks', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $workspaceId = app(WorkspaceContext::class)->id();

    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Biology A']);
    $topper = User::factory()->create(['name' => 'Tina Topper']);
    $low = User::factory()->create(['name' => 'Leo Low']);
    $section->users()->attach([$topper->id, $low->id]);

    Grade::factory()->create([
        'workspace_id' => $workspaceId,
        'section_id' => $section->id,
        'user_id' => $topper->id,
        'period' => 'Midterm',
        'score' => 80,
        'max_score' => 100,
    ]);
    Grade::factory()->create([
        'workspace_id' => $workspaceId,
        'section_id' => $section->id,
        'user_id' => $low->id,
        'period' => 'Midterm',
        'score' => 40,
        'max_score' => 100,
    ]);

    $exam = Exam::factory()->create(['section_id' => $section->id, 'status' => 'published']);
    $part = ExamPart::factory()->create(['exam_id' => $exam->id]);
    ExamSubmission::create(['user_id' => $topper->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 90]);
    ExamSubmission::create(['user_id' => $low->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 30]);

    $task = ActivityTask::query()->create([
        'workspace_id' => $workspaceId,
        'admin_id' => $admin->id,
        'section_id' => $section->id,
        'title' => 'Lab Report',
        'task_type' => 'Performance Task',
        'term' => 'Midterm',
        'max_points' => 100,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);
    ActivityTaskScore::query()->create(['activity_task_id' => $task->id, 'user_id' => $topper->id, 'score' => 80]);
    ActivityTaskScore::query()->create(['activity_task_id' => $task->id, 'user_id' => $low->id, 'score' => 40]);

    $data = json_decode((string) (new SectionPerformanceTool)->handle(new Request(['section_name' => 'Biology'])), true);

    expect($data['section']['name'])->toBe('Biology A')
        ->and($data['section']['enrolled_students'])->toBe(2)
        ->and($data['gradebook']['records'])->toBe(2)
        ->and((float) $data['gradebook']['average_percent'])->toBe(60.0)
        ->and($data['gradebook']['by_period'][0]['period'])->toBe('Midterm')
        ->and((float) $data['gradebook']['by_period'][0]['average_percent'])->toBe(60.0)
        ->and($data['recent_exams'][0]['submissions'])->toBe(2)
        ->and((float) $data['recent_exams'][0]['average_score'])->toBe(60.0)
        ->and((float) $data['recent_activity_tasks'][0]['average_score'])->toBe(60.0)
        ->and((float) $data['recent_activity_tasks'][0]['average_percent'])->toBe(60.0)
        ->and($data['top_students'][0]['name'])->toBe('Tina Topper')
        ->and($data['lowest_students'][0]['name'])->toBe('Leo Low');

    expect((string) (new SectionPerformanceTool)->handle(new Request(['section_name' => 'Missing'])))
        ->toContain('section not found in this workspace');
});

it('flags at-risk students in the workspace and supports search and section filters', function () {
    $season = Season::factory()->active()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignSection = Section::factory()->forSeason($season)->create();
    $foreignStudent = User::factory()->create(['name' => 'Foreign Fiona', 'last_login_at' => null]);
    $foreignSection->users()->attach($foreignStudent->id);

    $this->actingAs($adminA);
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $never = User::factory()->create(['name' => 'New Nina', 'last_login_at' => null, 'current_streak' => 0]);
    $idle = User::factory()->create(['name' => 'Idle Ivan', 'last_login_at' => now()->subDays(20), 'current_streak' => 0]);
    $healthy = User::factory()->create(['name' => 'Happy Hana', 'last_login_at' => now()->subHours(2), 'current_streak' => 5]);
    $struggling = User::factory()->create(['name' => 'Struggling Sam', 'last_login_at' => now()->subHours(3), 'current_streak' => 0]);
    $section->users()->attach([$never->id, $idle->id, $healthy->id, $struggling->id]);

    $exam = Exam::factory()->create(['section_id' => $section->id]);
    $part = ExamPart::factory()->create(['exam_id' => $exam->id]);
    ExamSubmission::create(['user_id' => $healthy->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 90]);
    ExamSubmission::create(['user_id' => $struggling->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 40]);

    $data = json_decode((string) (new AtRiskStudentsTool)->handle(new Request([])), true);

    expect($data['candidates_checked'])->toBe(4)
        ->and($data['flagged_count'])->toBe(3);

    $names = collect($data['students'])->pluck('name')->all();
    expect($names)->toContain('New Nina', 'Idle Ivan', 'Struggling Sam')
        ->not->toContain('Happy Hana');

    expect(json_encode($data))->not->toContain('Foreign Fiona');

    $strugglingRow = collect($data['students'])->firstWhere('name', 'Struggling Sam');
    expect($strugglingRow['reasons'])->toContain('Low exam average (40)')
        ->and($strugglingRow['risk_score'])->toBe(2);

    $idleRow = collect($data['students'])->firstWhere('name', 'Idle Ivan');
    expect($idleRow['reasons'])->toContain('Inactive for 20 days');

    $searched = json_decode((string) (new AtRiskStudentsTool)->handle(new Request(['search' => 'Idle'])), true);
    expect($searched['flagged_count'])->toBe(1)
        ->and($searched['students'][0]['name'])->toBe('Idle Ivan');

    $bySection = json_decode((string) (new AtRiskStudentsTool)->handle(new Request(['section_id' => $section->id])), true);
    expect($bySection['flagged_count'])->toBe(3)
        ->and($bySection['section']['name'])->toBe('Alpha');
});

it('builds section leaderboards for the workspace and blocks foreign sections', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA);
    $seasonA = Season::factory()->active()->create();
    $sectionA = Section::factory()->forSeason($seasonA)->create(['name' => 'Alpha']);
    $alice = User::factory()->create(['name' => 'Alice Santos']);
    $bob = User::factory()->create(['name' => 'Bob Cruz']);
    $sectionA->users()->attach([
        $alice->id => ['season_id' => $seasonA->id],
        $bob->id => ['season_id' => $seasonA->id],
    ]);

    $this->actingAs($adminB);
    $seasonB = Season::factory()->active()->create();
    $sectionB = Section::factory()->forSeason($seasonB)->create(['name' => 'Beta']);
    $bobForeign = User::factory()->create(['name' => 'Foreign Bob']);
    $sectionB->users()->attach([$bobForeign->id => ['season_id' => $seasonB->id]]);

    $this->actingAs($adminA);

    $board = json_decode((string) (new LeaderboardTool)->handle(new Request(['section_id' => $sectionA->id])), true);

    expect($board['section']['name'])->toBe('Alpha')
        ->and($board['section']['total_players'])->toBe(2)
        ->and(collect($board['leaders'])->pluck('name')->sort()->values()->all())->toBe(['Alice Santos', 'Bob Cruz'])
        ->and($board['leaders'][0]['rank'])->toBe(1);

    expect((string) (new LeaderboardTool)->handle(new Request(['section_id' => $sectionB->id])))
        ->toContain('not found');

    $overview = json_decode((string) (new LeaderboardTool)->handle(new Request([])), true);

    expect(collect($overview['sections'])->pluck('name')->all())->toBe(['Alpha'])
        ->and($overview['season']['name'])->toBe($seasonA->name);
});

it('serves the nightly AI report only to super administrators', function () {
    $this->actingAs(User::factory()->admin()->create());

    expect((string) (new DailyReportTool)->handle(new Request([])))
        ->toContain('Super Administrators only');

    $this->actingAs(User::factory()->superAdmin()->create());

    AiDailyReport::factory()->completed()->create([
        'report_date' => now()->toDateString(),
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
    ]);

    $data = json_decode((string) (new DailyReportTool)->handle(new Request([])), true);

    expect($data['report_date'])->toBe(now()->toDateString())
        ->and($data['status'])->toBe(AiDailyReport::STATUS_COMPLETED)
        ->and($data['analysis']['summary'])->toBe('No recorded activity in the selected workspaces.');

    expect((string) (new DailyReportTool)->handle(new Request(['date' => '2001-01-01'])))
        ->toContain('No daily report exists for 2001-01-01');

    expect((string) (new DailyReportTool)->handle(new Request(['date' => 'not-a-date'])))
        ->toContain('invalid date format');
});

it('lists the workspace AI action audit log with status filters', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $session = $admin->chatSessions()->create(['title' => 'Audit log']);
    (new PostAnnouncementTool(chatSessionId: $session->id))->handle(new Request([
        'title' => 'First notice',
        'description' => 'Body one.',
    ]));

    $secondSession = $admin->chatSessions()->create(['title' => 'Audit log 2']);
    (new PostAnnouncementTool(chatSessionId: $secondSession->id))->handle(new Request([
        'title' => 'Second notice',
        'description' => 'Body two.',
    ]));

    $first = PendingAiAction::query()->orderBy('id')->firstOrFail();
    PendingAiAction::query()->whereKey($first->id)->update([
        'status' => PendingAiAction::STATUS_EXECUTED,
        'executed_at' => now(),
        'result' => 'Announcement posted.',
    ]);

    $data = json_decode((string) (new AiActionLogTool)->handle(new Request([])), true);

    expect($data['total_in_window'])->toBe(2)
        ->and($data['counts_by_status'][PendingAiAction::STATUS_PENDING])->toBe(1)
        ->and($data['counts_by_status'][PendingAiAction::STATUS_EXECUTED])->toBe(1)
        ->and(collect($data['actions'])->pluck('action_type')->unique()->all())->toBe(['post_announcement'])
        ->and(json_encode($data))->not->toContain('nonce');

    $executed = json_decode((string) (new AiActionLogTool)->handle(new Request(['status' => 'executed'])), true);
    expect($executed['total_in_window'])->toBe(1)
        ->and($executed['actions'][0]['title'])->toBe('Post announcement')
        ->and($executed['actions'][0]['result'])->toBe('Announcement posted.');

    expect((string) (new AiActionLogTool)->handle(new Request(['status' => 'bogus'])))
        ->toContain('status must be one of');

    $other = User::factory()->admin()->create();
    $this->actingAs($other);
    $foreign = json_decode((string) (new AiActionLogTool)->handle(new Request([])), true);
    expect($foreign['total_in_window'])->toBe(0);
});

it('lists and filters support tickets for the workspace only', function () {
    $adminA = User::factory()->admin()->create();
    $this->actingAs($adminA);
    $workspaceId = app(WorkspaceContext::class)->id();

    $student = User::factory()->create(['name' => 'Tina Ticket']);
    SupportTicket::query()->create([
        'user_id' => $student->id,
        'workspace_id' => $workspaceId,
        'subject' => 'Cannot submit assignment',
        'category' => 'technical',
        'priority' => 'high',
        'message' => 'The submit button fails.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);
    SupportTicket::query()->create([
        'user_id' => $student->id,
        'workspace_id' => $workspaceId,
        'subject' => 'Old resolved request',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Everything is fine now.',
        'status' => SupportTicket::STATUS_RESOLVED,
    ]);

    $adminB = User::factory()->admin()->create();
    $this->actingAs($adminB);
    SupportTicket::query()->create([
        'user_id' => User::factory()->create(['name' => 'Foreign Student'])->id,
        'workspace_id' => app(WorkspaceContext::class)->id(),
        'subject' => 'Foreign ticket',
        'category' => 'general',
        'priority' => 'medium',
        'message' => 'From another workspace.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($adminA);

    $active = json_decode((string) (new SupportTicketsTool)->handle(new Request([])), true);

    expect($active['filter'])->toBe('active')
        ->and($active['counts_by_status'][SupportTicket::STATUS_OPEN])->toBe(1)
        ->and($active['counts_by_status'][SupportTicket::STATUS_RESOLVED])->toBe(1)
        ->and(collect($active['tickets'])->pluck('subject')->all())->toBe(['Cannot submit assignment'])
        ->and($active['tickets'][0]['status_label'])->toBe('Open')
        ->and($active['tickets'][0]['student']['name'])->toBe('Tina Ticket');

    $all = json_decode((string) (new SupportTicketsTool)->handle(new Request(['status' => 'all'])), true);
    expect($all['tickets'])->toHaveCount(2)
        ->and(json_encode($all))->not->toContain('Foreign ticket');

    $priority = json_decode((string) (new SupportTicketsTool)->handle(new Request(['priority' => 'high'])), true);
    expect($priority['tickets'])->toHaveCount(1);

    expect((string) (new SupportTicketsTool)->handle(new Request(['priority' => 'urgent'])))
        ->toContain('priority must be one of');
});

it('refuses insight tools for non-admins', function () {
    $this->actingAs(User::factory()->create());

    expect((string) (new SectionPerformanceTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new AtRiskStudentsTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new LeaderboardTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new AiActionLogTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new SupportTicketsTool)->handle(new Request([])))->toContain('Only admins')
        ->and((string) (new DailyReportTool)->handle(new Request([])))->toContain('Super Administrators only');
});
