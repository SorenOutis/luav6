<?php

namespace App\Services;

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\AiManager;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

/**
 * Shared Echo conversation pipeline used by both the floating widget
 * (ChatController) and the persisted Chats history page
 * (ChatHistoryController). Owns toxicity screening, the daily message cap,
 * user-context building, and provider routing with Ollama fallback.
 */
class ChatService
{
    /** Max number of files a student may attach to a single message. */
    public const MAX_ATTACHMENTS = 4;

    /** Per-file size cap in kilobytes (5 MB). */
    public const MAX_ATTACHMENT_KB = 5120;

    /** MIME types accepted as chat attachments (images + common documents). */
    public const ALLOWED_ATTACHMENT_MIMES = [
        'image/png', 'image/jpeg', 'image/webp', 'image/gif',
        'application/pdf',
        'text/plain', 'text/csv', 'text/markdown', 'text/html',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public function __construct(private AiChatLogger $aiChatLogger) {}

    /**
     * Convert an uploaded file into the SDK File attachment the agent
     * understands. Images become vision-capable Image attachments; every
     * other accepted type becomes a Document attachment.
     */
    public function attachmentFromUpload(UploadedFile $file): Image|Document
    {
        $attachment = str_starts_with($file->getMimeType() ?? '', 'image/')
            ? Image::fromUpload($file)
            : Document::fromUpload($file);

        return $attachment->as($file->getClientOriginalName());
    }

    /**
     * Serializable metadata for a persisted attachment (stored on the message
     * so history can render it) — not the raw bytes, which are only sent to
     * the provider once.
     *
     * @return array{name: string, size: int, mime: string, kind: string}
     */
    public function attachmentMeta(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'kind' => str_starts_with($file->getMimeType() ?? '', 'image/') ? 'image' : 'document',
        ];
    }

    /**
     * Validation rules shared by the chat endpoints and the Chats history page
     * for each individual uploaded attachment.
     *
     * @return array<int, string>
     */
    public function attachmentValidationRules(): array
    {
        return ['file', 'mimes:png,jpg,jpeg,webp,gif,pdf,txt,csv,md,html,doc,docx,xls,xlsx,ppt,pptx', 'max:'.self::MAX_ATTACHMENT_KB];
    }

    /**
     * Convert the uploaded files on a request into SDK attachments (to send to
     * the provider) and serializable metadata (to persist on the message).
     *
     * @return array{0: array<int, File>, 1: array<int, array<string, mixed>>}
     */
    public function buildAttachments(Request $request): array
    {
        $sdkAttachments = [];
        $attachmentMeta = [];

        foreach ($request->file('attachments', []) as $file) {
            $sdkAttachments[] = $this->attachmentFromUpload($file);
            $attachmentMeta[] = $this->attachmentMeta($file);
        }

        return [$sdkAttachments, $attachmentMeta];
    }

    /**
     * Build a streamable SSE response that emits a single text delta. Used to
     * keep the front-end streaming uniform even when a provider (Cloudflare,
     * Ollama fallback) can't stream natively.
     */
    public function streamText(string $text): StreamableAgentResponse
    {
        $text = (string) $text;

        return new StreamableAgentResponse(
            (string) Str::uuid7(),
            function () use ($text) {
                yield new TextDelta(
                    id: (string) Str::uuid7(),
                    messageId: (string) Str::uuid7(),
                    delta: $text,
                    timestamp: now()->getTimestampMs(),
                );
            },
            new Meta,
        );
    }

    /**
     * Combine the reasoning (thinking) deltas from a streamed response into a
     * single string. Multi-step generations carry one reasoning id per step;
     * each step's text is joined separately, mirroring TextDelta::combine.
     *
     * @param  Collection<int, mixed>  $events
     */
    public function combineReasoning(Collection $events): string
    {
        return $events->whereInstanceOf(ReasoningDelta::class)
            ->groupBy(fn (ReasoningDelta $event) => $event->reasoningId)
            ->map(fn (Collection $deltas) => $deltas->map(fn (ReasoningDelta $event) => $event->delta)->join(''))
            ->filter(fn (string $text) => trim($text) !== '')
            ->values()
            ->join("\n\n");
    }

