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
        foxChatUrl: '{{ asset('images/mascots/fox-chat.webp') }}',
        foxWelcomeUrl: '{{ asset('images/mascots/fox-welcome.webp') }}',
    })"
    class="relative flex h-screen w-screen overflow-hidden bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100"
>
        {{-- Left Sidebar: Conversations & History -------------------------------- --}}
        <aside
            :class="sidebarOpen ? 'w-72 translate-x-0' : 'w-0 -translate-x-full md:w-0 md:translate-x-0'"
            class="relative flex flex-col border-r border-zinc-200/90 bg-zinc-50/70 transition-all duration-200 ease-in-out dark:border-zinc-800 dark:bg-zinc-900/60"
            style="min-width: 0;"
        >
            <div x-show="sidebarOpen" class="flex h-full flex-col p-3" style="width: 18rem;">
                {{-- Sidebar Header & New Chat button --}}
                <div class="mb-2.5 flex items-center justify-between gap-1.5">
                    <button
                        type="button"
                        @click="newChat()"
                        class="flex flex-1 items-center justify-between rounded-xl border border-zinc-200/90 bg-white px-3 py-2 text-xs font-medium text-zinc-800 shadow-2xs transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-850"
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
                        title="Close sidebar"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Search Filter --}}
                <div class="relative mb-2.5">
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="Search chats..."
                        class="w-full rounded-xl border border-zinc-200/90 bg-white py-1.5 pe-3 ps-8 text-xs text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:placeholder:text-zinc-500 dark:focus:border-zinc-600"
                    />
                    <svg class="pointer-events-none absolute start-2.5 top-2.5 h-3.5 w-3.5 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button
                        x-show="searchQuery"
                        @click="searchQuery = ''"
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
                                    @click="selectSession(item)"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="p-0.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            title="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="p-0.5 text-zinc-400 hover:text-red-500"
                                            title="Delete chat"
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
                                    @click="selectSession(item)"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="p-0.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            title="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="p-0.5 text-zinc-400 hover:text-red-500"
                                            title="Delete chat"
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
                                    @click="selectSession(item)"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="p-0.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            title="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="p-0.5 text-zinc-400 hover:text-red-500"
                                            title="Delete chat"
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
                                    @click="selectSession(item)"
                                    :class="activeSessionId === item.id ? 'bg-zinc-200/70 text-zinc-900 font-medium dark:bg-zinc-800 dark:text-zinc-100' : 'text-zinc-600 hover:bg-zinc-200/40 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-zinc-200'"
                                    class="group relative flex cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 transition"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                        </svg>
                                        <span class="truncate" x-text="item.title"></span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button
                                            type="button"
                                            @click.stop="copyConversationLink(item)"
                                            class="p-0.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                            title="Copy link"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="confirmDeleteSession(item.id)"
                                            class="p-0.5 text-zinc-400 hover:text-red-500"
                                            title="Delete chat"
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

        {{-- Main Chat Area ------------------------------------------------------ --}}
        <main class="relative flex min-w-0 flex-1 flex-col bg-white dark:bg-zinc-950">
            {{-- Top Navbar / Copilot Header --}}
            <header class="flex h-13 shrink-0 items-center justify-between border-b border-zinc-200/90 px-3.5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex items-center gap-2.5">
                    <a
                        href="/admin"
                        class="flex items-center gap-1.5 rounded-lg border border-zinc-200/90 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white"
                        title="Return to Admin Panel Dashboard"
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
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-850 dark:hover:text-zinc-200"
                        title="Toggle chat history"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>

                    {{-- Fox / AI Mascot Presence --}}
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-100 text-amber-500 dark:bg-zinc-800 dark:text-amber-400">
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
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Echo</span>
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
                                : 'border-zinc-200/90 text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-850 dark:hover:text-zinc-200'"
                            :title="linkCopied ? 'Link copied to clipboard!' : 'Copy link to this conversation'"
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
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-850 dark:hover:text-zinc-200"
                        title="Open in new window / tab"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>

                    <button
                        type="button"
                        @click="newChat()"
                        class="rounded-lg border border-zinc-200/90 p-1.5 text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-800 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-850 dark:hover:text-zinc-200"
                        title="Start fresh conversation"
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
                            {{-- Geometric Fox Mark --}}
                            <div class="welcome-logo mb-5 flex flex-col items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100 sm:h-14 sm:w-14">
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
                                </div>
                                <img :src="foxWelcomeUrl" alt="Fox Mascot" class="hidden" />
                            </div>

                            {{-- Clean, Simple Greeting --}}
                            <div class="welcome-greeting space-y-1 text-center">
                                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-100">
                                    <span x-text="greetingLine"></span>
                                </h1>
                                <p class="mx-auto max-w-md text-xs text-zinc-500 sm:text-sm dark:text-zinc-400" x-text="greetingSubtext"></p>
                                <span class="hidden" x-text="claudeTimeGreeting"></span>
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
                                        class="min-h-[64px] w-full resize-none border-0 bg-transparent p-0 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 sm:text-sm dark:text-zinc-100 dark:placeholder:text-zinc-500"
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
                                                :title="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'"
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
                                                title="Send"
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
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-amber-500 dark:bg-zinc-800 dark:text-amber-400">
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
                                                        :title="msg.activity?.collapsed ? 'Expand activity' : 'Collapse activity'"
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
                                                    <div class="flex items-start justify-between gap-3 text-xs">
                                                        <div class="flex min-w-0 items-start gap-2.5">
                                                            {{-- Completed Checkmark Icon --}}
                                                            <template x-if="step.status === 'completed'">
                                                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                    <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </template>
                                                            {{-- Running Spinner Icon --}}
                                                            <template x-if="step.status === 'running'">
                                                                <svg class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                                </svg>
                                                            </template>
                                                            {{-- Pending / Queued Icon --}}
                                                            <template x-if="step.status === 'pending'">
                                                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                </svg>
                                                            </template>

                                                            <div class="min-w-0">
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        class="font-medium"
                                                                        :class="step.status === 'running' ? 'text-blue-300 font-semibold' : (step.status === 'completed' ? 'text-zinc-200' : 'text-zinc-500')"
                                                                        x-text="step.label"
                                                                    ></span>
                                                                    <template x-if="step.badge">
                                                                        <span class="rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 font-mono text-[10px] text-amber-300" x-text="step.badge"></span>
                                                                    </template>
                                                                    <template x-if="step.target">
                                                                        <span class="font-mono text-[11px] text-zinc-400" x-text="step.target"></span>
                                                                    </template>
                                                                </div>
                                                                <template x-if="step.detail">
                                                                    <p class="mt-1 text-[11px] leading-relaxed text-zinc-400" x-text="step.detail"></p>
                                                                </template>
                                                            </div>
                                                        </div>
                                                        <span class="shrink-0 font-mono text-[11px] text-zinc-500" x-text="step.time || '—'"></span>
                                                    </div>
                                                </template>

                                                {{-- Upcoming Queued Step if running --}}
                                                <div x-show="msg.activity?.status === 'running'" class="space-y-3 pt-1">
                                                    <div class="flex items-center justify-between gap-3 text-xs text-zinc-500">
                                                        <div class="flex min-w-0 items-center gap-2.5">
                                                            <svg class="h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                <circle cx="12" cy="12" r="9" />
                                                            </svg>
                                                            <span class="rounded border border-zinc-800 bg-zinc-900 px-1 py-0.5 font-mono text-[10px] text-zinc-500">agent</span>
                                                            <span class="truncate">Finalize synthesis & stage workspace payload</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Console Footer --}}
                                            <div class="flex items-center justify-between border-t border-zinc-800/80 bg-zinc-950/80 px-4 py-2.5 sm:px-5">
                                                <span class="font-mono text-[11px] text-zinc-500" x-text="'Executing real-time pipeline • ' + (msg.elapsedSeconds || 1) + 's'"></span>
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
                                                            :title="action._collapsed ? 'Expand activity' : 'Collapse activity'"
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
                                                                <div class="flex items-start justify-between gap-3 text-xs">
                                                                    <div class="flex min-w-0 items-start gap-2.5">
                                                                        <template x-if="step.status === 'completed'">
                                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                                <circle cx="12" cy="12" r="9" />
                                                                                <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                                                            </svg>
                                                                        </template>
                                                                        <template x-if="step.status === 'running'">
                                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                                            </svg>
                                                                        </template>
                                                                        <template x-if="step.status === 'pending'">
                                                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-dasharray="2 2">
                                                                                <circle cx="12" cy="12" r="9" />
                                                                            </svg>
                                                                        </template>
                                                                        <div class="min-w-0">
                                                                            <div class="flex flex-wrap items-center gap-2">
                                                                                <span class="font-medium text-zinc-200" x-text="step.label"></span>
                                                                                <template x-if="step.badge">
                                                                                    <span class="rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 font-mono text-[10px] text-amber-300" x-text="step.badge"></span>
                                                                                </template>
                                                                                <template x-if="step.target">
                                                                                    <span class="font-mono text-[11px] text-zinc-400" x-text="step.target"></span>
                                                                                </template>
                                                                            </div>
                                                                            <template x-if="step.detail">
                                                                                <p class="mt-1 text-[11px] leading-relaxed text-zinc-400" x-text="step.detail"></p>
                                                                            </template>
                                                                        </div>
                                                                    </div>
                                                                    <span class="shrink-0 font-mono text-[11px] text-zinc-500" x-text="step.time || '—'"></span>
                                                                </div>
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
                                                                            <span class="font-medium text-zinc-200">Verified workspace authorization</span>
                                                                            <span class="font-mono text-[11px] text-zinc-400" x-text="action.workspace ? action.workspace.name : (workspace ? workspace.name : 'Active Workspace')"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <span class="shrink-0 font-mono text-[11px] text-zinc-500">0:02</span>
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
                                                                            <span class="font-medium text-zinc-200">Staged operation payload</span>
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
                                                                class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950/80 px-3 py-1.5 text-xs text-zinc-100 placeholder:text-zinc-500 focus:border-zinc-500 focus:outline-none focus:ring-0"
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
                                                                <span class="text-[11px] text-zinc-500">Nonce verification active · 15m expiration window</span>
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
                                                                    class="cursor-pointer rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-medium text-zinc-300 transition hover:bg-zinc-750 hover:text-white active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                                                >
                                                                    <span x-text="(action._loading || actionLoading[action.id]) ? 'Rejecting...' : 'Reject'"></span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click.stop.prevent="approveAction(action)"
                                                                    :disabled="action._loading || actionLoading[action.id] || isStreaming"
                                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-zinc-950 transition hover:bg-amber-400 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
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
                                                            <span class="shrink-0 font-mono text-[11px] text-zinc-500">0:01</span>
                                                        </div>

                                                        {{-- Auto-proceed countdown banner --}}
                                                        <div x-show="continuationActionId === action.id" class="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-950/40 px-3 py-2 text-xs">
                                                            <div class="flex items-center gap-2 text-emerald-200">
                                                                <span class="relative flex h-2 w-2">
                                                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                                                </span>
                                                                <span>Continuing next steps in <strong class="font-mono text-emerald-300" x-text="continuationCountdown + 's'"></strong>...</span>
                                                            </div>
                                                            <div class="flex items-center gap-1.5">
                                                                <button
                                                                    type="button"
                                                                    @click.stop.prevent="proceedContinuation()"
                                                                    class="cursor-pointer rounded-md bg-emerald-500 px-2.5 py-1 text-[11px] font-semibold text-zinc-950 transition hover:bg-emerald-400"
                                                                >
                                                                    Proceed now
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click.stop.prevent="cancelAutoProceed()"
                                                                    class="cursor-pointer rounded-md border border-zinc-700 bg-zinc-800/80 px-2 py-1 text-[11px] text-zinc-300 transition hover:bg-zinc-700"
                                                                >
                                                                    Cancel
                                                                </button>
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
                                                    <span class="font-mono text-[11px] text-zinc-500" x-text="(action.status === 'pending' ? 'Step 3 of 5' : (action.status === 'rejected' ? 'Terminated at Step 3 of 5' : 'Step 5 of 5')) + ' • ' + (action.status === 'executed' ? '0:22' : '0:15')"></span>
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
                                                class="mt-1 ml-0.5 inline-block h-3.5 w-1.5 animate-pulse rounded-2xs bg-amber-500"
                                                title="Generating..."
                                            ></span>
                                        </div>

                                        {{-- Message Actions (Copy & Read Aloud) --}}
                                        <div x-show="!msg.typing && msg.content" class="flex items-center gap-2 pt-0.5">
                                            <button
                                                type="button"
                                                @click="copyText(msg.content)"
                                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                title="Copy message"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                <span>Copy</span>
                                            </button>

                                            <button
                                                type="button"
                                                @click="speakText(msg.content)"
                                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                                :title="isSpeaking ? 'Stop speaking' : 'Read aloud with voice'"
                                            >
                                                <svg x-show="!isSpeaking" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                                </svg>
                                                <svg x-show="isSpeaking" class="h-3.5 w-3.5 text-amber-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                </svg>
                                                <span x-text="isSpeaking ? 'Speaking...' : 'Listen'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

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
                            class="w-full resize-none border-0 bg-transparent px-1 py-1 text-xs text-zinc-900 placeholder:text-zinc-400 focus:outline-none focus:ring-0 sm:text-sm dark:text-zinc-100 dark:placeholder:text-zinc-500"
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
                                    :title="isListening ? 'Stop listening (Microphone active)' : 'Voice dictation (Click to speak - 0 tokens)'"
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
                                    title="Send"
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

    <style>
        [x-cloak] {
            display: none !important;
        }

        .welcome-logo,
        .welcome-greeting,
        .welcome-input,
        .welcome-suggestions {
            animation: welcome-enter 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .welcome-greeting {
            animation-delay: 40ms;
        }

        .welcome-input {
            animation-delay: 80ms;
        }

        .welcome-suggestions {
            animation-delay: 120ms;
        }

        @keyframes welcome-enter {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes welcome-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ── Thinking 3-dots animation ── */
        .thinking-dot {
            display: inline-block;
            width: 4px;
            height: 4px;
            border-radius: 9999px;
            background-color: currentColor;
            animation: thinking-dot-bounce 1.3s infinite ease-in-out both;
        }
        .thinking-dot:nth-child(1) {
            animation-delay: -0.32s;
        }
        .thinking-dot:nth-child(2) {
            animation-delay: -0.16s;
        }
        .thinking-dot:nth-child(3) {
            animation-delay: 0s;
        }

        @keyframes thinking-dot-bounce {
            0%, 80%, 100% {
                transform: scale(0.6);
                opacity: 0.35;
            }
            40% {
                transform: scale(1.25);
                opacity: 1;
            }
        }

        /* ── Geometric Fox (ChatAiOrb from Student Chat) ────────── */
        .wolf-persona {
            --wolf-inner: #ffffff;
        }
        .dark .wolf-persona {
            --wolf-inner: #18181b;
        }

        .wolf-head,
        .wolf-circle,
        .wolf-spark {
            transform-box: view-box;
            transform-origin: 60px 60px;
        }
        .wolf-ear-left {
            transform-origin: 35px 48px;
        }
        .wolf-ear-right {
            transform-origin: 85px 48px;
        }
        .wolf-muzzle {
            transform-origin: 60px 78px;
        }

        .wolf-circle,
        .wolf-spark {
            opacity: 0;
        }

        /* ── Tri-form cycle: Fox -> Circle -> Spark -> Fox (3.6s) ── */
        [data-motion='welcome'] .wolf-head {
            animation: wolf-to-circle 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-circle {
            animation: wolf-circle-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-spark {
            animation: wolf-spark-reveal 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-ear-left {
            animation: wolf-ear-fold-left 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-ear-right {
            animation: wolf-ear-fold-right 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='welcome'] .wolf-muzzle {
            animation: wolf-muzzle-retract 3.6s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }

        [data-motion='listening'] .wolf-ear-left {
            animation: wolf-listen-left 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='listening'] .wolf-ear-right {
            animation: wolf-listen-right 2.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='thinking'] .wolf-head {
            animation: wolf-think 2s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }
        [data-motion='speaking'] .wolf-muzzle {
            animation: wolf-speak 0.5s cubic-bezier(0.77, 0, 0.175, 1) infinite !important;
        }

        @keyframes wolf-to-circle {
            0%, 14%, 86%, 100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
            26%, 74% {
                transform: scale(0.2) rotate(30deg);
                opacity: 0;
            }
        }

        @keyframes wolf-circle-reveal {
            0%, 14%, 54%, 100% {
                transform: scale(0);
                opacity: 0;
            }
            26%, 42% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes wolf-spark-reveal {
            0%, 42%, 86%, 100% {
                transform: scale(0) rotate(-35deg);
                opacity: 0;
            }
            54%, 72% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes wolf-ear-fold-left {
            0%, 14%, 86%, 100% {
                transform: rotate(0deg);
            }
            26%, 74% {
                transform: rotate(25deg) translate(6px, 8px);
            }
        }

        @keyframes wolf-ear-fold-right {
            0%, 14%, 86%, 100% {
                transform: rotate(0deg);
            }
            26%, 74% {
                transform: rotate(-25deg) translate(-6px, 8px);
            }
        }

        @keyframes wolf-muzzle-retract {
            0%, 14%, 86%, 100% {
                transform: translateY(0) scale(1);
            }
            26%, 74% {
                transform: translateY(-8px) scale(0.7);
            }
        }

        @keyframes wolf-listen-left {
            0%, 80%, 100% { transform: rotate(0deg); }
            40% { transform: rotate(-7deg); }
        }
        @keyframes wolf-listen-right {
            0%, 80%, 100% { transform: rotate(0deg); }
            40% { transform: rotate(7deg); }
        }
        @keyframes wolf-think {
            0%, 100% { transform: rotate(-3deg); opacity: 1; }
            50% { transform: rotate(3deg); opacity: 0.7; }
        }
        @keyframes wolf-speak {
            0%, 100% { transform: scaleY(1); }
            50% { transform: scaleY(0.92); }
        }

        @media (prefers-reduced-motion: reduce) {
            .welcome-logo,
            .welcome-greeting,
            .welcome-input,
            .welcome-suggestions {
                animation: welcome-fade 0.2s ease-out both;
            }
            .fox-float,
            .wolf-head,
            .wolf-circle,
            .wolf-spark,
            .wolf-ear-left,
            .wolf-ear-right,
            .wolf-muzzle {
                animation: none !important;
            }
        }
    </style>

    <script>
        const registerAdminAiChat = () => {
            if (window.Alpine && !window.Alpine._adminAiChatRegistered) {
                window.Alpine._adminAiChatRegistered = true;
                window.Alpine.data('adminAiChat', (config) => ({
                csrfToken: config.csrfToken,
                provider: config.provider,
                providerLabel: config.providerLabel,
                modelName: config.modelName,
                workspace: config.workspace,
                adminUser: config.adminUser,
                sessions: config.initialSessions || [],
                initialActiveSession: config.initialActiveSession || null,
                foxChatUrl: config.foxChatUrl,
                foxWelcomeUrl: config.foxWelcomeUrl,

                sidebarOpen: true,
                searchQuery: '',
                activeSessionId: config.initialActiveSession ? config.initialActiveSession.id : null,
                activeSessionUuid: config.initialActiveSession ? config.initialActiveSession.uuid : null,
                linkCopied: false,
                messages: [],
                aiActions: [],
                inputMessage: '',
                isStreaming: false,
                abortController: null,
                actionLoading: {},
                continuationActionId: null,
                continuationInterval: null,
                continuationCountdown: 0,
                continuationFollowUp: '',

                isListening: false,
                voiceSupported: (typeof window !== 'undefined' && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)),
                voiceRecognition: null,
                voiceError: null,
                isSpeaking: false,

                promptStarters: [
                    {
                        category: 'exams',
                        categoryLabel: 'Exams',
                        title: 'Draft a Quiz',
                        prompt: 'Create a 20-minute draft quiz for Section 10-A with 5 multiple-choice questions.'
                    },
                    {
                        category: 'grading',
                        categoryLabel: 'Grading',
                        title: 'Review Submissions',
                        prompt: 'List all pending student submissions and suggest scores based on the answer key.'
                    },
                    {
                        category: 'analytics',
                        categoryLabel: 'Analytics',
                        title: 'Score Insights',
                        prompt: 'Summarize recent student grades and flag topics where students need extra support.'
                    },
                    {
                        category: 'announcements',
                        categoryLabel: 'Announcements',
                        title: 'Exam Notice',
                        prompt: 'Draft an encouraging announcement reminding students about exam dates and rules.'
                    }
                ],

                get filteredPromptStarters() {
                    return this.promptStarters;
                },

                init() {
                    this.isListening = false;
                    this.isStreaming = false;
                    this.voiceError = null;
                    this.isSpeaking = false;

                    this.$nextTick(() => {
                        if (this.$refs.welcomeComposerInput && !this.activeSessionId) {
                            this.$refs.welcomeComposerInput.focus();
                        }
                    });

                    const urlParams = new URLSearchParams(window.location.search);
                    const requestedParam = urlParams.get('c') || urlParams.get('session');

                    if (this.initialActiveSession) {
                        this.selectSession(this.initialActiveSession, false);
                    } else if (requestedParam && requestedParam !== 'undefined' && requestedParam !== 'null') {
                        this.selectSession(requestedParam, false);
                    }

                    window.addEventListener('popstate', () => {
                        if (this.isStreaming) return;
                        const currentParams = new URLSearchParams(window.location.search);
                        const targetUuid = currentParams.get('c') || currentParams.get('session');

                        if (targetUuid && targetUuid !== 'undefined' && targetUuid !== 'null') {
                            if (this.activeSessionUuid !== targetUuid && String(this.activeSessionId) !== String(targetUuid)) {
                                this.selectSession(targetUuid, false);
                            }
                        } else {
                            if (this.activeSessionId !== null || this.activeSessionUuid !== null) {
                                this.activeSessionId = null;
                                this.activeSessionUuid = null;
                                this.messages = [];
                                this.aiActions = [];
                                this.inputMessage = '';
                                this.$nextTick(() => {
                                    if (this.$refs.welcomeComposerInput) {
                                        this.$refs.welcomeComposerInput.focus();
                                    }
                                });
                            }
                        }
                    });
                },

                get claudeTimeGreeting() {
                    const hour = new Date().getHours();
                    if (hour >= 5 && hour < 12) return 'Good morning';
                    if (hour >= 12 && hour < 17) return 'Good afternoon';
                    if (hour >= 17 && hour < 22) return 'Good evening';
                    return 'Working late';
                },

                get currentDateFormatted() {
                    return new Intl.DateTimeFormat('en-US', {
                        weekday: 'long',
                        month: 'short',
                        day: 'numeric'
                    }).format(new Date());
                },

                get greetingSubtext() {
                    return 'How can I assist your workspace, exams, and grading today?';
                },

                get firstName() {
                    if (this.adminUser && this.adminUser.first_name) {
                        return this.adminUser.first_name;
                    }
                    const raw = (this.adminUser && this.adminUser.name) ? this.adminUser.name : '';
                    const fallback = raw.trim().split(/\s+/)[0];
                    return fallback || 'Admin';
                },

                get timeGreeting() {
                    return this.claudeTimeGreeting;
                },

                get greetingLine() {
                    return this.firstName
                        ? `${this.timeGreeting}, ${this.firstName}`
                        : this.timeGreeting;
                },

                get filteredSessions() {
                    if (!this.searchQuery.trim()) {
                        return this.sessions;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.sessions.filter(s => s.title.toLowerCase().includes(q));
                },

                get groupedSessions() {
                    const today = [];
                    const yesterday = [];
                    const previousWeek = [];
                    const older = [];

                    const now = new Date();
                    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                    const startOfYesterday = startOfToday - (24 * 60 * 60 * 1000);
                    const startOf7Days = startOfToday - (7 * 24 * 60 * 1000);

                    this.filteredSessions.forEach(session => {
                        const time = session.updated_at ? new Date(session.updated_at).getTime() : 0;
                        if (time >= startOfToday) {
                            today.push(session);
                        } else if (time >= startOfYesterday) {
                            yesterday.push(session);
                        } else if (time >= startOf7Days) {
                            previousWeek.push(session);
                        } else {
                            older.push(session);
                        }
                    });

                    return { today, yesterday, previousWeek, older };
                },

                async selectSession(target, updateUrl = true) {
                    if (this.isStreaming) return;
                    if (!target || target === 'undefined' || target === 'null') return;

                    let session = null;
                    let identifier = target;

                    if (typeof target === 'object' && target !== null) {
                        session = target;
                        identifier = target.uuid || target.id;
                    } else if (target) {
                        session = this.sessions.find(s => s.uuid === target || String(s.id) === String(target));
                        identifier = target;
                    }

                    if (!identifier || identifier === 'undefined' || identifier === 'null') return;

                    if (session) {
                        this.activeSessionId = session.id;
                        this.activeSessionUuid = session.uuid || null;
                    } else {
                        if (typeof identifier === 'number' || (typeof identifier === 'string' && /^\d+$/.test(identifier))) {
                            this.activeSessionId = parseInt(identifier, 10);
                            this.activeSessionUuid = null;
                        } else {
                            this.activeSessionId = null;
                            this.activeSessionUuid = String(identifier);
                        }
                    }

                    const urlParam = this.activeSessionUuid || (this.activeSessionId ? String(this.activeSessionId) : null) || (identifier ? String(identifier) : null);
                    if (updateUrl && urlParam && urlParam !== 'undefined' && urlParam !== 'null') {
                        const url = new URL(window.location.href);
                        url.searchParams.set('c', urlParam);
                        url.searchParams.delete('session');
                        window.history.pushState({ sessionUuid: urlParam }, '', url.toString());
                    }

                    this.messages = [];
                    this.aiActions = [];

                    try {
                        const res = await fetch(`/api/chats/${identifier}/messages`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.session) {
                                this.activeSessionId = data.session.id;
                                this.activeSessionUuid = data.session.uuid;
                                if (!this.sessions.some(s => s.id === data.session.id)) {
                                    this.sessions.unshift({
                                        id: data.session.id,
                                        uuid: data.session.uuid,
                                        title: data.session.title || 'New chat',
                                        updated_at: data.session.updated_at,
                                        updated_at_human: data.session.updated_at_human || 'recently'
                                    });
                                }
                            }

                            this.messages = (data.data || []).map(m => ({
                                id: m.id,
                                role: m.role,
                                content: m.content,
                                thinking: m.thinking || null,
                                thinkingOpen: false,
                                typing: false,
                                createdAt: m.createdAt || null,
                                actionIds: []
                            }));
                            this.scrollToBottom();
                            this.$nextTick(() => {
                                if (this.$refs.composerInput) {
                                    this.$refs.composerInput.focus();
                                }
                            });
                        }
                        await this.loadAiActions(this.activeSessionId || identifier);
                        this.associateActionsWithMessages();
                    } catch (e) {
                        console.error('Error loading session messages:', e);
                    }
                },

                newChat() {
                    if (this.isStreaming) return;
                    this.activeSessionId = null;
                    this.activeSessionUuid = null;
                    this.messages = [];
                    this.aiActions = [];
                    this.inputMessage = '';

                    const url = new URL(window.location.href);
                    url.searchParams.delete('c');
                    url.searchParams.delete('session');
                    const cleanUrl = url.pathname + (url.search ? url.search : '');
                    window.history.pushState({}, '', cleanUrl);

                    this.$nextTick(() => {
                        if (this.$refs.welcomeComposerInput) {
                            this.$refs.welcomeComposerInput.focus();
                        }
                    });
                },

                async createChatSession() {
                    try {
                        const res = await fetch('/api/chats', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({})
                        });
                        if (res.ok) {
                            const json = await res.json();
                            const newSession = {
                                id: json.session.id,
                                uuid: json.session.uuid,
                                title: 'New chat',
                                updated_at: new Date().toISOString(),
                                updated_at_human: 'just now'
                            };
                            this.sessions.unshift(newSession);
                            this.activeSessionId = newSession.id;
                            this.activeSessionUuid = newSession.uuid;

                            const url = new URL(window.location.href);
                            url.searchParams.set('c', newSession.uuid || newSession.id);
                            url.searchParams.delete('session');
                            window.history.pushState({ sessionUuid: newSession.uuid || newSession.id }, '', url.toString());

                            return newSession.id;
                        }
                    } catch (e) {
                        console.error('Error creating new chat session:', e);
                    }
                    return null;
                },

                async confirmDeleteSession(sessionId) {
                    if (!confirm('Are you sure you want to delete this conversation?')) return;
                    try {
                        const res = await fetch(`/api/chats/${sessionId}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            this.sessions = this.sessions.filter(s => s.id !== sessionId);
                            if (this.activeSessionId === sessionId) {
                                if (this.sessions.length > 0) {
                                    this.selectSession(this.sessions[0]);
                                } else {
                                    this.newChat();
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Error deleting session:', e);
                    }
                },

                async copyConversationLink(item = null) {
                    const targetUuid = (item && (item.uuid || item.id)) || this.activeSessionUuid || this.activeSessionId;
                    if (!targetUuid) return;

                    const url = new URL(window.location.href);
                    url.searchParams.set('c', targetUuid);
                    url.searchParams.delete('session');
                    const fullUrl = url.toString();

                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(fullUrl);
                        } else {
                            const textarea = document.createElement('textarea');
                            textarea.value = fullUrl;
                            textarea.style.position = 'fixed';
                            textarea.style.left = '-9999px';
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                        }

                        this.linkCopied = true;
                        setTimeout(() => {
                            this.linkCopied = false;
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy link:', err);
                    }
                },

                async loadAiActions(sessionId) {
                    const sid = sessionId || this.activeSessionId;
                    if (!sid) return;
                    try {
                        const res = await fetch(`/api/ai-actions?session_id=${sid}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            const existingMap = new Map((this.aiActions || []).map(a => [a.id, a]));
                            this.aiActions = (data.data || []).map(action => {
                                const existing = existingMap.get(action.id);
                                return {
                                    ...action,
                                    _loading: existing?._loading ?? false,
                                    _inlineAnswer: existing?._inlineAnswer || '',
                                    _collapsed: existing?._collapsed ?? false
                                };
                            });
                        }
                    } catch (e) {
                        console.error('Error loading AI actions:', e);
                    }
                },

                associateActionsWithMessages() {
                    if (!this.messages.length || !this.aiActions.length) return;

                    const assistantMessages = [];
                    for (let i = 0; i < this.messages.length; i++) {
                        if (this.messages[i].role === 'assistant') {
                            let prevUserTime = null;
                            for (let u = i - 1; u >= 0; u--) {
                                if (this.messages[u].role === 'user' && this.messages[u].createdAt) {
                                    const parsed = Date.parse(this.messages[u].createdAt);
                                    if (!isNaN(parsed)) {
                                        prevUserTime = parsed;
                                        break;
                                    }
                                }
                            }

                            let nextUserTime = null;
                            for (let u = i + 1; u < this.messages.length; u++) {
                                if (this.messages[u].role === 'user' && this.messages[u].createdAt) {
                                    const parsed = Date.parse(this.messages[u].createdAt);
                                    if (!isNaN(parsed)) {
                                        nextUserTime = parsed;
                                        break;
                                    }
                                }
                            }

                            const rawMsgTime = this.messages[i].createdAt;
                            const parsedMsgTime = rawMsgTime ? Date.parse(rawMsgTime) : null;
                            const msgTime = (parsedMsgTime !== null && !isNaN(parsedMsgTime)) ? parsedMsgTime : null;

                            assistantMessages.push({
                                msg: this.messages[i],
                                index: i,
                                msgTime: msgTime,
                                startTime: prevUserTime ?? (msgTime ? msgTime - 60000 : null),
                                endTime: nextUserTime ?? Infinity,
                            });
                        }
                    }

                    if (!assistantMessages.length) return;

                    assistantMessages.forEach(am => {
                        am.msg.actionIds = am.msg.actionIds || [];
                    });

                    const assignedActionIds = new Set();
                    this.messages.forEach(m => {
                        if (m.actionIds) {
                            m.actionIds.forEach(id => assignedActionIds.add(id));
                        }
                    });

                    this.aiActions.forEach(action => {
                        if (assignedActionIds.has(action.id)) return;

                        const rawActionTime = action.createdAt;
                        const parsedActionTime = rawActionTime ? Date.parse(rawActionTime) : null;
                        const actionTime = (parsedActionTime !== null && !isNaN(parsedActionTime)) ? parsedActionTime : null;
                        let matched = null;

                        if (actionTime !== null) {
                            matched = assistantMessages.find(am => {
                                const start = am.startTime ?? 0;
                                const end = am.endTime ?? Infinity;
                                return actionTime >= (start - 10000) && actionTime < end;
                            });
                        }

                        if (!matched && actionTime !== null) {
                            let closest = null;
                            let minDiff = Infinity;
                            assistantMessages.forEach(am => {
                                if (am.msgTime !== null) {
                                    const diff = Math.abs(actionTime - am.msgTime);
                                    if (diff < minDiff) {
                                        minDiff = diff;
                                        closest = am;
                                    }
                                }
                            });
                            matched = closest;
                        }

                        if (!matched) {
                            matched = assistantMessages[assistantMessages.length - 1];
                        }

                        if (matched) {
                            if (!matched.msg.actionIds) {
                                matched.msg.actionIds = [];
                            }
                            if (!matched.msg.actionIds.includes(action.id)) {
                                matched.msg.actionIds.push(action.id);
                            }
                            assignedActionIds.add(action.id);
                        }
                    });
                },

                getActionsForMessage(msg, index) {
                    if (msg.role !== 'assistant') return [];
                    if (msg.action) return [msg.action];

                    if (msg.actionIds && msg.actionIds.length > 0) {
                        return this.aiActions.filter(a => msg.actionIds.includes(a.id));
                    }

                    // Fallback: If this is the last assistant message and there are actions not claimed by any message
                    const isLastAssistant = !this.messages.slice(index + 1).some(m => m.role === 'assistant');
                    if (isLastAssistant && this.aiActions.length > 0) {
                        const assignedIds = new Set();
                        this.messages.forEach(m => {
                            if (m.actionIds) {
                                m.actionIds.forEach(id => assignedIds.add(id));
                            }
                        });
                        return this.aiActions.filter(a => !assignedIds.has(a.id));
                    }

                    return [];
                },

                sendAdjustment(action) {
                    if (!action) return;
                    const text = (action._inlineAnswer || '').trim();
                    if (!text || this.isStreaming) return;

                    action._inlineAnswer = '';
                    this.sendMessage(text);
                },

                formatDuration(secs) {
                    if (!secs || secs < 0) return '0:01';
                    const mins = Math.floor(secs / 60);
                    const rem = secs % 60;
                    return `${mins}:${rem < 10 ? '0' : ''}${rem}`;
                },

                isLikelyAgentTask(text) {
                    if (!text) return false;
                    const lower = text.toLowerCase();
                    const keywords = [
                        'exam', 'test', 'quiz', 'assign', 'grade', 'student',
                        'course', 'section', 'announc', 'material', 'task',
                        'xp', 'create', 'delete', 'update', 'research', 'generate',
                        'add', 'make', 'draft', 'award', 'curriculum'
                    ];
                    return keywords.some(k => lower.includes(k));
                },

                deriveTaskTitle(text) {
                    if (!text) return 'Processing agent workspace pipeline';
                    const lower = text.toLowerCase();
                    if (lower.includes('exam') || lower.includes('quiz') || lower.includes('test')) {
                        return 'Staging database write: Create exam';
                    }
                    if (lower.includes('assignment') || lower.includes('homework')) {
                        return 'Staging database write: Create assignment';
                    }
                    if (lower.includes('announc')) {
                        return 'Staging database write: Post announcement';
                    }
                    if (lower.includes('course')) {
                        return 'Staging database write: Course operation';
                    }
                    if (lower.includes('section')) {
                        return 'Staging database write: Create class section';
                    }
                    if (lower.includes('xp') || lower.includes('gamif') || lower.includes('award')) {
                        return 'Staging database write: Student XP award';
                    }
                    if (lower.includes('research') || lower.includes('curriculum')) {
                        return 'Autonomous research & analysis';
                    }
                    return 'Processing agent workspace pipeline';
                },

                handleToolCallEvent(target, event) {
                    if (!target) return;
                    if (!target.activity) {
                        target.activity = {
                            title: 'Executing agent pipeline',
                            status: 'running',
                            startedAt: Date.now(),
                            collapsed: false,
                            hasToolCalls: true,
                            steps: [
                                {
                                    id: 'auth',
                                    label: 'Verified workspace authorization',
                                    target: this.workspace?.name || 'Active Workspace',
                                    status: 'completed',
                                    time: '0:02'
                                }
                            ]
                        };
                    } else {
                        target.activity.hasToolCalls = true;
                    }

                    // Mark previous running step as completed
                    const prevRunning = target.activity.steps.find(s => s.status === 'running');
                    if (prevRunning) {
                        prevRunning.status = 'completed';
                        const elapsed = Math.max(1, Math.round((Date.now() - (prevRunning.startTime || Date.now())) / 1000));
                        prevRunning.time = this.formatDuration(elapsed);
                    }

                    let args = {};
                    try {
                        args = typeof event.arguments === 'string' ? JSON.parse(event.arguments) : (event.arguments || {});
                    } catch (e) {
                        args = {};
                    }

                    const toolName = event.tool_name || 'agent_tool';
                    const step = {
                        id: toolName + '_' + Date.now(),
                        tool: toolName,
                        label: '',
                        target: '',
                        badge: null,
                        status: 'running',
                        startTime: Date.now(),
                        time: '0:01'
                    };

                    if (toolName === 'research_topic') {
                        step.label = 'Researching topic & curriculum';
                        step.target = args.topic ? ('Wikipedia · ' + args.topic) : 'Wikipedia / Knowledge Base';
                        target.activity.title = args.topic ? ('Autonomous research: ' + args.topic) : 'Autonomous research & analysis';
                    } else if (toolName === 'generate_exam_questions') {
                        step.label = 'Generating exam questions & answer key';
                        step.target = args.topic ? (args.topic + (args.question_count ? ` (${args.question_count} Qs)` : '')) : 'Curriculum Question Generator';
                    } else if (toolName === 'create_exam') {
                        step.label = 'Staging exam creation payload';
                        step.target = 'create_exam';
                        step.badge = 'Check';
                        target.activity.title = args.title ? ('Staging database write: ' + args.title) : 'Staging database write: Create exam';
                    } else if (toolName === 'update_exam') {
                        step.label = 'Staging exam modifications';
                        step.target = 'update_exam';
                        step.badge = 'Check';
                    } else if (toolName === 'create_assignment') {
                        step.label = 'Staging assignment payload';
                        step.target = 'create_assignment';
                        step.badge = 'Check';
                        target.activity.title = args.title ? ('Staging database write: ' + args.title) : 'Staging database write: Create assignment';
                    } else if (toolName === 'update_assignment') {
                        step.label = 'Staging assignment modifications';
                        step.target = 'update_assignment';
                        step.badge = 'Check';
                    } else if (toolName === 'post_announcement') {
                        step.label = 'Staging announcement broadcast';
                        step.target = 'post_announcement';
                        step.badge = 'Check';
                        target.activity.title = args.title ? ('Staging database write: ' + args.title) : 'Staging database write: Post announcement';
                    } else if (toolName === 'award_student_xp') {
                        step.label = 'Staging student gamification XP';
                        step.target = 'award_student_xp';
                        step.badge = 'Check';
                        target.activity.title = 'Staging database write: Student XP award';
                    } else if (toolName === 'record_grade' || toolName === 'grade_submission' || toolName === 'update_grade') {
                        step.label = 'Staging grade record payload';
                        step.target = toolName;
                        step.badge = 'Check';
                    } else if (toolName === 'create_course' || toolName === 'update_course') {
                        step.label = 'Staging course configuration';
                        step.target = toolName;
                        step.badge = 'Check';
                    } else if (toolName === 'create_section' || toolName === 'update_section') {
                        step.label = 'Staging section configuration';
                        step.target = toolName;
                        step.badge = 'Check';
                    } else if (toolName === 'create_user' || toolName === 'update_user' || toolName === 'reset_user_password') {
                        step.label = 'Staging user management payload';
                        step.target = toolName;
                        step.badge = 'Check';
                    } else if (toolName === 'workspace_overview') {
                        step.label = 'Querying workspace analytics';
                        step.target = 'workspace_overview';
                    } else if (toolName === 'courses_admin' || toolName === 'sections_admin') {
                        step.label = 'Inspecting workspace curriculum';
                        step.target = toolName;
                    } else if (toolName === 'admin_grades' || toolName === 'submissions_to_grade') {
                        step.label = 'Reviewing student submissions & grades';
                        step.target = toolName;
                    } else if (toolName === 'students') {
                        step.label = 'Querying enrolled students list';
                        step.target = 'students';
                    } else if (toolName.startsWith('delete_')) {
                        step.label = 'Staging database deletion: ' + toolName.replace('delete_', '');
                        step.target = toolName;
                        step.badge = 'Check';
                    } else {
                        step.label = 'Executing ' + toolName.replace(/_/g, ' ');
                        step.target = toolName;
                    }

                    target.activity.steps.push(step);
                    this.scrollToBottom();
                },

                handleToolResultEvent(target, event, sessionId) {
                    if (!target) return;
                    const toolName = event.tool_name || '';

                    if (target.activity && target.activity.steps) {
                        const step = [...target.activity.steps].reverse().find(s => s.tool === toolName && s.status === 'running')
                            || target.activity.steps.find(s => s.status === 'running');
                        if (step) {
                            step.status = 'completed';
                            const elapsed = Math.max(1, Math.round((Date.now() - (step.startTime || Date.now())) / 1000));
                            step.time = this.formatDuration(elapsed);
                        }
                    }

                    const isStagingTool = [
                        'create_exam', 'update_exam', 'delete_exam',
                        'create_assignment', 'update_assignment', 'delete_assignment',
                        'create_course', 'update_course', 'delete_course',
                        'create_section', 'update_section', 'delete_section',
                        'create_user', 'update_user', 'delete_user', 'reset_user_password',
                        'post_announcement', 'update_announcement', 'delete_announcement',
                        'create_learning_material', 'delete_learning_material',
                        'create_activity_task', 'delete_activity_task',
                        'award_student_xp', 'record_grade', 'update_grade', 'delete_grade',
                        'grade_submission'
                    ].includes(toolName);

                    if (isStagingTool) {
                        if (target.activity) {
                            target.activity.status = 'needs_approval';
                        }
                        const beforeActionIds = new Set((this.aiActions || []).map(a => a.id));
                        this.loadAiActions(sessionId).then(() => {
                            (this.aiActions || []).forEach(a => {
                                if (!beforeActionIds.has(a.id)) {
                                    target.actionIds = target.actionIds || [];
                                    if (!target.actionIds.includes(a.id)) {
                                        target.actionIds.push(a.id);
                                    }
                                }
                            });
                            this.associateActionsWithMessages();
                        });
                    }
                },

                async approveAction(action) {
                    if (!action) return;
                    if (!action.nonce) {
                        action.error = action.status === 'pending'
                            ? 'The 15-minute approval window has expired. Please ask Echo to prepare the action again.'
                            : ('This action has already been ' + (action.status || 'processed') + '.');
                        return;
                    }
                    action._loading = true;
                    this.actionLoading[action.id] = true;
                    action.error = null;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
                        const res = await fetch(`/api/ai-actions/${action.id}/approve`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nonce: action.nonce })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.data) {
                            action.status = data.data.status;
                            action.result = data.data.result;
                            action.error = null;
                            const inList = this.aiActions.find(a => a.id === action.id);
                            if (inList) {
                                inList.status = data.data.status;
                                inList.result = data.data.result;
                            }
                            this.handleActionApproved(action);
                        } else {
                            action.error = data.message || ('Execution failed (HTTP ' + res.status + ')');
                            if (data.data?.status) {
                                action.status = data.data.status;
                            }
                        }
                    } catch (e) {
                        action.error = e.message || 'Execution error';
                        console.error('approveAction error:', e);
                    } finally {
                        action._loading = false;
                        this.actionLoading[action.id] = false;
                    }
                },

                handleActionApproved(action) {
                    if (!action) return;
                    const lastAssistant = [...this.messages].reverse().find(m => m.role === 'assistant');
                    const assistantText = (lastAssistant?.content || '').toLowerCase();
                    const hasNextSteps = /once approved|i will then|i'll then|i'll immediately|i will immediately|step 1|step 2|next steps?|remaining steps?|proceed with|so i can proceed|1\..*2\./s.test(assistantText);

                    const followUpText = `Approved: ${action.result || action.title}. Please proceed immediately with the next steps from my previous request.`;

                    if (hasNextSteps && !this.isStreaming) {
                        this.startAutoProceed(action.id, followUpText);
                    }
                },

                startAutoProceed(actionId, followUpText) {
                    this.cancelAutoProceed();
                    this.continuationActionId = actionId;
                    this.continuationFollowUp = followUpText;
                    this.continuationCountdown = 2;

                    this.continuationInterval = setInterval(() => {
                        this.continuationCountdown--;
                        if (this.continuationCountdown <= 0) {
                            this.proceedContinuation();
                        }
                    }, 1000);
                },

                cancelAutoProceed() {
                    if (this.continuationInterval) {
                        clearInterval(this.continuationInterval);
                        this.continuationInterval = null;
                    }
                    this.continuationActionId = null;
                    this.continuationCountdown = 0;
                    this.continuationFollowUp = '';
                },

                proceedContinuation() {
                    const text = this.continuationFollowUp;
                    this.cancelAutoProceed();
                    if (text && !this.isStreaming) {
                        this.sendMessage(text);
                    }
                },

                manualContinue(action) {
                    if (!action || this.isStreaming) return;
                    this.cancelAutoProceed();
                    const followUpText = `Approved: ${action.result || action.title}. Please proceed immediately with the next steps from my previous request.`;
                    this.sendMessage(followUpText);
                },

                async rejectAction(action) {
                    if (!action) return;
                    if (!action.nonce) {
                        action.error = action.status === 'pending'
                            ? 'The 15-minute approval window has expired. Please ask Echo to prepare the action again.'
                            : ('This action has already been ' + (action.status || 'processed') + '.');
                        return;
                    }
                    action._loading = true;
                    this.actionLoading[action.id] = true;
                    action.error = null;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.csrfToken;
                        const res = await fetch(`/api/ai-actions/${action.id}/reject`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nonce: action.nonce })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.data) {
                            action.status = data.data.status;
                            action.error = null;
                            const inList = this.aiActions.find(a => a.id === action.id);
                            if (inList) {
                                inList.status = data.data.status;
                            }
                        } else {
                            action.error = data.message || ('Rejection failed (HTTP ' + res.status + ')');
                            if (data.data?.status) {
                                action.status = data.data.status;
                            }
                        }
                    } catch (e) {
                        action.error = e.message || 'Rejection error';
                        console.error('rejectAction error:', e);
                    } finally {
                        action._loading = false;
                        this.actionLoading[action.id] = false;
                    }
                },

                fillAndSend(prompt) {
                    this.inputMessage = prompt;
                    this.sendMessage();
                },

                async sendMessage(explicitText = null) {
                    this.cancelAutoProceed();
                    if (explicitText !== null) {
                        this.inputMessage = explicitText;
                    }
                    const text = this.inputMessage.trim();
                    if (!text || this.isStreaming) return;

                    if (!this.activeSessionId) {
                        const createdId = await this.createChatSession();
                        if (!createdId) return;
                    }

                    const sessionId = this.activeSessionId;
                    this.inputMessage = '';
                    if (this.$refs.composerInput) {
                        this.$refs.composerInput.style.height = 'auto';
                    }
                    if (this.$refs.welcomeComposerInput) {
                        this.$refs.welcomeComposerInput.style.height = 'auto';
                    }

                    this.stopSpeaking();
                    this.stopVoice();

                    // Append user message
                    this.messages.push({
                        role: 'user',
                        content: text,
                        typing: false,
                        createdAt: new Date().toISOString()
                    });

                    // Update session title locally if new
                    const activeSession = this.sessions.find(s => s.id === sessionId);
                    if (activeSession && (activeSession.title === 'New chat' || !activeSession.title)) {
                        activeSession.title = text.length > 50 ? text.substring(0, 50) + '...' : text;
                    }

                    // Check if the prompt is an agent workspace task to show initial activity immediately
                    const hasLikelyTask = this.isLikelyAgentTask(text);
                    const assistantIndex = this.messages.push({
                        role: 'assistant',
                        content: '',
                        thinking: null,
                        thinkingOpen: true,
                        thinkingStartedAt: Date.now(),
                        thinkingMs: null,
                        elapsedSeconds: 0,
                        typing: true,
                        createdAt: new Date().toISOString(),
                        actionIds: [],
                        activity: hasLikelyTask ? {
                            title: this.deriveTaskTitle(text),
                            status: 'running',
                            startedAt: Date.now(),
                            collapsed: false,
                            hasToolCalls: false,
                            steps: [
                                {
                                    id: 'auth',
                                    label: 'Verified workspace authorization',
                                    target: this.workspace?.name || 'Active Workspace',
                                    status: 'completed',
                                    time: '0:02'
                                },
                                {
                                    id: 'analyze',
                                    label: 'Analyzing request & workspace context',
                                    target: 'Echo Agent',
                                    status: 'running',
                                    startTime: Date.now(),
                                    time: '0:01'
                                }
                            ]
                        } : null
                    }) - 1;

                    if (this.streamingTimer) {
                        clearInterval(this.streamingTimer);
                    }
                    this.streamingTimer = setInterval(() => {
                        const target = this.messages[assistantIndex];
                        if (target && target.typing) {
                            const started = target.thinkingStartedAt;
                            if (started) {
                                target.elapsedSeconds = Math.max(1, Math.round((Date.now() - started) / 1000));
                            }
                            if (target.activity && target.activity.steps) {
                                const runningStep = target.activity.steps.find(s => s.status === 'running');
                                if (runningStep && runningStep.startTime) {
                                    const secs = Math.max(1, Math.round((Date.now() - runningStep.startTime) / 1000));
                                    runningStep.time = this.formatDuration(secs);
                                }
                            }
                        }
                    }, 500);

                    this.isStreaming = true;
                    this.scrollToBottom();

                    this.$nextTick(() => {
                        if (this.$refs.composerInput) {
                            this.$refs.composerInput.focus();
                        }
                    });

                    this.abortController = new AbortController();
                    const existingActionIds = new Set((this.aiActions || []).map(a => a.id));

                    try {
                        const response = await fetch(`/api/chats/${sessionId}/stream`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'text/event-stream',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ message: text }),
                            signal: this.abortController.signal
                        });

                        if (!response.ok) {
                            throw new Error('Chat service returned HTTP ' + response.status);
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';
                        let assistantText = '';
                        let streamDone = false;

                        while (!streamDone) {
                            const { done, value } = await reader.read();
                            if (done) break;

                            buffer += decoder.decode(value, { stream: true });
                            let boundary;
                            while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                                const chunk = buffer.slice(0, boundary);
                                buffer = buffer.slice(boundary + 2);

                                const line = chunk.split('\n').find(l => l.startsWith('data: '));
                                if (!line) continue;

                                const payload = line.slice(6);
                                if (payload === '[DONE]') {
                                    streamDone = true;
                                    break;
                                }

                                try {
                                    const event = JSON.parse(payload);
                                    const target = this.messages[assistantIndex];

                                    if (event.type === 'tool_call') {
                                        this.handleToolCallEvent(target, event);
                                    } else if (event.type === 'tool_result') {
                                        this.handleToolResultEvent(target, event, sessionId);
                                    } else if (event.type === 'reasoning_delta' && event.delta) {
                                        if (!target.thinking) {
                                            target.thinking = '';
                                            target.thinkingOpen = true;
                                            target.thinkingStartedAt = Date.now();
                                        }
                                        target.thinking += event.delta;
                                        this.scrollToBottom();
                                    } else if (event.type === 'text_delta' && event.delta) {
                                        if (target.thinkingStartedAt && !target.thinkingMs) {
                                            target.thinkingMs = Math.max(1, Math.round((Date.now() - target.thinkingStartedAt) / 1000));
                                            target.thinkingOpen = false;
                                        }
                                        if (target.activity && target.activity.steps) {
                                            const analyzeStep = target.activity.steps.find(s => s.id === 'analyze' && s.status === 'running');
                                            if (analyzeStep) {
                                                analyzeStep.status = 'completed';
                                                analyzeStep.time = this.formatDuration(Math.max(1, Math.round((Date.now() - (analyzeStep.startTime || Date.now())) / 1000)));
                                            }
                                        }
                                        assistantText += event.delta;
                                        target.content = assistantText;
                                        this.scrollToBottom();
                                    }
                                } catch (e) {
                                    // Ignore non-json or delta parse errors
                                }
                            }
                        }
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            this.messages[assistantIndex].content += '\n\n*(Echo encountered an issue: ' + (err.message || 'connection failed') + ')*';
                        }
                    } finally {
                        if (this.streamingTimer) {
                            clearInterval(this.streamingTimer);
                            this.streamingTimer = null;
                        }
                        const target = this.messages[assistantIndex];
                        if (target) {
                            target.typing = false;
                            if (target.activity) {
                                target.activity.steps.forEach(s => {
                                    if (s.status === 'running') {
                                        s.status = 'completed';
                                        if (!s.time && s.startTime) {
                                            s.time = this.formatDuration(Math.max(1, Math.round((Date.now() - s.startTime) / 1000)));
                                        }
                                    }
                                });
                            }
                        }
                        this.isStreaming = false;
                        this.abortController = null;
                        await this.loadAiActions(sessionId);
                        if (target) {
                            (this.aiActions || []).forEach(a => {
                                if (!existingActionIds.has(a.id)) {
                                    target.actionIds = target.actionIds || [];
                                    if (!target.actionIds.includes(a.id)) {
                                        target.actionIds.push(a.id);
                                    }
                                }
                            });
                        }
                        this.associateActionsWithMessages();
                        if (target && target.activity) {
                            const targetActions = this.getActionsForMessage(target, assistantIndex);
                            if (targetActions.length > 0) {
                                target.activity.status = 'needs_approval';
                            } else {
                                target.activity.status = 'completed';
                                if (!target.activity.hasToolCalls && targetActions.length === 0) {
                                    target.activity = null;
                                }
                            }
                        }
                        this.scrollToBottom();
                    }
                },

                stopStreaming() {
                    this.stopSpeaking();
                    if (this.streamingTimer) {
                        clearInterval(this.streamingTimer);
                        this.streamingTimer = null;
                    }
                    if (this.abortController) {
                        this.abortController.abort();
                        this.isStreaming = false;
                    }
                },

                handleKeyDown(event) {
                    if (event.key === 'Enter') {
                        if (event.shiftKey) {
                            // Shift + Enter: create a newline and expand textarea height
                            this.$nextTick(() => this.autoGrowTextarea(event));
                            return;
                        }

                        if (event.isComposing) {
                            return;
                        }

                        // Plain Enter: submit the message without adding a newline
                        event.preventDefault();
                        this.sendMessage();
                    }
                },

                autoGrowTextarea(event) {
                    this.cancelAutoProceed();
                    const el = event?.target || event;
                    if (!el || !el.style) return;
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 180) + 'px';
                },

                handleScroll() {
                    // Optional user scroll tracking
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const container = this.$refs.messageContainer;
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                },

                copyText(text) {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text);
                    }
                },

                toggleVoice() {
                    if (this.isStreaming) return;

                    const SpeechRecognition = typeof window !== 'undefined'
                        ? (window.SpeechRecognition || window.webkitSpeechRecognition)
                        : null;

                    if (!SpeechRecognition) {
                        this.voiceError = 'Voice dictation requires Google Chrome, Microsoft Edge, Safari, or Opera with Web Speech support.';
                        return;
                    }

                    if (this.isListening) {
                        this.stopVoice();
                        return;
                    }

                    this.startVoice();
                },

                startVoice() {
                    this.voiceError = null;
                    const SpeechRecognition = typeof window !== 'undefined'
                        ? (window.SpeechRecognition || window.webkitSpeechRecognition)
                        : null;

                    if (!SpeechRecognition) {
                        this.voiceError = 'Voice dictation is not supported in this browser.';
                        return;
                    }

                    try {
                        const rec = new SpeechRecognition();
                        rec.continuous = false;
                        rec.interimResults = true;
                        rec.lang = 'en-US';

                        let baseText = (this.inputMessage || '').trim();

                        rec.onstart = () => {
                            this.isListening = true;
                            this.voiceError = null;
                            baseText = (this.inputMessage || '').trim();
                        };

                        rec.onresult = (event) => {
                            let interim = '';
                            let finalTranscript = '';

                            for (let i = event.resultIndex; i < event.results.length; ++i) {
                                if (event.results[i].isFinal) {
                                    finalTranscript += event.results[i][0].transcript;
                                } else {
                                    interim += event.results[i][0].transcript;
                                }
                            }

                            const spoken = (finalTranscript || interim).trim();
                            if (spoken) {
                                this.inputMessage = baseText ? (baseText + ' ' + spoken) : spoken;
                                this.$nextTick(() => {
                                    if (this.$refs.composerInput) {
                                        this.autoGrowTextarea({ target: this.$refs.composerInput });
                                    }
                                    if (this.$refs.welcomeComposerInput) {
                                        this.autoGrowTextarea({ target: this.$refs.welcomeComposerInput });
                                    }
                                });
                            }
                        };

                        rec.onerror = (event) => {
                            console.warn('Speech recognition error:', event.error);
                            if (event.error === 'no-speech') {
                                this.isListening = false;
                                return;
                            }

                            if (event.error === 'not-allowed') {
                                this.voiceError = 'Microphone dictation was blocked. In Edge/Chrome InPrivate (Incognito) mode, browser policy disables cloud speech dictation. Please open in a regular browser window to dictate for free with 0 tokens!';
                            } else if (event.error === 'network') {
                                this.voiceError = 'Speech service network error. Please verify your internet connection.';
                            } else if (event.error === 'audio-capture') {
                                this.voiceError = 'No audio captured. Check Windows sound settings to ensure your default microphone is active.';
                            } else {
                                this.voiceError = 'Dictation error: ' + event.error;
                            }

                            setTimeout(() => { this.voiceError = null; }, 8000);
                            this.isListening = false;
                        };

                        rec.onend = () => {
                            this.isListening = false;
                        };

                        this.voiceRecognition = rec;
                        rec.start();
                    } catch (e) {
                        console.error('Speech recognition start failed:', e);
                        this.voiceError = 'Could not start microphone dictation.';
                        this.isListening = false;
                    }
                },

                stopVoice() {
                    if (this.voiceRecognition) {
                        try {
                            this.voiceRecognition.stop();
                        } catch (e) {}
                    }
                    this.isListening = false;
                },

                speakText(text) {
                    if (typeof window === 'undefined' || !window.speechSynthesis) return;

                    if (this.isSpeaking) {
                        window.speechSynthesis.cancel();
                        this.isSpeaking = false;
                        return;
                    }

                    const clean = (text || '')
                        .replace(/```[\s\S]*?```/g, 'Code block omitted.')
                        .replace(/`([^`]+)`/g, '$1')
                        .replace(/\*\*([^*]+)\*\*/g, '$1')
                        .replace(/\*([^*]+)\*/g, '$1')
                        .replace(/#+\s+/g, '')
                        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
                        .replace(/[|>\-_~]/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (!clean) return;

                    const utterance = new SpeechSynthesisUtterance(clean);
                    utterance.rate = 1.05;
                    utterance.pitch = 1.0;

                    utterance.onstart = () => {
                        this.isSpeaking = true;
                    };
                    utterance.onend = () => {
                        this.isSpeaking = false;
                    };
                    utterance.onerror = () => {
                        this.isSpeaking = false;
                    };

                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(utterance);
                },

                stopSpeaking() {
                    if (typeof window !== 'undefined' && window.speechSynthesis) {
                        window.speechSynthesis.cancel();
                        this.isSpeaking = false;
                    }
                },

                formatMarkdown(raw) {
                    if (!raw) return '';
                    let text = String(raw);

                    // Escape HTML
                    text = text.replace(/&/g, '&amp;')
                               .replace(/</g, '&lt;')
                               .replace(/>/g, '&gt;');

                    // Code blocks with copy button
                    text = text.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
                        return `<div class="my-3 rounded-xl border border-gray-200 bg-gray-900 text-gray-100 p-3 font-mono text-xs overflow-x-auto dark:border-white/10"><div class="flex justify-between items-center text-[10px] text-gray-400 pb-2 border-b border-gray-800"><span>${lang || 'code'}</span><button type="button" class="hover:text-white" onclick="navigator.clipboard.writeText(decodeURIComponent('${encodeURIComponent(code)}'))">Copy</button></div><pre class="pt-2"><code>${code}</code></pre></div>`;
                    });

                    // Inline code
                    text = text.replace(/`([^`]+)`/g, '<code class="rounded bg-gray-100 dark:bg-zinc-800 px-1.5 py-0.5 font-mono text-xs text-amber-700 dark:text-amber-400">$1</code>');

                    // Headers
                    text = text.replace(/^### (.*$)/gim, '<h4 class="text-xs font-bold text-gray-900 dark:text-white mt-3 mb-1">$1</h4>');
                    text = text.replace(/^## (.*$)/gim, '<h3 class="text-sm font-bold text-gray-900 dark:text-white mt-4 mb-1.5">$1</h3>');
                    text = text.replace(/^# (.*$)/gim, '<h2 class="text-base font-extrabold text-gray-900 dark:text-white mt-4 mb-2">$1</h2>');

                    // Bold & Italic
                    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong class="font-semibold text-gray-900 dark:text-white">$1</strong>');
                    text = text.replace(/\*([^*]+)\*/g, '<em class="italic">$1</em>');

                    // Blockquotes
                    text = text.replace(/^\> (.*$)/gim, '<blockquote class="border-s-2 border-amber-500 ps-3 my-2 text-xs italic text-gray-500 dark:text-gray-400">$1</blockquote>');

                    // Unordered list
                    text = text.replace(/^\s*[-*]\s+(.*)$/gim, '<li class="ms-4 list-disc text-xs leading-relaxed">$1</li>');

                    // Tables
                    if (text.includes('|')) {
                        const lines = text.split('\n');
                        let inTable = false;
                        let tableHtml = '';
                        const outputLines = [];

                        for (let i = 0; i < lines.length; i++) {
                            const line = lines[i].trim();
                            if (line.startsWith('|') && line.endsWith('|')) {
                                const cells = line.slice(1, -1).split('|').map(c => c.trim());
                                if (!inTable) {
                                    inTable = true;
                                    tableHtml = '<div class="my-3 overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="min-w-full text-xs divide-y divide-gray-200 dark:divide-white/10"><thead class="bg-gray-50 dark:bg-zinc-800"><tr>';
                                    cells.forEach(c => { tableHtml += `<th class="px-3 py-2 text-left font-semibold text-gray-900 dark:text-white">${c}</th>`; });
                                    tableHtml += '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-white/5">';
                                } else if (line.includes('---')) {
                                    // Skip separator line
                                } else {
                                    tableHtml += '<tr>';
                                    cells.forEach(c => { tableHtml += `<td class="px-3 py-2 text-gray-700 dark:text-gray-300">${c}</td>`; });
                                    tableHtml += '</tr>';
                                }
                            } else {
                                if (inTable) {
                                    inTable = false;
                                    tableHtml += '</tbody></table></div>';
                                    outputLines.push(tableHtml);
                                }
                                outputLines.push(lines[i]);
                            }
                        }
                        if (inTable) {
                            tableHtml += '</tbody></table></div>';
                            outputLines.push(tableHtml);
                        }
                        text = outputLines.join('\n');
                    }

                    // Links
                    text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-amber-600 underline dark:text-amber-400 hover:text-amber-700">$1</a>');

                    // Line breaks
                    text = text.replace(/\n\n/g, '<div class="h-2"></div>');
                    text = text.replace(/\n/g, '<br/>');

                    return text;
                }
            }));
        };

        if (window.Alpine) {
            registerAdminAiChat();
        } else {
            document.addEventListener('alpine:init', registerAdminAiChat);
        }
    </script>
</div>
