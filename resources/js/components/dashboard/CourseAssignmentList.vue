<script setup lang="ts">
import Progress from '@/components/ui/progress/Progress.vue';

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

        <div class="mb-8 relative z-10 border-b border-border/10 pb-4">
            <h3 class="text-xl font-black tracking-tighter flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                Mission Control
            </h3>
            <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground/60 mt-1">Your learning trajectory and active assignments</p>
        </div>

        <div class="space-y-8 relative z-10">
            <div v-if="courses.length === 0 && assignments.length === 0" class="text-center py-16 px-4 animate-fade-in border border-dashed border-border/20 rounded-2xl bg-muted/5">
                <div class="w-16 h-16 bg-muted/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-border/20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 class="text-sm font-black tracking-widest uppercase mb-1">Queue Empty</h3>
                <p class="text-[10px] font-bold tracking-wider text-muted-foreground max-w-[250px] mx-auto uppercase">No active objectives available at this time.</p>
            </div>

            <!-- Courses Section -->
            <div v-if="courses.length > 0" class="space-y-4">
                <h4 class="text-[10px] font-black tracking-[0.2em] uppercase text-foreground/80 mb-3 border-l-2 border-primary pl-2">Active Modules</h4>
                <div v-for="(course, idx) in courses" :key="course.id"
                    class="group/course relative overflow-hidden space-y-3 p-5 rounded-2xl border border-border/30 hover:border-primary/40 bg-card/10 hover:bg-white/[0.02] cursor-pointer transition-all duration-500 animate-fade-up premium-hover"
                    :class="`stagger-${idx + 1}`"
                    @click="handleCourseClick(course)"
                    @mousemove="handleMouseMove">
                    
                    <!-- Item Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover/course:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(300px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary-rgb), 0.08), transparent 40%)">
                    </div>

                    <div class="flex items-center justify-between relative z-10">
                        <div>
                            <h4 class="font-bold text-sm tracking-tight group-hover/course:text-primary transition-colors">{{ course.name }}</h4>
                            <p class="text-[10px] font-bold text-muted-foreground/60 uppercase tracking-widest mt-0.5">{{ course.completedLessons }} of {{ course.totalLessons }} Units complete</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-black text-primary px-2 py-1 bg-primary/10 rounded-lg border border-primary/20 shadow-[0_0_15px_rgba(var(--primary-rgb),0.2)]">+{{ course.xpEarned }} XP</div>
                        </div>
                    </div>
                    <Progress :value="course.progress" class="h-1.5 relative z-10" />
                    <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-widest text-muted-foreground/50 relative z-10">
                        <span class="text-foreground">{{ course.progress }}% completion route</span>
                        <span>Terminal: {{ course.nextDeadline }}</span>
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
