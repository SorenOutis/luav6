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
