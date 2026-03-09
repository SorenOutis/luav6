<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
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
    <Card class="border-sidebar-border/70 dark:border-sidebar-border transition-all duration-200 hover:border-sidebar-border hover:shadow-md dark:hover:shadow-lg">
        <CardHeader>
            <CardTitle>Active Courses & Assignments</CardTitle>
            <CardDescription>Your current learning journey</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="courses.length === 0 && assignments.length === 0" class="text-center py-16 px-4 animate-fade-in">
                <div class="w-16 h-16 bg-muted/30 rounded-full flex items-center justify-center mx-auto mb-4 border border-border/40">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3 class="text-lg font-bold mb-1">All Caught Up!</h3>
                <p class="text-sm text-muted-foreground max-w-[250px] mx-auto">You have no active courses or pending assignments at the moment.</p>
            </div>

            <!-- Courses Section -->
            <div v-if="courses.length > 0" class="space-y-4">
                <div v-for="(course, idx) in courses" :key="course.id"
                    class="group/course relative overflow-hidden space-y-2 p-4 rounded-2xl border border-sidebar-border/30 hover:border-primary/40 bg-card/50 hover:bg-primary/[0.02] cursor-pointer transition-all animate-fade-up"
                    :class="`stagger-${idx + 1}`"
                    @click="handleCourseClick(course)"
                    @mousemove="handleMouseMove">
                    
                    <!-- Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover/course:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(300px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.08), transparent 40%)">
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-bold text-sm">{{ course.name }}</h4>
                            <p class="text-xs text-muted-foreground">{{ course.completedLessons }} / {{ course.totalLessons }} lessons</p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-primary">+{{ course.xpEarned }} XP</div>
                        </div>
                    </div>
                    <Progress :value="course.progress" class="h-2" />
                    <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60">
                        <span>{{ course.progress }}% complete</span>
                        <span>Due: {{ course.nextDeadline }}</span>
                    </div>
                </div>
            </div>

            <!-- Assignments Section -->
            <div v-if="assignments.length > 0" class="space-y-2">
                <div v-if="courses.length > 0" class="border-t pt-4">
                    <h5 class="text-sm font-bold mb-2">Pending Tasks</h5>
                </div>
                <div v-else>
                    <h5 class="text-sm font-bold mb-2">Pending Tasks</h5>
                </div>
                <div v-for="(assignment, idx) in assignments" :key="assignment.id"
                    class="group/task relative overflow-hidden p-4 rounded-xl border border-sidebar-border/50 hover:border-primary/40 bg-card/30 hover:bg-primary/[0.01] cursor-pointer transition-all animate-fade-up"
                    :class="`stagger-${idx + courses.length + 1}`"
                    @click="handleAssignmentClick(assignment)"
                    @mousemove="handleMouseMove">
                    
                    <!-- Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover/task:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(300px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.05), transparent 40%)">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-sm truncate">{{ assignment.title }}</h4>
                            <p class="text-xs text-muted-foreground truncate">{{ assignment.description }}</p>
                        </div>
                        <div class="text-right ml-4">
                             <p :class="['text-xs font-bold px-2 py-0.5 rounded-full', assignment.isOverdue ? 'bg-red-500/10 text-red-500' : 'bg-yellow-500/10 text-yellow-500']">
                                {{ assignment.isOverdue ? 'Overdue' : 'Due' }}
                            </p>
                            <p class="text-[10px] text-muted-foreground mt-1">{{ assignment.dueDate }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
