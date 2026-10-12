<?php

use App\Models\AiUsageLog;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiDailyReportSnapshotService;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['app.timezone' => 'Asia/Manila']);
});

/** @param array<string, mixed> $attributes */
function aiDailyReportActivity(Workspace $workspace, User $student, ?string $submittedAt, array $attributes = []): Submission
{
    $assignment = Assignment::create([
        'workspace_id' => $workspace->id,
        'title' => 'Private activity title',
        'description' => 'Private activity description',
    ]);

    $submission = new Submission;
    $submission->forceFill([
        'user_id' => $student->id,
        'assignment_id' => $assignment->id,
        'submitted' => true,
        'status' => 'Submitted',
        'submitted_at' => $submittedAt,
        'created_at' => '2026-10-01 12:00:00',
        'updated_at' => '2026-10-13 12:00:00',
        ...$attributes,
    ])->save();

    return $submission;
}

/** @param array<string, mixed> $attributes */
function aiDailyReportExam(Workspace $workspace, User $student, string $submittedAt, array $attributes = []): ExamSubmission
{
    $exam = Exam::factory()->published()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Private exam title',
        'description' => 'Private exam description',
    ]);
    $part = ExamPart::factory()->forExam($exam)->create();

    return ExamSubmission::factory()->forSubmission($student, $exam, $part)->create([
        'created_at' => $submittedAt,
        'updated_at' => '2026-10-13 12:00:00',
        ...$attributes,
    ]);
}

function aiDailyReportUsage(?Workspace $workspace, string $date, int $inputTokens = 0, int $outputTokens = 0, int $costMicros = 0): AiUsageLog
{
    $usage = new AiUsageLog;
    $usage->forceFill([
        'workspace_id' => $workspace?->id,
        'date' => $date,
        'provider' => 'private-provider',
        'model' => 'private-model',
        'source' => 'private-source',
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'estimated_cost_micros' => $costMicros,
        'created_at' => '2026-10-13 12:00:00',
        'updated_at' => '2026-10-13 12:00:00',
    ])->save();

    return $usage;
}

function aiDailyReportEnrollment(Workspace $workspace, User $student, string $enrolledAt): void
{
    $course = Course::create(['workspace_id' => $workspace->id, 'name' => 'Private course name']);

    DB::table('course_user')->insert([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'created_at' => $enrolledAt,
        'updated_at' => '2026-10-13 12:00:00',
    ]);
}

/**
 * @param  array<string, mixed>  $snapshot
 * @return array{key: string, label: string, workspace_id: int, current: int|float, previous: int|float, unit: string}
 */
function aiDailyReportMetric(array $snapshot, Workspace $workspace, string $key): array
{
    return collect($snapshot['metrics'])->sole('key', "workspace:{$workspace->id}:{$key}");
}

