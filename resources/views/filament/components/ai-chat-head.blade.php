@if (\Filament\Facades\Filament::auth()->user()?->isSuperAdmin() && ! request()->boolean('embed'))
    @php
        $chatHeadUrl = \App\Filament\Pages\AdminAiApp::getUrl();
        $chatHeadPaths = collect([
            \App\Filament\Pages\AdminAiChat::getUrl(),
            $chatHeadUrl,
        ])->map(fn (string $url): string => (string) parse_url($url, PHP_URL_PATH))
            ->filter()
            ->unique()
            ->values()
            ->all();
    @endphp

    {{-- Floating Echo AI chat head (visible in the installed app only) -------- --}}
    <div
        data-echo-chat-head
        data-chat-url="{{ $chatHeadUrl }}"
        data-chat-paths="{{ Js::from($chatHeadPaths) }}"
        hidden
    >
        <button
            type="button"
            data-chat-head-bubble
            class="fixed end-4 bottom-4 z-[9999] flex h-14 w-14 touch-none cursor-grab items-center justify-center rounded-full border border-zinc-200/90 bg-white shadow-xl transition-transform duration-150 select-none hover:scale-105 active:scale-95 dark:border-zinc-700 dark:bg-zinc-900"
            aria-label="Open Echo AI chat"
            aria-haspopup="dialog"
            aria-expanded="false"
        >
            <img src="{{ route('favicon', ['size' => 192]) }}" alt="" class="h-10 w-10 rounded-full object-cover" />
            <span class="absolute bottom-1 end-1 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"></span>
        </button>

        <section
            data-chat-head-panel
            class="fixed z-[9999] flex flex-col overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-950"
            style="width: 22.5rem; max-width: calc(100vw - 2rem); height: min(30rem, calc(100dvh - 2rem));"
            role="dialog"
            aria-label="Echo AI chat"
            hidden
        >
            <header class="flex h-11 shrink-0 items-center justify-between gap-2 border-b border-zinc-200/90 bg-zinc-50/80 px-3 dark:border-zinc-800 dark:bg-zinc-900/70">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <img src="{{ route('favicon', ['size' => 192]) }}" alt="" class="h-7 w-7 object-cover" />
                    </span>
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-xs font-semibold text-zinc-900 dark:text-zinc-100">Echo AI</span>
                        <span class="text-[10px] leading-tight text-zinc-400 dark:text-zinc-500">Assistant</span>
                    </span>
                </div>

                <div class="flex items-center gap-0.5">
                    <button
                        type="button"
                        data-chat-head-expand
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        title="Open full chat" aria-label="Open full chat"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-chat-head-minimize
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        title="Minimize chat" aria-label="Minimize chat"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-chat-head-close
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        title="Close chat" aria-label="Close chat"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </header>

            <iframe
                data-chat-head-frame
                title="Echo AI chat"
                class="h-full w-full flex-1 border-0 bg-white dark:bg-zinc-950"
                allow="microphone"
                loading="lazy"
            ></iframe>
        </section>
    </div>

    <script src="{{ asset('js/ai-chat-head.js') }}" defer></script>
@endif
