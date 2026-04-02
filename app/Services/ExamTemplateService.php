<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamPart;
use Illuminate\Support\Collection;
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
            'Points'
        ]);

        // Example Rows
        fputcsv($handle, [
            'Part I - Multiple Choice',
            'Select the best answer for each question.',
            'What is the capital of France?',
            'multiple_choice',
            'Berlin|Madrid|Paris|Rome',
            'Paris',
            '1'
        ]);
        
        fputcsv($handle, [
            'Part I - Multiple Choice',
            '',
            'Which planet is known as the Red Planet?',
            'multiple_choice',
            'Earth|Mars|Jupiter|Saturn',
            'Mars',
            '1'
        ]);

        fputcsv($handle, [
            'Part II - True or False',
            'Write True if the statement is correct, otherwise False.',
            'The sun is a star.',
            'true_false',
            'True|False',
            'True',
            '1'
        ]);

        fputcsv($handle, [
            'Part III - Identification',
            'Identify the following.',
            'Who wrote "Noli Me Tangere"?',
            'identification',
            '',
            'Jose Rizal',
            '5'
        ]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function uploadFromCsv(Exam $exam, string $csvPath): void
    {
        $rows = [];
        if (($handle = fopen($csvPath, 'r')) !== FALSE) {
            $header = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($header) === count($data)) {
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
                
                if (!isset($partsData[$partTitle])) {
                    $partsData[$partTitle] = [
                        'instructions' => $partInstructions,
                        'questions' => [],
                        'sort_order' => $sortOrder++
                    ];
                }

                $type = $row['Type'] ?? 'multiple_choice';
                $questionText = $row['Question Text'] ?? '';
                $choicesStr = $row['Choices (Pipe | Separated)'] ?? '';
                $correctInput = $row['Correct Choice/Answer'] ?? '';

                $questionData = [
                    'text' => $questionText,
                    'type' => $type,
                    'options' => [],
                    'correct_answer' => null,
                    'points' => (int) ($row['Points'] ?? 1)
                ];

                if (in_array($type, ['multiple_choice', 'true_false'])) {
                    $choices = array_filter(array_map('trim', explode('|', $choicesStr)));
                    foreach ($choices as $choiceText) {
                        $questionData['options'][] = [
                            'text' => $choiceText,
                            'is_correct' => trim($choiceText) === trim($correctInput)
                        ];
                    }
                } elseif ($type === 'identification') {
                    $questionData['correct_answer'] = $correctInput;
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
                    'points' => (int) ($data['questions'][0]['points'] ?? 1) // Default part points to first question's points
                ]);
            }
        });
    }
}
