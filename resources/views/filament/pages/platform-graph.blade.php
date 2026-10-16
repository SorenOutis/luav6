<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $hasGraph = $this->hasGraph;
        $report = $this->reportMarkdown;
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
                        <div class="mt-1 text-xs text-gray-400">Code symbols & files</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Edges</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {{ number_format($stats['edges']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">AST & call relations</div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Communities</div>
                        <div class="mt-1 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ number_format($stats['communities']) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-400">Leiden cluster partitions</div>
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
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $stats['commit'] ? 'Commit '.$stats['commit'] : ($stats['last_updated'] ? date('M j, H:i', strtotime($stats['last_updated'])) : 'Ready') }}
                        </div>
                        <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Graphify v0.9+</div>
                    </div>
                </div>

                {{-- Top Hub / God Nodes --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Core Architectural Hubs (God Nodes)
                        </h3>
                        <span class="text-xs text-gray-400">Ranked by total connected edges</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($stats['top_nodes'] as $node)
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-800 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <span class="font-semibold">{{ $node['name'] }}</span>
                                <span class="rounded bg-primary-100 px-1.5 py-0.2 text-[10px] font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                    {{ $node['edges'] }}
                                </span>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- View Selection Tabs and Action Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 dark:border-white/10 pb-3">
            <nav class="flex flex-wrap gap-2">
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
                    <span>Community Tree</span>
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
            {{-- Standard / Max Canvas Views --}}
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
                            {{ number_format($stats['nodes']) }} nodes &bull; {{ number_format($stats['communities']) }} communities
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

                {{-- The Iframe Display --}}
                @if ($this->activeTab === 'network' || $this->activeTab === 'full')
                    <iframe
                        src="{{ route('admin.graphify.view', ['type' => 'network']) }}"
                        :class="{
                            'h-full w-full flex-1 border-0 rounded-lg': isExpanded,
                            'w-full border-0 rounded-2xl transition-all': !isExpanded,
                            'h-[calc(100vh-175px)] min-h-[750px]': !isExpanded && '{{ $this->activeTab }}' === 'full',
                            'h-[820px]': !isExpanded && '{{ $this->activeTab }}' === 'network'
                        }"
                        title="Graphify Architecture Network"
                        loading="lazy"
                    ></iframe>
                @elseif ($this->activeTab === 'tree')
                    <iframe
                        src="{{ route('admin.graphify.view', ['type' => 'tree']) }}"
                        :class="{
                            'h-full w-full flex-1 border-0 rounded-lg': isExpanded,
                            'w-full h-[820px] border-0 rounded-2xl': !isExpanded
                        }"
                        title="Graphify Community Tree"
                        loading="lazy"
                    ></iframe>
                @elseif ($this->activeTab === 'report')
                    <div class="p-6">
                        <div class="prose max-w-none dark:prose-invert">
                            <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl bg-gray-50 p-4 font-mono text-xs leading-relaxed text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $report }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
