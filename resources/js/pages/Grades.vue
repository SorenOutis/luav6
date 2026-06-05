<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import {
    GraduationCap,
    TrendingUp,
    AlertCircle,
    FileText,
    Clock,
} from 'lucide-vue-next';

interface GradePeriodScore {
    id: number;
    score: string;
    maxScore: string;
    percentage: number;
    remarks: string | null;
    updatedAt: string;
}

interface GradePeriod {
    key: string;
    label: string;
    grade: GradePeriodScore | null;
}

interface SemesterGrade {
    key: string;
    label: string;
    quarters: GradePeriod[];
    finalGrade: number | null;
}

interface SubjectGrade {
    subject: string;
    section: {
        id: number;
        name: string;
        schoolLevel: string;
        schoolLevelLabel: string;
    } | null;
    periods: Array<{
        key: string;
        label: string;
    }>;
    periodGrades: GradePeriod[];
    semesterGrades: SemesterGrade[];
    semesterGrade: number | null;
}

const props = defineProps<{
    subjectGrades: SubjectGrade[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grades', href: '/grades' },
];

const averageSemesterGrade = computed(() => {
    const validGrades = props.subjectGrades
        .map((sg) => sg.semesterGrade)
        .filter((grade) => grade !== null);

    if (validGrades.length === 0) return 0;
    return Math.round(
        validGrades.reduce((sum, grade) => sum + grade, 0) / validGrades.length,
    );
});

const gradeGroups = computed(() => {
    const groups = new Map<
        string,
        {
            key: string;
            label: string;
            finalGradeLabel: string;
            isSeniorHigh: boolean;
            periods: SubjectGrade['periods'];
            subjects: SubjectGrade[];
        }
    >();

    for (const subjectGrade of props.subjectGrades) {
        const key =
            subjectGrade.section?.schoolLevel ??
            subjectGrade.periods.map((period) => period.key).join('|');
        const label = subjectGrade.section?.schoolLevelLabel ?? 'Grades';

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label,
                finalGradeLabel:
                    subjectGrade.section?.schoolLevel === 'senior_high'
                        ? 'Final Grade'
                        : 'Semester Grade',
                isSeniorHigh:
                    subjectGrade.section?.schoolLevel === 'senior_high',
                periods: subjectGrade.periods,
                subjects: [],
            });
        }

        groups.get(key)?.subjects.push(subjectGrade);
    }

    return Array.from(groups.values());
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

const formatGrade = (grade: number | null) => {
    if (grade === null) return '';

    return Number.isInteger(grade) ? String(grade) : grade.toFixed(2);
};

const gradesContainer = ref<HTMLElement | null>(null);

onMounted(() => {
    if (!gradesContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power3.out' },
    });

    // 1. Title and Description entrance
    tl.fromTo(
        '.animate-section',
        { opacity: 0, y: 20 },
        { opacity: 1, y: 0, duration: 0.8, stagger: 0.1 },
    );

    // 2. Overview Cards - Tactical slide-in
    tl.fromTo(
        '.animate-card',
        {
            opacity: 0,
            x: -20,
            scale: 0.98,
        },
        {
            opacity: 1,
            x: 0,
            scale: 1,
            stagger: 0.1,
            duration: 0.8,
            ease: 'back.out(1.2)',
        },
        '-=0.4',
    );

    // 3. Tables / Grade Groups
    tl.fromTo(
        '.animate-group',
        { opacity: 0, y: 30 },
        {
            opacity: 1,
            y: 0,
            duration: 1,
            stagger: 0.15,
            ease: 'expo.out',
        },
        '-=0.6',
    );
});
</script>

