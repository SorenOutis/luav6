<?php

namespace App\Services;

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

/**
 * Shared Echo conversation pipeline used by both the floating widget
 * (ChatController) and the persisted Chats history page
 * (ChatHistoryController). Owns toxicity screening, the daily message cap,
 * user-context building, and provider routing with Ollama fallback.
 */
class ChatService
{
    /**
     * Strip leetspeak substitutions from a string so creative spellings
     * like 'sh1t' or 'b@stard' are caught by the regex patterns.
     */
    private function normalizeMessage(string $message): string
    {
        return str_replace(
            ['0', '1', '3', '4', '5', '7', '8', '@', '$', '!', '|'],
            ['o', 'i', 'e', 'a', 's', 't', 'b', 'a', 's', 'i', 'i'],
            $message
        );
    }

    /**
     * Server-side toxicity guardrail – mirrors the client-side patterns.
     * Blocks profanity, insults, and harassment before the message reaches the AI.
     */
    public function isToxic(string $message): bool
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
    public function buildUserContext(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'The user is not logged in.';
        }

        if ($user->is_admin) {
            return "=== AUTHENTICATED USER ===\n".
                "Role: Teacher/Admin (workspace owner)\n".
                "Name: {$user->name}\n".
                "Email: {$user->email}\n".
                "===========================\n".
                'Address them as a colleague managing their workspace, and use the tools for all workspace data.';
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

    /**
     * Enforce the student daily message cap (cost/abuse guard; admins exempt).
     * Returns the "limit reached" response message, or null when the message
     * may proceed (consuming one slot).
     */
    public function dailyLimitMessage(?User $user): ?string
    {
        if ($user && ! $user->is_admin) {
            $dailyLimit = (int) Setting::get('ai_chat_daily_limit', 100);

            if ($dailyLimit > 0) {
                $cacheKey = "ai_chat_daily:{$user->id}:".now()->toDateString();
                $used = (int) Cache::get($cacheKey, 0);

                if ($used >= $dailyLimit) {
                    return "You've used all {$dailyLimit} of your Echo messages for today — nice dedication! Your limit resets at midnight. In the meantime, your dashboard has your assignments, exams, and lessons.";
                }

                Cache::put($cacheKey, $used + 1, now()->endOfDay());
            }
        }

        return null;
    }

    /**
     * Route the conversation through the configured AI provider and return
     * the assistant's text response, falling back to Ollama when enabled.
     *
     * @param  array<int, array{role: string, content: string}>  $historyData
     */
    public function prompt(string $message, array $historyData, string $userContext, ?User $user): string
    {
        $history = collect($historyData)->map(function ($msg) {
            if ($msg['role'] === 'user') {
                return new UserMessage($msg['content']);
            }

            return new AssistantMessage($msg['content']);
        })->toArray();

        // Select agent based on provider setting; the user's role picks
        // the agent class (admins get workspace management tools).
        $provider = Setting::get('ai_provider', 'gemini');
        $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';
        $agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;
        $lastError = null;

        try {
            if ($provider === 'cloudflare') {
                // Cloudflare Workers AI keeps its raw integration — it has
                // no tool-calling support, so Echo answers without tools.
                $cloudflareService = new CloudflareAIService;

                return $cloudflareService->prompt($message, $historyData, $userContext);
            }

            if ($provider === 'groq') {
                // Groq goes through the Laravel AI SDK so tool calling
                // works — the raw GroqAIService integration has none.
                $groqApiKey = Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key');
                $groqModel = Setting::get('groq_model', 'llama-3.1-8b-instant');

                if (! $groqApiKey) {
                    throw new \Exception('Groq is not configured. Paste your API key in Platform Settings.');
                }

                config([
                    'ai.providers.groq.key' => $groqApiKey,
                    'ai.providers.groq.models.text.default' => $groqModel,
                ]);
                app(AiManager::class)->forgetInstance('groq');

                $response = $this->promptAgent($agentClass, $history, $userContext, $message, 'groq', $groqModel);

                app(AiUsageTracker::class)->record(
                    'groq',
                    $groqModel,
                    'chat',
                    AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                    AiUsageTracker::tokensFromChars(strlen((string) $response)),
                );

                return $response;
            }

            if (AiSdkProviderService::isSdkRouted($provider)) {
                // Any other text-capable Laravel AI SDK provider (OpenAI,
                // Anthropic, Mistral, DeepSeek, xAI, OpenRouter, Azure,
                // Ollama). Credentials and model come from Platform
                // Settings; the per-prompt provider/model override beats
                // the agent's #[Provider('gemini')] attribute.
                $sdkProvider = AiSdkProviderService::for($provider);

                if (! $sdkProvider->isConfigured()) {
                    throw new \Exception("{$provider} is not configured. Paste your API key in Platform Settings.");
                }

                $sdkProvider->applyToSdk();

                $response = $this->promptAgent($agentClass, $history, $userContext, $message, $provider, $sdkProvider->model());

                app(AiUsageTracker::class)->record(
                    $provider,
                    $sdkProvider->model(),
                    'chat',
                    AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                    AiUsageTracker::tokensFromChars(strlen((string) $response)),
                );

                return $response;
            }

            // Point the Laravel AI SDK at the Gemini key/model stored
            // in Platform Settings (falls back to env GEMINI_API_KEY).
            $gemini = app(GeminiAIService::class);
            if (! $gemini->apiKey()) {
                throw new \Exception('Gemini is not configured. Paste your API key in Platform Settings.');
            }
            $gemini->applyToSdk();

            $response = $this->promptAgent($agentClass, $history, $userContext, $message, 'gemini', $gemini->chatModel());

            app(AiUsageTracker::class)->record(
                'gemini',
                $gemini->chatModel(),
                'chat',
                AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                AiUsageTracker::tokensFromChars(strlen((string) $response)),
            );

            return $response;
        } catch (\Exception $e) {
            $lastError = $e->getMessage();
            Log::error('Primary AI provider failed: '.$lastError);

            // Try Ollama fallback if enabled
            if ($ollamaEnabled) {
                try {
                    $ollamaService = new OllamaAIService;
                    $response = $ollamaService->prompt($message, $historyData, $userContext);
                    Log::info('Successfully fell back to Ollama');

                    return $response;
                } catch (\Exception $ollamaError) {
                    Log::error('Ollama fallback also failed: '.$ollamaError->getMessage());
                    throw $e; // Throw original error
                }
            }

            throw $e; // No fallback enabled, throw original error
        }
    }

    /**
     * Run the role-resolved agent through the Laravel AI SDK and return its
     * text response.
     *
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     */
    private function promptAgent(string $agentClass, array $history, string $userContext, string $message, string $provider, ?string $model = null): string
    {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);

        return $agent->prompt($message, provider: $provider, model: $model)->text;
    }
}
