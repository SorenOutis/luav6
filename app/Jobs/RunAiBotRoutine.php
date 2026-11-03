<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\BotAgent;
use App\Ai\Support\BotRunContext;
use App\Models\AiBot;
use App\Models\AiBotRoutine;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use App\Services\EchoReplyGuard;
use App\Support\AiBotsGate;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one due bot routine as the bot's creator.
 *
 * A routine is unattended work, so it gets the same guardrails as a handoff:
 * tenant + actor are bound explicitly, the transcript lands in a dedicated
 * `source = routine` session, and the daily cap bounds spend per bot.
 */
class RunAiBotRoutine implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const MAX_DAILY_RUNS = 10;

    public const SOURCE_ROUTINE = 'routine';

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    public bool $failOnTimeout = true;

    public function __construct(public int $routineId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->routineId;
    }

    public function handle(
        AiProviderConfigurator $providers,
        AiOutputSanitizerService $sanitizer,
        EchoReplyGuard $replyGuard,
    ): void {
        $routine = AiBotRoutine::query()->withoutGlobalScope('workspace')->find($this->routineId);

        if (! $routine || ! $routine->is_active) {
            return;
        }

        if (! AiBotsGate::enabled()) {
            $this->markSkipped($routine, AiBotsGate::disabledMessage());

            return;
        }

        $bot = AiBot::query()->withoutGlobalScope('workspace')->find($routine->bot_id);

        if (! $bot || ! $bot->is_active) {
            $this->markSkipped($routine, 'The routine bot is missing or inactive.');

            return;
        }

        if ($bot->isCoordinator()) {
            $this->markSkipped($routine, 'Routines must target a specialist bot, not the Chief of Staff.');

            return;
        }

        $actor = $bot->created_by ? User::query()->find($bot->created_by) : null;

        if (! $actor?->is_admin) {
            $this->markSkipped($routine, 'The bot creator is missing or is no longer an administrator.');

            return;
        }

        $provider = $providers->currentProvider();
        if (! $providers->supportsTools($provider)) {
            $this->markSkipped($routine, 'The active AI provider cannot run tool-using bots.');

            return;
        }

        if ($this->runsToday($bot) >= self::MAX_DAILY_RUNS) {
            $this->markSkipped($routine, 'Daily routine limit reached for this bot.');

            return;
        }

        $routine->forceFill(['last_run_at' => now()])->save();

        try {
            $model = $providers->prepare($provider);
            $model = $bot->model ?: $model;

            (new BotRunContext($bot, $actor))
                ->run(function () use ($routine, $bot, $actor, $provider, $model, $sanitizer, $replyGuard): void {
                    $session = ChatSession::query()->create([
                        'user_id' => $actor->id,
                        'bot_id' => $bot->id,
                        'mode' => ChatSession::MODE_SOLO,
                        'source' => self::SOURCE_ROUTINE,
                        'title' => Str::limit($routine->name.' — '.now()->toDateString(), 100),
                    ]);

                    $session->messages()->create([
                        'role' => 'user',
                        'content' => $routine->prompt,
                    ]);

                    $agent = new BotAgent($bot, $actor, $session);
                    $text = (string) $agent->prompt($routine->prompt, provider: $provider, model: $model)->text;
                    $text = $sanitizer->sanitize($text);
                    $staged = $replyGuard->stagedActionForSession($actor, $session->id);
                    $text = $replyGuard->enforce($text, $staged !== null, $replyGuard->stagedSummary($staged));

                    $session->messages()->create([
                        'role' => 'assistant',
                        'bot_id' => $bot->id,
                        'content' => $text,
                    ]);
                    $session->touch();

                    $routine->update([
                        'last_status' => AiBotRoutine::STATUS_COMPLETED,
                        'last_error' => null,
                    ]);
                });
        } catch (Throwable $exception) {
            $routine->update([
                'last_status' => AiBotRoutine::STATUS_FAILED,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }

    private function runsToday(AiBot $bot): int
    {
        return ChatSession::query()
            ->where('bot_id', $bot->id)
            ->where('source', self::SOURCE_ROUTINE)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    private function markSkipped(AiBotRoutine $routine, string $reason): void
    {
        $routine->update([
            'last_run_at' => now(),
            'last_status' => AiBotRoutine::STATUS_SKIPPED,
            'last_error' => mb_substr($reason, 0, 2000),
        ]);
    }
}
