<?php

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
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
