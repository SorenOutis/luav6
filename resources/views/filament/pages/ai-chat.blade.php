<div
    x-data="adminAiChat({
        csrfToken: '{{ csrf_token() }}',
        provider: '{{ $provider }}',
        providerLabel: '{{ $providerLabel }}',
        modelName: '{{ $modelName }}',
        workspace: {{ Js::from($workspace) }},
        adminUser: {{ Js::from($adminUser) }},
        initialSessions: {{ Js::from($initialSessions) }},
        initialActiveSession: {{ Js::from($initialActiveSession) }},
    })"
    class="relative flex h-dvh w-full overflow-hidden bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100"
>
        <div class="sr-only" aria-live="polite" x-text="isStreaming ? 'Echo is responding' : (aiActions.some(a => a.status === 'pending') ? 'An action is waiting for your approval.' : '')"></div>

        {{-- Left Sidebar: Conversations & History -------------------------------- --}}
        <aside
            :class="sidebarOpen ? 'w-72 translate-x-0' : 'w-0 -translate-x-full md:w-0 md:translate-x-0'"
            class="absolute inset-y-0 start-0 z-40 flex flex-col border-r border-zinc-200/90 bg-zinc-50/70 transition-all duration-200 ease-in-out md:static md:z-auto dark:border-zinc-800 dark:bg-zinc-900/60"
            style="min-width: 0;"
        >
            <div x-show="sidebarOpen" class="flex h-full flex-col p-3" style="width: 18rem;">
                {{-- Sidebar Header & New Chat button --}}
                <div class="mb-2.5 flex items-center justify-between gap-1.5">
                    <button
                        type="button"
                        @click="newChat()"
                        class="flex flex-1 items-center justify-between rounded-xl border border-zinc-200/90 bg-white px-3 py-2 text-xs font-medium text-zinc-800 shadow-2xs transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 text-zinc-500 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>New Chat</span>
                        </span>
                        <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-mono text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">+</span>
                    </button>

                    <button
                        type="button"
                        @click="sidebarOpen = false"
                        class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-200/60 hover:text-zinc-700 md:hidden dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        title="Close sidebar" aria-label="Close sidebar"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if (\Filament\Facades\Filament::auth()->user()?->isSuperAdmin())
                    <div class="mb-2.5 rounded-xl border border-zinc-200/90 bg-white p-3 text-xs shadow-2xs dark:border-zinc-800 dark:bg-zinc-900" data-ai-app-install>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Standalone App</span>
                            <button type="button" data-ai-app-install-button class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 transition hover:bg-amber-500/20 dark:bg-amber-400/10 dark:text-amber-400">Install {{ $assistantName ?? 'Echo' }}</button>
                        </div>
                        <p data-ai-app-install-help class="mt-2 text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400" role="status" hidden></p>
                    </div>
                @endif

                {{-- Search Filter --}}
                <div class="relative mb-2.5">
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input.debounce.300ms="fetchSearchResults()"
                        placeholder="Search chats..."
                        class="w-full rounded-xl border border-zinc-200/90 bg-white py-1.5 pe-3 ps-8 text-xs text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:border-zinc-600"
                    />
                    <svg class="pointer-events-none absolute start-2.5 top-2.5 h-3.5 w-3.5 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button
                        x-show="searchQuery"
                        @click="searchQuery = ''; searchResults = [];"
                        class="absolute end-2 top-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Sessions List Grouped by Timeline --}}
                <div class="flex-1 space-y-3.5 overflow-y-auto pe-1 text-xs">
                    <template x-if="filteredSessions.length === 0">
                        <div class="p-4 text-center text-xs text-zinc-400 dark:text-zinc-500">
                            <span x-text="searchQuery ? 'No chats matching search.' : 'No conversations yet.'"></span>
                        </div>
                    </template>

                    {{-- Group: Today --}}
                    <div x-show="groupedSessions.today.length > 0">
                        <p class="px-2 pb-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Today</p>
                        <div class="space-y-0.5">
                            <template x-for="item in groupedSessions.today" :key="item.id">
                                <div
                                    @click="selectSession(item)" @keydown.enter.prevent="selectSession(item)" @keydown.space.prevent="selectSession(item)" role="button" tabindex="0"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition md:gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 md:focus-within:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-zinc-700 md:p-0.5 md:text-zinc-400 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            title="Copy link" aria-label="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-red-500 md:p-0.5 md:text-zinc-400"
                                            title="Delete chat" aria-label="Delete chat"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Group: Yesterday --}}
                    <div x-show="groupedSessions.yesterday.length > 0">
                        <p class="px-2 pb-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Yesterday</p>
                        <div class="space-y-0.5">
                            <template x-for="item in groupedSessions.yesterday" :key="item.id">
                                <div
                                    @click="selectSession(item)" @keydown.enter.prevent="selectSession(item)" @keydown.space.prevent="selectSession(item)" role="button" tabindex="0"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition md:gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 md:focus-within:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-zinc-700 md:p-0.5 md:text-zinc-400 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            title="Copy link" aria-label="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-red-500 md:p-0.5 md:text-zinc-400"
                                            title="Delete chat" aria-label="Delete chat"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Group: Previous 7 Days --}}
                    <div x-show="groupedSessions.previousWeek.length > 0">
                        <p class="px-2 pb-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Previous 7 Days</p>
                        <div class="space-y-0.5">
                            <template x-for="item in groupedSessions.previousWeek" :key="item.id">
                                <div
                                    @click="selectSession(item)" @keydown.enter.prevent="selectSession(item)" @keydown.space.prevent="selectSession(item)" role="button" tabindex="0"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition md:gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 md:focus-within:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-zinc-700 md:p-0.5 md:text-zinc-400 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            title="Copy link" aria-label="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-red-500 md:p-0.5 md:text-zinc-400"
                                            title="Delete chat" aria-label="Delete chat"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Group: Older --}}
                    <div x-show="groupedSessions.older.length > 0">
                        <p class="px-2 pb-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Older</p>
                        <div class="space-y-0.5">
                            <template x-for="item in groupedSessions.older" :key="item.id">
                                <div
                                    @click="selectSession(item)" @keydown.enter.prevent="selectSession(item)" @keydown.space.prevent="selectSession(item)" role="button" tabindex="0"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition md:gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 md:focus-within:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-zinc-700 md:p-0.5 md:text-zinc-400 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            title="Copy link" aria-label="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="rounded p-1.5 text-zinc-500 hover:text-red-500 md:p-0.5 md:text-zinc-400"
                                            title="Delete chat" aria-label="Delete chat"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Sidebar Footer: Admin profile badge --}}
                <div class="mt-auto border-t border-zinc-200/80 pt-2.5 dark:border-zinc-800">
                    <div class="flex items-center gap-2 px-1">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-200/80 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            <span x-text="adminUser.name ? adminUser.name.charAt(0).toUpperCase() : 'A'"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium text-zinc-800 dark:text-zinc-200" x-text="adminUser.name"></p>
                            <p class="truncate text-[10px] text-zinc-400 dark:text-zinc-500" x-text="adminUser.email"></p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-zinc-950/50 md:hidden"
            aria-hidden="true"
        ></div>

        {{-- Main Chat Area ------------------------------------------------------ --}}
        <main class="relative flex min-w-0 flex-1 flex-col bg-white dark:bg-zinc-950">
            {{-- Top Navbar / Copilot Header --}}
            <header class="flex h-13 shrink-0 items-center justify-between border-b border-zinc-200/90 px-3.5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-center gap-2.5">
                    <a
                        href="/admin"
                        class="flex items-center gap-1.5 rounded-lg border border-zinc-200/90 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                        title="Return to Admin Panel Dashboard" aria-label="Return to Admin Panel Dashboard"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="hidden md:inline">Dashboard</span>
                    </a>

                    <div class="h-3.5 w-px bg-zinc-200 dark:bg-zinc-800"></div>

                    <button
                        type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        title="Toggle chat history" aria-label="Toggle chat history"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>

                    {{-- Echo brand mark — follows Platform Settings school logo, falls back to fox mark --}}
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 text-amber-500 dark:bg-zinc-800 dark:text-amber-400">
                            @if (! empty($assistantLogoUrl ?? null))
                                <img src="{{ $assistantLogoUrl }}" alt="{{ ($schoolName ?? 'School') }} logo" class="h-7 w-7 object-contain" />
                            @else
                            <div class="wolf-persona relative h-4.5 w-4.5" :data-motion="isStreaming ? 'thinking' : 'welcome'">
                                <svg class="h-full w-full" viewBox="0 0 120 120" fill="none" focusable="false" data-wolf-mark>
                                    <circle class="wolf-circle" cx="60" cy="60" r="30" fill="currentColor"/>
                                    <g class="wolf-spark">
                                        <path d="M60 16 L68 52 L104 60 L68 68 L60 104 L52 68 L16 60 L52 52Z" fill="currentColor"/>
                                        <path d="M60 36 L64 56 L84 60 L64 64 L60 84 L56 64 L36 60 L56 56Z" fill="var(--wolf-inner, #ffffff)" opacity=".35"/>
                                    </g>
                                    <g class="wolf-head">
                                        <g class="wolf-ear wolf-ear-left">
                                            <path d="M22 52 18 14 45 35 37 50Z" fill="currentColor" />
                                            <path d="M24 26 36 37 27 44Z" fill="var(--wolf-inner, #ffffff)" opacity=".65" />
                                        </g>
                                        <g class="wolf-ear wolf-ear-right">
                                            <path d="M98 52 102 14 75 35 83 50Z" fill="currentColor" />
                                            <path d="M96 26 84 37 93 44Z" fill="var(--wolf-inner, #ffffff)" opacity=".65" />
                                        </g>
                                        <path d="M24 51 46 33 60 40 53 64 39 55 34 66 18 59Z" fill="currentColor" />
                                        <path d="M96 51 74 33 60 40 67 64 81 55 86 66 102 59Z" fill="currentColor" />
                                        <path d="M46 35 60 40 74 35 65 69 60 79 55 69Z" fill="currentColor" opacity=".65" />
                                        <path d="M18 64 33 69 42 65 51 77 52 91 32 81Z M102 64 87 69 78 65 69 77 68 91 88 81Z" fill="currentColor" opacity=".8" />
                                        <g class="wolf-muzzle">
                                            <path d="M44 68 57 80 63 80 76 68 65 95 60 103 55 95Z" fill="currentColor" />
                                            <path d="M53 80 67 80 60 87Z" fill="var(--wolf-inner, #ffffff)" />
                                        </g>
                                        <path d="M39 58 49 63 41 63Z M81 58 71 63 79 63Z" fill="var(--wolf-inner, #ffffff)" />
                                    </g>
                                </svg>
                            </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $assistantName ?? 'Echo' }}</span>
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </div>
                    </div>
                </div>

                {{-- Status indicators: Provider model pill, Workspace, New Tab & New Chat --}}
                <div class="flex items-center gap-1.5">
                    <div class="hidden items-center gap-1.5 rounded-lg border border-zinc-200/90 bg-zinc-50/80 px-2 py-1 text-[11px] text-zinc-600 sm:flex dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <span class="font-medium" x-text="providerLabel"></span>
                        <span class="text-zinc-300 dark:text-zinc-700">·</span>
                        <span class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400" x-text="modelName"></span>
                    </div>

                    <div class="hidden rounded-lg border border-zinc-200/90 bg-zinc-50/80 px-2 py-1 text-[11px] font-medium text-zinc-600 md:block dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                        <span x-text="workspace.name"></span>
                    </div>

                    <template x-if="activeSessionId || activeSessionUuid">
                        <button
                            type="button"
                            @click="copyConversationLink()"
                            class="flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-medium transition"
                            :class="linkCopied
                                ? 'border-emerald-500/40 bg-emerald-50 text-emerald-600 dark:border-emerald-500/40 dark:bg-emerald-950/40 dark:text-emerald-400'
                                : 'border-zinc-200/90 text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'"
                            :title="linkCopied ? 'Link copied to clipboard!' : 'Copy link to this conversation'" :aria-label="linkCopied ? 'Link copied to clipboard!' : 'Copy link to this conversation'"
                        >
                            <template x-if="!linkCopied">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                            </template>
                            <template x-if="linkCopied">
                                <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </template>
                            <span x-text="linkCopied ? 'Copied!' : 'Copy link'" class="hidden text-[11px] sm:inline"></span>
                        </button>
                    </template>

                    <a
                        href="/admin/ai-chat"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        title="Open in new window / tab" aria-label="Open in new window / tab"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>

                    <button
                        type="button"
                        @click="newChat()"
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                        title="Start fresh conversation" aria-label="Start fresh conversation"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Message Stream Container --}}
            <div
                x-ref="messageContainer"
                @scroll="handleScroll()"
                class="flex-1 overflow-y-auto px-4 py-6 sm:px-8"
            >
                {{-- Welcome Screen (when no messages exist) --}}
                <template x-if="messages.length === 0">
                    <div class="relative flex min-h-full flex-col items-center justify-center px-4 py-8 text-center sm:py-12">
                        <div class="relative z-10 m-auto flex w-full max-w-xl flex-col items-center">
                            {{-- Brand mark — school logo when uploaded, fox mark otherwise --}}
                            <div class="welcome-logo mb-5 flex flex-col items-center">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100 sm:h-14 sm:w-14">
                                    @if (! empty($assistantLogoUrl ?? null))
                                        <img src="{{ $assistantLogoUrl }}" alt="{{ ($schoolName ?? 'School') }} logo" class="h-12 w-12 object-contain sm:h-14 sm:w-14" />
                                    @else
                                    <div
                                        class="wolf-persona relative h-7 w-7 text-amber-500 dark:text-amber-400 sm:h-8 sm:w-8"
                                        :data-motion="isStreaming ? 'thinking' : (isListening ? 'listening' : 'welcome')"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="h-full w-full"
                                            viewBox="0 0 120 120"
                                            fill="none"
                                            focusable="false"
                                            data-wolf-mark
                                        >
                                            <circle
                                                class="wolf-circle"
                                                cx="60"
                                                cy="60"
                                                r="30"
                                                fill="currentColor"
                                            />
                                            <!-- Geometric 4-point star/spark of insight -->
                                            <g class="wolf-spark">
                                                <path
                                                    d="M60 16 L68 52 L104 60 L68 68 L60 104 L52 68 L16 60 L52 52Z"
                                                    fill="currentColor"
                                                />
                                                <path
                                                    d="M60 36 L64 56 L84 60 L64 64 L60 84 L56 64 L36 60 L56 56Z"
                                                    fill="var(--wolf-inner, #ffffff)"
                                                    opacity=".35"
                                                />
                                            </g>
                                            <g class="wolf-head">
                                                <g class="wolf-ear wolf-ear-left">
                                                    <path d="M22 52 18 14 45 35 37 50Z" fill="currentColor" />
                                                    <path
                                                        d="M24 26 36 37 27 44Z"
                                                        fill="var(--wolf-inner, #ffffff)"
                                                        opacity=".65"
                                                    />
                                                </g>
                                                <g class="wolf-ear wolf-ear-right">
                                                    <path d="M98 52 102 14 75 35 83 50Z" fill="currentColor" />
                                                    <path
                                                        d="M96 26 84 37 93 44Z"
                                                        fill="var(--wolf-inner, #ffffff)"
                                                        opacity=".65"
                                                    />
                                                </g>
                                                <path
                                                    d="M24 51 46 33 60 40 53 64 39 55 34 66 18 59Z"
                                                    fill="currentColor"
                                                />
                                                <path
                                                    d="M96 51 74 33 60 40 67 64 81 55 86 66 102 59Z"
                                                    fill="currentColor"
                                                />
                                                <path
                                                    d="M46 35 60 40 74 35 65 69 60 79 55 69Z"
                                                    fill="currentColor"
                                                    opacity=".65"
                                                />
                                                <path
                                                    d="M18 64 33 69 42 65 51 77 52 91 32 81Z M102 64 87 69 78 65 69 77 68 91 88 81Z"
                                                    fill="currentColor"
                                                    opacity=".8"
                                                />
                                                <g class="wolf-muzzle">
                                                    <path
                                                        d="M44 68 57 80 63 80 76 68 65 95 60 103 55 95Z"
                                                        fill="currentColor"
                                                    />
                                                    <path d="M53 80 67 80 60 87Z" fill="var(--wolf-inner, #ffffff)" />
                                                </g>
                                                <path
                                                    d="M39 58 49 63 41 63Z M81 58 71 63 79 63Z"
                                                    fill="var(--wolf-inner, #ffffff)"
                                                />
                                            </g>
                                        </svg>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Clean, Simple Greeting --}}
                            <div class="welcome-greeting space-y-1 text-center">
                                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-100">
                                    <span x-text="greetingLine"></span>
                                </h1>
                                <p class="mx-auto max-w-md text-xs text-zinc-500 sm:text-sm dark:text-zinc-400" x-text="greetingSubtext"></p>
                                <span class="hidden" x-text="timeGreeting"></span>
                            </div>

                            {{-- Minimalist Input Card --}}
                            <form
                                class="welcome-input mt-7 w-full max-w-xl"
                                @submit.prevent="sendMessage()"
                            >
                                <div
                                    class="group relative rounded-2xl border border-zinc-200/90 bg-white p-3 shadow-2xs transition focus-within:border-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:focus-within:border-zinc-600"
                                >
                                    <textarea
                                        x-ref="welcomeComposerInput"
                                        x-model="inputMessage"
                                        @keydown="handleKeyDown($event)"
                                        @input="autoGrowTextarea($event)"
                                        rows="2"
                                        placeholder="How can Echo help manage your workspace today?"
                                        class="min-h-[64px] w-full resize-none border-0 bg-transparent p-0 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus:ring-0 sm:text-sm dark:text-zinc-100 dark:placeholder:text-zinc-500"
                                    ></textarea>

                                    {{-- Listening Feedback Banner (Free Browser Dictation - 0 Tokens) --}}
                                    <div x-show="isListening" x-cloak class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-red-500/20 bg-red-500/5 px-2.5 py-1.5 text-xs text-red-500 dark:text-red-400">
                                        <div class="flex items-center gap-2">
                                            <span class="relative flex h-2 w-2">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                            </span>
                                            <span class="font-medium animate-pulse">Listening... speak into your microphone</span>
                                            <span class="hidden sm:inline text-[11px] text-zinc-400 dark:text-zinc-500">(0 tokens)</span>
                                        </div>
                                        <button
                                            type="button"
                                            @click.stop.prevent="toggleVoice()"
                                            class="rounded bg-red-600 px-2 py-0.5 text-[11px] font-medium text-white shadow-xs hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                        >
                                            Done
                                        </button>
                                    </div>

                                    <div x-show="voiceError" x-cloak class="mt-2 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/90 p-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                                        <svg class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div class="flex-1 leading-relaxed" x-text="voiceError"></div>
                                        <button type="button" @click="voiceError = null" class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between border-t border-zinc-100 pt-2.5 dark:border-zinc-800/80">
                                        <div class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            <span class="font-medium" x-text="providerLabel"></span>
                                            <span class="text-zinc-300 dark:text-zinc-700">·</span>
                                            <span class="font-mono text-[11px] text-zinc-400 dark:text-zinc-500" x-text="modelName"></span>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            {{-- Free Microphone Dictation Button (0 Tokens) --}}
                                            <button
                                                type="button"
                                                @click.stop.prevent="toggleVoice()"
                                                :disabled="isStreaming"
                                                class="relative flex h-7 w-7 items-center justify-center rounded-lg transition"
                                                :class="isListening ? 'bg-red-500 text-white shadow-md shadow-red-500/40 ring-2 ring-red-400/50' : 'border border-zinc-200/90 bg-zinc-50 text-zinc-600 hover:border-zinc-300 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/80 dark:text-zinc-400 dark:hover:border-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-100'"
                                                :title="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'" :aria-label="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'"
                                            >
                                                <span x-show="isListening" x-cloak class="absolute -inset-0.5 animate-ping rounded-lg bg-red-400 opacity-75"></span>
                                                <svg class="relative h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                                </svg>
                                            </button>

                                            {{-- Send Button --}}
                                            <button
                                                type="submit"
                                                :disabled="!inputMessage.trim() || isStreaming"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-900 text-white transition hover:bg-zinc-800 disabled:opacity-30 disabled:cursor-not-allowed dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                                title="Send" aria-label="Send"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-center text-[11px] text-zinc-400 dark:text-zinc-500">
                                    Echo stages workspace writes. High-impact operations require your explicit confirmation.
                                </p>
                            </form>

                            {{-- Clean Prompt Starters (4 chips in 2x2 grid) --}}
                            <div class="welcome-suggestions mt-6 w-full max-w-xl">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <template x-for="starter in filteredPromptStarters" :key="starter.title">
                                        <button
                                            type="button"
                                            @click="fillAndSend(starter.prompt)"
                                            class="flex flex-col items-start rounded-xl border border-zinc-200/80 bg-zinc-50/60 p-2.5 text-left transition hover:border-zinc-300 hover:bg-zinc-100/70 dark:border-zinc-800 dark:bg-zinc-900/40 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/60"
                                        >
                                            <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200" x-text="starter.title"></span>
                                            <span class="mt-0.5 text-[11px] text-zinc-500 dark:text-zinc-400 line-clamp-1" x-text="starter.prompt"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Messages List --}}
                <div class="mx-auto max-w-3xl space-y-6">
                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="flex flex-col gap-2">
                            {{-- User Message --}}
                            <template x-if="msg.role === 'user'">
                                <div class="flex items-start justify-end gap-2.5">
                                    <div class="max-w-[85%] rounded-2xl rounded-tr-xs bg-zinc-900 px-3.5 py-2.5 text-xs leading-relaxed text-white shadow-2xs sm:text-sm dark:bg-zinc-100 dark:text-zinc-900">
                                        <div class="whitespace-pre-wrap font-sans" x-text="msg.content"></div>
                                    </div>
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-zinc-200 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                        <span x-text="adminUser.name ? adminUser.name.charAt(0).toUpperCase() : 'A'"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Assistant Message --}}
                            <template x-if="msg.role === 'assistant'">
                                <div class="flex items-start gap-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 text-amber-500 dark:bg-zinc-800 dark:text-amber-400">
                                        @if (! empty($assistantLogoUrl ?? null))
                                            <img src="{{ $assistantLogoUrl }}" alt="{{ ($schoolName ?? 'School') }} logo" class="h-7 w-7 object-contain" />
                                        @else
                                        <div class="wolf-persona relative h-4.5 w-4.5" :data-motion="msg.typing ? 'thinking' : 'speaking'">
                                            <svg class="h-full w-full" viewBox="0 0 120 120" fill="none" focusable="false" data-wolf-mark>
                                                <circle class="wolf-circle" cx="60" cy="60" r="30" fill="currentColor"/>
                                                <g class="wolf-spark">
                                                    <path d="M60 16 L68 52 L104 60 L68 68 L60 104 L52 68 L16 60 L52 52Z" fill="currentColor"/>
                                                    <path d="M60 36 L64 56 L84 60 L64 64 L60 84 L56 64 L36 60 L56 56Z" fill="var(--wolf-inner, #ffffff)" opacity=".35"/>
                                                </g>
                                                <g class="wolf-head">
                                                    <g class="wolf-ear wolf-ear-left">
                                                        <path d="M22 52 18 14 45 35 37 50Z" fill="currentColor" />
                                                        <path d="M24 26 36 37 27 44Z" fill="var(--wolf-inner, #ffffff)" opacity=".65" />
                                                    </g>
                                                    <g class="wolf-ear wolf-ear-right">
                                                        <path d="M98 52 102 14 75 35 83 50Z" fill="currentColor" />
                                                        <path d="M96 26 84 37 93 44Z" fill="var(--wolf-inner, #ffffff)" opacity=".65" />
                                                    </g>
                                                    <path d="M24 51 46 33 60 40 53 64 39 55 34 66 18 59Z" fill="currentColor" />
                                                    <path d="M96 51 74 33 60 40 67 64 81 55 86 66 102 59Z" fill="currentColor" />
                                                    <path d="M46 35 60 40 74 35 65 69 60 79 55 69Z" fill="currentColor" opacity=".65" />
                                                    <path d="M18 64 33 69 42 65 51 77 52 91 32 81Z M102 64 87 69 78 65 69 77 68 91 88 81Z" fill="currentColor" opacity=".8" />
                                                    <g class="wolf-muzzle">
                                                        <path d="M44 68 57 80 63 80 76 68 65 95 60 103 55 95Z" fill="currentColor" />
                                                        <path d="M53 80 67 80 60 87Z" fill="var(--wolf-inner, #ffffff)" />
                                                    </g>
                                                    <path d="M39 58 49 63 41 63Z M81 58 71 63 79 63Z" fill="var(--wolf-inner, #ffffff)" />
                                                </g>
                                            </svg>
                                        </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-2.5">
                                        {{-- Thinking / Loading Indicator with 3 animated bouncing dots --}}
                                        <div
                                            x-show="msg.typing && !msg.content && !msg.thinking"
                                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50/80 px-3 py-1.5 text-xs text-zinc-600 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300"
                                        >
                                            <div class="flex h-3.5 w-3.5 items-center justify-center text-amber-500">
                                                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                            </div>
                                            <span class="font-medium text-zinc-700 dark:text-zinc-200">Thinking</span>
                                            <span class="inline-flex items-center gap-1 text-amber-500">
                                                <span class="thinking-dot"></span>
                                                <span class="thinking-dot"></span>
                                                <span class="thinking-dot"></span>
                                            </span>
                                            <span
                                                x-show="msg.elapsedSeconds"
                                                class="font-mono text-[10px] text-zinc-400 dark:text-zinc-500"
                                                x-text="msg.elapsedSeconds + 's'"
                                            ></span>
                                        </div>

                                        {{-- Live Reasoning Process Accordion --}}
                                        <div x-show="msg.thinking" class="rounded-xl border border-zinc-200/80 bg-zinc-50/60 p-2.5 text-xs dark:border-zinc-800 dark:bg-zinc-900/40">
                                            <details :open="msg.thinkingOpen">
                                                <summary class="flex cursor-pointer items-center justify-between font-medium text-zinc-700 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                                                    <div class="flex items-center gap-1.5">
                                                        <svg
                                                            class="h-3.5 w-3.5 text-amber-500"
                                                            :class="msg.typing && !msg.content ? 'animate-spin' : ''"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        <span x-text="msg.typing && !msg.content ? 'Thinking' : 'Reasoning process'"></span>
                                                        <span x-show="msg.typing && !msg.content" class="inline-flex items-center gap-1 text-amber-500">
                                                            <span class="thinking-dot"></span>
                                                            <span class="thinking-dot"></span>
                                                            <span class="thinking-dot"></span>
                                                        </span>
                                                    </div>
                                                    <span
                                                        x-show="msg.thinkingMs || msg.elapsedSeconds"
                                                        class="font-mono text-[10px] text-zinc-400"
                                                        x-text="(msg.thinkingMs || msg.elapsedSeconds) + 's'"
                                                    ></span>
                                                </summary>
                                                <div class="mt-2 whitespace-pre-wrap border-t border-zinc-200/60 pt-2 font-mono text-[11px] leading-relaxed text-zinc-600 dark:border-zinc-800 dark:text-zinc-400" x-text="msg.thinking"></div>
                                            </details>
                                        </div>

                                        {{-- Live ReUI Agent Activity Console (During Real-time Execution & Streaming) --}}
                                        <div
                                            x-show="!getActionsForMessage(msg, index).length && msg.activity"
                                            class="my-3 overflow-hidden rounded-2xl border border-zinc-800 bg-[#121214] text-zinc-100 shadow-xl"
                                        >
                                            {{-- Console Header --}}
                                            <div class="flex items-center justify-between border-b border-zinc-800/80 px-4 py-3 sm:px-5">
                                                <div class="flex min-w-0 items-center gap-2.5">
                                                    <h3 class="truncate text-xs font-semibold tracking-tight text-white sm:text-sm" x-text="msg.activity?.title || 'Processing agent activity...'"></h3>
                                                    {{-- Status Pill --}}
                                                    <template x-if="msg.activity?.status === 'running'">
                                                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-blue-500/15 px-2 py-0.5 text-[10px] font-medium text-blue-300 ring-1 ring-blue-500/30 ring-inset">
                                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-400"></span>
                                                            Running
                                                        </span>
                                                    </template>
                                                    <template x-if="msg.activity?.status === 'completed'">
                                                        <span class="inline-flex shrink-0 items-center rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-300 ring-1 ring-emerald-500/30 ring-inset">
                                                            Completed
                                                        </span>
                                                    </template>
                                                    <template x-if="msg.activity?.status === 'needs_approval'">
                                                        <span class="inline-flex shrink-0 items-center rounded-md bg-amber-500/15 px-2 py-0.5 text-[10px] font-medium text-amber-300 ring-1 ring-amber-500/30 ring-inset">
                                                            Needs you
                                                        </span>
                                                    </template>
                                                </div>

                                                <div class="flex items-center gap-1 text-zinc-400">
                                                    <button
                                                        type="button"
                                                        @click="msg.activity.collapsed = !msg.activity.collapsed"
                                                        class="rounded-md p-1 transition hover:bg-zinc-800/80 hover:text-white"
                                                        :title="msg.activity?.collapsed ? 'Expand activity' : 'Collapse activity'" :aria-label="msg.activity?.collapsed ? 'Expand activity' : 'Collapse activity'"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Activity Timeline Body --}}
                                            <div x-show="!msg.activity?.collapsed" class="space-y-3.5 p-4 sm:p-5">
                                                        <template x-for="(step, sIdx) in (msg.activity?.steps || [])" :key="step.id || sIdx">
                                                            @include('filament.pages.ai-chat-step-item')
                                                        </template>

                                                {{-- Upcoming Queued Step if running --}}
                                                <div x-show="msg.activity?.status === 'running'" class="space-y-3 pt-1">
                                                    <div class="flex items-center justify-between gap-3 text-xs text-zinc-500">
                                                        <div class="flex min-w-0 items-center gap-2.5">
                                                            <svg class="h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                <circle cx="12" cy="12" r="9" />
                                                            </svg>
                                                            <span class="rounded border border-zinc-800 bg-zinc-900 px-1 py-0.5 font-mono text-[10px] text-zinc-500">agent</span>
                                                            <span class="truncate">Finalize and prepare the workspace changes</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Console Footer --}}
                                            <div class="flex items-center justify-between border-t border-zinc-800/80 bg-zinc-950/80 px-4 py-2.5 sm:px-5">
                                                <span class="font-mono text-[11px] text-zinc-500" x-text="'Executing real-time pipeline ' + (msg.elapsedSeconds || 1) + 's'"></span>
                                                <span class="inline-flex items-center gap-1.5 font-mono text-[11px] text-zinc-400">
                                                    <svg class="h-3 w-3 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span>Echo Guard Active</span>
                                                </span>
                                            </div>
                                        </div>

                                        {{-- ReUI Agent Activity Console Block --}}
                                        <template x-for="action in getActionsForMessage(msg, index)" :key="action.id">
                                            <div class="my-3 overflow-hidden rounded-2xl border border-zinc-800 bg-[#121214] text-zinc-100 shadow-xl">
                                                {{-- Console Header --}}
                                                <div class="flex items-center justify-between border-b border-zinc-800/80 px-4 py-3 sm:px-5">
                                                    <div class="flex min-w-0 items-center gap-2.5">
                                                        <h3 class="truncate text-xs font-semibold tracking-tight text-white sm:text-sm" x-text="action.title"></h3>
                                                        {{-- Dynamic Status Pill --}}
                                                        <span
                                                            x-show="action.status === 'pending'"
                                                            class="inline-flex shrink-0 items-center rounded-md bg-amber-500/15 px-2 py-0.5 text-[10px] font-medium text-amber-300 ring-1 ring-amber-500/30 ring-inset"
                                                        >
                                                            Needs you
                                                        </span>
                                                        <span
                                                            x-show="action.status === 'executing' || action._loading || actionLoading[action.id]"
                                                            class="inline-flex shrink-0 items-center gap-1 rounded-md bg-blue-500/15 px-2 py-0.5 text-[10px] font-medium text-blue-300 ring-1 ring-blue-500/30 ring-inset"
                                                        >
                                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-400"></span>
                                                            Executing
                                                        </span>
                                                        <span
                                                            x-show="action.status === 'executed'"
                                                            class="inline-flex shrink-0 items-center rounded-md bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-300 ring-1 ring-emerald-500/30 ring-inset"
                                                        >
                                                            Completed
                                                        </span>
                                                        <span
                                                            x-show="action.status === 'rejected'"
                                                            class="inline-flex shrink-0 items-center rounded-md bg-red-500/15 px-2 py-0.5 text-[10px] font-medium text-red-300 ring-1 ring-red-500/30 ring-inset"
                                                        >
                                                            Rejected
                                                        </span>
                                                        <span
                                                            x-show="action.status === 'expired' || action.status === 'failed'"
                                                            class="inline-flex shrink-0 items-center rounded-md bg-zinc-800 px-2 py-0.5 text-[10px] font-medium text-zinc-400 ring-1 ring-zinc-700 ring-inset"
                                                            x-text="action.status === 'expired' ? 'Expired' : 'Failed'"
                                                        ></span>
                                                    </div>

                                                    <div class="flex items-center gap-1 text-zinc-400">
                                                        <button
                                                            type="button"
                                                            @click="action._collapsed = !action._collapsed"
                                                            class="rounded-md p-1 transition hover:bg-zinc-800/80 hover:text-white"
                                                            :title="action._collapsed ? 'Expand activity' : 'Collapse activity'" :aria-label="action._collapsed ? 'Expand activity' : 'Collapse activity'"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Activity Timeline Body --}}
                                                <div x-show="!action._collapsed" class="space-y-3.5 p-4 sm:p-5">
                                                    {{-- Real-time Captured Steps if available --}}
                                                    <template x-if="msg.activity && msg.activity.steps && msg.activity.steps.length > 0">
                                                        <div class="space-y-3.5">
                                                            <template x-for="(step, sIdx) in msg.activity.steps" :key="step.id || sIdx">
                                                                @include('filament.pages.ai-chat-step-item')
                                                            </template>
                                                        </div>
                                                    </template>

                                                    {{-- Fallback Steps (shown when msg.activity is not present, e.g. on chat reload) --}}
                                                    <template x-if="!msg.activity || !msg.activity.steps || msg.activity.steps.length === 0">
                                                        <div class="space-y-3.5">
                                                            {{-- Step 1: Verification Completed --}}
                                                            <div class="flex items-start justify-between gap-3 text-xs">
                                                                <div class="flex min-w-0 items-start gap-2.5">
                                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <circle cx="12" cy="12" r="9" />
                                                                        <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                    <div class="min-w-0">
                                                                        <div class="flex flex-wrap items-center gap-2">
                                                                            <span class="font-medium text-zinc-200">Workspace context loaded</span>
                                                                            <span class="font-mono text-[11px] text-zinc-400" x-text="action.workspace ? action.workspace.name : (workspace ? workspace.name : 'Active Workspace')"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                            </div>

                                                            {{-- Step 2: Staged Operation Payload --}}
                                                            <div class="flex items-start justify-between gap-3 text-xs">
                                                                <div class="flex min-w-0 items-start gap-2.5">
                                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <circle cx="12" cy="12" r="9" />
                                                                        <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                    <div class="min-w-0">
                                                                        <div class="flex flex-wrap items-center gap-2">
                                                                            <span class="font-medium text-zinc-200">Prepared changes</span>
                                                                            <span class="rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 font-mono text-[10px] text-amber-300">Check</span>
                                                                            <span class="font-mono text-[11px] text-zinc-400" x-text="action.actionType"></span>
                                                                        </div>
                                                                        <p class="mt-1 text-[11px] leading-relaxed text-zinc-400" x-text="action.summary"></p>
                                                                    </div>
                                                                </div>
                                                                <span class="shrink-0 font-mono text-[11px] text-zinc-500">0:05</span>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    {{-- Step 3: Interactive Active Card ("Needs your approval") --}}
                                                    <div x-show="action.status === 'pending'" class="rounded-xl border border-zinc-700/80 bg-zinc-900/90 p-3.5 shadow-xs">
                                                        <div class="flex items-center justify-between gap-2 border-b border-zinc-800 pb-2.5">
                                                            <div class="flex min-w-0 items-center gap-2">
                                                                <svg class="h-4 w-4 shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                                                </svg>
                                                                <span class="rounded border border-zinc-700 bg-zinc-800 px-1.5 py-0.5 font-mono text-[10px] text-zinc-300">echo</span>
                                                                <span class="text-xs font-semibold text-white sm:text-sm">Needs your approval</span>
                                                                <span class="font-mono text-xs text-zinc-400" x-text="action.actionType"></span>
                                                            </div>
                                                            <span class="shrink-0 rounded-md border border-red-500/30 bg-red-500/10 px-2 py-0.5 text-[10px] font-medium text-red-400">
                                                                High impact
                                                            </span>
                                                        </div>

                                                        <p class="mt-2 text-xs leading-relaxed text-zinc-300">
                                                            Echo prepared this write operation. Review the proposed changes below before committing to the database.
                                                        </p>

                                                        {{-- Changes Diff Table --}}
                                                        <div x-show="action.changes && action.changes.length > 0" class="mt-2.5 overflow-x-auto rounded-lg border border-zinc-800 bg-zinc-950/70 p-2.5">
                                                            <table class="w-full text-left text-xs">
                                                                <thead>
                                                                    <tr class="border-b border-zinc-800 text-[11px] text-zinc-500">
                                                                        <th class="pe-2 pb-1.5 font-medium">Field</th>
                                                                        <th class="px-2 pb-1.5 font-medium">Current</th>
                                                                        <th class="ps-2 pb-1.5 font-medium text-amber-400">Proposed</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y border-zinc-800/60 font-mono text-[11px]">
                                                                    <template x-for="change in action.changes" :key="change.field">
                                                                        <tr>
                                                                            <td class="pe-2 py-1.5 font-sans font-medium text-zinc-300" x-text="change.field"></td>
                                                                            <td class="px-2 py-1.5 text-zinc-500" x-text="change.before || '—'"></td>
                                                                            <td class="ps-2 py-1.5 font-semibold text-amber-300" x-text="change.after"></td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        {{-- Inline Instruction / Clarification Bar --}}
                                                        <div class="mt-3 flex items-center gap-2">
                                                            <input
                                                                type="text"
                                                                x-model="action._inlineAnswer"
                                                                @keydown.enter.stop.prevent="sendAdjustment(action)"
                                                                placeholder="Type adjustments or instructions for Echo..."
                                                                class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950/80 px-3 py-1.5 text-xs text-zinc-100 placeholder:text-zinc-500 focus:border-zinc-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus:ring-0"
                                                            />
                                                            <button
                                                                type="button"
                                                                @click.stop.prevent="sendAdjustment(action)"
                                                                :disabled="!action._inlineAnswer || !action._inlineAnswer.trim() || isStreaming"
                                                                class="cursor-pointer rounded-lg bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-200 transition hover:bg-zinc-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                                                            >
                                                                Send
                                                            </button>
                                                        </div>

                                                        {{-- Error Alert --}}
                                                        <div x-show="action.error" class="mt-2.5 rounded-lg border border-red-500/30 bg-red-500/10 p-2 text-xs text-red-300">
                                                            <span class="font-medium">Error:</span> <span x-text="action.error"></span>
                                                        </div>

                                                        {{-- Action Buttons --}}
                                                        <div class="mt-3 flex items-center justify-between border-t border-zinc-800 pt-2.5">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-[11px] text-zinc-500">Security check active — expires after 15 min</span>
                                                                <template x-if="!action.nonce && action.status === 'pending'">
                                                                    <span class="rounded border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] text-amber-300">
                                                                        Window expired
                                                                    </span>
                                                                </template>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button
                                                                    type="button"
                                                                    @click.stop.prevent="rejectAction(action)"
                                                                    :disabled="action._loading || actionLoading[action.id] || isStreaming"
                                                                    class="cursor-pointer rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700 hover:text-white active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    <span x-text="(action._loading || actionLoading[action.id]) ? 'Rejecting...' : 'Reject'"></span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click.stop.prevent="approveAction(action)"
                                                                    :disabled="action._loading || actionLoading[action.id] || isStreaming"
                                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-amber-950 transition hover:bg-amber-400 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    <svg x-show="action._loading || actionLoading[action.id]" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                                    </svg>
                                                                    <span x-text="(action._loading || actionLoading[action.id]) ? 'Executing...' : 'Approve & Execute'"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Step 3 Alternative: Executed Successfully --}}
                                                    <div x-show="action.status === 'executed'" class="space-y-2.5 text-xs">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div class="flex min-w-0 items-start gap-2.5">
                                                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                    <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                                <div class="min-w-0">
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <span class="font-medium text-emerald-300">Committed action to database</span>
                                                                        <span class="font-mono text-[11px] text-zinc-400" x-text="action.actionType"></span>
                                                                    </div>
                                                                    <div class="mt-1 flex items-center gap-1.5 font-mono text-xs text-emerald-400">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                                        </svg>
                                                                        <span x-text="action.result || 'Execution completed successfully'"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                        </div>


                                                        {{-- Manual continue button when no countdown is running and not streaming --}}
                                                        <div x-show="continuationActionId !== action.id && !isStreaming" class="flex justify-end pt-0.5">
                                                            <button
                                                                type="button"
                                                                @click.stop.prevent="manualContinue(action)"
                                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-700/80 bg-zinc-800/70 px-2.5 py-1 text-[11px] font-medium text-zinc-300 transition hover:border-emerald-500/50 hover:bg-zinc-800 hover:text-emerald-300 active:scale-95"
                                                            >
                                                                <span>Proceed to next steps with Echo</span>
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Step 3 Alternative: Rejected --}}
                                                    <div x-show="action.status === 'rejected'" class="flex items-start justify-between gap-3 text-xs">
                                                        <div class="flex min-w-0 items-start gap-2.5">
                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="9" />
                                                                <path d="m15 9-6 6M9 9l6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <div class="min-w-0">
                                                                <span class="font-medium text-red-300">Action rejected by administrator</span>
                                                                <p class="mt-0.5 text-[11px] text-zinc-400">Execution was cancelled and changes were discarded.</p>
                                                            </div>
                                                        </div>
                                                        <span class="shrink-0 font-mono text-[11px] text-zinc-500">0:00</span>
                                                    </div>

                                                    {{-- Step 3 Alternative: Expired --}}
                                                    <div x-show="action.status === 'expired'" class="flex items-start justify-between gap-3 text-xs">
                                                        <div class="flex min-w-0 items-start gap-2.5">
                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="9" />
                                                                <path d="M12 8v4l2.5 2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <div class="min-w-0">
                                                                <span class="font-medium text-amber-300">Approval window expired</span>
                                                                <p class="mt-0.5 text-[11px] text-zinc-400">The 15-minute verification window lapsed before approval. Ask Echo to prepare this action again.</p>
                                                            </div>
                                                        </div>
                                                        <span class="shrink-0 font-mono text-[11px] text-zinc-500">15:00</span>
                                                    </div>

                                                    {{-- Step 3 Alternative: Failed --}}
                                                    <div x-show="action.status === 'failed'" class="flex items-start justify-between gap-3 text-xs">
                                                        <div class="flex min-w-0 items-start gap-2.5">
                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <circle cx="12" cy="12" r="9" />
                                                                <line x1="15" y1="9" x2="9" y2="15" />
                                                                <line x1="9" y1="9" x2="15" y2="15" />
                                                            </svg>
                                                            <div class="min-w-0">
                                                                <span class="font-medium text-red-300">Action execution failed</span>
                                                                <p class="mt-0.5 text-[11px] text-red-400" x-text="action.error || 'Execution encountered an error'"></p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Upcoming Queued Steps (shown when pending) --}}
                                                    <div x-show="action.status === 'pending'" class="space-y-3 pt-1">
                                                        <div class="flex items-center justify-between gap-3 text-xs text-zinc-500">
                                                            <div class="flex min-w-0 items-center gap-2.5">
                                                                <svg class="h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                </svg>
                                                                <span class="rounded border border-zinc-800 bg-zinc-900 px-1 py-0.5 font-mono text-[10px] text-zinc-500">db</span>
                                                                <span class="truncate">Commit write operation to workspace database</span>
                                                                <span class="font-mono text-[11px] text-zinc-600" x-text="action.actionType"></span>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center justify-between gap-3 text-xs text-zinc-500">
                                                            <div class="flex min-w-0 items-center gap-2.5">
                                                                <svg class="h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                </svg>
                                                                <span class="rounded border border-zinc-800 bg-zinc-900 px-1 py-0.5 font-mono text-[10px] text-zinc-500">audit</span>
                                                                <span class="truncate">Log immutable audit event & sync workspace status</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Console Footer --}}
                                                <div class="flex items-center justify-between border-t border-zinc-800/80 bg-zinc-950/80 px-4 py-2.5 sm:px-5">
                                                    <span class="font-mono text-[11px] text-zinc-500" x-text="(action.status === 'pending' ? 'Awaiting your approval' : (action.status === 'rejected' ? 'Stopped before any changes' : 'Completed'))"></span>
                                                    <span class="inline-flex items-center gap-1.5 font-mono text-[11px] text-zinc-400">
                                                        <svg class="h-3 w-3 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>Echo Guard Active</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Message Body (Markdown formatted) with streaming cursor --}}
                                        <div x-show="msg.content" class="flex items-start">
                                            <div
                                                class="prose prose-sm dark:prose-invert max-w-none pt-0.5 text-xs leading-relaxed text-zinc-800 sm:text-sm dark:text-zinc-200"
                                                x-html="formatMarkdown(msg.content)"
                                            ></div>
                                            <span
                                                x-show="msg.typing"
                                                class="mt-1 ml-0.5 inline-block h-3.5 w-1.5 animate-pulse rounded-xs bg-amber-500"
                                                title="Generating..." aria-label="Generating..."
                                            ></span>
                                        </div>

                                        {{-- Message Actions (Copy & Read Aloud) --}}
                                        <div x-show="!msg.typing && msg.content" class="flex items-center gap-2 pt-0.5">
                                            <button
                                                type="button"
                                                @click="copyText(msg.content); copiedMessageId = msg.content; setTimeout(() => { if (copiedMessageId === msg.content) copiedMessageId = null }, 1500)"
                                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                title="Copy message" aria-label="Copy message"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                <span x-text="copiedMessageId === msg.content ? 'Copied' : 'Copy'"></span>
                                            </button>

                                            <button
                                                type="button"
                                                @click="speakText(msg.content)"
                                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                :title="speakingContent === msg.content ? 'Stop speaking' : 'Read aloud with voice'" :aria-label="speakingContent === msg.content ? 'Stop speaking' : 'Read aloud with voice'"
                                            >
                                                <svg x-show="speakingContent !== msg.content" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                                </svg>
                                                <svg x-show="speakingContent === msg.content" class="h-3.5 w-3.5 text-amber-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                </svg>
                                                <span x-text="speakingContent === msg.content ? 'Speaking...' : 'Listen'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <button
                x-show="showNewMessages"
                x-cloak
                type="button"
                @click="pinnedToBottom = true; showNewMessages = false; scrollToBottom(true)"
                class="absolute bottom-28 left-1/2 z-20 -translate-x-1/2 rounded-full border border-zinc-200/90 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-md transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
            >
                New messages
            </button>

            {{-- Docked Prompt Composer -------------------------------------------- --}}
            <div
                x-show="messages.length > 0"
                x-cloak
                class="border-t border-zinc-200/90 bg-white/95 p-3 backdrop-blur-md dark:border-zinc-800 dark:bg-zinc-950/95"
            >
                <div class="mx-auto max-w-3xl">
                    <form @submit.prevent="sendMessage()" class="relative flex flex-col rounded-2xl border border-zinc-200/90 bg-white p-2.5 shadow-2xs transition focus-within:border-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:focus-within:border-zinc-600">
                        <textarea
                            x-ref="composerInput"
                            x-model="inputMessage"
                            @keydown="handleKeyDown($event)"
                            @input="autoGrowTextarea($event)"
                            rows="2"
                            placeholder="Ask Echo to manage exams, record grades, evaluate submissions, or post announcements..."
                            class="w-full resize-none border-0 bg-transparent px-1 py-1 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus:ring-0 sm:text-sm dark:text-zinc-100 dark:placeholder:text-zinc-500"
                        ></textarea>

                        {{-- Listening Feedback Banner (Free Browser Dictation - 0 Tokens) --}}
                        <div x-show="isListening" x-cloak class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-red-500/20 bg-red-500/5 px-2.5 py-1.5 text-xs text-red-500 dark:text-red-400">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                </span>
                                <span class="font-medium animate-pulse">Listening... speak into your microphone</span>
                                <span class="hidden sm:inline text-[11px] text-zinc-400 dark:text-zinc-500">(0 tokens)</span>
                            </div>
                            <button
                                type="button"
                                @click.stop.prevent="toggleVoice()"
                                class="rounded bg-red-600 px-2 py-0.5 text-[11px] font-medium text-white shadow-xs hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                            >
                                Done
                            </button>
                        </div>

                        <div x-show="voiceError" x-cloak class="mb-2 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50/90 p-2 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                            <svg class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="flex-1 leading-relaxed" x-text="voiceError"></div>
                            <button type="button" @click="voiceError = null" class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center justify-between border-t border-zinc-100 pt-2 dark:border-zinc-800/80">
                            <div class="flex items-center gap-1.5 px-1 text-[11px] text-zinc-400 dark:text-zinc-500">
                                <span class="hidden sm:inline">Press</span>
                                <kbd class="rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[10px] font-mono text-zinc-600 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-400">Enter</kbd>
                                <span class="hidden sm:inline">to send</span>
                                <span class="hidden text-zinc-300 sm:inline dark:text-zinc-700">·</span>
                                <kbd class="hidden rounded border border-zinc-200 bg-zinc-50 px-1.5 py-0.5 text-[10px] font-mono text-zinc-600 sm:inline-block dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-400">Shift + Enter</kbd>
                                <span class="hidden sm:inline">for new line</span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                {{-- Free Microphone Dictation Button (0 Tokens) --}}
                                <button
                                    type="button"
                                    @click.stop.prevent="toggleVoice()"
                                    :disabled="isStreaming"
                                    class="relative flex h-7 w-7 items-center justify-center rounded-lg transition"
                                    :class="isListening ? 'bg-red-500 text-white shadow-md shadow-red-500/40 ring-2 ring-red-400/50' : 'border border-zinc-200/90 bg-zinc-50 text-zinc-600 hover:border-zinc-300 hover:bg-zinc-100 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-800/80 dark:text-zinc-400 dark:hover:border-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-100'"
                                    :title="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'" :aria-label="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'"
                                >
                                    <span x-show="isListening" x-cloak class="absolute -inset-0.5 animate-ping rounded-lg bg-red-400 opacity-75"></span>
                                    <svg class="relative h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                    </svg>
                                </button>

                                <button
                                    x-show="isStreaming"
                                    x-cloak
                                    type="button"
                                    @click="stopStreaming()"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-3 py-1 text-xs font-medium text-white transition hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                >
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                        <rect x="6" y="6" width="12" height="12" rx="2" />
                                    </svg>
                                    <span>Stop</span>
                                </button>

                                <button
                                    x-show="!isStreaming"
                                    x-cloak
                                    type="submit"
                                    :disabled="!inputMessage.trim()"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-900 text-white transition hover:bg-zinc-800 disabled:opacity-30 disabled:cursor-not-allowed dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                                    title="Send" aria-label="Send"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </form>

                    <p class="mt-2 text-center text-[10px] text-zinc-400 dark:text-zinc-500">
                        Echo stages workspace writes. High-impact operations require your explicit confirmation.
                    </p>
                </div>
            </div>
        </main>
    </div>

    @include('filament.pages.ai-chat-style')

    @include('filament.pages.ai-chat-script')
</div>
