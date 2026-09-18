<?php

use App\Filament\Resources\Exams\Schemas\ExamForm;

it('materialises the requested number of sets', function () {
    $data = ExamForm::syncSetCount(['sets_count' => 3, 'sets' => []]);

    expect($data['sets'])->toHaveCount(3);
});

it('keeps the sets already on screen when growing the count', function () {
    $data = ExamForm::syncSetCount([
        'sets_count' => 3,
        'sets' => [
            'abc' => ['title' => 'Set A'],
            'def' => ['title' => 'Set B'],
        ],
    ]);

    expect($data['sets'])->toHaveCount(3)
        ->and(array_values($data['sets'])[0])->toBe(['title' => 'Set A'])
        ->and(array_values($data['sets'])[1])->toBe(['title' => 'Set B']);
});

it('trims sets from the end when the count is lowered', function () {
    $data = ExamForm::syncSetCount([
        'sets_count' => 1,
        'sets' => [
            'abc' => ['title' => 'Set A'],
            'def' => ['title' => 'Set B'],
        ],
    ]);

    expect($data['sets'])->toHaveCount(1)
        ->and(array_values($data['sets'])[0])->toBe(['title' => 'Set A']);
});

it('clamps the requested count to one through twenty-six sets', function () {
    expect(ExamForm::syncSetCount(['sets_count' => 0, 'sets' => []])['sets'])->toHaveCount(1)
        ->and(ExamForm::syncSetCount(['sets_count' => -4, 'sets' => []])['sets'])->toHaveCount(1)
        ->and(ExamForm::syncSetCount(['sets_count' => 500, 'sets' => []])['sets'])->toHaveCount(26);
});

it('never lets the sets counter reach the model', function () {
    expect(ExamForm::syncSetCount(['sets_count' => 2, 'sets' => []]))
        ->not->toHaveKey('sets_count');
});
