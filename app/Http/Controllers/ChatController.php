<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Services\CloudflareAIService;
use App\Services\GroqAIService;
use App\Services\OllamaAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class ChatController extends Controller
{
    protected string $sessionKey = 'koa_chat_history';

    public function __invoke(Request $request)
    {
        if (! Setting::get('ai_chat_enabled', true)) {
            return response()->json([
                'response' => Setting::get('ai_chat_maintenance_message', 'KOA is currently under maintenance.'),
            ], 503);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            // Get history from session
            $historyData = session()->get($this->sessionKey, []);

            // Map session data to message objects
            $history = collect($historyData)->map(function ($msg) {
                if ($msg['role'] === 'user') {
                    return new UserMessage($msg['content']);
                }

                return new AssistantMessage($msg['content']);
            })->toArray();

            // Select agent based on provider setting
            $provider = Setting::get('ai_provider', 'gemini');
            $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';
            $response = null;
            $lastError = null;

            try {
                if ($provider === 'cloudflare') {
                    $cloudflareService = new CloudflareAIService;
                    $response = $cloudflareService->prompt($request->message, $historyData);
                } elseif ($provider === 'groq') {
                    $groqService = new GroqAIService;
                    $response = $groqService->prompt($request->message, $historyData);
                } else {
                    $agent = new AssistantAgent;
                    $agent->setHistory($history);
                    $agentResponse = $agent->prompt($request->message);
                    $response = $agentResponse->text;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error('Primary AI provider failed: '.$lastError);

                // Try Ollama fallback if enabled
                if ($ollamaEnabled) {
                    try {
                        $ollamaService = new OllamaAIService;
                        $response = $ollamaService->prompt($request->message, $historyData);
                        Log::info('Successfully fell back to Ollama');
                    } catch (\Exception $ollamaError) {
                        Log::error('Ollama fallback also failed: '.$ollamaError->getMessage());
                        throw $e; // Throw original error
                    }
                } else {
                    throw $e; // No fallback enabled, throw original error
                }
            }

            // Update history in session
            $historyData[] = ['role' => 'user', 'content' => $request->message];
            $historyData[] = ['role' => 'assistant', 'content' => $response];
            session()->put($this->sessionKey, $historyData);
            session()->save(); // Explicitly save session

            return response()->json([
                'response' => $response,
                'history' => $historyData,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Controller Error: '.$e->getMessage());

            return response()->json([
                'response' => 'KOA is currently having technical difficulties. Please try again later.',
            ], 500);
        }
    }

    public function getHistory()
    {
        $history = session()->get($this->sessionKey);

        if (! $history) {
            $history = [['role' => 'assistant', 'content' => 'Hello! How can I help you today?']];
            session()->put($this->sessionKey, $history);
            session()->save();
        }

        return response()->json([
            'history' => $history,
        ]);
    }
}
