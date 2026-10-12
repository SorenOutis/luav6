<x-filament-panels::page>
    <x-filament::section heading="Nightly schedule">
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <p>
                Run starts daily at <strong class="text-gray-950 dark:text-white">{{ $reportTime }}</strong>
                in <strong class="text-gray-950 dark:text-white">{{ $reportTimezone }}</strong> (application timezone).
            </p>
            <p>Each report covers the previous complete local day. Email arrives later, after processing completes.</p>
            <p>Timezone is set by the application environment. Start time can be customized below. Reports remain disabled until configured and enabled.</p>
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
            Save Settings
        </x-filament::button>
    </form>

    <x-filament::section heading="Recent reports" description="Latest 10 persisted reports. Click 'View preview' on completed reports to inspect the AI summary, findings, and recommendations.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Recent AI daily reports and email delivery status</caption>
                <thead class="text-gray-600 dark:text-gray-400">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th scope="col" class="px-3 py-3 font-medium">Report day</th>
                        <th scope="col" class="px-3 py-3 font-medium">Timezone</th>
                        <th scope="col" class="px-3 py-3 font-medium">Status</th>
                        <th scope="col" class="px-3 py-3 font-medium">Provider / model</th>
                        <th scope="col" class="px-3 py-3 font-medium">Email delivery</th>
                        <th scope="col" class="px-3 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-950 dark:divide-gray-700 dark:text-white">
                    @forelse ($recentReports as $report)
                        <tr wire:key="daily-report-row-{{ $report->id }}" x-data="{ expanded: false }">
                            <td colspan="6" class="p-0">
                                <table class="w-full text-left text-sm">
                                    <tr>
                                        <td class="w-1/6 whitespace-nowrap px-3 py-3 font-medium">{{ $report->report_date->format('Y-m-d') }}</td>
                                        <td class="w-1/6 whitespace-nowrap px-3 py-3">{{ $report->timezone }}</td>
                                        <td class="w-1/6 px-3 py-3">
                                            <div class="space-y-1">
                                                <x-filament::badge :color="match ($report->status) {
                                                    'completed' => 'success',
                                                    'failed' => 'danger',
                                                    'incomplete' => 'warning',
                                                    default => 'gray',
                                                }">
                                                    {{ ucfirst($report->status) }}
                                                </x-filament::badge>
                                                @if ($report->error_code)
                                                    <p class="text-xs text-danger-600 dark:text-danger-400">Failure: {{ $report->error_code }}</p>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="w-1/4 px-3 py-3">
                                            <div class="space-y-1">
                                                <p class="truncate font-mono text-xs">{{ $report->provider }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $report->model ?? 'Not selected' }}</p>
                                            </div>
                                        </td>
                                        <td class="w-1/6 px-3 py-3">
                                            @if ($report->delivery_failed_at)
                                                <p class="text-danger-600 dark:text-danger-400">Delivery failed</p>
                                                <p class="text-xs text-danger-600 dark:text-danger-400">{{ $report->delivery_error_code ?? 'Unknown failure' }}</p>
                                            @elseif ($report->email_sent_at)
                                                <p>Sent {{ $report->email_sent_at->timezone($reportTimezone)->format('Y-m-d H:i') }} ({{ $reportTimezone }})</p>
                                            @else
                                                <p class="text-gray-500 dark:text-gray-400">Not sent</p>
                                            @endif
                                        </td>
                                        <td class="w-1/6 px-3 py-3 text-right">
                                            <div class="inline-flex items-center justify-end gap-3">
                                                <button
                                                    type="button"
                                                    wire:click="rerunAndSend({{ $report->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="rerunAndSend({{ $report->id }})"
                                                    class="inline-flex items-center gap-1 text-xs font-semibold text-warning-600 hover:text-warning-500 dark:text-warning-400 cursor-pointer disabled:opacity-50"
                                                    title="Re-analyze and resend report"
                                                >
                                                    <x-filament::icon
                                                        icon="heroicon-m-arrow-path"
                                                        class="h-3.5 w-3.5"
                                                        wire:loading.class="animate-spin"
                                                        wire:target="rerunAndSend({{ $report->id }})"
                                                    />
                                                    <span wire:loading.remove wire:target="rerunAndSend({{ $report->id }})">Resend</span>
                                                    <span wire:loading wire:target="rerunAndSend({{ $report->id }})">Sending...</span>
                                                </button>

                                                @if ($report->status === 'completed' && isset($report->analysis['summary']))
                                                    <button
                                                        type="button"
                                                        @click="expanded = !expanded"
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 cursor-pointer"
                                                    >
                                                        <span x-text="expanded ? 'Hide' : 'Summary'"></span>
                                                        <x-filament::icon
                                                            icon="heroicon-m-chevron-down"
                                                            class="h-4 w-4 transition-transform duration-200"
                                                            x-bind:class="{ 'rotate-180': expanded }"
                                                        />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @if ($report->status === 'completed' && isset($report->analysis['summary']))
                                        <tr x-show="expanded" x-cloak class="bg-gray-50/75 dark:bg-white/5 border-t border-gray-100 dark:border-gray-800">
                                            <td colspan="6" class="px-4 py-4">
                                                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                                    <!-- Summary -->
                                                    <div>
                                                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">AI Summary</h4>
                                                        <p class="mt-1 text-sm text-gray-800 dark:text-gray-200 leading-relaxed font-normal">{{ $report->analysis['summary'] }}</p>
                                                    </div>

                                                    <!-- Key Highlights -->
                                                    @if (! empty($report->analysis['findings']))
                                                        <div>
                                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Key Highlights</h4>
                                                            <ul class="mt-1.5 space-y-1 text-xs text-gray-700 dark:text-gray-300">
                                                                @foreach ($report->analysis['findings'] as $finding)
                                                                    <li class="flex items-start gap-1.5">
                                                                        <span class="mt-1 inline-block h-1.5 w-1.5 rounded-full {{ match($finding['severity'] ?? 'info') {
                                                                            'critical' => 'bg-danger-500',
                                                                            'warning' => 'bg-warning-500',
                                                                            default => 'bg-info-500',
                                                                        } }}"></span>
                                                                        <span><strong>{{ $finding['title'] ?? '' }}</strong>@if (! empty($finding['detail'])): {{ $finding['detail'] }}@endif</span>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif

                                                    <!-- Section Leaderboards -->
                                                    @if (! empty($report->snapshot['section_leaderboards']))
                                                        <div>
                                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Section Leaderboards</h4>
                                                            <div class="mt-1.5 overflow-x-auto">
                                                                <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300">
                                                                    <thead>
                                                                        <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-500">
                                                                            <th class="py-1 pr-3 font-medium">Section</th>
                                                                            <th class="py-1 pr-3 font-medium">Top Student</th>
                                                                            <th class="py-1 pr-3 font-medium">Level</th>
                                                                            <th class="py-1 font-medium">EXP</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                                        @foreach ($report->snapshot['section_leaderboards'] as $leader)
                                                                            <tr>
                                                                                <td class="py-1.5 pr-3 font-medium">{{ $leader['section_name'] }}</td>
                                                                                <td class="py-1.5 pr-3">{{ $leader['top_student_name'] ?? 'No active students' }}</td>
                                                                                <td class="py-1.5 pr-3">Lvl {{ $leader['level'] }}</td>
                                                                                <td class="py-1.5">{{ number_format($leader['exp']) }} EXP</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Usage & Cost Footer -->
                                                    <div class="flex flex-wrap items-center gap-4 border-t border-gray-100 pt-2 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                                        <span>Provider: <strong class="text-gray-700 dark:text-gray-300">{{ $report->provider }}</strong></span>
                                                        @if ($report->input_tokens !== null)
                                                            <span>Tokens: <strong class="text-gray-700 dark:text-gray-300">{{ number_format($report->input_tokens + ($report->output_tokens ?? 0)) }}</strong></span>
                                                        @endif
                                                        @if ($report->estimated_cost_micros !== null)
                                                            <span>Est. cost: <strong class="text-gray-700 dark:text-gray-300">${{ number_format($report->estimated_cost_micros / 1000000, 4) }} USD</strong></span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No daily reports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
