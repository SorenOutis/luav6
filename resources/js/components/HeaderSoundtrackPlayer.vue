<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Music,
    Pause,
    PauseCircle,
    Play,
    Volume1,
    Volume2,
    VolumeX,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSoundtrack } from '@/composables/useSoundtrack';
import type { UserSoundtrack } from '@/types/auth';

const page = usePage();
const soundtrack = computed<UserSoundtrack | null>(
    () =>
        (page.props.auth?.soundtrack as UserSoundtrack | null | undefined) ??
        null,
);

const {
    isPlaying,
    isMuted,
    volume,
    eqBars,
    isPausedForExam,
    initTrack,
    togglePlay,
    toggleMute,
    setVolume,
} = useSoundtrack();

// Keep track synced with user's selected soundtrack
watch(
    soundtrack,
    (newTrack) => {
        initTrack(newTrack);
    },
    { immediate: true },
);

const volumeIcon = computed(() => {
    if (isMuted.value || volume.value === 0) return VolumeX;
    if (volume.value <= 0.5) return Volume1;
    return Volume2;
});
</script>

<template>
    <div v-if="soundtrack" class="flex items-center">
        <!-- Paused during exam indicator -->
        <div
            v-if="isPausedForExam"
            class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-700 dark:text-amber-300"
            title="Soundtrack automatically paused for exam concentration. It will resume once you finish or exit."
        >
            <PauseCircle
                class="size-3.5 animate-pulse text-amber-600 dark:text-amber-400"
            />
            <span class="hidden sm:inline">Exam Mode</span>
            <span class="text-[10px] opacity-80">(Paused)</span>
        </div>

        <template v-else>
            <!-- ── Mobile Soundtrack Control (Dropdown) ────────────────── -->
            <div class="sm:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium shadow-2xs transition-all active:scale-95"
                            :class="
                                isPlaying && !isMuted
                                    ? 'border-primary/40 bg-primary/10 text-primary shadow-primary/10'
                                    : 'border-border/80 bg-background/80 text-muted-foreground hover:text-foreground'
                            "
                            :aria-label="`Profile soundtrack: ${soundtrack.title}`"
                        >
                            <component
                                :is="volumeIcon"
                                class="size-3.5 shrink-0"
                            />

                            <!-- Equalizer bars when playing on mobile -->
                            <div
                                v-if="isPlaying && !isMuted"
                                class="flex h-3 items-end gap-0.5 px-0.5"
                                aria-hidden="true"
                            >
                                <span
                                    class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                    :style="{ height: `${eqBars[0]}px` }"
                                ></span>
                                <span
                                    class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                    :style="{ height: `${eqBars[1]}px` }"
                                ></span>
                                <span
                                    class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                    :style="{ height: `${eqBars[2]}px` }"
                                ></span>
                                <span
                                    class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                    :style="{ height: `${eqBars[3]}px` }"
                                ></span>
                            </div>

                            <span
                                class="max-w-[70px] truncate text-[11px] font-semibold text-foreground"
                            >
                                {{ soundtrack.title }}
                            </span>
                        </button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        class="w-64 border-border/80 bg-background/95 p-3 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-background/90"
                    >
                        <!-- Track header & Play/Pause -->
                        <div
                            class="flex items-center justify-between gap-3 border-b border-border/50 pb-2.5"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <Music class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-bold text-foreground"
                                    >
                                        {{ soundtrack.title }}
                                    </p>
                                    <p
                                        class="truncate text-[10px] text-muted-foreground"
                                    >
                                        {{ soundtrack.artist }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex size-8 shrink-0 items-center justify-center rounded-full transition-all focus-visible:outline-none"
                                :class="
                                    isPlaying && !isMuted
                                        ? 'bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                                "
                                @click="togglePlay"
                            >
                                <Pause
                                    v-if="isPlaying && !isMuted"
                                    class="size-3.5 fill-current"
                                />
                                <Play
                                    v-else
                                    class="ml-0.5 size-3.5 fill-current"
                                />
                            </button>
                        </div>

                        <!-- Mobile Volume Control -->
                        <div class="space-y-1.5 pt-2.5">
                            <div
                                class="flex items-center justify-between text-[11px]"
                            >
                                <span
                                    class="flex items-center gap-1.5 font-medium text-muted-foreground"
                                >
                                    <component
                                        :is="volumeIcon"
                                        class="size-3 text-foreground"
                                    />
                                    Volume
                                </span>
                                <span
                                    class="font-mono text-[10px] font-semibold text-foreground"
                                >
                                    {{
                                        Math.round(
                                            (isMuted ? 0 : volume) * 100,
                                        )
                                    }}%
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="p-1 text-muted-foreground hover:text-foreground"
                                    @click="toggleMute"
                                >
                                    <VolumeX
                                        v-if="isMuted || volume === 0"
                                        class="size-3.5"
                                    />
                                    <Volume2 v-else class="size-3.5" />
                                </button>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    :value="isMuted ? 0 : volume"
                                    class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary focus:outline-none [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-muted [&::-webkit-slider-thumb]:-mt-[3px] [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-xs"
                                    @input="
                                        setVolume(
                                            Number(
                                                (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            ),
                                        )
                                    "
                                />
                            </div>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- ── Desktop Soundtrack Player Pill ──────────────────────── -->
            <div
                class="group/soundtrack relative hidden items-center gap-1.5 rounded-full border border-border/80 bg-background/80 py-0.5 pr-2.5 pl-1 shadow-2xs backdrop-blur-md transition-all hover:border-primary/50 sm:inline-flex"
            >
                <!-- Speaker mute / unmute button with hover slider -->
                <div class="group/vol relative flex items-center">
                    <button
                        type="button"
                        :aria-label="
                            isPlaying && !isMuted
                                ? 'Mute soundtrack'
                                : 'Play soundtrack'
                        "
                        :title="
                            isPlaying && !isMuted
                                ? `Mute soundtrack (${Math.round(volume * 100)}%)`
                                : `Play soundtrack: ${soundtrack.title}`
                        "
                        class="flex size-7 shrink-0 items-center justify-center rounded-full transition-all focus-visible:outline-none"
                        :class="
                            isPlaying && !isMuted
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                        "
                        @click="toggleMute"
                    >
                        <component :is="volumeIcon" class="size-3.5" />
                    </button>

                    <!-- Expandable mini volume slider on desktop hover -->
                    <div
                        class="hidden max-w-0 items-center overflow-hidden transition-all duration-200 group-hover/vol:max-w-[105px] group-hover/vol:pl-1.5 focus-within:max-w-[105px] focus-within:pl-1.5 sm:flex"
                    >
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.01"
                            :value="isMuted ? 0 : volume"
                            :title="`Volume: ${Math.round((isMuted ? 0 : volume) * 100)}%`"
                            class="h-1.5 w-14 cursor-pointer appearance-none rounded-full bg-muted accent-primary focus:outline-none [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-muted [&::-webkit-slider-thumb]:-mt-[3px] [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-xs"
                            @input="
                                setVolume(
                                    Number(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                )
                            "
                        />
                        <span
                            class="min-w-[28px] pl-1 font-mono text-[9px] text-muted-foreground"
                        >
                            {{ Math.round((isMuted ? 0 : volume) * 100) }}%
                        </span>
                    </div>
                </div>

                <!-- Title & live animated equalizer bars (clicking toggles play/pause) -->
                <button
                    type="button"
                    class="flex items-center gap-1.5 text-left text-xs transition-colors hover:text-primary focus-visible:outline-none"
                    :title="`${soundtrack.title} by ${soundtrack.artist} (Click to toggle playback)`"
                    @click="togglePlay"
                >
                    <!-- Equalizer bars when playing -->
                    <div
                        v-if="isPlaying && !isMuted"
                        class="flex h-3 items-end gap-0.5 px-0.5"
                        aria-hidden="true"
                    >
                        <span
                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                            :style="{ height: `${eqBars[0]}px` }"
                        ></span>
                        <span
                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                            :style="{ height: `${eqBars[1]}px` }"
                        ></span>
                        <span
                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                            :style="{ height: `${eqBars[2]}px` }"
                        ></span>
                        <span
                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                            :style="{ height: `${eqBars[3]}px` }"
                        ></span>
                    </div>
                    <Music v-else class="size-3 text-muted-foreground" />

                    <span
                        class="max-w-[70px] truncate text-[11px] font-medium text-foreground sm:max-w-[120px]"
                    >
                        {{ soundtrack.title }}
                    </span>
                </button>
            </div>
        </template>
    </div>
</template>
