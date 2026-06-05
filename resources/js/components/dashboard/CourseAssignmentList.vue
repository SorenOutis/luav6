<script setup lang="ts">
import Progress from '@/components/ui/progress/Progress.vue';
import { Clock } from 'lucide-vue-next';
import { SpotlightCard } from '@/components/ui/spotlight-card';

interface Course {
    id: number;
    name: string;
    progress: number;
    completedLessons: number;
    totalLessons: number;
    xpEarned: number;
    nextDeadline: string;
}

interface Assignment {
    id: number;
    title: string;
    description: string;
    dueDate: string;
    isOverdue: boolean;
    submitted: boolean;
    status: string;
    grade: string | null;
}

interface Props {
    courses: Course[];
    assignments: Assignment[];
}

const props = defineProps<Props>();
const emit = defineEmits(['course-click', 'assignment-click']);

const handleCourseClick = (course: Course) => {
    emit('course-click', course);
};

const handleAssignmentClick = (assignment: Assignment) => {
    emit('assignment-click', assignment);
};

// SpotlightCard handles internal mouse tracking automatically
</script>

<template>
    <SpotlightCard
        customSize
        glowColor="blue"
        className="surface-card p-0 w-full min-w-0"
    >
        <div class="relative flex h-full w-full flex-col p-5 sm:p-8">
            <div
                class="relative z-10 mb-6 flex items-center justify-between border-b border-border/10 pb-4 sm:mb-8"
            >
                <div>
                    <h3
                        class="flex items-center gap-2 text-lg font-black tracking-tighter sm:text-xl"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="text-primary sm:h-5 sm:w-5"
                        >
                            <path d="M12 20h9" />
                            <path
                                d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"
                            />
                        </svg>
                        Mission Control
                    </h3>
                    <p
                        class="mt-1 text-[9px] font-bold tracking-widest text-muted-foreground/60 uppercase sm:text-[10px]"
                    >
                        Your learning trajectory and active assignments
                    </p>
                </div>
                <div class="hidden -space-x-2 sm:flex">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border-2 border-background bg-muted"
                    >
                        <div
                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/20 to-primary/40 text-[8px] font-bold"
                        >
                            {{ String.fromCharCode(64 + i) }}
                        </div>
                    </div>
                    <div
                        class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-background bg-primary/10 text-[8px] font-bold text-primary backdrop-blur-sm"
                    >
                        +12
                    </div>
                </div>
            </div>

            <div class="relative z-10 space-y-6 sm:space-y-8">
                <div
                    v-if="courses.length === 0 && assignments.length === 0"
                    class="animate-fade-in group/empty rounded-2xl border border-dashed border-primary/20 bg-primary/5 px-4 py-10 text-center backdrop-blur-sm sm:rounded-3xl sm:py-20"
                >
                    <div
                        class="relative mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-primary/20 bg-primary/10 sm:mb-6 sm:h-20 sm:w-20"
                    >
                        <div
                            class="absolute inset-0 animate-ping rounded-full bg-primary/20 opacity-20"
                        ></div>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="text-primary transition-transform duration-500 group-hover/empty:scale-110 sm:h-8 sm:w-8"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>
                    <h3
                        class="premium-gradient-text mb-2 text-xs font-black tracking-widest uppercase sm:text-base"
                    >
                        Objectives Secured
                    </h3>
                    <p
                        class="mx-auto max-w-[240px] text-[8px] leading-relaxed font-bold tracking-wider text-muted-foreground/80 uppercase sm:max-w-[280px] sm:text-[11px]"
                    >
                        All sectors clear. You've completed all current modules
                        and transmissions.
                    </p>
                    <button
                        class="mt-6 rounded-lg border border-primary/20 bg-primary/10 px-5 py-2 text-[8px] font-black tracking-widest text-primary uppercase transition-all duration-300 hover:bg-primary hover:text-primary-foreground sm:mt-8 sm:rounded-xl sm:px-6 sm:text-[10px]"
                    >
                        Browse Archives
                    </button>
                </div>

                <!-- Courses Section -->
                <div v-if="courses.length > 0" class="space-y-3 sm:space-y-4">
                    <div class="mb-2 flex items-center justify-between sm:mb-4">
                        <h4
                            class="border-l-2 border-primary pl-2 text-[9px] font-black tracking-[0.2em] text-foreground/80 uppercase sm:text-[10px]"
                        >
                            Active Modules
                        </h4>
                        <span
                            class="text-[8px] font-bold tracking-widest text-primary/60 uppercase sm:text-[9px]"
                            >{{ courses.length }} total</span
                        >
                    </div>
                    <SpotlightCard
                        v-for="(course, idx) in courses"
                        :key="course.id"
                        as="div"
                        customSize
                        glowColor="blue"
                        :className="
                            [
                                'group/course relative space-y-3 sm:space-y-4 p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-border/40 bg-card/20 hover:bg-white/[0.03] cursor-pointer transition-all duration-500 animate-fade-up premium-hover backdrop-blur-sm w-full min-w-0',
                            ].join(' ')
                        "
                        :class="`stagger-${idx + 1}`"
                        @click="handleCourseClick(course)"
                    >
                        <div class="relative flex h-full w-full flex-col">
                            <div
                                class="relative z-10 flex items-center justify-between gap-3"
                            >
                                <div
                                    class="flex min-w-0 items-center gap-3 sm:gap-4"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary transition-all duration-500 group-hover/course:bg-primary group-hover/course:text-primary-foreground sm:h-12 sm:w-12 sm:rounded-2xl"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="18"
                                            height="18"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="sm:h-5 sm:w-5"
                                        >
                                            <path
                                                d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"
                                            />
                                            <path
                                                d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <h4
                                            class="truncate text-sm font-black tracking-tight transition-colors group-hover/course:text-primary sm:text-base"
                                        >
                                            {{ course.name }}
                                        </h4>
                                        <div
                                            class="mt-0.5 flex items-center gap-1.5 sm:mt-1 sm:gap-2"
                                        >
                                            <p
                                                class="text-[8px] font-black tracking-widest text-muted-foreground/60 uppercase sm:text-[10px]"
                                            >
                                                {{ course.completedLessons }}/{{
                                                    course.totalLessons
                                                }}
                                                Units
                                            </p>
                                            <span
                                                class="h-1 w-1 rounded-full bg-border/40"
                                            ></span>
                                            <span
                                                class="text-[8px] font-black tracking-widest text-emerald-500/80 uppercase sm:text-[10px]"
                                                >Active</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div
                                        class="rounded-lg border border-primary/20 bg-primary/10 px-2 py-1 text-[8px] font-black text-primary shadow-[0_0_20px_rgba(var(--primary-rgb),0.15)] transition-transform group-hover/course:scale-105 sm:rounded-xl sm:px-3 sm:py-1.5 sm:text-[10px]"
                                    >
                                        +{{ course.xpEarned }}
                                    </div>
                                </div>
                            </div>

                            <div class="relative z-10 space-y-1.5 sm:space-y-2">
                                <div
                                    class="flex items-center justify-between text-[7px] font-black tracking-widest text-muted-foreground/50 uppercase sm:text-[9px]"
                                >
                                    <span class="text-foreground/70"
                                        >{{ course.progress }}% Synced</span
                                    >
                                    <span class="flex items-center gap-1"
                                        ><Clock
                                            class="h-2 w-2 sm:h-2.5 sm:w-2.5"
                                        />
                                        {{ course.nextDeadline }}</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 w-full overflow-hidden rounded-full border border-border/10 bg-muted/30 sm:h-2"
                                >
                                    <div
                                        class="relative h-full bg-primary transition-all duration-1000 ease-out"
                                        :style="{
                                            width: `${course.progress}%`,
                                        }"
                                    >
                                        <div
                                            class="absolute inset-0 h-full w-full -translate-x-full animate-[shimmer_3s_infinite] bg-gradient-to-r from-transparent via-white/20 to-transparent"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </SpotlightCard>
                </div>

                <!-- Assignments Section -->
                <div v-if="assignments.length > 0" class="space-y-3 pt-2">
                    <h4
                        class="mb-3 border-l-2 border-destructive/80 pl-2 text-[10px] font-black tracking-[0.2em] text-foreground/80 uppercase"
                    >
                        Pending Transmissions
                    </h4>
                    <SpotlightCard
                        v-for="(assignment, idx) in assignments"
                        :key="assignment.id"
                        as="div"
                        customSize
                        :glowColor="assignment.isOverdue ? 'red' : 'blue'"
                        :className="
                            [
                                'group/task relative p-5 rounded-2xl border border-border/30 bg-card/10 hover:bg-white/[0.02] cursor-pointer transition-all duration-500 animate-fade-up premium-hover w-full min-w-0',
                            ].join(' ')
                        "
                        :class="`stagger-${idx + courses.length + 1}`"
                        @click="handleAssignmentClick(assignment)"
                    >
                        <div class="relative flex h-full w-full flex-col">
                            <div
                                class="relative z-10 flex items-center justify-between"
                            >
                                <div class="min-w-0 flex-1 pr-4">
                                    <h4
                                        class="truncate text-sm font-bold tracking-tight transition-colors group-hover/task:text-primary"
                                    >
                                        {{ assignment.title }}
                                    </h4>
                                    <p
                                        class="mt-0.5 truncate text-[11px] font-medium text-muted-foreground"
                                    >
                                        {{ assignment.description }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-shrink-0 flex-col items-end text-right"
                                >
                                    <p
                                        :class="[
                                            'rounded-lg border px-2.5 py-1 text-[9px] font-black tracking-widest uppercase',
                                            assignment.isOverdue
                                                ? 'border-red-500/20 bg-red-500/10 text-red-500'
                                                : 'border-yellow-500/20 bg-yellow-500/10 text-yellow-500',
                                        ]"
                                    >
                                        {{
                                            assignment.isOverdue
                                                ? 'Critical'
                                                : 'Pending'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1.5 text-[9px] font-bold tracking-widest text-muted-foreground/60 uppercase tabular-nums"
                                    >
                                        {{ assignment.dueDate }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </SpotlightCard>
                </div>
            </div>
        </div>
    </SpotlightCard>
</template>
