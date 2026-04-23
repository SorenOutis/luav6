<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $baseUrl;

    protected string $model;

    public function __construct()
    {
        $this->baseUrl = config('ai.providers.ollama.url', 'http://localhost:11434');
        $this->model = config('ai.providers.ollama.model', 'llama3.2:1b');
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
     * Assess multiple essays in parallel.
     *
     * @param  array<int, array{essayText: string, questionText: string, maxPoints: int}>  $essays
     * @return array<int, array{score: float, feedback: string}>
     */
    public function batchAssessEssays(array $essays): array
    {
        if (empty($essays)) {
            return [];
        }

        $responses = Http::pool(function ($pool) use ($essays) {
            foreach ($essays as $index => $essay) {
                $prompt = $this->buildPrompt($essay['essayText'], $essay['questionText']);
                $pool->as((string) $index)->timeout(300)->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'keep_alive' => -1,
                    'options' => [
                        'temperature' => 0,
                        'num_predict' => 50, // Increased to ensure JSON is not cut off
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
            $result = ['score' => 0.0, 'feedback' => ''];
            $maxPoints = (int) ($essay['maxPoints'] ?? 1);

            if ($response && $response->successful()) {
                $data = json_decode($response->json('response'), true);
                if (isset($data['score'])) {
                    // AI provides a score from 0-100, we scale it to maxPoints
                    $percentage = (float) $data['score'];
                    $scaledScore = ($percentage / 100) * $maxPoints;
                    $result['score'] = (float) round($scaledScore, 2);
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
    protected function buildPrompt(string $essayText, string $questionText): string
    {
        return <<<PROMPT
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
You MUST respond with a valid JSON object ONLY. DO NOT provide feedback, explanations, or reasoning.
The score MUST be a WHOLE NUMBER between 0 and 100.
{
    "score": <integer_value_between_0_and_100>
}
PROMPT;
    }

    /**
     * Assess an essay and return a score and feedback.
     *
     * @param  string  $essayText  The student's essay answer.
     * @param  string  $questionText  The essay prompt/question.
     * @param  int  $maxPoints  The maximum points possible for this question.
     * @return array{score: float, feedback: string}
     */
    public function assessEssay(string $essayText, string $questionText, int $maxPoints): array
    {
        $prompt = $this->buildPrompt($essayText, $questionText);

        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'keep_alive' => -1,
                'options' => [
                    'temperature' => 0,
                    'num_predict' => 50, // Increased
                    'num_ctx' => 1024,
                    'top_k' => 5,
                    'top_p' => 0.1,
                ],
            ]);

            if ($response->successful()) {
                $data = json_decode($response->json('response'), true);

                if (isset($data['score'])) {
                    $percentage = (float) $data['score'];
                    $scaledScore = ($percentage / 100) * $maxPoints;

                    return [
                        'score' => (float) round($scaledScore, 2),
                        'feedback' => '',
                    ];
                }
            }

            Log::error('AI Assessment failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('AI Assessment error: '.$e->getMessage());
        }

        return [
            'score' => 0.0,
            'feedback' => '',
        ];
    }
}
