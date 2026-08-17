<?php

namespace App\Services;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class AIService
{
    protected ?string $provider;

    protected ?string $ollamaUrl;

    protected ?string $ollamaModel;

    protected ?string $cloudflareAccountId;

    protected ?string $cloudflareApiToken;

    protected ?string $cloudflareModel;

    protected ?string $groqApiKey;

    protected ?string $groqModel;

    protected ?string $geminiApiKey;

    protected ?string $geminiModel;

    protected bool $ollamaEnabled;

    /**
     * Phase 2.3 — Only read the active provider's settings on construction.
     *
     * Previously every constructor call issued ~10 Setting::get() queries for
     * all four providers. With 2.1 (cache) that's cheap, but reading 6 settings
     * you don't need is wasteful. Non-active providers are left as null; the
     * provider methods already handle that with descriptive exception messages.
     */
    public function __construct()
    {
        $this->provider = Setting::get('ai_provider', 'gemini');

        // Ollama is always read — it's used as a fallback when the primary
        // provider fails.
        $this->ollamaUrl = Setting::get('ollama_url', 'http://localhost:11434');
        $this->ollamaModel = Setting::get('ollama_model', 'llama3.2:1b');
        $this->ollamaEnabled = Setting::get('ollama_enabled', false) === '1';

        $this->configureProvider((string) $this->provider);
    }

    private function configureProvider(string $provider): void
    {
        $this->provider = $provider;

        match ($provider) {
            'cloudflare' => $this->loadCloudflareSettings(),
            'groq' => $this->loadGroqSettings(),
            'gemini' => $this->loadGeminiSettings(),
            default => null,
        };
    }

    private function loadCloudflareSettings(): void
    {
        $this->cloudflareAccountId = Setting::get('cloudflare_account_id');
        $this->cloudflareApiToken = Setting::get('cloudflare_api_token');
        // Grading has its own model setting so the chat widget can use a
        // different (e.g. cheaper/faster) model. Backfill from the legacy
        // cloudflare_model setting until an admin picks a grading model.
        $this->cloudflareModel = Setting::get('cloudflare_grading_model') ?? Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');
    }

    private function loadGroqSettings(): void
    {
        $this->groqApiKey = Setting::get('groq_api_key');
        $this->groqModel = Setting::get('groq_model', 'llama-3.1-8b-instant');
    }

    private function loadGeminiSettings(): void
    {
        // Prefer the key pasted in Platform Settings, falling back to
        // config/ai.php (env GEMINI_API_KEY). Phase 2.2 — never call env()
        // outside a config file: it returns null once config:cache runs.
        $gemini = app(GeminiAIService::class);
        $this->geminiApiKey = $gemini->apiKey();
        $this->geminiModel = $gemini->gradingModel();
    }