    /**
     * Convert raw history rows into SDK Message objects. Past attachments are
     * intentionally not re-sent to the provider on follow-up turns — only the
     * current message's attachments travel with each request.
     *
     * @param  array<int, array<string, mixed>>  $historyData
     * @return array<int, Message>
     */
    private function buildHistoryMessages(array $historyData): array
    {
        return collect($historyData)->map(function ($msg) {
            if ($msg['role'] === 'user') {
                return new UserMessage($msg['content']);
            }

            return new AssistantMessage($msg['content']);
        })->toArray();
    }

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
     * @param  array<int, File>  $attachments
     */
    public function prompt(string $message, array $historyData, string $userContext, ?User $user, array $attachments = [], array $loggingContext = []): string
    {
        $history = $this->buildHistoryMessages($historyData);

        // Select agent based on provider setting; the user's role picks
        // the agent class (admins get workspace management tools).
        $provider = Setting::get('ai_provider', 'gemini');
        $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';
        $agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;
        $startedAt = hrtime(true);
        $attemptContext = $this->providerContext($loggingContext, $provider, null, $agentClass);

        if (AiSdkProviderService::isRemovedCompatibleProvider($provider)) {
            throw new \Exception('The selected OpenAI-compatible provider was removed. Choose another provider in Platform Settings.');
        }

        try {
            if ($provider === 'cloudflare') {
                // Cloudflare Workers AI keeps its raw integration — it has
                // no tool-calling support, so Echo answers without tools.
                $cloudflareService = new CloudflareAIService;
                $attemptContext = $this->providerContext($loggingContext, 'cloudflare', Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash'), $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.started', $attemptContext);

                $response = $cloudflareService->prompt($message, $historyData, $userContext);
                $this->logCompletedResponse($attemptContext, $response, $startedAt);

                return $response;
            }

            if ($provider === 'groq') {
                // Groq goes through the Laravel AI SDK so tool calling
                // works — the raw GroqAIService integration has none.
                $groqApiKey = Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key');
                $groqModel = Setting::get('groq_model', 'llama-3.1-8b-instant');
                $attemptContext = $this->providerContext($loggingContext, 'groq', $groqModel, $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.started', $attemptContext);

                if (! $groqApiKey) {
                    throw new \Exception('Groq is not configured. Paste your API key in Platform Settings.');
                }

                config([
                    'ai.providers.groq.key' => $groqApiKey,
                    'ai.providers.groq.models.text.default' => $groqModel,
                ]);
                app(AiManager::class)->forgetInstance('groq');

                $response = $this->promptAgent($agentClass, $history, $userContext, $message, 'groq', $groqModel, $attachments);

                app(AiUsageTracker::class)->record(
                    'groq',
                    $groqModel,
                    'chat',
                    AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                    AiUsageTracker::tokensFromChars(strlen((string) $response)),
                );
                $this->logCompletedResponse($attemptContext, $response, $startedAt);

                return $response;
            }

            if (AiSdkProviderService::isSdkRouted($provider)) {
                // Any other text-capable Laravel AI SDK provider (OpenAI,
                // Anthropic, Mistral, DeepSeek, xAI, OpenRouter, Azure,
                // Ollama). Credentials and model come from Platform
                // Settings; the per-prompt provider/model override beats
                // the agent's #[Provider('gemini')] attribute.
                $sdkProvider = AiSdkProviderService::for($provider);
                $attemptContext = $this->providerContext($loggingContext, $provider, $sdkProvider->model(), $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.started', $attemptContext);

                if (! $sdkProvider->isConfigured()) {
                    throw new \Exception("{$provider} is not configured. Paste your API key in Platform Settings.");
                }

                $sdkProvider->applyToSdk();

                $response = $this->promptAgent($agentClass, $history, $userContext, $message, $provider, $sdkProvider->model(), $attachments);

                app(AiUsageTracker::class)->record(
                    $provider,
                    $sdkProvider->model(),
                    'chat',
                    AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                    AiUsageTracker::tokensFromChars(strlen((string) $response)),
                );
                $this->logCompletedResponse($attemptContext, $response, $startedAt);

                return $response;
            }

            // Point the Laravel AI SDK at the Gemini key/model stored
            // in Platform Settings (falls back to env GEMINI_API_KEY).
            $gemini = app(GeminiAIService::class);
            $attemptContext = $this->providerContext($loggingContext, 'gemini', $gemini->chatModel(), $agentClass);
            $this->aiChatLogger->info('ai_chat.provider.started', $attemptContext);
            if (! $gemini->apiKey()) {
                throw new \Exception('Gemini is not configured. Paste your API key in Platform Settings.');
            }
            $gemini->applyToSdk();

            $response = $this->promptAgent($agentClass, $history, $userContext, $message, 'gemini', $gemini->chatModel(), $attachments);

            app(AiUsageTracker::class)->record(
                'gemini',
                $gemini->chatModel(),
                'chat',
                AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext)),
                AiUsageTracker::tokensFromChars(strlen((string) $response)),
            );
            $this->logCompletedResponse($attemptContext, $response, $startedAt);

            return $response;
        } catch (Throwable $e) {
            $this->aiChatLogger->error('ai_chat.provider.failed', $e, array_merge($attemptContext, [
                'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                'fallback_enabled' => $ollamaEnabled,
            ]));

            // Try Ollama fallback if enabled
            if ($ollamaEnabled) {
                try {
                    $ollamaService = new OllamaAIService;
                    $fallbackContext = $this->providerContext($loggingContext, 'ollama', Setting::get('ollama_model', 'llama3.2:1b'), $agentClass, $provider);
                    $fallbackStartedAt = hrtime(true);
                    $this->aiChatLogger->info('ai_chat.provider.fallback_started', $fallbackContext);
                    $response = $ollamaService->prompt($message, $historyData, $userContext);
                    $this->logCompletedResponse($fallbackContext, $response, $fallbackStartedAt);

                    return $response;
                } catch (Throwable $ollamaError) {
                    $this->aiChatLogger->error('ai_chat.provider.fallback_failed', $ollamaError, array_merge($fallbackContext ?? $loggingContext, [
                        'duration_ms' => isset($fallbackStartedAt) ? $this->aiChatLogger->elapsedMilliseconds($fallbackStartedAt) : null,
                    ]));
                    throw $e; // Throw original error
                }
            }

            throw $e; // No fallback enabled, throw original error
        }
    }

