<?php

use App\Support\MatchingAnswerMatcher;

function matchingQuestion(): array
{
    return [
        'type' => 'matching',
        'matching_items' => [
            ['prompt' => 'Technical SEO', 'answer' => 'Crawlability', 'points' => 2],
            ['prompt' => 'On-page SEO', 'answer' => 'Content and headings', 'points' => 3],
            ['prompt' => 'Off-page SEO', 'answer' => 'External authority signals', 'points' => 5],
        ],
    ];
}

test('matching scores each aligned pair with normalized text and partial credit', function () {
    $question = matchingQuestion();

    expect(MatchingAnswerMatcher::score($question, [
        ' crawlability ',
        'CONTENT   AND HEADINGS!',
        'wrong',
    ]))->toBe(5.0);

    expect(MatchingAnswerMatcher::breakdown($question, [
        ' crawlability ',
        'CONTENT   AND HEADINGS!',
        'wrong',
    ]))->toMatchArray([
        ['prompt' => 'Technical SEO', 'expected' => 'Crawlability', 'submitted' => 'crawlability', 'points' => 2.0, 'earned' => 2.0, 'matched' => true],
        ['prompt' => 'On-page SEO', 'expected' => 'Content and headings', 'submitted' => 'CONTENT   AND HEADINGS!', 'points' => 3.0, 'earned' => 3.0, 'matched' => true],
        ['prompt' => 'Off-page SEO', 'expected' => 'External authority signals', 'submitted' => 'wrong', 'points' => 5.0, 'earned' => 0.0, 'matched' => false],
    ]);
});

test('matching options are visible once and max points sum pair values', function () {
    $question = matchingQuestion();

    expect(MatchingAnswerMatcher::options($question))->toBe([
        'Crawlability',
        'Content and headings',
        'External authority signals',
    ])->and(MatchingAnswerMatcher::maxPoints($question))->toBe(10.0);
});

test('matching does not award duplicate credit for repeated expected answers', function () {
    $question = [
        'matching_items' => [
            ['prompt' => 'First prompt', 'answer' => 'Shared answer', 'points' => 2],
            ['prompt' => 'Second prompt', 'answer' => 'Shared answer', 'points' => 3],
        ],
    ];

    expect(MatchingAnswerMatcher::score($question, [
        'Shared answer',
        'Shared answer',
    ]))->toBe(2.0);
});

test('matching selection validation accepts only configured visible choices', function () {
    $question = matchingQuestion();

    expect(MatchingAnswerMatcher::isValidSelection('', $question))->toBeTrue()
        ->and(MatchingAnswerMatcher::isValidSelection(' crawlability ', $question))->toBeTrue()
        ->and(MatchingAnswerMatcher::isValidSelection('Not an option', $question))->toBeFalse()
        ->and(MatchingAnswerMatcher::isValidSelection(['Crawlability'], $question))->toBeFalse();
});