it('isolates explicit workspaces without authentication and normalizes duplicate selections', function () {
    $first = Workspace::factory()->create(['name' => 'First workspace label']);
    $second = Workspace::factory()->create(['name' => 'Second workspace label']);
    $excluded = Workspace::factory()->create(['name' => 'Excluded workspace label']);
    $student = User::factory()->create(['current_workspace_id' => $excluded->id]);

    foreach ([$first, $second, $excluded] as $workspace) {
        aiDailyReportActivity($workspace, $student, '2026-10-11 12:00:00');
        aiDailyReportExam($workspace, $student, '2026-10-11 12:00:00');
        aiDailyReportEnrollment($workspace, $student, '2026-10-11 12:00:00');
        $workspace->users()->attach($student->id, [
            'role' => Workspace::ROLE_STUDENT,
            'created_at' => '2026-10-11 12:00:00',
            'updated_at' => '2026-10-13 12:00:00',
        ]);
    }

    aiDailyReportActivity($first, $student, '2026-10-11 13:00:00');
    aiDailyReportActivity($second, $student, '2026-10-10 12:00:00');
    aiDailyReportUsage($first, '2026-10-11', 10, 20, 250000);
    aiDailyReportUsage($second, '2026-10-11', 30, 40, 750000);
    aiDailyReportUsage($excluded, '2026-10-11', 999, 999, 999000000);
    aiDailyReportUsage(null, '2026-10-11', 999, 999, 999000000);

    $this->assertGuest();
    app(WorkspaceContext::class)->set($excluded);
    session()->put('workspace_inspection_id', $excluded->id);
    $session = session()->all();

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([
        $second->id, $first->id, $second->id,
    ], '2026-10-11', 'Asia/Manila');

    expect($snapshot['workspace_ids'])->toBe([$first->id, $second->id])
        ->and($snapshot['workspace_names'])->toBe([
            $first->id => 'First workspace label',
            $second->id => 'Second workspace label',
        ])
        ->and($snapshot['metrics'])->toHaveCount(18)
        ->and(array_unique(array_column($snapshot['metrics'], 'workspace_id')))->toBe([0 => $first->id, 9 => $second->id])
        ->and(array_unique(array_column($snapshot['metrics'], 'key')))->toHaveCount(18)
        ->and(aiDailyReportMetric($snapshot, $first, 'activity_completions')['current'])->toBe(2)
        ->and(aiDailyReportMetric($snapshot, $second, 'activity_completions')['current'])->toBe(1)
        ->and(aiDailyReportMetric($snapshot, $second, 'activity_completions')['previous'])->toBe(1)
        ->and(aiDailyReportMetric($snapshot, $first, 'ai_input_tokens')['current'])->toBe(10)
        ->and(aiDailyReportMetric($snapshot, $second, 'ai_input_tokens')['current'])->toBe(30)
        ->and(json_encode($snapshot, JSON_THROW_ON_ERROR))->not->toContain('Excluded workspace label')
        ->and(session()->all())->toBe($session)
        ->and(app(WorkspaceContext::class)->id())->toBe($excluded->id);

    foreach ([$first, $second] as $workspace) {
        foreach (['exam_submissions', 'new_student_memberships', 'course_enrollments', 'ai_requests'] as $key) {
            expect(aiDailyReportMetric($snapshot, $workspace, $key)['current'])->toBe(1);
        }
    }

    $this->assertGuest();
});

it('uses half-open calendar boundaries in the configured timestamp storage timezone', function (
    string $storageTimezone,
    string $date,
    string $timezone,
    string $previousStart,
    string $currentStart,
    string $end,
) {
    config(['app.timezone' => $storageTimezone]);
    $workspace = Workspace::factory()->create();
    $student = User::factory()->create();
    $previous = CarbonImmutable::parse($previousStart, $storageTimezone);
    $current = CarbonImmutable::parse($currentStart, $storageTimezone);
    $next = CarbonImmutable::parse($end, $storageTimezone);

    foreach ([$previous->subSecond(), $previous, $current->subSecond(), $current, $next->subSecond(), $next, $next->addMinutes(30)] as $timestamp) {
        $storedTimestamp = $timestamp->format('Y-m-d H:i:s');
        aiDailyReportActivity($workspace, $student, $storedTimestamp);
        aiDailyReportExam($workspace, $student, $storedTimestamp);
        aiDailyReportEnrollment($workspace, $student, $storedTimestamp);
        $member = User::factory()->create(['created_at' => '2020-01-01 12:00:00']);
        $workspace->users()->attach($member->id, [
            'role' => Workspace::ROLE_STUDENT,
            'created_at' => $storedTimestamp,
            'updated_at' => '2026-10-13 12:00:00',
        ]);
    }

    aiDailyReportActivity($workspace, $student, $currentStart, ['submitted' => false]);
    aiDailyReportActivity($workspace, $student, null);
    aiDailyReportExam($workspace, $student, $currentStart, ['status' => 'pending']);

    foreach ([Workspace::ROLE_ADMIN, Workspace::ROLE_OWNER] as $role) {
        $workspace->users()->attach(User::factory()->create()->id, [
            'role' => $role,
            'created_at' => $currentStart,
            'updated_at' => $currentStart,
        ]);
    }

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], $date, $timezone);

    foreach (['activity_completions', 'exam_submissions', 'new_student_memberships', 'course_enrollments'] as $key) {
        $metric = aiDailyReportMetric($snapshot, $workspace, $key);
        expect($metric['current'])->toBe(2)->and($metric['previous'])->toBe(2);
    }

    expect($snapshot['date'])->toBe($date)
        ->and($snapshot['comparison_date'])->toBe(CarbonImmutable::parse($date, $timezone)->subDay()->toDateString())
        ->and($snapshot['timezone'])->toBe($timezone);
})->with([
    'Manila timestamps stay wall-clock' => ['Asia/Manila', '2026-10-11', 'Asia/Manila', '2026-10-10 00:00:00', '2026-10-11 00:00:00', '2026-10-12 00:00:00'],
    'Manila calendar against UTC storage' => ['UTC', '2026-10-11', 'Asia/Manila', '2026-10-09 16:00:00', '2026-10-10 16:00:00', '2026-10-11 16:00:00'],
    'DST calendar day has twenty-three hours' => ['UTC', '2026-03-08', 'America/New_York', '2026-03-07 05:00:00', '2026-03-08 05:00:00', '2026-03-09 04:00:00'],
    'Santiago skipped midnight resets adjacent day starts' => ['America/Santiago', '2026-09-06', 'America/Santiago', '2026-09-05 00:00:00', '2026-09-06 01:00:00', '2026-09-07 00:00:00'],
    'Santiago skipped midnight against UTC storage' => ['UTC', '2026-09-06', 'America/Santiago', '2026-09-05 04:00:00', '2026-09-06 04:00:00', '2026-09-07 03:00:00'],
]);