    /**
     * Stream the conversation through the configured AI provider, returning a
     * streamable SSE response. Providers that can't stream natively (Cloudflare,
     * Ollama fallback) emit their full text as a single delta so the front-end
     * always receives a uniform stream.
     *
     * @param  array<int, array{role: string, content: string}>  $historyData
     * @param  array<int, File>  $attachments
     */
    public function stream(string $message, array $historyData, string $userContext, ?User $user, array $attachments = [], array $loggingContext = []): StreamableAgentResponse
    {
        $history = $this->buildHistoryMessages($historyData);

        $provider = Setting::get('ai_provider', 'gemini');
        $ollamaEnabled = Setting::get('ollama_enabled', false) === '1';
        $agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;
        $startedAt = hrtime(true);
        $attemptContext = $this->providerContext($loggingContext, $provider, null, $agentClass);

        if (AiSdkProviderService::isRemovedCompatibleProvider($provider)) {
            throw new \Exception('The selected OpenAI-compatible provider was removed. Choose another provider in Platform Settings.');
        }

        try {
            if ($provider === 'cloudflare') {
                // Cloudflare Workers AI has no streaming — emit the full text.
                $cloudflareService = new CloudflareAIService;
                $attemptContext = $this->providerContext($loggingContext, 'cloudflare', Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash'), $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.stream_started', $attemptContext);

                return $this->logTextStream($this->streamText($cloudflareService->prompt($message, $historyData, $userContext)), $attemptContext, $startedAt);
            }

            if ($provider === 'groq') {
                $groqApiKey = Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key');
                $groqModel = Setting::get('groq_model', 'llama-3.1-8b-instant');
                $attemptContext = $this->providerContext($loggingContext, 'groq', $groqModel, $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.stream_started', $attemptContext);

                if (! $groqApiKey) {
                    throw new \Exception('Groq is not configured. Paste your API key in Platform Settings.');
                }

                config([
                    'ai.providers.groq.key' => $groqApiKey,
                    'ai.providers.groq.models.text.default' => $groqModel,
                ]);
                app(AiManager::class)->forgetInstance('groq');

                return $this->streamAgent($agentClass, $history, $userContext, $message, 'groq', $groqModel, $attachments, $attemptContext, $startedAt);
            }

            if (AiSdkProviderService::isSdkRouted($provider)) {
                $sdkProvider = AiSdkProviderService::for($provider);
                $attemptContext = $this->providerContext($loggingContext, $provider, $sdkProvider->model(), $agentClass);
                $this->aiChatLogger->info('ai_chat.provider.stream_started', $attemptContext);

                if (! $sdkProvider->isConfigured()) {
                    throw new \Exception("{$provider} is not configured. Paste your API key in Platform Settings.");
                }

                $sdkProvider->applyToSdk();

                return $this->streamAgent($agentClass, $history, $userContext, $message, $provider, $sdkProvider->model(), $attachments, $attemptContext, $startedAt);
            }

            // Gemini streams through the Laravel AI SDK.
            $gemini = app(GeminiAIService::class);
            $attemptContext = $this->providerContext($loggingContext, 'gemini', $gemini->chatModel(), $agentClass);
            $this->aiChatLogger->info('ai_chat.provider.stream_started', $attemptContext);
            if (! $gemini->apiKey()) {
                throw new \Exception('Gemini is not configured. Paste your API key in Platform Settings.');
            }
            $gemini->applyToSdk();

            return $this->streamAgent($agentClass, $history, $userContext, $message, 'gemini', $gemini->chatModel(), $attachments, $attemptContext, $startedAt);
        } catch (Throwable $e) {
            $this->aiChatLogger->error('ai_chat.provider.stream_failed', $e, array_merge($attemptContext, [
                'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                'fallback_enabled' => $ollamaEnabled,
            ]));

            if ($ollamaEnabled) {
                try {
                    $ollamaService = new OllamaAIService;
                    $fallbackContext = $this->providerContext($loggingContext, 'ollama', Setting::get('ollama_model', 'llama3.2:1b'), $agentClass, $provider);
                    $fallbackStartedAt = hrtime(true);
                    $this->aiChatLogger->info('ai_chat.provider.stream_fallback_started', $fallbackContext);

                    return $this->logTextStream($this->streamText($ollamaService->prompt($message, $historyData, $userContext)), $fallbackContext, $fallbackStartedAt);
                } catch (Throwable $ollamaError) {
                    $this->aiChatLogger->error('ai_chat.provider.stream_fallback_failed', $ollamaError, array_merge($fallbackContext ?? $loggingContext, [
                        'duration_ms' => isset($fallbackStartedAt) ? $this->aiChatLogger->elapsedMilliseconds($fallbackStartedAt) : null,
                    ]));
                    throw $e;
                }
            }

            throw $e;
        }
    }

    /**
     * Run the role-resolved agent through the Laravel AI SDK and return its
     * text response.
     *
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, File>  $attachments
     */
    private function promptAgent(string $agentClass, array $history, string $userContext, string $message, string $provider, ?string $model = null, array $attachments = []): string
    {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);

        return $agent->prompt($message, attachments: $attachments, provider: $provider, model: $model)->text;
    }

