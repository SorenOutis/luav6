<?php

use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('passes visible exams to the activities hub card props', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->published()->create([
        'title' => 'Visible activity',
    ]);

    ExamPart::factory()
        ->forExam($exam)
        ->multipleChoice(1)
        ->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->has('examsBySeason.0.exams', 1)
            ->where('examsBySeason.0.exams.0.id', $exam->id)
            ->where('examsBySeason.0.exams.0.title', 'Visible activity')
            ->where('examsBySeason.0.exams.0.total_parts', 1)
            ->where('hubStats.exams.total', 1)
            ->where('sectionTabs.0.key', 'all'));
});

it('returns the next activities page from the hub listing endpoint', function () {
    $user = User::factory()->create();

    Exam::factory()
        ->count(25)
        ->published()
        ->create()
        ->each(fn (Exam $exam) => ExamPart::factory()->forExam($exam)->create());

    $first = actingAs($user)
        ->getJson(route('activities.listing'))
        ->assertOk()
        ->assertJsonCount(24, 'data.0.exams')
        ->assertJsonPath('meta.hasMore', true);

    actingAs($user)
        ->getJson(route('activities.listing', [
            'cursor' => $first->json('meta.nextCursor'),
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.0.exams')
        ->assertJsonPath('meta.hasMore', false);
});

it('passes per-activity scores to the My Scores drawer prop', function () {
    $user = User::factory()->create();

    // `created_at` is not fillable, so unguard to pin the timestamp and keep
    // the drawer's newest-first ordering deterministic.
    $scored = Exam::unguarded(fn () => Exam::factory()->published()->create([
        'title' => 'Scored activity',
        'created_at' => now()->subMinute(),
    ]));
    $part = ExamPart::factory()->forExam($scored)->multipleChoice(1)->create();
    ExamSubmission::factory()
        ->forSubmission($user, $scored, $part)
        ->graded(88.5)
        ->create();

    $untaken = Exam::factory()->published()->create([
        'title' => 'Untaken activity',
    ]);
    ExamPart::factory()->forExam($untaken)->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->has('activityScores.0.exams', 2)
            ->where('activityScores.0.exams.0.title', 'Untaken activity')
            ->where('activityScores.0.exams.0.score', null)
            ->where('activityScores.0.exams.0.state', 'open')
            ->where('activityScores.0.exams.1.title', 'Scored activity')
            ->where('activityScores.0.exams.1.score', 88.5)
            ->where('activityScores.0.exams.1.state', 'completed'));
});

it('flags a scheduled exam as upcoming and not open before its start time', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->published()->create([
        'title' => 'Scheduled activity',
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);
    ExamPart::factory()->forExam($exam)->multipleChoice(1)->create();

    // The hub card keys its "Start" lock off these flags, so they are the
    // contract the UI relies on. Before `starts_at` the exam is neither
    // locked (nothing submitted, not closed) nor open.
    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('examsBySeason.0.exams.0.id', $exam->id)
            ->where('examsBySeason.0.exams.0.is_upcoming', true)
            ->where('examsBySeason.0.exams.0.is_open_now', false)
            ->where('examsBySeason.0.exams.0.has_ended', false)
            ->where('examsBySeason.0.exams.0.is_locked', false)
            ->where('examsBySeason.0.exams.0.starts_at_iso', $exam->starts_at->toIso8601String())
            // Still pending in the overview: it has not been taken yet.
            ->where('hubStats.exams.pending', 1)
            ->where('hubStats.exams.completed', 0));
});

it('flags a scheduled exam as open inside its window', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->published()->create([
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
    ]);
    ExamPart::factory()->forExam($exam)->multipleChoice(1)->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('examsBySeason.0.exams.0.is_upcoming', false)
            ->where('examsBySeason.0.exams.0.is_open_now', true)
            ->where('examsBySeason.0.exams.0.has_ended', false)
            ->where('examsBySeason.0.exams.0.is_locked', false));
});

it('treats an exam whose window ended as closed in the tiles, cards and scores alike', function () {
    $user = User::factory()->create();

    // Published (never manually closed) but the scheduled window is over.
    $ended = Exam::factory()->published()->create([
        'title' => 'Ended activity',
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);
    ExamPart::factory()->forExam($ended)->multipleChoice(1)->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            // Card: locked + ended, not open.
            ->where('examsBySeason.0.exams.0.has_ended', true)
            ->where('examsBySeason.0.exams.0.is_open_now', false)
            ->where('examsBySeason.0.exams.0.is_locked', true)
            // Overview tile: an ended exam the student never took is neither
            // pending nor completed. Before this was fixed, hubSummary()
            // only looked at `status`, so the tile still said "1 pending"
            // while the card underneath it read "Closed".
            ->where('hubStats.exams.total', 1)
            ->where('hubStats.exams.pending', 0)
            ->where('hubStats.exams.completed', 0)
            // My Scores drawer: same rule, so it never calls it "open".
            ->where('activityScores.0.exams.0.title', 'Ended activity')
            ->where('activityScores.0.exams.0.state', 'closed'));
});

