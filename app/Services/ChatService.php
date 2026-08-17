<?php

namespace App\Services;

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\AiBudgetReservation;
use App\Models\ChatSession;
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

    /**
     * Maximum persisted turns sent back to an AI provider.
     *
     * The conversation itself remains durable; this only bounds each prompt's
     * database read, memory use, latency, and provider context cost.
     */
    public const MAX_CONTEXT_MESSAGES = 40;

    /** Approximate prompt-history budget (about 8k tokens for English text). */
    public const MAX_CONTEXT_CHARACTERS = 32000;

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
     * Return the newest useful slice of a persisted conversation.
     *
     * Rows are read newest-first so SQL can stop at MAX_CONTEXT_MESSAGES, then
     * selected newest-to-oldest until the character budget is full and finally
     * restored to chronological order for the provider. A single oversized
     * turn is truncated rather than allowing one message to defeat the bound.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contextMessages(ChatSession $session): array
    {
        $remaining = self::MAX_CONTEXT_CHARACTERS;
        $selected = [];

        $messages = $session->messages()
            ->select(['id', 'role', 'content', 'thinking', 'attachments'])
            ->reorder()
            ->latest('id')
            ->limit(self::MAX_CONTEXT_MESSAGES)
            ->get();

        foreach ($messages as $message) {
            if ($remaining <= 0) {
                break;
            }

            $content = (string) $message->content;
            if (mb_strlen($content) > $remaining) {
                $content = mb_substr($content, 0, $remaining);
            }

            $row = [
                'role' => (string) $message->role,
                'content' => $content,
            ];

            if ($message->thinking) {
                $row['thinking'] = $message->thinking;
            }
            if ($message->attachments) {
                $row['attachments'] = $message->attachments;
            }

            $selected[] = $row;
            $remaining -= mb_strlen($content);
        }

        return array_reverse($selected);
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
     * the assistant's text response, applying the workspace fallback rule.
     *
     * @param  array<int, array{role: string, content: string}>  $historyData
     * @param  array<int, File>  $attachments
     */
    public function prompt(string $message, array $historyData, string $userContext, ?User $user, array $attachments = [], array $loggingContext = []): string
    {
        $history = $this->buildHistoryMessages($historyData);
        $primary = (string) Setting::get('ai_provider', 'gemini');
        $agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;

        try {
            return $this->promptWithProvider(
                $primary,
                $agentClass,
                $history,
                $historyData,
                $userContext,
                $message,
                $attachments,
                $loggingContext,
            );
        } catch (Throwable $primaryFailure) {
            $fallbackPolicy = app(AiProviderFallbackPolicy::class);
            $fallback = $fallbackPolicy->fallbackFor($primary, $primaryFailure);

            if (! $fallback) {
                throw $primaryFailure;
            }

            app(AiBudgetManager::class)->recordFallback(
                'chat',
                $primary,
                $fallback,
                $fallbackPolicy->reason($primaryFailure),
            );

            try {
                return $this->promptWithProvider(
                    $fallback,
                    $agentClass,
                    $history,
                    $historyData,
                    $userContext,
                    $message,
                    $attachments,
                    $loggingContext,
                    $primary,
                );
            } catch (Throwable $fallbackFailure) {
                $this->aiChatLogger->error('ai_chat.provider.fallback_failed', $fallbackFailure, [
                    ...$loggingContext,
                    'provider' => $fallback,
                    'fallback_from' => $primary,
                ]);

                throw $primaryFailure;
            }
        }
    }

    /**
     * Stream a conversation through the selected provider. Budget rejection
     * and provider setup failures happen before any SSE bytes are emitted, so
     * the configured fallback can still be selected safely.
     *
     * @param  array<int, array{role: string, content: string}>  $historyData
     * @param  array<int, File>  $attachments
     */
    public function stream(string $message, array $historyData, string $userContext, ?User $user, array $attachments = [], array $loggingContext = []): StreamableAgentResponse
    {
        $history = $this->buildHistoryMessages($historyData);
        $primary = (string) Setting::get('ai_provider', 'gemini');
        $agentClass = $user?->is_admin ? AdminAssistantAgent::class : AssistantAgent::class;

        try {
            return $this->streamWithProvider(
                $primary,
                $agentClass,
                $history,
                $historyData,
                $userContext,
                $message,
                $attachments,
                $loggingContext,
            );
        } catch (Throwable $primaryFailure) {
            $fallbackPolicy = app(AiProviderFallbackPolicy::class);
            $fallback = $fallbackPolicy->fallbackFor($primary, $primaryFailure);

            if (! $fallback) {
                throw $primaryFailure;
            }

            app(AiBudgetManager::class)->recordFallback(
                'chat',
                $primary,
                $fallback,
                $fallbackPolicy->reason($primaryFailure),
            );

            try {
                return $this->streamWithProvider(
                    $fallback,
                    $agentClass,
                    $history,
                    $historyData,
                    $userContext,
                    $message,
                    $attachments,
                    $loggingContext,
                    $primary,
                );
            } catch (Throwable $fallbackFailure) {
                $this->aiChatLogger->error('ai_chat.provider.stream_fallback_failed', $fallbackFailure, [
                    ...$loggingContext,
                    'provider' => $fallback,
                    'fallback_from' => $primary,
                ]);

                throw $primaryFailure;
            }
        }
    }

    /**
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, array<string, mixed>>  $historyData
     * @param  array<int, File>  $attachments
     */
    private function promptWithProvider(
        string $provider,
        string $agentClass,
        array $history,
        array $historyData,
        string $userContext,
        string $message,
        array $attachments,
        array $loggingContext,
        ?string $fallbackFrom = null,
    ): string {
        $startedAt = hrtime(true);
        $attemptContext = $this->providerContext($loggingContext, $provider, null, $agentClass, $fallbackFrom);
        $usage = app(AiUsageTracker::class);
        $reservation = null;

        try {
            $model = $this->prepareProvider($provider);
            $attemptContext = $this->providerContext($loggingContext, $provider, $model, $agentClass, $fallbackFrom);
            $inputTokens = $this->chatInputTokens($message, $historyData, $userContext, $loggingContext);
            $reservation = $usage->start($provider, $model, 'chat', $inputTokens, 4096);
            $this->aiChatLogger->info(
                $fallbackFrom ? 'ai_chat.provider.fallback_started' : 'ai_chat.provider.started',
                $attemptContext,
            );

            $response = $provider === 'cloudflare'
                ? (new CloudflareAIService)->prompt($message, $historyData, $userContext, trackUsage: false)
                : $this->promptAgent(
                    $agentClass,
                    $history,
                    $userContext,
                    $message,
                    $provider,
                    $model,
                    $attachments,
                    $this->sessionIdFrom($loggingContext),
                );

            $usage->complete(
                $reservation,
                $provider,
                $model,
                'chat',
                $inputTokens,
                AiUsageTracker::tokensFromChars(strlen($response)),
            );
            $this->logCompletedResponse($attemptContext, $response, $startedAt);

            return $response;
        } catch (Throwable $failure) {
            $usage->cancel($reservation, $failure->getMessage());
            $this->aiChatLogger->error('ai_chat.provider.failed', $failure, [
                ...$attemptContext,
                'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                'fallback_enabled' => app(AiProviderFallbackPolicy::class)->fallbackFor($provider, $failure) !== null,
            ]);

            throw $failure;
        }
    }

    /**
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, array<string, mixed>>  $historyData
     * @param  array<int, File>  $attachments
     */
    private function streamWithProvider(
        string $provider,
        string $agentClass,
        array $history,
        array $historyData,
        string $userContext,
        string $message,
        array $attachments,
        array $loggingContext,
        ?string $fallbackFrom = null,
    ): StreamableAgentResponse {
        // Cloudflare has no native streaming or tool calling. Execute its
        // budgeted synchronous request and expose the text as one SSE delta.
        if ($provider === 'cloudflare') {
            return $this->streamText($this->promptWithProvider(
                $provider,
                $agentClass,
                $history,
                $historyData,
                $userContext,
                $message,
                $attachments,
                $loggingContext,
                $fallbackFrom,
            ));
        }

        $startedAt = hrtime(true);
        $attemptContext = $this->providerContext($loggingContext, $provider, null, $agentClass, $fallbackFrom);
        $usage = app(AiUsageTracker::class);
        $reservation = null;

        try {
            $model = $this->prepareProvider($provider);
            $attemptContext = $this->providerContext($loggingContext, $provider, $model, $agentClass, $fallbackFrom);
            $inputTokens = $this->chatInputTokens($message, $historyData, $userContext, $loggingContext);
            $reservation = $usage->start($provider, $model, 'chat', $inputTokens, 4096);
            $this->aiChatLogger->info(
                $fallbackFrom ? 'ai_chat.provider.stream_fallback_started' : 'ai_chat.provider.stream_started',
                $attemptContext,
            );

            return $this->streamAgent(
                $agentClass,
                $history,
                $userContext,
                $message,
                $provider,
                (string) $model,
                $attachments,
                $attemptContext,
                $startedAt,
                $reservation,
                $inputTokens,
            );
        } catch (Throwable $failure) {
            $usage->cancel($reservation, $failure->getMessage());
            $this->aiChatLogger->error('ai_chat.provider.stream_failed', $failure, [
                ...$attemptContext,
                'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                'fallback_enabled' => app(AiProviderFallbackPolicy::class)->fallbackFor($provider, $failure) !== null,
            ]);

            throw $failure;
        }
    }

    private function prepareProvider(string $provider): ?string
    {
        if (AiSdkProviderService::isRemovedCompatibleProvider($provider)) {
            throw new \RuntimeException('The selected OpenAI-compatible provider was removed. Choose another provider in Platform Settings.');
        }

        if ($provider === 'cloudflare') {
            return Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash');
        }

        if ($provider === 'groq') {
            $apiKey = Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key');
            $model = Setting::get('groq_model', 'llama-3.1-8b-instant');
            if (! $apiKey) {
                throw new \RuntimeException('Groq is not configured. Paste your API key in Platform Settings.');
            }
            config([
                'ai.providers.groq.key' => $apiKey,
                'ai.providers.groq.models.text.default' => $model,
            ]);
            app(AiManager::class)->forgetInstance('groq');

            return $model;
        }

        if ($provider === 'gemini') {
            $gemini = app(GeminiAIService::class);
            if (! $gemini->apiKey()) {
                throw new \RuntimeException('Gemini is not configured. Paste your API key in Platform Settings.');
            }
            $gemini->applyToSdk();

            return $gemini->chatModel();
        }

        if (AiSdkProviderService::isSdkRouted($provider)) {
            $sdkProvider = AiSdkProviderService::for($provider);
            if (! $sdkProvider->isConfigured()) {
                throw new \RuntimeException("{$provider} is not configured. Paste its credentials in Platform Settings.");
            }
            $sdkProvider->applyToSdk();

            return $sdkProvider->model();
        }

        throw new \RuntimeException("Unsupported AI provider [{$provider}].");
    }

    /**
     * @param  array<int, array<string, mixed>>  $historyData
     * @param  array<string, mixed>  $loggingContext
     */
    private function chatInputTokens(
        string $message,
        array $historyData,
        string $userContext,
        array $loggingContext,
    ): int {
        // Reserve room for the agent/system instructions in addition to the
        // bounded conversation context and current user message.
        $characters = 6000 + strlen($message) + strlen($userContext);
        foreach ($historyData as $historyMessage) {
            $characters += strlen((string) ($historyMessage['content'] ?? ''));
        }

        // Documents are conservatively estimated from bytes; image token use
        // depends on provider resolution, so reserve roughly 1,000 tokens per
        // image instead of treating compressed image bytes as plain text.
        foreach ((array) ($loggingContext['attachments'] ?? []) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $characters += ($attachment['kind'] ?? null) === 'image'
                ? 4000
                : min(400000, max(0, (int) ($attachment['size'] ?? 0)));
        }

        return AiUsageTracker::tokensFromChars($characters);
    }

    /**
     * Run the role-resolved agent through the Laravel AI SDK and return its
     * text response.
     *
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, File>  $attachments
     */
    private function promptAgent(string $agentClass, array $history, string $userContext, string $message, string $provider, ?string $model = null, array $attachments = [], ?int $chatSessionId = null): string
    {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);
        if ($agent instanceof AdminAssistantAgent) {
            $agent->setChatSessionId($chatSessionId);
        }

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
    private function streamAgent(
        string $agentClass,
        array $history,
        string $userContext,
        string $message,
        string $provider,
        string $model,
        array $attachments = [],
        array $loggingContext = [],
        ?int $startedAt = null,
        ?AiBudgetReservation $reservation = null,
        ?int $inputTokens = null,
    ): StreamableAgentResponse {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);
        if ($agent instanceof AdminAssistantAgent) {
            $agent->setChatSessionId($this->sessionIdFrom($loggingContext));
        }

        $inputTokens ??= AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext));
        $usage = app(AiUsageTracker::class);
        $stream = $agent
            ->stream($message, attachments: $attachments, provider: $provider, model: $model)
            ->then(function ($response) use ($provider, $model, $inputTokens, $loggingContext, $startedAt, $reservation, $usage) {
                $usage->complete(
                    $reservation,
                    $provider,
                    $model,
                    'chat',
                    $inputTokens,
                    AiUsageTracker::tokensFromChars(strlen((string) $response->text)),
                );

                $this->logCompletedResponse($loggingContext, (string) $response->text, $startedAt ?? hrtime(true), true);
            });

        return $this->logStreamErrors(
            $stream,
            $loggingContext,
            $startedAt ?? hrtime(true),
            $reservation,
            $usage,
        );
    }

    /** @param array<string, mixed> $loggingContext */
    private function sessionIdFrom(array $loggingContext): ?int
    {
        $sessionId = (int) ($loggingContext['session_id'] ?? 0);

        return $sessionId > 0 ? $sessionId : null;
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
    private function logStreamErrors(
        StreamableAgentResponse $stream,
        array $loggingContext,
        int $startedAt,
        ?AiBudgetReservation $reservation = null,
        ?AiUsageTracker $usage = null,
    ): StreamableAgentResponse {
        return new StreamableAgentResponse(
            (string) Str::uuid7(),
            function () use ($stream, $loggingContext, $startedAt, $reservation, $usage) {
                try {
                    yield from $stream;
                } catch (Throwable $e) {
                    $usage?->cancel($reservation, $e->getMessage());
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
