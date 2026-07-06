<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaAIService
{
    protected ?string $baseUrl;
    protected ?string $model;

    public function __construct()
    {
        $this->baseUrl = Setting::get('ollama_url', 'http://localhost:11434');
        $this->model = Setting::get('ollama_model', 'llama3.2:1b');
    }

    /**
     * Send a prompt to Ollama API and return the response.
     */
    public function prompt(string $prompt, array $history = []): string
    {
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

            $response = Http::timeout(60)->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
            ]);

            if (!$response->successful()) {
                $errorMessage = 'Ollama API Error: Status '.$response->status().' - '.$response->body();
                Log::error($errorMessage);
                throw new \Exception($errorMessage);
            }

            $data = $response->json();

            // Ollama response format
            if (isset($data['message']['content'])) {
                return $data['message']['content'];
            }

            throw new \Exception('Unexpected response format from Ollama API');
        } catch (\Exception $e) {
            Log::error('OllamaAIService Error: '.$e->getMessage());
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
