<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The persisted Chats history page — the ChatGPT-style UI where every
 * conversation from the Echo widget is saved and can be reopened,
 * continued, or deleted.
 */
class ChatHistoryController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    public function index(Request $request)
    {
        return Inertia::render('Chats', [
            'sessions' => $this->sessionList($request->user()),
        ]);
    }

    public function show(Request $request, ChatSession $session)
    {
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
        $session = $this->sessionForUser($request, $session);

        $request->validate([
            'message' => 'required|string',
            'stream_retry' => 'sometimes|boolean',
            'attachments' => ['sometimes', 'array', 'max:'.ChatService::MAX_ATTACHMENTS],
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();

        // ── Server-side toxicity guardrail ──
        if ($this->chatService->isToxic($request->message)) {
            return response()->json([
                'response' => "I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?",
            ], 200);
        }

        // If a browser accepted the SSE response but could not parse or finish
        // it, the streaming action may already have persisted this turn. Reuse
        // that work instead of charging the limit twice or duplicating rows.
        $streamRetry = $request->boolean('stream_retry')
            ? $this->streamRetry($session, $request->message)
            : null;

        if (($streamRetry['state'] ?? null) === 'completed') {
            /** @var ChatMessage $assistant */
            $assistant = $streamRetry['assistant'];
            $session->load('messages');

            return response()->json([
                'response' => $assistant->content,
                'session' => $this->sessionPayload($session),
            ]);
        }

        // ── Student daily message cap (cost/abuse guard; admins exempt) ──
        // A matched retry already consumed its slot in the streaming action.
        if ($streamRetry === null && ($blocked = $this->chatService->dailyLimitMessage($user))) {
            return response()->json(['response' => $blocked]);
        }

        try {
            $userContext = $this->chatService->buildUserContext();

            $historyData = $this->sessionHistory($session);
            if (($streamRetry['state'] ?? null) === 'pending') {
                // The current user turn is already the final history row; the
                // SDK receives it separately as the new prompt.
                array_pop($historyData);
            }

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);

            $response = $this->chatService->prompt($request->message, $historyData, $userContext, $user, $sdkAttachments);

            if (! $session->title || $session->title === 'New chat') {
                $session->update(['title' => Str::limit($request->message, 60)]);
            }

            $messagesToPersist = [];
            if (($streamRetry['state'] ?? null) !== 'pending') {
                $messagesToPersist[] = [
                    'role' => 'user',
                    'content' => $request->message,
                    'attachments' => $attachmentMeta,
                ];
            }
            $messagesToPersist[] = ['role' => 'assistant', 'content' => $response];

            $session->messages()->createMany($messagesToPersist);

            $session->touch();

            $session->load('messages');

            return response()->json([
                'response' => $response,
                'session' => $this->sessionPayload($session),
            ]);
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Controller Error', $e, $session);

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
    public function stream(Request $request, ChatSession $session): StreamableAgentResponse|Response
    {
        $session = $this->sessionForUser($request, $session);

        $request->validate([
            'message' => 'required|string',
            'attachments' => ['sometimes', 'array', 'max:'.ChatService::MAX_ATTACHMENTS],
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();

        if ($this->chatService->isToxic($request->message)) {
            return $this->chatService->streamText("I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?");
        }

        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            return $this->chatService->streamText($blocked);
        }

        try {
            $userContext = $this->chatService->buildUserContext();

            $historyData = $this->sessionHistory($session);

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);

            if (! $session->title || $session->title === 'New chat') {
                $session->update(['title' => Str::limit($request->message, 60)]);
            }

            $session->messages()->create([
                'role' => 'user',
                'content' => $request->message,
                'attachments' => $attachmentMeta,
            ]);

            $session->touch();

            return $this->chatService
                ->stream($request->message, $historyData, $userContext, $user, $sdkAttachments)
                ->then(function ($response) use ($session) {
                    try {
                        $text = (string) $response->text;

                        if (trim($text) !== '') {
                            $thinking = $this->chatService->combineReasoning($response->events);

                            $session->messages()->create([
                                'role' => 'assistant',
                                'content' => $text,
                                'thinking' => $thinking !== '' ? $thinking : null,
                            ]);
                            $session->touch();
                        }
                    } catch (Throwable $e) {
                        // Persisting the assistant turn after the stream is a
                        // separate failure from generating the reply — log it
                        // with its own correlation id so it isn't lost.
                        $this->logError('Chat History Stream Persist Error', $e, $session);
                    }
                });
        } catch (Throwable $e) {
            $errorId = $this->logError('Chat History Stream Error', $e, $session);
            $payload = $this->errorPayload($e, $errorId);

            $message = 'Sorry, something went wrong. Please try again in a moment.';

            // Surface the correlation id so the failure can be reported and
            // matched to a log line; expose the raw detail only to admins or
            // when APP_DEBUG is enabled.
            if ($request->user()?->is_admin || config('app.debug')) {
                $message .= " (Reference: {$payload['id']})";
                if ($payload['message'] !== 'An unexpected error occurred.') {
                    $message .= " — {$payload['message']}";
                }
            }

            return $this->chatService->streamText($message);
        }
    }

    /**
     * Match a JSON fallback to the turn already handled by the accepted SSE
     * request. A completed turn can be returned immediately; a pending user
     * turn can be reused while the non-streaming provider call is retried.
     *
     * @return array{state: 'completed', assistant: ChatMessage}|array{state: 'pending'}|null
     */
    private function streamRetry(ChatSession $session, string $message): ?array
    {
        $latest = $session->messages()
            ->reorder('id', 'desc')
            ->limit(2)
            ->get();

        /** @var ChatMessage|null $last */
        $last = $latest->get(0);
        if (! $last) {
            return null;
        }

        if ($last->role === 'user' && $last->content === $message) {
            return ['state' => 'pending'];
        }

        /** @var ChatMessage|null $previous */
        $previous = $latest->get(1);
        if (
            $last->role === 'assistant'
            && $previous?->role === 'user'
            && $previous->content === $message
        ) {
            return ['state' => 'completed', 'assistant' => $last];
        }

        return null;
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
    private function logError(string $context, Throwable $e, ?ChatSession $session = null): string
    {
        $request = request();
        $errorId = Str::uuid()->toString();

        Log::error($context, [
            'error_id' => $errorId,
            'exception' => $e,
            'user_id' => $request->user()?->id,
            'session_id' => $session?->id,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

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
