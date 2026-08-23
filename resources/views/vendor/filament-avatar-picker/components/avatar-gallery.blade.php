{{-- -- this file OVERRIDES the Matondo Avatar Picker gallery view. --}}
{{--
    Published by hand (equivalent to
    `php artisan vendor:publish --tag=filament-avatar-picker-views`,
    keeping only the view we customize).

    Differences from the vendor original:

    1. The original lists files via `Storage::disk('public')->files('avatars')`
       and keeps ONLY png/jpg/jpeg/webp — which silently hides the curated
       avatar-*.svg files this application ships, leaving the gallery empty.
       It also breaks when the public disk is S3/R2 in production, because the
       curated files live in the repo (storage/app/public), not the bucket.
       Here the list comes from App\Support\AvatarGallery instead, the same
       source the student-facing picker uses, so both galleries always show
       the exact same curated set.
    2. Each item carries both the DB value (path) and a resolved URL
       (App\Support\PublicFileUrl), so images render correctly whether the
       public disk is local (public/storage symlink) or S3/R2.

    Markup, styling, lazy-loading ("load more" on scroll) and the empty state
    are unchanged from the vendor view.
--}}
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        // Same curated list the student profile picker shows, with URLs
        // already resolved for the configured public disk.
        $avatars = \App\Support\AvatarGallery::items();
    @endphp

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 20px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(107, 114, 128, 0.8);
        }
    </style>
    <div x-data="{
        state: $wire.$entangle('{{ $getStatePath() }}'),
        allAvatars: @js($avatars),
        limit: 100,
        get visibleAvatars() { return this.allAvatars.slice(0, this.limit); },
        loadMore() { this.limit += 50; }
    }">
        <div
            style="min-height: 250px; max-height: 50vh; overflow-y: auto; overflow-x: hidden; display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 12px; justify-content: center; align-content: start;"
            class="p-2 sm:p-4 custom-scrollbar"
            @scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 20) loadMore()"
        >
            <template x-for="avatar in visibleAvatars" :key="avatar.path">
                <button
                    type="button"
                    @click="state = avatar.path"
                    :style="state === avatar.path
                        ? 'aspect-ratio: 1/1; width: 100%; max-width: 100px; border-style: solid; border-width: 4px; border-color: #f97316; border-color: var(--primary-600); border-color: rgb(var(--primary-600)); transform: scale(1.05); box-shadow: 0px 4px 10px rgba(0,0,0,0.2); transition: all 0.2s;'
                        : 'aspect-ratio: 1/1; width: 100%; max-width: 100px; border: 1px solid #e5e7eb; transition: all 0.2s;'"
                    class="relative rounded-full overflow-hidden shrink-0 focus:outline-none mx-auto"
                >
                    <img :src="avatar.url" :alt="avatar.name" class="w-full h-full object-cover">
                </button>
            </template>
        </div>

        <template x-if="allAvatars.length === 0">
            <div class="text-sm text-gray-500 text-center py-4">{{ __('filament-avatar-picker::avatar.no_avatars') }}</div>
        </template>
    </div>
</x-dynamic-component>
