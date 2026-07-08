<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function __construct()
    {
        $this->provider = Setting::get('ai_provider', 'gemini');

        // Ollama settings
        $this->ollamaUrl = Setting::get('ollama_url', 'http://localhost:11434');
        $this->ollamaModel = Setting::get('ollama_model', 'llama3.2:1b');
        $this->ollamaEnabled = Setting::get('ollama_enabled', false) === '1';

        // Cloudflare settings
        $this->cloudflareAccountId = Setting::get('cloudflare_account_id');
        $this->cloudflareApiToken = Setting::get('cloudflare_api_token');
        $this->cloudflareModel = Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct');

        // Groq settings
        $this->groqApiKey = Setting::get('groq_api_key');
        $this->groqModel = Setting::get('groq_model', 'llama-3.1-8b-instant');

        // Gemini settings (from .env via config/ai.php)
        $this->geminiApiKey = config('ai.providers.gemini.key') ?? env('GEMINI_API_KEY');
        $this->geminiModel = 'gemini-1.5-flash';
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

        // Dispatch to the configured AI provider
        try {
            return match ($this->provider) {
                'cloudflare' => $this->batchAssessWithCloudflare($essays),
                'groq' => $this->batchAssessWithGroq($essays),
                default => $this->batchAssessWithGemini($essays),
            };
        } catch (\Exception $e) {
            Log::warning("AI provider '{$this->provider}' failed for essay grading: ".$e->getMessage());

            if ($this->ollamaEnabled) {
                Log::info('Falling back to Ollama for essay grading');

                try {
                    return $this->batchAssessWithOllama($essays);
                } catch (\Exception $ollamaError) {
                    Log::error('Ollama fallback also failed: '.$ollamaError->getMessage());
                }
            }

            // Return zero scores for all essays on total failure
            return $this->zeroScores($essays);
        }
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

                $numPredict = ($feedbackOnly || $includeFeedback) ? 200 : 35;
                $pool->as((string) $index)->timeout(45)->post("{$this->ollamaUrl}/api/generate", [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'keep_alive' => -1,
                    'options' => [
                        'temperature' => 0,
                        'num_predict' => $numPredict,
                        'num_ctx' => 1024,
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
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response && $response->successful()) {
                $data = json_decode($response->json('response'), true);
                $result = $this->buildResultFromData($data, $essay);
            } elseif ($response) {
                Log::error("AI Ollama assessment failed for index $index: ".$response->body());
            } else {
                Log::error("AI Ollama assessment missing response for index $index");
            }

            $results[$index] = $result;
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
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response && $response->successful()) {
                $data = $response->json();
                $rawText = $data['result']['response'] ?? $data['response'] ?? null;
                if ($rawText) {
                    $parsed = json_decode($rawText, true);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                Log::error("AI Cloudflare assessment failed for index $index: ".$response->body());
            } else {
                Log::error("AI Cloudflare assessment missing response for index $index");
            }

            $results[$index] = $result;
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

                $numTokens = ($feedbackOnly || $includeFeedback) ? 200 : 60;

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
                        'max_tokens' => $numTokens,
                    ]);
            }
        });

        return $this->parseGroqResponses($essays, $responses);
    }

    private function parseGroqResponses(array $essays, array $responses): array
    {
        $results = [];
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response && $response->successful()) {
                $data = $response->json();
                $rawText = $data['choices'][0]['message']['content'] ?? null;
                if ($rawText) {
                    $parsed = json_decode($rawText, true);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                Log::error("AI Groq assessment failed for index $index: ".$response->body());
            } else {
                Log::error("AI Groq assessment missing response for index $index");
            }

            $results[$index] = $result;
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
            throw new \Exception('Gemini API key is not configured. Set GEMINI_API_KEY in your .env file.');
        }

        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $maxTokens = ($feedbackOnly || $includeFeedback) ? 200 : 60;

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
                            'maxOutputTokens' => $maxTokens,
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
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];

            if ($response && $response->successful()) {
                $data = $response->json();
                $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($rawText) {
                    // Strip markdown code fences if present
                    $rawText = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawText));
                    $parsed = json_decode($rawText, true);
                    $result = $this->buildResultFromData($parsed ?: [], $essay);
                }
            } elseif ($response) {
                Log::error("AI Gemini assessment failed for index $index: ".$response->body());
            } else {
                Log::error("AI Gemini assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        return $results;
    }

    // ──────────────────────────────────────────────
    //   Shared helpers
    // ──────────────────────────────────────────────

    /**
     * Build a result array from parsed JSON data and the essay config.
     */
    private function buildResultFromData(?array $data, array $essay): array
    {
        $result = ['score' => 0.0];
        $maxPoints = (int) ($essay['maxPoints'] ?? 1);
        $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);

        if ($data && isset($data['score'])) {
            $percentage = (float) $data['score'];
            $scaledScore = ($percentage / 100) * $maxPoints;
            $result['score'] = (int) round($scaledScore);
        }

        if ($includeFeedback && isset($data['feedback'])) {
            $result['feedback'] = (string) $data['feedback'];
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
