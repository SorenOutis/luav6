<?php

use App\Events\ExamAnswersSaved;
use App\Models\Exam;
use App\Models\ExamAnswerDraft;
use App\Models\ExamPart;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function answerAutosaveContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $exam = Exam::factory()->published()->forSection($section)->create();
    $part = ExamPart::factory()
        ->forExam($exam)
        ->multipleChoice(count: 2, correctIndex: 1)
        ->create();

    return [$student, $exam, $part];
}

it('durably merges answer changes and broadcasts a private save acknowledgement', function () {
    [$student, $exam, $part] = answerAutosaveContext();
    Event::fake([ExamAnswersSaved::class]);

    actingAs($student)
        ->putJson("/exams/{$exam->id}/parts/{$part->id}/answers", [
            'answers' => [
                ['question_number' => 1, 'answer' => 1],
                ['question_number' => 2, 'answer' => 0],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('answered_count', 2);

    actingAs($student)
        ->putJson("/exams/{$exam->id}/parts/{$part->id}/answers", [
            'answers' => [
                ['question_number' => 2, 'answer' => 1],
            ],
        ])
        ->assertSuccessful();

    $draft = ExamAnswerDraft::firstOrFail();

    expect($draft->user_id)->toBe($student->id)
        ->and($draft->exam_id)->toBe($exam->id)
        ->and($draft->exam_part_id)->toBe($part->id)
        ->and($draft->answers)->toBe([
            ['question_number' => 1, 'answer' => 1],
            ['question_number' => 2, 'answer' => 1],
        ]);

    Event::assertDispatched(
        ExamAnswersSaved::class,
        fn (ExamAnswersSaved $event): bool => $event->userId === $student->id
            && $event->examId === $exam->id
            && $event->examPartId === $part->id
            && $event->questionNumbers === [2],
    );
});

it('restores a server answer draft when the exam page reloads', function () {
    [$student, $exam, $part] = answerAutosaveContext();

    ExamAnswerDraft::create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => [
            ['question_number' => 1, 'answer' => 1],
        ],
        'saved_at' => now(),
    ]);

    actingAs($student)
        ->get("/exams/{$exam->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exams/Show')
            ->where("answerDrafts.{$part->id}.answers.0.question_number", 1)
            ->where("answerDrafts.{$part->id}.answers.0.answer", 1)
            ->where('realtimeChannel', "exam.{$exam->id}.student.{$student->id}"));
});

it('rejects invalid or unauthorized answer autosaves', function () {
    [$student, $exam, $part] = answerAutosaveContext();
    $outsider = User::factory()->create();

    actingAs($student)
        ->putJson("/exams/{$exam->id}/parts/{$part->id}/answers", [
            'answers' => [
                ['question_number' => 99, 'answer' => 0],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers.0.question_number');

    actingAs($outsider)
        ->putJson("/exams/{$exam->id}/parts/{$part->id}/answers", [
            'answers' => [
                ['question_number' => 1, 'answer' => 1],
            ],
        ])
        ->assertForbidden();

    expect(ExamAnswerDraft::count())->toBe(0);
})->group('security');

it('removes the draft only after the final part submission is recorded', function () {
    [$student, $exam, $part] = answerAutosaveContext();

    $draft = ExamAnswerDraft::create([
        'user_id' => $student->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => [
            ['question_number' => 1, 'answer' => 1],
            ['question_number' => 2, 'answer' => 1],
        ],
        'saved_at' => now(),
    ]);

    actingAs($student)
        ->post("/exams/{$exam->id}/parts/{$part->id}/submit", [
            'answers' => [
                ['question_number' => 1, 'answer' => 1],
                ['question_number' => 2, 'answer' => 1],
            ],
        ])
        ->assertRedirect("/exams/{$exam->id}");

    $this->assertModelMissing($draft);
});
