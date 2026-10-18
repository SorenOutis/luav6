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

trait ChatServicePart0
{
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

    /** @return array<int, mixed> */
    public function messageValidationRules(): array
    {
        return [
            'required',
            'string',
            'max:'.self::MAX_MESSAGE_CHARACTERS,
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && self::tokensFromText($value) > self::MAX_MESSAGE_TOKENS) {
                    $fail('The message is too large for one AI request. Shorten it or split it into smaller messages.');
                }
            },
        ];
    }
}
