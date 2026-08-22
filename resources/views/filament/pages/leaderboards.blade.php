<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters ---------------------------------------------------------------- --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <label for="leaderboard-season" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                        Season
                    </label>
                    <select
                        id="leaderboard-season"
                        wire:model.live="seasonId"
                        @disabled(empty($seasonOptions))
                        class="block w-full rounded-lg border-gray-300 bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        @foreach ($seasonOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($showWorkspaceFilter)
                    <div class="w-full sm:w-64">
                        <label for="leaderboard-workspace" class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">
                            Workspace
                        </label>
                        <select
                            id="leaderboard-workspace"
                            wire:model.live="workspaceId"
                            class="block w-full rounded-lg border-gray-300 bg-white py-2 pe-8 ps-3 text-sm text-gray-950 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="0" @selected($workspaceId === null || $workspaceId === 0)>All workspaces</option>
                            @foreach ($workspaceOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <p class="w-full text-sm text-gray-500 sm:w-auto sm:ms-auto dark:text-gray-400">
                    @if ($activeSeasonName)
                        Rankings for <strong class="font-semibold text-gray-700 dark:text-gray-200">{{ $activeSeasonName }}</strong>
                    @else
                        No season selected
                    @endif
                    <span class="block text-xs text-gray-400 dark:text-gray-500">
                        Ranked by XP earned in the selected season.
                    </span>
                </p>
            </div>
        </div>

        {{-- Section-count truncation note ------------------------------------------ --}}
        @php
            $displayedSections = collect($leaderboards)->sum(fn (array $group): int => count($group['sections']));
        @endphp

        @if ($totalSections > $displayedSections)
            <div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-xs text-warning-700 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                Showing {{ $displayedSections }} of {{ $totalSections }} sections — narrow the workspace filter to see the rest.
            </div>
        @endif

        {{-- Leaderboards ------------------------------------------------------------ --}}
        @forelse ($leaderboards as $group)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-3 dark:border-white/10 dark:bg-white/5">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        {{ $group['workspaceName'] }}
                    </h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ count($group['sections']) }} {{ Str::plural('section', count($group['sections'])) }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($group['sections'] as $section)
                        @php
                            $sectionId = (int) $section['sectionId'];
                            $expanded = isset($this->expandedSections[$sectionId]);
                            $rows = $section['users'];
                            $top = $rows->take(5);
                            $leader = $top->first();
                        @endphp

                        <div wire:key="leaderboard-section-{{ $sectionId }}" class="bg-white dark:bg-gray-900">
                            {{-- Accordion header --}}
                            <button
                                type="button"
                                wire:click="toggleSection({{ $sectionId }})"
                                aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                                class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                                    <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $section['sectionName'] }}
                                    </span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $section['totalPlayers'] }} {{ Str::plural('student', $section['totalPlayers']) }}
                                        @if ($leader)
                                            · #1 {{ $leader['name'] }} · {{ number_format($leader['xp']) }} XP
                                        @endif
                                    </span>
                                </span>

                                <x-filament::icon
                                    icon="heroicon-m-chevron-down"
                                    class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500 {{ $expanded ? 'rotate-180' : '' }}"
                                />
                            </button>

                            {{-- Section body --}}
                            @if ($expanded)
                                <div class="border-t border-gray-100 dark:border-white/5">
                                    <x-admin.leaderboard-table :rows="$rows" />

                                    @if ($section['isTruncated'])
                                        <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                                            Showing the top {{ $rows->count() }} of {{ $section['totalPlayers'] }} students.
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="border-t border-gray-100 dark:border-white/5">
                                    <x-admin.leaderboard-table :rows="$top" />

                                    @if ($section['isTruncated'])
                                        <button
                                            type="button"
                                            wire:click="toggleSection({{ $sectionId }})"
                                            class="group flex w-full items-center justify-between border-t border-gray-100 px-5 py-3 text-left transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                                        >
                                            <span class="text-xs font-medium text-primary-600 group-hover:underline dark:text-primary-400">
                                                View full leaderboard
                                            </span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                {{ $section['totalPlayers'] }} students
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-white/10 dark:bg-gray-900">
                <x-filament::icon icon="heroicon-o-trophy" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <h3 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No leaderboards to show</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    @if (empty($seasonOptions))
                        No seasons with student enrollments exist yet. Create a season, enroll students in sections, and their leaderboards will appear here.
                    @else
                        No sections have students enrolled{{ $activeSeasonName ? ' for '.$activeSeasonName : ' in the selected season' }}.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
