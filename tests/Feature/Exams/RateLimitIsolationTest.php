<?php

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * Regression coverage for the "Too many requests. Please try again later."
 * bug on exam submission.
 *
 * The exam routes used to be throttled with the string form `throttle:N,1`,
 * which Laravel keys by the authenticated user ONLY (no route component in
 * the key). Autosaves, the 5-second monitor-progress heartbeat, the
 * 2-second essay-grading status poll and the final submit therefore all
 * shared a single per-user counter. A student typing an essay drove that
 * shared counter past the submit route's 10-per-minute allowance, so the
 * submit request itself returned 429. The routes now use named limiters
 * (`throttle:exams.*`), whose keys hash the limiter name, giving each route
 * its own bucket.
 */
function examRateLimitContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()
        ->forExam($exam)
        ->multipleChoice(count: 1, correctIndex: 1)
        ->create();

    return [$student, $exam, $part];
}

function pingProgress(User $student, Exam $exam, ExamPart $part): void
{
    actingAs($student)
        ->postJson("/exams/{$exam->id}/monitor-progress", [
            'status' => 'in_progress',
            'exam_part_id' => $part->id,
            'submitted_parts_count' => 0,
            'current_part_answered_count' => 0,
            'current_part_total_questions' => 1,
        ])
        ->assertSuccessful();
}

it('does not let heartbeat and autosave traffic block the final submit', function () {
    [$student, $exam, $part] = examRateLimitContext();

    // Start the part (as the UI does), then mirror a real exam session's
    // traffic: a heartbeat every 5s plus periodic autosaves while typing.
    actingAs($student)
        ->postJson("/exams/{$exam->id}/parts/{$part->id}/start")
        ->assertSuccessful();

    for ($i = 0; $i < 12; $i++) {
        pingProgress($student, $exam, $part);
    }

    for ($i = 0; $i < 3; $i++) {
        actingAs($student)
            ->putJson("/exams/{$exam->id}/parts/{$part->id}/answers", [
                'answers' => [['question_number' => 1, 'answer' => 1]],
            ])
            ->assertSuccessful();
    }

    // 16 non-submit hits in a minute — over the old shared bucket's submit
    // allowance of 10. The submit must still go through on its own bucket.
    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
            'answers' => [['question_number' => 1, 'answer' => 1]],
        ])
        ->assertRedirect("/exams/{$exam->id}");
});

it('keeps the submit bucket isolated even when another exam limiter is exhausted', function () {
    [$student, $exam, $part] = examRateLimitContext();

    actingAs($student)
        ->postJson("/exams/{$exam->id}/parts/{$part->id}/start")
        ->assertSuccessful();

    // Saturate the progress heartbeat limiter (60/min) — and far past the
    // submit limiter (10/min) if the two buckets were still shared.
    for ($i = 0; $i < 60; $i++) {
        pingProgress($student, $exam, $part);
    }

    actingAs($student)
        ->postJson("/exams/{$exam->id}/monitor-progress", [
            'status' => 'in_progress',
            'exam_part_id' => $part->id,
            'submitted_parts_count' => 0,
            'current_part_answered_count' => 0,
            'current_part_total_questions' => 1,
        ])
        ->assertStatus(429);

    // Submit still succeeds: its bucket never saw the heartbeat traffic.
    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
            'answers' => [['question_number' => 1, 'answer' => 1]],
        ])
        ->assertRedirect("/exams/{$exam->id}");
});
