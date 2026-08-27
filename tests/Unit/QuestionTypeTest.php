<?php

use App\Enums\QuestionType;

test('question types expose the admin selector options in product order', function () {
    expect(QuestionType::options())->toBe([
        'multiple_choice' => 'Multiple Choice',
        'identification' => 'Identification',
        'essay' => 'Essay',
        'true_false' => 'True/False',
    ]);
});

test('question types classify choice and text answers', function () {
    expect(QuestionType::MultipleChoice->usesChoiceAnswer())->toBeTrue()
        ->and(QuestionType::TrueFalse->usesChoiceAnswer())->toBeTrue()
        ->and(QuestionType::Identification->usesTextAnswer())->toBeTrue()
        ->and(QuestionType::Essay->usesTextAnswer())->toBeTrue();
});

test('unknown stored values fall back to the safe multiple choice label', function () {
    expect(QuestionType::tryFromStored(' not-a-type '))->toBeNull()
        ->and(QuestionType::labelFor('not-a-type'))->toBe('Multiple Choice');
});

it('serializes the readable question type for students without exposing the answer key', function () {
    $part = \App\Models\ExamPart::factory()->make([
        'questions' => [[
            'text' => 'Which planet is known as the Red Planet?',
            'type' => 'multiple_choice',
            'points' => 1,
            'options' => [
                ['text' => 'Earth', 'is_correct' => false],
                ['text' => 'Mars', 'is_correct' => true],
            ],
        ]],
    ]);

    $question = \App\Support\ExamPartSerializer::one($part, false)['questions'][0];

    expect($question['type'])->toBe('multiple_choice')
        ->and($question['type_label'])->toBe('Multiple Choice')
        ->and($question['options'])->toBe([
            ['text' => 'Earth'],
            ['text' => 'Mars'],
        ]);
});
