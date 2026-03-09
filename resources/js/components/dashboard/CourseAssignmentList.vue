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
    grade: number | null;
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
</script>

<template>
    <Card class="border-sidebar-border/70 dark:border-sidebar-border transition-all duration-200 hover:border-sidebar-border hover:shadow-md dark:hover:shadow-lg">
        <CardHeader>
            <CardTitle>Active Courses & Assignments</CardTitle>
            <CardDescription>Your current learning journey</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="courses.length === 0 && assignments.length === 0" class="text-center py-8">
                <p class="text-muted-foreground">No active courses or assignments yet.</p>
            </div>

            <!-- Courses Section -->
            <div v-if="courses.length > 0" class="space-y-4">
                <div v-for="course in courses" :key="course.id"
                    class="space-y-2 p-3 rounded-xl border border-transparent hover:border-primary/20 hover:bg-primary/5 cursor-pointer transition-all"
                    @click="handleCourseClick(course)">
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
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
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
                <div v-for="assignment in assignments" :key="assignment.id"
                    class="p-3 rounded-lg border border-sidebar-border/50 hover:border-primary/30 hover:bg-primary/5 cursor-pointer transition-all"
                    @click="handleAssignmentClick(assignment)">
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
