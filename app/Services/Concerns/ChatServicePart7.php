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

trait ChatServicePart7
{
    private function sessionIdFrom(array $loggingContext): ?int
    {
        $sessionId = (int) ($loggingContext['session_id'] ?? 0);

        return $sessionId > 0 ? $sessionId : null;
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     * @return array<string, mixed>
     */
    private function providerContext(array $loggingContext, string $provider, ?string $model, string $agentClass, ?string $fallbackFrom = null): array
    {
        return array_merge($loggingContext, [
            'provider' => $provider,
            'model' => $model,
            'agent' => class_basename($agentClass),
            'fallback_from' => $fallbackFrom,
        ]);
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     */
    private function logCompletedResponse(array $loggingContext, string $response, int $startedAt, bool $streamed = false): void
    {
        $this->aiChatLogger->info($streamed ? 'ai_chat.provider.stream_completed' : 'ai_chat.provider.completed', array_merge($loggingContext, [
            'response' => $this->aiChatLogger->textMetadata($response),
            'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
        ]));
    }

    /**
     * @param  array<string, mixed>  $loggingContext
     */
    private function logStreamErrors(
        StreamableAgentResponse $stream,
        array $loggingContext,
        int $startedAt,
        ?AiBudgetReservation $reservation = null,
        ?AiUsageTracker $usage = null,
    ): StreamableAgentResponse {
        return new StreamableAgentResponse(
            (string) Str::uuid7(),
            function () use ($stream, $loggingContext, $startedAt, $reservation, $usage) {
                try {
                    yield from $stream;
                } catch (Throwable $e) {
                    $usage?->cancel($reservation, $e->getMessage());
                    $this->aiChatLogger->error('ai_chat.provider.stream_runtime_failed', $e, array_merge($loggingContext, [
                        'duration_ms' => $this->aiChatLogger->elapsedMilliseconds($startedAt),
                    ]));

                    throw $e;
                }
            },
            new Meta,
        );
    }
}
