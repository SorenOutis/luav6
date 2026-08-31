<?php

use App\Models\Exam;
use App\Models\ExamPart;
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

it('passes an empty My Scores list when the student has no visible exams', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->where('activityScores', []));
});
