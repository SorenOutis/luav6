<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        /**
         * Only the checked-in curated avatars belong in this gallery. Uploaded
         * avatars use the same disk but must not silently become choices for
         * every other user.
         */
        $avatars = collect(\Illuminate\Support\Facades\Storage::disk('public')->files('avatars'))
            ->filter(fn (string $path): bool => preg_match('/^avatar-\d+\.svg$/i', basename($path)) === 1)
            ->sortBy(fn (string $path): int => (int) preg_replace('/\D+/', '', basename($path)))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => str_replace('-', ' ', ucfirst(pathinfo($path, PATHINFO_FILENAME))),
                'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
            ])
            ->values()
            ->all();
    @endphp

    <div
        class="avatar-picker-gallery"
        x-data="{
            selected: $wire.$entangle('{{ $getStatePath() }}'),
            allAvatars: @js($avatars),
            limit: 48,
            get visibleAvatars() {
                return this.allAvatars.slice(0, this.limit);
            },
            loadMore() {
                this.limit += 48;
            },
        }"
    >
        <div
            class="avatar-picker-gallery__grid"
            role="listbox"
            aria-label="Curated avatars"
            @scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) loadMore()"
        >
            <template x-for="avatar in visibleAvatars" :key="avatar.path">
                <button
                    type="button"
                    role="option"
                    class="avatar-picker-gallery__option"
                    :class="{ 'avatar-picker-gallery__option--selected': selected === avatar.path }"
                    :aria-label="`Choose ${avatar.name}`"
                    :aria-selected="selected === avatar.path"
                    @click="selected = avatar.path"
                >
                    <img
                        :src="avatar.url"
                        :alt="avatar.name"
                        class="avatar-picker-gallery__image"
                        loading="lazy"
                    />
                    <span class="sr-only" x-text="avatar.name"></span>
                </button>
            </template>
        </div>

        <template x-if="allAvatars.length === 0">
            <p class="avatar-picker-gallery__empty">
                No curated avatars are available yet.
            </p>
        </template>
    </div>

    <style>
        .avatar-picker-gallery__grid {
            align-content: start;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fill, minmax(4.75rem, 1fr));
            max-height: min(50vh, 28rem);
            min-height: 15rem;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 0.25rem;
        }

        .avatar-picker-gallery__grid::-webkit-scrollbar {
            width: 0.375rem;
        }

        .avatar-picker-gallery__grid::-webkit-scrollbar-thumb {
            background: color-mix(in oklab, var(--gray-400) 55%, transparent);
            border-radius: 999px;
        }

        .avatar-picker-gallery__option {
            aspect-ratio: 1;
            background: transparent;
            border: 2px solid color-mix(in oklab, var(--gray-300) 75%, transparent);
            border-radius: 999px;
            cursor: pointer;
            display: block;
            overflow: hidden;
            padding: 0;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease;
            width: 100%;
        }

        .avatar-picker-gallery__option:hover {
            border-color: color-mix(in oklab, var(--primary-500) 70%, transparent);
            transform: translateY(-1px) scale(1.03);
        }

        .avatar-picker-gallery__option:focus-visible {
            outline: 2px solid var(--primary-500);
            outline-offset: 3px;
        }

        .avatar-picker-gallery__option--selected {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 25%, transparent);
            transform: scale(1.04);
        }

        .avatar-picker-gallery__image {
            display: block;
            height: 100%;
            object-fit: cover;
            width: 100%;
        }

        .avatar-picker-gallery__empty {
            color: var(--gray-500);
            font-size: 0.875rem;
            padding: 2rem 1rem;
            text-align: center;
        }

        .dark .avatar-picker-gallery__option {
            border-color: color-mix(in oklab, var(--gray-600) 80%, transparent);
        }
    </style>
</x-dynamic-component>
