<?php

use App\Enums\QuestionType;
use App\Models\ExamPart;
use App\Support\ExamPartSerializer;

test('question types expose the admin selector options in product order', function () {
    expect(QuestionType::options())->toBe([
        'multiple_choice' => 'Multiple Choice',
        'identification' => 'Identification',
        'enumeration' => 'Enumeration',
        'true_false' => 'True/False',
        'essay' => 'Essay',
    ]);
});

test('question types classify choice and text answers', function () {
    expect(QuestionType::MultipleChoice->usesChoiceAnswer())->toBeTrue()
        ->and(QuestionType::TrueFalse->usesChoiceAnswer())->toBeTrue()
        ->and(QuestionType::Enumeration->usesEnumerationAnswer())->toBeTrue()
        ->and(QuestionType::Identification->usesTextAnswer())->toBeTrue()
        ->and(QuestionType::Essay->usesTextAnswer())->toBeTrue();
});

test('unknown stored values fall back to the safe multiple choice label', function () {
    expect(QuestionType::tryFromStored(' not-a-type '))->toBeNull()
        ->and(QuestionType::labelFor('not-a-type'))->toBe('Multiple Choice');
});

it('serializes the readable question type for students without exposing the answer key', function () {
    $part = new ExamPart([
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

    $question = ExamPartSerializer::one($part, false)['questions'][0];

    expect($question['type'])->toBe('multiple_choice')
        ->and($question['type_label'])->toBe('Multiple Choice')
        ->and($question['options'])->toBe([
            ['text' => 'Earth'],
            ['text' => 'Mars'],
        ]);
});

it('serializes Enumeration slots with points but not expected answers', function () {
    $part = new ExamPart([
        'questions' => [[
            'text' => 'List the three pillars of SEO.',
            'type' => 'enumeration',
            'points' => 999,
            'enumeration_items' => [
                ['answer' => 'Technical SEO', 'points' => 2],
                ['answer' => 'On-page SEO', 'points' => 3],
                ['answer' => 'Off-page SEO', 'points' => 5],
            ],
        ]],
    ]);

    $question = ExamPartSerializer::one($part, false)['questions'][0];

    expect($question['type_label'])->toBe('Enumeration')
        ->and($question['points'])->toBe(10.0)
        ->and($question['enumeration_items'])->toBe([
            ['points' => 2.0],
            ['points' => 3.0],
            ['points' => 5.0],
        ])
        ->and($question)->not->toHaveKey('correct_answer');
});
