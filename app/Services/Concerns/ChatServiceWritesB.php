<?php

namespace App\Services\Concerns;

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Support\AdminAssistantIntent;
use App\Models\AiBudgetReservation;
use App\Models\User;
use Laravel\Ai\Files\File;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

trait ChatServiceWritesB
{
    private function chatInputTokens(
        string $message,
        array $historyData,
        string $userContext,
        array $loggingContext,
    ): int {
        $characters = 6000 + strlen($message) + strlen($userContext);
        foreach ($historyData as $historyMessage) {
            $characters += strlen((string) ($historyMessage['content'] ?? ''));
        }

        foreach ((array) ($loggingContext['attachments'] ?? []) as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $characters += ($attachment['kind'] ?? null) === 'image'
                ? 4000
                : min(400000, max(0, (int) ($attachment['size'] ?? 0)));
        }

        return AiUsageTracker::tokensFromChars($characters);
    }

    /**
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, File>  $attachments
     */
    private function promptAgent(string $agentClass, array $history, string $userContext, string $message, string $provider, ?string $model = null, array $attachments = [], ?int $chatSessionId = null): string
    {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);
        if ($agent instanceof AdminAssistantAgent) {
            $agent->setChatSessionId($chatSessionId);
            $agent->setIntentGroups(AdminAssistantIntent::detect($message));
        }

        $text = $agent->prompt($message, attachments: $attachments, provider: $provider, model: $model)->text;

        if ($agent instanceof AdminAssistantAgent) {
            $text = $this->guardAdminReply((string) $text, $chatSessionId);
        }

        return $text;
    }

    /**
     * @param  class-string  $agentClass
     * @param  array<int, mixed>  $history
     * @param  array<int, File>  $attachments
     */
    private function streamAgent(
        string $agentClass,
        array $history,
        string $userContext,
        string $message,
        string $provider,
        string $model,
        array $attachments = [],
        array $loggingContext = [],
        ?int $startedAt = null,
        ?AiBudgetReservation $reservation = null,
        ?int $inputTokens = null,
    ): StreamableAgentResponse {
        $agent = new $agentClass;
        $agent->setHistory($history);
        $agent->setUserContext($userContext);
        $chatSessionId = $this->sessionIdFrom($loggingContext);
        if ($agent instanceof AdminAssistantAgent) {
            $agent->setChatSessionId($chatSessionId);
            $agent->setIntentGroups(AdminAssistantIntent::detect($message));
        }

        $inputTokens ??= AiUsageTracker::tokensFromChars(strlen($message) + strlen($userContext));
        $usage = app(AiUsageTracker::class);
        $isAdminAgent = $agent instanceof AdminAssistantAgent;
        $stream = $agent
            ->stream($message, attachments: $attachments, provider: $provider, model: $model)
            ->then(function ($response) use ($provider, $model, $inputTokens, $loggingContext, $startedAt, $reservation, $usage, $isAdminAgent, $chatSessionId) {
                $text = (string) $response->text;
                if ($isAdminAgent) {
                    $text = $this->guardAdminReply($text, $chatSessionId);
                }
                $usage->complete(
                    $reservation,
                    $provider,
                    $model,
                    'chat',
                    $inputTokens,
                    AiUsageTracker::tokensFromChars(strlen($text)),
                );

                $this->logCompletedResponse($loggingContext, $text, $startedAt ?? hrtime(true), true);
            });

        return $this->logStreamErrors(
            $stream,
            $loggingContext,
            $startedAt ?? hrtime(true),
            $reservation,
            $usage,
        );
    }

    private function guardAdminReply(string $text, ?int $chatSessionId): string
    {
        $staged = $this->replyGuard->stagedActionForSession(auth()->user(), $chatSessionId);
        $summary = $this->replyGuard->stagedSummary($staged);

        return $this->replyGuard->enforce($text, $staged !== null, $summary);
    }

    /** @param array<string, mixed> $loggingContext */
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
