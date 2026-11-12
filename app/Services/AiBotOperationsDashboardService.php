<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RunAiBotRoutine;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\AiBotPolicyRevision;
use App\Models\AiBotRoutine;
use App\Models\AiBotTeamMessage;
use App\Models\AiUsageLog;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\PendingAiAction;
use App\Models\Workspace;
use App\Support\AiBotsGate;
use App\Support\AiBotWatchers;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only data source for the super-admin Bot Operations dashboard.
 *
 * The dashboard is intentionally assembled in one place so the Blade view
 * never runs queries. Every list is capped and every write table is only read.
 */
class AiBotOperationsDashboardService
{
    public const ALL_WORKSPACES = 'all';

    public const PERIOD_TODAY = 'today';

    public const PERIOD_7_DAYS = '7d';

    public const PERIOD_30_DAYS = '30d';

    public const ACTIVITY_ALL = 'all';

    public const ACTIVITY_MESSAGES = 'messages';

    public const ACTIVITY_HANDOFFS = 'handoffs';

    public const ACTIVITY_ROUTINES = 'routines';

    public const ACTIVITY_APPROVALS = 'approvals';

    public const ACTIVITY_POLICIES = 'policies';

    public const STALE_HANDOFF_MINUTES = 15;

    public const LIVE_SESSION_MINUTES = 15;

    public const APPROVAL_EXPIRY_WINDOW_HOURS = 24;

    public const TEAM_ROOM_ITEMS = 30;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(
        string $workspace = '',
        string $period = self::PERIOD_7_DAYS,
        string $activityType = self::ACTIVITY_ALL,
        ?int $selectedBotId = null,
    ): array {
        $period = $this->normalizePeriod($period);
        $activityType = $this->normalizeActivityType($activityType);
        $range = $this->periodRange($period);
        $workspaceFilter = $this->resolveWorkspace($workspace);
        $workspaceId = $workspaceFilter['id'];

        $bots = $this->bots($workspaceId, $range['from']);
        $botIds = $bots->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $botMap = $bots->keyBy(fn (AiBot $bot): int => (int) $bot->id);

        $usageByBot = $this->usageByBot($botIds, $range['from']);
        $routineRunsToday = $this->routineRunsToday($botIds);

        $botCards = $bots
            ->map(fn (AiBot $bot): array => $this->botCard(
                $bot,
                $usageByBot->get((int) $bot->id, self::emptyUsage()),
                (int) $routineRunsToday->get((int) $bot->id, 0),
                $range['from'],
            ))
            ->values();

        return [
            'generated_at' => now(),
            'period' => [
                'key' => $period,
                'label' => $this->periodOptions()[$period],
                'from' => $range['from'],
                'to' => $range['to'],
                'options' => $this->periodOptions(),
            ],
            'activity_types' => $this->activityTypeOptions(),
            'workspace' => $workspaceFilter,
            'workspace_options' => $this->workspaceOptions($workspaceFilter),
            'kpis' => $this->kpis($bots, $workspaceId, $botIds, $range['from'], $usageByBot),
            'workspaces' => $workspaceFilter['is_all']
                ? $this->workspaceSummaries($bots, $range['from'], $botMap)
                : [],
            'team' => [
                'coordinator' => $botCards->firstWhere('is_coordinator', true),
                'specialists' => $botCards->where('is_coordinator', false)->values()->all(),
            ],
            'activity' => [
                'selected' => $activityType,
                'items' => $this->activityFeed($workspaceId, $botIds, $range['from'], $activityType),
            ],
            'handoffs' => $this->handoffSection($workspaceId, $range['from'], $botMap),
            'routines' => $this->routineSection($workspaceId, $routineRunsToday),
            'approvals' => $this->approvalSection($workspaceId, $range['from']),
            'sessions' => $this->sessionSection($workspaceId, $range['from'], $botMap),
            'usage' => $this->usageSection($botCards, $usageByBot),
            'health' => $this->healthWarnings($bots, $botCards, $workspaceId, $range['from']),
            'team_room' => $this->teamRoom($workspaceId),
            'selected_bot' => $selectedBotId
                ? $this->botDetail($selectedBotId, $botMap, $botCards, $range['from'])
                : null,
        ];
    }

    /** @return array<string, string> */
    public function periodOptions(): array
    {
        return [
            self::PERIOD_TODAY => 'Today',
            self::PERIOD_7_DAYS => 'Last 7 days',
            self::PERIOD_30_DAYS => 'Last 30 days',
        ];
    }

    /** @return array<string, string> */
    public function activityTypeOptions(): array
    {
        return [
            self::ACTIVITY_ALL => 'Everything',
            self::ACTIVITY_MESSAGES => 'Messages',
            self::ACTIVITY_HANDOFFS => 'Handoffs',
            self::ACTIVITY_ROUTINES => 'Routines',
            self::ACTIVITY_APPROVALS => 'Approvals',
            self::ACTIVITY_POLICIES => 'Policy changes',
        ];
    }

    private function normalizePeriod(string $period): string
    {
        return array_key_exists($period, $this->periodOptions())
            ? $period
            : self::PERIOD_7_DAYS;
    }

