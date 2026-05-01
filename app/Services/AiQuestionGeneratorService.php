<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class AiQuestionGeneratorService
{
    protected string $baseUrl;

    protected string $model;

    public function __construct()
    {
        $this->baseUrl = config('ai.providers.ollama.url', 'http://localhost:11434');
        $this->model = config('ai.providers.ollama.model', 'llama3.2:1b');
    }

    /**
     * Extract plain text from a PDF or DOCX/DOC file on disk.
     */
    public function extractText(string $absolutePath): string
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        try {
            if ($ext === 'pdf') {
                $parser = new PdfParser;
                $pdf = $parser->parseFile($absolutePath);

                return trim((string) $pdf->getText());
            }

            if (in_array($ext, ['docx', 'doc'], true)) {
                $reader = $ext === 'doc' ? 'MsDoc' : 'Word2007';
                $phpWord = WordIOFactory::load($absolutePath, $reader);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        $text .= self::extractWordElementText($element)."\n";
                    }
                }

                return trim($text);
            }

            if ($ext === 'txt' || $ext === 'md') {
                return trim((string) file_get_contents($absolutePath));
            }
        } catch (\Throwable $e) {
            Log::warning('AI question extract failed: '.$e->getMessage());
        }

        return '';
    }

    private static function extractWordElementText(object $element): string
    {
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                return $text;
            }
        }

        $out = '';
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $out .= self::extractWordElementText($child).' ';
            }
        }

        return $out;
    }

    /**
     * Call the local AI to generate exam questions.
     *
     * @param  array<string, int>  $typeCounts  keys: multiple_choice, true_false, identification, essay
     * @return array<int, array<string, mixed>>
     */
    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $sourceText = self::clampSource($sourceText, 12000);
        $prompt = $this->buildPrompt($sourceText, $typeCounts, $difficulty, $topic);

        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'keep_alive' => -1,
                'options' => [
                    'temperature' => 0.2,
                    'num_predict' => 2048,
                    'num_ctx' => 8192,
                    'top_p' => 0.9,
                ],
            ]);

            if (! $response->successful()) {
                Log::error('AI question gen HTTP failed: '.$response->body());

                return [];
            }

            $raw = (string) $response->json('response');
            $data = json_decode($raw, true);

            if (! is_array($data)) {
                // Try to salvage a JSON object inside the payload.
                if (preg_match('/\{.*\}/s', $raw, $m)) {
                    $data = json_decode($m[0], true);
                }
            }

            $questions = $data['questions'] ?? (is_array($data) ? $data : []);

            return is_array($questions) ? self::normalize($questions) : [];
        } catch (\Throwable $e) {
            Log::error('AI question gen exception: '.$e->getMessage());

            return [];
        }
    }

    private static function clampSource(string $text, int $maxChars): string
    {
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return substr($text, 0, $maxChars);
    }

    protected function buildPrompt(string $sourceText, array $typeCounts, string $difficulty, ?string $topic): string
    {
        $mc = (int) ($typeCounts['multiple_choice'] ?? 0);
        $tf = (int) ($typeCounts['true_false'] ?? 0);
        $id = (int) ($typeCounts['identification'] ?? 0);
        $es = (int) ($typeCounts['essay'] ?? 0);

        $topicLine = $topic ? "Topic focus: {$topic}\n" : '';

        return <<<PROMPT
You are an expert exam author. Generate exam questions strictly from the SOURCE MATERIAL below.

{$topicLine}Difficulty: {$difficulty}

Required counts:
- multiple_choice: {$mc} (each with exactly 4 options, exactly one correct)
- true_false: {$tf} (two options: "True" and "False", exactly one correct)
- identification: {$id} (short exact answer, <= 5 words)
- essay: {$es} (open-ended question, no answer key)

SOURCE MATERIAL:
\"\"\"
{$sourceText}
\"\"\"

RULES:
1. Every non-essay question MUST be answerable from the source material.
2. Do NOT invent facts. If material is insufficient, produce fewer questions.
3. Options text must be distinct and plausible.
4. Identification answers must be short and exact.
5. Essay questions must require analysis/explanation (no yes/no).

Respond ONLY with valid JSON matching this schema:
{
  "questions": [
    {
      "type": "multiple_choice" | "true_false" | "identification" | "essay",
      "text": "<question text>",
      "options": [ { "text": "<choice>", "is_correct": true|false } ],   // only for multiple_choice and true_false
      "correct_answer": "<string>"                                          // only for identification
    }
  ]
}
PROMPT;
    }

    /**
     * Normalize AI output into the structure stored in ExamPart.questions.
     *
     * @param  array<int, mixed>  $questions
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $questions): array
    {
        $out = [];
        foreach ($questions as $q) {
            if (! is_array($q)) {
                continue;
            }

            $type = (string) ($q['type'] ?? '');
            $text = trim((string) ($q['text'] ?? ''));
            if ($text === '' || ! in_array($type, ['multiple_choice', 'true_false', 'identification', 'essay'], true)) {
                continue;
            }

            $entry = [
                'text' => $text,
                'type' => $type,
                'points' => (int) ($q['points'] ?? 1),
            ];

            if ($type === 'multiple_choice' || $type === 'true_false') {
                $options = [];
                foreach ((array) ($q['options'] ?? []) as $opt) {
                    if (! is_array($opt)) {
                        continue;
                    }
                    $optText = trim((string) ($opt['text'] ?? ''));
                    if ($optText === '') {
                        continue;
                    }
                    $options[] = [
                        'text' => $optText,
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                    ];
                }

                if ($type === 'true_false' && count($options) < 2) {
                    $options = [
                        ['text' => 'True', 'is_correct' => false],
                        ['text' => 'False', 'is_correct' => false],
                    ];
                }

                // Ensure at least one correct option (default first).
                $hasCorrect = false;
                foreach ($options as $o) {
                    if (! empty($o['is_correct'])) {
                        $hasCorrect = true;
                        break;
                    }
                }
                if (! $hasCorrect && ! empty($options)) {
                    $options[0]['is_correct'] = true;
                }

                $entry['options'] = $options;
            }

            if ($type === 'identification') {
                $entry['correct_answer'] = trim((string) ($q['correct_answer'] ?? ''));
            }

            $out[] = $entry;
        }

        return $out;
    }
}
