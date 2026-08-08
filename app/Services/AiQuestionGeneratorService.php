<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Utf8;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class AiQuestionGeneratorService
{
    /** Maximum number of source-material characters sent to the AI. */
    protected const SOURCE_PROMPT_LIMIT = 12_000;

    protected string $baseUrl;

    protected string $model;

    protected ?string $provider;

    /**
     * The raw completion text returned by the last successful provider call.
     * Exposed so callers (e.g. the queue job) can persist what the AI actually
     * said for debugging, even when the response cannot be parsed into usable
     * questions.
     */
    public ?string $lastRawResponse = null;

    public function __construct()
    {
        $this->provider = Setting::get('ai_provider', 'gemini');
        $this->baseUrl = Setting::get('ollama_url', 'http://localhost:11434');
        $this->model = Setting::get('ollama_model', 'llama3.2:1b');
    }

    /*
    |--------------------------------------------------------------------------
    | Text extraction
    |--------------------------------------------------------------------------
    */

    /**
     * Extract plain text from a PDF, Word, or plain-text file on disk.
     *
     * The result is ALWAYS valid UTF-8: PDF/Word parsers regularly emit
     * malformed byte sequences that would otherwise explode downstream in
     * json_encode() (Livewire component state, queue payloads, DB casts).
     */
    public function extractText(string $absolutePath): string
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        try {
            $text = match (true) {
                $ext === 'pdf' => $this->extractPdfText($absolutePath),
                in_array($ext, ['docx', 'doc'], true) => $this->extractWordText($absolutePath, $ext),
                in_array($ext, ['txt', 'md'], true) => (string) file_get_contents($absolutePath),
                default => '',
            };

            return self::tidyExtractedText($text);
        } catch (\Throwable $e) {
            Log::warning('AI question extract failed: '.$e->getMessage());

            return '';
        }
    }

    private function extractPdfText(string $absolutePath): string
    {
        return (string) (new PdfParser)->parseFile($absolutePath)->getText();
    }

    private function extractWordText(string $absolutePath, string $ext): string
    {
        $reader = $ext === 'doc' ? 'MsDoc' : 'Word2007';
        $phpWord = WordIOFactory::load($absolutePath, $reader);

        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= self::extractWordElementText($element)."\n";
            }
        }

        return $text;
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
     * Normalize extracted text: valid UTF-8, LF line endings, collapsed
     * blank-line runs.
     */
    private static function tidyExtractedText(string $text): string
    {
        $text = Utf8::clean($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /*
    |--------------------------------------------------------------------------
    | Generation API
    |--------------------------------------------------------------------------
    */

    /**
     * Call the AI to generate exam questions using the configured provider.
     *
     * @param  array<string, int>  $typeCounts  keys: multiple_choice, true_false, identification, essay
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException when every configured provider fails
     */
    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $sourceText = self::clampSource($sourceText, self::SOURCE_PROMPT_LIMIT);
        $prompt = $this->buildPrompt($sourceText, $typeCounts, $difficulty, $topic);

        $raw = $this->ask($prompt, jsonMode: true, maxTokens: 8192, temperature: 0.2);

        $this->lastRawResponse = $raw;

        // Enforce requested counts — slice AI output to at most the requested
        // amount per type, even when the model ignores the count instructions.
        return self::enforceCounts($this->parseResponse($raw), $typeCounts);
    }

    /**
     * Generate educational source material with the AI (alternative to
     * uploading/pasting source text).
     *
     * @throws \RuntimeException when every configured provider fails
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

        $text = $this->ask($prompt, jsonMode: false, maxTokens: min($length * 3, 32768), temperature: 0.7);

        return trim(Utf8::clean($text));
    }

    /*
    |--------------------------------------------------------------------------
    | Provider layer
    |--------------------------------------------------------------------------
    */

    /**
     * Send a prompt to the configured provider, falling back to Ollama when
     * enabled. Throws a RuntimeException describing the real failure(s) when
     * no provider can answer, so drafts surface actionable error messages
     * instead of a generic "no usable questions".
     */
    protected function ask(string $prompt, bool $jsonMode, int $maxTokens, float $temperature): string
    {
        $primary = in_array($this->provider, ['cloudflare', 'groq'], true) ? $this->provider : 'ollama';
        $failures = [];

        try {
            return $this->callProvider($primary, $prompt, $jsonMode, $maxTokens, $temperature);
        } catch (\Throwable $e) {
            $failures[] = "{$primary}: {$e->getMessage()}";
            Log::error("Primary AI provider [{$primary}] failed: {$e->getMessage()}");
        }

        if ($primary !== 'ollama' && Setting::get('ollama_enabled', false) === '1') {
            try {
                Log::info('Attempting Ollama fallback for AI request');

                return $this->callProvider('ollama', $prompt, $jsonMode, $maxTokens, $temperature);
            } catch (\Throwable $e) {
                $failures[] = "ollama: {$e->getMessage()}";
                Log::error('Ollama fallback also failed: '.$e->getMessage());
            }
        }

        throw new \RuntimeException('AI request failed ('.implode(' | ', $failures).')');
    }

    /**
     * Dispatch a prompt to a single provider and return the raw completion.
     */
    protected function callProvider(string $provider, string $prompt, bool $jsonMode, int $maxTokens, float $temperature): string
    {
        return match ($provider) {
            'cloudflare' => $this->callCloudflare($prompt, $maxTokens),
            'groq' => $this->callGroq($prompt, $jsonMode, $maxTokens, $temperature),
            default => $this->callOllama($prompt, $jsonMode, $maxTokens, $temperature),
        };
    }

    /**
     * Call Cloudflare Workers AI and return the raw text completion.
     */
    protected function callCloudflare(string $prompt, int $maxTokens): string
    {
        $accountId = Setting::get('cloudflare_account_id');
        $apiToken = Setting::get('cloudflare_api_token');
        // Question/source generation follows the grading model setting — both
        // are batch, quality-sensitive AI work (the chat widget has its own).
        // Backfill from the legacy cloudflare_model setting until a grading
        // model is picked explicitly.
        $model = Setting::get('cloudflare_grading_model') ?? Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');

        if (! $accountId || ! $apiToken) {
            throw new \RuntimeException('Cloudflare Workers AI is not configured.');
        }

        $response = Http::withToken($apiToken)
            ->timeout(300)
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => min($maxTokens, 8192),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Cloudflare API error: '.$response->body());
        }

        $data = $response->json();

        // Chat-style models return result.choices[0].message.content; older
        // text-generation models return result.response / response.
        $text = $data['result']['choices'][0]['message']['content']
            ?? $data['result']['response']
            ?? $data['response']
            ?? '';

        if (! is_string($text)) {
            throw new \RuntimeException('Cloudflare returned an unexpected response format.');
        }

        app(AiUsageTracker::class)->record(
            'cloudflare',
            $model,
            'generation',
            AiUsageTracker::tokensFromChars(strlen($prompt)),
            AiUsageTracker::tokensFromChars(strlen($text)),
        );

        return $text;
    }

    /**
     * Call Groq (OpenAI-compatible chat completions) and return the raw text.
     */
    protected function callGroq(string $prompt, bool $jsonMode, int $maxTokens, float $temperature): string
    {
        $apiKey = Setting::get('groq_api_key');
        $model = Setting::get('groq_model', 'llama-3.1-8b-instant');

        if (! $apiKey) {
            throw new \RuntimeException('Groq is not configured.');
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            // JSON mode keeps the model from wrapping output in prose/fences.
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(300)
            ->post('https://api.groq.com/openai/v1/chat/completions', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Groq API error: '.$response->body());
        }

        return (string) ($response->json('choices.0.message.content') ?? '');
    }

    /**
     * Call a local Ollama instance and return the raw text completion.
     */
    protected function callOllama(string $prompt, bool $jsonMode, int $maxTokens, float $temperature): string
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'keep_alive' => -1,
            'options' => [
                'temperature' => $temperature,
                'num_predict' => $maxTokens,
                'num_ctx' => 8192,
                'top_p' => 0.9,
            ],
        ];

        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Ollama API error: '.$response->body());
        }

        return (string) $response->json('response');
    }

    /*
    |--------------------------------------------------------------------------
    | Response parsing
    |--------------------------------------------------------------------------
    */

    /**
     * Parse the raw AI response and extract normalized questions.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseResponse(string $raw): array
    {
        $data = $this->decodeLenient(Utf8::clean($raw));

        if (! is_array($data)) {
            Log::error('AI question generation: unable to decode provider response as JSON');

            return [];
        }

        $questions = $data['questions'] ?? $data;

        return self::normalize(is_array($questions) ? $questions : []);
    }

    /**
     * Attempt to decode JSON with lenient handling for fenced or truncated
     * responses. Tries: strip code fences → exact decode → first complete
     * object → first complete array → closing-brace repair.
     *
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeLenient(string $raw): ?array
    {
        // 0. Unwrap markdown code fences (```json ... ```) models love to add.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }

        $raw = trim($raw);

        // 1. Try exact decode
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }

        Log::warning('Failed exact JSON decode, attempting lenient extraction');

        // 2. Try to find a complete JSON object ({...})
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        // 3. Try to find a complete JSON array ([...])
        if (preg_match('/\[.*\]/s', $raw, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        // 4. Try to fix truncated JSON — find the outermost {...} and repair it
        if (preg_match('/^.*?(\{.*)$/s', $raw, $m)) {
            $fragment = $m[1];
            // Try adding closing braces step by step
            for ($i = 0; $i <= 10; $i++) {
                $candidate = $fragment.str_repeat('}', $i);
                $data = json_decode($candidate, true);
                if (is_array($data)) {
                    Log::warning('Repaired truncated JSON by adding '.$i.' closing braces');

                    return $data;
                }
            }
        }

        return null;
    }

    /**
     * Clamp source text to a character budget without splitting a multibyte
     * character mid-sequence (plain substr() can produce invalid UTF-8).
     */
    private static function clampSource(string $text, int $maxChars): string
    {
        $text = Utf8::clean($text);

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars);
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
6. Respond with raw JSON only — no markdown code fences, no commentary.

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

    /*
    |--------------------------------------------------------------------------
    | Normalization
    |--------------------------------------------------------------------------
    */

    /**
     * Enforce requested question counts — slice AI output to at most the
     * requested amount per type. This ensures the user's count settings are
     * always respected even when the AI model doesn't follow count
     * instructions precisely.
     *
     * @param  array<int, array<string, mixed>>  $questions  AI-generated questions
     * @param  array<string, int>  $typeCounts  requested counts per type
     * @return array<int, array<string, mixed>>
     */
    public static function enforceCounts(array $questions, array $typeCounts): array
    {
        // Group questions by type, preserving order within each type
        $grouped = [
            'multiple_choice' => [],
            'true_false' => [],
            'identification' => [],
            'essay' => [],
        ];
        foreach ($questions as $q) {
            $type = $q['type'] ?? '';
            if (isset($grouped[$type])) {
                $grouped[$type][] = $q;
            }
        }

        // Slice each type to the requested count and merge back in original order
        $result = [];
        foreach (['multiple_choice', 'true_false', 'identification', 'essay'] as $type) {
            $requested = (int) ($typeCounts[$type] ?? 0);
            $available = $grouped[$type];
            if ($requested > 0) {
                // Keep up to the requested count (trim excess)
                $result = array_merge($result, array_slice($available, 0, $requested));
            }
            // If $requested === 0, questions of this type are dropped entirely
        }

        return $result;
    }

    /**
     * Normalize AI output into the structure stored in AiQuestionDraft.questions
     * (and later copied into ExamPart.questions). Every string is guaranteed
     * to be valid UTF-8.
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
            $text = trim(Utf8::clean((string) ($q['text'] ?? '')));

            if ($text === '' || ! in_array($type, ['multiple_choice', 'true_false', 'identification', 'essay'], true)) {
                continue;
            }

            $entry = [
                'text' => $text,
                'type' => $type,
                'points' => max(1, (int) ($q['points'] ?? 1)),
            ];

            if ($type === 'multiple_choice' || $type === 'true_false') {
                $options = self::normalizeOptions($q['options'] ?? [], $type);

                // A choice question without usable choices is unanswerable.
                if ($options === []) {
                    continue;
                }

                $entry['options'] = $options;
            }

            if ($type === 'identification') {
                $entry['correct_answer'] = trim(Utf8::clean((string) ($q['correct_answer'] ?? '')));
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Normalize the option list of a choice-type question.
     *
     * Guarantees: non-empty UTF-8-safe texts, at least one correct option,
     * and exactly one correct option for true/false questions.
     *
     * @return array<int, array{text: string, is_correct: bool}>
     */
    private static function normalizeOptions(mixed $rawOptions, string $type): array
    {
        $options = [];

        foreach ((array) $rawOptions as $opt) {
            if (! is_array($opt)) {
                continue;
            }

            $optText = trim(Utf8::clean((string) ($opt['text'] ?? '')));

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

        if ($options === []) {
            return [];
        }

        $correctIndexes = array_keys(array_filter(
            $options,
            fn (array $option): bool => $option['is_correct']
        ));

        if ($correctIndexes === []) {
            // Ensure at least one correct option (default first).
            $options[0]['is_correct'] = true;
        } elseif ($type === 'true_false' && count($correctIndexes) > 1) {
            // True/false can only have one correct answer — keep the first.
            foreach (array_slice($correctIndexes, 1) as $index) {
                $options[$index]['is_correct'] = false;
            }
        }

        return $options;
    }
}
