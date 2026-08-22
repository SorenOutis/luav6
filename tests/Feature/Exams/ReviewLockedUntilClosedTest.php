<?php

/**
 * "Review results" must stay locked until the exam is closed.
 *
 * Finishing every part flips `is_locked` on a card, but the exam is still
 * running for everyone else. If results opened at that moment, a student who
 * finished early could reopen their paper and pass the questions (and their
 * answers) to classmates who have not sat it yet. Results therefore unlock on
 * exam *closure*, not on submission.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function reviewLockContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$student, $section];
}

it('blocks review while the exam is still open, even after submitting every part', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->forSubmission($student, $exam, $part)->create();

    actingAs($student)
        ->get(route('exams.review', $exam))
        ->assertForbidden();
})->group('security');

it('does not hand back the paper of an open exam to a finished student', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->forSubmission($student, $exam, $part)->create();

    expect(actingAs($student)->get(route('exams.review', $exam))->getContent())
        ->not->toContain('Manila');
})->group('security');

it('unlocks review once the exam is closed', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->forSubmission($student, $exam, $part)->create();

    actingAs($student)
        ->get(route('exams.review', $exam))
        ->assertOk();
})->group('security');

it('still lets an admin review an open exam', function () {
    [, $section] = reviewLockContext();
    $admin = User::factory()->create(['is_admin' => true]);
    $exam = Exam::factory()->published()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    actingAs($admin)
        ->get(route('exams.review', $exam))
        ->assertOk();
})->group('security');

it('marks results as unavailable on the card while the exam runs', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->forSubmission($student, $exam, $part)->create();

    actingAs($student)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            // Finished every part, so the card is "locked"…
            ->where('examsBySeason.0.exams.0.is_locked', true)
            // …but the results are not open for review yet.
            ->where('examsBySeason.0.exams.0.results_available', false)
            ->etc()
        );
});

it('marks results as available on the card once the exam closes', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();
    ExamSubmission::factory()->forSubmission($student, $exam, $part)->create();

    actingAs($student)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            ->where('examsBySeason.0.exams.0.results_available', true)
            ->etc()
        );
});

it('keeps results unavailable for a closed exam the student never took', function () {
    [$student, $section] = reviewLockContext();
    $exam = Exam::factory()->closed()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    actingAs($student)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            ->where('examsBySeason.0.exams.0.results_available', false)
            ->etc()
        );

    actingAs($student)
        ->get(route('exams.review', $exam))
        ->assertForbidden();
})->group('security');
