<?php

namespace App\Services;

use App\Enums\EssayGradingMethod;
use App\Enums\QuestionType;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class ExamTemplateService
{
    public function getTemplateCsv(): string
    {
        $handle = fopen('php://memory', 'r+');

        // CSV Header
        fputcsv($handle, [
            'Part Title',
            'Part Instructions',
            'Question Text',
            'Type',
            'Choices (Pipe | Separated)',
            'Correct Choice/Answer',
            'Points',
            'Essay Grading (ai|manual)',
        ]);

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
            'Part V - Essay',
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

    public function uploadFromCsv(Exam $exam, string $csvPath): void
    {
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

        DB::transaction(function () use ($exam, $rows) {
            // Delete existing parts for a clean slate
            $exam->parts()->delete();

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
                    'title' => $title,
                    'instructions' => $data['instructions'],
                    'questions' => $data['questions'],
                    'sort_order' => $data['sort_order'],
                    'type' => 'section', // Default from ExamForm
                    'points' => (int) ($data['questions'][0]['points'] ?? 1), // Default part points to first question's points
                ]);
            }
        });
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
