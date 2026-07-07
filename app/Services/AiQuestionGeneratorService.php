<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class AiQuestionGeneratorService
{
    protected string $baseUrl;

    protected string $model;

    protected ?string $provider;

    public function __construct()
    {
        $this->provider = Setting::get('ai_provider', 'gemini');
        $this->baseUrl = Setting::get('ollama_url', 'http://localhost:11434');
        $this->model = Setting::get('ollama_model', 'llama3.2:1b');
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
     * Call the AI to generate exam questions using the configured provider.
     *
     * @param  array<string, int>  $typeCounts  keys: multiple_choice, true_false, identification, essay
     * @return array<int, array<string, mixed>>
     */
    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $sourceText = self::clampSource($sourceText, 12000);
        $prompt = $this->buildPrompt($sourceText, $typeCounts, $difficulty, $topic);
        $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';

        try {
            // Try primary provider first
            if ($this->provider === 'cloudflare') {
                return $this->generateWithCloudflare($prompt);
            } elseif ($this->provider === 'groq') {
                return $this->generateWithGroq($prompt);
            } else {
                // Default to Ollama
                return $this->generateWithOllama($prompt);
            }
        } catch (\Throwable $e) {
            Log::error('Primary AI provider failed for question generation: '.$e->getMessage());

            // Try Ollama fallback if enabled
            if ($ollamaEnabled && $this->provider !== 'ollama') {
                try {
                    Log::info('Attempting Ollama fallback for question generation');

                    return $this->generateWithOllama($prompt);
                } catch (\Throwable $ollamaError) {
                    Log::error('Ollama fallback also failed: '.$ollamaError->getMessage());
                }
            }

            return [];
        }
    }

    /**
     * Generate questions using Cloudflare Workers AI.
     */
    protected function generateWithCloudflare(string $prompt): array
    {
        $accountId = Setting::get('cloudflare_account_id');
        $apiToken = Setting::get('cloudflare_api_token');
        $model = Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');

        if (! $accountId || ! $apiToken) {
            throw new \Exception('Cloudflare Workers AI is not configured.');
        }

        $response = Http::withToken($apiToken)
            ->timeout(300)
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 4096, // Increase max tokens to allow longer responses
            ]);

        if (! $response->successful()) {
            throw new \Exception('Cloudflare API Error: '.$response->body());
        }

        $data = $response->json();

        // Cloudflare returns content in result.choices[0].message.content
        $responseText = $data['result']['choices'][0]['message']['content'] ?? $data['result']['response'] ?? $data['response'] ?? '';

        if (! is_string($responseText)) {
            Log::error('Cloudflare response is not a string: '.gettype($responseText));
            throw new \Exception('Cloudflare returned unexpected response format');
        }

        Log::info('Cloudflare raw response length: '.strlen($responseText));
        Log::info('Cloudflare raw response preview: '.substr($responseText, 0, 500));

        return $this->parseResponse($responseText);
    }

    /**
     * Generate questions using Groq.
     */
    protected function generateWithGroq(string $prompt): array
    {
        $apiKey = Setting::get('groq_api_key');
        $model = Setting::get('groq_model', 'llama-3.1-8b-instant');

        if (! $apiKey) {
            throw new \Exception('Groq is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(300)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 2048,
            ]);

        if (! $response->successful()) {
            throw new \Exception('Groq API Error: '.$response->body());
        }

        $data = $response->json();
        $responseText = $data['choices'][0]['message']['content'] ?? '';

        return $this->parseResponse($responseText);
    }

    /**
     * Generate questions using Ollama.
     */
    protected function generateWithOllama(string $prompt): array
    {
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
            throw new \Exception('Ollama API Error: '.$response->body());
        }

        $raw = (string) $response->json('response');

        return $this->parseResponse($raw);
    }

    /**
     * Parse the AI response and extract questions.
     */
    protected function parseResponse(string $raw): array
    {
        Log::info('Parsing response, raw length: '.strlen($raw));
        Log::info('Raw response: '.$raw);

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            Log::warning('Failed to decode JSON as array, trying to extract JSON from response');
            // Try to salvage a JSON object inside the payload.
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $data = json_decode($m[0], true);
                Log::info('Extracted JSON from response: '.json_encode($data));
            }
        }

        if (! is_array($data)) {
            Log::error('Failed to decode JSON, data is not array');

            return [];
        }

        $questions = $data['questions'] ?? (is_array($data) ? $data : []);

        Log::info('Questions count before normalization: '.count($questions));

        $normalized = self::normalize($questions);

        Log::info('Questions count after normalization: '.count($normalized));

        return $normalized;
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
    public function generateSource(string $subject, string $gradeLevel, string $description, int $length): string
    {
        $prompt = <<<PROMPT
You are an expert educational content creator. Create a comprehensive educational source material based on the following:

Subject: {$subject}
Grade Level: {$gradeLevel}
Description of what to cover: {$description}

Guidelines:
- Create a well-structured lesson with clear sections
- Use language appropriate for the specified grade level
- Include key concepts, explanations, and examples
- Make it informative and educational
- Aim for approximately {$length} words

Respond only with the source material text, no additional formatting or comments.
PROMPT;

        $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';

        try {
            // Try primary provider first
            if ($this->provider === 'cloudflare') {
                return $this->generateSourceWithCloudflare($prompt, $length);
            } elseif ($this->provider === 'groq') {
                return $this->generateSourceWithGroq($prompt, $length);
            } else {
                // Default to Ollama
                return $this->generateSourceWithOllama($prompt, $length);
            }
        } catch (\Throwable $e) {
            Log::error('Primary AI provider failed for source generation: '.$e->getMessage());

            // Try Ollama fallback if enabled
            if ($ollamaEnabled && $this->provider !== 'ollama') {
                try {
                    Log::info('Attempting Ollama fallback for source generation');

                    return $this->generateSourceWithOllama($prompt, $length);
                } catch (\Throwable $ollamaError) {
                    Log::error('Ollama fallback also failed: '.$ollamaError->getMessage());
                }
            }

            return '';
        }
    }

    /**
     * Generate source material using Cloudflare Workers AI.
     */
    protected function generateSourceWithCloudflare(string $prompt, int $length): string
    {
        $accountId = Setting::get('cloudflare_account_id');
        $apiToken = Setting::get('cloudflare_api_token');
        $model = Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');

        if (! $accountId || ! $apiToken) {
            throw new \Exception('Cloudflare Workers AI is not configured.');
        }

        $response = Http::withToken($apiToken)
            ->timeout(300)
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \Exception('Cloudflare API Error: '.$response->body());
        }

        $data = $response->json();

        return trim($data['result']['response'] ?? $data['response'] ?? '');
    }

    /**
     * Generate source material using Groq.
     */
    protected function generateSourceWithGroq(string $prompt, int $length): string
    {
        $apiKey = Setting::get('groq_api_key');
        $model = Setting::get('groq_model', 'llama-3.1-8b-instant');

        if (! $apiKey) {
            throw new \Exception('Groq is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(300)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => min($length * 3, 32768),
            ]);

        if (! $response->successful()) {
            throw new \Exception('Groq API Error: '.$response->body());
        }

        $data = $response->json();

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Generate source material using Ollama.
     */
    protected function generateSourceWithOllama(string $prompt, int $length): string
    {
        $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'keep_alive' => -1,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => min($length * 3, 32768),
                'num_ctx' => 8192,
                'top_p' => 0.9,
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('Ollama API Error: '.$response->body());
        }

        return trim((string) $response->json('response'));
    }

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