it('counts submissions on their recorded submission day regardless of later grading', function () {
    $workspace = Workspace::factory()->create();
    $student = User::factory()->create();

    aiDailyReportActivity($workspace, $student, '2026-10-10 12:00:00', [
        'status' => 'Graded',
        'graded_at' => '2026-10-13 12:00:00',
    ]);

    foreach (['submitted', 'pending_review', 'pending_ai', 'graded'] as $status) {
        aiDailyReportExam($workspace, $student, '2026-10-10 12:00:00', [
            'status' => $status,
            'grading_failed' => $status === 'pending_ai',
        ]);
    }

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    expect(aiDailyReportMetric($snapshot, $workspace, 'activity_completions')['previous'])->toBe(1)
        ->and(aiDailyReportMetric($snapshot, $workspace, 'activity_completions')['current'])->toBe(0)
        ->and(aiDailyReportMetric($snapshot, $workspace, 'exam_submissions')['previous'])->toBe(4)
        ->and(aiDailyReportMetric($snapshot, $workspace, 'exam_submissions')['current'])->toBe(0)
        ->and(implode(' ', $snapshot['limitations']))->toContain('Historical pending grading backlog is unavailable');

    foreach ($snapshot['metrics'] as $metric) {
        expect($metric['key'])->not->toContain('backlog', 'failures');
    }
});

it('aggregates AI usage by recorded calendar date and converts microdollars to USD', function () {
    $workspace = Workspace::factory()->create();
    aiDailyReportUsage($workspace, '2026-10-09', 9999, 9999, 9999999);
    aiDailyReportUsage($workspace, '2026-10-10', 10, 20, 500000);
    aiDailyReportUsage($workspace, '2026-10-10', 30, 40, 250000);
    aiDailyReportUsage($workspace, '2026-10-11', 100, 50, 1250001);
    aiDailyReportUsage($workspace, '2026-10-11', 25, 5, 749999);
    aiDailyReportUsage($workspace, '2026-10-12', 9999, 9999, 9999999);

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    foreach ([
        'ai_requests' => [2, 2, 'requests'],
        'ai_input_tokens' => [125, 40, 'tokens'],
        'ai_output_tokens' => [55, 60, 'tokens'],
        'ai_estimated_cost_usd' => [2.0, 0.75, 'USD'],
    ] as $key => [$current, $previous, $unit]) {
        $metric = aiDailyReportMetric($snapshot, $workspace, $key);
        expect($metric['current'])->toBe($current)
            ->and($metric['previous'])->toBe($previous)
            ->and($metric['unit'])->toBe($unit);
    }

    expect(implode(' ', $snapshot['limitations']))->toContain(
        'Unlogged requests and failures are unavailable, not zero',
        'reservation date',
        'not provider invoices',
        'missing legacy pricing',
    );
});

