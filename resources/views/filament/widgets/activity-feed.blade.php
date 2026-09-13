<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent Activity</x-slot>
        <x-slot name="description">Last 24 hours across registrations, exams, badges, and assignments.</x-slot>

        @if ($activities->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <x-filament::icon icon="heroicon-o-clock" class="h-8 w-8 text-gray-400" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No activity in the last 24 hours.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-3 py-2 text-left font-medium">When</th>
                            <th class="px-3 py-2 text-left font-medium">Type</th>
                            <th class="px-3 py-2 text-left font-medium">User</th>
                            <th class="px-3 py-2 text-left font-medium">Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
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
                            @endphp
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-3 py-3 text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon :icon="$activity['icon']" class="h-4 w-4 text-gray-400" />
                                        <span>{{ $activity['timestamp'] }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <x-filament::badge :color="$badgeColor" size="sm">{{ $typeLabel }}</x-filament::badge>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ $activity['user_name'] }}
                                </td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $activity['description'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
