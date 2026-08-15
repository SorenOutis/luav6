<?php

use App\Models\Exam;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamXpAward;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function xpAwardContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['exp' => 0, 'points' => 0]);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $exam = Exam::factory()->published()->forSection($section)->create([
        'completion_xp' => 10,
        'on_time_xp' => 5,
        'accuracy_xp_enabled' => true,
    ]);

    return [$student, $exam, $section];
}

function submitXpPart(User $student, Exam $exam, ExamPart $part, int $answer = 1)
{
    return actingAs($student)->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
        'answers' => [[
            'question_number' => 1,
            'answer' => $answer,
        ]],
    ]);
}

it('keeps academic points separate and awards xp after the final part', function () {
    [$student, $exam, $section] = xpAwardContext();
    $first = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 10)->create(['sort_order' => 0]);
    $last = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 10)->create(['sort_order' => 1]);

    submitXpPart($student, $exam, $first);

    expect(ExamXpAward::count())->toBe(0)
        ->and((float) $student->fresh()->points)->toBe(10.0)
        ->and((float) $student->fresh()->exp)->toBe(0.0);

    submitXpPart($student, $exam, $last);

    $award = ExamXpAward::firstOrFail();
    $progress = $student->activeSectionProgress($section->id)->fresh();

    expect((int) $award->completion_xp)->toBe(10)
        ->and((int) $award->on_time_xp)->toBe(5)
        ->and((int) $award->accuracy_xp)->toBe(15)
        ->and((float) $award->accuracy_percentage)->toBe(100.0)
        ->and((float) $progress->points)->toBe(20.0)
        ->and((float) $progress->exp)->toBe(30.0);
});

it('uses only the highest accuracy tier', function (float $scorePercent, int $expectedXp) {
    [$student, $exam] = xpAwardContext();
    $part = ExamPart::factory()->forExam($exam)->withQuestions([
        [
            'text' => 'Question?',
            'type' => 'multiple_choice',
            'points' => 100,
            'options' => [
                ['text' => 'Wrong', 'is_correct' => false],
                ['text' => 'Correct', 'is_correct' => true],
            ],
        ],
    ])->create();

    // Create the desired percentage directly so this data set checks all tier
    // boundaries without requiring 100 one-point questions.
    \App\Models\ExamSubmission::create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => [],
        'status' => 'submitted',
        'score' => $scorePercent,
    ]);

    $award = app(\App\Services\ExamXpAwardService::class)->awardIfEligible($student, $exam);

    expect((int) $award->accuracy_xp)->toBe($expectedXp);
})->with([
    'below threshold' => [69, 0],
    '70 percent' => [70, 5],
    '85 percent' => [85, 10],
    '95 percent' => [95, 15],
]);

it('does not grant the on-time bonus when any part is late', function () {
    [$student, $exam] = xpAwardContext();
    $exam->update(['duration_minutes' => 10]);
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 10)->create();

    ExamLiveSession::create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'status' => 'in_progress',
        'started_at' => now()->subMinutes(20),
        'last_seen_at' => now(),
    ]);

    submitXpPart($student, $exam, $part);

    expect((int) ExamXpAward::firstOrFail()->on_time_xp)->toBe(0);
});

it('is idempotent when eligibility is checked repeatedly', function () {
    [$student, $exam] = xpAwardContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 10)->create();
    submitXpPart($student, $exam, $part);

    $service = app(\App\Services\ExamXpAwardService::class);
    $service->awardIfEligible($student, $exam);
    $service->awardIfEligible($student, $exam);

    expect(ExamXpAward::count())->toBe(1)
        ->and((float) $student->fresh()->exp)->toBe(30.0)
        ->and($student->gamificationHistories()->where('amount_xp', '>', 0)->count())->toBe(3);
});