<template>
    <Head title="My Grades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="gradesContainer"
            class="container mx-auto px-4 py-6 perspective-[1000px] lg:px-8 lg:py-8"
        >
            <div class="animate-section mb-8">
                <h1 class="text-3xl font-bold tracking-tight">My Grades</h1>
                <p class="mt-2 text-muted-foreground">
                    View your academic performance across all Enrolled Subjects.
                </p>
            </div>

            <!-- Overview Cards -->
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card class="animate-card">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Overall Average</CardTitle
                        >
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold"
                            :class="gradeColor(averageSemesterGrade)"
                        >
                            {{ averageSemesterGrade }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ gradeLabel(averageSemesterGrade) }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="animate-card">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Total Subjects</CardTitle
                        >
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ subjectGrades.length }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Enrolled subjects
                        </p>
                    </CardContent>
                </Card>

                <Card class="animate-card">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Completed</CardTitle
                        >
                        <GraduationCap class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                subjectGrades.filter(
                                    (sg) => sg.semesterGrade !== null,
                                ).length
                            }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            With semester grades
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card
                v-if="subjectGrades.length === 0"
                class="animate-section border-dashed"
            >
                <CardContent
                    class="flex flex-col items-center justify-center py-12"
                >
                    <AlertCircle class="mb-4 h-12 w-12 text-muted-foreground" />
                    <h3 class="text-lg font-semibold">No subjects enrolled</h3>
                    <p class="mt-2 max-w-md text-center text-muted-foreground">
                        You are not enrolled in any sections yet. Contact your
                        instructor to get enrolled.
                    </p>
                </CardContent>
            </Card>

            <!-- Grades Tables -->
            <template v-else>
                <Card
                    v-for="group in gradeGroups"
                    :key="group.key"
                    class="animate-group mb-6"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5 text-primary" />
                            {{ group.label }} Performance
                        </CardTitle>
                        <CardDescription>
                            Grades by subject across all grading periods
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table v-if="group.isSeniorHigh" class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th
                                            class="p-4 text-left font-semibold"
                                            rowspan="2"
                                        >
                                            Subject
                                        </th>
                                        <th
                                            v-for="semester in group.subjects[0]
                                                ?.semesterGrades ?? []"
                                            :key="semester.key"
                                            class="p-4 text-center font-semibold"
                                            colspan="3"
                                        >
                                            {{ semester.label }}
                                        </th>
                                        <th
                                            class="p-4 text-center font-semibold"
                                            rowspan="2"
                                        >
                                            {{ group.finalGradeLabel }}
                                        </th>
                                    </tr>
                                    <tr class="border-b">
                                        <template
                                            v-for="semester in group.subjects[0]
                                                ?.semesterGrades ?? []"
                                            :key="`${semester.key}-quarters`"
                                        >
                                            <th
                                                v-for="quarter in semester.quarters"
                                                :key="quarter.key"
                                                class="p-4 text-center font-semibold"
                                            >
                                                {{ quarter.label }}
                                            </th>
                                            <th
                                                class="p-4 text-center font-semibold"
                                            >
                                                Final Grade
                                            </th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="subjectGrade in group.subjects"
                                        :key="subjectGrade.subject"
                                        class="border-b last:border-b-0 hover:bg-muted/50"
                                    >
                                        <td class="p-4 font-medium">
                                            {{ subjectGrade.subject }}
                                        </td>
                                        <template
                                            v-for="semester in subjectGrade.semesterGrades"
                                            :key="semester.key"
                                        >
                                            <td
                                                v-for="quarter in semester.quarters"
                                                :key="quarter.key"
                                                class="p-4 text-center"
                                            >
                                                <div
                                                    v-if="quarter.grade"
                                                    class="flex flex-col items-center"
                                                >
                                                    <div
                                                        class="text-lg font-bold"
                                                        :class="
                                                            gradeColor(
                                                                quarter.grade
                                                                    .percentage,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            quarter.grade
                                                                .percentage
                                                        }}
                                                    </div>
                                                    <div
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            quarter.grade.score
                                                        }}
                                                        /
                                                        {{
                                                            quarter.grade
                                                                .maxScore
                                                        }}
                                                    </div>
                                                </div>
                                                <div
                                                    v-else
                                                    class="flex items-center justify-center gap-1 text-muted-foreground"
                                                >
                                                    <Clock class="h-4 w-4" />
                                                    <span class="text-sm"
                                                        >Pending</span
                                                    >
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <div
                                                    v-if="
                                                        semester.finalGrade !==
                                                        null
                                                    "
                                                    class="flex flex-col items-center"
                                                >
                                                    <div
                                                        class="text-xl font-bold"
                                                        :class="
                                                            gradeColor(
                                                                semester.finalGrade,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            formatGrade(
                                                                semester.finalGrade,
                                                            )
                                                        }}
                                                    </div>
                                                    <div
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            gradeLabel(
                                                                semester.finalGrade,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                                <div
                                                    v-else
                                                    class="flex items-center justify-center gap-1 text-muted-foreground"
                                                >
                                                    <Clock class="h-4 w-4" />
                                                    <span class="text-sm"
                                                        >Pending</span
                                                    >
                                                </div>
                                            </td>
                                        </template>
                                        <td class="p-4 text-center">
                                            <div
                                                v-if="
                                                    subjectGrade.semesterGrade !==
                                                    null
                                                "
                                                class="flex flex-col items-center"
                                            >
                                                <div
                                                    class="text-2xl font-bold"
                                                    :class="
                                                        gradeColor(
                                                            subjectGrade.semesterGrade,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatGrade(
                                                            subjectGrade.semesterGrade,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        gradeLabel(
                                                            subjectGrade.semesterGrade,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center justify-center gap-1 text-muted-foreground"
                                            >
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm"
                                                    >Pending</span
                                                >
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <table v-else class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="p-4 text-left font-semibold">
                                            Subject
                                        </th>
                                        <th
                                            v-for="period in group.periods"
                                            :key="period.key"
                                            class="p-4 text-center font-semibold"
                                        >
                                            {{ period.label }}
                                        </th>
                                        <th
                                            class="p-4 text-center font-semibold"
                                        >
                                            {{ group.finalGradeLabel }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="subjectGrade in group.subjects"
                                        :key="subjectGrade.subject"
                                        class="border-b last:border-b-0 hover:bg-muted/50"
                                    >
                                        <td class="p-4 font-medium">
                                            {{ subjectGrade.subject }}
                                        </td>
                                        <td
                                            v-for="periodGrade in subjectGrade.periodGrades"
                                            :key="periodGrade.key"
                                            class="p-4 text-center"
                                        >
                                            <div
                                                v-if="periodGrade.grade"
                                                class="flex flex-col items-center"
                                            >
                                                <div
                                                    class="text-lg font-bold"
                                                    :class="
                                                        gradeColor(
                                                            periodGrade.grade
                                                                .percentage,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        periodGrade.grade
                                                            .percentage
                                                    }}
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        periodGrade.grade.score
                                                    }}
                                                    /
                                                    {{
                                                        periodGrade.grade
                                                            .maxScore
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center justify-center gap-1 text-muted-foreground"
                                            >
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm"
                                                    >Pending</span
                                                >
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div
                                                v-if="
                                                    subjectGrade.semesterGrade !==
                                                    null
                                                "
                                                class="flex flex-col items-center"
                                            >
                                                <div
                                                    class="text-2xl font-bold"
                                                    :class="
                                                        gradeColor(
                                                            subjectGrade.semesterGrade,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        subjectGrade.semesterGrade
                                                    }}
                                                </div>
                                                <div
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        gradeLabel(
                                                            subjectGrade.semesterGrade,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center justify-center gap-1 text-muted-foreground"
                                            >
                                                <Clock class="h-4 w-4" />
                                                <span class="text-sm"
                                                    >Pending</span
                                                >
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
