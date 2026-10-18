<?php

namespace App\Services\Concerns;

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

trait ChatServicePart1
{
    public static function tokensFromText(string $text): int
    {
        return max(1, (int) ceil(strlen($text) / 3));
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
}