it('omits AI metrics when date-only buckets cannot match the requested timezone', function () {
    config(['app.timezone' => 'UTC']);
    $workspace = Workspace::factory()->create();
    aiDailyReportUsage($workspace, '2026-10-11', 100, 200, 500000);

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    expect($snapshot['metrics'])->toHaveCount(5)
        ->and(implode(' ', $snapshot['limitations']))->toContain('AI daily metrics are unavailable', 'date-only usage buckets');

    foreach ($snapshot['metrics'] as $metric) {
        expect($metric['key'])->not->toContain(':ai_');
    }
});

it('returns the exact plain JSON-compatible schema and explicit recorded zeros', function () {
    $workspace = Workspace::factory()->create(['name' => 'Report workspace label']);
    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    expect(array_keys($snapshot))->toBe([
        'date', 'comparison_date', 'timezone', 'workspace_ids', 'workspace_names', 'section_leaderboards', 'metrics', 'limitations',
    ])
        ->and($snapshot['workspace_names'])->toBe([$workspace->id => 'Report workspace label'])
        ->and(array_is_list($snapshot['workspace_ids']))->toBeTrue()
        ->and(array_is_list($snapshot['section_leaderboards']))->toBeTrue()
        ->and(array_is_list($snapshot['metrics']))->toBeTrue()
        ->and(array_is_list($snapshot['limitations']))->toBeTrue()
        ->and($snapshot['metrics'])->toHaveCount(9)
        ->and(json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, flags: JSON_THROW_ON_ERROR))->toBe($snapshot);

    $expectedMetrics = [
        'active_student_logins' => ['Active student logins', 'students'],
        'activity_completions' => ['Activity completions (student submissions)', 'submissions'],
        'exam_submissions' => ['Exam part submissions', 'submissions'],
        'new_student_memberships' => ['New student workspace memberships', 'memberships'],
        'course_enrollments' => ['New course enrollments', 'enrollments'],
        'ai_requests' => ['Recorded AI requests', 'requests'],
        'ai_input_tokens' => ['Recorded AI input tokens', 'tokens'],
        'ai_output_tokens' => ['Recorded AI output tokens', 'tokens'],
        'ai_estimated_cost_usd' => ['Recorded estimated AI cost', 'USD'],
    ];

    foreach ($expectedMetrics as $key => [$label, $unit]) {
        $zero = $unit === 'USD' ? 0.0 : 0;
        expect(aiDailyReportMetric($snapshot, $workspace, $key))->toBe([
            'key' => "workspace:{$workspace->id}:{$key}",
            'label' => $label,
            'workspace_id' => $workspace->id,
            'current' => $zero,
            'previous' => $zero,
            'unit' => $unit,
        ]);
    }

    foreach ($snapshot['limitations'] as $limitation) {
        expect($limitation)->toBeString();
    }
});

it('collects only read-only aggregates without retrieving sensitive fields or freeform content', function () {
    $workspace = Workspace::factory()->create(['name' => 'UI workspace label']);
    $student = User::factory()->withTwoFactor()->create([
        'first_name' => 'PrivateStudentMarker',
        'last_name' => 'PrivateSurnameMarker',
        'email' => 'snapshot-private@example.test',
        'password' => 'private-password-marker',
    ]);
    aiDailyReportActivity($workspace, $student, '2026-10-11 12:00:00', [
        'feedback' => 'Private feedback marker',
        'file_path' => 'private/path/proof.txt',
    ]);
    aiDailyReportExam($workspace, $student, '2026-10-11 12:00:00', [
        'answers' => [['question_text' => 'Private question marker', 'answer' => 'Private answer marker']],
        'feedback' => 'Private exam feedback marker',
    ]);
    aiDailyReportEnrollment($workspace, $student, '2026-10-11 12:00:00');
    aiDailyReportUsage($workspace, '2026-10-11', 10, 20, 250000);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($queries)->toHaveCount(8);

    foreach ($queries as $query) {
        $sql = strtolower($query['query']);
        expect($sql)->toStartWith('select ')->not->toContain(
            'select *', 'answers', 'feedback', 'password', 'email', 'file_path', 'two_factor', 'provider', 'model', 'source',
        );
    }

    $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
    expect($encoded)->toContain('UI workspace label')->not->toContain(
        'PrivateStudentMarker', 'PrivateSurnameMarker', 'snapshot-private@example.test', 'private-password-marker',
        $student->getRawOriginal('password'), $student->getRawOriginal('two_factor_secret'),
        $student->getRawOriginal('two_factor_recovery_codes'),
        'Private activity title', 'Private activity description', 'Private exam title', 'Private exam description',
        'Private feedback marker', 'private/path/proof.txt', 'Private question marker', 'Private answer marker',
        'Private exam feedback marker', 'Private course name', 'private-provider', 'private-model', 'private-source',
    );
});

