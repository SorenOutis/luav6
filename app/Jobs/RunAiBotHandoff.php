<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\BotAgent;
use App\Ai\Support\BotRunContext;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AiInputGuardrailService;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use App\Services\EchoReplyGuard;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Wakes a specialist bot for one queued handoff.
 *
 * The job is idempotent: only a `queued` row is processed. Any failure marks
 * the row failed instead of leaving it `running` forever.
 */
class RunAiBotHandoff implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $handoffId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->handoffId;
    }

    public function handle(
        AiProviderConfigurator $providers,
        AiInputGuardrailService $guardrails,
        AiOutputSanitizerService $sanitizer,
        EchoReplyGuard $replyGuard,
    ): void {
        $handoff = AiBotHandoff::query()->withoutGlobalScope('workspace')->find($this->handoffId);

        if (! $handoff || $handoff->status !== AiBotHandoff::STATUS_QUEUED) {
            return;
        }

        $bot = AiBot::query()->withoutGlobalScope('workspace')->find($handoff->to_bot_id);
        $actor = $handoff->requested_by_user_id
            ? User::query()->find($handoff->requested_by_user_id)
            : null;
        $session = $handoff->chat_session_id
            ? ChatSession::query()->find($handoff->chat_session_id)
            : null;

        if (! $bot || ! $bot->is_active || $bot->isCoordinator()) {
            $this->markFailed($handoff, 'The target bot is missing, inactive, or not a specialist.');

            return;
        }

        if (! $actor?->is_admin) {
            $this->markFailed($handoff, 'The requesting administrator is missing or inactive.');

            return;
        }

        $provider = $providers->currentProvider();
        if (! $providers->supportsTools($provider)) {
            $this->markFailed($handoff, 'The active AI provider cannot run tool-using bots.');

            return;
        }

        try {
            $model = $providers->prepare($provider);
            $model = $bot->model ?: $model;

            (new BotRunContext($bot, $actor, $handoff->depth, $handoff->chat_session_id, $handoff->id))
                ->run(function () use ($handoff, $bot, $actor, $session, $provider, $model, $guardrails, $sanitizer, $replyGuard): void {
                    $handoff->update(['status' => AiBotHandoff::STATUS_RUNNING]);

                    $agent = new BotAgent(
                        $bot,
                        $actor,
                        $session,
                        [],
                        '',
                        $handoff->depth,
                        $handoff->id,
                    );

                    $task = $guardrails->wrapUntrusted((string) $handoff->task, 'Delegated Task');
                    $text = (string) $agent->prompt($task, provider: $provider, model: $model)->text;
                    $text = $sanitizer->sanitize($text);
                    $staged = $replyGuard->stagedActionForSession($actor, $session?->id);
                    $text = $replyGuard->enforce($text, $staged !== null, $replyGuard->stagedSummary($staged));

                    $handoff->update([
                        'result' => $text,
                        'status' => AiBotHandoff::STATUS_COMPLETED,
                        'error' => null,
                    ]);

                    if ($session) {
                        ChatMessage::query()->create([
                            'session_id' => $session->id,
                            'bot_id' => $bot->id,
                            'role' => 'assistant',
                            'content' => $text,
                        ]);
                        $session->touch();
                    }
                });
        } catch (Throwable $exception) {
            $this->markFailed($handoff, $exception->getMessage());
        }
    }

    private function markFailed(AiBotHandoff $handoff, string $error): void
    {
        $handoff->update([
            'status' => AiBotHandoff::STATUS_FAILED,
            'error' => mb_substr($error, 0, 2000),
        ]);
    }
}
