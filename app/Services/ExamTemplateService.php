<?php

namespace App\Services;

use App\Enums\EssayGradingMethod;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamTemplateService
{
    /**
     * The single source of truth for the CSV columns. Both the downloadable
     * template and the exam export use it, so an exported file always parses
     * with the same column names the import expects.
     *
     * @return array<int, string>
     */
    public function header(): array
    {
        return [
            'Part Title',
            'Part Instructions',
            'Question Text',
            'Type',
            'Choices (Pipe | Separated)',
            'Correct Choice/Answer',
            'Points',
            'Essay Grading (ai|manual)',
        ];
    }

    public function getTemplateCsv(): string
    {
        $handle = fopen('php://memory', 'r+');

        // CSV Header
        fputcsv($handle, $this->header());

        // Example Rows
        fputcsv($handle, [
            'Part I - Multiple Choice',
            'Select the best answer for each question.',
            'What is the capital of France?',
            'multiple_choice',
            'Berlin|Madrid|Paris|Rome',
            'Paris',
            '1',
            '',
        ]);

        fputcsv($handle, [
            'Part I - Multiple Choice',
            '',
            'Which planet is known as the Red Planet?',
            'multiple_choice',
            'Earth|Mars|Jupiter|Saturn',
            'Mars',
            '1',
            '',
        ]);

        fputcsv($handle, [
            'Part II - True or False',
            'Write True if the statement is correct, otherwise False.',
            'The sun is a star.',
            'true_false',
            'True|False',
            'True',
            '1',
            '',
        ]);

        fputcsv($handle, [
            'Part III - Enumeration',
            'List the three pillars of SEO.',
            'What are the three pillars of SEO?',
            'enumeration',
            '',
            'Technical SEO::2|On-page SEO::3|Off-page SEO::5',
            '10',
            '',
        ]);

        fputcsv($handle, [
            'Part IV - Identification',
            'Identify the following.',
            'Who wrote "Noli Me Tangere"?',
            'identification',
            '',
            'Jose Rizal|J. Rizal',
            '5',
            '',
        ]);

        fputcsv($handle, [
            'Part V - Matching Type',
            'Match each item on the left with the best answer on the right.',
            'Match each SEO pillar with its description.',
            'matching',
            '',
            'Technical SEO=>Crawlability and site structure::2|On-page SEO=>Content and headings::3|Off-page SEO=>External authority signals::5',
            '10',
            '',
        ]);

        fputcsv($handle, [
            'Part VI - Essay',
            'Answer in complete sentences.',
            'Explain why photosynthesis is important to life on Earth.',
            'essay',
            '',
            '',
            '10',
            'ai',
        ]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Replace the questions of one set with the contents of a CSV.
     *
     * Only the target set is touched, so a multi-set exam is filled one upload
     * at a time (Set A, then Set B, …). Without a set the exam's first set is
     * used, which keeps single-set imports working exactly as before.
     */
    public function uploadFromCsv(Exam $exam, string $csvPath, ?ExamSet $set = null): ExamSet
    {
        $set ??= ExamSet::ensureDefaultForExam($exam->getKey());

        $rows = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            // Check for BOM and skip it if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle);
            if ($header) {
                // Ensure header is UTF-8
                $header = array_map(fn ($h) => $this->ensureUtf8($h), $header);
            }

            while (($data = fgetcsv($handle)) !== false) {
                if (count($header) === count($data)) {
                    // Ensure data is UTF-8
                    $data = array_map(fn ($d) => $this->ensureUtf8($d), $data);
                    $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }

        DB::transaction(function () use ($exam, $rows, $set) {
            // Delete existing parts of THIS set for a clean slate — importing
            // Set B must not wipe the questions already written for Set A.
            $exam->parts()->where('exam_set_id', $set->getKey())->delete();

            $partsData = [];
            $sortOrder = 0;

            foreach ($rows as $row) {
                $partTitle = $row['Part Title'] ?? 'Default Part';
                $partInstructions = $row['Part Instructions'] ?? '';

                if (! isset($partsData[$partTitle])) {
                    $partsData[$partTitle] = [
                        'instructions' => $partInstructions,
                        'questions' => [],
                        'sort_order' => $sortOrder++,
                    ];
                }

                $type = QuestionType::tryFromStored($row['Type'] ?? null) ?? QuestionType::MultipleChoice;
                $questionText = $row['Question Text'] ?? '';
                $choicesStr = $row['Choices (Pipe | Separated)'] ?? '';
                $correctInput = $row['Correct Choice/Answer'] ?? '';

                $questionData = [
                    'text' => $questionText,
                    'type' => $type->value,
                    'options' => [],
                    'correct_answer' => null,
                    'points' => (int) ($row['Points'] ?? 1),
                ];

                if ($type->usesEnumerationAnswer()) {
                    $questionData['enumeration_items'] = collect(explode('|', $correctInput))
                        ->map(function (string $item): array {
                            [$answer, $points] = array_pad(explode('::', trim($item), 2), 2, '1');

                            return [
                                'answer' => trim($answer),
                                'points' => (float) ($points !== '' ? $points : 1),
                            ];
                        })
                        ->filter(fn (array $item): bool => $item['answer'] !== '')
                        ->values()
                        ->all();
                } elseif ($type->usesMatchingAnswer()) {
                    $questionData['matching_items'] = collect(explode('|', $correctInput))
                        ->map(function (string $item): array {
                            [$prompt, $answerAndPoints] = array_pad(explode('=>', trim($item), 2), 2, '');
                            [$answer, $points] = array_pad(explode('::', trim($answerAndPoints), 2), 2, '1');

                            return [
                                'prompt' => trim($prompt),
                                'answer' => trim($answer),
                                'points' => (float) ($points !== '' ? $points : 1),
                            ];
                        })
                        ->filter(fn (array $item): bool => $item['prompt'] !== '' && $item['answer'] !== '')
                        ->values()
                        ->all();
                } elseif ($type->usesChoiceAnswer()) {
                    $choices = array_filter(array_map('trim', explode('|', $choicesStr)));
                    foreach ($choices as $choiceText) {
                        // Case-insensitive comparison for correct answer
                        $isCorrect = strcasecmp(trim($choiceText), trim($correctInput)) === 0;

                        $questionData['options'][] = [
                            'text' => $choiceText,
                            'is_correct' => $isCorrect,
                        ];
                    }
                } elseif ($type === QuestionType::Identification) {
                    $acceptedAnswers = collect(preg_split('/\s*\|\s*/', $correctInput) ?: [])
                        ->map(fn (string $answer): string => trim($answer))
                        ->filter()
                        ->values();

                    $questionData['correct_answer'] = $acceptedAnswers->first() ?? '';
                    $alternatives = $acceptedAnswers->skip(1)
                        ->map(fn (string $answer): array => ['answer' => $answer])
                        ->values()
                        ->all();

                    if ($alternatives !== []) {
                        $questionData['accepted_answers'] = $alternatives;
                    }
                } elseif ($type === QuestionType::Essay) {
                    $gradingMethod = str((string) ($row['Essay Grading (ai|manual)'] ?? ''))
                        ->trim()
                        ->lower()
                        ->toString();
                    $questionData['grading_method'] = EssayGradingMethod::tryFrom($gradingMethod)?->value
                        ?? EssayGradingMethod::Ai->value;
                }

                $partsData[$partTitle]['questions'][] = $questionData;
            }

            foreach ($partsData as $title => $data) {
                $exam->parts()->create([
                    'exam_set_id' => $set->getKey(),
                    'title' => $title,
                    'instructions' => $data['instructions'],
                    'questions' => $data['questions'],
                    'sort_order' => $data['sort_order'],
                    'type' => 'section', // Default from ExamForm
                    'points' => (int) ($data['questions'][0]['points'] ?? 1), // Default part points to first question's points
                ]);
            }
        });

        return $set;
    }

    /**
     * Export every question of one set back into the same CSV shape the import
     * accepts. Without a set the whole exam (all sets) is exported into a
     * single CSV. This is the inverse of uploadFromCsv(): whatever the admin
     * edited on screen comes back out, ready to be imported again.
     */
    public function exportCsv(Exam $exam, ?ExamSet $set = null): string
    {
        $query = $exam->parts()->with('examSet');

        if ($set !== null) {
            $query->where('exam_set_id', $set->getKey());
        }

        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, $this->header());

        foreach ($query->get() as $part) {
            foreach ((array) ($part->questions ?? []) as $question) {
                if (! is_array($question)) {
                    continue;
                }

                fputcsv($handle, $this->questionToRow($part, $question));
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Package every set of an exam into a ZIP archive with one CSV per set,
     * so a multi-set exam can be backed up (and re-imported set by set) in a
     * single download.
     *
     * @return string Absolute path of the generated archive. The caller owns
     *                the temporary file and must delete it after sending.
     */
    public function exportZip(Exam $exam): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The ZIP extension is required to export multiple sets.');
        }

        $sets = $exam->sets()->orderBy('sort_order')->orderBy('id')->get();

        if ($sets->isEmpty()) {
            $sets = collect([ExamSet::ensureDefaultForExam($exam->getKey())]);
        }

        $path = tempnam(sys_get_temp_dir(), 'exam-export-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary file for the export.');
        }
        @unlink($path);
        $path .= '.zip';

        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the export archive.');
        }

        foreach ($sets as $set) {
            $zip->addFromString($this->exportFilename($set).'.csv', $this->exportCsv($exam, $set));
        }

        $zip->close();

        return $path;
    }

    /**
     * One CSV row for a stored question, formatted exactly as uploadFromCsv()
     * reads it back.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, string>
     */
    private function questionToRow(ExamPart $part, array $question): array
    {
        $type = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;
        $choices = '';
        $correct = '';

        if ($type->usesChoiceAnswer()) {
            $options = array_values((array) ($question['options'] ?? []));
            $choices = collect($options)
                ->map(fn (mixed $option): string => is_array($option)
                    ? (string) ($option['text'] ?? '')
                    : (string) $option)
                ->filter(fn (string $text): bool => $text !== '')
                ->implode('|');

            $correctOption = collect($options)
                ->first(fn (mixed $option): bool => is_array($option) && (bool) ($option['is_correct'] ?? false));
            $correct = is_array($correctOption) ? (string) ($correctOption['text'] ?? '') : '';
        } elseif ($type === QuestionType::Enumeration) {
            $correct = collect($question['enumeration_items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): string => trim((string) ($item['answer'] ?? '')).'::'.(string) ($item['points'] ?? 1))
                ->implode('|');
        } elseif ($type === QuestionType::Matching) {
            $correct = collect($question['matching_items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): string => trim((string) ($item['prompt'] ?? '')).'=>'.trim((string) ($item['answer'] ?? '')).'::'.(string) ($item['points'] ?? 1))
                ->implode('|');
        } elseif ($type === QuestionType::Identification) {
            $answers = collect([(string) ($question['correct_answer'] ?? '')])
                ->merge(collect($question['accepted_answers'] ?? [])
                    ->map(fn (mixed $answer): string => is_array($answer)
                        ? (string) ($answer['answer'] ?? '')
                        : (string) $answer))
                ->map(fn (string $answer): string => trim($answer))
                ->filter()
                ->values()
                ->all();
            $correct = implode('|', $answers);
        }

        $points = match (true) {
            $type === QuestionType::Enumeration => collect($question['enumeration_items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->sum(fn (array $item): float => (float) ($item['points'] ?? 0)),
            $type === QuestionType::Matching => collect($question['matching_items'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->sum(fn (array $item): float => (float) ($item['points'] ?? 0)),
            default => (int) ($question['points'] ?? $part->points ?? 1),
        };

        $grading = $type === QuestionType::Essay
            ? (string) (($question['grading_method'] ?? '') ?: EssayGradingMethod::Ai->value)
            : '';

        return [
            (string) ($part->title ?? ''),
            (string) ($part->instructions ?? ''),
            (string) ($question['text'] ?? ''),
            $type->value,
            $choices,
            $correct,
            (string) $points,
            $grading,
        ];
    }

    /**
     * A filesystem-safe file name for one set's CSV inside the export archive.
     */
    private function exportFilename(ExamSet $set): string
    {
        return Str::slug($set->title) ?: 'set-'.$set->getKey();
    }

    /**
     * Ensure string is valid UTF-8, converting from other encodings if necessary.
     */
    private function ensureUtf8(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        // Check if it's already valid UTF-8
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }

        // Try to convert from Windows-1252 (very common in Excel CSVs) to UTF-8
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
    }
}