it('keeps query count fixed at the twenty-five-workspace bound', function () {
    $workspaces = Workspace::factory()->count(25)->create();
    $ids = $workspaces->modelKeys();
    $service = app(AiDailyReportSnapshotService::class);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $service->collect([$ids[0]], '2026-10-11', 'Asia/Manila');
        $singleQueries = DB::getQueryLog();
        DB::flushQueryLog();
        $snapshot = $service->collect($ids, '2026-10-11', 'Asia/Manila');
        $allQueries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($singleQueries)->toHaveCount(8)
        ->and($allQueries)->toHaveCount(8)
        ->and($snapshot['workspace_ids'])->toBe($ids)
        ->and($snapshot['workspace_names'])->toHaveCount(25)
        ->and($snapshot['metrics'])->toHaveCount(225);

    foreach (array_slice($allQueries, 1, 6) as $query) {
        expect(strtolower($query['query']))->toContain('group by')->not->toContain('select *');
    }
    expect(strtolower(end($allQueries)['query']))->not->toContain('select *');
});

it('terminates with immutable dates and compares the previous calendar day across month and year edges', function (string $date, string $comparisonDate) {
    $workspace = Workspace::factory()->create();
    $service = app(AiDailyReportSnapshotService::class);
    $snapshot = $service->collect([$workspace->id], $date, 'Asia/Manila');

    expect(now())->toBeInstanceOf(CarbonImmutable::class)
        ->and($snapshot['date'])->toBe($date)
        ->and($snapshot['comparison_date'])->toBe($comparisonDate)
        ->and($service->collect([$workspace->id], $date, 'Asia/Manila'))->toBe($snapshot);
})->with([
    'month edge' => ['2026-03-01', '2026-02-28'],
    'leap day' => ['2024-03-01', '2024-02-29'],
    'year edge' => ['2026-01-01', '2025-12-31'],
]);

it('rejects a calendar date skipped by the selected timezone instead of silently shifting it', function () {
    $workspace = Workspace::factory()->create();

    try {
        app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2011-12-30', 'Pacific/Apia');
        $this->fail('Expected the skipped calendar day to fail validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'date' => ['The selected calendar day does not exist in this timezone.'],
        ]);
    }
});

it('reports an explicitly selected archived workspace instead of silently dropping it', function () {
    $workspace = Workspace::factory()->create(['archived_at' => '2026-10-12 12:00:00']);
    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    expect($snapshot['workspace_ids'])->toBe([$workspace->id])
        ->and($snapshot['workspace_names'])->toBe([$workspace->id => $workspace->name])
        ->and($snapshot['metrics'])->toHaveCount(9);
});