    private function normalizeActivityType(string $activityType): string
    {
        return array_key_exists($activityType, $this->activityTypeOptions())
            ? $activityType
            : self::ACTIVITY_ALL;
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    private function periodRange(string $period): array
    {
        $to = now();

        $from = match ($period) {
            self::PERIOD_TODAY => $to->startOfDay(),
            self::PERIOD_30_DAYS => $to->startOfDay()->subDays(29),
            default => $to->startOfDay()->subDays(6),
        };

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @return array{id: int|null, value: string, is_all: bool, is_current: bool, name: string}
     */
    private function resolveWorkspace(string $workspace): array
    {
        if ($workspace === self::ALL_WORKSPACES) {
            return [
                'id' => null,
                'value' => self::ALL_WORKSPACES,
                'is_all' => true,
                'is_current' => false,
                'name' => 'All workspaces',
            ];
        }

        $currentId = $this->workspaceContext->id();
        $requestedId = ctype_digit($workspace) ? (int) $workspace : null;
        $candidateId = $requestedId ?: $currentId;

        $candidate = $candidateId
            ? Workspace::query()
                ->whereKey($candidateId)
                ->whereNull('archived_at')
                ->first(['id', 'name'])
            : null;

        if (! $candidate && $currentId && $currentId !== $candidateId) {
            $candidate = Workspace::query()
                ->whereKey($currentId)
                ->whereNull('archived_at')
                ->first(['id', 'name']);
            $requestedId = null;
        }

        if (! $candidate) {
            return [
                'id' => null,
                'value' => self::ALL_WORKSPACES,
                'is_all' => true,
                'is_current' => false,
                'name' => 'All workspaces',
            ];
        }

        return [
            'id' => (int) $candidate->id,
            'value' => (string) $candidate->id,
            'is_all' => false,
            'is_current' => $requestedId === null,
            'name' => $candidate->name,
        ];
    }

    /**
     * @param  array{id: int|null, value: string, is_all: bool, is_current: bool, name: string}  $workspaceFilter
     * @return list<array{value: string, label: string}>
     */
    private function workspaceOptions(array $workspaceFilter): array
    {
        $currentLabel = $workspaceFilter['is_all']
            ? 'Current workspace'
            : 'Current workspace — '.$workspaceFilter['name'];

        $options = [
            ['value' => '', 'label' => $currentLabel],
            ['value' => self::ALL_WORKSPACES, 'label' => 'All workspaces'],
        ];

        Workspace::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (Workspace $workspace) use (&$options, $workspaceFilter): void {
                if ((int) $workspace->id === $workspaceFilter['id']) {
                    return;
                }

                $options[] = [
                    'value' => (string) $workspace->id,
                    'label' => $workspace->name,
                ];
            });

        return $options;
    }

    /**
     * @return Collection<int, AiBot>
     */
    private function bots(?int $workspaceId, CarbonImmutable $from): Collection
    {
        return AiBot::query()
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->with([
                'workspace:id,name',
                'routines' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount([
                'handoffsTo as open_handoffs_to_count' => fn (Builder $query): Builder => $query->pending(),
                'handoffsFrom as open_handoffs_from_count' => fn (Builder $query): Builder => $query->pending(),
                'messages as period_message_count' => fn (Builder $query): Builder => $query
                    ->where('role', 'assistant')
                    ->where('created_at', '>=', $from),
            ])
            ->withMax('messages as last_message_at', 'created_at')
            ->withMax('handoffsFrom as last_handoff_from_at', 'created_at')
            ->withMax('handoffsTo as last_handoff_to_at', 'created_at')
            ->orderByRaw('CASE WHEN kind = ? THEN 0 ELSE 1 END', [AiBot::KIND_COORDINATOR])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{requests: int, tokens: int, cost_micros: int}  $usage
     * @return array<string, mixed>
     */
    private function botCard(AiBot $bot, array $usage, int $runsToday, CarbonImmutable $from): array
    {
        $lastRoutine = $bot->routines
            ->filter(fn (AiBotRoutine $routine): bool => $routine->last_run_at !== null)
            ->sortByDesc(fn (AiBotRoutine $routine): int => $routine->last_run_at->getTimestamp())
            ->first();

        $nextRoutine = $bot->routines
            ->filter(fn (AiBotRoutine $routine): bool => $routine->is_active)
            ->map(fn (AiBotRoutine $routine): array => [
                'routine' => $routine,
                'next_run_at' => $this->nextRunAt($routine->cron),
            ])
            ->filter(fn (array $entry): bool => $entry['next_run_at'] !== null)
            ->sortBy(fn (array $entry): int => $entry['next_run_at']->getTimestamp())
            ->first();

        $lastActivity = collect([
            $this->toCarbon($bot->last_message_at),
            $this->toCarbon($bot->last_handoff_from_at),
            $this->toCarbon($bot->last_handoff_to_at),
            $lastRoutine?->last_run_at,
        ])
            ->filter()
            ->sortByDesc(fn (CarbonImmutable $date): int => $date->getTimestamp())
            ->first();

        $periodMessages = (int) $bot->period_message_count;

        return [
            'id' => (int) $bot->id,
            'name' => $bot->name,
            'title' => $bot->title ?: ($bot->policy['identity']['role'] ?? null),
            'avatar' => $bot->avatar,
            'initials' => Str::upper(Str::substr($bot->name, 0, 1)),
            'kind' => $bot->kind,
            'kind_label' => $bot->isCoordinator() ? 'Coordinator' : 'Specialist',
            'is_coordinator' => $bot->isCoordinator(),
            'is_active' => (bool) $bot->is_active,
            'workspace_name' => $bot->workspace?->name ?? 'Platform',
            'model' => $bot->model,
            'model_label' => $bot->model ?: 'Platform default',
            'tools_count' => count($bot->allowed_tools),
            'memory_scope' => $bot->memory_scope === AiBot::MEMORY_BOT ? 'Bot memory' : 'Session memory',
            'period_messages' => $periodMessages,
            'open_handoffs' => (int) $bot->open_handoffs_to_count,
            'delegating_handoffs' => (int) $bot->open_handoffs_from_count,
            'last_activity_at' => $lastActivity,
            'last_activity_label' => $lastActivity?->diffForHumans() ?? 'No activity yet',
            'has_period_activity' => $periodMessages > 0 || ($lastActivity && $lastActivity->gte($from)),
            'drift_warnings' => $bot->driftWarnings(),
            'next_routine_name' => $nextRoutine['routine']->name ?? null,
            'next_routine_at' => $nextRoutine['next_run_at'] ?? null,
            'next_routine_label' => ($nextRoutine['next_run_at'] ?? null)?->diffForHumans() ?? 'No routine scheduled',
            'last_routine_status' => $lastRoutine?->last_status,
            'last_routine_label' => $lastRoutine?->last_run_at?->diffForHumans() ?? 'Never run',
            'last_routine_error' => $lastRoutine?->last_error
                ? $this->excerpt($lastRoutine->last_error, 140)
                : null,
            'runs_today' => $runsToday,
            'max_daily_runs' => RunAiBotRoutine::MAX_DAILY_RUNS,
            'usage_tokens' => $usage['tokens'],
            'usage_cost_micros' => $usage['cost_micros'],
            'usage_requests' => $usage['requests'],
        ];
    }

    /**
     * @param  array<int, int>  $botIds
     * @return Collection<int, array{requests: int, tokens: int, cost_micros: int}>
     */
    private function usageByBot(array $botIds, CarbonImmutable $from): Collection
    {
        if ($botIds === []) {
            return collect();
        }

        return AiUsageLog::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('bot_id', $botIds)
            ->whereDate('date', '>=', $from->toDateString())
            ->select('bot_id')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->groupBy('bot_id')
            ->get()
            ->mapWithKeys(fn (mixed $row): array => [
                (int) $row->bot_id => [
                    'requests' => (int) $row->requests,
                    'tokens' => (int) $row->tokens,
                    'cost_micros' => (int) $row->cost_micros,
                ],
            ]);
    }

    /**
     * @param  array<int, int>  $botIds
     * @return Collection<int, int>
     */
    private function routineRunsToday(array $botIds): Collection
    {
        if ($botIds === []) {
            return collect();
        }

        return ChatSession::query()
            ->where('source', RunAiBotRoutine::SOURCE_ROUTINE)
            ->whereIn('bot_id', $botIds)
            ->where('created_at', '>=', now()->startOfDay())
            ->select('bot_id')
            ->selectRaw('COUNT(*) as runs')
            ->groupBy('bot_id')
            ->get()
            ->mapWithKeys(fn (mixed $row): array => [(int) $row->bot_id => (int) $row->runs]);
    }

    /**
     * @param  Collection<int, AiBot>  $bots
     * @param  array<int, int>  $botIds
     * @param  Collection<int, array{requests: int, tokens: int, cost_micros: int}>  $usageByBot
     * @return list<array{key: string, label: string, value: string, raw: int, hint: string, tone: string}>
     */
    private function kpis(
        Collection $bots,
        ?int $workspaceId,
        array $botIds,
        CarbonImmutable $from,
        Collection $usageByBot,
    ): array {
        $activeBots = $bots->where('is_active', true)->count();
        $totalBots = $bots->count();
        $liveSessions = $this->liveSessionsQuery($workspaceId, $botIds)->count();
        $pendingHandoffs = $this->handoffQuery($workspaceId)->pending()->count();
        $pendingApprovals = $this->approvalQuery($workspaceId)
            ->where('status', PendingAiAction::STATUS_PENDING)
            ->whereNotNull('bot_id')
            ->count();
        $failedHandoffs = $this->handoffQuery($workspaceId)
            ->where('status', AiBotHandoff::STATUS_FAILED)
            ->where('created_at', '>=', $from)
            ->count();
        $routineFailures = $this->routineQuery($workspaceId)
            ->where('last_status', AiBotRoutine::STATUS_FAILED)
            ->count();
        $messages = $this->messageQuery($workspaceId, $botIds)
            ->where('role', 'assistant')
            ->where('created_at', '>=', $from)
            ->count();

        $tokens = (int) $usageByBot->sum('tokens');
        $costMicros = (int) $usageByBot->sum('cost_micros');

        return [
            [
                'key' => 'active_bots',
                'label' => 'Active bots',
                'value' => $activeBots.' / '.$totalBots,
                'raw' => $activeBots,
                'hint' => $totalBots === 0 ? 'No roster yet' : $totalBots.' configured',
                'tone' => $totalBots > 0 && $activeBots === $totalBots ? 'success' : 'primary',
            ],
            [
                'key' => 'live_sessions',
                'label' => 'Live sessions',
                'value' => (string) $liveSessions,
                'raw' => $liveSessions,
                'hint' => 'Updated in the last '.self::LIVE_SESSION_MINUTES.' minutes',
                'tone' => $liveSessions > 0 ? 'success' : 'gray',
            ],
            [
                'key' => 'pending_handoffs',
                'label' => 'Pending handoffs',
                'value' => (string) $pendingHandoffs,
                'raw' => $pendingHandoffs,
                'hint' => 'Queued or running now',
                'tone' => $pendingHandoffs > 0 ? 'warning' : 'gray',
            ],
            [
                'key' => 'pending_approvals',
                'label' => 'Pending approvals',
                'value' => (string) $pendingApprovals,
                'raw' => $pendingApprovals,
                'hint' => 'Bot writes awaiting a human',
                'tone' => $pendingApprovals > 0 ? 'warning' : 'gray',
            ],
            [
                'key' => 'failed_handoffs',
                'label' => 'Failed handoffs',
                'value' => (string) $failedHandoffs,
                'raw' => $failedHandoffs,
                'hint' => 'In the selected period',
                'tone' => $failedHandoffs > 0 ? 'danger' : 'gray',
            ],
            [
                'key' => 'routine_failures',
                'label' => 'Routine failures',
                'value' => (string) $routineFailures,
                'raw' => $routineFailures,
                'hint' => 'Latest run status',
                'tone' => $routineFailures > 0 ? 'danger' : 'gray',
            ],
            [
                'key' => 'messages',
                'label' => 'Assistant messages',
                'value' => number_format($messages),
                'raw' => $messages,
                'hint' => 'In the selected period',
                'tone' => 'primary',
            ],
            [
                'key' => 'tokens',
                'label' => 'Tokens',
                'value' => number_format($tokens),
                'raw' => $tokens,
                'hint' => '$'.number_format($costMicros / 1_000_000, 4).' estimated',
                'tone' => 'gray',
            ],
        ];
    }

    /** @return Builder<ChatSession> */
    private function liveSessionsQuery(?int $workspaceId, array $botIds): Builder
    {
        return ChatSession::query()
            ->botSessions()
            ->where('updated_at', '>=', now()->subMinutes(self::LIVE_SESSION_MINUTES))
            ->when($workspaceId, fn (Builder $query): Builder => $query->forBotIds($botIds));
    }

    /** @return Builder<ChatMessage> */
    private function messageQuery(?int $workspaceId, array $botIds): Builder
    {
        return ChatMessage::query()
            ->whereNotNull('bot_id')
            ->when($workspaceId, fn (Builder $query): Builder => $query->whereIn('bot_id', $botIds));
    }

    /** @return Builder<AiBotHandoff> */
    private function handoffQuery(?int $workspaceId): Builder
    {
        return AiBotHandoff::query()
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId));
    }

    /** @return Builder<AiBotRoutine> */
    private function routineQuery(?int $workspaceId): Builder
    {
        return AiBotRoutine::query()
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId));
    }

