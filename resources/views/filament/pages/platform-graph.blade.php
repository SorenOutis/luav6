<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $hasGraph = $this->hasGraph;
        $report = $this->reportMarkdown;
        $domainDefinitions = $this->domainDefinitions;
        $domainSummary = $this->domainSummary;
        $subsystems = $this->subsystems;
        $inspectedSubsystem = $this->inspectedSubsystem;
    @endphp

    <div
        x-data="{
            isExpanded: false,
            showMetrics: true,
            toggleFullscreen() {
                this.isExpanded = !this.isExpanded;
                if (this.isExpanded) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }"
        @keydown.window.escape="if (isExpanded) { toggleFullscreen(); }"
        class="space-y-6"
    >
        {{-- Architecture Metrics Bar (collapsible to give maximum space to graph) --}}
        @if ($this->activeTab !== 'full')
            <div x-show="showMetrics" x-collapse class="space-y-4">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Nodes</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">
                            {{ number_format($stats['nodes']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">Code symbols &amp; files</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Edges</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {{ number_format($stats['edges']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">AST &amp; call relations</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Architecture Subsystems</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ number_format($stats['subsystems'] ?? $stats['communities']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">Organized domain clusters</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">AST Extraction</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-indigo-600 dark:text-indigo-400">
                            {{ $stats['extracted_pct'] }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">Deterministic code AST</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Inferred Edges</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
                            {{ number_format($stats['inferred_edges']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">Cross-boundary inferences</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Graph Freshness</div>
                        <div class="mt-1 text-sm font-bold tracking-tight text-gray-800 dark:text-gray-200 truncate">
                            {{ $stats['commit'] ? 'Commit ' . $stats['commit'] : 'Generated' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400 truncate">
                            {{ $stats['last_updated'] ? date('M j, Y H:i', strtotime($stats['last_updated'])) : 'Up to date' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Top Navigation Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3 dark:border-white/10">
            <nav class="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    wire:click="setTab('network')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeTab === 'network',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => $this->activeTab !== 'network',
                    ])
                >
                    <x-heroicon-o-share class="h-4 w-4" />
                    <span>Interactive Network</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('subsystems')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeTab === 'subsystems',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => $this->activeTab !== 'subsystems',
                    ])
                >
                    <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                    <span>Architecture Subsystems</span>
                    <span class="ml-1 rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                        {{ number_format($stats['subsystems'] ?? $stats['communities']) }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('full')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeTab === 'full',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => $this->activeTab !== 'full',
                    ])
                >
                    <x-heroicon-o-arrows-pointing-out class="h-4 w-4" />
                    <span>Max Canvas View</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('tree')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeTab === 'tree',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => $this->activeTab !== 'tree',
                    ])
                >
                    <x-heroicon-o-folder class="h-4 w-4" />
                    <span>Community Tree &amp; Hierarchy</span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('report')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeTab === 'report',
                        'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => $this->activeTab !== 'report',
                    ])
                >
                    <x-heroicon-o-document-text class="h-4 w-4" />
                    <span>Architecture Report</span>
                </button>
            </nav>

            <div class="flex items-center gap-2">
                @if ($this->activeTab === 'network')
                    <button
                        type="button"
                        @click="showMetrics = !showMetrics"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-adjustments-horizontal class="h-3.5 w-3.5" />
                        <span x-text="showMetrics ? 'Hide Metrics' : 'Show Metrics'"></span>
                    </button>
                @endif

                @if ($this->activeTab === 'network' || $this->activeTab === 'full')
                    <button
                        type="button"
                        @click="toggleFullscreen()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-white dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-arrows-pointing-out class="h-3.5 w-3.5 text-primary-500" />
                        <span>Theater Expand</span>
                    </button>

                    <a
                        href="{{ route('admin.graphify.view', ['type' => $this->activeTab === 'tree' ? 'tree' : 'network']) }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
                        <span>Open Standalone ↗</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Main View Container --}}
        @if (! $hasGraph)
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center dark:border-white/10">
                <x-heroicon-o-cpu-chip class="h-12 w-12 text-gray-400" />
                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">Architecture Graph Not Generated</h3>
                <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    Click the "Regenerate Graph" button above or run <code>php artisan graphify:generate</code> in your terminal to extract the codebase AST.
                </p>
            </div>
        @else
            {{-- Standard / Max Canvas / Subsystems Views --}}
            <div
                :class="{
                    'fixed inset-0 z-[100] w-screen h-screen bg-gray-950 p-3 flex flex-col': isExpanded,
                    'relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900': !isExpanded
                }"
            >
                {{-- Theater Mode Header (only visible when expanded) --}}
                <div x-show="isExpanded" class="flex items-center justify-between pb-2 mb-2 border-b border-white/10 text-white">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-2 text-sm font-bold text-primary-400">
                            <x-heroicon-o-share class="h-4 w-4" />
                            <span>Platform Architecture Graph (Theater Mode)</span>
                        </span>
                        <span class="rounded bg-white/10 px-2 py-0.5 text-xs text-gray-300">
                            {{ number_format($stats['nodes']) }} nodes &bull; {{ number_format($stats['subsystems'] ?? $stats['communities']) }} subsystems
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Press <kbd class="px-1.5 py-0.5 rounded bg-white/10 font-mono">Esc</kbd> to exit</span>
                        <button
                            type="button"
                            @click="toggleFullscreen()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-semibold text-white transition"
                        >
                            <x-heroicon-o-arrows-pointing-in class="h-4 w-4" />
                            <span>Exit Theater Mode</span>
                        </button>
                    </div>
                </div>

                {{-- The Iframe Display for Network / Full Canvas --}}
                @if ($this->activeTab === 'network' || $this->activeTab === 'full')
                    <iframe
                        src="{{ route('admin.graphify.view', array_filter(['type' => 'network', 'focus' => $this->focusSubsystemHub])) }}"
                        :class="{
                            'h-full w-full flex-1 border-0 rounded-lg': isExpanded,
                            'w-full border-0 rounded-2xl transition-all': !isExpanded,
                            'h-[calc(100vh-175px)] min-h-[750px]': !isExpanded && '{{ $this->activeTab }}' === 'full',
                            'h-[820px]': !isExpanded && '{{ $this->activeTab }}' === 'network'
                        }"
                        title="Graphify Architecture Network"
                        loading="lazy"
                    ></iframe>

                {{-- Dedicated Organized Subsystems Tab --}}
                @elseif ($this->activeTab === 'subsystems')
                    <div class="p-6 space-y-6">
                        {{-- Search and Domain Filter Controls --}}
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="relative flex-1 max-w-md">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="Search subsystems by title, hub, or keyword..."
                                    class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-8 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                                />
                                @if ($this->search !== '')
                                    <button
                                        type="button"
                                        wire:click="$set('search', '')"
                                        class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span>Showing <strong>{{ count($subsystems) }}</strong> of <strong>{{ number_format($stats['subsystems'] ?? $stats['communities']) }}</strong> subsystems</span>
                                @if ($this->selectedDomain !== 'all' || $this->search !== '')
                                    <button
                                        type="button"
                                        wire:click="$set('selectedDomain', 'all'); $set('search', '');"
                                        class="font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        Reset filters
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Domain Filter Pills --}}
                        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 pb-4 dark:border-white/5">
                            <button
                                type="button"
                                wire:click="filterDomain('all')"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                    'bg-primary-600 text-white shadow-sm' => $this->selectedDomain === 'all',
                                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $this->selectedDomain !== 'all',
                                ])
                            >
                                <x-heroicon-o-squares-2x2 class="h-3.5 w-3.5" />
                                <span>All Architecture</span>
                                <span class="rounded-full bg-black/10 px-1.5 py-0.2 text-[10px] dark:bg-white/10">
                                    {{ number_format($stats['subsystems'] ?? $stats['communities']) }}
                                </span>
                            </button>

                            @foreach ($domainDefinitions as $slug => $def)
                                @php
                                    $domainStat = $domainSummary[$slug] ?? null;
                                    $count = $domainStat['subsystems_count'] ?? 0;
                                @endphp
                                <button
                                    type="button"
                                    wire:click="filterDomain('{{ $slug }}')"
                                    @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                        'bg-primary-600 text-white shadow-sm' => $this->selectedDomain === $slug,
                                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $this->selectedDomain !== $slug,
                                    ])
                                >
                                    <x-dynamic-component :component="$def['icon']" class="h-3.5 w-3.5" />
                                    <span>{{ $def['name'] }}</span>
                                    <span class="rounded-full bg-black/10 px-1.5 py-0.2 text-[10px] dark:bg-white/10">
                                        {{ $count }}
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Domain Architecture Pillars (shown when viewing all domains without search) --}}
                        @if ($this->selectedDomain === 'all' && $this->search === '')
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Platform Architecture Domains
                                    </h4>
                                    <span class="text-xs text-gray-400">10 high-level functional pillars</span>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    @foreach ($domainSummary as $slug => $summary)
                                        <div
                                            wire:click="filterDomain('{{ $slug }}')"
                                            class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="rounded-lg p-2 {{ $summary['badge'] }}">
                                                        <x-dynamic-component :component="$summary['icon']" class="h-4 w-4" />
                                                    </div>
                                                    <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                                        {{ $summary['name'] }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mt-3 flex items-baseline justify-between">
                                                <div>
                                                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                                                        {{ $summary['subsystems_count'] }}
                                                    </span>
                                                    <span class="text-xs text-gray-400">subsystems</span>
                                                </div>
                                                <span class="text-xs font-bold text-primary-600 dark:text-primary-400">
                                                    {{ $summary['percentage'] }}
                                                </span>
                                            </div>

                                            <p class="mt-2 text-xs text-gray-500 line-clamp-2 dark:text-gray-400">
                                                {{ $summary['description'] }}
                                            </p>

                                            @if (! empty($summary['top_subsystems']))
                                                <div class="mt-3 border-t border-gray-100 pt-2 dark:border-white/5">
                                                    <div class="text-[10px] font-medium text-gray-400">Key modules:</div>
                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                        @foreach (array_slice($summary['top_subsystems'], 0, 2) as $top)
                                                            <span class="inline-block rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/5 dark:text-gray-300 truncate max-w-[140px]">
                                                                {{ $top }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Subsystem Cards Grid --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $this->selectedDomain === 'all' ? 'All Architectural Subsystems' : ($domainDefinitions[$this->selectedDomain]['name'] ?? 'Subsystems') }}
                                </h4>
                                <span class="text-xs text-gray-400">Sorted by structural node scale</span>
                            </div>

                            @if (empty($subsystems))
                                <div class="rounded-xl border border-dashed border-gray-200 p-8 text-center dark:border-white/10">
                                    <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8 text-gray-400" />
                                    <div class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No subsystems found</div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Try adjusting your search query or domain filter.</p>
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($subsystems as $sub)
                                        <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 dark:border-white/10 dark:bg-gray-800/60 dark:hover:border-white/20">
                                            <div>
                                                {{-- Header & Badges --}}
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-medium {{ $sub['badge'] }}">
                                                            {{ $sub['domain_label'] }}
                                                        </span>
                                                        <span class="ml-1.5 text-[11px] font-mono text-gray-400">#{{ $sub['id'] }}</span>
                                                    </div>

                                                    <div class="flex items-center gap-1.5">
                                                        <span class="rounded bg-primary-50 px-2 py-0.5 text-xs font-bold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                                                            {{ $sub['node_count'] }} nodes
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- Subsystem Name --}}
                                                <h3 class="mt-2.5 text-base font-bold text-gray-900 dark:text-white">
                                                    {{ $sub['name'] }}
                                                </h3>

                                                {{-- Primary Hub --}}
                                                <div class="mt-2 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <span class="font-medium">Structural Hub:</span>
                                                    <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[11px] text-gray-800 dark:bg-white/10 dark:text-gray-200 truncate max-w-[200px]">
                                                        {{ $sub['hub'] }}
                                                    </code>
                                                </div>

                                                {{-- Key Symbols Tags --}}
                                                @if (! empty($sub['top_symbols']))
                                                    <div class="mt-3 flex flex-wrap gap-1">
                                                        @foreach (array_slice($sub['top_symbols'], 0, 4) as $sym)
                                                            <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-700 dark:bg-white/5 dark:text-gray-300 truncate max-w-[130px]">
                                                                {{ $sym }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                {{-- Directory Scope --}}
                                                @if (! empty($sub['top_dirs']))
                                                    <div class="mt-2.5 text-[11px] text-gray-400 truncate">
                                                        <span class="font-medium text-gray-500 dark:text-gray-400">Dirs:</span>
                                                        {{ implode(', ', $sub['top_dirs']) }}
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Card Footer Actions --}}
                                            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-white/5">
                                                <button
                                                    type="button"
                                                    wire:click="focusInGraph('{{ addslashes($sub['name']) }}')"
                                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                                >
                                                    <x-heroicon-o-viewfinder-circle class="h-3.5 w-3.5" />
                                                    <span>Focus in Force Graph</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    wire:click="inspectSubsystem({{ $sub['id'] }})"
                                                    class="inline-flex items-center gap-1 rounded border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
                                                >
                                                    <x-heroicon-o-information-circle class="h-3.5 w-3.5 text-gray-400" />
                                                    <span>Inspect</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                {{-- D3 Collapsible Tree View --}}
                @elseif ($this->activeTab === 'tree')
                    <iframe
                        src="{{ route('admin.graphify.view', ['type' => 'tree']) }}"
                        :class="{
                            'h-full w-full flex-1 border-0 rounded-lg': isExpanded,
                            'w-full h-[820px] border-0 rounded-2xl': !isExpanded
                        }"
                        title="Graphify Architecture Hierarchy"
                        loading="lazy"
                    ></iframe>

                {{-- Architecture Report View --}}
                @elseif ($this->activeTab === 'report')
                    <div class="p-6">
                        <div class="prose max-w-none dark:prose-invert">
                            <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl bg-gray-50 p-4 font-mono text-xs leading-relaxed text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $report }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Subsystem Detail Inspector Slideover/Modal --}}
        @if ($inspectedSubsystem)
            <div
                class="fixed inset-0 z-[120] flex items-center justify-end bg-black/60 backdrop-blur-sm"
                @keydown.window.escape="$wire.closeInspection()"
            >
                <div
                    class="h-full w-full max-w-xl overflow-y-auto bg-white p-6 shadow-2xl dark:bg-gray-900 border-l border-gray-200 dark:border-white/10 flex flex-col justify-between"
                >
                    <div class="space-y-5">
                        {{-- Header --}}
                        <div class="flex items-start justify-between border-b border-gray-100 pb-4 dark:border-white/5">
                            <div>
                                <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium {{ $inspectedSubsystem['badge'] }}">
                                    {{ $inspectedSubsystem['domain_label'] }}
                                </span>
                                <h3 class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                                    {{ $inspectedSubsystem['name'] }}
                                </h3>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Subsystem #{{ $inspectedSubsystem['id'] }} &bull; {{ $inspectedSubsystem['node_count'] }} constituent AST nodes
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="closeInspection()"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10 dark:hover:text-white"
                            >
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>

                        {{-- Subsystem Metrics --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/5">
                                <div class="text-xs text-gray-400">Cohesion Density</div>
                                <div class="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                                    {{ number_format($inspectedSubsystem['cohesion'], 3) }}
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/5">
                                <div class="text-xs text-gray-400">Structural Hub</div>
                                <div class="mt-1 text-sm font-bold text-gray-900 dark:text-white truncate">
                                    {{ $inspectedSubsystem['hub'] }}
                                </div>
                            </div>
                        </div>

                        {{-- Constituent Nodes & Classes List --}}
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Constituent Components &amp; Symbols ({{ count($inspectedSubsystem['nodes'] ?? []) }})
                            </h4>

                            <div class="max-h-[460px] overflow-y-auto rounded-lg border border-gray-200 bg-gray-50/50 p-2 text-xs divide-y divide-gray-100 dark:border-white/10 dark:bg-black/30 dark:divide-white/5">
                                @forelse ($inspectedSubsystem['nodes'] ?? [] as $node)
                                    <div class="py-2 px-1 flex flex-col gap-0.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $node['label'] ?: $node['id'] }}
                                            </span>
                                            @if ($node['type'])
                                                <span class="rounded bg-gray-200 px-1 py-0.2 text-[9px] text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                                    {{ $node['type'] }}
                                                </span>
                                            @endif
                                        </div>
                                        @if ($node['file'])
                                            <span class="text-[10px] text-gray-400 font-mono truncate">
                                                {{ $node['file'] }}
                                            </span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-4 text-center text-xs text-gray-400">
                                        No individual nodes listed.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Drawer Footer --}}
                    <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-white/5">
                        <button
                            type="button"
                            wire:click="closeInspection()"
                            class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300"
                        >
                            Close Details
                        </button>

                        <button
                            type="button"
                            wire:click="focusInGraph('{{ addslashes($inspectedSubsystem['name']) }}'); closeInspection();"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-500"
                        >
                            <x-heroicon-o-viewfinder-circle class="h-4 w-4" />
                            <span>View in Force Graph</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
