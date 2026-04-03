<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Button from '@/components/ui/button/Button.vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Clock } from 'lucide-vue-next';
import { index as examsIndex, show as examsShow } from '@/routes/exams';

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
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
}

const props = defineProps<Props>();
const emit = defineEmits(['quick-action']);
</script>

<template>
    <div class="space-y-4">
        <Card class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-primary/5 to-transparent backdrop-blur-xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-bold">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-2">
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'resume')">
                    <span class="text-xs">Resume</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'assignments')">
                    <span class="text-xs">Tasks</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'leaderboard')">
                    <span class="text-xs">Ranks</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'settings')">
                    <span class="text-xs">Profile</span>
                </Button>
            </CardContent>
        </Card>

        <Card v-if="weeklyGoal" class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative backdrop-blur-xl">
            <div class="absolute -right-10 -top-10 w-24 h-24 bg-primary/10 rounded-full blur-2xl"></div>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-bold">Weekly Goal</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div class="flex justify-between items-end">
                        <div class="text-2xl font-bold">{{ weeklyXP }} <span class="text-xs text-muted-foreground font-normal">/ {{ weeklyGoal }} XP</span></div>
                        <div class="text-xs font-bold text-primary">{{ Math.round((weeklyXP || 0) / weeklyGoal * 100) }}%</div>
                    </div>
                    <div class="h-2 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-1000" :style="{ width: `${Math.min(100, (weeklyXP || 0) / weeklyGoal * 100)}%` }"></div>
                    </div>
                    <p class="text-[10px] text-muted-foreground">Keep it up! You're almost at your weekly target.</p>
                </div>
            </CardContent>
        </Card>

        <!-- Upcoming Exams Card -->
        <Card v-if="upcomingExams && upcomingExams.length > 0" class="surface-card premium-hover border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative backdrop-blur-xl">
            <div class="absolute -right-12 -top-12 w-28 h-28 bg-primary/5 rounded-full blur-2xl"></div>
            <CardHeader class="pb-3 flex flex-row items-center justify-between">
                <CardTitle class="text-sm font-bold flex items-center gap-2">
                    <BookOpen class="w-4 h-4 text-primary" />
                    Upcoming Exams
                </CardTitle>
                <Link :href="examsIndex().url" class="text-[10px] font-semibold text-primary hover:text-primary/80 transition-colors">
                    All →
                </Link>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <Link v-for="exam in upcomingExams.slice(0, 2)" :key="exam.id"
                        :href="examsShow(exam.id).url"
                        class="p-3 rounded-lg border border-border/30 hover:border-primary/40 bg-muted/20 hover:bg-muted/40 transition-all duration-300 group cursor-pointer block"
                        as="div">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-semibold text-foreground group-hover:text-primary transition-colors truncate">{{ exam.title }}</h4>
                                <div class="flex items-center gap-2 mt-1 text-[10px] text-muted-foreground">
                                    <Clock class="w-2.5 h-2.5" />
                                    {{ exam.duration_minutes }}m
                                </div>
                            </div>
                            <div v-if="!exam.is_completed" class="text-right flex-shrink-0">
                                <div class="text-xs font-bold text-primary">{{ exam.submitted_parts }}/{{ exam.parts_count }}</div>
                            </div>
                            <div v-else class="flex-shrink-0">
                                <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-primary/10 border border-primary/20 text-primary font-semibold uppercase">
                                    Done
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
