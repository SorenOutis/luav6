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
    public function prompt(string $prompt, array $history = []): string
    {
        if (! $this->accountId || ! $this->apiToken) {
            throw new \Exception('Cloudflare Workers AI is not configured. Please set your Account ID and API Token in Platform Settings.');
        }

        try {
            // Build the conversation context
            $messages = [
                ['role' => 'system', 'content' => $this->getInstructions()],
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
        return "You are 'KOA', the official AI assistant for the LSI learning platform.
        
        GUARDRAILS & RULES:
        1. Your primary role is to assist students with their learning journey on LSI.
        2. You MUST ONLY discuss topics related to the LSI platform, education, student progress, assignments, and courses.
        3. If a user asks about unrelated topics (e.g., entertainment, politics, general trivia not related to their studies), politely decline and remind them that you are here to help with their studies on LSI.
        4. Always be professional, encouraging, and concise in your responses.
        5. If a user submits an essay for review, provide constructive feedback on structure, clarity, grammar, and content quality.
        6. NEVER make up information about the user or their progress.
        
        TONE:
        Professional, encouraging, and educational.";
    }
}