    /**
     * Run the role-resolved agent through the Laravel AI SDK and return its
     * streamable response, chaining usage tracking onto stream completion.
     *
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, File>  $attachments
     */
    private function streamAgent(string $agentClass, array $history, string $userContext, string $message, string $provider, string $model, array $attachments = [], array $loggingContext = [], ?int $startedAt = null): StreamableAgentResponse
    {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);

        $input = strlen($message) + strlen($userContext);

        $stream = $agent
            ->stream($message, attachments: $attachments, provider: $provider, model: $model)
            ->then(function ($response) use ($provider, $model, $input, $loggingContext, $startedAt) {
                app(AiUsageTracker::class)->record(
                    $provider,
                    $model,
                    'chat',
                    AiUsageTracker::tokensFromChars($input),
                    AiUsageTracker::tokensFromChars(strlen((string) $response->text)),
                );

                $this->logCompletedResponse($loggingContext, (string) $response->text, $startedAt ?? hrtime(true), true);
            });

        return $this->logStreamErrors($stream, $loggingContext, $startedAt ?? hrtime(true));
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     * @return array<string, mixed>
     */
    private function providerContext(array $loggingContext, string $provider, ?string $model, string $agentClass, ?string $fallbackFrom = null): array
    {
        return array_merge($loggingContext, [
            'provider' => $provider,
            'model' => $model,
            'agent' => class_basename($agentClass),
            'fallback_from' => $fallbackFrom,
        ]);
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     */
    private function logCompletedResponse(array $loggingContext, string $response, int $startedAt, bool $streamed = false): void
    {
        $this->aiChatLogger->info($streamed ? 'ai_chat.provider.stream_completed' : 'ai_chat.provider.completed', array_merge($loggingContext, [
            'response' => $this->aiChatLogger->textMetadata($response),
            'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
        ]));
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     */
    private function logTextStream(StreamableAgentResponse $stream, array $loggingContext, int $startedAt): StreamableAgentResponse
    {
        return $stream->then(function ($response) use ($loggingContext, $startedAt) {
            $this->logCompletedResponse($loggingContext, (string) $response->text, $startedAt, true);
        });
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     */
    private function logStreamErrors(StreamableAgentResponse $stream, array $loggingContext, int $startedAt): StreamableAgentResponse
    {
        return new StreamableAgentResponse(
            (string) Str::uuid7(),
            function () use ($stream, $loggingContext, $startedAt) {
                try {
                    yield from $stream;
                } catch (Throwable $e) {
                    $this->aiChatLogger->error('ai_chat.provider.stream_runtime_failed', $e, array_merge($loggingContext, [
                        'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                    ]));

                    throw $e;
                }
            },
            new Meta,
        );
    }
}
