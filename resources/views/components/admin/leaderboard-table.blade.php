@props([
    'rows',
    'emptyMessage' => 'No students enrolled in this section yet.',
])

<div class="overflow-x-auto">
    <table class="w-full min-w-[680px] text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="w-14 px-5 py-2.5 font-medium">#</th>
                <th class="px-3 py-2.5 font-medium">Student</th>
                <th class="px-3 py-2.5 font-medium">Level</th>
                <th class="px-3 py-2.5 text-right font-medium">XP</th>
                <th class="px-3 py-2.5 text-right font-medium">XP this week</th>
                <th class="px-3 py-2.5 text-right font-medium">Streak</th>
                <th class="w-16 px-5 py-2.5 text-center font-medium">Trend</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($rows as $row)
                @php
                    $rank = $loop->iteration;
                    $avatar = $row['avatar'] ?? null;
                    $trend = $row['trend'] ?? 'stable';
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="px-5 py-3">
                        @if ($rank <= 3)
                            <span @class([
                                'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white',
                                'bg-amber-400' => $rank === 1,
                                'bg-gray-300 text-gray-700' => $rank === 2,
                                'bg-orange-400' => $rank === 3,
                            ])>{{ $rank }}</span>
                        @else
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ $rank }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            @if ($avatar)
                                <img src="{{ $avatar }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" />
                            @else
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500 dark:bg-white/10 dark:text-gray-300">
                                    {{ strtoupper(mb_substr($row['name'], 0, 1)) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Joined {{ $row['joinedAt'] ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                            Lv {{ $row['level'] }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($row['xp']) }}</div>
                        <div class="ml-auto mt-1 h-1 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-500" style="width: {{ $row['xpProgress'] }}%"></div>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">+{{ number_format($row['weeklyXp']) }}</td>
                    <td class="px-3 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">
                        @if (($row['streak'] ?? 0) > 0)
                            <span class="inline-flex items-center gap-1">
                                <x-filament::icon icon="heroicon-o-fire" class="h-4 w-4 text-orange-500" />
                                {{ $row['streak'] }}
                            </span>
                        @else
                            <span class="text-gray-400 dark:text-gray-500">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if ($trend === 'up')
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" class="mx-auto h-4 w-4 text-success-500" />
                        @elseif ($trend === 'down')
                            <x-filament::icon icon="heroicon-m-arrow-trending-down" class="mx-auto h-4 w-4 text-danger-500" />
                        @else
                            <x-filament::icon icon="heroicon-m-minus" class="mx-auto h-4 w-4 text-gray-400 dark:text-gray-500" />
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
