<?php

namespace App\Services;

use App\Services\Concerns\ChatServicePart0;
use App\Services\Concerns\ChatServicePart1;
use App\Services\Concerns\ChatServicePart2;
use App\Services\Concerns\ChatServicePart3;
use App\Services\Concerns\ChatServicePart4;
use App\Services\Concerns\ChatServicePart5;
use App\Services\Concerns\ChatServicePart6;
use App\Services\Concerns\ChatServicePart7;

/**
 * Shared Echo conversation pipeline used by both the floating widget
 * (ChatController) and the persisted Chats history page
 * (ChatHistoryController). Owns toxicity screening, the daily message cap,
 * user-context building, and provider routing with Ollama fallback.
 */
class ChatService
{
    use ChatServicePart0;
    use ChatServicePart1;
    use ChatServicePart2;
    use ChatServicePart3;
    use ChatServicePart4;
    use ChatServicePart5;
    use ChatServicePart6;
    use ChatServicePart7;

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
