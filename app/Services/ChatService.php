<?php

namespace App\Services;

use App\Services\Concerns\ChatServiceReads;
use App\Services\Concerns\ChatServiceWrites;

/**
 * Shared Echo conversation pipeline used by both the floating widget
 * (ChatController) and the persisted Chats history page
 * (ChatHistoryController). Owns toxicity screening, the daily message cap,
 * user-context building, and provider routing with Ollama fallback.
 */
class ChatService
{
    use ChatServiceReads;
    use ChatServiceWrites;

    public const MAX_ATTACHMENTS = 4;

    public const MAX_MESSAGE_CHARACTERS = 8000;

    public const MAX_MESSAGE_TOKENS = 3000;

    public const MAX_ATTACHMENT_KB = 5120;

    public const MAX_CONTEXT_MESSAGES = 40;

    public const MAX_CONTEXT_CHARACTERS = 32000;

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

    public function __construct(
        private AiChatLogger $aiChatLogger,
        private ?AiInputGuardrailService $inputGuardrails = null,
        private ?AiOutputSanitizerService $outputSanitizer = null,
        private ?EchoReplyGuard $replyGuard = null,
    ) {
        $this->inputGuardrails ??= app(AiInputGuardrailService::class);
        $this->outputSanitizer ??= app(AiOutputSanitizerService::class);
        $this->replyGuard ??= app(EchoReplyGuard::class);
    }
}
