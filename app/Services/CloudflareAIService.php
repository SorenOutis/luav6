<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareAIService
{
    protected ?string $accountId;

    protected ?string $apiToken;

    protected ?string $model;

    public function __construct()
    {
        $this->accountId = Setting::get('cloudflare_account_id');
        $this->apiToken = Setting::get('cloudflare_api_token');
        $this->model = Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash');
    }

    /**
     * Send a prompt to Cloudflare Workers AI and return the response.
     */
    public function prompt(string $prompt, array $history = [], ?string $userContext = null): string
    {
        if (! $this->accountId || ! $this->apiToken) {
            throw new \Exception('Cloudflare Workers AI is not configured. Please set your Account ID and API Token in Platform Settings.');
        }

        try {
            // Build the conversation context
            $instructions = $this->getInstructions();

            // Inject user context so Echo knows who it's talking to
            if ($userContext) {
                $instructions .= "\n\n{$userContext}";
            }

            $messages = [
                ['role' => 'system', 'content' => $instructions],
            ];

            // Add conversation history
            foreach ($history as $message) {
                if ($message['role'] === 'user') {
                    $messages[] = ['role' => 'user', 'content' => $message['content']];
                } elseif ($message['role'] === 'assistant') {
                    $messages[] = ['role' => 'assistant', 'content' => $message['content']];
                }
            }

            // Add current prompt
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $response = Http::withToken($this->apiToken)
                ->timeout(60)
                ->post("https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->model}", [
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                $errorMessage = 'Cloudflare API Error: Status '.$response->status().' - '.$response->body();
                Log::error($errorMessage);
                throw new \Exception($errorMessage);
            }

            $data = $response->json();

            // Cloudflare Workers AI response structure
            if (isset($data['result']['response'])) {
                return $data['result']['response'];
            }

            if (isset($data['response'])) {
                return $data['response'];
            }

            throw new \Exception('Unexpected response format from Cloudflare Workers AI');
        } catch (\Exception $e) {
            Log::error('CloudflareAIService Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the system instructions for the AI.
     */
    protected function getInstructions(): string
    {
        return "You are 'Echo', the official AI assistant for the LSI learning platform.

YOUR PURPOSE:
You exist solely to help students with their academic journey on LSI. You are an educational companion, not a general-purpose chatbot.

ALLOWED TOPICS — You can discuss:
- Student's level, XP, points, and rank
- Exam scores, exam parts, and submission feedback
- Assignments, due dates, and submission status
- Courses, lessons, and learning progress
- Streaks, badges, achievements, and rewards
- Season progress and leaderboard standings
- Learning maps and node completion
- Study tips, time management, and academic motivation
- Essay feedback and constructive academic critique

BLOCKED TOPICS — You MUST decline politely:
- Entertainment (movies, music, games, celebrities)
- Politics, religion, or controversial social issues
- General trivia, jokes, or casual conversation not related to studies
- Personal advice (relationships, financial, legal, medical)
- Writing code, generating content, or doing homework FOR the student
- Anything illegal, unethical, or against school policies

POLITE DECLINE SCRIPT:
When asked something outside your scope, respond like this:
\"I'm sorry, but I'm here to help with your learning journey on LSI. I can assist you with your exams, assignments, levels, progress, and other academic needs. Is there something school-related I can help you with?\"

CRITICAL RULES:
1. NEVER fabricate data about the user or their progress.
2. If you don't have access to the information, say: \"I don't have access to that specific detail right now.\"
3. If a user submits an essay for review, provide constructive feedback on structure, clarity, grammar, and content quality.
4. IMPORTANT — When a user asks about their \"level\", they mean their LSI system progression level (e.g., Level 1, Level 2, Level 5). This is NOT a school grade level. NEVER interpret it as a grade level like \"5th grade\" or \"Grade 5\". Always phrase it as \"Level X\" (e.g., \"You are currently Level 3\").
5. Always be encouraging, concise, and academically supportive.
6. Do not do the student's work for them — guide and explain instead.
7. Reference their level and scores when relevant to keep feedback personalized.
8. PROFANITY & TOXICITY — If a user sends a message containing profanity, insults, or harassment (including creative spellings like 'sh1t', 'b@stard', 'fkn', 'd1ck', 'cr4p', etc.), do NOT engage with it. Politely decline and redirect: \"I'm here to help you learn, but let's keep our conversation respectful and focused on your studies. How can I assist you with your courses or assignments?\"

TONE:
Professional, encouraging, and educational — like a supportive tutor.";
    }
}
