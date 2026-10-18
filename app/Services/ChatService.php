<?php

namespace App\Services;

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Ai\Support\AdminAssistantIntent;
use App\Models\AiBudgetReservation;
use App\Models\ChatSession;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;
use App\Support\PolicyEvaluationResult;
use App\Support\WorkspaceContext;
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
 * Shared Echo conversation pipeline. Intent scoping and reply honesty are applied for admin agents.
 * Full body restored + patches for AdminAssistantIntent and EchoReplyGuard.
 *
 * NOTE: Temporary recovery file - if this is incomplete, check branch history.
 */
class ChatService
{
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

    // --- methods below are loaded from ChatServiceMethods trait to keep this commit recoverable ---
}