    /**
     * The internal Bot Team Room: watcher reasoning the specialists posted
     * while patrolling. Bot-authored only; never user messages or raw payload
     * contents, so the read-only dashboard can render it safely.
     */
    private function teamRoom(?int $workspaceId): array
    {
        $items = AiBotTeamMessage::query()
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->with(['bot:id,name,slug,avatar,kind', 'workspace:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::TEAM_ROOM_ITEMS)
            ->get()
            ->map(fn (AiBotTeamMessage $message): array => [
                'id' => $message->id,
                'bot' => $message->bot ? [
                    'id' => $message->bot->id,
                    'name' => $message->bot->name,
                    'avatar' => $message->bot->avatar,
                    'is_coordinator' => $message->bot->kind === AiBot::KIND_COORDINATOR,
                ] : null,
                'kind' => $message->kind,
                'severity' => $message->severity,
                'body' => $message->body,
                'workspace_name' => $message->workspace?->name,
                'posted_at' => $message->created_at,
                'posted_at_label' => $message->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        $todayCount = AiBotTeamMessage::query()
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return [
            'items' => $items,
            'total_today' => $todayCount,
            'watchers_enabled' => AiBotsGate::enabled() && AiBotWatchers::enabled(),
            'watchers_alert_email' => AiBotWatchers::alertEmail(),
        ];
    }

    /** @return Builder<PendingAiAction> */
    private function approvalQuery(?int $workspaceId): Builder
    {
        return PendingAiAction::query()
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId));
    }

    /**
     * @param  array<int, int>  $botIds
     * @return list<array<string, mixed>>
     */
    private function activityFeed(
        ?int $workspaceId,
        array $botIds,
        CarbonImmutable $from,
        string $activityType,
    ): array {
        $messages = $this->messageQuery($workspaceId, $botIds)
            ->where('role', 'assistant')
            ->where('created_at', '>=', $from)
            ->whereHas('session', fn (Builder $query): Builder => $query
                ->where('source', '!=', RunAiBotRoutine::SOURCE_ROUTINE))
            ->with([
                'bot:id,name,avatar,workspace_id',
                'bot.workspace:id,name',
                'session:id,uuid,title,user_id',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get()
            ->map(fn (ChatMessage $message): array => [
                'type' => 'message',
                'tone' => 'primary',
                'title' => 'Reply from '.($message->bot?->name ?? 'bot'),
                'excerpt' => $this->excerpt($message->content, 180),
                'status' => null,
                'meta' => 'Assistant message',
                'workspace_name' => $message->bot?->workspace?->name ?? 'Platform',
                'bot_id' => $message->bot_id,
                'bot_name' => $message->bot?->name,
                'session_uuid' => $message->session?->uuid,
                'can_open_chat' => $message->session
                    && (int) $message->session->user_id === (int) auth()->id(),
                'created_at' => $this->toCarbon($message->created_at),
            ]);

        $handoffs = $this->handoffQuery($workspaceId)
            ->where('created_at', '>=', $from)
            ->with([
                'fromBot:id,name',
                'toBot:id,name',
                'workspace:id,name',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get()
            ->map(fn (AiBotHandoff $handoff): array => [
                'type' => 'handoff',
                'tone' => $this->handoffTone($handoff->status),
                'title' => ($handoff->fromBot?->name ?? 'Unknown bot')
                    .' → '.($handoff->toBot?->name ?? 'Unknown bot'),
                'excerpt' => $this->excerpt($handoff->task, 180),
                'status' => $handoff->status,
                'meta' => 'Handoff · depth '.$handoff->depth,
                'workspace_name' => $handoff->workspace?->name ?? 'Platform',
                'bot_id' => $handoff->to_bot_id,
                'bot_name' => $handoff->toBot?->name,
                'session_uuid' => null,
                'can_open_chat' => false,
                'created_at' => $this->toCarbon($handoff->created_at),
            ]);

        $routineSessions = ChatSession::query()
            ->where('source', RunAiBotRoutine::SOURCE_ROUTINE)
            ->where('created_at', '>=', $from)
            ->when($workspaceId, fn (Builder $query): Builder => $query->forBotIds($botIds))
            ->with([
                'bot:id,name,workspace_id',
                'bot.workspace:id,name',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get()
            ->map(fn (ChatSession $session): array => [
                'type' => 'routine',
                'tone' => 'warning',
                'title' => 'Routine run · '.($session->bot?->name ?? 'Bot'),
                'excerpt' => $session->title ?: 'Scheduled routine run',
                'status' => null,
                'meta' => 'Routine',
                'workspace_name' => $session->bot?->workspace?->name ?? 'Platform',
                'bot_id' => $session->bot_id,
                'bot_name' => $session->bot?->name,
                'session_uuid' => $session->uuid,
                'can_open_chat' => (int) $session->user_id === (int) auth()->id(),
                'created_at' => $this->toCarbon($session->created_at),
            ]);

        $approvals = $this->approvalQuery($workspaceId)
            ->whereNotNull('bot_id')
            ->where('created_at', '>=', $from)
            ->with([
                'bot:id,name,workspace_id',
                'bot.workspace:id,name',
                'workspace:id,name',
                'chatSession:id,uuid,user_id',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get()
            ->map(fn (PendingAiAction $action): array => [
                'type' => 'approval',
                'tone' => $this->approvalTone($action->status),
                'title' => $action->title,
                'excerpt' => $this->excerpt($action->summary, 180),
                'status' => $action->status,
                'meta' => 'Approval · '.str_replace('_', ' ', $action->action_type),
                'workspace_name' => $action->bot?->workspace?->name
                    ?? $action->workspace?->name
                    ?? 'Platform',
                'bot_id' => $action->bot_id,
                'bot_name' => $action->bot?->name,
                'session_uuid' => $action->chatSession?->uuid,
                'can_open_chat' => $action->chatSession
                    && (int) $action->chatSession->user_id === (int) auth()->id(),
                'created_at' => $this->toCarbon($action->created_at),
            ]);

        $policies = AiBotPolicyRevision::query()
            ->withoutGlobalScope('workspace')
            ->where('created_at', '>=', $from)
            ->when($workspaceId, fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->with([
                'bot:id,name,workspace_id',
                'bot.workspace:id,name',
                'changedBy:id,name',
            ])
            ->latest('created_at')
            ->limit(40)
            ->get()
            ->map(fn (AiBotPolicyRevision $revision): array => [
                'type' => 'policy',
                'tone' => 'gray',
                'title' => 'Policy v'.$revision->version.' · '.($revision->bot?->name ?? 'Bot'),
                'excerpt' => $this->excerpt(
                    $revision->change_summary ?: 'Bot policy saved.',
                    180,
                ),
                'status' => null,
                'meta' => $revision->changedBy?->name
                    ? 'Policy change · '.$revision->changedBy->name
                    : 'Policy change',
                'workspace_name' => $revision->bot?->workspace?->name ?? 'Platform',
                'bot_id' => $revision->ai_bot_id,
                'bot_name' => $revision->bot?->name,
                'session_uuid' => null,
                'can_open_chat' => false,
                'created_at' => $this->toCarbon($revision->created_at),
            ]);

        $items = collect()
            ->merge($messages)
            ->merge($handoffs)
            ->merge($routineSessions)
            ->merge($approvals)
            ->merge($policies);

        $type = match ($activityType) {
            self::ACTIVITY_MESSAGES => 'message',
            self::ACTIVITY_HANDOFFS => 'handoff',
            self::ACTIVITY_ROUTINES => 'routine',
            self::ACTIVITY_APPROVALS => 'approval',
            self::ACTIVITY_POLICIES => 'policy',
            default => null,
        };

        if ($type !== null) {
            $items = $items->where('type', $type);
        }

        return $items
            ->sortByDesc(fn (array $item): int => $item['created_at']?->getTimestamp() ?? 0)
            ->take(40)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AiBot>  $botMap
     * @return array<string, mixed>
     */
    private function handoffSection(?int $workspaceId, CarbonImmutable $from, Collection $botMap): array
    {
        $base = $this->handoffQuery($workspaceId)->where('created_at', '>=', $from);

        $counts = array_fill_keys([
            AiBotHandoff::STATUS_QUEUED,
            AiBotHandoff::STATUS_RUNNING,
            AiBotHandoff::STATUS_COMPLETED,
            AiBotHandoff::STATUS_FAILED,
            AiBotHandoff::STATUS_REJECTED,
            AiBotHandoff::STATUS_EXPIRED,
        ], 0);

        (clone $base)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function (mixed $row) use (&$counts): void {
                $counts[$row->status] = (int) $row->total;
            });

        $recent = (clone $base)
            ->with([
                'fromBot:id,name',
                'toBot:id,name',
                'workspace:id,name',
            ])
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(fn (AiBotHandoff $handoff): array => $this->handoffItem($handoff))
            ->all();

        $matrix = (clone $base)
            ->whereNotNull('from_bot_id')
            ->whereNotNull('to_bot_id')
            ->select(['from_bot_id', 'to_bot_id'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('from_bot_id', 'to_bot_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn (mixed $row): array => [
                'from' => $botMap->get((int) $row->from_bot_id)?->name ?? 'Unknown bot',
                'to' => $botMap->get((int) $row->to_bot_id)?->name ?? 'Unknown bot',
                'total' => (int) $row->total,
            ])
            ->all();

        return [
            'counts' => $counts,
            'recent' => $recent,
            'matrix' => $matrix,
        ];
    }

    /**
     * @param  Collection<int, int>  $routineRunsToday
     * @return list<array<string, mixed>>
     */
    private function routineSection(?int $workspaceId, Collection $routineRunsToday): array
    {
        return $this->routineQuery($workspaceId)
            ->with([
                'bot:id,name,avatar,workspace_id',
                'bot.workspace:id,name',
                'workspace:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (AiBotRoutine $routine): array => $this->routineItem(
                $routine,
                (int) $routineRunsToday->get((int) $routine->bot_id, 0),
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function approvalSection(?int $workspaceId, CarbonImmutable $from): array
    {
        $base = $this->approvalQuery($workspaceId)->whereNotNull('bot_id');

        $scoped = (clone $base)->where(function (Builder $query) use ($from): void {
            $query
                ->where('created_at', '>=', $from)
                ->orWhere('status', PendingAiAction::STATUS_PENDING);
        });

        $counts = array_fill_keys([
            PendingAiAction::STATUS_PENDING,
            PendingAiAction::STATUS_EXECUTED,
            PendingAiAction::STATUS_REJECTED,
            PendingAiAction::STATUS_FAILED,
            PendingAiAction::STATUS_EXPIRED,
        ], 0);

        (clone $scoped)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function (mixed $row) use (&$counts): void {
                $counts[$row->status] = (int) $row->total;
            });

        $items = (clone $scoped)
            ->with([
                'bot:id,name,workspace_id',
                'bot.workspace:id,name',
                'workspace:id,name',
                'chatSession:id,uuid,user_id',
            ])
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PendingAiAction $action): array => $this->approvalItem($action))
            ->all();

        return ['counts' => $counts, 'items' => $items];
    }

    /**
     * @param  Collection<int, AiBot>  $botMap
     * @return list<array<string, mixed>>
     */
    private function sessionSection(?int $workspaceId, CarbonImmutable $from, Collection $botMap): array
    {
        return ChatSession::query()
            ->botSessions()
            ->when(
                $workspaceId,
                fn (Builder $query): Builder => $query->forBotIds(
                    $botMap->keys()->map(fn (mixed $id): int => (int) $id)->all(),
                ),
            )
            ->where('updated_at', '>=', $from)
            ->with([
                'user:id,name',
                'bot:id,name,avatar,workspace_id',
                'bot.workspace:id,name',
            ])
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(fn (ChatSession $session): array => $this->sessionItem($session, $botMap))
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $botCards
     * @param  Collection<int, array{requests: int, tokens: int, cost_micros: int}>  $usageByBot
     * @return array<string, mixed>
     */
    private function usageSection(Collection $botCards, Collection $usageByBot): array
    {
        $maxTokens = max(1, (int) $botCards->max('usage_tokens'));

        $rows = $botCards
            ->map(fn (array $card): array => [
                'bot_id' => $card['id'],
                'bot_name' => $card['name'],
                'workspace_name' => $card['workspace_name'],
                'tokens' => $card['usage_tokens'],
                'cost_micros' => $card['usage_cost_micros'],
                'requests' => $card['usage_requests'],
                'bar_percent' => (int) round(min(100, ($card['usage_tokens'] / $maxTokens) * 100)),
            ])
            ->sortByDesc('tokens')
            ->take(15)
            ->values()
            ->all();

        return [
            'bots' => $rows,
            'max_tokens' => $maxTokens,
            'total_tokens' => (int) $usageByBot->sum('tokens'),
            'total_cost_micros' => (int) $usageByBot->sum('cost_micros'),
            'total_requests' => (int) $usageByBot->sum('requests'),
        ];
    }

    /**
     * @param  Collection<int, AiBot>  $bots
     * @param  Collection<int, AiBot>  $botMap
     * @return list<array<string, mixed>>
     */
    private function workspaceSummaries(Collection $bots, CarbonImmutable $from, Collection $botMap): array
    {
        $botsByWorkspace = $bots->groupBy(fn (AiBot $bot): int => (int) ($bot->workspace_id ?? 0));

        $liveCounts = [];
        $this->liveSessionsQuery(null, [])
            ->with('bot:id,workspace_id')
            ->get()
            ->each(function (ChatSession $session) use (&$liveCounts, $botMap): void {
                $workspaceId = $session->bot_id
                    ? $session->bot?->workspace_id
                    : $botMap->get((int) collect((array) $session->participant_bot_ids)->first())?->workspace_id;

                if ($workspaceId) {
                    $liveCounts[(int) $workspaceId] = ($liveCounts[(int) $workspaceId] ?? 0) + 1;
                }
            });

        $pendingHandoffs = $this->groupedCounts($this->handoffQuery(null)->pending());
        $failedHandoffs = $this->groupedCounts(
            $this->handoffQuery(null)
                ->where('status', AiBotHandoff::STATUS_FAILED)
                ->where('created_at', '>=', $from),
        );
        $pendingApprovals = $this->groupedCounts(
            $this->approvalQuery(null)
                ->where('status', PendingAiAction::STATUS_PENDING)
                ->whereNotNull('bot_id'),
        );

        return Workspace::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Workspace $workspace) use (
                $botsByWorkspace,
                $liveCounts,
                $pendingHandoffs,
                $failedHandoffs,
                $pendingApprovals,
            ): array {
                $workspaceBots = $botsByWorkspace->get((int) $workspace->id, collect());
                $coordinator = $workspaceBots->first(
                    fn (AiBot $bot): bool => $bot->isCoordinator() && $bot->is_active,
                );

                return [
                    'id' => (int) $workspace->id,
                    'name' => $workspace->name,
                    'coordinator' => $coordinator?->name,
                    'total_bots' => $workspaceBots->count(),
                    'active_bots' => $workspaceBots->where('is_active', true)->count(),
                    'live_sessions' => (int) ($liveCounts[(int) $workspace->id] ?? 0),
                    'pending_handoffs' => (int) $pendingHandoffs->get((int) $workspace->id, 0),
                    'failed_handoffs' => (int) $failedHandoffs->get((int) $workspace->id, 0),
                    'pending_approvals' => (int) $pendingApprovals->get((int) $workspace->id, 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, int> */
    private function groupedCounts(Builder $query, string $column = 'workspace_id'): Collection
    {
        return $query
            ->whereNotNull($column)
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn (mixed $row): array => [(int) $row->{$column} => (int) $row->total]);
    }

    /**
     * @param  Collection<int, AiBot>  $bots
     * @param  Collection<int, array<string, mixed>>  $botCards
     * @return list<array<string, mixed>>
     */
    private function healthWarnings(
        Collection $bots,
        Collection $botCards,
        ?int $workspaceId,
        CarbonImmutable $from,
    ): array {
        $warnings = [];
        $botCardsById = $botCards->keyBy(fn (array $card): int => (int) $card['id']);

        if ($workspaceId) {
            $hasCoordinator = $bots->contains(
                fn (AiBot $bot): bool => $bot->isCoordinator() && $bot->is_active,
            );

            if (! $hasCoordinator) {
                $warnings[] = $this->warning(
                    'critical',
                    'No active Chief of Staff',
                    'Team chats cannot start until this workspace has an active coordinator.',
                    null,
                    $workspaceId,
                );
            }
        } else {
            Workspace::query()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->each(function (Workspace $workspace) use ($bots, &$warnings): void {
                    $hasCoordinator = $bots->contains(
                        fn (AiBot $bot): bool => (int) $bot->workspace_id === (int) $workspace->id
                            && $bot->isCoordinator()
                            && $bot->is_active,
                    );

                    if (! $hasCoordinator) {
                        $warnings[] = $this->warning(
                            'critical',
                            $workspace->name.' has no active Chief of Staff',
                            'Team chats cannot start in this workspace until a coordinator is active.',
                            null,
                            (int) $workspace->id,
                        );
                    }
                });
        }

        foreach ($bots as $bot) {
            if ($bot->isSpecialist() && ! $bot->is_active) {
                $warnings[] = $this->warning(
                    'warning',
                    $bot->name.' is inactive',
                    'Inactive specialists cannot join team chats or receive new handoffs.',
                    (int) $bot->id,
                    $bot->workspace_id,
                );
            }

            $card = $botCardsById->get((int) $bot->id);

            if ($card && $card['drift_warnings'] !== []) {
                $warnings[] = $this->warning(
                    'warning',
                    $bot->name.' has policy drift',
                    implode(' ', $card['drift_warnings']),
                    (int) $bot->id,
                    $bot->workspace_id,
                );
            }

            foreach ($bot->routines as $routine) {
                if ($routine->last_status === AiBotRoutine::STATUS_FAILED) {
                    $warnings[] = $this->warning(
                        'warning',
                        'Routine failed · '.$routine->name,
                        $routine->last_error ?: 'The last run failed.',
                        (int) $bot->id,
                        $bot->workspace_id,
                    );
                } elseif ($routine->last_status === AiBotRoutine::STATUS_SKIPPED) {
                    $warnings[] = $this->warning(
                        'info',
                        'Routine skipped · '.$routine->name,
                        $routine->last_error ?: 'The last run was skipped.',
                        (int) $bot->id,
                        $bot->workspace_id,
                    );
                }
            }
        }

        $this->handoffQuery($workspaceId)
            ->pending()
            ->where('created_at', '<', now()->subMinutes(self::STALE_HANDOFF_MINUTES))
            ->with([
                'fromBot:id,name',
                'toBot:id,name',
                'workspace:id,name',
            ])
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->each(function (AiBotHandoff $handoff) use (&$warnings): void {
                $waiting = $this->toCarbon($handoff->created_at)?->diffForHumans(null, true) ?? 'a while';

                $warnings[] = $this->warning(
                    'warning',
                    'Handoff waiting too long',
                    ($handoff->fromBot?->name ?? 'Coordinator')
                        .' → '.($handoff->toBot?->name ?? 'specialist')
                        .' has been '.$handoff->status.' for '.$waiting.'.',
                    $handoff->to_bot_id,
                    $handoff->workspace_id,
                );
            });

        $this->approvalQuery($workspaceId)
            ->whereNotNull('bot_id')
            ->where('status', PendingAiAction::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours(self::APPROVAL_EXPIRY_WINDOW_HOURS)])
            ->with([
                'bot:id,name',
                'workspace:id,name',
            ])
            ->orderBy('expires_at')
            ->limit(10)
            ->get()
            ->each(function (PendingAiAction $action) use (&$warnings): void {
                $warnings[] = $this->warning(
                    'warning',
                    'Approval expires soon',
                    $action->title.' · '.($this->toCarbon($action->expires_at)?->diffForHumans() ?? 'expiring soon'),
                    $action->bot_id,
                    $action->workspace_id,
                );
            });

        $noActivity = $bots
            ->where('is_active', true)
            ->filter(fn (AiBot $bot): bool => ! ($botCardsById->get((int) $bot->id)['has_period_activity'] ?? false))
            ->groupBy(fn (AiBot $bot): int => (int) ($bot->workspace_id ?? 0));

        foreach ($noActivity as $group) {
            $names = $group->pluck('name')->take(8)->implode(', ');
            $extra = $group->count() > 8 ? ' +'.($group->count() - 8).' more' : '';
            $workspaceName = $group->first()->workspace?->name ?? 'Platform';

            $warnings[] = $this->warning(
                'info',
                $group->count().' bot'.($group->count() === 1 ? '' : 's').' with no activity',
                $workspaceName.': '.$names.$extra,
                null,
                $group->first()->workspace_id,
            );
        }

        usort($warnings, function (array $first, array $second): int {
            return $this->severityRank($first['severity']) <=> $this->severityRank($second['severity'])
                ?: strcmp($first['title'], $second['title']);
        });

        return array_slice($warnings, 0, 40);
    }

    /**
     * @param  Collection<int, AiBot>  $botMap
     * @param  Collection<int, array<string, mixed>>  $botCards
     * @return array<string, mixed>|null
     */
    private function botDetail(int $botId, Collection $botMap, Collection $botCards, CarbonImmutable $from): ?array
    {
        $bot = $botMap->get($botId);
        $card = $botCards->firstWhere('id', $botId);

        if (! $bot || ! $card) {
            return null;
        }

        $bot->routines->each(fn (AiBotRoutine $routine) => $routine->setRelation('bot', $bot));

        $recentMessages = ChatMessage::query()
            ->where('bot_id', $bot->id)
            ->where('role', 'assistant')
            ->with('session:id,uuid,title,user_id,source')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ChatMessage $message): array => [
                'excerpt' => $this->excerpt($message->content, 220),
                'session_title' => $message->session?->title ?: 'Untitled session',
                'source_label' => $message->session?->source === RunAiBotRoutine::SOURCE_ROUTINE
                    ? 'Routine'
                    : 'Echo',
                'session_uuid' => $message->session?->uuid,
                'can_open_chat' => $message->session
                    && (int) $message->session->user_id === (int) auth()->id(),
                'created_at' => $this->toCarbon($message->created_at),
            ])
            ->all();

        $handoffs = $this->handoffQuery(null)
            ->where(function (Builder $query) use ($bot): void {
                $query
                    ->where('from_bot_id', $bot->id)
                    ->orWhere('to_bot_id', $bot->id);
            })
            ->with([
                'fromBot:id,name',
                'toBot:id,name',
                'workspace:id,name',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AiBotHandoff $handoff): array => $this->handoffItem($handoff))
            ->all();

        $sessions = ChatSession::query()
            ->forBot((int) $bot->id)
            ->with([
                'user:id,name',
                'bot:id,name,avatar,workspace_id',
                'bot.workspace:id,name',
            ])
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (ChatSession $session): array => $this->sessionItem($session, $botMap))
            ->all();

        return [
            'bot' => $card,
            'recent_messages' => $recentMessages,
            'handoffs' => $handoffs,
            'routines' => $bot->routines
                ->map(fn (AiBotRoutine $routine): array => $this->routineItem(
                    $routine,
                    (int) $card['runs_today'],
                    $bot,
                ))
                ->values()
                ->all(),
            'sessions' => $sessions,
            'usage_daily' => $this->usageDaily((int) $bot->id, $from),
        ];
    }

    /** @return array<string, mixed> */
    private function handoffItem(AiBotHandoff $handoff): array
    {
        $created = $this->toCarbon($handoff->created_at);
        $updated = $this->toCarbon($handoff->updated_at);
        $waiting = in_array($handoff->status, [
            AiBotHandoff::STATUS_QUEUED,
            AiBotHandoff::STATUS_RUNNING,
        ], true);

        $duration = $waiting
            ? ($created ? 'Waiting '.$created->diffForHumans(null, true) : 'Waiting')
            : ($created && $updated ? 'Took '.$created->diffForHumans($updated, true) : 'Finished');

        return [
            'id' => (int) $handoff->id,
            'from' => $handoff->fromBot?->name ?? 'Unknown bot',
            'to' => $handoff->toBot?->name ?? 'Unknown bot',
            'task' => $this->excerpt($handoff->task, 160),
            'status' => $handoff->status,
            'depth' => (int) $handoff->depth,
            'duration' => $duration,
            'workspace_name' => $handoff->workspace?->name ?? 'Platform',
            'created_at' => $created,
        ];
    }

    /** @return array<string, mixed> */
    private function routineItem(AiBotRoutine $routine, int $runsToday, ?AiBot $bot = null): array
    {
        $bot ??= $routine->bot;

        return [
            'id' => (int) $routine->id,
            'name' => $routine->name,
            'bot_id' => (int) $routine->bot_id,
            'bot_name' => $bot?->name,
            'workspace_name' => $bot?->workspace?->name
                ?? $routine->workspace?->name
                ?? 'Platform',
            'cron' => $routine->cron,
            'is_active' => (bool) $routine->is_active,
            'next_run_at' => $this->nextRunAt($routine->cron),
            'last_run_at' => $this->toCarbon($routine->last_run_at),
            'last_status' => $routine->last_status,
            'last_error' => $routine->last_error
                ? $this->excerpt($routine->last_error, 160)
                : null,
            'runs_today' => $runsToday,
            'max_daily_runs' => RunAiBotRoutine::MAX_DAILY_RUNS,
        ];
    }

    /**
     * @param  Collection<int, AiBot>  $botMap
     * @return array<string, mixed>
     */
    private function sessionItem(ChatSession $session, Collection $botMap): array
    {
        $participantIds = array_values(array_filter(
            array_map('intval', (array) $session->participant_bot_ids),
            fn (int $id): bool => $id > 0,
        ));

        $participants = collect($participantIds)
            ->map(fn (int $id): ?string => $botMap->get($id)?->name)
            ->filter()
            ->values();

        $bot = $session->bot_id ? $botMap->get((int) $session->bot_id) : null;

        if ($participants->isEmpty() && $bot) {
            $participants->push($bot->name);
        }

        $workspaceName = $bot?->workspace?->name
            ?? ($participantIds !== [] ? $botMap->get($participantIds[0])?->workspace?->name : null)
            ?? 'Platform';

        return [
            'id' => (int) $session->id,
            'uuid' => $session->uuid,
            'title' => $session->title ?: 'New chat',
            'mode' => $session->mode,
            'mode_label' => $session->isGroup() ? 'Team chat' : 'Solo chat',
            'source' => $session->source,
            'source_label' => $session->source === RunAiBotRoutine::SOURCE_ROUTINE ? 'Routine' : 'Echo',
            'owner' => $session->user?->name ?? 'Unknown admin',
            'participants' => $participants->all(),
            'workspace_name' => $workspaceName,
            'message_count' => (int) ($session->messages_count ?? 0),
            'updated_at' => $this->toCarbon($session->updated_at),
            'can_open_chat' => $session->uuid !== null
                && (int) $session->user_id === (int) auth()->id(),
        ];
    }

    /** @return array<string, mixed> */
    private function approvalItem(PendingAiAction $action): array
    {
        return [
            'id' => (int) $action->id,
            'title' => $action->title,
            'summary' => $this->excerpt($action->summary, 180),
            'status' => $action->status,
            'action_type' => str_replace('_', ' ', $action->action_type),
            'bot_id' => $action->bot_id,
            'bot_name' => $action->bot?->name,
            'workspace_name' => $action->bot?->workspace?->name
                ?? $action->workspace?->name
                ?? 'Platform',
            'created_at' => $this->toCarbon($action->created_at),
            'expires_at' => $this->toCarbon($action->expires_at),
            'session_uuid' => $action->chatSession?->uuid,
            'can_open_chat' => $action->chatSession
                && (int) $action->chatSession->user_id === (int) auth()->id(),
        ];
    }

    /**
     * @return list<array{date: string, tokens: int, cost_micros: int}>
     */
    private function usageDaily(int $botId, CarbonImmutable $from): array
    {
        $rows = AiUsageLog::query()
            ->withoutGlobalScope('workspace')
            ->where('bot_id', $botId)
            ->whereDate('date', '>=', $from->toDateString())
            ->select('date')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->groupBy('date')
            ->get()
            ->keyBy(fn (mixed $row): string => $row->date->toDateString());

        $days = [];
        $cursor = $from->startOfDay();
        $end = now()->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $days[] = [
                'date' => $date,
                'tokens' => (int) ($row?->tokens ?? 0),
                'cost_micros' => (int) ($row?->cost_micros ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    private function nextRunAt(?string $cron): ?CarbonImmutable
    {
        if (blank($cron)) {
            return null;
        }

        try {
            $next = (new CronExpression($cron))->getNextRunDate(now()->format('Y-m-d H:i:s'));

            return CarbonImmutable::parse($next->format('Y-m-d H:i:s'), config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function toCarbon(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function excerpt(?string $value, int $limit = 160): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        return $text === '' ? 'No text captured.' : Str::limit($text, $limit);
    }

    /** @return array{severity: string, title: string, description: string, bot_id: int|null, workspace_id: int|null} */
    private function warning(
        string $severity,
        string $title,
        string $description,
        ?int $botId,
        ?int $workspaceId,
    ): array {
        return [
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'bot_id' => $botId,
            'workspace_id' => $workspaceId,
        ];
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 0,
            'warning' => 1,
            default => 2,
        };
    }

    private function handoffTone(string $status): string
    {
        return match ($status) {
            AiBotHandoff::STATUS_COMPLETED => 'success',
            AiBotHandoff::STATUS_FAILED => 'danger',
            AiBotHandoff::STATUS_QUEUED, AiBotHandoff::STATUS_RUNNING => 'warning',
            default => 'gray',
        };
    }

    private function approvalTone(string $status): string
    {
        return match ($status) {
            PendingAiAction::STATUS_EXECUTED => 'success',
            PendingAiAction::STATUS_FAILED => 'danger',
            PendingAiAction::STATUS_PENDING => 'warning',
            default => 'gray',
        };
    }

    /** @return array{requests: int, tokens: int, cost_micros: int} */
    private static function emptyUsage(): array
    {
        return ['requests' => 0, 'tokens' => 0, 'cost_micros' => 0];
    }
}
