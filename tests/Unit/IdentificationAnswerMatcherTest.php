<?php

use App\Support\IdentificationAnswerMatcher;

test('matches any configured Identification answer after normalization', function () {
    $question = [
        'correct_answer' => 'Virus',
        'accepted_answers' => [
            ['answer' => 'Malware'],
            ['answer' => 'Computer virus'],
        ],
    ];

    expect(IdentificationAnswerMatcher::matches(' malware! ', $question))->toBeTrue()
        ->and(IdentificationAnswerMatcher::matches('COMPUTER   VIRUS', $question))->toBeTrue()
        ->and(IdentificationAnswerMatcher::matches('Worm', $question))->toBeFalse();
});

test('keeps legacy primary answers and removes normalized duplicates', function () {
    $answers = IdentificationAnswerMatcher::acceptedAnswers([
        'correct_answer' => 'Virus',
        'accepted_answers' => [
            ['answer' => 'Malware'],
            ['answer' => ' virus! '],
        ],
    ]);

    expect($answers)->toBe(['Virus', 'Malware']);
});

test('formats all accepted Identification answers for answer-key display', function () {
    expect(IdentificationAnswerMatcher::display([
        'correct_answer' => 'Virus',
        'accepted_answers' => [['answer' => 'Malware']],
    ]))->toBe('Virus or Malware');
});
