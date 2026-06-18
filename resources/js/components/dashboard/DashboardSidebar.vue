<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock,
    RefreshCw,
    Trophy,
    Sparkles,
    CalendarX,
    Shield,
} from 'lucide-vue-next';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { index as examsIndex, show as examsShow } from '@/routes/exams';
import EmptyState from './EmptyState.vue';
import NextUpCard from './NextUpCard.vue';
import type {NextUpItem} from './NextUpCard.vue';

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    exam_date_iso?: string | null;
    duration_minutes: number;
    status: string;
    parts_count: number;
    submitted_parts: number;
    is_completed: boolean;
}

interface Badge {
    id: number;
    name: string;
    description?: string | null;
    requiredLevel?: number | null;
    image?: string | null;
    iconUrl?: string | null;
    earnedSeason?: string | null;
    earnedAt?: string | null;
}

interface Props {
    unreadNotificationCount: number;
    badges?: Badge[];
    weeklyXP?: number;
    weeklyGoal?: number;
    upcomingExams?: Exam[];
    nextUpItem?: NextUpItem | null;
    profileUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
    badges: () => [],
    weeklyXP: 0,
    weeklyGoal: 0,
    upcomingExams: () => [],
    nextUpItem: null,
    profileUrl: '/dashboard',
});
const emit = defineEmits(['quick-action']);

const weeklyPercent = (xp: number, goal: number) => {
    if (!goal) return 0;
    return Math.min(100, Math.round((xp / goal) * 100));
};
</script>

