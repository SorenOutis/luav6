<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { X, Plus, Megaphone, ArrowRight, RefreshCw } from 'lucide-vue-next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { useNumberAnimation } from '@/composables/useNumberAnimation';

interface Announcement {
    id: number;
    title: string;
    description?: string;
    link?: string;
    sectionName?: string | null;
    createdAt?: string | null;
}

interface UserStats {
    level: number;
    totalXP: number;
    currentXP: number;
    maxXPForLevel: number;
    points: number;
    rankNumber?: number;
    totalPlayers?: number;
    achievements?: number;
    streak?: number;
}

interface Props {
    userName: string;
    userAvatar?: string;
    /** Link to the user's public profile; makes the greeting avatar tappable. */
    profileHref?: string;
    userStats: UserStats;
    announcements: Announcement[];
    timeBasedGreeting: string;
    greetingTheme?: string;
    statusColor?: string;
    smarterStatus: string;
    isRefreshing?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits([
    'close-announcement',
    'refresh',
    'open-section-modal',
]);

const animatedLevel = useNumberAnimation(() => props.userStats.level);
</script>

<template>
    <div class="space-y-3 sm:space-y-5 lg:space-y-6">
        <TransitionGroup
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="opacity-0 -translate-y-3 scale-[0.99]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-[0.98]"
        >
            <section
                v-for="item in announcements.slice(0, 3)"
                :key="item.id"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#E8895F] via-[#D97757] to-[#B64A2E] p-4 text-white shadow-xl ring-1 shadow-[#D97757]/25 ring-white/10 sm:rounded-[1.75rem] sm:p-7"
                aria-live="polite"
            >
                <!-- Decorative depth layers -->
                <div
                    class="pointer-events-none absolute -top-24 -right-16 h-64 w-64 rounded-full bg-white/15 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute right-1/3 -bottom-28 h-56 w-56 rounded-full bg-black/10 blur-3xl"
                    aria-hidden="true"
                />
                <Megaphone
                    class="pointer-events-none absolute -right-4 -bottom-8 hidden h-40 w-40 -rotate-12 text-white/10 sm:block"
                    aria-hidden="true"
                />

