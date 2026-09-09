<?php

use App\Filament\Resources\ExamSubmissions\Pages\EditExamSubmission;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Support\ExamPartAnswerLabels;
use Livewire\Livewire;

it('lets a super admin open the edit exam submission page', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $exam = Exam::factory()->create();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    $student = User::factory()->create();
    $submission = ExamSubmission::factory()->forSubmission($student, $exam, $part)->create([
        'answers' => [
            ['question_number' => 1, 'question_type' => 'multiple_choice', 'question_text' => 'Q1?', 'points' => 2, 'answer' => 1],
        ],
        'status' => 'submitted',
        'score' => 0,
    ]);

    Livewire::test(EditExamSubmission::class, ['record' => $submission->id])
        ->assertSuccessful();
});

it('lets a workspace admin open the edit exam submission page', function () {
    $this->actingAs(User::factory()->admin()->create());

    $exam = Exam::factory()->create();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    $student = User::factory()->create();
    $submission = ExamSubmission::factory()->forSubmission($student, $exam, $part)->create([
        'answers' => [
            ['question_number' => 1, 'question_type' => 'multiple_choice', 'question_text' => 'Q1?', 'points' => 2, 'answer' => 1],
        ],
        'status' => 'submitted',
        'score' => 0,
    ]);

    Livewire::test(EditExamSubmission::class, ['record' => $submission->id])
        ->assertSuccessful();
});

it('merges enumeration answers into arrays when saving', function () {
    $merged = ExamPartAnswerLabels::mergeAnswerFieldsForSave([
        'answers' => [
            ['question_type' => 'enumeration', 'response_text' => "One\nTwo"],
            ['question_type' => 'enumeration', 'response_text' => '   '],
        ],
    ]);

    expect($merged['answers'][0]['answer'])->toBe(['One', 'Two'])
        ->and($merged['answers'][1]['answer'])->toBe([]);
});