    /**
     * Pre-warm the AI model — only relevant for Ollama (local models).
     * Cloud providers don't need pre-warming.
     */
    public function preWarm(): bool
    {
        if (! $this->ollamaEnabled) {
            // No local Ollama model to pre-warm; cloud providers don't need it
            return true;
        }

        try {
            Http::timeout(60)->post("{$this->ollamaUrl}/api/generate", [
                'model' => $this->ollamaModel,
                'prompt' => '',
                'stream' => false,
                'keep_alive' => -1,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('AI Pre-warm failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Assess multiple essays in parallel (score only or score + feedback).
     *
     * @param  array<int, array{essayText: string, questionText: string, maxPoints: int, feedbackOnly?: bool, includeFeedback?: bool}>  $essays
     * @return array<int, array{score: float, feedback?: string}>
     */
    public function batchAssessEssays(array $essays): array
    {
        if (empty($essays)) {
            return [];
        }

        if (AiSdkProviderService::isRemovedCompatibleProvider($this->provider)) {
            throw new \RuntimeException('The selected OpenAI-compatible provider was removed. Choose another provider in Platform Settings.');
        }

        $primary = (string) $this->provider;

        try {
            return $this->attemptAssessment($primary, $essays);
        } catch (\Throwable $primaryFailure) {
            Log::warning("AI provider '{$primary}' failed for essay grading: ".$primaryFailure->getMessage());
        }

        $fallbackPolicy = app(AiProviderFallbackPolicy::class);
        $fallback = $fallbackPolicy->fallbackFor($primary, $primaryFailure);

        if ($fallback) {
            app(AiBudgetManager::class)->recordFallback(
                'grading',
                $primary,
                $fallback,
                $fallbackPolicy->reason($primaryFailure),
            );
            Log::info("Falling back to {$fallback} for essay grading");

            try {
                return $this->attemptAssessment($fallback, $essays);
            } catch (\Throwable $fallbackFailure) {
                Log::error("AI fallback [{$fallback}] also failed: ".$fallbackFailure->getMessage());
            }
        }

        if ($primaryFailure instanceof AiBudgetExceededException) {
            throw $primaryFailure;
        }
        if (($fallbackFailure ?? null) instanceof AiBudgetExceededException) {
            throw $fallbackFailure;
        }

        // Preserve the historical grading failure behavior for provider
        // outages, but never convert a hard budget stop into a zero grade.
        return $this->zeroScores($essays);
    }

    /**
     * Assess an essay and return a score (and feedback if requested).
     *
     * @param  string  $essayText  The student's essay answer.
     * @param  string  $questionText  The essay prompt/question.
     * @param  int  $maxPoints  The maximum points possible for this question.
     * @param  bool  $includeFeedback  Whether to include feedback in the response.
     * @return array{score: float, feedback?: string}
     */
    public function assessEssay(string $essayText, string $questionText, int $maxPoints, bool $includeFeedback = false): array
    {
        return $this->batchAssessEssays([
            [
                'essayText' => $essayText,
                'questionText' => $questionText,
                'maxPoints' => $maxPoints,
                'includeFeedback' => $includeFeedback,
            ],
        ])[0] ?? ['score' => 0.0];
    }

    private function attemptAssessment(string $provider, array $essays): array
    {
        $this->configureProvider($provider);
        $model = $this->modelForProvider($provider);
        $inputTokens = $this->gradingInputTokens($essays);
        $usage = app(AiUsageTracker::class);
        $reservation = $usage->start(
            $provider,
            $model,
            'grading',
            $inputTokens,
            max(1000, count($essays) * 1000),
        );

        try {
            $results = match (true) {
                $provider === 'cloudflare' => $this->batchAssessWithCloudflare($essays),
                $provider === 'groq' => $this->batchAssessWithGroq($essays),
                $provider === 'ollama' => $this->batchAssessWithOllama($essays),
                AiSdkProviderService::isSdkRouted($provider) => $this->batchAssessWithSdk($essays),
                default => $this->batchAssessWithGemini($essays),
            };

            $usage->complete(
                $reservation,
                $provider,
                $model,
                'grading',
                $inputTokens,
                $this->gradingOutputTokens($results),
            );

            return $results;
        } catch (\Throwable $exception) {
            $usage->cancel($reservation, $exception->getMessage());

            throw $exception;
        }
    }

    private function modelForProvider(string $provider): ?string
    {
        return match ($provider) {
            'cloudflare' => $this->cloudflareModel,
            'groq' => $this->groqModel,
            'gemini' => $this->geminiModel,
            'ollama' => $this->ollamaModel,
            default => AiSdkProviderService::isSdkRouted($provider)
                ? AiSdkProviderService::for($provider)->model()
                : null,
        };
    }

    private function gradingInputTokens(array $essays): int
    {
        $characters = count($essays) * 1500;
        foreach ($essays as $essay) {
            $characters += strlen((string) ($essay['essayText'] ?? ''))
                + strlen((string) ($essay['questionText'] ?? ''));
        }

        return AiUsageTracker::tokensFromChars($characters);
    }

    private function gradingOutputTokens(array $results): int
    {
        $characters = count($results) * 24;
        foreach ($results as $result) {
            $characters += strlen((string) ($result['feedback'] ?? ''));
        }

        return AiUsageTracker::tokensFromChars($characters);
    }

    // ──────────────────────────────────────────────
    //   Ollama (local) grading
    // ──────────────────────────────────────────────

    private function batchAssessWithOllama(array $essays): array
    {
        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $pool->as((string) $index)->timeout(45)->post("{$this->ollamaUrl}/api/generate", [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'keep_alive' => -1,
                    'options' => [
                        'temperature' => 0,
                        'num_predict' => 1000,
                        'num_ctx' => 2048,
                        'top_k' => 5,
                        'top_p' => 0.1,
                    ],
                ]);
            }
        });

        return $this->parseOllamaResponses($essays, $responses);
    }

    private function parseOllamaResponses(array $essays, array $responses): array
    {
        $results = [];
        $successfulResponses = 0;
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response instanceof Response && $response->successful()) {
                $successfulResponses++;
                $rawPayload = $response->json('response') ?? $response->json();
                if ($rawPayload !== null) {
                    $parsed = $this->extractJsonFromResponse($rawPayload);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                $errorMsg = $response instanceof Response ? $response->body() : get_class($response);
                Log::error("AI Ollama assessment failed for index $index: $errorMsg");
            } else {
                Log::error("AI Ollama assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        if ($successfulResponses === 0 && $essays !== []) {
            throw new \RuntimeException('Ollama returned no successful grading responses.');
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Cloudflare Workers AI grading
    // ──────────────────────────────────────────────

    private function batchAssessWithCloudflare(array $essays): array
    {
        if (! $this->cloudflareAccountId || ! $this->cloudflareApiToken) {
            throw new \Exception('Cloudflare Workers AI is not configured. Please set your Account ID and API Token in Platform Settings.');
        }

        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $messages = [
                    ['role' => 'system', 'content' => 'You are a strict academic examiner. Always respond with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ];

                $pool->as((string) $index)
                    ->withToken($this->cloudflareApiToken)
                    ->timeout(45)
                    ->post("https://api.cloudflare.com/client/v4/accounts/{$this->cloudflareAccountId}/ai/run/{$this->cloudflareModel}", [
                        'messages' => $messages,
                    ]);
            }
        });

        return $this->parseCloudflareResponses($essays, $responses);
    }

    private function parseCloudflareResponses(array $essays, array $responses): array
    {
        $results = [];
        $successfulResponses = 0;
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response instanceof Response && $response->successful()) {
                $successfulResponses++;
                $data = $response->json();
                $rawPayload = $data['result']['response'] ?? $data['result'] ?? $data['response'] ?? null;
                if ($rawPayload !== null) {
                    $parsed = $this->extractJsonFromResponse($rawPayload);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                $errorMsg = $response instanceof Response ? $response->body() : get_class($response);
                Log::error("AI Cloudflare assessment failed for index $index: $errorMsg");
            } else {
                Log::error("AI Cloudflare assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        if ($successfulResponses === 0 && $essays !== []) {
            throw new \RuntimeException('Cloudflare returned no successful grading responses.');
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Groq grading
    // ──────────────────────────────────────────────

    private function batchAssessWithGroq(array $essays): array
    {
        if (! $this->groqApiKey) {
            throw new \Exception('Groq is not configured. Please set your API Key in Platform Settings.');
        }

        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $messages = [
                    ['role' => 'system', 'content' => 'You are a strict academic examiner. Always respond with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ];

                $pool->as((string) $index)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->groqApiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(45)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => $this->groqModel,
                        'messages' => $messages,
                        'temperature' => 0,
                        'max_tokens' => 1000,
                        'response_format' => ['type' => 'json_object'],
                    ]);
            }
        });

        return $this->parseGroqResponses($essays, $responses);
    }

    private function parseGroqResponses(array $essays, array $responses): array
    {
        $results = [];
        $successfulResponses = 0;
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response instanceof Response && $response->successful()) {
                $successfulResponses++;
                $data = $response->json();
                $rawPayload = $data['choices'][0]['message']['content'] ?? null;
                if ($rawPayload !== null) {
                    $parsed = $this->extractJsonFromResponse($rawPayload);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                $errorMsg = $response instanceof Response ? $response->body() : get_class($response);
                Log::error("AI Groq assessment failed for index $index: $errorMsg");
            } else {
                Log::error("AI Groq assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        if ($successfulResponses === 0 && $essays !== []) {
            throw new \RuntimeException('Groq returned no successful grading responses.');
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Laravel AI SDK grading (OpenAI, Anthropic, Mistral, DeepSeek, xAI,
    //   OpenRouter, Azure, Ollama)
    // ──────────────────────────────────────────────

    /**
     * Grade essays through any text-capable Laravel AI SDK provider whose
     * credentials live in Platform Settings. Calls are sequential — the SDK
     * has no HTTP pool equivalent.
     */
    private function batchAssessWithSdk(array $essays): array
    {
        $sdkProvider = AiSdkProviderService::for((string) $this->provider);

        if (! $sdkProvider->isConfigured()) {
            throw new \Exception("AI provider [{$this->provider}] is not configured. Paste your API key in Platform Settings.");
        }

        $sdkProvider->applyToSdk();

        $results = [];
        $successfulResponses = 0;
        foreach ($essays as $index => $essay) {
            $result = ['score' => 0.0];

            try {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $response = agent(instructions: 'You are a strict academic examiner. Always respond with valid JSON only.')
                    ->prompt($prompt, provider: $this->provider, model: $sdkProvider->model());
                $successfulResponses++;

                $parsed = $this->extractJsonFromResponse((string) $response->text);
                $result = $this->buildResultFromData($parsed ?: [], $essay);
            } catch (\Throwable $e) {
                Log::error("AI {$this->provider} assessment failed for index $index: ".$e->getMessage());
            }

            $results[$index] = $result;
        }

        if ($successfulResponses === 0 && $essays !== []) {
            throw new \RuntimeException("AI provider [{$this->provider}] returned no successful grading responses.");
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Gemini (Google) grading
    // ──────────────────────────────────────────────

    private function batchAssessWithGemini(array $essays): array
    {
        if (! $this->geminiApiKey) {
            // If no Gemini API key is configured, fall through to Ollama
            throw new \Exception('Gemini API key is not configured. Paste your key in Platform Settings.');
        }

        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $pool->as((string) $index)
                    ->timeout(45)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->geminiModel}:generateContent?key={$this->geminiApiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0,
                            'maxOutputTokens' => 1000,
                            'responseMimeType' => 'application/json',
                            'topK' => 5,
                            'topP' => 0.1,
                        ],
                    ]);
            }
        });

        return $this->parseGeminiResponses($essays, $responses);
    }

    private function parseGeminiResponses(array $essays, array $responses): array
    {
        $results = [];
        $successfulResponses = 0;
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response instanceof Response && $response->successful()) {
                $successfulResponses++;
                $data = $response->json();
                $rawPayload = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($rawPayload !== null) {
                    $parsed = $this->extractJsonFromResponse($rawPayload);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                $errorMsg = $response instanceof Response ? $response->body() : get_class($response);
                Log::error("AI Gemini assessment failed for index $index: $errorMsg");
            } else {
                Log::error("AI Gemini assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        if ($successfulResponses === 0 && $essays !== []) {
            throw new \RuntimeException('Gemini returned no successful grading responses.');
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Shared helpers
    // ──────────────────────────────────────────────

    /**
     * Resilient JSON extractor that parses JSON objects from LLM outputs.
     * Accepts strings, arrays, or objects returned by different providers.
     */
    private function extractJsonFromResponse(mixed $rawInput): ?array
    {
        if ($rawInput === null) {
            return null;
        }

        if (is_array($rawInput)) {
            if (isset($rawInput['response']) && (is_string($rawInput['response']) || is_array($rawInput['response']))) {
                $nested = $this->extractJsonFromResponse($rawInput['response']);
                if (is_array($nested)) {
                    return $nested;
                }
            }

            return $rawInput;
        }

        if (! is_string($rawInput)) {
            return null;
        }

        $text = trim($rawInput);
        if ($text === '') {
            return null;
        }

        // 1. Direct decode attempt
        $data = json_decode($text, true);
        if (is_array($data)) {
            return $data;
        }

        // 2. Strip markdown code fences (```json ... ```)
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $stripped = preg_replace('/\s*```$/', '', $stripped);
        $data = json_decode(trim($stripped), true);
        if (is_array($data)) {
            return $data;
        }

        // 3. Extract the outermost JSON object { ... }
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $jsonCandidate = $matches[0];
            $data = json_decode($jsonCandidate, true);
            if (is_array($data)) {
                return $data;
            }

            // 4. Strip trailing commas before closing braces/brackets
            $cleaned = preg_replace('/,\s*([\}\]])/', '$1', $jsonCandidate);
            $data = json_decode($cleaned, true);
            if (is_array($data)) {
                return $data;
            }
        }

        // 5. Regex fallback for score and feedback if JSON syntax was invalid
        if (preg_match('/["\']?score["\']?\s*:\s*["\']?(\d+(?:\.\d+)?)%?["\']?/i', $text, $scoreMatch)) {
            $feedback = null;
            if (preg_match('/["\']?feedback["\']?\s*:\s*"([^"\\]*(?:\\.[^"\\]*)*)"/i', $text, $fbMatch)) {
                $feedback = stripcslashes($fbMatch[1]);
            } elseif (preg_match('/["\']?feedback["\']?\s*:\s*\'([^\'\\]*(?:\\.[^\'\\]*)*)\'/i', $text, $fbMatch)) {
                $feedback = stripcslashes($fbMatch[1]);
            }

            return [
                'score' => (float) $scoreMatch[1],
                'feedback' => $feedback,
            ];
        }

        return null;
    }

    /**
     * Build a result array from parsed JSON data and the essay config.
     */
    private function buildResultFromData(?array $data, array $essay): array
    {
        $result = ['score' => 0.0];
        $maxPoints = (int) ($essay['maxPoints'] ?? 1);
        $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);

        if ($data && isset($data['score'])) {
            $rawScore = $data['score'];
            $percentage = null;

            if (is_string($rawScore)) {
                if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', trim($rawScore), $fraction)) {
                    $num = (float) $fraction[1];
                    $denom = (float) $fraction[2];
                    $percentage = $denom > 0 ? ($num / $denom) * 100 : 0.0;
                } else {
                    $percentage = (float) rtrim(trim($rawScore), '%');
                }
            } else {
                $percentage = (float) $rawScore;
            }

            $scaledScore = ($percentage / 100) * $maxPoints;
            $scaledScore = max(0.0, min((float) $maxPoints, $scaledScore));

            $rounded = round($scaledScore, 2);
            $result['score'] = ($rounded == (int) $rounded) ? (int) $rounded : $rounded;
        }

        if ($includeFeedback && isset($data['feedback']) && trim((string) $data['feedback']) !== '') {
            $result['feedback'] = trim((string) $data['feedback']);
        }

        return $result;
    }

    /**
     * Generate zero-score results for all essays (used on total failure).
     */
    private function zeroScores(array $essays): array
    {
        $results = [];
        foreach ($essays as $index => $essay) {
            $results[$index] = ['score' => 0];
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Prompt builders (unchanged from original)
    // ──────────────────────────────────────────────

    /**
     * Build the prompt for essay assessment.
     */
    protected function buildPrompt(string $essayText, string $questionText, bool $includeFeedback = false): string
    {
        $prompt = <<<PROMPT
Act as a STRICT academic examiner. Your task is to evaluate a student's essay response based on a specific question.

Question: "$questionText"
Student Essay: "$essayText"

STRICT GRADING RULES:
1. COMPREHENSIVENESS: The answer MUST be comprehensive and thorough. Short, vague, or superficial answers should receive significantly fewer points.
2. RELEVANCE: The answer MUST be directly related to the question. Irrelevant content, even if well-written, must not be rewarded.
3. FACTUAL ACCURACY: Points should only be awarded for correct facts and logical reasoning.
4. "I DON'T KNOW" CLAUSE: If the student says "I don't know", "skip", or anything similar, the score MUST be 0.
5. MINIMUM SUBSTANCE: If the essay is too short to provide meaningful information (e.g., less than 2-3 sentences of actual content), it should receive a very low score or 0.

SCORING CRITERIA (0-100 SCALE):
- 100: Comprehensive, highly relevant, and accurate answer that covers all aspects of the question.
- 70-90: Relevant and mostly accurate but lacks some depth or misses minor aspects.
- 40-60: Relevant but superficial, or has minor factual inaccuracies.
- 10-30: Barely relevant, very short, or has significant inaccuracies.
- 0: Irrelevant, nonsensical, or explicitly states they don't know.

Response Format:
You MUST respond with a valid JSON object ONLY.
The score MUST be a WHOLE NUMBER between 0 and 100.
PROMPT;

        if ($includeFeedback) {
            $prompt .= <<<'PROMPT'

The feedback MUST start with "Your answer" or "Your essay".
The feedback MUST be at most 1 short sentence (max 18 words), actionable, and mention the biggest missing point.

{
    "score": <integer_value_between_0_and_100>,
    "feedback": "<short_actionable_feedback>"
}
PROMPT;
        } else {
            $prompt .= <<<'PROMPT'

{
    "score": <integer_value_between_0_and_100>
}
PROMPT;
        }

        return $prompt;
    }

    /**
     * Build a compact prompt for feedback-only generation (still returns score).
     */
    protected function buildFeedbackOnlyPrompt(string $essayText, string $questionText, bool $includeFeedback = true): string
    {
        $prompt = <<<PROMPT
Act as a strict academic examiner.
Question: "$questionText"
Student Essay: "$essayText"

Return ONLY valid JSON.
Score is between 0 and 100.
PROMPT;

        if ($includeFeedback) {
            $prompt .= <<<'PROMPT'

Feedback MUST start with "Your answer" or "Your essay".
One concise actionable feedback sentence.
{
    "score": <integer_value_between_0_and_100>,
    "feedback": "<one concise actionable sentence>"
}
PROMPT;
        } else {
            $prompt .= <<<'PROMPT'

{
    "score": <integer_value_between_0_and_100>
}
PROMPT;
        }

        return $prompt;
    }
}