                <div
                    class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6"
                >
                    <div
                        class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 shadow-inner backdrop-blur-sm sm:h-[4.5rem] sm:w-[4.5rem]"
                    >
                        <span
                            class="absolute inset-0 animate-ping rounded-2xl bg-white/20 [animation-duration:2.4s]"
                            aria-hidden="true"
                        />
                        <Megaphone class="relative h-6 w-6 sm:h-9 sm:w-9" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="text-[11px] font-bold tracking-[0.18em] text-white/80 uppercase sm:text-xs"
                            >
                                Announcement
                            </span>
                            <span
                                class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold tracking-wide text-[#B64A2E] uppercase shadow-sm"
                            >
                                New
                            </span>
                            <span
                                v-if="item.sectionName"
                                class="shrink-0 rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm sm:text-xs"
                            >
                                {{ item.sectionName }}
                            </span>
                            <span
                                v-if="item.createdAt"
                                class="text-[11px] font-medium text-white/70 sm:text-xs"
                            >
                                {{ item.createdAt }}
                            </span>
                        </div>
                        <h2
                            class="mt-1.5 text-xl leading-tight font-extrabold tracking-tight break-words sm:text-2xl lg:text-3xl"
                        >
                            {{ item.title }}
                        </h2>
                        <p
                            v-if="item.description"
                            class="mt-1.5 max-w-3xl text-[13px] leading-relaxed break-words whitespace-pre-line text-white/90 sm:text-[15px]"
                        >
                            {{ item.description }}
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-2 border-t border-white/20 pt-3 sm:flex-col sm:items-stretch sm:border-0 sm:pt-0"
                    >
                        <Link
                            v-if="item.link"
                            :href="item.link"
                            class="dash-btn inline-flex flex-1 items-center justify-center gap-2 bg-white px-5 text-[15px] font-semibold text-[#B64A2E] shadow-lg transition-colors hover:bg-white/90 sm:flex-none"
                        >
                            View details
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center self-end rounded-full bg-white/15 text-white backdrop-blur-sm transition-colors hover:bg-white/25"
                            title="Dismiss announcement"
                            aria-label="Dismiss announcement"
                            @click="emit('close-announcement', item.id)"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </section>
        </TransitionGroup>

        <div class="relative flex flex-col justify-center">
            <div
                class="relative flex flex-col justify-between gap-2.5 lg:flex-row lg:items-end lg:gap-8"
            >
                <div
                    class="flex w-full flex-col gap-2.5 sm:gap-3 lg:w-auto lg:flex-row lg:items-center lg:gap-5"
                >
                    <!-- App-style header row: avatar left, greeting right of it, refresh trailing -->
                    <div
                        class="flex w-full flex-row items-center gap-3 sm:gap-5"
                    >
                        <div class="group/avatar relative shrink-0">
                            <div class="relative">
                                <div
                                    class="absolute -top-1 -right-1 z-30 rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-tight text-white tabular-nums shadow-sm"
                                    :class="greetingTheme || 'bg-[#D97757]'"
                                >
                                    Lvl {{ animatedLevel }}
                                </div>

                                <div
                                    class="absolute -right-0.5 -bottom-0.5 z-20 flex h-4 w-4 items-center justify-center rounded-full border-2 border-background bg-background sm:h-5 sm:w-5"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full sm:h-2.5 sm:w-2.5"
                                        :class="statusColor || 'bg-[#4D9375]'"
                                    ></span>
                                </div>

                                <component
                                    :is="profileHref ? Link : 'div'"
                                    v-bind="
                                        profileHref
                                            ? {
                                                  href: profileHref,
                                                  'aria-label': `View ${userName}'s profile`,
                                                  title: 'View your profile',
                                              }
                                            : {}
                                    "
                                    class="block rounded-full transition-transform duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    :class="
                                        profileHref
                                            ? 'cursor-pointer hover:scale-[1.03] active:scale-[0.98]'
                                            : ''
                                    "
                                >
                                    <Avatar
                                        class="relative size-12 overflow-hidden rounded-full border border-border/50 bg-card sm:size-16 lg:size-20"
                                    >
                                        <AvatarImage
                                            v-if="userAvatar"
                                            :src="userAvatar"
                                            :alt="userName"
                                            class="aspect-square object-cover"
                                        />
                                        <AvatarFallback
                                            class="flex items-center justify-center bg-muted text-sm font-semibold text-foreground sm:text-lg lg:text-xl"
                                        >
                                            {{ getInitials(userName) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </component>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="mb-1 hidden items-center gap-1 lg:flex">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted active:scale-95"
                                    aria-label="Refresh dashboard"
                                    title="Refresh"
                                    @click="emit('refresh')"
                                >
                                    <RefreshCw
                                        class="h-4 w-4"
                                        :class="{
                                            'animate-spin': isRefreshing,
                                        }"
                                    />
                                </button>
                            </div>

                            <div class="space-y-0.5 sm:space-y-1">
                                <h1
                                    class="dash-title truncate text-[20px] leading-[1.15] text-foreground sm:text-[32px] lg:text-[40px]"
                                >
                                    {{ timeBasedGreeting }}, {{ userName }}
                                </h1>
                                <p
                                    class="line-clamp-2 text-[13px] leading-snug text-muted-foreground sm:text-[17px]"
                                >
                                    {{ smarterStatus }}
                                </p>
                            </div>
                        </div>

                        <!-- Join Section: button on the right side of the greeting card -->
                        <button
                            type="button"
                            class="dash-btn inline-flex shrink-0 items-center gap-2 rounded-full border border-border/60 bg-card px-3 py-2 text-[13px] font-semibold text-foreground shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-colors hover:bg-muted active:scale-95 sm:rounded-xl sm:px-4 sm:text-[15px]"
                            @click="emit('open-section-modal')"
                        >
                            <Plus class="h-4 w-4 shrink-0" />
                            <span class="hidden sm:inline">Join section</span>
                            <span class="sm:hidden">Join</span>
                        </button>

                        <!-- Mobile-only trailing refresh action (app header pattern) -->
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border/60 bg-card text-muted-foreground shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-colors hover:bg-muted active:scale-95 lg:hidden"
                            aria-label="Refresh dashboard"
                            title="Refresh"
                            @click="emit('refresh')"
                        >
                            <RefreshCw
                                class="h-4 w-4"
                                :class="{ 'animate-spin': isRefreshing }"
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
