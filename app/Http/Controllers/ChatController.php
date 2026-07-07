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
    protected string $sessionKey = 'echo_chat_history';

    /**
     * Strip leetspeak substitutions from a string so creative spellings
     * like 'sh1t' or 'b@stard' are caught by the regex patterns.
     */
    private function normalizeMessage(string $message): string
    {
        $message = str_replace(
            ['0', '1', '3', '4', '5', '7', '8', '@', '$', '!', '|'],
            ['o', 'i', 'e', 'a', 's', 't', 'b', 'a', 's', 'i', 'i'],
            $message
        );

        return $message;
    }

    /**
     * Server-side toxicity guardrail – mirrors the client-side patterns.
     * Blocks profanity, insults, and harassment before the message reaches the AI.
     */
    private function isToxic(string $message): bool
    {
        // Normalize leetspeak/creative spellings before checking
        $normalized = $this->normalizeMessage($message);

        $patterns = [
            // Swear words and abbreviations (word-boundary)
            '/\b(fuck|fck|fkn|wtf|wth|stfu|shit|bullshit|shitty|ass|asshole|bitch|bastard|damn|goddamn|hell|crap|pissed|dick|dickhead|prick|cunt|whore|slut|hoe|motherfucker|mofo|douche|douchebag|jackass|arse|bloody)\b/i',
            // Sloppy match — catches fuck/fck anywhere (inside compound words like "fucking", "motherfcker")
            '/(fuck|fck)/i',
            // Insults
            '/\b(stupid|dumb|idiot|moron|retard|useless|trash|suck|kys|kill yourself|shut up|annoying|loser)\b/i',
            // Harassment / toxicity — match bully and inflected forms like bullying
            '/\b(bully(?:ing)?|harass|threat|hate speech|racist|sexist|creep|weirdo)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) || preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a user context block with the authenticated user's real data,
     * so Echo can give accurate, personalized responses without needing
     * to fabricate or guess.
     */
    protected function buildUserContext(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'The user is not logged in.';
        }

        $progress = $user->activeSeasonProgress();

        $totalXp = $progress?->exp ?? 0;
        $level = $progress?->level ?? 1;
        $points = $progress?->points ?? 0;
        $streak = $user->current_streak ?? 0;
        $joined = $user->created_at?->format('M Y') ?? 'Unknown';

        return "=== AUTHENTICATED USER DATA (use this to personalize your response) ===\n".
            "Name: {$user->name}\n".
            "Joined: {$joined}\n".
            "LSI System Level: {$level} (this is the gamification progression level, NOT a school grade)\n".
            "Total XP: {$totalXp}\n".
            "Points: {$points}\n".
            "Current Streak: {$streak} day(s)\n".
            "Email: {$user->email}\n".
            '====================================================================';
    }

    public function __invoke(Request $request)
    {
        if (! Setting::get('ai_chat_enabled', true)) {
            return response()->json([
                'response' => Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance.'),
            ], 503);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        // ── Server-side toxicity guardrail ──
        if ($this->isToxic($request->message)) {
            return response()->json([
                'response' => "I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?",
            ], 200);
        }

        try {
            // Build user context with real data for personalization
            $userContext = $this->buildUserContext();

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
                    $response = $cloudflareService->prompt($request->message, $historyData, $userContext);
                } elseif ($provider === 'groq') {
                    $groqService = new GroqAIService;
                    $response = $groqService->prompt($request->message, $historyData, $userContext);
                } else {
                    $agent = new AssistantAgent;
                    $agent->setHistory($history);
                    $agent->setUserContext($userContext);
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
                        $response = $ollamaService->prompt($request->message, $historyData, $userContext);
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
                'response' => 'Sorry, something went wrong. Please try again in a moment.',
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
