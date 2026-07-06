<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
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
     * Pre-warm the AI model by loading it into RAM and keeping it alive.
     */
    public function preWarm(): bool
    {
        try {
            // Increase timeout for pre-warm to ensure model is fully loaded
            // We use keep_alive: -1 to keep the model in memory indefinitely
            Http::timeout(60)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
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
     * Assess an essay and return a score and feedback.
     *
     * @param  string  $essayText  The student's essay answer.
     * @param  string  $questionText  The essay prompt/question.
     * @param  int  $maxPoints  The maximum points possible for this question.
     * @return array{score: float, feedback: string}
     */
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

        // Use Ollama for essay grading (current implementation)
        // TODO: Add support for Cloudflare/Groq for essay grading
        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
                $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);
                $prompt = $feedbackOnly
                    ? $this->buildFeedbackOnlyPrompt($essay['essayText'], $essay['questionText'], $includeFeedback)
                    : $this->buildPrompt($essay['essayText'], $essay['questionText'], $includeFeedback);

                $numPredict = ($feedbackOnly || $includeFeedback) ? 200 : 35;
                $pool->as((string) $index)->timeout(45)->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
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

        $results = [];
        foreach ($essays as $index => $essay) {
            $response = $responses[(string) $index] ?? null;
            $result = ['score' => 0.0];
            $maxPoints = (int) ($essay['maxPoints'] ?? 1);
            $feedbackOnly = (bool) ($essay['feedbackOnly'] ?? false);
            $includeFeedback = (bool) ($essay['includeFeedback'] ?? false);

            if ($response && $response->successful()) {
                $data = json_decode($response->json('response'), true);
                if (isset($data['score'])) {
                    // AI provides a score from 0-100, we scale it to maxPoints
                    $percentage = (float) $data['score'];
                    $scaledScore = ($percentage / 100) * $maxPoints;
                    $result['score'] = (float) round($scaledScore, 2);
                }
                if ($includeFeedback && isset($data['feedback'])) {
                    $result['feedback'] = (string) $data['feedback'];
                }
            } elseif ($response) {
                Log::error("AI Batch Assessment failed for index $index: ".$response->body());
            } else {
                Log::error("AI Batch Assessment missing response for index $index");
            }

            $results[$index] = $result;
        }

        return $results;
    }

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
        $prompt = $this->buildPrompt($essayText, $questionText, $includeFeedback);

        try {
            $numPredict = $includeFeedback ? 200 : 60;
            $response = Http::timeout(90)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
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

            if ($response->successful()) {
                $data = json_decode($response->json('response'), true);
                $result = ['score' => 0.0];

                if (isset($data['score'])) {
                    $percentage = (float) $data['score'];
                    $scaledScore = ($percentage / 100) * $maxPoints;
                    $result['score'] = (float) round($scaledScore, 2);
                }

                if ($includeFeedback && isset($data['feedback'])) {
                    $result['feedback'] = (string) $data['feedback'];
                }

                return $result;
            }

            Log::error('AI Assessment failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('AI Assessment error: '.$e->getMessage());
        }

        return ['score' => 0.0];
    }
}
