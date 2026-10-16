{{-- Session rows: real buttons so keyboard and screen readers get a single,
     non-nested control per conversation. $source is an Alpine array expression. --}}
<div class="space-y-0.5">
    <template x-for="item in {{ $source }}" :key="item.id">
        <div
            :class="activeSessionId === item.id ? 'bg-zinc-200/70 dark:bg-zinc-800' : 'hover:bg-zinc-200/40 dark:hover:bg-zinc-800/50'"
            class="group relative flex items-center justify-between rounded-lg transition"
        >
            <button
                type="button"
                @click="selectSession(item)"
                :aria-current="activeSessionId === item.id ? 'true' : 'false'"
                :class="activeSessionId === item.id ? 'font-medium text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 dark:hover:text-zinc-200'"
                class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2.5 py-1.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50"
            >
                <svg class="h-3.5 w-3.5 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:text-zinc-500 dark:group-hover:text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>
                <span class="truncate" x-text="item.title"></span>
            </button>

            <div class="flex shrink-0 items-center gap-0.5 pe-1 opacity-100 transition md:gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100">
                <button
                    type="button"
                    @click="copyConversationLink(item)"
                    class="rounded p-1.5 text-zinc-500 transition hover:text-zinc-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 md:p-0.5 md:text-zinc-400 dark:text-zinc-400 dark:hover:text-zinc-200"
                    title="Copy link" aria-label="Copy link"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </button>
                <button
                    type="button"
                    @click="confirmDeleteSession(item.id)"
                    class="rounded p-1.5 text-zinc-500 transition hover:text-red-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 md:p-0.5 md:text-zinc-400"
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
