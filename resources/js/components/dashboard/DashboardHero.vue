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
    <div class="space-y-6 lg:space-y-8">
        <!-- Integrated Announcement -->
        <TransitionGroup
            enter-active-class="transition duration-500 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-for="item in announcements.slice(0, 1)"
                :key="item.id"
                class="group relative mb-4 overflow-hidden rounded-2xl border border-border/40 border-primary/10 bg-card/30 p-3 shadow-2xl shadow-primary/5 backdrop-blur-xl transition-all duration-500 hover:border-primary/30 sm:rounded-3xl sm:p-5"
            >
                <div
                    class="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent"
                ></div>
                <div
                    class="relative flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center sm:gap-4"
                >
                    <div
                        class="flex w-full flex-1 items-center gap-3 sm:w-auto sm:gap-4"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary shadow-inner transition-all duration-500 group-hover:bg-primary group-hover:text-primary-foreground sm:h-12 sm:w-12 sm:rounded-2xl"
                        >
                            <Megaphone class="h-5 w-5 sm:h-6 sm:w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4
                                    class="truncate text-xs font-black tracking-tight tracking-widest text-foreground uppercase sm:text-sm"
                                >
                                    {{ item.title }}
                                </h4>
                                <span
                                    class="shrink-0 animate-pulse rounded-md bg-primary/10 px-1.5 py-0.5 text-[7px] font-black tracking-widest text-primary uppercase sm:text-[8px]"
                                    >New</span
                                >
                            </div>
                            <p
                                v-if="item.description"
                                class="mt-0.5 line-clamp-1 text-[10px] font-medium text-muted-foreground italic opacity-70 sm:text-xs"
                            >
                                "{{ item.description }}"
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex w-full items-center justify-between gap-2 border-t border-primary/5 pt-2 sm:w-auto sm:justify-end sm:border-0 sm:pt-0"
                    >
                        <Link
                            v-if="item.link"
                            :href="item.link"
                            class="group/link flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary/10 px-3 py-1.5 text-[9px] font-black tracking-widest text-primary uppercase transition-all duration-300 hover:bg-primary hover:text-primary-foreground sm:flex-none sm:rounded-xl sm:px-4 sm:py-2 sm:text-[10px]"
                        >
                            Explore
                            <ArrowRight
                                class="h-3 w-3 transition-transform group-hover/link:translate-x-1"
                            />
                        </Link>
                        <button
                            @click="emit('close-announcement', item.id)"
                            class="group/close shrink-0 rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive sm:rounded-xl sm:p-2"
                            title="Dismiss"
                        >
                            <X
                                class="h-3.5 h-4 w-3.5 transition-transform group-hover/close:rotate-90 sm:w-4"
                            />
                        </button>
                    </div>
                </div>
            </div>
        </TransitionGroup>

        <!-- Bespoke Hero Section - Open Layout -->
        <div class="relative flex flex-col justify-center sm:px-2 lg:px-4">
            <div
                class="relative flex flex-col justify-between gap-3 lg:flex-row lg:items-end lg:gap-10"
            >
                <!-- Left side: Greetings + Integrated Progress | Right: Profile Picture (mobile) -->
                <div
                    class="flex w-full flex-row items-center gap-2 sm:gap-6 lg:w-auto lg:items-center lg:gap-10"
                >
                    <!-- Profile Picture with Integrated Level Badge -->
                    <div
                        class="group/avatar relative order-2 shrink-0 lg:order-1"
                    >
                        <div class="relative">
                            <!-- Level Badge integrated into Avatar -->
                            <div
                                class="absolute -top-1 -right-1 z-30 rounded-md border border-background bg-gradient-to-br px-1.5 py-0.5 text-[8px] font-black tracking-tighter text-primary-foreground uppercase tabular-nums shadow-lg sm:-top-1 sm:-right-1 sm:px-1.5 sm:py-0.5 sm:text-[8px] lg:-top-1 lg:-right-1 lg:px-1.5 lg:py-0.5 lg:text-[9px]"
                                :class="
                                    greetingTheme || 'from-primary to-primary'
                                "
                            >
                                Lvl {{ animatedLevel }}
                            </div>

                            <div
                                class="absolute -right-1.5 -bottom-1.5 z-20 flex h-4 w-4 items-center justify-center rounded-full border-2 border-background bg-background shadow-lg sm:h-4 sm:w-4 lg:h-5 lg:w-5"
                            >
                                <span
                                    class="h-2 w-2 rounded-full sm:h-2 sm:w-2 lg:h-2.5 lg:w-2.5"
                                    :class="
                                        statusColor ||
                                        'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]'
                                    "
                                ></span>
                            </div>

                            <Avatar
                                class="relative size-14 overflow-hidden rounded-xl border border-primary/20 bg-card/40 shadow-lg backdrop-blur-md transition-all duration-700 group-hover/avatar:scale-105 group-hover/avatar:rotate-2 sm:size-16 sm:rounded-xl lg:size-24 lg:rounded-2xl"
                            >
                                <AvatarImage
                                    v-if="userAvatar"
                                    :src="userAvatar"
                                    :alt="userName"
                                    class="aspect-square object-cover"
                                />
                                <AvatarFallback
                                    class="flex items-center justify-center bg-primary/5 text-sm font-black text-primary sm:text-xl lg:text-2xl"
                                >
                                    {{ getInitials(userName) }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                    </div>

                    <div class="order-1 min-w-0 flex-1 lg:order-2">
                        <div
                            class="flex flex-wrap items-center gap-1.5 text-[7px] font-black tracking-[0.1em] text-muted-foreground/40 uppercase sm:text-[10px] sm:tracking-[0.2em] lg:text-xs lg:tracking-[0.3em]"
                        >
                            <button
                                class="group/sync flex cursor-pointer items-center rounded-full border border-primary/10 bg-primary/5 p-1.5 text-primary/60 transition-colors hover:bg-primary/10 active:scale-95"
                                @click="emit('refresh')"
                                aria-label="Refresh dashboard"
                                title="Refresh"
                            >
                                <RefreshCw
                                    class="h-2.5 w-2.5 sm:h-3 sm:w-3"
                                    :class="{ 'animate-spin': isRefreshing }"
                                />
                            </button>
                        </div>

                        <div class="space-y-0.5 sm:space-y-1">
                            <h1
                                class="truncate text-base leading-tight font-bold tracking-tight text-foreground sm:text-2xl lg:text-4xl"
                            >
                                {{ timeBasedGreeting }}, {{ userName }}
                            </h1>
                            <p
                                class="line-clamp-1 text-[8px] leading-relaxed font-medium text-muted-foreground/60 sm:text-sm lg:mt-1 lg:text-lg"
                                v-html="smarterStatus"
                            ></p>
                        </div>

                        <!-- Mobile: Add Section CTA (compact pill) -->
                        <div class="mt-1.5 lg:hidden">
                            <button
                                @click="emit('open-section-modal')"
                                class="group inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-2.5 py-1 text-[8px] font-black tracking-widest text-primary uppercase backdrop-blur-sm transition-all duration-300 hover:border-primary/40 hover:bg-primary/10 hover:shadow-sm active:scale-95"
                            >
                                <Plus
                                    class="h-3 w-3 shrink-0 transition-transform duration-300 group-hover:rotate-90"
                                />
                                Join Section
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop: Add Section CTA -->
                <div class="mb-1 hidden shrink-0 self-end lg:block">
                    <button
                        @click="emit('open-section-modal')"
                        class="group relative flex items-center gap-2 overflow-hidden rounded-xl border border-primary/20 bg-primary/5 px-4 py-2 text-[10px] font-black tracking-widest text-primary uppercase backdrop-blur-sm transition-all duration-300 hover:border-primary/40 hover:bg-primary/10 hover:shadow-sm active:scale-95"
                    >
                        <!-- Shine effect -->
                        <div
                            class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent transition-transform duration-700 group-hover:translate-x-full"
                        ></div>

                        <Plus
                            class="h-3.5 w-3.5 shrink-0 transition-transform duration-300 group-hover:rotate-90"
                        />
                        Join Section
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slow-drift {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(5%, 5%) scale(1.1);
    }
}

@keyframes slow-drift-reverse {
    0%,
    100% {
        transform: translate(0, 0) scale(1.1);
    }
    50% {
        transform: translate(-5%, -5%) scale(1);
    }
}

@keyframes shimmer {
    0% {
        transform: translateX(-200%) skew-x(-45deg);
    }
    100% {
        transform: translateX(200%) skew-x(-45deg);
    }
}

.animate-slow-drift {
    animation: slow-drift 20s infinite ease-in-out;
}

.animate-slow-drift-reverse {
    animation: slow-drift-reverse 25s infinite ease-in-out;
}

.animate-shimmer {
    animation: shimmer 3s infinite ease-in-out;
}
</style>
