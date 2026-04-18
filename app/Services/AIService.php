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
     * Pre-warm the AI model by loading it into RAM.
     *
     * @return bool
     */
    public function preWarm(): bool
    {
        try {
            // This just loads the model without a full prompt
            Http::timeout(5)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => '',
                'stream' => false,
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
     * @param string $essayText The student's essay answer.
     * @param string $questionText The essay prompt/question.
     * @param int $maxPoints The maximum points possible for this question.
     * @return array{score: float, feedback: string}
     */
    public function assessEssay(string $essayText, string $questionText, int $maxPoints): array
    {
        $prompt = <<<PROMPT
        Act as a STRICT academic examiner. Your task is to evaluate a student's essay response based on a specific question.
        
        Question: "$questionText"
        Student Essay: "$essayText"
        Maximum Points: $maxPoints
        
        STRICT GRADING RULES:
        1. If the essay says "I don't know", "skip", or anything similar, the score MUST be 0.
        2. If the essay is completely irrelevant to the question, the score MUST be 0.
        3. If the essay is too short to provide any meaningful information (e.g., just 1-2 random words), the score MUST be 0.
        4. ONLY give points if the student actually attempts to answer the question with relevant facts or reasoning.
        
        Response Format:
        You MUST respond with a valid JSON object only. No other text.
        The score MUST be a WHOLE NUMBER (flat number), no decimals allowed.
        {
            "score": <integer_value_between_0_and_$maxPoints>
        }
        PROMPT;

        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = json_decode($response->json('response'), true);
                
                if (isset($data['score'])) {
                    return [
                        'score' => (float) round((float) $data['score']),
                        'feedback' => '',
                    ];
                }
            }
            
            Log::error('AI Assessment failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('AI Assessment error: ' . $e->getMessage());
        }

        // Fallback in case of failure
        return [
            'score' => 0.0,
            'feedback' => 'AI Assessment unavailable at this time.',
        ];
    }
}
