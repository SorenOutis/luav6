<?php

/**
 * A CSV import replaces the questions of one set only, so a multi-set exam can
 * be filled one upload at a time.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Services\ExamTemplateService;

function examSetCsv(string $answer, string $question): string
{
    $path = tempnam(sys_get_temp_dir(), 'exam-set-').'.csv';

    file_put_contents($path, implode("\n", [
        'Part Title,Part Instructions,Question Text,Type,Choices (Pipe | Separated),Correct Choice/Answer,Points,Essay Grading (ai|manual)',
        "Part I - Identification,Identify the following.,{$question},identification,,{$answer},1,",
    ])."\n");

    return $path;
}

it('imports a CSV into the chosen set without touching the other sets', function () {
    $exam = Exam::factory()->published()->withSets(2)->create();
    [$setA, $setB] = $exam->sets()->orderBy('sort_order')->get()->all();

    $csv = examSetCsv('Set B only', 'Who is on set B?');

    try {
        $target = (new ExamTemplateService)->uploadFromCsv($exam, $csv, $setB);
    } finally {
        @unlink($csv);
    }

    expect($target->id)->toBe($setB->id)
        ->and($setA->parts()->count())->toBe(0)
        ->and($setB->parts()->count())->toBe(1)
        ->and($setB->parts()->first()->questions[0]['correct_answer'])->toBe('Set B only');
});

it('replaces the previous questions of that set on a second import', function () {
    $exam = Exam::factory()->published()->withSets(2)->create();
    $setA = $exam->sets()->orderBy('sort_order')->first();
    ExamPart::factory()->forSet($setA)->identification(['stale'])->create();

    $csv = examSetCsv('fresh', 'Who is on set A?');

    try {
        (new ExamTemplateService)->uploadFromCsv($exam, $csv, $setA);
    } finally {
        @unlink($csv);
    }

    expect($setA->parts()->count())->toBe(1)
        ->and($setA->parts()->first()->questions[0]['correct_answer'])->toBe('fresh');
});

it('falls back to the exam’s first set when no set is given', function () {
    $exam = Exam::factory()->published()->withSets(2)->create();
    $setA = $exam->sets()->orderBy('sort_order')->first();

    $csv = examSetCsv('default set', 'Who is on the default set?');

    try {
        $target = (new ExamTemplateService)->uploadFromCsv($exam, $csv);
    } finally {
        @unlink($csv);
    }

    expect($target->id)->toBe($setA->id)
        ->and($setA->parts()->count())->toBe(1);
});
