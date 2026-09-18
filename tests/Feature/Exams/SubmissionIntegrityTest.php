<?php

/**
 * Exam submission integrity tests (Phase 1.3 – 1.6).
 *
 * ⚠️ MOST OF THESE FAIL BEFORE THE PHASE 1 FIXES. They encode target behaviour.
 *
 * Decisions encoded here:
 *   - Single attempt per part (no retakes).
 *   - Late submissions are ACCEPTED and FLAGGED, not rejected.
 *   - Timer is per-part.
 *   - Answer keys revealed only once the exam is closed AND the student has
 *     actually participated (no review for students who never answered).
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function integrityContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $exam = Exam::factory()->published()->forSection($section)->create();

    return [$student, $exam, $section, $season];
}

function postAnswers(User $student, Exam $exam, ExamPart $part, array $answers)
{
    $payload = collect($answers)->map(fn ($answer, $number) => [
        'question_number' => $number,
        'answer' => $answer,
    ])->values()->all();

    return actingAs($student)->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
        'answers' => $payload,
    ]);
}

// ─────────────────────────────────────────────
//  1.3 — single attempt per part
// ─────────────────────────────────────────────

it('rejects a second submission for the same part', function () {
    [$student, $exam] = integrityContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    postAnswers($student, $exam, $part, [1 => 3]);          // wrong, scores 0
    $second = postAnswers($student, $exam, $part, [1 => 1]); // corrected retry

    $second->assertStatus(409);

    expect(ExamSubmission::count())->toBe(1)
        ->and(ExamSubmission::first()->score)->toEqual('0.00');
})->group('security');

/**
 * NOTE: the retry deliberately uses a DIFFERENT (correct) answer.
 *
 * With a matching answer this test passes even without the resubmission guard,
 * because updateOrCreate() would write an identical score and the `updated`
 * hook only awards XP when `score` actually changes. Varying the answer means
 * an unguarded resubmit would award XP twice, so the test genuinely fails
 * before the fix.
 */
it('applies exam xp exactly once per part', function () {
    [$student, $exam] = integrityContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    postAnswers($student, $exam, $part, [1 => 3]); // wrong, scores 0
    postAnswers($student, $exam, $part, [1 => 1]); // correct — would re-award XP if allowed

    $history = $student->gamificationHistories()->where('reason', 'Exam Submission')->get();

    // First submit scores 0 (wrong answer) which produces no XP, so there
    // are 0 history entries. The second submit is blocked, so still 0.
    // The important assertion is that the first submission's score persists
    // and the second didn't overwrite it.
    expect($history)->toHaveCount(0)
        ->and(ExamSubmission::first()->score)->toEqual('0.00');
})->group('security');

// ─────────────────────────────────────────────
//  1.5 — scoping and access control
// ─────────────────────────────────────────────

it('rejects a part belonging to a different exam', function () {
    [$student, $examA, $section] = integrityContext();
    $examB = Exam::factory()->published()->forSection($section)->create();

    $partOfB = ExamPart::factory()->forExam($examB)->multipleChoice(count: 1, correctIndex: 0)->create();

    // Post examB's part against examA.
    $response = postAnswers($student, $examA, $partOfB, [1 => 0]);

    $response->assertNotFound();
    expect(ExamSubmission::count())->toBe(0);
})->group('security');

it('forbids submitting to an exam outside the students section', function () {
    [$student] = integrityContext();

    $otherSection = Section::factory()->create();
    $otherExam = Exam::factory()->published()->forSection($otherSection)->create();
    $part = ExamPart::factory()->forExam($otherExam)->multipleChoice(count: 1, correctIndex: 0)->create();

    $response = postAnswers($student, $otherExam, $part, [1 => 0]);

    $response->assertForbidden();
    expect(ExamSubmission::count())->toBe(0);
})->group('security');

it('forbids submitting to a closed exam', function () {
    [$student, $exam] = integrityContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    $exam->update(['status' => 'closed']);

    postAnswers($student, $exam, $part, [1 => 0])->assertForbidden();
})->group('security');

// ─────────────────────────────────────────────
//  1.4 — per-part time limit, accept-and-flag
// ─────────────────────────────────────────────

it('accepts a late submission but flags it', function () {
    [$student, $exam] = integrityContext();
    $exam->update(['duration_minutes' => 30]);
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    // Student started the part 45 minutes ago — past the 30 minute limit.
    actingAs($student)->post("/exams/{$exam->id}/parts/{$part->id}/start");
    $this->travel(45)->minutes();

    postAnswers($student, $exam, $part, [1 => 1]);

    $submission = ExamSubmission::first();

    expect($submission)->not->toBeNull()
        ->and($submission->score)->toEqual('2.00')   // still graded normally
        ->and($submission->is_late)->toBeTrue();     // but flagged for the teacher
})->group('security');

it('does not flag a submission made within the time limit', function () {
    [$student, $exam] = integrityContext();
    $exam->update(['duration_minutes' => 30]);
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 1, points: 2)->create();

    actingAs($student)->post("/exams/{$exam->id}/parts/{$part->id}/start");
    $this->travel(10)->minutes();

    postAnswers($student, $exam, $part, [1 => 1]);

    expect(ExamSubmission::first()->is_late)->toBeFalse();
})->group('security');

// ─────────────────────────────────────────────
//  1.6 — validation
// ─────────────────────────────────────────────

it('rejects a malformed answers payload', function () {
    [$student, $exam] = integrityContext();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(count: 1, correctIndex: 0)->create();

    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
            'answers' => [['answer' => 'no question number']],
        ])
        ->assertSessionHasErrors('answers.0.question_number');
})->group('security');
