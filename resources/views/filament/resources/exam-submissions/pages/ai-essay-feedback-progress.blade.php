<x-filament-panels::page>
    @php
        /** @var \App\Models\ExamAiFeedbackRun|null $run */
        $run = $this->run;
        $total = $run?->total_essays ?? 0;
        $processed = $run?->processed_essays ?? 0;
        $skipped = $run?->skipped_essays ?? 0;
        $done = $processed + $skipped;
        $pct = $total > 0 ? (int) floor(($done / $total) * 100) : 0;
        $breakdown = $this->partBreakdown;
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <div class="text-xs font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                        Live progress refreshes every 2 seconds
                    </div>
                    <div class="text-xl font-bold">
                        {{ $this->exam->title }}
                    </div>
                    @if ($this->exam->section?->name)
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Section: <span class="font-semibold">{{ $this->exam->section->name }}</span>
                        </div>
                    @endif
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Status: <span class="font-semibold">{{ $run?->status ?? '—' }}</span>
                        @if($run?->last_error)
                            <span class="ml-2 text-red-600 dark:text-red-400">({{ $run->last_error }})</span>
                        @endif
                    </div>
                </div>

                <div class="min-w-[260px] space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">Essay feedback</span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $done }}/{{ $total }} ({{ $pct }}%)</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Processed: <span class="font-semibold">{{ $processed }}</span>
                        <span class="mx-2">·</span>
                        Skipped: <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $skipped }}</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-blue-600 to-emerald-500 transition-[width] duration-300"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Current: <span class="font-semibold">{{ $run?->current_part_title ?? '—' }}</span>
                        <span class="mx-2">·</span>
                        <span class="font-semibold">{{ $run?->current_user_name ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 text-sm font-semibold">Part breakdown</div>
                <div class="space-y-3">
                    @forelse ($breakdown as $row)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ $row['part_title'] }}</div>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="rounded-full bg-amber-500/15 px-2 py-1 font-semibold text-amber-600 dark:text-amber-400">
                                        pending_ai: {{ $row['pending_ai'] }}
                                    </span>
                                    <span class="rounded-full bg-blue-500/15 px-2 py-1 font-semibold text-blue-600 dark:text-blue-400">
                                        review: {{ $row['pending_review'] }}
                                    </span>
                                    <span class="rounded-full bg-gray-500/10 px-2 py-1 font-semibold text-gray-600 dark:text-gray-300">
                                        submitted: {{ $row['submitted'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            No submissions found yet for this exam.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 text-sm font-semibold">Runs</div>
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>

