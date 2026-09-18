<?php

use App\Services\AiQuestionGeneratorService;

test('normalize drops questions with unknown types or empty text', function () {
    $result = AiQuestionGeneratorService::normalize([
        ['type' => 'multiple_choice', 'text' => '', 'options' => [['text' => 'A', 'is_correct' => true]]],
        ['type' => 'fill_in_the_blank', 'text' => 'Unsupported type'],
        'not-an-array',
        ['type' => 'essay', 'text' => 'Discuss osmosis.'],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('essay');
});

test('normalize sanitizes malformed UTF-8 from AI output', function () {
    $result = AiQuestionGeneratorService::normalize([
        [
            'type' => 'multiple_choice',
            'text' => "What powers \xC3\x28 the cell?",
            'options' => [
                ['text' => "\x93Mitochondria\x94", 'is_correct' => true],
                ['text' => 'Nucleus', 'is_correct' => false],
            ],
        ],
        [
            'type' => 'identification',
            'text' => 'Energy currency of the cell',
            'correct_answer' => "A\x00TP",
        ],
    ]);

    expect($result)->toHaveCount(2);
    expect(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();
    expect($result[0]['options'][0]['text'])->toContain('Mitochondria');
    expect($result[1]['correct_answer'])->toBe('ATP');
});

test('normalize guarantees at least one correct option for choice questions', function () {
    $result = AiQuestionGeneratorService::normalize([
        [
            'type' => 'multiple_choice',
            'text' => 'Pick one',
            'options' => [
                ['text' => 'A', 'is_correct' => false],
                ['text' => 'B', 'is_correct' => false],
            ],
        ],
    ]);

    expect($result[0]['options'][0]['is_correct'])->toBeTrue();
});

test('normalize rebuilds missing true/false options and keeps exactly one correct', function () {
    $rebuilt = AiQuestionGeneratorService::normalize([
        ['type' => 'true_false', 'text' => 'The sky is blue.', 'options' => []],
    ]);

    expect($rebuilt[0]['options'])->toHaveCount(2);
    expect(collect($rebuilt[0]['options'])->where('is_correct', true))->toHaveCount(1);

    $bothCorrect = AiQuestionGeneratorService::normalize([
        [
            'type' => 'true_false',
            'text' => 'Water is wet.',
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => true],
            ],
        ],
    ]);

    expect(collect($bothCorrect[0]['options'])->where('is_correct', true))->toHaveCount(1);
});

test('normalize drops multiple choice questions without usable options', function () {
    $result = AiQuestionGeneratorService::normalize([
        ['type' => 'multiple_choice', 'text' => 'No choices here', 'options' => [['text' => '  ']]],
    ]);

    expect($result)->toHaveCount(0);
});

test('enforceCounts trims to requested counts and drops unrequested types', function () {
    $questions = [];
    foreach (range(1, 5) as $i) {
        $questions[] = ['type' => 'multiple_choice', 'text' => "MC {$i}", 'points' => 1, 'options' => []];
    }
    foreach (range(1, 3) as $i) {
        $questions[] = ['type' => 'essay', 'text' => "Essay {$i}", 'points' => 1];
    }

    $result = AiQuestionGeneratorService::enforceCounts($questions, [
        'multiple_choice' => 2,
        'true_false' => 0,
        'identification' => 0,
        'essay' => 5,
    ]);

    expect($result)->toHaveCount(5); // 2 MC (trimmed) + 3 essay (all kept)
    expect(collect($result)->where('type', 'multiple_choice'))->toHaveCount(2);
    expect(collect($result)->where('type', 'essay'))->toHaveCount(3);
});