it('counts an ended exam the student answered as completed', function () {
    $user = User::factory()->create();

    $ended = Exam::factory()->published()->create([
        'title' => 'Ended and taken',
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);
    $part = ExamPart::factory()->forExam($ended)->multipleChoice(1)->create();
    ExamSubmission::factory()
        ->forSubmission($user, $ended, $part)
        ->graded(70.5)
        ->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('examsBySeason.0.exams.0.results_available', true)
            ->where('hubStats.exams.pending', 0)
            ->where('hubStats.exams.completed', 1)
            ->where('activityScores.0.exams.0.state', 'completed')
            ->where('activityScores.0.exams.0.score', 70.5));
});

it('passes an empty My Scores list when the student has no visible exams', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('activityScores', []));
});

it('hides activity record when the student sections have activity_record_enabled turned off', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create([
        'activity_record_enabled' => false,
    ]);
    $section->users()->attach($user->id);

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('activityRecordEnabled', false));
});

it('passes exam term, total points, and missed status to activityScores', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
        'activity_record_enabled' => true,
    ]);
    $section->users()->attach($user->id);

    $missedExam = Exam::factory()->published()->create([
        'title' => 'Quarter 1 Quiz',
        'term' => '1st Quarter',
        'section_id' => $section->id,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
    ]);
    ExamPart::factory()->forExam($missedExam)->multipleChoice(2, 1, 5)->create();

    $res = actingAs($user)
        ->get(route('activities.index'));

    $res->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('activityRecordEnabled', true));

    $examScore = $res->viewData('page')['props']['activityScores'][0]['exams'][0];
    expect($examScore['title'])->toBe('Quarter 1 Quiz')
        ->and($examScore['category'])->toBe('written')
        ->and($examScore['section_id'])->toBe($missedExam->section_id)
        ->and($examScore['section_name'])->toBe($section->name)
        ->and($examScore['term'])->toBe('1st Quarter')
        ->and($examScore['is_missed'])->toBeTrue()
        ->and((float) $examScore['total_points'])->toBe(10.0);
});

it('scopes exam term options strictly by school level', function () {
    $college = Section::examTermOptions(Section::SCHOOL_LEVEL_COLLEGE);
    expect(array_keys($college))->toBe(['Prelim', 'Midterm', 'Final']);

    $shs = Section::examTermOptions(Section::SCHOOL_LEVEL_SENIOR_HIGH);
    expect(array_keys($shs))->toBe([
        'First Semester - 1st Quarter',
        'First Semester - 2nd Quarter',
        'Second Semester - 1st Quarter',
        'Second Semester - 2nd Quarter',
    ]);
});

it('filters activityScores by allowed section activity_record_terms', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'activity_record_enabled' => true,
        'activity_record_terms' => ['Prelim'],
    ]);
    $section->users()->attach($user->id);

    $prelimExam = Exam::factory()->published()->create([
        'title' => 'Prelim Exam',
        'term' => 'Prelim',
        'section_id' => $section->id,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
    ]);
    ExamPart::factory()->forExam($prelimExam)->multipleChoice(2, 1, 5)->create();

    $midtermExam = Exam::factory()->published()->create([
        'title' => 'Midterm Exam',
        'term' => 'Midterm',
        'section_id' => $section->id,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
    ]);
    ExamPart::factory()->forExam($midtermExam)->multipleChoice(2, 1, 5)->create();

    $res = actingAs($user)->get(route('activities.index'))->assertOk();

    $scores = $res->viewData('page')['props']['activityScores'];
    $allExamTitles = collect($scores)->flatMap(fn ($group) => collect($group['exams'])->pluck('title'))->all();

    expect($allExamTitles)->toContain('Prelim Exam');
    expect($allExamTitles)->not()->toContain('Midterm Exam');

    // When activity_record_terms is empty, all terms are allowed
    $section->update(['activity_record_terms' => null]);
    $resAll = actingAs($user)->get(route('activities.index'))->assertOk();
    $allExamTitlesNow = collect($resAll->viewData('page')['props']['activityScores'])
        ->flatMap(fn ($group) => collect($group['exams'])->pluck('title'))
        ->all();

    expect($allExamTitlesNow)->toContain('Prelim Exam');
    expect($allExamTitlesNow)->toContain('Midterm Exam');
});

