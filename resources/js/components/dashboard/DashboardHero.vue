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
            enter-active-class="transition duration-400 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in announcements.slice(0, 1)"
                :key="item.id"
                class="relative overflow-hidden rounded-xl border border-border/50 bg-card p-3 sm:rounded-[1.25rem] sm:p-5"
            >
                <div
                    class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <div
                        class="flex w-full flex-1 items-center gap-3 sm:w-auto"
                    >
                        <div
                            class="dash-icon-well bg-[#D97757]/10 text-[#D97757]"
                        >
                            <Megaphone class="h-4 w-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4
                                    class="truncate text-[15px] font-semibold tracking-tight text-foreground"
                                >
                                    {{ item.title }}
                                </h4>
                                <span
                                    class="shrink-0 rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[12px] font-medium text-[#D97757]"
                                >
                                    New
                                </span>
                            </div>
                            <p
                                v-if="item.description"
                                class="mt-0.5 line-clamp-2 text-[13px] leading-snug text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex w-full items-center justify-between gap-2 border-t border-border/40 pt-3 sm:w-auto sm:justify-end sm:border-0 sm:pt-0"
                    >
                        <Link
                            v-if="item.link"
                            :href="item.link"
                            class="dash-btn inline-flex flex-1 items-center justify-center gap-1.5 bg-[#D97757] px-4 text-[15px] text-white sm:flex-none"
                        >
                            Explore
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted"
                            title="Dismiss"
                            @click="emit('close-announcement', item.id)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </TransitionGroup>

        <div class="relative flex flex-col justify-center">
            <div
                class="relative flex flex-col justify-between gap-2.5 lg:flex-row lg:items-end lg:gap-8"
            >
                <div
                    class="flex w-full flex-row items-center gap-2.5 sm:gap-5 lg:w-auto"
                >
                    <div
                        class="group/avatar relative order-2 shrink-0 lg:order-1"
                    >
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

                            <Avatar
                                class="relative size-11 overflow-hidden rounded-full border border-border/50 bg-card sm:size-16 lg:size-20"
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
                        </div>
                    </div>

                    <div class="order-1 min-w-0 flex-1 lg:order-2">
                        <div class="mb-0.5 flex items-center gap-1 sm:mb-1">
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted active:scale-95 sm:h-9 sm:w-9"
                                aria-label="Refresh dashboard"
                                title="Refresh"
                                @click="emit('refresh')"
                            >
                                <RefreshCw
                                    class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                    :class="{ 'animate-spin': isRefreshing }"
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

                        <div class="mt-2 lg:hidden">
                            <button
                                type="button"
                                class="dash-btn inline-flex h-9 items-center gap-1.5 border border-border/60 bg-card px-3 text-[13px] text-foreground active:scale-[0.98] sm:h-11 sm:px-4 sm:text-[15px]"
                                @click="emit('open-section-modal')"
                            >
                                <Plus
                                    class="h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4"
                                />
                                Join section
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-1 hidden shrink-0 self-end lg:block">
                    <button
                        type="button"
                        class="dash-btn inline-flex items-center gap-2 border border-border/60 bg-card px-5 text-[15px] text-foreground transition-colors hover:bg-muted"
                        @click="emit('open-section-modal')"
                    >
                        <Plus class="h-4 w-4 shrink-0" />
                        Join section
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
