<?php

/**
 * Export questions turns the current exam questions back into the same CSV
 * shape the import accepts, so an exported file can be re-imported as-is.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Services\ExamTemplateService;

function exportCsvPath(string $csv): string
{
    $path = tempnam(sys_get_temp_dir(), 'exam-export-').'.csv';
    file_put_contents($path, $csv);

    return $path;
}

it('round-trips every question type through export and import', function () {
    $exam = Exam::factory()->published()->withSets(1)->create();
    $set = $exam->sets()->first();

    ExamPart::factory()->forSet($set)->withQuestions([
        [
            'text' => 'Capital of France?',
            'type' => 'multiple_choice',
            'points' => 2,
            'options' => [
                ['text' => 'Berlin', 'is_correct' => false],
                ['text' => 'Paris', 'is_correct' => true],
            ],
        ],
        [
            'text' => 'The sun is a star.',
            'type' => 'true_false',
            'points' => 1,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ],
        [
            'text' => 'Who wrote Noli Me Tangere?',
            'type' => 'identification',
            'points' => 3,
            'correct_answer' => 'Jose Rizal',
            'accepted_answers' => [['answer' => 'J. Rizal']],
        ],
        [
            'text' => 'List the pillars.',
            'type' => 'enumeration',
            'points' => 5,
            'enumeration_items' => [
                ['answer' => 'Technical SEO', 'points' => 2],
                ['answer' => 'On-page SEO', 'points' => 3],
            ],
        ],
        [
            'text' => 'Match each pillar.',
            'type' => 'matching',
            'points' => 5,
            'matching_items' => [
                ['prompt' => 'Technical SEO', 'answer' => 'Crawlability', 'points' => 2],
                ['prompt' => 'On-page SEO', 'answer' => 'Content', 'points' => 3],
            ],
        ],
        [
            'text' => 'Explain photosynthesis.',
            'type' => 'essay',
            'points' => 10,
            'grading_method' => 'manual',
        ],
    ])->create();

    $csv = (new ExamTemplateService)->exportCsv($exam, $set);
    $path = exportCsvPath($csv);

    $target = Exam::factory()->published()->withSets(1)->create();
    $targetSet = $target->sets()->first();

    try {
        (new ExamTemplateService)->uploadFromCsv($target, $path, $targetSet);
    } finally {
        @unlink($path);
    }

    $questions = $targetSet->parts()->first()->questions;

    expect(count($questions))->toBe(6);

    $multipleChoice = collect($questions)->first(fn ($q) => $q['type'] === 'multiple_choice');
    expect($multipleChoice['options'][1]['text'])->toBe('Paris')
        ->and($multipleChoice['options'][1]['is_correct'])->toBeTrue();

    $trueFalse = collect($questions)->first(fn ($q) => $q['type'] === 'true_false');
    expect($trueFalse['options'][0]['text'])->toBe('True')
        ->and($trueFalse['options'][0]['is_correct'])->toBeTrue();

    $identification = collect($questions)->first(fn ($q) => $q['type'] === 'identification');
    expect($identification['correct_answer'])->toBe('Jose Rizal')
        ->and($identification['accepted_answers'][0]['answer'])->toBe('J. Rizal');

    $enumeration = collect($questions)->first(fn ($q) => $q['type'] === 'enumeration');
    expect(count($enumeration['enumeration_items']))->toBe(2)
        ->and($enumeration['enumeration_items'][1]['answer'])->toBe('On-page SEO')
        ->and((float) $enumeration['enumeration_items'][1]['points'])->toBe(3.0);

    $matching = collect($questions)->first(fn ($q) => $q['type'] === 'matching');
    expect(count($matching['matching_items']))->toBe(2)
        ->and($matching['matching_items'][0]['prompt'])->toBe('Technical SEO')
        ->and($matching['matching_items'][0]['answer'])->toBe('Crawlability');

    $essay = collect($questions)->first(fn ($q) => $q['type'] === 'essay');
    expect($essay['grading_method'])->toBe('manual');
});

it('packages each set into its own CSV inside a ZIP archive', function () {
    if (! class_exists(\ZipArchive::class)) {
        $this->markTestSkipped('The ZIP extension is not available.');
    }

    $exam = Exam::factory()->published()->withSets(2)->create();
    [$setA, $setB] = $exam->sets()->orderBy('sort_order')->get()->all();

    ExamPart::factory()->forSet($setA)->identification(['A only'])->create();
    ExamPart::factory()->forSet($setB)->identification(['B only'])->create();

    $zipPath = (new ExamTemplateService)->exportZip($exam);

    try {
        $zip = new \ZipArchive;
        expect($zip->open($zipPath))->toBe(true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        expect($names)->toContain('set-a.csv', 'set-b.csv')
            ->and(count($names))->toBe(2);
    } finally {
        @unlink($zipPath);
    }
});
