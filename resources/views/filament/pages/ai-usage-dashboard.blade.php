<x-filament-panels::page>
    @php
        $money = fn (int $micros): string => '$'.number_format($micros / 1_000_000, 4);
        $tokens = fn (int $value): string => number_format($value);
        $periodCards = ['Daily' => $daily, 'Monthly' => $monthly];
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $platformMode ? 'Platform-wide AI usage' : 'Workspace AI budget' }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $estimatedNotice }}</p>
                </div>
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' => $budgetEnabled === true,
                    'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' => $budgetEnabled !== true,
                ])>
                    {{ $platformMode ? 'Per-workspace enforcement' : ($budgetEnabled ? 'Budget enforcement enabled' : 'Budget enforcement disabled') }}
                </span>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($periodCards as $label => $period)
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $label }} budget</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($period['request_count']) }} completed · {{ number_format($period['blocked_count']) }} blocked
                            </p>
                        </div>
                        <span class="rounded-lg bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                            {{ number_format($period['reserved_tokens']) }} tokens reserved
                        </span>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Tokens committed</span>
                                <strong class="text-gray-950 dark:text-white">
                                    {{ $tokens($period['committed_tokens']) }}
                                    @if ($period['token_limit']) / {{ $tokens($period['token_limit']) }} @endif
                                </strong>
                            </div>
                            @if ($period['token_percent'] !== null)
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ $period['token_percent'] }}%"></div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Estimated cost committed</span>
                                <strong class="text-gray-950 dark:text-white">
                                    {{ $money($period['committed_cost_micros']) }}
                                    @if ($period['cost_limit_micros']) / {{ $money($period['cost_limit_micros']) }} @endif
                                </strong>
                            </div>
                            @if ($period['cost_percent'] !== null)
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-warning-500" style="width: {{ $period['cost_percent'] }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Usage by feature this month</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr><th class="px-4 py-3">Feature</th><th class="px-4 py-3">Requests</th><th class="px-4 py-3">Tokens</th><th class="px-4 py-3">Est. cost</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($features as $feature)
                                <tr><td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ ucfirst($feature['feature']) }}</td><td class="px-4 py-3">{{ number_format($feature['requests']) }}</td><td class="px-4 py-3">{{ $tokens($feature['input_tokens'] + $feature['output_tokens']) }}</td><td class="px-4 py-3">{{ $money($feature['cost_micros']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No AI usage recorded this month.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Usage by provider and model</h3>
                </div>
                <div class="max-h-96 overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr><th class="px-4 py-3">Provider / model</th><th class="px-4 py-3">Requests</th><th class="px-4 py-3">Tokens</th><th class="px-4 py-3">Est. cost</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($providers as $provider)
                                <tr><td class="px-4 py-3"><div class="font-medium text-gray-950 dark:text-white">{{ $provider['provider'] }}</div><div class="max-w-xs truncate text-xs text-gray-500">{{ $provider['model'] ?: 'Default model' }}</div></td><td class="px-4 py-3">{{ number_format($provider['requests']) }}</td><td class="px-4 py-3">{{ $tokens($provider['tokens']) }}</td><td class="px-4 py-3">{{ $money($provider['cost_micros']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No provider usage recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @if ($platformMode)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10"><h3 class="font-semibold text-gray-950 dark:text-white">Workspace usage this month</h3></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400"><tr><th class="px-4 py-3">Workspace</th><th class="px-4 py-3">Requests</th><th class="px-4 py-3">Tokens</th><th class="px-4 py-3">Est. cost</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">@forelse ($workspaces as $workspace)<tr><td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $workspace['workspace'] }}</td><td class="px-4 py-3">{{ number_format($workspace['requests']) }}</td><td class="px-4 py-3">{{ $tokens($workspace['tokens']) }}</td><td class="px-4 py-3">{{ $money($workspace['cost_micros']) }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No workspace usage recorded.</td></tr>@endforelse</tbody></table></div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10"><h3 class="font-semibold text-gray-950 dark:text-white">Recent budget events</h3></div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($events as $event)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div><span class="font-semibold text-gray-950 dark:text-white">{{ ucfirst($event['event']) }}</span><span class="ml-2 text-gray-500">{{ ucfirst($event['feature']) }} · {{ $event['provider'] ?: 'No provider' }}@if ($platformMode && $event['workspace']) · {{ $event['workspace'] }}@endif</span></div>
                        <div class="text-xs text-gray-500">{{ $event['reason'] ? str_replace('_', ' ', $event['reason']) : '' }} · {{ $event['created_at'] }}</div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No warnings, blocks, releases, or fallbacks recorded.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
