<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-primary-500" />
                Recent Activity
            </div>
        </x-slot>
        <x-slot name="description">
            Last 24 hours — registrations, exams, badges, assignments. Auto-refreshes every 60s.
        </x-slot>

        @if ($activities->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <div class="rounded-full bg-gray-100 dark:bg-white/5 p-3">
                    <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6 text-gray-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">No activity yet</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Student activity will appear here in real-time.</p>
                </div>
            </div>
        @else
            <div class="activity-feed-timeline">
                @foreach ($activities as $activity)
                    @php
                        $badgeColor = match ($activity['type']) {
                            'registration' => 'success',
                            'exam' => 'info',
                            'badge' => 'warning',
                            'assignment' => 'primary',
                            default => 'gray',
                        };
                        $typeLabel = match ($activity['type']) {
                            'registration' => 'Registration',
                            'exam' => 'Exam',
                            'badge' => 'Badge',
                            'assignment' => 'Assignment',
                            default => ucfirst($activity['type']),
                        };
                        $dotColor = match ($activity['type']) {
                            'registration' => 'bg-emerald-500 shadow-emerald-500/20',
                            'exam' => 'bg-sky-500 shadow-sky-500/20',
                            'badge' => 'bg-amber-500 shadow-amber-500/20',
                            'assignment' => 'bg-violet-500 shadow-violet-500/20',
                            default => 'bg-gray-400',
                        };
                    @endphp
                    <div class="activity-feed-item group">
                        <div class="activity-feed-item__dot {{ $dotColor }}"></div>
                        <div class="activity-feed-item__content">
                            <div class="flex items-center gap-2 flex-wrap">
                                <x-filament::badge :color="$badgeColor" size="sm">{{ $typeLabel }}</x-filament::badge>
                                <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3" />
                                    {{ $activity['human_time'] ?? $activity['timestamp'] }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-baseline gap-1.5 flex-wrap">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ $activity['user_name'] }}
                                </span>
                                <span class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ $activity['description'] }}
                                </span>
                            </div>
                        </div>
                        <div class="activity-feed-item__icon">
                            <x-filament::icon :icon="$activity['icon']" class="h-4 w-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/5 flex items-center justify-between">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Showing {{ $activities->count() }} most recent events
                </span>
                <a href="/admin/users" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                    View all users →
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
