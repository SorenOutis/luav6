<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes structured, privacy-safe lifecycle records for Echo conversations.
 */
class AiChatLogger
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function interaction(Request $request, ?User $user, string $surface, string $transport, string $message, ?int $sessionId = null, array $history = [], array $attachments = []): array
    {
        return [
            'chat_id' => (string) Str::uuid7(),
            'surface' => $surface,
            'transport' => $transport,
            'route' => $request->route()?->getName(),
            'request_id' => $request->header('X-Request-ID'),
            'user_id' => $user?->id,
            'user_role' => $user ? ($user->is_admin ? 'admin' : 'student') : null,
            'session_id' => $sessionId,
            'message' => $this->textMetadata($message),
            ...$this->historyMetadata($history),
            ...$this->attachmentMetadata($attachments),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    public function withConversation(array $context, ?int $sessionId, array $history, array $attachments): array
    {
        return array_merge($context, [
            'session_id' => $sessionId,
            ...$this->historyMetadata($history),
            ...$this->attachmentMetadata($attachments),
        ]);
    }

    /**
     * @return array{length: int, sha256: string}
     */
    public function textMetadata(string $text): array
    {
        return [
            'length' => Str::length($text),
            'sha256' => hash('sha256', $text),
        ];
    }

    public function elapsedMilliseconds(int $startedAt): int
    {
        return (int) floor((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('AI chat lifecycle', array_merge($context, ['event' => $event]));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, Throwable $exception, array $context = []): void
    {
        Log::error('AI chat lifecycle', array_merge($context, [
            'event' => $event,
            'exception' => $exception,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{history_message_count: int, history_user_message_count: int, history_assistant_message_count: int}
     */
    private function historyMetadata(array $history): array
    {
        return [
            'history_message_count' => count($history),
            'history_user_message_count' => collect($history)->where('role', 'user')->count(),
            'history_assistant_message_count' => collect($history)->where('role', 'assistant')->count(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array{attachment_count: int, attachments: array<int, array{kind: mixed, mime: mixed, size: mixed}>}
     */
    private function attachmentMetadata(array $attachments): array
    {
        return [
            'attachment_count' => count($attachments),
            'attachments' => Collection::make($attachments)
                ->map(fn (array $attachment) => [
                    'kind' => $attachment['kind'] ?? null,
                    'mime' => $attachment['mime'] ?? null,
                    'size' => $attachment['size'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }
}