<template>
    <div class="space-y-4">
        <!-- Promoted: Next Up -->
        <NextUpCard v-if="nextUpItem" :item="nextUpItem" />

        <!-- Compact Quick Actions -->
        <SpotlightCard
            customSize
            glowColor="blue"
            className="surface-card premium-hover relative p-0 w-full min-w-0"
        >
            <div class="relative flex h-full w-full flex-col gap-6 py-6">
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                >
                    <div
                        class="pointer-events-none absolute -top-6 -right-6 h-20 w-20 rounded-full bg-primary/10 blur-2xl"
                        aria-hidden="true"
                    />
                </div>
                <CardHeader class="relative z-10 pb-3">
                    <CardTitle
                        class="text-[9px] font-black tracking-[0.2em] text-muted-foreground/70 uppercase sm:text-[10px]"
                        >Quick Actions</CardTitle
                    >
                </CardHeader>
                <CardContent
                    class="relative z-10 grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="group/btn flex h-12 flex-col items-center justify-center gap-1 border-primary/10 hover:border-primary/30 sm:h-14"
                        @click="emit('quick-action', 'resume')"
                    >
                        <RefreshCw
                            class="h-3.5 w-3.5 text-primary transition-transform duration-500 group-hover/btn:rotate-180 sm:h-4 sm:w-4"
                        />
                        <span
                            class="text-[8px] font-black tracking-widest uppercase sm:text-[9px]"
                            >Resume</span
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="group/btn flex h-12 flex-col items-center justify-center gap-1 border-primary/10 hover:border-primary/30 sm:h-14"
                        @click="emit('quick-action', 'assignments')"
                    >
                        <BookOpen
                            class="h-3.5 w-3.5 text-primary transition-transform group-hover/btn:scale-110 sm:h-4 sm:w-4"
                        />
                        <span
                            class="text-[8px] font-black tracking-widest uppercase sm:text-[9px]"
                            >Tasks</span
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="group/btn flex h-12 flex-col items-center justify-center gap-1 border-primary/10 hover:border-primary/30 sm:h-14"
                        @click="emit('quick-action', 'leaderboard')"
                    >
                        <Trophy
                            class="h-3.5 w-3.5 text-primary transition-transform group-hover/btn:-translate-y-0.5 sm:h-4 sm:w-4"
                        />
                        <span
                            class="text-[8px] font-black tracking-widest uppercase sm:text-[9px]"
                            >Ranks</span
                        >
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="group/btn flex h-12 flex-col items-center justify-center gap-1 border-primary/10 hover:border-primary/30 sm:h-14"
                        @click="emit('quick-action', 'settings')"
                    >
                        <Sparkles
                            class="h-3.5 w-3.5 text-primary transition-transform group-hover/btn:rotate-12 sm:h-4 sm:w-4"
                        />
                        <span
                            class="text-[8px] font-black tracking-widest uppercase sm:text-[9px]"
                            >Profile</span
                        >
                    </Button>
                </CardContent>
            </div>
        </SpotlightCard>

        <!-- Badges -->
        <SpotlightCard
            customSize
            glowColor="purple"
            className="surface-card premium-hover relative p-0 w-full min-w-0"
        >
            <div class="relative flex h-full w-full flex-col gap-6 py-6">
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                >
                    <div
                        class="pointer-events-none absolute -top-10 -right-10 h-24 w-24 rounded-full bg-primary/10 blur-2xl"
                        aria-hidden="true"
                    />
                </div>
                <CardHeader
                    class="relative z-10 flex flex-row items-center justify-between pb-3"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <Shield class="h-4 w-4 text-primary" />
                        My Badges
                    </CardTitle>
                    <Link
                        :href="profileUrl"
                        class="text-[10px] font-semibold text-primary transition-colors hover:text-primary/80"
                    >
                        View all →
                    </Link>
                </CardHeader>
                <CardContent class="relative z-10 pt-0">
                    <div v-if="badges.length > 0" class="space-y-2">
                        <div
                            v-for="badge in badges.slice(0, 4)"
                            :key="badge.id"
                            class="flex items-center gap-3 rounded-xl border border-border/30 bg-muted/20 p-2.5 transition-all duration-300 hover:border-primary/40 hover:bg-muted/40"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary/10"
                            >
                                <img
                                    v-if="badge.image"
                                    :src="badge.image"
                                    :alt="badge.name"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="text-[9px] font-black tracking-widest text-primary uppercase"
                                    >Lvl {{ badge.requiredLevel ?? '--' }}</span
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-bold text-foreground"
                                >
                                    {{ badge.name }}
                                </p>
                                <p
                                    class="mt-0.5 truncate text-[10px] text-muted-foreground"
                                >
                                    {{ badge.earnedSeason ?? 'Unlocked badge' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <EmptyState
                        v-else
                        compact
                        :icon="Shield"
                        title="No badges yet"
                        message="Reach higher levels to unlock lifetime badges and show where you earned them."
                    />
                </CardContent>
            </div>
        </SpotlightCard>

        <!-- Weekly Goal -->
        <SpotlightCard
            v-if="weeklyGoal"
            customSize
            glowColor="green"
            className="surface-card premium-hover relative p-0 w-full min-w-0"
        >
            <div class="relative flex h-full w-full flex-col gap-6 py-6">
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                >
                    <div
                        class="pointer-events-none absolute -top-10 -right-10 h-24 w-24 rounded-full bg-primary/10 blur-2xl"
                        aria-hidden="true"
                    />
                </div>
                <CardHeader class="relative z-10 pb-2">
                    <CardTitle class="text-xs font-bold sm:text-sm"
                        >Weekly Goal</CardTitle
                    >
                </CardHeader>
                <CardContent class="relative z-10">
                    <div class="space-y-3">
                        <div class="flex items-end justify-between">
                            <div
                                class="text-xl font-bold tabular-nums sm:text-2xl"
                            >
                                {{ weeklyXP }}
                                <span
                                    class="text-[10px] font-normal text-muted-foreground"
                                    >/ {{ weeklyGoal }} XP</span
                                >
                            </div>
                            <div
                                class="text-[10px] font-bold text-primary tabular-nums sm:text-xs"
                            >
                                {{ weeklyPercent(weeklyXP || 0, weeklyGoal) }}%
                            </div>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full bg-primary transition-all duration-1000"
                                :style="{
                                    width: `${weeklyPercent(weeklyXP || 0, weeklyGoal)}%`,
                                }"
                            />
                        </div>
                        <p class="text-[10px] text-muted-foreground">
                            Keep it up! You're almost at your weekly target.
                        </p>
                    </div>
                </CardContent>
            </div>
        </SpotlightCard>

        <!-- Upcoming Exams -->
        <SpotlightCard
            customSize
            glowColor="orange"
            className="surface-card premium-hover relative p-0 w-full min-w-0"
        >
            <div class="relative flex h-full w-full flex-col gap-6 py-6">
                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                >
                    <div
                        class="pointer-events-none absolute -top-12 -right-12 h-28 w-28 rounded-full bg-primary/5 blur-2xl"
                        aria-hidden="true"
                    />
                </div>
                <CardHeader
                    class="relative z-10 flex flex-row items-center justify-between pb-3"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <BookOpen class="h-4 w-4 text-primary" />
                        Upcoming Activities
                    </CardTitle>
                    <Link
                        :href="examsIndex().url"
                        class="text-[10px] font-semibold text-primary transition-colors hover:text-primary/80"
                    >
                        All →
                    </Link>
                </CardHeader>
                <CardContent class="relative z-10">
                    <div
                        v-if="upcomingExams && upcomingExams.length > 0"
                        class="space-y-2"
                    >
                        <Link
                            v-for="exam in upcomingExams.slice(0, 2)"
                            :key="exam.id"
                            :href="examsShow(exam.id).url"
                            class="group block cursor-pointer rounded-lg border border-border/30 bg-muted/20 p-3 transition-all duration-300 hover:border-primary/40 hover:bg-muted/40"
                            as="div"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <h4
                                        class="truncate text-xs font-semibold text-foreground transition-colors group-hover:text-primary"
                                    >
                                        {{ exam.title }}
                                    </h4>
                                    <div
                                        class="mt-1 flex items-center gap-2 text-[10px] text-muted-foreground tabular-nums"
                                    >
                                        <Clock class="h-2.5 w-2.5" />
                                        {{ exam.duration_minutes }}m
                                    </div>
                                </div>
                                <div
                                    v-if="!exam.is_completed"
                                    class="flex-shrink-0 text-right"
                                >
                                    <div
                                        class="text-xs font-bold text-primary tabular-nums"
                                    >
                                        {{ exam.submitted_parts }}/{{
                                            exam.parts_count
                                        }}
                                    </div>
                                </div>
                                <div v-else class="flex-shrink-0">
                                    <span
                                        class="rounded-full border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-[8px] font-semibold text-primary uppercase"
                                    >
                                        Done
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <EmptyState
                        v-else
                        compact
                        :icon="CalendarX"
                        title="All caught up"
                        message="No scheduled activities right now. New exams will appear here the moment they're published."
                    />
                </CardContent>
            </div>
        </SpotlightCard>
    </div>
</template>