it('rejects invalid inputs before querying any workspaces', function (array $ids, string $date, string $timezone, string $errorKey) {
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        app(AiDailyReportSnapshotService::class)->collect($ids, $date, $timezone);
        $this->fail('Expected snapshot input validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey);
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
})->with([
    'empty selection' => [[], '2026-10-11', 'Asia/Manila', 'workspace_ids'],
    'more than twenty-five IDs' => [range(1, 26), '2026-10-11', 'Asia/Manila', 'workspace_ids'],
    'duplicates cannot bypass the input bound' => [array_fill(0, 26, 1), '2026-10-11', 'Asia/Manila', 'workspace_ids'],
    'non-list selection' => [[2 => 1], '2026-10-11', 'Asia/Manila', 'workspace_ids'],
    'zero ID' => [[0], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'negative ID' => [[-1], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'string ID' => [['1'], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'float ID' => [[1.0], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'boolean ID' => [[true], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'null ID' => [[null], '2026-10-11', 'Asia/Manila', 'workspace_ids.0'],
    'invalid calendar day' => [[1], '2026-02-30', 'Asia/Manila', 'date'],
    'relative date' => [[1], 'yesterday', 'Asia/Manila', 'date'],
    'date with timestamp' => [[1], '2026-10-11 12:00:00', 'Asia/Manila', 'date'],
    'non-padded date' => [[1], '2026-2-1', 'Asia/Manila', 'date'],
    'invalid timezone' => [[1], '2026-10-11', 'Invalid/Timezone', 'timezone'],
]);

it('rejects missing workspaces without collecting a partial snapshot', function () {
    $workspace = Workspace::factory()->create();
    $missingId = $workspace->id + 1000;
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        app(AiDailyReportSnapshotService::class)->collect([$workspace->id, $missingId], '2026-10-11', 'Asia/Manila');
        $this->fail('Expected a missing workspace to reject the complete selection.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'workspace_ids' => ["Selected workspaces do not exist: {$missingId}."],
        ])
            ->and(DB::getQueryLog())->toHaveCount(1)
            ->and(DB::getQueryLog()[0]['query'])->toContain('workspaces');
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('extracts section leaderboards and active student logins accurately', function () {
    $workspace = Workspace::factory()->create();
    $season = Season::factory()->active()->create();

    $visibleSection = Section::factory()->forSeason($season)->create([
        'name' => 'Alpha Section',
        'workspace_id' => $workspace->id,
        'leaderboard_enabled' => true,
    ]);
    $hiddenSection = Section::factory()->forSeason($season)->leaderboardHidden()->create([
        'name' => 'Beta Section',
        'workspace_id' => $workspace->id,
    ]);

    $student1 = User::factory()->create(['name' => 'Alice TopStudent', 'is_admin' => false]);
    $student2 = User::factory()->create(['name' => 'Bob RunnerUp', 'is_admin' => false]);
    $admin = User::factory()->create(['name' => 'Admin User', 'is_admin' => true]);

    $workspace->users()->attach($student1->id, ['role' => Workspace::ROLE_STUDENT]);
    $workspace->users()->attach($student2->id, ['role' => Workspace::ROLE_STUDENT]);
    $workspace->users()->attach($admin->id, ['role' => Workspace::ROLE_ADMIN]);

    $student1->sections()->attach($visibleSection->id, ['season_id' => $season->id]);
    $student2->sections()->attach($visibleSection->id, ['season_id' => $season->id]);
    $admin->sections()->attach($visibleSection->id, ['season_id' => $season->id]);

    $student1->update(['last_login_at' => '2026-10-11 10:00:00']);
    $student2->update(['last_login_at' => '2026-10-10 10:00:00']);
    $admin->update(['last_login_at' => '2026-10-11 10:00:00']);

    DB::table('section_progress')->insert([
        ['section_id' => $visibleSection->id, 'user_id' => $student1->id, 'exp' => 2500, 'level' => 5],
        ['section_id' => $visibleSection->id, 'user_id' => $student2->id, 'exp' => 1200, 'level' => 3],
        ['section_id' => $visibleSection->id, 'user_id' => $admin->id, 'exp' => 9999, 'level' => 99],
    ]);

    $snapshot = app(AiDailyReportSnapshotService::class)->collect([$workspace->id], '2026-10-11', 'Asia/Manila');

    expect($snapshot['section_leaderboards'])->toHaveCount(1)
        ->and($snapshot['section_leaderboards'][0])->toBe([
            'workspace_id' => $workspace->id,
            'section_id' => $visibleSection->id,
            'section_name' => 'Alpha Section',
            'top_student_name' => 'Alice TopStudent',
            'exp' => 2500,
            'level' => 5,
        ])
        ->and(aiDailyReportMetric($snapshot, $workspace, 'active_student_logins')['current'])->toBe(1)
        ->and(aiDailyReportMetric($snapshot, $workspace, 'active_student_logins')['previous'])->toBe(1);
});
