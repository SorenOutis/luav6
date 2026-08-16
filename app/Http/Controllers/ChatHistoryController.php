<?php

namespace App\Http\Controllers;

use App\Http\Responses\AiSseResponse;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Services\AiChatLogger;
use App\Services\ChatService;
use App\Support\StudentPageRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The persisted Chats history page — the ChatGPT-style UI where every
 * conversation from the Echo widget is saved and can be reopened,
 * continued, or deleted.
 */
class ChatHistoryController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
        protected AiChatLogger $aiChatLogger,
    ) {}

    private function aiChatMaintenanceMessage(): string
    {
        return Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance. Please try again later.');
    }

    /**
     * The student-page control is the only switch that closes the Chats page.
     * Disabling AI chat leaves history readable and only blocks its composer.
     */
    private function pageBlockedMessage(Request $request): ?string
    {
        if ($request->user()?->is_admin) {
            return null;
        }

        $control = StudentPageRegistry::controlFor('chats');

        return ($control['mode'] ?? null) === StudentPageRegistry::MODE_DISABLED
            ? ($control['message'] ?: 'The Chats page is currently unavailable.')
            : null;
    }

    private function composerBlockedMessage(Request $request): ?string
    {
        if (! (bool) Setting::get('ai_chat_enabled', true)) {
            return $this->aiChatMaintenanceMessage();
        }

        return $this->pageBlockedMessage($request);
    }

    public function index(Request $request)
    {
        if ($message = $this->pageBlockedMessage($request)) {
            return Inertia::render('StudentPageUnavailable', [
                'pageTitle' => 'Chats',
                'message' => $message,
            ])->toResponse($request)->setStatusCode(423);
        }

        return Inertia::render('Chats', [
            'sessions' => $this->sessionList($request->user()),
        ]);
    }

    public function show(Request $request, ChatSession $session)
    {
        if ($message = $this->pageBlockedMessage($request)) {
            return Inertia::render('StudentPageUnavailable', [
                'pageTitle' => 'Chats',
                'message' => $message,
            ])->toResponse($request)->setStatusCode(423);
        }

        $session = $this->sessionForUser($request, $session);

        return Inertia::render('Chats', [
            'sessions' => $this->sessionList($request->user()),
            'activeSession' => $this->sessionPayload($session),
        ]);
    }

    /**
     * New chat — creates an empty persisted session.
     */
    public function store(Request $request)
    {
        if ($message = $this->composerBlockedMessage($request)) {
            return response()->json([
                'response' => $message,
            ], 503);
        }

        try {
            $session = $request->user()->chatSessions()->create([
                'title' => 'New chat',
            ]);

            return response()->json([
                'session' => ['id' => $session->id],
            ]);
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Store Error', $e);

            return response()->json([
                'error' => $this->errorPayload($e, $errorId),
            ], 500);
        }
    }

    /**
     * Continue an existing conversation and persist the new exchange.
     */
    public function message(Request $request, ChatSession $session)
    {
        if ($message = $this->composerBlockedMessage($request)) {
            return response()->json([
                'response' => $message,
            ], 503);
        }

        $session = $this->sessionForUser($request, $session);

        $request->validate([
            'message' => 'required|string',
            'attachments' => ['sometimes', 'array', 'max:'.ChatService::MAX_ATTACHMENTS],
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();
        $loggingContext = $this->aiChatLogger->interaction(
            $request,
            $user,
            'history',
            'sync',
            $request->message,
            $session->id,
        );
        $this->aiChatLogger->info('ai_chat.request.received', $loggingContext);

        // ── Server-side toxicity guardrail ──
        if ($this->chatService->isToxic($request->message)) {
            $this->aiChatLogger->info('ai_chat.request.blocked', array_merge($loggingContext, [
                'blocked_reason' => 'toxicity_guardrail',
            ]));

            return response()->json([
                'response' => "I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?",
            ], 200);
        }

        // ── Student daily message cap (cost/abuse guard; admins exempt) ──
        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            $this->aiChatLogger->info('ai_chat.request.blocked', array_merge($loggingContext, [
                'blocked_reason' => 'daily_message_limit',
            ]));

            return response()->json(['response' => $blocked]);
        }

        try {
            $userContext = $this->chatService->buildUserContext();

            $historyData = $this->sessionHistory($session);

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);
            $loggingContext = $this->aiChatLogger->withConversation($loggingContext, $session->id, $historyData, $attachmentMeta);
            $this->aiChatLogger->info('ai_chat.request.dispatched', $loggingContext);

            $response = $this->chatService->prompt($request->message, $historyData, $userContext, $user, $sdkAttachments, $loggingContext);

            if (! $session->title || $session->title === 'New chat') {
                $session->update(['title' => Str::limit($request->message, 60)]);
            }

            $session->messages()->createMany([
                [
                    'role' => 'user',
                    'content' => $request->message,
                    'attachments' => $attachmentMeta,
                ],
                ['role' => 'assistant', 'content' => $response],
            ]);

            $session->touch();

            $session->load('messages');
            $this->aiChatLogger->info('ai_chat.response.persisted', array_merge($loggingContext, [
                'response' => $this->aiChatLogger->textMetadata($response),
            ]));

            return response()->json([
                'response' => $response,
                'session' => $this->sessionPayload($session),
            ]);
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Controller Error', $e, $session, $loggingContext);

            return response()->json([
                'response' => 'Sorry, something went wrong. Please try again in a moment.',
                'error' => $this->errorPayload($e, $errorId),
            ], 500);
        }
    }

    /**
     * Stream an Echo reply on an existing conversation as Server-Sent Events.
     * Persists the user turn (with attachments) up front and the assistant turn
     * once the stream completes, then returns the uniform SSE body.
     */
    public function stream(Request $request, ChatSession $session): Response
    {
        if ($message = $this->composerBlockedMessage($request)) {
            return AiSseResponse::from($this->chatService->streamText($message));
        }

        $session = $this->sessionForUser($request, $session);

        $request->validate([
            'message' => 'required|string',
            'attachments' => ['sometimes', 'array', 'max:'.ChatService::MAX_ATTACHMENTS],
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();
        $loggingContext = $this->aiChatLogger->interaction(
            $request,
            $user,
            'history',
            'stream',
            $request->message,
            $session->id,
        );
        $this->aiChatLogger->info('ai_chat.request.received', $loggingContext);

        if ($this->chatService->isToxic($request->message)) {
            $this->aiChatLogger->info('ai_chat.request.blocked', array_merge($loggingContext, [
                'blocked_reason' => 'toxicity_guardrail',
            ]));

            return AiSseResponse::from($this->chatService->streamText("I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?"));
        }

        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            $this->aiChatLogger->info('ai_chat.request.blocked', array_merge($loggingContext, [
                'blocked_reason' => 'daily_message_limit',
            ]));

            return AiSseResponse::from($this->chatService->streamText($blocked));
        }

        try {
            $userContext = $this->chatService->buildUserContext();
            $historyData = $this->sessionHistory($session);
            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);
            $loggingContext = $this->aiChatLogger->withConversation($loggingContext, $session->id, $historyData, $attachmentMeta);
            $this->aiChatLogger->info('ai_chat.request.dispatched', $loggingContext);

            if (! $session->title || $session->title === 'New chat') {
                $session->update(['title' => Str::limit($request->message, 60)]);
            }

            $session->messages()->create([
                'role' => 'user',
                'content' => $request->message,
                'attachments' => $attachmentMeta,
            ]);
            $session->touch();

            $nativeStream = $this->chatService->stream(
                $request->message,
                $historyData,
                $userContext,
                $user,
                $sdkAttachments,
                $loggingContext,
            );

            // StreamableAgentResponse is lazy: provider exceptions can happen
            // after Laravel has already sent the HTTP 200/SSE headers. Wrap the
            // SDK stream so an early runtime failure (or an empty text stream)
            // can transparently fall back to the regular prompt path while the
            // same request is still open. This avoids the browser seeing a 200
            // response with an unusable/empty body and also avoids persisting a
            // duplicate user message through a second HTTP fallback request.
            $response = new StreamableAgentResponse(
                (string) Str::uuid7(),
                function () use ($nativeStream, $request, $historyData, $userContext, $user, $sdkAttachments, $session, $loggingContext) {
                    $emittedText = false;

                    try {
                        foreach ($nativeStream as $event) {
                            if ($event instanceof TextDelta && trim($event->delta) !== '') {
                                $emittedText = true;
                            }

                            yield $event;
                        }
                    } catch (Throwable $e) {
                        $this->logError('Chat History Stream Runtime Error', $e, $session, $loggingContext);

                        // If text was already delivered, do not append a second
                        // complete answer. The partial answer is still useful and
                        // the runtime error remains available through its log id.
                        if ($emittedText) {
                            return;
                        }
                    }

                    if ($emittedText) {
                        return;
                    }

                    try {
                        $fallbackText = $this->chatService->prompt(
                            $request->message,
                            $historyData,
                            $userContext,
                            $user,
                            $sdkAttachments,
                            $loggingContext,
                        );

                        foreach ($this->chatService->streamText($fallbackText) as $event) {
                            yield $event;
                        }
                    } catch (Throwable $fallbackError) {
                        $errorId = $this->logError('Chat History Stream Fallback Error', $fallbackError, $session, $loggingContext);
                        $message = 'Sorry, something went wrong. Please try again in a moment.';

                        if ($request->user()?->is_admin || config('app.debug')) {
                            $message .= " (Reference: {$errorId})";
                            if (config('app.debug')) {
                                $message .= ' — '.$fallbackError->getMessage();
                            }
                        }

                        foreach ($this->chatService->streamText($message) as $event) {
                            yield $event;
                        }
                    }
                },
                new Meta,
            );

            $response->then(function ($streamedResponse) use ($session, $loggingContext) {
                try {
                    $text = (string) $streamedResponse->text;

                    if (trim($text) !== '') {
                        $thinking = $this->chatService->combineReasoning($streamedResponse->events);

                        $session->messages()->create([
                            'role' => 'assistant',
                            'content' => $text,
                            'thinking' => $thinking !== '' ? $thinking : null,
                        ]);
                        $session->touch();
                    }

                    $this->aiChatLogger->info('ai_chat.response.persisted', array_merge($loggingContext, [
                        'response' => $this->aiChatLogger->textMetadata($text),
                    ]));
                } catch (Throwable $e) {
                    $this->logError('Chat History Stream Persist Error', $e, $session, $loggingContext);
                }
            });

            return AiSseResponse::from($response);
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Stream Error', $e, $session, $loggingContext);
            $payload = $this->errorPayload($e, $errorId);

            $message = 'Sorry, something went wrong. Please try again in a moment.';

            if ($request->user()?->is_admin || config('app.debug')) {
                $message .= " (Reference: {$payload['id']})";
                if ($payload['message'] !== 'An unexpected error occurred.') {
                    $message .= " — {$payload['message']}";
                }
            }

            return AiSseResponse::from($this->chatService->streamText($message));
        }
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function sessionHistory(ChatSession $session): array
    {
        return $session->messages
            ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
            ->all();
    }

    public function destroy(Request $request, ChatSession $session)
    {
        if ($message = $this->pageBlockedMessage($request)) {
            return response()->json([
                'response' => $message,
            ], 503);
        }

        $session = $this->sessionForUser($request, $session);

        try {
            $session->delete();

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Destroy Error', $e, $session);

            return response()->json([
                'error' => $this->errorPayload($e, $errorId),
            ], 500);
        }
    }

    private function sessionForUser(Request $request, ChatSession $session): ChatSession
    {
        if ($session->user_id !== $request->user()->id) {
            abort(404);
        }

        return $session;
    }

    /**
     * Log a thrown exception with full diagnostics and return a correlation id
     * that the frontend/support can use to find this exact failure in the logs.
     *
     * The exception object is passed as the `exception` context key so Monolog
     * renders the full class, message and stack trace automatically.
     */
    private function logError(string $context, Throwable $e, ?ChatSession $session = null, array $loggingContext = []): string
    {
        $request = request();
        $errorId = Str::uuid()->toString();

        $this->aiChatLogger->error('ai_chat.request.failed', $e, array_merge($loggingContext, [
            'error_id' => $errorId,
            'user_id' => $request->user()?->id,
            'session_id' => $session?->id,
            'failure_stage' => $context,
        ]));

        return $errorId;
    }

    /**
     * Build the client-facing error structure. The correlation `id` is always
     * returned so failures can be referenced; the exception class and raw
     * message are only exposed when APP_DEBUG is enabled, never to students.
     *
     * @return array{id: string, type: string|null, message: string}
     */
    private function errorPayload(Throwable $e, string $errorId): array
    {
        return [
            'id' => $errorId,
            'type' => config('app.debug') ? $e::class : null,
            'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessionList($user): array
    {
        return $user->chatSessions()
            ->with('messages')
            ->get()
            ->map(fn (ChatSession $session) => [
                'id' => $session->id,
                'title' => $session->title ?? 'New chat',
                'source' => $session->source,
                'messageCount' => $session->messages->count(),
                'lastMessage' => ($last = $session->messages->last()) ? $last->content : null,
                'updatedAt' => $session->updated_at?->toIso8601String(),
                'updatedAtHuman' => $session->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(ChatSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title ?? 'New chat',
            'source' => $session->source,
            'messages' => $session->messages->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'thinking' => $msg->thinking,
                'attachments' => $msg->attachments,
                'createdAt' => $msg->created_at?->toIso8601String(),
            ])->values()->all(),
            'updatedAt' => $session->updated_at?->toIso8601String(),
        ];
    }
}
