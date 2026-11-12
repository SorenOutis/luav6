<?php

declare(strict_types=1);

namespace App\Ai\Watchers;

use App\Models\AiBot;
use App\Models\AiBotFinding;
use App\Models\AiBotTeamMessage;
use App\Models\Workspace;
use App\Services\AiOutputSanitizerService;
use App\Services\AiProviderConfigurator;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs every specialist's read-only domain check for one workspace and turns
 * candidates into deduped findings, team-room reasoning, and alert emails.
 *
 * Safety rails: no check can write; the AI step only phrases text and gets
 * no tools; every external-looking action stays a suggestion that needs a
 * human approval. Heartbeats ("all clear") post at most once per bot per day
 * so a quiet workspace stays quiet.
 */
class AiBotWatcherService
{
    public function __construct(
        private readonly AiProviderConfigurator $providers,
        private readonly AiOutputSanitizerService $sanitizer,
    ) {}

    /**
     * @return list<AiBotFinding> findings created, reopened, or escalated this run
     */
    public function runForWorkspace(Workspace $workspace): array
    {
        $now = now();
        $alertable = [];

        $bots = AiBot::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('kind', AiBot::KIND_SPECIALIST)
            ->orderBy('id')
            ->get();

        foreach ($bots as $bot) {
            $check = BotWatcherRegistry::checkFor($bot);

            if (! $check) {
                continue;
            }

            try {
                foreach ($this->runForBot($bot, $workspace, $check, $now) as $finding) {
                    $alertable[] = $finding;
                }
            } catch (Throwable $exception) {
                // One broken domain check never aborts the rest of the roster
                // or the alert email; the next cadence retries the domain.
                Log::error('Bot watcher check failed', [
                    'workspace_id' => $workspace->id,
                    'bot_id' => $bot->id,
                    'domain' => $check->domain(),
                    'error' => Str::limit($exception->getMessage(), 500, ''),
                ]);
            }
        }

        return $alertable;
    }

    /**
     * One bot against one domain check.
     *
     * @return list<AiBotFinding> findings that may warrant an email
     */
    private function runForBot(AiBot $bot, Workspace $workspace, BotWatcherCheck $check, CarbonInterface $now): array
    {
        $candidates = $check->run($bot, $workspace);
        $outcome = $this->upsertFindings($bot, $check->domain(), $candidates, $now);
        $alertable = $outcome['alertable'];

        if ($outcome['announced']->isNotEmpty()) {
            $reasoning = $this->reasoningText($bot, $workspace, $outcome['announced']);

            foreach ($outcome['announced'] as $finding) {
                $this->postMessage(
                    $bot,
                    $this->messageKind($finding),
                    $reasoning->get((int) $finding->getKey()),
                    $finding,
                );
            }
        } elseif ($outcome['cleared']->isNotEmpty()) {
            $titles = $outcome['cleared']
                ->map(fn (AiBotFinding $finding): string => $finding->title)
                ->unique()
                ->take(5)
                ->implode(' / ');

            $this->postMessage(
                $bot,
                AiBotTeamMessage::KIND_OBSERVATION,
                "Situation normal again: {$titles}. I re-checked my domain and it's clean, so I will not take any action.",
                null,
            );
        } elseif ($candidates === []) {
            $this->maybePostAllClear($bot, $check);
        }

        return $alertable;
    }

    /**
     * Diff this run's candidates against stored findings; sweep conditions
     * that no longer hold open.
     *
     * @param  list<BotFinding>  $candidates
     * @return array{announced: Collection<int, AiBotFinding>, alertable: list<AiBotFinding>, cleared: list<AiBotFinding>}
     */
    private function upsertFindings(AiBot $bot, string $domain, array $candidates, CarbonInterface $now): array
    {
        $stored = AiBotFinding::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $bot->workspace_id)
            ->where('bot_id', $bot->id)
            ->get()
            ->keyBy('fingerprint');

        $dismissed = collect($candidates)
            ->map(fn (BotFinding $candidate): ?AiBotFinding => $stored->get($candidate->fingerprint))
            ->filter(fn (?AiBotFinding $finding): bool => $finding?->status === AiBotFinding::STATUS_DISMISSED)
            ->keyBy('fingerprint');

        $announced = collect();
        $alertable = [];
        $cleared = collect();
        $seen = [];

