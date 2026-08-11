<?php

namespace App\Http\Controllers;

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
        $session = $request->user()->chatSessions()->create([
            'title' => 'New chat',
        ]);

        return response()->json([
            'session' => ['id' => $session->id],
        ]);
    }

    /**
     * Continue an existing conversation and persist the new exchange.
     */
    public function message(Request $request, ChatSession $session)
    {
        $session = $this->sessionForUser($request, $session);

        $request->validate([
            'message' => 'required|string',
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

        // ── Student daily message cap (cost/abuse guard; admins exempt) ──
        if ($blocked = $this->chatService->dailyLimitMessage($user)) {
            return response()->json(['response' => $blocked]);
        }

        try {
            $userContext = $this->chatService->buildUserContext();

            $historyData = $this->sessionHistory($session);

            [$sdkAttachments, $attachmentMeta] = $this->chatService->buildAttachments($request);

            $response = $this->chatService->prompt($request->message, $historyData, $userContext, $user, $sdkAttachments);

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

            return response()->json([
                'response' => $response,
                'session' => $this->sessionPayload($session),
            ]);
        } catch (Throwable $e) {
            Log::error('Chat History Controller Error: '.$e->getMessage());

            return response()->json([
                'response' => 'Sorry, something went wrong. Please try again in a moment.',
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
                    $text = (string) $response->text;

                    if (trim($text) !== '') {
                        $session->messages()->create([
                            'role' => 'assistant',
                            'content' => $text,
                        ]);
                        $session->touch();
                    }
                });
        } catch (Throwable $e) {
            Log::error('Chat History Stream Error: '.$e->getMessage());

            return $this->chatService->streamText('Sorry, something went wrong. Please try again in a moment.');
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
        $session = $this->sessionForUser($request, $session);

        $session->delete();

        return response()->json(['ok' => true]);
    }

    private function sessionForUser(Request $request, ChatSession $session): ChatSession
    {
        if ($session->user_id !== $request->user()->id) {
            abort(404);
        }

        return $session;
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
                'attachments' => $msg->attachments,
                'createdAt' => $msg->created_at?->toIso8601String(),
            ])->values()->all(),
            'updatedAt' => $session->updated_at?->toIso8601String(),
        ];
    }
}
