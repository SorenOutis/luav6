<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import axios from 'axios';
import gsap from 'gsap';
import {
    GraduationCap,
    TrendingUp,
    AlertCircle,
    Clock,
    Search,
    Printer,
    ChevronDown,
    BarChart3,
    BookOpen,
    Loader2,
    RefreshCw,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import GradeDistributionChart from '@/components/GradeDistributionChart.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Input from '@/components/ui/input/Input.vue';
import Progress from '@/components/ui/progress/Progress.vue';
import { useStaleWhileRevalidate } from '@/composables/useStaleWhileRevalidate';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

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
    subjectGrades?: SubjectGrade[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grades', href: '/grades' },
];

// ── API fetch with stale-while-revalidate caching ────────────────
const {
    data: subjectGrades,
    isLoading,
    error: fetchError,
    revalidate: fetchGrades,
} = useStaleWhileRevalidate<SubjectGrade[]>(
    'grades-data',
    async () => {
        const { data } = await axios.get<{ subjectGrades: SubjectGrade[] }>(
            '/api/grades',
        );
        return data.subjectGrades;
    },
    30 * 1000, // 30-second TTL — grades are admin-entered and should reflect quickly
    props.subjectGrades, // Use SSR data as initial value if no cache exists
);

// Revalidate whenever the page becomes visible (e.g., after admin enters grades)
// This ensures changes made in the admin panel show up immediately.
//
// useEventListener unbinds on unmount. The previous version registered this at
// module scope with no teardown, so in an Inertia SPA every visit to this page
// added another listener that stayed for the rest of the session and called
// fetchGrades() on behalf of a component that no longer existed.
useEventListener(document, 'visibilitychange', () => {
    if (!document.hidden) {
        fetchGrades();
    }
});

// ── Search / Filter ──────────────────────────────────────────────
const searchQuery = ref('');

const filteredSubjectGrades = computed(() => {
    const grades = subjectGrades.value ?? [];
    if (!searchQuery.value) return grades;
    const q = searchQuery.value.toLowerCase();
    return grades.filter((sg) => sg.subject.toLowerCase().includes(q));
});

// ── Computed Values (from filtered data) ─────────────────────────
const averageSemesterGrade = computed(() => {
    const validGrades = filteredSubjectGrades.value
        .map((sg) => sg.semesterGrade)
        .filter((grade): grade is number => grade !== null);

    if (validGrades.length === 0) return 0;
    return Math.round(
        validGrades.reduce((sum, grade) => sum + grade, 0) / validGrades.length,
    );
});

const completedCount = computed(
    () =>
        filteredSubjectGrades.value.filter((sg) => sg.semesterGrade !== null)
            .length,
);

const totalFilteredCount = computed(() => filteredSubjectGrades.value.length);

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

    for (const rawSubject of filteredSubjectGrades.value) {
        const subjectGrade = rawSubject as SubjectGrade;
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

// ── Grade Distribution ───────────────────────────────────────────
const distributionData = computed(() => {
    const grades = filteredSubjectGrades.value
        .map((sg) => sg.semesterGrade)
        .filter((g): g is number => g !== null);

    const excellent = grades.filter((g) => g >= 85).length;
    const good = grades.filter((g) => g >= 70 && g < 85).length;
    const satisfactory = grades.filter((g) => g >= 60 && g < 70).length;
    const needsImprovement = grades.filter((g) => g < 60).length;

    return {
        segments: [
            {
                label: 'Excellent (≥85)',
                count: excellent,
                color: '#10b981',
                textColor: 'text-emerald-600',
            },
            {
                label: 'Good (70-84)',
                count: good,
                color: '#f59e0b',
                textColor: 'text-amber-600',
            },
            {
                label: 'Satisfactory (60-69)',
                count: satisfactory,
                color: '#f97316',
                textColor: 'text-orange-600',
            },
            {
                label: 'Needs Improvement (<60)',
                count: needsImprovement,
                color: '#f43f5e',
                textColor: 'text-rose-600',
            },
        ],
        total: grades.length,
    };
});

// ── Styling helpers ──────────────────────────────────────────────
const gradeColor = (percentage: number | null) => {
    if (percentage === null) return 'text-muted-foreground';
    if (percentage >= 85) return 'text-emerald-500';
    if (percentage >= 70) return 'text-amber-500';
    return 'text-rose-500';
};

const progressColor = (percentage: number | null) => {
    if (percentage === null) return 'bg-muted';
    if (percentage >= 85) return 'bg-emerald-500';
    if (percentage >= 70) return 'bg-amber-500';
    if (percentage >= 60) return 'bg-orange-500';
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

// ── Collapsible Periods (College) ────────────────────────────────
const STORAGE_KEY = 'grades-expanded-periods';

const expandedPeriods = ref<string[]>(
    (() => {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
        } catch {
            return [];
        }
    })(),
);

// Persist state changes to sessionStorage across navigation
watch(expandedPeriods, (keys) => {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
});

// Track which periods are showing the "scroll for more" hint
const scrollHints = ref<Record<string, boolean>>({});
const scrollHintTimers = ref<Record<string, ReturnType<typeof setTimeout>>>({});

const togglePeriod = (key: string) => {
    const wasExpanded = expandedPeriods.value.includes(key);

    if (wasExpanded) {
        expandedPeriods.value = expandedPeriods.value.filter((k) => k !== key);
        // Clear any pending hint timer for collapsed periods
        if (scrollHintTimers.value[key]) {
            clearTimeout(scrollHintTimers.value[key]);
            delete scrollHintTimers.value[key];
        }
        scrollHints.value[key] = false;
    } else {
        expandedPeriods.value = [...expandedPeriods.value, key];
        // Scroll period content to top on expand so user sees first subjects
        nextTick(() => {
            const el = document.querySelector(`[data-period-key="${key}"]`);
            if (el) el.scrollTop = 0;
        });
        // Clear any previous timer before setting a new one
        if (scrollHintTimers.value[key]) {
            clearTimeout(scrollHintTimers.value[key]);
        }
        scrollHints.value[key] = true;
        scrollHintTimers.value[key] = setTimeout(() => {
            scrollHints.value[key] = false;
            delete scrollHintTimers.value[key];
        }, 2500);
    }
};

// Auto-expand all college periods when searching, collapse when cleared
watch(searchQuery, (query) => {
    if (query) {
        const keys: string[] = [];
        for (const group of gradeGroups.value) {
            if (group.isSeniorHigh) continue;
            // Desktop period keys
            keys.push(...group.periods.map((p) => p.key));
            // Mobile period keys (prefixed per subject)
            for (const subject of group.subjects) {
                for (const period of group.periods) {
                    keys.push('m-' + subject.subject + '-' + period.key);
                }
            }
        }
        expandedPeriods.value = keys;
        // Show scroll hints for desktop periods in groups with many subjects
        for (const group of gradeGroups.value) {
            if (group.isSeniorHigh || group.subjects.length <= MAX_VISIBLE_ROWS)
                continue;
            for (const period of group.periods) {
                const key = period.key;
                if (scrollHintTimers.value[key]) {
                    clearTimeout(scrollHintTimers.value[key]);
                }
                scrollHints.value[key] = true;
                scrollHintTimers.value[key] = setTimeout(() => {
                    scrollHints.value[key] = false;
                    delete scrollHintTimers.value[key];
                }, 2500);
            }
        }
    } else {
        expandedPeriods.value = [];
        // Clear any lingering scroll hints
        for (const key of Object.keys(scrollHintTimers.value)) {
            clearTimeout(scrollHintTimers.value[key]);
        }
        scrollHintTimers.value = {};
        scrollHints.value = {};
    }
});

const getPeriodGrade = (
    subject: SubjectGrade,
    periodKey: string,
): GradePeriodScore | null => {
    const found = subject.periodGrades.find((pg) => pg.key === periodKey);
    return found?.grade ?? null;
};

/**
 * Look up a quarter grade from the specific subject's own semesterGrades,
 * rather than from the first subject in the group.
 */
const getSubjectQuarterGrade = (
    subject: SubjectGrade,
    semesterKey: string,
    quarterKey: string,
): GradePeriodScore | null => {
    const semester = subject.semesterGrades.find((s) => s.key === semesterKey);
    if (!semester) return null;
    const quarter = semester.quarters.find((q) => q.key === quarterKey);
    return quarter?.grade ?? null;
};

/**
 * Look up the final grade for a semester from the specific subject's own semesterGrades.
 */
const getSubjectFinalGrade = (
    subject: SubjectGrade,
    semesterKey: string,
): number | null => {
    const semester = subject.semesterGrades.find((s) => s.key === semesterKey);
    return semester?.finalGrade ?? null;
};

const HEADER_H = 40;
const ROW_H = 44;
const MAX_VISIBLE_ROWS = 8;

const periodDesktopHeight = (subjectsCount: number): number => {
    const totalH = HEADER_H + subjectsCount * ROW_H;
    const cappedH = HEADER_H + MAX_VISIBLE_ROWS * ROW_H;
    return Math.min(totalH, cappedH);
};

const periodMobileHeight = (): number => {
    return 280;
};

const gradedCount = (subjects: SubjectGrade[], periodKey: string): number => {
    return subjects.filter((s) => {
        const pg = s.periodGrades.find((p) => p.key === periodKey);
        return pg?.grade !== null && pg?.grade !== undefined;
    }).length;
};

const isGroupAllExpanded = (group: {
    periods: Array<{ key: string }>;
    subjects: SubjectGrade[];
}): boolean => {
    for (const period of group.periods) {
        if (!expandedPeriods.value.includes(period.key)) return false;
    }
    for (const subject of group.subjects) {
        for (const period of group.periods) {
            if (
                !expandedPeriods.value.includes(
                    'm-' + subject.subject + '-' + period.key,
                )
            )
                return false;
        }
    }
    return true;
};

const toggleAllCollege = (group: {
    periods: Array<{ key: string }>;
    subjects: SubjectGrade[];
}): void => {
    const allKeys: string[] = [];
    for (const period of group.periods) allKeys.push(period.key);
    for (const subject of group.subjects) {
        for (const period of group.periods) {
            allKeys.push('m-' + subject.subject + '-' + period.key);
        }
    }

    const allExpanded = allKeys.every((key) =>
        expandedPeriods.value.includes(key),
    );

    if (allExpanded) {
        expandedPeriods.value = expandedPeriods.value.filter(
            (k) => !allKeys.includes(k),
        );
    } else {
        const missing = allKeys.filter(
            (k) => !expandedPeriods.value.includes(k),
        );
        expandedPeriods.value = [...expandedPeriods.value, ...missing];
    }
};

// ── PDF Export ───────────────────────────────────────────────────
const gradesContainer = ref<HTMLElement | null>(null);
const isExporting = ref(false);

const exportPdf = async () => {
    if (!gradesContainer.value) return;
    isExporting.value = true;

    try {
        const html2pdf = (await import('html2pdf.js')).default;
        const element = gradesContainer.value;

        await html2pdf()
            .set({
                margin: [0.4, 0.4, 0.4, 0.4],
                filename: 'my-grades.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'portrait',
                },
            })
            .from(element)
            .save();
    } catch (err) {
        console.error('PDF export failed:', err);
    } finally {
        isExporting.value = false;
    }
};

// ── Animations ───────────────────────────────────────────────────
// Pending scroll-hint timers would otherwise fire into a destroyed component.
onBeforeUnmount(() => {
    Object.values(scrollHintTimers.value).forEach((timer) =>
        clearTimeout(timer),
    );
    scrollHintTimers.value = {};
});

onMounted(() => {
    // Animations start immediately because the composable already
    // resolved data from cache or SSR on init.
    if (!gradesContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power3.out' },
    });

    tl.fromTo(
        '.animate-section',
        { opacity: 0, y: 20 },
        { opacity: 1, y: 0, duration: 0.8, stagger: 0.1 },
    );

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
            <!-- Header -->
            <div
                class="animate-section mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">My Grades</h1>
                    <p class="mt-2 text-muted-foreground">
                        View your academic performance across all Enrolled
                        Subjects.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative w-full sm:w-56 lg:w-72">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search subjects..."
                            class="pl-9"
                        />
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="isExporting"
                        @click="exportPdf"
                    >
                        <Printer class="h-4 w-4" />
                        {{ isExporting ? 'Exporting...' : 'Export PDF' }}
                    </Button>
                </div>
            </div>

            <!-- Loading state -->
            <div
                v-if="isLoading"
                class="animate-section mb-8 flex flex-col items-center justify-center py-16"
            >
                <Loader2
                    class="mb-4 h-10 w-10 animate-spin text-muted-foreground"
                />
                <p class="text-muted-foreground">Loading your grades...</p>
            </div>

            <!-- Error state -->
            <div v-else-if="fetchError" class="animate-section mb-8">
                <Card class="border-destructive/30">
                    <CardContent
                        class="flex flex-col items-center justify-center py-12"
                    >
                        <AlertCircle class="mb-4 h-12 w-12 text-destructive" />
                        <h3 class="text-lg font-semibold">
                            Something went wrong
                        </h3>
                        <p
                            class="mt-2 max-w-md text-center text-muted-foreground"
                        >
                            {{ fetchError }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-4"
                            @click="fetchGrades"
                        >
                            <RefreshCw class="h-4 w-4" />
                            Try Again
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Overview Cards -->
            <div
                v-show="!isLoading && !fetchError"
                class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
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
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ totalFilteredCount }}
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
                            {{ completedCount }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            With semester grades
                        </p>
                    </CardContent>
                </Card>

                <Card class="animate-card">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium"
                            >Grade Distribution</CardTitle
                        >
                        <BarChart3 class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <GradeDistributionChart
                            :segments="distributionData.segments"
                            :total="distributionData.total"
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card
                v-if="
                    !isLoading &&
                    filteredSubjectGrades.length === 0 &&
                    !searchQuery
                "
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

            <!-- No results state -->
            <Card
                v-else-if="filteredSubjectGrades.length === 0 && searchQuery"
                class="animate-group border-dashed"
            >
                <CardContent
                    class="flex flex-col items-center justify-center py-12"
                >
                    <Search class="mb-4 h-12 w-12 text-muted-foreground" />
                    <h3 class="text-lg font-semibold">No subjects found</h3>
                    <p class="mt-2 max-w-md text-center text-muted-foreground">
                        No subjects match "{{ searchQuery }}". Try a different
                        search term.
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
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <GraduationCap
                                        class="h-5 w-5 text-primary"
                                    />
                                    {{ group.label }} Performance
                                </CardTitle>
                                <CardDescription>
                                    Grades by subject across all grading periods
                                </CardDescription>
                            </div>
                            <Button
                                v-if="!group.isSeniorHigh"
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="toggleAllCollege(group)"
                            >
                                <ChevronDown
                                    class="h-3.5 w-3.5 transition-transform duration-200"
                                    :class="
                                        isGroupAllExpanded(group)
                                            ? 'rotate-180'
                                            : ''
                                    "
                                />
                                <Transition name="fade" mode="out-in">
                                    <span
                                        :key="
                                            isGroupAllExpanded(group)
                                                ? 'collapse'
                                                : 'expand'
                                        "
                                        class="inline-block"
                                    >
                                        {{
                                            isGroupAllExpanded(group)
                                                ? 'Collapse All'
                                                : 'Expand All'
                                        }}
                                    </span>
                                </Transition>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- ===== MOBILE: Card Layout ===== -->
                        <div class="space-y-3 md:hidden">
                            <Card
                                v-for="subjectGrade in group.subjects"
                                :key="subjectGrade.subject"
                                class="overflow-hidden border shadow-none"
                            >
                                <CardHeader
                                    class="border-b bg-muted/30 px-4 py-3"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <CardTitle
                                            class="text-sm font-semibold"
                                        >
                                            {{ subjectGrade.subject }}
                                        </CardTitle>
                                        <Badge
                                            variant="outline"
                                            class="text-[10px]"
                                        >
                                            {{ subjectGrade.section?.name }}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent class="divide-y p-0">
                                    <!-- Senior High: semesters with quarters -->
                                    <template v-if="group.isSeniorHigh">
                                        <div
                                            v-for="semester in subjectGrade.semesterGrades"
                                            :key="semester.key"
                                            class="px-4 py-3"
                                        >
                                            <div
                                                class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                            >
                                                {{ semester.label }}
                                            </div>
                                            <div
                                                v-for="quarter in semester.quarters"
                                                :key="quarter.key"
                                                class="mb-2 flex items-center gap-3"
                                            >
                                                <span
                                                    class="w-28 shrink-0 text-xs text-muted-foreground"
                                                >
                                                    {{ quarter.label }}
                                                </span>
                                                <div
                                                    v-if="quarter.grade"
                                                    class="flex flex-1 items-center gap-2"
                                                >
                                                    <Progress
                                                        :value="
                                                            quarter.grade
                                                                .percentage
                                                        "
                                                        class="h-1.5 flex-1"
                                                        :indicator-class="
                                                            progressColor(
                                                                quarter.grade
                                                                    .percentage,
                                                            )
                                                        "
                                                    />
                                                    <span
                                                        class="w-10 text-right text-sm font-bold tabular-nums"
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
                                                    </span>
                                                </div>
                                                <div
                                                    v-else
                                                    class="flex items-center gap-1 text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    <span class="text-xs"
                                                        >Pending</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="mt-1 flex items-center justify-between border-t pt-2 text-xs"
                                            >
                                                <span
                                                    class="text-muted-foreground"
                                                    >Semester Grade</span
                                                >
                                                <span
                                                    v-if="
                                                        semester.finalGrade !==
                                                        null
                                                    "
                                                    class="font-bold"
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
                                                    ({{
                                                        gradeLabel(
                                                            semester.finalGrade,
                                                        )
                                                    }})
                                                </span>
                                                <span
                                                    v-else
                                                    class="flex items-center gap-1 text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    Pending
                                                </span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- College: collapsible periods -->
                                    <template v-else>
                                        <div class="divide-y">
                                            <div
                                                v-for="(
                                                    period, pIdx
                                                ) in group.periods"
                                                :key="period.key"
                                            >
                                                <button
                                                    @click="
                                                        togglePeriod(
                                                            'm-' +
                                                                subjectGrade.subject +
                                                                '-' +
                                                                period.key,
                                                        )
                                                    "
                                                    :aria-expanded="
                                                        expandedPeriods.includes(
                                                            'm-' +
                                                                subjectGrade.subject +
                                                                '-' +
                                                                period.key,
                                                        )
                                                    "
                                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left transition-colors hover:bg-muted/30"
                                                >
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="flex h-6 w-6 items-center justify-center rounded bg-primary/10 text-xs font-bold text-primary"
                                                        >
                                                            {{ pIdx + 1 }}
                                                        </div>
                                                        <span
                                                            class="text-sm font-medium"
                                                        >
                                                            {{ period.label }}
                                                        </span>
                                                        <span
                                                            v-if="
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )
                                                            "
                                                            class="text-[10px] text-emerald-500"
                                                            >Graded</span
                                                        >
                                                        <span
                                                            v-else
                                                            class="text-[10px] text-muted-foreground"
                                                            >Pending</span
                                                        >
                                                    </div>
                                                    <ChevronDown
                                                        class="h-4 w-4 text-muted-foreground transition-transform duration-200"
                                                        :class="
                                                            expandedPeriods.includes(
                                                                'm-' +
                                                                    subjectGrade.subject +
                                                                    '-' +
                                                                    period.key,
                                                            )
                                                                ? 'rotate-180'
                                                                : ''
                                                        "
                                                    />
                                                </button>
                                                <div
                                                    :data-period-key="
                                                        'm-' +
                                                        subjectGrade.subject +
                                                        '-' +
                                                        period.key
                                                    "
                                                    :class="[
                                                        'transition-all duration-300 ease-out',
                                                        expandedPeriods.includes(
                                                            'm-' +
                                                                subjectGrade.subject +
                                                                '-' +
                                                                period.key,
                                                        )
                                                            ? 'grades-period-scroll relative overflow-y-auto border-t px-4 py-3 opacity-100'
                                                            : 'overflow-hidden border-t-0 px-0 py-0 opacity-0',
                                                    ]"
                                                    :style="{
                                                        maxHeight:
                                                            expandedPeriods.includes(
                                                                'm-' +
                                                                    subjectGrade.subject +
                                                                    '-' +
                                                                    period.key,
                                                            )
                                                                ? periodMobileHeight() +
                                                                  'px'
                                                                : '0px',
                                                    }"
                                                >
                                                    <div
                                                        v-if="
                                                            getPeriodGrade(
                                                                subjectGrade,
                                                                period.key,
                                                            )
                                                        "
                                                        class="flex flex-col gap-2"
                                                    >
                                                        <div
                                                            class="flex items-center justify-between"
                                                        >
                                                            <span
                                                                class="text-xs text-muted-foreground"
                                                                >Score</span
                                                            >
                                                            <span
                                                                class="text-sm font-medium tabular-nums"
                                                            >
                                                                {{
                                                                    getPeriodGrade(
                                                                        subjectGrade,
                                                                        period.key,
                                                                    )!.score
                                                                }}
                                                                /
                                                                {{
                                                                    getPeriodGrade(
                                                                        subjectGrade,
                                                                        period.key,
                                                                    )!.maxScore
                                                                }}
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="flex items-center justify-between"
                                                        >
                                                            <span
                                                                class="text-xs text-muted-foreground"
                                                                >Percentage</span
                                                            >
                                                            <div
                                                                class="flex items-center gap-2"
                                                            >
                                                                <Progress
                                                                    :value="
                                                                        getPeriodGrade(
                                                                            subjectGrade,
                                                                            period.key,
                                                                        )!
                                                                            .percentage
                                                                    "
                                                                    class="h-1.5 w-20"
                                                                    :indicator-class="
                                                                        progressColor(
                                                                            getPeriodGrade(
                                                                                subjectGrade,
                                                                                period.key,
                                                                            )!
                                                                                .percentage,
                                                                        )
                                                                    "
                                                                />
                                                                <span
                                                                    class="text-sm font-bold tabular-nums"
                                                                    :class="
                                                                        gradeColor(
                                                                            getPeriodGrade(
                                                                                subjectGrade,
                                                                                period.key,
                                                                            )!
                                                                                .percentage,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        getPeriodGrade(
                                                                            subjectGrade,
                                                                            period.key,
                                                                        )!
                                                                            .percentage
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div
                                                            v-if="
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )?.remarks
                                                            "
                                                            class="flex items-center justify-between"
                                                        >
                                                            <span
                                                                class="text-xs text-muted-foreground"
                                                                >Remarks</span
                                                            >
                                                            <span
                                                                class="text-xs text-muted-foreground"
                                                            >
                                                                {{
                                                                    getPeriodGrade(
                                                                        subjectGrade,
                                                                        period.key,
                                                                    )!.remarks
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex items-center gap-1 text-muted-foreground"
                                                    >
                                                        <Clock
                                                            class="h-3 w-3"
                                                        />
                                                        <span class="text-xs"
                                                            >No grade yet</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="pointer-events-none sticky bottom-0 -mx-4 -mb-3 h-6 bg-gradient-to-t from-card to-transparent"
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Overall grade footer -->
                                    <div
                                        class="flex items-center justify-between bg-muted/20 px-4 py-3"
                                    >
                                        <span
                                            class="text-xs font-semibold tracking-wider uppercase"
                                        >
                                            {{
                                                group.isSeniorHigh
                                                    ? 'Final Grade'
                                                    : 'Semester Grade'
                                            }}
                                        </span>
                                        <div
                                            v-if="
                                                subjectGrade.semesterGrade !==
                                                null
                                            "
                                            class="flex items-center gap-2"
                                        >
                                            <Progress
                                                :value="
                                                    subjectGrade.semesterGrade
                                                "
                                                class="h-2 w-20"
                                                :indicator-class="
                                                    progressColor(
                                                        subjectGrade.semesterGrade,
                                                    )
                                                "
                                            />
                                            <span
                                                class="text-sm font-bold"
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
                                            </span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex items-center gap-1 text-muted-foreground"
                                        >
                                            <Clock class="h-3 w-3" />
                                            <span class="text-xs">Pending</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- ===== DESKTOP: Table Layout ===== -->
                        <div class="hidden overflow-x-auto md:block">
                            <!-- Senior High: Separate tables per semester -->
                            <template v-if="group.isSeniorHigh">
                                <div
                                    v-for="semester in group.subjects[0]
                                        ?.semesterGrades ?? []"
                                    :key="semester.key"
                                    class="mb-6 last:mb-0"
                                >
                                    <div class="mb-3 flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-md bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                                        >
                                            {{ semester.label }}
                                        </span>
                                        <div class="h-px flex-1 bg-border" />
                                    </div>
                                    <table class="w-full">
                                        <thead>
                                            <tr class="border-b">
                                                <th
                                                    class="sticky left-0 z-10 bg-card p-3 text-left text-sm font-semibold"
                                                >
                                                    Subject
                                                </th>
                                                <th
                                                    v-for="quarter in semester.quarters"
                                                    :key="quarter.key"
                                                    class="p-3 text-center text-sm font-semibold"
                                                >
                                                    {{ quarter.label }}
                                                </th>
                                                <th
                                                    class="p-3 text-center text-sm font-semibold"
                                                >
                                                    Final Grade
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="subjectGrade in group.subjects"
                                                :key="subjectGrade.subject"
                                                class="border-b last:border-b-0 hover:bg-muted/50"
                                            >
                                                <td
                                                    class="sticky left-0 z-10 bg-card p-3 text-sm font-medium hover:bg-muted/50"
                                                >
                                                    {{ subjectGrade.subject }}
                                                </td>
                                                <td
                                                    v-for="quarter in semester.quarters"
                                                    :key="quarter.key"
                                                    class="p-3 text-center"
                                                >
                                                    <div
                                                        v-if="
                                                            getSubjectQuarterGrade(
                                                                subjectGrade,
                                                                semester.key,
                                                                quarter.key,
                                                            )
                                                        "
                                                        class="flex flex-col items-center gap-1"
                                                    >
                                                        <div
                                                            class="text-base font-bold"
                                                            :class="
                                                                gradeColor(
                                                                    getSubjectQuarterGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                        quarter.key,
                                                                    )!
                                                                        .percentage,
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                getSubjectQuarterGrade(
                                                                    subjectGrade,
                                                                    semester.key,
                                                                    quarter.key,
                                                                )!.percentage
                                                            }}
                                                        </div>
                                                        <Progress
                                                            :value="
                                                                getSubjectQuarterGrade(
                                                                    subjectGrade,
                                                                    semester.key,
                                                                    quarter.key,
                                                                )!.percentage
                                                            "
                                                            class="h-1 w-14"
                                                            :indicator-class="
                                                                progressColor(
                                                                    getSubjectQuarterGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                        quarter.key,
                                                                    )!
                                                                        .percentage,
                                                                )
                                                            "
                                                        />
                                                        <div
                                                            class="text-[10px] text-muted-foreground"
                                                        >
                                                            {{
                                                                getSubjectQuarterGrade(
                                                                    subjectGrade,
                                                                    semester.key,
                                                                    quarter.key,
                                                                )!.score
                                                            }}
                                                            /
                                                            {{
                                                                getSubjectQuarterGrade(
                                                                    subjectGrade,
                                                                    semester.key,
                                                                    quarter.key,
                                                                )!.maxScore
                                                            }}
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex items-center justify-center gap-1 text-muted-foreground"
                                                    >
                                                        <Clock
                                                            class="h-4 w-4"
                                                        />
                                                        <span class="text-sm"
                                                            >Pending</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="p-3 text-center">
                                                    <div
                                                        v-if="
                                                            getSubjectFinalGrade(
                                                                subjectGrade,
                                                                semester.key,
                                                            ) !== null
                                                        "
                                                        class="flex flex-col items-center"
                                                    >
                                                        <div
                                                            class="text-lg font-bold"
                                                            :class="
                                                                gradeColor(
                                                                    getSubjectFinalGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                    ),
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                formatGrade(
                                                                    getSubjectFinalGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                    ),
                                                                )
                                                            }}
                                                        </div>
                                                        <Progress
                                                            :value="
                                                                getSubjectFinalGrade(
                                                                    subjectGrade,
                                                                    semester.key,
                                                                ) ?? undefined
                                                            "
                                                            class="mt-1 h-1.5 w-14"
                                                            :indicator-class="
                                                                progressColor(
                                                                    getSubjectFinalGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                    ),
                                                                )
                                                            "
                                                        />
                                                        <div
                                                            class="mt-1 text-[10px] text-muted-foreground"
                                                        >
                                                            {{
                                                                gradeLabel(
                                                                    getSubjectFinalGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                    ),
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex items-center justify-center gap-1 text-muted-foreground"
                                                    >
                                                        <Clock
                                                            class="h-4 w-4"
                                                        />
                                                        <span class="text-sm"
                                                            >Pending</span
                                                        >
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Overall Final Grade summary -->
                                <div
                                    class="mt-4 rounded-lg border bg-muted/20 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-sm font-semibold tracking-wider uppercase"
                                        >
                                            Overall {{ group.finalGradeLabel }}
                                        </span>
                                        <div
                                            v-if="group.subjects.length > 0"
                                            class="flex items-center gap-4"
                                        >
                                            <div
                                                v-for="subjectGrade in group.subjects"
                                                :key="subjectGrade.subject"
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ subjectGrade.subject }}:
                                                </span>
                                                <span
                                                    v-if="
                                                        subjectGrade.semesterGrade !==
                                                        null
                                                    "
                                                    class="text-sm font-bold"
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
                                                </span>
                                                <span
                                                    v-else
                                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    Pending
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- College: Collapsible Period Sections -->
                            <div v-else class="space-y-3">
                                <div
                                    v-for="(period, pIdx) in group.periods"
                                    :key="period.key"
                                    class="overflow-hidden rounded-lg border transition-shadow duration-200 hover:shadow-sm"
                                >
                                    <button
                                        @click="togglePeriod(period.key)"
                                        :aria-expanded="
                                            expandedPeriods.includes(period.key)
                                        "
                                        class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/30"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-sm font-bold text-primary"
                                            >
                                                {{ pIdx + 1 }}
                                            </div>
                                            <div>
                                                <div class="font-semibold">
                                                    {{ period.label }}
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        gradedCount(
                                                            group.subjects,
                                                            period.key,
                                                        )
                                                    }}
                                                    /
                                                    {{ group.subjects.length }}
                                                    graded
                                                    <span
                                                        v-if="
                                                            expandedPeriods.includes(
                                                                period.key,
                                                            ) &&
                                                            group.subjects
                                                                .length >
                                                                MAX_VISIBLE_ROWS
                                                        "
                                                        class="ml-1.5 inline-flex items-center rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                                    >
                                                        +{{
                                                            group.subjects
                                                                .length -
                                                            MAX_VISIBLE_ROWS
                                                        }}
                                                        more
                                                    </span>
                                                    <Transition name="fade">
                                                        <span
                                                            v-if="
                                                                scrollHints[
                                                                    period.key
                                                                ] &&
                                                                group.subjects
                                                                    .length >
                                                                    MAX_VISIBLE_ROWS
                                                            "
                                                            class="ml-1.5 inline-flex animate-bounce items-center gap-0.5 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                                                        >
                                                            <ChevronDown
                                                                class="h-3 w-3"
                                                            />
                                                            Scroll
                                                        </span>
                                                    </Transition>
                                                </div>
                                            </div>
                                        </div>
                                        <ChevronDown
                                            class="h-5 w-5 text-muted-foreground transition-transform duration-200"
                                            :class="
                                                expandedPeriods.includes(
                                                    period.key,
                                                )
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                        />
                                    </button>
                                    <div
                                        :data-period-key="period.key"
                                        :class="[
                                            'transition-all duration-300 ease-out',
                                            expandedPeriods.includes(period.key)
                                                ? 'grades-period-scroll relative overflow-y-auto border-t opacity-100'
                                                : 'overflow-hidden border-t-0 opacity-0',
                                        ]"
                                        :style="{
                                            maxHeight: expandedPeriods.includes(
                                                period.key,
                                            )
                                                ? periodDesktopHeight(
                                                      group.subjects.length,
                                                  ) + 'px'
                                                : '0px',
                                        }"
                                    >
                                        <table class="w-full">
                                            <thead>
                                                <tr
                                                    class="border-b bg-muted/30"
                                                >
                                                    <th
                                                        class="p-3 text-left text-sm font-semibold"
                                                    >
                                                        Subject
                                                    </th>
                                                    <th
                                                        class="p-3 text-center text-sm font-semibold"
                                                    >
                                                        Score
                                                    </th>
                                                    <th
                                                        class="p-3 text-center text-sm font-semibold"
                                                    >
                                                        Percentage
                                                    </th>
                                                    <th
                                                        class="p-3 text-center text-sm font-semibold"
                                                    >
                                                        Remarks
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="subjectGrade in group.subjects"
                                                    :key="subjectGrade.subject"
                                                    class="border-b last:border-b-0 hover:bg-muted/50"
                                                >
                                                    <td
                                                        class="p-3 text-sm font-medium"
                                                    >
                                                        {{
                                                            subjectGrade.subject
                                                        }}
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <span
                                                            v-if="
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )
                                                            "
                                                            class="font-medium tabular-nums"
                                                        >
                                                            {{
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )?.score
                                                            }}
                                                            /
                                                            {{
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )?.maxScore
                                                            }}
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="flex items-center justify-center gap-1 text-muted-foreground"
                                                        >
                                                            <Clock
                                                                class="h-3 w-3"
                                                            />
                                                            <span
                                                                class="text-xs"
                                                                >Pending</span
                                                            >
                                                        </span>
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <div
                                                            v-if="
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )
                                                            "
                                                            class="flex flex-col items-center gap-1"
                                                        >
                                                            <span
                                                                class="text-base font-bold tabular-nums"
                                                                :class="
                                                                    gradeColor(
                                                                        getPeriodGrade(
                                                                            subjectGrade,
                                                                            period.key,
                                                                        )!
                                                                            .percentage,
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    getPeriodGrade(
                                                                        subjectGrade,
                                                                        period.key,
                                                                    )!
                                                                        .percentage
                                                                }}
                                                            </span>
                                                            <Progress
                                                                :value="
                                                                    getPeriodGrade(
                                                                        subjectGrade,
                                                                        period.key,
                                                                    )!
                                                                        .percentage
                                                                "
                                                                class="h-1 w-14"
                                                                :indicator-class="
                                                                    progressColor(
                                                                        getPeriodGrade(
                                                                            subjectGrade,
                                                                            period.key,
                                                                        )!
                                                                            .percentage,
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                        <span
                                                            v-else
                                                            class="flex items-center justify-center gap-1 text-muted-foreground"
                                                        >
                                                            <Clock
                                                                class="h-3 w-3"
                                                            />
                                                            <span
                                                                class="text-xs"
                                                                >Pending</span
                                                            >
                                                        </span>
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <span
                                                            v-if="
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )?.remarks
                                                            "
                                                            class="text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )?.remarks
                                                            }}
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="text-xs text-muted-foreground"
                                                            >—</span
                                                        >
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div
                                            class="pointer-events-none sticky bottom-0 h-6 bg-gradient-to-t from-card to-transparent"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Overall Semester Grade summary -->
                                <div class="rounded-lg border bg-muted/20 p-4">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-sm font-semibold tracking-wider uppercase"
                                        >
                                            {{ group.finalGradeLabel }}
                                        </span>
                                        <div
                                            v-if="group.subjects.length > 0"
                                            class="flex flex-wrap items-center gap-x-4 gap-y-2"
                                        >
                                            <div
                                                v-for="subjectGrade in group.subjects"
                                                :key="subjectGrade.subject"
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ subjectGrade.subject }}:
                                                </span>
                                                <span
                                                    v-if="
                                                        subjectGrade.semesterGrade !==
                                                        null
                                                    "
                                                    class="text-sm font-bold tabular-nums"
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
                                                </span>
                                                <span
                                                    v-else
                                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    Pending
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Thin scroll indicator for expanded period tables */
.grades-period-scroll {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--border)) transparent;
}
.grades-period-scroll::-webkit-scrollbar {
    width: 4px;
}
.grades-period-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.grades-period-scroll::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 4px;
}
.grades-period-scroll::-webkit-scrollbar-thumb:hover {
    background: hsl(var(--muted-foreground));
}
</style>
