<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Button from '@/components/ui/button/Button.vue';
import NextUpCard, { type NextUpItem } from './NextUpCard.vue';
import EmptyState from './EmptyState.vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Clock, RefreshCw, Trophy, Sparkles, CalendarX } from 'lucide-vue-next';
import { index as examsIndex, show as examsShow } from '@/routes/exams';

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

interface Props {
    unreadNotificationCount: number;
    weeklyXP?: number;
    weeklyGoal?: number;
    upcomingExams?: Exam[];
    nextUpItem?: NextUpItem | null;
}

const props = withDefaults(defineProps<Props>(), {
    weeklyXP: 0,
    weeklyGoal: 0,
    upcomingExams: () => [],
    nextUpItem: null,
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
        <Card class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-primary/10 rounded-full blur-2xl pointer-events-none" aria-hidden="true" />
            <CardHeader class="pb-3">
                <CardTitle class="text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="flex flex-col items-center gap-1 justify-center h-12 sm:h-14 border-primary/10 hover:border-primary/30 group/btn"
                    @click="emit('quick-action', 'resume')"
                >
                    <RefreshCw class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary group-hover/btn:rotate-180 transition-transform duration-500" />
                    <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest">Resume</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="flex flex-col items-center gap-1 justify-center h-12 sm:h-14 border-primary/10 hover:border-primary/30 group/btn"
                    @click="emit('quick-action', 'assignments')"
                >
                    <BookOpen class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary group-hover/btn:scale-110 transition-transform" />
                    <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest">Tasks</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="flex flex-col items-center gap-1 justify-center h-12 sm:h-14 border-primary/10 hover:border-primary/30 group/btn"
                    @click="emit('quick-action', 'leaderboard')"
                >
                    <Trophy class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary group-hover/btn:-translate-y-0.5 transition-transform" />
                    <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest">Ranks</span>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="flex flex-col items-center gap-1 justify-center h-12 sm:h-14 border-primary/10 hover:border-primary/30 group/btn"
                    @click="emit('quick-action', 'settings')"
                >
                    <Sparkles class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary group-hover/btn:rotate-12 transition-transform" />
                    <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest">Profile</span>
                </Button>
            </CardContent>
        </Card>

        <!-- Weekly Goal -->
        <Card
            v-if="weeklyGoal"
            class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative"
        >
            <div class="absolute -right-10 -top-10 w-24 h-24 bg-primary/10 rounded-full blur-2xl pointer-events-none" aria-hidden="true" />
            <CardHeader class="pb-2">
                <CardTitle class="text-xs sm:text-sm font-bold">Weekly Goal</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div class="flex justify-between items-end">
                        <div class="text-xl sm:text-2xl font-bold tabular-nums">
                            {{ weeklyXP }}
                            <span class="text-[10px] text-muted-foreground font-normal">/ {{ weeklyGoal }} XP</span>
                        </div>
                        <div class="text-[10px] sm:text-xs font-bold text-primary tabular-nums">
                            {{ weeklyPercent(weeklyXP || 0, weeklyGoal) }}%
                        </div>
                    </div>
                    <div class="h-2 bg-muted rounded-full overflow-hidden">
                        <div
                            class="h-full bg-primary transition-all duration-1000"
                            :style="{ width: `${weeklyPercent(weeklyXP || 0, weeklyGoal)}%` }"
                        />
                    </div>
                    <p class="text-[10px] text-muted-foreground">
                        Keep it up! You're almost at your weekly target.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Upcoming Exams -->
        <Card class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative">
            <div class="absolute -right-12 -top-12 w-28 h-28 bg-primary/5 rounded-full blur-2xl pointer-events-none" aria-hidden="true" />
            <CardHeader class="pb-3 flex flex-row items-center justify-between">
                <CardTitle class="text-sm font-bold flex items-center gap-2">
                    <BookOpen class="h-4 w-4 text-primary" />
                    Upcoming Activities
                </CardTitle>
                <Link :href="examsIndex().url" class="text-[10px] font-semibold text-primary hover:text-primary/80 transition-colors">
                    All →
                </Link>
            </CardHeader>
            <CardContent>
                <div v-if="upcomingExams && upcomingExams.length > 0" class="space-y-2">
                    <Link
                        v-for="exam in upcomingExams.slice(0, 2)"
                        :key="exam.id"
                        :href="examsShow(exam.id).url"
                        class="p-3 rounded-lg border border-border/30 hover:border-primary/40 bg-muted/20 hover:bg-muted/40 transition-all duration-300 group cursor-pointer block"
                        as="div"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-semibold text-foreground group-hover:text-primary transition-colors truncate">
                                    {{ exam.title }}
                                </h4>
                                <div class="flex items-center gap-2 mt-1 text-[10px] text-muted-foreground tabular-nums">
                                    <Clock class="h-2.5 w-2.5" />
                                    {{ exam.duration_minutes }}m
                                </div>
                            </div>
                            <div v-if="!exam.is_completed" class="text-right flex-shrink-0">
                                <div class="text-xs font-bold text-primary tabular-nums">
                                    {{ exam.submitted_parts }}/{{ exam.parts_count }}
                                </div>
                            </div>
                            <div v-else class="flex-shrink-0">
                                <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-semibold uppercase">
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
        </Card>
    </div>
</template>
