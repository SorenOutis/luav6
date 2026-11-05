<x-filament-panels::page>
    @php
        $money = fn (int $micros): string => '$'.number_format($micros / 1_000_000, 4);
        $toneClasses = fn (string $tone): string => match ($tone) {
            'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-400/20',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-400/20',
            'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-400/20',
            'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20',
            default => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
        };
        $severityClasses = fn (string $severity): string => match ($severity) {
            'critical' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-400/20',
            'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-400/20',
            default => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20',
        };
        $chatUrl = fn (string $uuid): string => \App\Filament\Pages\AdminAiChat::getUrl(['c' => $uuid]);
        $statusLabel = fn (?string $status): string => $status
            ? str_replace('_', ' ', ucfirst($status))
            : 'Unknown';
        $filterButton = 'rounded-lg px-3 py-1.5 text-xs font-semibold transition';
    @endphp

    <div class="space-y-6" @if ($polling) wire:poll.15s @endif>
        @if (! $bots_enabled)
            <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-300" role="alert">
                <strong>AI bots are disabled.</strong> {{ $bots_disabled_message }}
                <a href="{{ $ai_bots_settings_url }}" class="font-semibold underline underline-offset-2">Re-enable in Platform Settings</a>
            </div>
        @endif
        {{-- Scope and freshness --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $workspace['is_all'] ? 'All workspaces' : $workspace['name'] }}
                        </h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                            Read-only
                        </span>
                        @if ($polling)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-400/20">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success-500"></span>
                                Live
                            </span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                Updates paused
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $period['label'] }} · refreshed {{ $generated_at->diffForHumans() }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="bot-operations-workspace">Workspace</label>
                    <select
                        id="bot-operations-workspace"
                        wire:model.live="workspace"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                    >
                        @foreach ($workspace_options as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-white/5">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period</span>
                @foreach ($period['options'] as $value => $label)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $value }}')"
                        @class([
                            $filterButton,
                            'bg-primary-600 text-white shadow-sm' => $period['key'] === $value,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $period['key'] !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- KPI strip --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $kpi['value'] }}</p>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $kpi['hint'] }}</p>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses($kpi['tone']) }}">
                            {{ $kpi['label'] === 'Active bots' ? 'roster' : 'live' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- All-workspaces pulse --}}
        @if ($workspace['is_all'])
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div>
                        <h3 class="font-semibold text-gray-950 dark:text-white">Workspace pulse</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">One row per workspace, including empty rosters.</p>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ count($workspaces) }} workspaces</span>
                </div>
                <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($workspaces as $workspaceCard)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $workspaceCard['name'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $workspaceCard['coordinator'] ? 'Chief: '.$workspaceCard['coordinator'] : 'No active Chief of Staff' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $workspaceCard['coordinator'] ? $toneClasses('success') : $toneClasses('danger') }}">
                                    {{ $workspaceCard['active_bots'] }}/{{ $workspaceCard['total_bots'] }} active
                                </span>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-white/5">
                                    <dt class="text-gray-500 dark:text-gray-400">Live sessions</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $workspaceCard['live_sessions'] }}</dd>
                                </div>
                                <div class="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-white/5">
                                    <dt class="text-gray-500 dark:text-gray-400">Pending handoffs</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $workspaceCard['pending_handoffs'] }}</dd>
                                </div>
                                <div class="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-white/5">
                                    <dt class="text-gray-500 dark:text-gray-400">Pending approvals</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $workspaceCard['pending_approvals'] }}</dd>
                                </div>
                                <div class="rounded-md bg-gray-50 px-2 py-1.5 dark:bg-white/5">
                                    <dt class="text-gray-500 dark:text-gray-400">Failed handoffs</dt>
                                    <dd class="font-semibold {{ $workspaceCard['failed_handoffs'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">{{ $workspaceCard['failed_handoffs'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    @empty
                        <div class="col-span-full px-2 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No workspaces exist yet.</div>
                    @endforelse
                </div>
            </section>
        @endif

        {{-- Team map --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-gray-950 dark:text-white">Team map</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Click a bot to inspect its recent work without leaving the page.</p>
                </div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ count($team['specialists']) + ($team['coordinator'] ? 1 : 0) }} bots</span>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @if ($team['coordinator'])
                    @php($bot = $team['coordinator'])
                    <button type="button" wire:click="openBot({{ $bot['id'] }})" class="group relative overflow-hidden rounded-xl border border-warning-200 bg-warning-50/60 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-warning-500/20 dark:bg-warning-500/5">
                        <span class="absolute right-3 top-3 rounded-full bg-warning-100 px-2 py-0.5 text-[11px] font-semibold text-warning-800 dark:bg-warning-500/20 dark:text-warning-200">Chief</span>
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-500 text-sm font-semibold text-white">{{ $bot['initials'] }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $bot['name'] }}</p>
                                <p class="truncate text-xs text-gray-600 dark:text-gray-400">{{ $bot['title'] ?: 'Coordinator' }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $bot['is_active'] ? $toneClasses('success') : $toneClasses('danger') }}">{{ $bot['is_active'] ? 'Active' : 'Inactive' }}</span>
                            @if ($workspace['is_all'])
                                <span class="rounded-full bg-white px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10">{{ $bot['workspace_name'] }}</span>
                            @endif
                            @if ($bot['drift_warnings'] !== [])
                                <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $toneClasses('warning') }}">Policy drift</span>
                            @endif
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-warning-200/60 pt-3 text-xs dark:border-warning-500/10">
                            <div><dt class="text-gray-500 dark:text-gray-400">Messages</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ number_format($bot['period_messages']) }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Open handoffs</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $bot['open_handoffs'] }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Tokens</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ number_format($bot['usage_tokens']) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">{{ $bot['last_activity_label'] }} · {{ $bot['next_routine_label'] }}</p>
                    </button>
                @endif

                @foreach ($team['specialists'] as $bot)
                    <button type="button" wire:click="openBot({{ $bot['id'] }})" class="group rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/40">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">{{ $bot['initials'] }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $bot['name'] }}</p>
                                <p class="truncate text-xs text-gray-600 dark:text-gray-400">{{ $bot['title'] ?: 'Specialist' }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $bot['is_active'] ? $toneClasses('success') : $toneClasses('danger') }}">{{ $bot['is_active'] ? 'Active' : 'Inactive' }}</span>
                            @if ($workspace['is_all'])
                                <span class="rounded-full bg-gray-50 px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $bot['workspace_name'] }}</span>
                            @endif
                            @if ($bot['drift_warnings'] !== [])
                                <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $toneClasses('warning') }}">Policy drift</span>
                            @endif
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-gray-100 pt-3 text-xs dark:border-white/5">
                            <div><dt class="text-gray-500 dark:text-gray-400">Messages</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ number_format($bot['period_messages']) }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Open handoffs</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $bot['open_handoffs'] }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Tokens</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ number_format($bot['usage_tokens']) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">{{ $bot['last_activity_label'] }} · {{ $bot['next_routine_label'] }}</p>
                    </button>
                @endforeach
            </div>

            @if (! $team['coordinator'] && $team['specialists'] === [])
                <div class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    No bots configured for this scope yet.
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Live activity --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2 dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">Live activity</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Assistant excerpts only; full transcripts and user messages stay private.</p>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ count($activity['items']) }} recent events</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($activity_types as $value => $label)
                            <button
                                type="button"
                                wire:click="setActivity('{{ $value }}')"
                                @class([
                                    $filterButton,
                                    'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $activity['selected'] === $value,
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $activity['selected'] !== $value,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($activity['items'] as $item)
                        <div class="flex gap-3 px-5 py-3">
                            <span class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-bold uppercase ring-1 ring-inset {{ $toneClasses($item['tone']) }}">
                                {{ strtoupper(substr($item['type'], 0, 2)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                                    <span class="shrink-0 text-[11px] text-gray-500 dark:text-gray-400">{{ $item['created_at']?->diffForHumans() ?? 'Unknown time' }}</span>
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-300">{{ $item['excerpt'] }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>{{ $item['meta'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $item['workspace_name'] }}</span>
                                    @if ($item['status'])
                                        <span class="rounded-full px-1.5 py-0.5 font-semibold ring-1 ring-inset {{ $toneClasses($item['tone']) }}">{{ $statusLabel($item['status']) }}</span>
                                    @endif
                                    @if ($item['can_open_chat'] && $item['session_uuid'])
                                        <a href="{{ $chatUrl($item['session_uuid']) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Open chat</a>
                                    @endif
                                    @if ($item['bot_id'])
                                        <button type="button" wire:click="openBot({{ $item['bot_id'] }})" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">View bot</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No activity matches this filter. Try a longer period or a different event type.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- Needs attention --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Needs attention</h3>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $health === [] ? $toneClasses('success') : $toneClasses('warning') }}">
                            {{ count($health) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Stale handoffs, expiring approvals, policy drift, and quiet bots.</p>
                </div>
                <div class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                    @forelse ($health as $warning)
                        <div class="px-5 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $severityClasses($warning['severity']) }}">{{ $warning['severity'] }}</span>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $warning['title'] }}</p>
                            </div>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $warning['description'] }}</p>
                            @if ($warning['bot_id'])
                                <button type="button" wire:click="openBot({{ $warning['bot_id'] }})" class="mt-1.5 text-[11px] font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Inspect bot</button>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            Nothing needs attention in this scope.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Bot team room --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-950 dark:text-white">Bot team room</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How the specialists explain what they saw and suggest what happens next. Internal, super-admin only, and read-only — approval always stays with you.</p>
                    </div>
                    @if ($team_room['watchers_enabled'])
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $toneClasses('success') }}">Watchers on</span>
                    @else
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $toneClasses('warning') }}">Watchers off</span>
                    @endif
                </div>
            </div>
            @if ($team_room['items'] === [])
                <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    @if ($team_room['watchers_enabled'])
                        Nothing posted yet — the first watcher pass arrives within 30 minutes.
                    @else
                        Watchers are currently disabled. Turn them on in Platform Settings to give the bots autonomous patrol shifts.
                    @endif
                </div>
            @else
                <div class="max-h-[36rem] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                    @foreach ($team_room['items'] as $entry)
                        <div class="flex gap-3 px-5 py-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                {{ $entry['bot']['avatar'] ?? ($entry['bot']['name'][0] ?? '?') }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $entry['bot']['name'] ?? 'Retired bot' }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ match ($entry['kind']) {
                                        'suggestion' => $toneClasses('primary'),
                                        'all_clear' => $toneClasses('success'),
                                        default => $toneClasses('gray'),
                                    } }}">{{ str_replace('_', ' ', $entry['kind']) }}</span>
                                    @if ($entry['severity'] === 'critical' || $entry['severity'] === 'warning')
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $severityClasses($entry['severity']) }}">{{ $entry['severity'] }}</span>
                                    @endif
                                    @if ($workspace['is_all'] && $entry['workspace_name'])
                                        <span class="rounded-full bg-gray-50 px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $entry['workspace_name'] }}</span>
                                    @endif
                                    <span class="ml-auto text-[11px] text-gray-500 dark:text-gray-400">{{ $entry['posted_at_label'] }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $entry['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Handoffs --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <h3 class="font-semibold text-gray-950 dark:text-white">Handoffs and delegation</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How work moves between the Chief of Staff and specialists.</p>
            </div>
            <div class="grid gap-0 divide-y divide-gray-100 dark:divide-white/5 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                <div class="p-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</h4>
                    <dl class="mt-3 space-y-2">
                        @foreach ($handoffs['counts'] as $status => $count)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">{{ $statusLabel($status) }}</dt>
                                <dd class="font-semibold text-gray-950 dark:text-white">{{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="p-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Busiest routes</h4>
                    <div class="mt-3 space-y-2">
                        @forelse ($handoffs['matrix'] as $route)
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="truncate text-gray-600 dark:text-gray-300">{{ $route['from'] }} → {{ $route['to'] }}</span>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $route['total'] }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">No handoffs in this period.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent handoffs</h4>
                    <div class="mt-3 max-h-64 space-y-3 overflow-y-auto pr-1">
                        @forelse ($handoffs['recent'] as $handoff)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-950 dark:text-white">{{ $handoff['from'] }} → {{ $handoff['to'] }}</p>
                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $toneClasses(match ($handoff['status']) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'queued', 'running' => 'warning',
                                        default => 'gray',
                                    }) }}">{{ $statusLabel($handoff['status']) }}</span>
                                </div>
                                <p class="mt-1 line-clamp-2 text-[11px] text-gray-500 dark:text-gray-400">{{ $handoff['task'] }}</p>
                                <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $handoff['duration'] }} · {{ $handoff['workspace_name'] }}</p>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">No handoffs in this period.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            {{-- Routines --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Routines</h3>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ count($routines) }} scheduled</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Daily cap: {{ \App\Jobs\RunAiBotRoutine::MAX_DAILY_RUNS }} runs per bot.</p>
                </div>
                <div class="max-h-[30rem] overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2.5">Routine</th>
                                <th class="px-4 py-2.5">Next</th>
                                <th class="px-4 py-2.5">Last</th>
                                <th class="px-4 py-2.5 text-right">Today</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($routines as $routine)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $routine['name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $routine['bot_name'] ?? 'Unknown bot' }}@if ($workspace['is_all']) · {{ $routine['workspace_name'] }}@endif
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">{{ $routine['cron'] }}</p>
                                        @if ($routine['last_error'])
                                            <p class="mt-1 line-clamp-2 text-[11px] text-danger-600 dark:text-danger-400">{{ $routine['last_error'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $routine['is_active'] ? ($routine['next_run_at']?->diffForHumans() ?? 'Invalid cron') : 'Paused' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $toneClasses(match ($routine['last_status']) {
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'skipped' => 'warning',
                                            default => 'gray',
                                        }) }}">{{ $routine['last_status'] ? $statusLabel($routine['last_status']) : 'Never' }}</span>
                                        <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $routine['last_run_at']?->diffForHumans() ?? 'Not run yet' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs font-semibold text-gray-950 dark:text-white">{{ $routine['runs_today'] }} / {{ $routine['max_daily_runs'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No routines configured in this scope.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Approvals --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-950 dark:text-white">Bot approvals</h3>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ ($approvals['counts']['pending'] ?? 0) > 0 ? $toneClasses('warning') : $toneClasses('gray') }}">
                            {{ $approvals['counts']['pending'] ?? 0 }} pending
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Writes stay blocked until a human approves them in Echo.</p>
                </div>
                <div class="max-h-[30rem] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                    @forelse ($approvals['items'] as $approval)
                        <div class="px-5 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $approval['title'] }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $toneClasses(match ($approval['status']) {
                                    'executed' => 'success',
                                    'failed' => 'danger',
                                    'pending' => 'warning',
                                    default => 'gray',
                                }) }}">{{ $statusLabel($approval['status']) }}</span>
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-300">{{ $approval['summary'] }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $approval['action_type'] }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $approval['bot_name'] ?? 'Unknown bot' }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $approval['workspace_name'] }}</span>
                                @if ($approval['expires_at'])
                                    <span aria-hidden="true">·</span>
                                    <span>expires {{ $approval['expires_at']->diffForHumans() }}</span>
                                @endif
                                @if ($approval['can_open_chat'] && $approval['session_uuid'])
                                    <a href="{{ $chatUrl($approval['session_uuid']) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Open chat</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No bot approvals in this period.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Recent sessions --}}
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2 dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Recent bot sessions</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Solo and team conversations, including scheduled routine runs.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2.5">Session</th>
                                <th class="px-4 py-2.5">Participants</th>
                                <th class="px-4 py-2.5">Owner</th>
                                <th class="px-4 py-2.5 text-right">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($sessions as $session)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $session['title'] }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $session['mode_label'] }} · {{ $session['source_label'] }}
                                            @if ($workspace['is_all']) · {{ $session['workspace_name'] }}@endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $session['participants'] === [] ? 'No participants' : implode(', ', $session['participants']) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $session['owner'] }}</td>
                                    <td class="px-4 py-3 text-right text-xs text-gray-500 dark:text-gray-400">
                                        {{ $session['updated_at']?->diffForHumans() ?? 'Unknown' }}
                                        @if ($session['can_open_chat'] && $session['uuid'])
                                            <div><a href="{{ $chatUrl($session['uuid']) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Open chat</a></div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No bot sessions in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Usage --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Usage</h3>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ number_format($usage['total_requests']) }} requests</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($usage['total_tokens']) }} tokens · {{ $money($usage['total_cost_micros']) }} estimated
                </p>
                <div class="mt-4 space-y-3">
                    @forelse ($usage['bots'] as $row)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="truncate font-medium text-gray-700 dark:text-gray-200">{{ $row['bot_name'] }}</span>
                                <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ number_format($row['tokens']) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-500" style="width: {{ $row['bar_percent'] }}%"></div>
                            </div>
                            @if ($workspace['is_all'])
                                <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">{{ $row['workspace_name'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">No token usage recorded in this period.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    {{-- Per-bot read-only slide-over --}}
    @if ($selected_bot)
        @php($detail = $selected_bot['bot'])
        @php($maxUsage = max(1, (int) collect($selected_bot['usage_daily'])->max('tokens')))
        <div class="fixed inset-0 z-40 bg-gray-950/50 backdrop-blur-sm" wire:click="closeBot" aria-hidden="true"></div>

        <aside class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col overflow-hidden bg-white shadow-2xl dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="bot-detail-title" x-on:keydown.escape.window="$wire.closeBot()">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $detail['is_coordinator'] ? 'bg-warning-500 text-white' : 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-200' }} text-base font-semibold">{{ $detail['initials'] }}</span>
                    <div class="min-w-0">
                        <h2 id="bot-detail-title" class="truncate text-lg font-semibold text-gray-950 dark:text-white">{{ $detail['name'] }}</h2>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $detail['title'] ?: $detail['kind_label'] }}</p>
                        <div class="mt-1 flex flex-wrap gap-1.5 text-[11px]">
                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $detail['is_active'] ? $toneClasses('success') : $toneClasses('danger') }}">{{ $detail['is_active'] ? 'Active' : 'Inactive' }}</span>
                            <span class="rounded-full bg-gray-50 px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $detail['kind_label'] }}</span>
                            <span class="rounded-full bg-gray-50 px-2 py-0.5 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $detail['workspace_name'] }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="closeBot" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-200" aria-label="Close bot details">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <p class="text-gray-500 dark:text-gray-400">Model</p>
                        <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail['model_label'] }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <p class="text-gray-500 dark:text-gray-400">Memory</p>
                        <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail['memory_scope'] }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <p class="text-gray-500 dark:text-gray-400">Tools</p>
                        <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail['tools_count'] }} allowed</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <p class="text-gray-500 dark:text-gray-400">Runs today</p>
                        <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail['runs_today'] }} / {{ $detail['max_daily_runs'] }}</p>
                    </div>
                </div>

                @if ($detail['drift_warnings'] !== [])
                    <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-xs text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-200">
                        <p class="font-semibold">Policy drift</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            @foreach ($detail['drift_warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Policy changes stay on the control-center editor.</p>
                    <a href="{{ $edit_bot_url($detail['id']) }}" class="shrink-0 text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Edit bot</a>
                </div>

                <section>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent assistant messages</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Short excerpts only. Private memory and raw approval payloads are never shown.</p>
                    <div class="mt-2 space-y-2">
                        @forelse ($selected_bot['recent_messages'] as $message)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/5">
                                <p class="text-xs text-gray-700 dark:text-gray-200">{{ $message['excerpt'] }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-gray-400 dark:text-gray-500">
                                    <span>{{ $message['source_label'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $message['session_title'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $message['created_at']?->diffForHumans() ?? 'Unknown time' }}</span>
                                    @if ($message['can_open_chat'] && $message['session_uuid'])
                                        <a href="{{ $chatUrl($message['session_uuid']) }}" class="font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Open chat</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">No assistant messages yet.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent handoffs</h3>
                    <div class="mt-2 space-y-2">
                        @forelse ($selected_bot['handoffs'] as $handoff)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-950 dark:text-white">{{ $handoff['from'] }} → {{ $handoff['to'] }}</p>
                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $toneClasses(match ($handoff['status']) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'queued', 'running' => 'warning',
                                        default => 'gray',
                                    }) }}">{{ $statusLabel($handoff['status']) }}</span>
                                </div>
                                <p class="mt-1 line-clamp-2 text-[11px] text-gray-500 dark:text-gray-400">{{ $handoff['task'] }}</p>
                                <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $handoff['duration'] }} · depth {{ $handoff['depth'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">No handoffs yet.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Routines</h3>
                    <div class="mt-2 space-y-2">
                        @forelse ($selected_bot['routines'] as $routine)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-950 dark:text-white">{{ $routine['name'] }}</p>
                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $toneClasses(match ($routine['last_status']) {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'skipped' => 'warning',
                                        default => 'gray',
                                    }) }}">{{ $routine['last_status'] ? $statusLabel($routine['last_status']) : 'Never' }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $routine['cron'] }} · next {{ $routine['next_run_at']?->diffForHumans() ?? 'invalid schedule' }} · {{ $routine['runs_today'] }} / {{ $routine['max_daily_runs'] }} today
                                </p>
                                @if ($routine['last_error'])
                                    <p class="mt-1 line-clamp-2 text-[11px] text-danger-600 dark:text-danger-400">{{ $routine['last_error'] }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">No routines configured.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent sessions</h3>
                    <div class="mt-2 space-y-2">
                        @forelse ($selected_bot['sessions'] as $session)
                            <div class="rounded-lg border border-gray-100 px-3 py-2 dark:border-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-950 dark:text-white">{{ $session['title'] }}</p>
                                    <span class="shrink-0 text-[10px] text-gray-400 dark:text-gray-500">{{ $session['updated_at']?->diffForHumans() ?? 'Unknown' }}</span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $session['mode_label'] }} · {{ $session['source_label'] }} · {{ $session['message_count'] }} messages · {{ $session['owner'] }}
                                </p>
                                @if ($session['can_open_chat'] && $session['uuid'])
                                    <a href="{{ $chatUrl($session['uuid']) }}" class="mt-1 inline-block text-[11px] font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Open chat</a>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">No sessions yet.</p>
                        @endforelse
                    </div>
                </section>

                <section>
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Usage</h3>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($detail['usage_tokens']) }} tokens · {{ $money($detail['usage_cost_micros']) }}</span>
                    </div>
                    <div class="mt-2 flex h-16 items-end gap-1">
                        @foreach ($selected_bot['usage_daily'] as $day)
                            <div class="group relative flex-1">
                                <div class="rounded-t bg-primary-400/70 dark:bg-primary-500/60" style="height: {{ max(2, (int) round(($day['tokens'] / $maxUsage) * 64)) }}px" title="{{ $day['date'] }}: {{ number_format($day['tokens']) }} tokens"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 flex justify-between text-[10px] text-gray-400 dark:text-gray-500">
                        <span>{{ $selected_bot['usage_daily'][0]['date'] ?? '' }}</span>
                        <span>{{ $selected_bot['usage_daily'][count($selected_bot['usage_daily']) - 1]['date'] ?? '' }}</span>
                    </div>
                </section>
            </div>
        </aside>
    @endif
</x-filament-panels::page>
