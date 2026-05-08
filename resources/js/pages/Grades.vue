<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { GraduationCap, TrendingUp, AlertCircle, FileText, CheckCircle2, Clock } from 'lucide-vue-next';

const props = defineProps<{
    subjectGrades: Array<{
        subject: string;
        section: { id: number; name: string } | null;
        prelim: {
            id: number;
            score: string;
            maxScore: string;
            percentage: number;
            remarks: string | null;
            updatedAt: string;
        } | null;
        midterm: {
            id: number;
            score: string;
            maxScore: string;
            percentage: number;
            remarks: string | null;
            updatedAt: string;
        } | null;
        final: {
            id: number;
            score: string;
            maxScore: string;
            percentage: number;
            remarks: string | null;
            updatedAt: string;
        } | null;
        semesterGrade: number | null;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grades', href: '/grades' },
];

const averageSemesterGrade = computed(() => {
    const validGrades = props.subjectGrades
        .map(sg => sg.semesterGrade)
        .filter(grade => grade !== null);

    if (validGrades.length === 0) return 0;
    return Math.round(validGrades.reduce((sum, grade) => sum + grade, 0) / validGrades.length);
});

const gradeColor = (percentage: number | null) => {
    if (percentage === null) return 'text-muted-foreground';
    if (percentage >= 85) return 'text-emerald-500';
    if (percentage >= 70) return 'text-amber-500';
    return 'text-rose-500';
};

const gradeBg = (percentage: number | null) => {
    if (percentage === null) return 'bg-muted';
    if (percentage >= 85) return 'bg-emerald-500';
    if (percentage >= 70) return 'bg-amber-500';
    return 'bg-rose-500';
};

const gradeLabel = (percentage: number | null) => {
    if (percentage === null) return 'Pending';
    if (percentage >= 85) return 'Excellent';
    if (percentage >= 70) return 'Good';
    if (percentage >= 60) return 'Satisfactory';
    return 'Needs Improvement';
};
</script>

<template>
    <Head title="My Grades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto px-4 py-6 lg:px-8 lg:py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight">My Grades</h1>
                <p class="text-muted-foreground mt-2">View your academic performance across all enrolled sections.</p>
            </div>

            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Semester Average</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" :class="gradeColor(averageSemesterGrade)">{{ averageSemesterGrade }}</div>
                        <p class="text-xs text-muted-foreground mt-1">{{ gradeLabel(averageSemesterGrade) }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Subjects</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ subjectGrades.length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">Enrolled subjects</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Completed</CardTitle>
                        <GraduationCap class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ subjectGrades.filter(sg => sg.semesterGrade !== null).length }}</div>
                        <p class="text-xs text-muted-foreground mt-1">With semester grades</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-if="subjectGrades.length === 0" class="border-dashed">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <AlertCircle class="h-12 w-12 text-muted-foreground mb-4" />
                    <h3 class="text-lg font-semibold">No subjects enrolled</h3>
                    <p class="text-muted-foreground text-center max-w-md mt-2">
                        You are not enrolled in any sections yet. Contact your instructor to get enrolled.
                    </p>
                </CardContent>
            </Card>

            <!-- Grades Table -->
            <template v-else>
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5 text-primary" />
                            Academic Performance
                        </CardTitle>
                        <CardDescription>
                            Grades by subject across all grading periods
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left p-4 font-semibold">Subject</th>
                                        <th class="text-center p-4 font-semibold">Prelim</th>
                                        <th class="text-center p-4 font-semibold">Midterm</th>
                                        <th class="text-center p-4 font-semibold">Final</th>
                                        <th class="text-center p-4 font-semibold">Semester Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="subjectGrade in subjectGrades" :key="subjectGrade.subject" class="border-b last:border-b-0 hover:bg-muted/50">
                                        <td class="p-4 font-medium">{{ subjectGrade.subject }}</td>
                                        <td class="p-4 text-center">
                                            <div v-if="subjectGrade.prelim" class="flex flex-col items-center">
                                                <div class="text-lg font-bold" :class="gradeColor(subjectGrade.prelim.percentage)">
                                                    {{ subjectGrade.prelim.percentage }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ subjectGrade.prelim.score }} / {{ subjectGrade.prelim.maxScore }}
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1 text-muted-foreground">
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm">Pending</span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div v-if="subjectGrade.midterm" class="flex flex-col items-center">
                                                <div class="text-lg font-bold" :class="gradeColor(subjectGrade.midterm.percentage)">
                                                    {{ subjectGrade.midterm.percentage }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ subjectGrade.midterm.score }} / {{ subjectGrade.midterm.maxScore }}
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1 text-muted-foreground">
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm">Pending</span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div v-if="subjectGrade.final" class="flex flex-col items-center">
                                                <div class="text-lg font-bold" :class="gradeColor(subjectGrade.final.percentage)">
                                                    {{ subjectGrade.final.percentage }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ subjectGrade.final.score }} / {{ subjectGrade.final.maxScore }}
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1 text-muted-foreground">
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm">Pending</span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div v-if="subjectGrade.semesterGrade !== null" class="flex flex-col items-center">
                                                <div class="text-2xl font-bold" :class="gradeColor(subjectGrade.semesterGrade)">
                                                    {{ subjectGrade.semesterGrade }}
                                                </div>
                                                <div class="text-xs text-muted-foreground mt-1">
                                                    {{ gradeLabel(subjectGrade.semesterGrade) }}
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center justify-center gap-1 text-muted-foreground">
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm">Pending</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