it('includes senior high performance task scores in activityScores', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
        'activity_record_enabled' => true,
    ]);
    $section->users()->attach($user->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'PT 1: Creative Presentation',
        'term' => 'First Semester - 1st Quarter',
        'task_type' => 'Performance Task',
        'max_points' => 50,
    ]);

    ActivityTaskScore::create([
        'activity_task_id' => $task->id,
        'user_id' => $user->id,
        'score' => 45,
        'is_missed' => false,
    ]);

    $res = actingAs($user)->get(route('activities.index'))->assertOk();

    $scores = $res->viewData('page')['props']['activityScores'];
    $allExams = collect($scores)->flatMap(fn ($group) => $group['exams'])->all();

    $pt = collect($allExams)->firstWhere('title', 'PT 1: Creative Presentation');
    expect($pt)->not->toBeNull();
    expect($pt['activity_type'])->toBe('Performance Task');
    expect($pt['category'])->toBe('performance');
    expect($pt['section_id'])->toBe($task->section_id);
    expect($pt['section_name'])->toBe($section->name);
    expect((float) $pt['score'])->toBe(45.0);
    expect((float) $pt['total_points'])->toBe(50.0);
    expect((float) $pt['percentage'])->toBe(90.0);
    expect($pt['submitted'])->toBeTrue();
});

it('includes college custom activity task scores in activityScores', function () {
    $user = User::factory()->create();
    $section = Section::factory()->create([
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'activity_record_enabled' => true,
    ]);
    $section->users()->attach($user->id);

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'Laboratory 1: Packet Analysis',
        'term' => 'Prelim',
        'task_type' => 'Laboratory',
        'max_points' => 100,
    ]);

    ActivityTaskScore::create([
        'activity_task_id' => $task->id,
        'user_id' => $user->id,
        'score' => 95,
        'is_missed' => false,
    ]);

    $res = actingAs($user)->get(route('activities.index'))->assertOk();

    $scores = $res->viewData('page')['props']['activityScores'];
    $allExams = collect($scores)->flatMap(fn ($group) => $group['exams'])->all();

    $lab = collect($allExams)->firstWhere('title', 'Laboratory 1: Packet Analysis');
    expect($lab)->not->toBeNull();
    expect($lab['activity_type'])->toBe('Laboratory');
    expect($lab['category'])->toBe('performance');
    expect($lab['section_id'])->toBe($task->section_id);
    expect($lab['section_name'])->toBe($section->name);
    expect((float) $lab['score'])->toBe(95.0);
    expect((float) $lab['total_points'])->toBe(100.0);
    expect((float) $lab['percentage'])->toBe(95.0);
});

it('eager loads section season for both exams and tasks so they share the same season name', function () {
    $user = User::factory()->create();
    $season = Season::factory()->create(['name' => 'Season 1']);
    $section = Section::factory()->create([
        'name' => 'Section A',
        'season_id' => $season->id,
        'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
        'activity_record_enabled' => true,
    ]);
    $section->users()->attach($user->id);

    $exam = Exam::factory()->published()->create([
        'title' => 'Written Quiz #1',
        'term' => 'First Semester - 1st Quarter',
        'section_id' => $section->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->addDays(2),
    ]);
    ExamPart::factory()->forExam($exam)->multipleChoice(2, 1, 5)->create();

    $task = ActivityTask::create([
        'section_id' => $section->id,
        'title' => 'PT #1',
        'term' => 'First Semester - 1st Quarter',
        'task_type' => 'Performance Task',
        'max_points' => 100,
    ]);

    $res = actingAs($user)->get(route('activities.index'))->assertOk();

    $scores = $res->viewData('page')['props']['activityScores'];
    expect($scores)->toHaveCount(1);
    expect($scores[0]['seasonName'])->toBe('Season 1');

    $allExams = $scores[0]['exams'];
    $writtenExam = collect($allExams)->firstWhere('title', 'Written Quiz #1');
    $performanceTask = collect($allExams)->firstWhere('title', 'PT #1');

    expect($writtenExam)->not->toBeNull()
        ->and($writtenExam['season_name'])->toBe('Season 1')
        ->and($writtenExam['section_id'])->toBe($section->id)
        ->and($writtenExam['category'])->toBe('written');

    expect($performanceTask)->not->toBeNull()
        ->and($performanceTask['season_name'])->toBe('Season 1')
        ->and($performanceTask['section_id'])->toBe($section->id)
        ->and($performanceTask['category'])->toBe('performance');
});
