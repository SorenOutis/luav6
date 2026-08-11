<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Setting;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ChatController extends Controller
{
    protected string $sessionKey = 'echo_chat_history';

    private string $sessionIdKey = 'echo_chat_session_id';

    public function __construct(protected ChatService $chatService) {}

    public function __invoke(Request $request)
    {
        if (! Setting::get('ai_chat_enabled', true)) {
            return response()->json([
                'response' => Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance.'),
            ], 503);
        }

        $request->validate([
            'message' => 'required|string',
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();

        // ── Server-side toxicity guardrail ──
        if ($this->chatService->isToxic($request->message)) {
            return response()->json([
                'response' => "I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?",
            ], 200);
        }

        // ── Student daily message cap (cost/abuse guard; admins exempt) ──
        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            return response()->json(['response' => $blocked]);
        }

        try {
            // Resolve which persisted conversation this message belongs to,
            // migrating any legacy session history into the first DB session.
            [$historyData, $sessionId] = $this->resolveConversation($request, $user);

            // Build user context with real data for personalization
            $userContext = $this->chatService->buildUserContext();

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);

            $response = $this->chatService->prompt($request->message, $historyData, $userContext, $user, $sdkAttachments);

            $this->persistExchange($sessionId, [
                'role' => 'user',
                'content' => $request->message,
                'attachments' => $attachmentMeta,
            ], ['role' => 'assistant', 'content' => $response]);

            $historyData[] = ['role' => 'user', 'content' => $request->message, 'attachments' => $attachmentMeta];
            $historyData[] = ['role' => 'assistant', 'content' => $response];

            return response()->json([
                'response' => $response,
                'history' => $historyData,
                'session_id' => $sessionId,
            ]);
        } catch (Throwable $e) {
            Log::error('Chat Controller Error: '.$e->getMessage());

            return response()->json([
                'response' => 'Sorry, something went wrong. Please try again in a moment.',
            ], 500);
        }
    }

    /**
     * Stream an Echo response as Server-Sent Events. Accepts the same message
     * plus optional files, resolves the conversation, persists the exchange,
     * and returns the Laravel AI SDK's streamable response (a uniform SSE body
     * even for providers that can't stream natively).
     */
    public function stream(Request $request): JsonResponse|StreamableAgentResponse|Response
    {
        if (! Setting::get('ai_chat_enabled', true)) {
            return $this->chatService->streamText(Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance.'));
        }

        $request->validate([
            'message' => 'required|string',
            'attachments' => ['sometimes', 'array', 'max:'.ChatService::MAX_ATTACHMENTS],
            'attachments.*' => $this->chatService->attachmentValidationRules(),
        ]);

        $user = $request->user();

        // ── Server-side toxicity guardrail ──
        if ($this->chatService->isToxic($request->message)) {
            return $this->chatService->streamText("I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?");
        }

        // ── Student daily message cap (cost/abuse guard; admins exempt) ──
        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            return $this->chatService->streamText($blocked);
        }

        try {
            [$historyData, $sessionId] = $this->resolveConversation($request, $user);

            $userContext = $this->chatService->buildUserContext();

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);

            // Persist the user turn up front so history reflects it even if
            // the stream is interrupted part-way through.
            $this->persistExchange($sessionId, [
                'role' => 'user',
                'content' => $request->message,
                'attachments' => $attachmentMeta,
            ]);

            $sessionId = $this->resolveSessionId($sessionId);

            return $this->chatService
                ->stream($request->message, $historyData, $userContext, $user, $sdkAttachments)
                ->then(function ($response) use ($sessionId) {
                    $text = (string) $response->text;

                    if ($sessionId && trim($text) !== '') {
                        $this->persistExchange((int) $sessionId, [
                            'role' => 'assistant',
                            'content' => $text,
                        ]);
                    }
                });
        } catch (Throwable $e) {
            Log::error('Chat Stream Error: '.$e->getMessage());

            return $this->chatService->streamText('Sorry, something went wrong. Please try again in a moment.');
        }
    }

    /**
     * Resolve the persisted session id, auto-creating one when the widget has
     * sent no explicit id yet. The non-streaming path auto-creates it inside
     * resolveConversation; streaming does the same work here so the user turn
     * is captured even when the AI call is deferred to the stream.
     */
    private function resolveSessionId(?int $sessionId): ?int
    {
        if ($sessionId) {
            return (int) $sessionId;
        }

        $user = auth()->user();

        if ($user && ! session()->has($this->sessionIdKey)) {
            $session = $user->chatSessions()->create(['title' => 'New chat']);
            session()->put($this->sessionIdKey, (int) $session->id);
            session()->save();

            return (int) $session->id;
        }

        return (int) session()->get($this->sessionIdKey);
    }

    /**
     * Find the DB conversation for this widget request.
     *
     * Priority: explicit `session_id` from the request, then the session
     * stored on a previous widget request, then the legacy PHP-session
     * history (which gets migrated into a fresh DB session).
     *
     * @return array{0: array<int, array{role: string, content: string}>, 1: ?int}
     */
    private function resolveConversation(Request $request, $user): array
    {
        $candidates = collect([$request->input('session_id'), session()->get($this->sessionIdKey)])
            ->filter()
            ->unique();

        foreach ($candidates as $candidateId) {
            $session = ChatSession::query()
                ->where('id', $candidateId)
                ->where('user_id', $user?->id)
                ->first();

            if ($session) {
                session()->put($this->sessionIdKey, (int) $session->id);
                session()->save();

                return [$this->historyData($session), (int) $session->id];
            }
        }

        // Legacy PHP-session history → migrate into a fresh DB session so it
        // becomes part of the persisted Chats history.
        $historyData = session()->get($this->sessionKey, []);
        $firstUserMessage = collect($historyData)->firstWhere('role', 'user');

        $session = $user?->chatSessions()->create([
            'title' => $firstUserMessage ? Str::limit($firstUserMessage['content'], 60) : 'New chat',
        ]);

        if ($session && ! empty($historyData)) {
            $session->messages()->createMany(array_map(
                fn (array $msg) => ['role' => $msg['role'], 'content' => $msg['content']],
                $historyData
            ));
        }

        session()->put($this->sessionIdKey, (int) ($session?->id));
        session()->save();

        return [$historyData, $session?->id];
    }

    /**
     * @return array<int, array{role: string, content: string, attachments?: array<int, array<string, mixed>>}>
     */
    private function historyData(ChatSession $session): array
    {
        return $session->messages
            ->map(fn ($msg) => collect([
                'role' => $msg->role,
                'content' => $msg->content,
            ])->when($msg->attachments, fn ($row) => $row->put('attachments', $msg->attachments))->all())
            ->all();
    }

    /**
     * Persist one or more messages into the DB session, auto-titling the
     * session from its first user message. Each message supports an optional
     * `attachments` array of serializable metadata.
     *
     * @param  array<int, array{role: string, content: string, attachments?: array<int, array<string, mixed>>}>  ...$messages
     */
    private function persistExchange(?int $sessionId, ...$messages): void
    {
        if (! $sessionId) {
            return;
        }

        $session = ChatSession::find($sessionId);

        if (! $session || $session->user_id !== auth()->id()) {
            return;
        }

        $firstUser = collect($messages)->firstWhere('role', 'user');

        if ($firstUser && (! $session->title || $session->title === 'New chat')) {
            $session->update(['title' => Str::limit($firstUser['content'], 60)]);
        }

        $session->messages()->createMany(collect($messages)->map(fn ($msg) => collect([
            'role' => $msg['role'],
            'content' => $msg['content'],
        ])->when($msg['attachments'] ?? [], fn ($row, $attachments) => $row->put('attachments', $attachments))->all())->all());
    }

    /**
     * Clear the widget conversation (the widget's "New chat" button).
     * The persisted DB session is kept — it stays in the Chats history.
     */
    public function clearHistory()
    {
        session()->forget($this->sessionKey);
        session()->forget($this->sessionIdKey);
        session()->save();

        return response()->json(['ok' => true]);
    }

    public function getHistory()
    {
        $storedId = session()->get($this->sessionIdKey);

        if ($storedId) {
            $session = ChatSession::query()
                ->where('id', $storedId)
                ->where('user_id', auth()->id())
                ->first();

            if ($session) {
                return response()->json([
                    'history' => $this->historyData($session),
                    'session_id' => (int) $session->id,
                ]);
            }
        }

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