        foreach ($candidates as $candidate) {
            // An admin who dismissed a finding means "stop telling me"; the
            // condition refreshing silently is fine, resurrecting it is not.
            if ($dismissed->has($candidate->fingerprint)) {
                continue;
            }

            $seen[] = $candidate->fingerprint;
            $existing = $stored->get($candidate->fingerprint);

            if ($existing) {
                $escalated = $candidate->severity !== $existing->severity;
                $reopened = $existing->status === AiBotFinding::STATUS_RESOLVED;

                $existing->forceFill([
                    'severity' => $candidate->severity,
                    'title' => $candidate->title,
                    'summary' => $candidate->summary,
                    'evidence' => $candidate->evidence,
                    'suggested_action' => $candidate->suggestedAction,
                    'times_observed' => $existing->times_observed + 1,
                    'last_seen_at' => $now,
                ])->save();

                if ($reopened) {
                    $existing->forceFill(['status' => AiBotFinding::STATUS_OPEN])->save();
                }

                $announce = $reopened || $escalated;

                if ($announce) {
                    $announced->push($existing);
                }

                if ($announce && $this->isAlertWorthy($existing)) {
                    $alertable[] = $existing;
                }

                continue;
            }

            $created = AiBotFinding::query()->withoutGlobalScope('workspace')->create([
                'workspace_id' => $bot->workspace_id,
                'bot_id' => $bot->id,
                'domain' => $domain,
                'severity' => $candidate->severity,
                'fingerprint' => $candidate->fingerprint,
                'title' => $candidate->title,
                'summary' => $candidate->summary,
                'evidence' => $candidate->evidence,
                'suggested_action' => $candidate->suggestedAction,
                'status' => AiBotFinding::STATUS_OPEN,
                'times_observed' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            $announced->push($created);

            if ($this->isAlertWorthy($created)) {
                $alertable[] = $created;
            }
        }

        foreach ($stored as $finding) {
            if (
                ! in_array($finding->fingerprint, $seen, true)
                && $finding->status === AiBotFinding::STATUS_OPEN
            ) {
                $finding->forceFill(['status' => AiBotFinding::STATUS_RESOLVED])->save();
                $cleared->push($finding);
            }
        }

        return [
            'announced' => $announced->keyBy(fn (AiBotFinding $finding): int => (int) $finding->getKey()),
            'alertable' => $alertable,
            'cleared' => $cleared,
        ];
    }

    private function isAlertWorthy(AiBotFinding $finding): bool
    {
        return $finding->severity !== AiBotFinding::SEVERITY_INFO && filled($finding->suggested_action);
    }

    /**
     * Ask the bot to phrase its own reasoning. No tools are mounted; any
     * provider failure degrades to deterministic text and is swallowed.
     *
     * @param  Collection<int, AiBotFinding>  $findings
     * @return Collection<int, string|null> reasoning keyed by finding id
     */
    private function reasoningText(AiBot $bot, Workspace $workspace, Collection $findings): Collection
    {
        return $findings->mapWithKeys(function (AiBotFinding $finding) use ($bot, $workspace): array {
            $directive = sprintf(
                'Workspace: %s. Your %s domain check reported: %s (severity: %s). Evidence: %s. Suggested action on file: %s. Write your team-room note now.',
                $workspace->name ?: 'this workspace',
                $finding->domain,
                $finding->title,
                $finding->severity,
                implode(' | ', (array) ($finding->evidence ?? [])),
                $finding->suggested_action ?? 'none recorded',
            );

            try {
                $provider = $this->providers->currentProvider();
                $model = $this->providers->prepare($provider);
                $model = $bot->model ?: $model;

                $text = (new WatcherThinker($bot, $directive))
                    ->prompt($directive, provider: $provider, model: $model)
                    ->text;

                $text = trim($this->sanitizer->sanitize((string) $text));

                return [(int) $finding->getKey() => $text !== '' ? $text : null];
            } catch (Throwable) {
                return [(int) $finding->getKey() => null];
            }
        });
    }

    private function messageKind(AiBotFinding $finding): string
    {
        return $finding->severity === AiBotFinding::SEVERITY_INFO
            ? AiBotTeamMessage::KIND_OBSERVATION
            : AiBotTeamMessage::KIND_SUGGESTION;
    }

    private function postMessage(AiBot $bot, string $kind, ?string $reasoning, ?AiBotFinding $finding): void
    {
        AiBotTeamMessage::query()->create([
            'workspace_id' => $bot->workspace_id,
            'bot_id' => $bot->id,
            'kind' => $kind,
            'severity' => $finding?->severity,
            'body' => $reasoning ?: $this->deterministicBody($finding, $kind),
            'finding_id' => $finding?->getKey(),
        ]);
    }

    private function deterministicBody(?AiBotFinding $finding, string $kind): string
    {
        if ($finding === null) {
            return 'Domain re-checked: everything is back to normal. No action needed.';
        }

        if ($kind === AiBotTeamMessage::KIND_SUGGESTION) {
            return sprintf(
                'I found: %s. %s Evidence: %s My suggestion: %s '
                .'I will not post, send, or change anything before you approve it.',
                $finding->title,
                $finding->summary,
                implode(' ', (array) ($finding->evidence ?? [])),
                $finding->suggested_action ?? 'no specific action yet',
            );
        }

        return sprintf('%s %s I will keep watching; approvals stay with you.', $finding->title, $finding->summary);
    }

    private function maybePostAllClear(AiBot $bot, BotWatcherCheck $check): void
    {
        $alreadyPosted = AiBotTeamMessage::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $bot->workspace_id)
            ->where('bot_id', $bot->id)
            ->where('kind', AiBotTeamMessage::KIND_ALL_CLEAR)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        AiBotTeamMessage::query()->create([
            'workspace_id' => $bot->workspace_id,
            'bot_id' => $bot->id,
            'kind' => AiBotTeamMessage::KIND_ALL_CLEAR,
            'severity' => null,
            'body' => sprintf(
                'Routine check of my domain (%s) is done: nothing needs attention right now. '
                .'I check every 30 minutes and will report here if something needs you.',
                $check->domain(),
            ),
            'finding_id' => null,
        ]);
    }
}
