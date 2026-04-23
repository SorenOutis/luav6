<script setup lang="ts">
import Progress from '@/components/ui/progress/Progress.vue';
import { Clock } from 'lucide-vue-next';

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

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};
</script>

<template>
    <div class="surface-card p-6 md:p-8 flex flex-col relative overflow-hidden group/board" @mousemove="handleMouseMove">
        <!-- Hover Bloom Effect -->
        <div class="absolute inset-0 opacity-0 group-hover/board:opacity-100 transition-opacity duration-700 pointer-events-none"
            :style="{ background: `radial-gradient(800px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.05), transparent 40%)` }">
        </div>

        <div class="mb-8 relative z-10 border-b border-border/10 pb-4 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-black tracking-tighter flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Mission Control
                </h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/60 mt-1">Your learning trajectory and active assignments</p>
            </div>
            <div class="hidden sm:flex -space-x-2">
                <div v-for="i in 3" :key="i" class="w-7 h-7 rounded-full border-2 border-background bg-muted flex items-center justify-center overflow-hidden">
                    <div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/40 flex items-center justify-center text-[8px] font-bold">
                        {{ String.fromCharCode(64 + i) }}
                    </div>
                </div>
                <div class="w-7 h-7 rounded-full border-2 border-background bg-primary/10 flex items-center justify-center text-[8px] font-bold text-primary backdrop-blur-sm">
                    +12
                </div>
            </div>
        </div>

        <div class="space-y-6 sm:space-y-8 relative z-10">
            <div v-if="courses.length === 0 && assignments.length === 0" class="text-center py-12 sm:py-20 px-4 animate-fade-in border border-dashed border-primary/20 rounded-2xl sm:rounded-3xl bg-primary/5 backdrop-blur-sm group/empty">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6 border border-primary/20 relative">
                    <div class="absolute inset-0 rounded-full bg-primary/20 animate-ping opacity-20"></div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary group-hover/empty:scale-110 transition-transform duration-500 sm:w-8 sm:h-8"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 class="text-sm sm:text-base font-black tracking-widest uppercase mb-2 premium-gradient-text">Objectives Secured</h3>
                <p class="text-[9px] sm:text-[11px] font-bold tracking-wider text-muted-foreground/80 max-w-[280px] mx-auto uppercase leading-relaxed">All sectors clear. You've completed all current modules and transmissions.</p>
                <button class="mt-6 sm:mt-8 px-5 sm:px-6 py-2 rounded-lg sm:rounded-xl bg-primary/10 hover:bg-primary text-primary hover:text-primary-foreground text-[8px] sm:text-[10px] font-black uppercase tracking-widest transition-all duration-300 border border-primary/20">
                    Browse Archives
                </button>
            </div>

            <!-- Courses Section -->
            <div v-if="courses.length > 0" class="space-y-3 sm:space-y-4">
                <div class="flex items-center justify-between mb-2 sm:mb-4">
                    <h4 class="text-[9px] sm:text-[10px] font-black tracking-[0.2em] uppercase text-foreground/80 border-l-2 border-primary pl-2">Active Modules</h4>
                    <span class="text-[8px] sm:text-[9px] font-bold text-primary/60 uppercase tracking-widest">{{ courses.length }} total</span>
                </div>
                <div v-for="(course, idx) in courses" :key="course.id"
                    class="group/course relative overflow-hidden space-y-3 sm:space-y-4 p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-border/40 hover:border-primary/50 bg-card/20 hover:bg-white/[0.03] cursor-pointer transition-all duration-500 animate-fade-up premium-hover backdrop-blur-sm"
                    :class="`stagger-${idx + 1}`"
                    @click="handleCourseClick(course)"
                    @mousemove="handleMouseMove">
                    
                    <!-- Item Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover/course:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(300px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.12), transparent 40%)">
                    </div>

                    <div class="flex items-center justify-between relative z-10 gap-3">
                        <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-primary/10 flex items-center justify-center text-primary border border-primary/20 group-hover/course:bg-primary group-hover/course:text-primary-foreground transition-all duration-500 shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-5 sm:h-5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-black text-sm sm:text-base tracking-tight group-hover/course:text-primary transition-colors truncate">{{ course.name }}</h4>
                                <div class="flex items-center gap-1.5 sm:gap-2 mt-0.5 sm:mt-1">
                                    <p class="text-[8px] sm:text-[10px] font-black text-muted-foreground/60 uppercase tracking-widest">{{ course.completedLessons }}/{{ course.totalLessons }} Units</p>
                                    <span class="w-1 h-1 rounded-full bg-border/40"></span>
                                    <span class="text-[8px] sm:text-[10px] font-black text-emerald-500/80 uppercase tracking-widest">Active</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[8px] sm:text-[10px] font-black text-primary px-2 sm:px-3 py-1 sm:py-1.5 bg-primary/10 rounded-lg sm:rounded-xl border border-primary/20 shadow-[0_0_20px_rgba(var(--primary-rgb),0.15)] group-hover/course:scale-105 transition-transform">+{{ course.xpEarned }}</div>
                        </div>
                    </div>
                    
                    <div class="space-y-1.5 sm:space-y-2 relative z-10">
                        <div class="flex items-center justify-between text-[7px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground/50">
                            <span class="text-foreground/70">{{ course.progress }}% Synced</span>
                            <span class="flex items-center gap-1"><Clock class="w-2 sm:w-2.5 h-2 sm:h-2.5" /> {{ course.nextDeadline }}</span>
                        </div>
                        <div class="h-1.5 sm:h-2 w-full bg-muted/30 rounded-full overflow-hidden border border-border/10">
                            <div class="h-full bg-primary transition-all duration-1000 ease-out relative" :style="{ width: `${course.progress}%` }">
                                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full animate-[shimmer_3s_infinite]"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignments Section -->
            <div v-if="assignments.length > 0" class="space-y-3 pt-2">
                <h4 class="text-[10px] font-black tracking-[0.2em] uppercase text-foreground/80 mb-3 border-l-2 border-destructive/80 pl-2">Pending Transmissions</h4>
                <div v-for="(assignment, idx) in assignments" :key="assignment.id"
                    class="group/task relative overflow-hidden p-5 rounded-2xl border border-border/30 hover:border-primary/40 bg-card/10 hover:bg-white/[0.02] cursor-pointer transition-all duration-500 animate-fade-up premium-hover"
                    :class="`stagger-${idx + courses.length + 1}`"
                    @click="handleAssignmentClick(assignment)"
                    @mousemove="handleMouseMove">
                    
                    <!-- Item Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover/task:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(300px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.05), transparent 40%)">
                    </div>

                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex-1 min-w-0 pr-4">
                            <h4 class="font-bold text-sm truncate tracking-tight group-hover/task:text-primary transition-colors">{{ assignment.title }}</h4>
                            <p class="text-[11px] text-muted-foreground truncate font-medium mt-0.5">{{ assignment.description }}</p>
                        </div>
                        <div class="text-right flex-shrink-0 flex flex-col items-end">
                             <p :class="['text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg border', assignment.isOverdue ? 'bg-red-500/10 text-red-500 border-red-500/20' : 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20']">
                                {{ assignment.isOverdue ? 'Critical' : 'Pending' }}
                            </p>
                            <p class="text-[9px] font-bold text-muted-foreground/60 uppercase tracking-widest mt-1.5 tabular-nums">{{ assignment.dueDate }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
