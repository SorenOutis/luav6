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
import FoxCompanion from '@/components/FoxCompanion.vue';
import GradeDistributionChart from '@/components/GradeDistributionChart.vue';
import MascotEmptyState from '@/components/MascotEmptyState.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
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
import type { TourStep } from '@/lib/onboarding';
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
    gradedPeriods?: number;
    totalPeriods?: number;
    isComplete?: boolean;
    currentAverage?: number | null;
    semesterGrade: number | null;
}

const props = defineProps<{
    subjectGrades?: SubjectGrade[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Grades', href: '/grades' },
];

// ─── Onboarding tour ────────────────────────────────────────────────────────
// Per user + per device (localStorage). Waits until grades finish loading so
// the overview cards / tables exist; steps whose target is missing (e.g. no
// grades yet) are skipped automatically.
const gradesTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Welcome to your Grades',
        body: 'Track your academic performance across every enrolled subject — quarter by quarter. Here’s a quick tour.',
    },
    {
        id: 'search',
        target: 'grades-search',
        title: 'Search your subjects',
        body: 'Type a subject name to filter the grade tables instantly.',
    },
    {
        id: 'export',
        target: 'grades-export',
        title: 'Export as PDF',
        body: 'Need a copy for your records or parents? Export your full grade report as a PDF anytime.',
    },
    {
        id: 'overview',
        target: 'grades-overview',
        title: 'Your overview',
        body: 'Overall average, subject count, completion and grade distribution — the big picture at a glance.',
    },
    {
        id: 'tables',
        target: 'grades-table',
        title: 'Detailed grade tables',
        body: 'Each subject shows per-quarter scores, remarks and final grades. Grades update as soon as your teacher posts them.',
    },
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
if (typeof document !== 'undefined') {
    useEventListener(document, 'visibilitychange', () => {
        if (!document.hidden) {
            fetchGrades();
        }
    });
}

// ── Search / Filter ──────────────────────────────────────────────
const searchQuery = ref('');
const hasSearchQuery = computed(() => Boolean(searchQuery.value.trim()));

const filteredSubjectGrades = computed(() => {
    const grades = subjectGrades.value ?? [];
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return grades;

    return grades.filter((subject) =>
        subject.subject.toLowerCase().includes(query),
    );
});

// ── Computed Values ──────────────────────────────────────────────
// Summary cards always describe the complete gradebook, even while the table is
// filtered. Older cached payloads do not contain the new progress fields, so the
// fallbacks keep navigation safe while the background refresh completes.
const allSubjectGrades = computed(() => subjectGrades.value ?? []);

type SubjectStanding = {
    readonly isComplete?: boolean;
    readonly currentAverage?: number | null;
    readonly semesterGrade: number | null;
};

const isSubjectComplete = (subject: SubjectStanding): boolean =>
    subject.isComplete ?? subject.semesterGrade !== null;

const currentGrade = (subject: SubjectStanding): number | null =>
    subject.currentAverage ?? subject.semesterGrade;

const averageSemesterGrade = computed<number | null>(() => {
    const validGrades = allSubjectGrades.value
        .map(currentGrade)
        .filter((grade): grade is number => grade !== null);

    if (validGrades.length === 0) return null;

    return Math.round(
        validGrades.reduce((sum, grade) => sum + grade, 0) / validGrades.length,
    );
});

const completedCount = computed(
    () => allSubjectGrades.value.filter(isSubjectComplete).length,
);

const totalSubjectCount = computed(() => allSubjectGrades.value.length);

const latestGradeUpdate = computed(() => {
    const updates = allSubjectGrades.value
        .flatMap((subject) => subject.periodGrades)
        .map((period) => period.grade?.updatedAt)
        .filter((date): date is string => Boolean(date))
        .sort((a, b) => Date.parse(b) - Date.parse(a));

    return updates[0] ?? null;
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
    const grades = allSubjectGrades.value
        .map(currentGrade)
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
                color: '#059669',
                textColor: 'text-emerald-700 dark:text-emerald-400',
            },
            {
                label: 'Good (70-84)',
                count: good,
                color: '#EA580C',
                textColor: 'text-orange-700 dark:text-orange-400',
            },
            {
                label: 'Satisfactory (60-69)',
                count: satisfactory,
                color: '#D97706',
                textColor: 'text-amber-700 dark:text-amber-400',
            },
            {
                label: 'Needs Improvement (<60)',
                count: needsImprovement,
                color: '#DC2626',
                textColor: 'text-red-700 dark:text-red-400',
            },
        ],
        total: grades.length,
    };
});

// ── Styling helpers ──────────────────────────────────────────────
const gradeColor = (percentage: number | null) => {
    if (percentage === null) return 'text-muted-foreground';
    if (percentage >= 85) return 'text-emerald-700 dark:text-emerald-400';
    if (percentage >= 70) return 'text-orange-700 dark:text-orange-400';
    if (percentage >= 60) return 'text-amber-700 dark:text-amber-400';
    return 'text-red-700 dark:text-red-400';
};

const progressColor = (percentage: number | null) => {
    if (percentage === null) return 'bg-muted';
    if (percentage >= 85) return 'bg-emerald-600 dark:bg-emerald-400';
    if (percentage >= 70) return 'bg-orange-600 dark:bg-orange-400';
    if (percentage >= 60) return 'bg-amber-600 dark:bg-amber-400';
    return 'bg-red-600 dark:bg-red-400';
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

/**
 * Display a raw score without trailing float noise: "90.00" → "90",
 * "89.50" → "89.5". Display-only; grading math is untouched.
 */
const formatScore = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined || value === '') return '';

    const num = typeof value === 'number' ? value : Number.parseFloat(value);
    if (!Number.isFinite(num)) return String(value);

    // parseFloat already drops trailing float noise ("90.00" → 90).
    return String(num);
};

// ── Collapsible mobile subjects (per-subject accordion) ────────────
// One tap expands a whole subject instead of one period at a time. Grading
// math is untouched — this only controls which subject cards are open.
const STORAGE_KEY = 'grades-expanded-subjects';

const readStoredSubjects = (): string[] => {
    if (typeof window === 'undefined') return [];

    try {
        const stored = JSON.parse(
            window.sessionStorage.getItem(STORAGE_KEY) ?? '[]',
        );
        if (Array.isArray(stored)) return stored as string[];
        return [];
    } catch {
        return [];
    }
};

type SubjectIdentity = Pick<SubjectGrade, 'section' | 'subject'>;

const subjectKey = (subject: SubjectIdentity): string =>
    `s-${subject.section?.id ?? subject.subject}`;

const expandedSubjects = ref<string[]>(readStoredSubjects());

watch(expandedSubjects, (keys) => {
    if (typeof window === 'undefined') return;

    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
});

// First visit: expand the first subject so mobile is not a wall of
// collapsed cards. Once the student interacts, their choice wins.
const hasInitializedDefaultSubject = ref(expandedSubjects.value.length > 0);

watch(
    allSubjectGrades,
    (subjects) => {
        if (hasInitializedDefaultSubject.value) return;
        if (subjects.length === 0) return;

        const first = subjects[0];
        if (first) {
            expandedSubjects.value = [subjectKey(first)];
        }
        hasInitializedDefaultSubject.value = true;
    },
    { immediate: true },
);

const toggleSubject = (subject: SubjectIdentity) => {
    const key = subjectKey(subject);
    expandedSubjects.value = expandedSubjects.value.includes(key)
        ? expandedSubjects.value.filter((storedKey) => storedKey !== key)
        : [...expandedSubjects.value, key];
};

const isSubjectExpanded = (subject: SubjectIdentity): boolean =>
    expandedSubjects.value.includes(subjectKey(subject));

const subjectKeysForGroup = (group: {
    subjects: SubjectIdentity[];
}): string[] => group.subjects.map(subjectKey);

// Searching opens matching subject cards but clearing the query restores the
// student's previous accordion state rather than unexpectedly erasing it.
let expandedBeforeSearch: string[] | null = null;
watch(searchQuery, (query, previousQuery) => {
    const hasQuery = Boolean(query.trim());
    const hadQuery = Boolean(previousQuery.trim());

    if (hasQuery && !hadQuery) {
        expandedBeforeSearch = [...expandedSubjects.value];
        const searchKeys = gradeGroups.value.flatMap(subjectKeysForGroup);
        expandedSubjects.value = Array.from(
            new Set([...expandedSubjects.value, ...searchKeys]),
        );
    } else if (!hasQuery && hadQuery && expandedBeforeSearch) {
        expandedSubjects.value = expandedBeforeSearch;
        expandedBeforeSearch = null;
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

const isGroupAllExpanded = (group: { subjects: SubjectGrade[] }): boolean => {
    if (group.subjects.length === 0) return false;
    return subjectKeysForGroup(group).every((key) =>
        expandedSubjects.value.includes(key),
    );
};

const toggleAllSubjects = (group: { subjects: SubjectGrade[] }): void => {
    const allKeys = subjectKeysForGroup(group);

    if (isGroupAllExpanded(group)) {
        expandedSubjects.value = expandedSubjects.value.filter(
            (key) => !allKeys.includes(key),
        );
        return;
    }

    expandedSubjects.value = Array.from(
        new Set([...expandedSubjects.value, ...allKeys]),
    );
};

// ── PDF Export ───────────────────────────────────────────────────
const gradesContainer = ref<HTMLElement | null>(null);
const isExporting = ref(false);
const exportError = ref<string | null>(null);

const exportPdf = async () => {
    if (!gradesContainer.value) return;

    const element = gradesContainer.value;
    const previousQuery = searchQuery.value;
    const previousExpandedSubjects = [...expandedSubjects.value];
    const previousWidth = element.style.width;
    const previousMaxWidth = element.style.maxWidth;

    isExporting.value = true;
    exportError.value = null;

    try {
        // Export is intentionally independent of the student's current search,
        // accordion state, and viewport. The PDF always contains every subject
        // in the complete desktop comparison layout.
        searchQuery.value = '';
        element.classList.add('grades-export-mode');
        element.style.width = '1024px';
        element.style.maxWidth = '1024px';
        await nextTick();

        const html2pdf = (await import('html2pdf.js')).default;
        await html2pdf()
            .set({
                margin: [0.35, 0.35, 0.35, 0.35],
                filename: `my-grades-${new Date().toISOString().slice(0, 10)}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    windowWidth: 1200,
                },
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'landscape',
                },
            })
            .from(element)
            .save();
    } catch (err) {
        console.error('PDF export failed:', err);
        exportError.value =
            'We could not create your PDF. Please try again in a moment.';
    } finally {
        element.classList.remove('grades-export-mode');
        element.style.width = previousWidth;
        element.style.maxWidth = previousMaxWidth;
        searchQuery.value = previousQuery;
        expandedSubjects.value = previousExpandedSubjects;
        isExporting.value = false;
    }
};

// ── Animations ───────────────────────────────────────────────────
let animationContext: ReturnType<typeof gsap.context> | null = null;

onBeforeUnmount(() => animationContext?.revert());

onMounted(() => {
    // Keep the page still for students who request reduced motion.
    if (
        !gradesContainer.value ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    )
        return;

    animationContext = gsap.context(() => {
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
    }, gradesContainer.value);
});
</script>

<template>
    <Head title="My Grades" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="gradesContainer"
            class="student-ui mobile-ui-page container mx-auto max-w-[1600px] px-3 py-3 perspective-[1000px] sm:px-6 sm:py-6 lg:px-8 lg:py-8"
        >
            <section
                class="mobile-grades-intro md:hidden"
                aria-label="Grades overview"
            >
                <div class="mobile-grades-intro__topline">
                    <FoxCompanion
                        v-if="filteredSubjectGrades.length > 0"
                        class="min-w-0 flex-1"
                        mascot="grades"
                        :size="68"
                        message="Progress is built one result at a time."
                        label="Show grades fox message"
                        compact
                        :initially-open="false"
                    >
                        <div class="min-w-0">
                            <span class="mobile-dashboard-kicker"
                                >Your progress</span
                            >
                            <h1 class="mobile-dashboard-title">Grades</h1>
                        </div>
                    </FoxCompanion>
                    <div v-else>
                        <span class="mobile-dashboard-kicker"
                            >Your progress</span
                        >
                        <h1 class="mobile-dashboard-title">Grades</h1>
                    </div>
                    <button
                        type="button"
                        class="mobile-grades-export shrink-0"
                        :disabled="isExporting"
                        @click="exportPdf"
                    >
                        <Loader2
                            v-if="isExporting"
                            class="h-4 w-4 animate-spin"
                        />
                        <Printer v-else class="h-4 w-4" />
                        Export
                    </button>
                </div>
                <p class="mobile-grades-intro__copy">
                    See your current averages and what needs attention.
                </p>
                <label class="mobile-grades-search">
                    <Search class="h-4 w-4" />
                    <span class="sr-only">Search subjects</span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search subjects"
                    />
                </label>
            </section>

            <!-- Header -->
            <div
                class="mobile-existing-header grades-desktop-header animate-section mb-4 hidden flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between sm:gap-4 md:flex"
            >
                <FoxCompanion
                    v-if="filteredSubjectGrades.length > 0"
                    class="min-w-0 flex-1"
                    mascot="grades"
                    :size="96"
                    message="Progress is built one result at a time."
                    label="Show grades fox message"
                    compact
                    :initially-open="false"
                >
                    <div class="min-w-0">
                        <h1
                            class="dash-title text-[22px] text-foreground sm:text-[34px]"
                        >
                            Grades
                        </h1>
                        <p
                            class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                        >
                            Your academic performance across enrolled subjects.
                        </p>
                    </div>
                </FoxCompanion>
                <div v-else class="min-w-0 flex-1">
                    <h1
                        class="dash-title text-[22px] text-foreground sm:text-[34px]"
                    >
                        Grades
                    </h1>
                    <p
                        class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                    >
                        Your academic performance across enrolled subjects.
                    </p>
                </div>
                <div
                    class="flex w-full flex-row items-center gap-2 sm:w-auto sm:gap-3"
                    data-html2canvas-ignore="true"
                >
                    <div
                        class="relative min-w-0 flex-1 sm:w-56 sm:flex-none lg:w-72"
                        data-tour="grades-search"
                    >
                        <label for="grade-subject-search" class="sr-only">
                            Search subjects
                        </label>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="grade-subject-search"
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search subjects"
                            class="pl-10"
                        />
                    </div>
                    <button
                        type="button"
                        data-tour="grades-export"
                        class="dash-btn inline-flex h-11 shrink-0 items-center justify-center gap-1.5 border border-border/60 bg-card px-3 text-[13px] text-foreground transition-colors hover:bg-muted disabled:cursor-wait disabled:opacity-60 sm:px-4 sm:text-[15px]"
                        :disabled="isExporting"
                        :aria-busy="isExporting"
                        :aria-label="
                            isExporting
                                ? 'Exporting grades'
                                : 'Export grades as PDF'
                        "
                        @click="exportPdf"
                    >
                        <Loader2
                            v-if="isExporting"
                            class="h-4 w-4 animate-spin"
                            aria-hidden="true"
                        />
                        <Printer v-else class="h-4 w-4" aria-hidden="true" />
                        <span class="hidden min-[380px]:inline">
                            {{ isExporting ? 'Exporting…' : 'Export PDF' }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                v-if="exportError"
                role="alert"
                class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                data-html2canvas-ignore="true"
            >
                <span>{{ exportError }}</span>
                <button
                    type="button"
                    class="min-h-11 shrink-0 font-semibold"
                    @click="exportError = null"
                >
                    Dismiss
                </button>
            </div>

            <!-- Loading state -->
            <div
                v-if="isLoading"
                class="animate-section mb-8 flex flex-col items-center justify-center py-16"
            >
                <Loader2
                    class="mb-4 h-8 w-8 animate-spin text-orange-600 dark:text-orange-400"
                />
                <p class="text-[15px] text-muted-foreground">
                    Loading your grades…
                </p>
            </div>

            <!-- Error state -->
            <div v-else-if="fetchError" class="animate-section mb-8">
                <Card class="border-destructive/30">
                    <CardContent
                        class="flex flex-col items-center justify-center py-12"
                    >
                        <AlertCircle class="mb-4 h-12 w-12 text-destructive" />
                        <h3 class="text-[17px] font-semibold tracking-tight">
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
                            class="dash-btn mt-4 px-5"
                            @click="fetchGrades"
                        >
                            <RefreshCw class="h-4 w-4" />
                            Try Again
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Overview strip: desktop-only summary (hidden on phones) -->
            <div
                v-show="!isLoading && !fetchError && totalSubjectCount > 0"
                data-tour="grades-overview"
                class="grades-desktop-overview grades-overview-strip mb-4 sm:mb-6"
            >
                <Card class="animate-card py-3 sm:py-4">
                    <CardContent class="px-3 sm:px-6">
                        <div
                            class="grid grid-cols-2 gap-x-3 gap-y-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-center md:gap-6"
                        >
                            <div class="min-w-0">
                                <p class="dash-label flex items-center gap-1.5">
                                    <TrendingUp
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    Overall average
                                </p>
                                <p
                                    class="dash-metric mt-1 text-[26px] leading-none sm:text-[32px]"
                                    :class="gradeColor(averageSemesterGrade)"
                                >
                                    {{
                                        averageSemesterGrade === null
                                            ? '—'
                                            : formatGrade(averageSemesterGrade)
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-[12px] text-muted-foreground sm:text-[13px]"
                                >
                                    {{
                                        averageSemesterGrade === null
                                            ? 'Awaiting grades'
                                            : gradeLabel(averageSemesterGrade)
                                    }}
                                </p>
                            </div>

                            <div class="min-w-0">
                                <p class="dash-label flex items-center gap-1.5">
                                    <BookOpen
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    Subjects
                                </p>
                                <p
                                    class="dash-metric mt-1 text-[26px] leading-none sm:text-[32px]"
                                >
                                    {{ totalSubjectCount }}
                                </p>
                                <p
                                    class="mt-1 text-[12px] text-muted-foreground sm:text-[13px]"
                                >
                                    Enrolled this term
                                </p>
                            </div>

                            <div class="min-w-0">
                                <p class="dash-label flex items-center gap-1.5">
                                    <GraduationCap
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    Completed
                                </p>
                                <p
                                    class="dash-metric mt-1 text-[26px] leading-none sm:text-[32px]"
                                >
                                    {{ completedCount
                                    }}<span
                                        class="text-[15px] font-medium text-muted-foreground"
                                        >/{{ totalSubjectCount }}</span
                                    >
                                </p>
                                <Progress
                                    :value="
                                        totalSubjectCount > 0
                                            ? (completedCount /
                                                  totalSubjectCount) *
                                              100
                                            : 0
                                    "
                                    class="mt-2 h-1.5 w-full max-w-32"
                                />
                                <p
                                    class="mt-1 text-[12px] text-muted-foreground sm:text-[13px]"
                                >
                                    With a final grade
                                </p>
                            </div>

                            <div
                                class="col-span-2 border-t border-border/60 pt-3 md:col-span-1 md:border-t-0 md:border-l md:pt-0 md:pl-6"
                            >
                                <p
                                    class="dash-label mb-2 flex items-center gap-1.5"
                                >
                                    <BarChart3
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    Distribution
                                </p>
                                <GradeDistributionChart
                                    :segments="distributionData.segments"
                                    :total="distributionData.total"
                                    compact
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div
                v-if="!isLoading && !fetchError && totalSubjectCount > 0"
                class="animate-section mb-4 flex flex-col gap-1 rounded-xl border border-border/60 bg-muted/20 px-4 py-2.5 text-[12px] leading-5 text-muted-foreground sm:mb-6 sm:flex-row sm:items-center sm:justify-between sm:text-[13px]"
            >
                <p class="hidden md:block">
                    Current averages use the simple mean of available periods.
                </p>
                <p class="flex shrink-0 items-center gap-1.5">
                    <Clock class="h-3.5 w-3.5" aria-hidden="true" />
                    <span class="sm:hidden"
                        >{{ totalSubjectCount }} subjects ·
                    </span>
                    {{
                        latestGradeUpdate
                            ? `Last updated ${latestGradeUpdate}`
                            : 'No grades posted yet'
                    }}
                </p>
            </div>

            <!-- Empty State -->
            <Card
                v-if="
                    !isLoading &&
                    filteredSubjectGrades.length === 0 &&
                    !hasSearchQuery
                "
                class="animate-section border-dashed"
            >
                <CardContent class="flex justify-center">
                    <MascotEmptyState
                        mascot="grades"
                        title="No grades posted yet"
                        description="You're not enrolled in any sections yet, or your instructor hasn't posted grades. Check back soon — your results will appear here."
                    />
                </CardContent>
            </Card>

            <!-- No results state -->
            <Card
                v-else-if="filteredSubjectGrades.length === 0 && hasSearchQuery"
                class="animate-group border-dashed"
            >
                <CardContent class="flex justify-center">
                    <MascotEmptyState
                        mascot="grades"
                        :size="108"
                        bare
                        title="No subjects found"
                        :description="`No subjects match '${searchQuery.trim()}'. Try a different search term.`"
                    />
                </CardContent>
            </Card>

            <!-- Grades Tables -->
            <template v-else>
                <Card
                    v-for="(group, groupIdx) in gradeGroups"
                    :key="group.key"
                    :data-tour="groupIdx === 0 ? 'grades-table' : undefined"
                    class="animate-group mb-4 sm:mb-6"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <GraduationCap
                                        class="h-5 w-5 text-primary"
                                    />
                                    {{ group.label }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        hasSearchQuery
                                            ? `Showing ${filteredSubjectGrades.length} of ${totalSubjectCount} subjects`
                                            : 'Grades by subject and period'
                                    }}
                                </CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="dash-btn min-h-11 shrink-0 px-4 md:hidden"
                                @click="toggleAllSubjects(group)"
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
                                                ? 'Collapse all'
                                                : 'Expand all'
                                        }}
                                    </span>
                                </Transition>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- ===== MOBILE: Subject cards (per-subject accordion) ===== -->
                        <div class="grades-mobile-layout space-y-2 md:hidden">
                            <Card
                                v-for="subjectGrade in group.subjects"
                                :key="
                                    subjectGrade.section?.id ??
                                    subjectGrade.subject
                                "
                                class="grades-subject-card overflow-hidden border shadow-none"
                            >
                                <button
                                    type="button"
                                    :aria-expanded="
                                        isSubjectExpanded(subjectGrade)
                                    "
                                    :aria-label="`${isSubjectExpanded(subjectGrade) ? 'Collapse' : 'Expand'} ${subjectGrade.subject} grades`"
                                    class="flex min-h-11 w-full items-center gap-3 bg-muted/30 px-4 py-3 text-left transition-colors hover:bg-muted/50"
                                    @click="toggleSubject(subjectGrade)"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm leading-5 font-semibold"
                                        >
                                            {{ subjectGrade.subject }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="
                                                    (subjectGrade.gradedPeriods ??
                                                        0) > 0
                                                "
                                            >
                                                {{
                                                    subjectGrade.gradedPeriods
                                                }}/{{
                                                    subjectGrade.totalPeriods ??
                                                    group.periods.length
                                                }}
                                                graded ·
                                                {{
                                                    gradeLabel(
                                                        currentGrade(
                                                            subjectGrade,
                                                        ),
                                                    )
                                                }}
                                            </template>
                                            <template v-else>
                                                No grades yet · Pending
                                            </template>
                                        </span>
                                    </span>
                                    <span
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <span
                                            v-if="
                                                currentGrade(subjectGrade) !==
                                                null
                                            "
                                            class="text-[17px] font-semibold tabular-nums"
                                            :class="
                                                gradeColor(
                                                    currentGrade(subjectGrade),
                                                )
                                            "
                                        >
                                            {{
                                                formatGrade(
                                                    currentGrade(subjectGrade),
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <Clock
                                                class="h-3.5 w-3.5"
                                                aria-hidden="true"
                                            />
                                            —
                                        </span>
                                        <ChevronDown
                                            class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                            :class="
                                                isSubjectExpanded(subjectGrade)
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                            aria-hidden="true"
                                        />
                                    </span>
                                </button>
                                <div
                                    v-show="isSubjectExpanded(subjectGrade)"
                                    class="grades-subject-body border-t"
                                >
                                    <!-- Senior High: semesters with quarters -->
                                    <template v-if="group.isSeniorHigh">
                                        <div
                                            v-for="semester in subjectGrade.semesterGrades"
                                            :key="semester.key"
                                            class="px-4 py-3"
                                        >
                                            <div
                                                class="mb-2 text-[13px] font-medium text-muted-foreground"
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
                                                    class="flex flex-1 items-center justify-end gap-2"
                                                >
                                                    <span
                                                        class="text-right text-[15px] font-semibold tabular-nums"
                                                    >
                                                        {{
                                                            formatScore(
                                                                quarter.grade
                                                                    .score,
                                                            )
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
                                                    class="font-semibold"
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

                                    <!-- College: flat period rows (subject expands as a whole) -->
                                    <template v-else>
                                        <div class="divide-y">
                                            <div
                                                v-for="(
                                                    period, pIdx
                                                ) in group.periods"
                                                :key="period.key"
                                                class="px-4 py-3"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-[13px] font-semibold text-orange-700 dark:bg-orange-950/60 dark:text-orange-400"
                                                    >
                                                        {{ pIdx + 1 }}
                                                    </div>
                                                    <span
                                                        class="min-w-0 flex-1 truncate text-sm font-medium"
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
                                                        class="shrink-0 text-[15px] font-semibold tabular-nums"
                                                        >{{
                                                            formatScore(
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )!.score,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="shrink-0 text-[13px] text-muted-foreground"
                                                        >Pending</span
                                                    >
                                                </div>
                                                <div
                                                    v-if="
                                                        getPeriodGrade(
                                                            subjectGrade,
                                                            period.key,
                                                        )?.remarks
                                                    "
                                                    class="mt-1 pl-11 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        getPeriodGrade(
                                                            subjectGrade,
                                                            period.key,
                                                        )!.remarks
                                                    }}
                                                </div>
                                                <div
                                                    v-else-if="
                                                        !getPeriodGrade(
                                                            subjectGrade,
                                                            period.key,
                                                        )
                                                    "
                                                    class="mt-1 flex items-center gap-1 pl-11 text-muted-foreground"
                                                >
                                                    <Clock class="h-3 w-3" />
                                                    <span class="text-xs"
                                                        >No grade yet</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Overall grade footer -->
                                    <div
                                        class="flex items-center justify-between bg-muted/20 px-4 py-3"
                                    >
                                        <span class="text-[13px] font-semibold">
                                            {{
                                                isSubjectComplete(subjectGrade)
                                                    ? group.finalGradeLabel
                                                    : 'Current average'
                                            }}
                                        </span>
                                        <div
                                            v-if="
                                                currentGrade(subjectGrade) !==
                                                null
                                            "
                                            class="flex items-center gap-2"
                                        >
                                            <Progress
                                                :value="
                                                    currentGrade(
                                                        subjectGrade,
                                                    ) ?? undefined
                                                "
                                                class="h-2 w-20"
                                                :indicator-class="
                                                    progressColor(
                                                        currentGrade(
                                                            subjectGrade,
                                                        ),
                                                    )
                                                "
                                            />
                                            <span
                                                class="text-[15px] font-semibold"
                                                :class="
                                                    gradeColor(
                                                        currentGrade(
                                                            subjectGrade,
                                                        ),
                                                    )
                                                "
                                            >
                                                {{
                                                    formatGrade(
                                                        currentGrade(
                                                            subjectGrade,
                                                        ),
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
                                </div>
                            </Card>
                        </div>

                        <!-- ===== DESKTOP: Table Layout ===== -->
                        <div
                            class="grades-desktop-layout hidden overflow-x-auto md:block"
                        >
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
                                            class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-1 text-[13px] font-medium text-orange-700 dark:bg-orange-950/60 dark:text-orange-400"
                                        >
                                            {{ semester.label }}
                                        </span>
                                        <div class="h-px flex-1 bg-border" />
                                    </div>
                                    <table class="w-full">
                                        <caption class="sr-only">
                                            {{
                                                semester.label
                                            }}
                                            grades by subject
                                        </caption>
                                        <thead>
                                            <tr class="border-b">
                                                <th
                                                    scope="col"
                                                    class="sticky left-0 z-10 bg-card p-3 text-left text-sm font-semibold"
                                                >
                                                    Subject
                                                </th>
                                                <th
                                                    v-for="quarter in semester.quarters"
                                                    :key="quarter.key"
                                                    scope="col"
                                                    class="p-3 text-center text-sm font-semibold"
                                                >
                                                    {{ quarter.label }}
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="p-3 text-center text-sm font-semibold"
                                                >
                                                    Final Grade
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="subjectGrade in group.subjects"
                                                :key="
                                                    subjectGrade.section?.id ??
                                                    subjectGrade.subject
                                                "
                                                class="border-b last:border-b-0 hover:bg-muted/50"
                                            >
                                                <th
                                                    scope="row"
                                                    class="sticky left-0 z-10 bg-card p-3 text-left text-sm font-medium hover:bg-muted/50"
                                                >
                                                    {{ subjectGrade.subject }}
                                                </th>
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
                                                        class="flex min-h-11 w-full flex-col items-center justify-center gap-1"
                                                    >
                                                        <div
                                                            class="text-[15px] font-semibold tabular-nums"
                                                        >
                                                            {{
                                                                formatScore(
                                                                    getSubjectQuarterGrade(
                                                                        subjectGrade,
                                                                        semester.key,
                                                                        quarter.key,
                                                                    )!.score,
                                                                )
                                                            }}
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="flex min-h-11 w-full items-center justify-center gap-1 text-muted-foreground"
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
                                                            class="text-[22px] font-semibold tabular-nums"
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
                                                            class="mt-1 text-[13px] text-muted-foreground"
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
                                            class="text-[15px] font-semibold tracking-tight"
                                        >
                                            Current and final standing
                                        </span>
                                        <div
                                            v-if="group.subjects.length > 0"
                                            class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2"
                                        >
                                            <div
                                                v-for="subjectGrade in group.subjects"
                                                :key="
                                                    subjectGrade.section?.id ??
                                                    subjectGrade.subject
                                                "
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ subjectGrade.subject }}:
                                                </span>
                                                <span
                                                    v-if="
                                                        currentGrade(
                                                            subjectGrade,
                                                        ) !== null
                                                    "
                                                    class="text-[15px] font-semibold"
                                                    :class="
                                                        gradeColor(
                                                            currentGrade(
                                                                subjectGrade,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatGrade(
                                                            currentGrade(
                                                                subjectGrade,
                                                            ),
                                                        )
                                                    }}
                                                    <span
                                                        class="ml-1 text-[11px] font-normal text-muted-foreground"
                                                    >
                                                        {{
                                                            isSubjectComplete(
                                                                subjectGrade,
                                                            )
                                                                ? 'final'
                                                                : 'current'
                                                        }}
                                                    </span>
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

                            <!-- College: subject-first comparison table -->
                            <div
                                v-else
                                class="overflow-hidden rounded-xl border"
                            >
                                <table class="w-full min-w-[760px]">
                                    <caption class="sr-only">
                                        College grades by subject and period
                                    </caption>
                                    <thead class="bg-muted/30">
                                        <tr class="border-b">
                                            <th
                                                scope="col"
                                                class="sticky left-0 z-20 min-w-48 bg-muted p-3 text-left text-sm font-semibold"
                                            >
                                                Subject
                                            </th>
                                            <th
                                                v-for="period in group.periods"
                                                :key="period.key"
                                                scope="col"
                                                class="min-w-36 p-3 text-center text-sm font-semibold"
                                            >
                                                {{ period.label }}
                                            </th>
                                            <th
                                                scope="col"
                                                class="sticky right-0 z-20 min-w-40 bg-muted p-3 text-center text-sm font-semibold"
                                            >
                                                Current / Final
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="subjectGrade in group.subjects"
                                            :key="
                                                subjectGrade.section?.id ??
                                                subjectGrade.subject
                                            "
                                            class="border-b last:border-b-0 hover:bg-muted/30"
                                        >
                                            <th
                                                scope="row"
                                                class="sticky left-0 z-10 bg-card p-3 text-left text-sm font-semibold"
                                            >
                                                {{ subjectGrade.subject }}
                                            </th>
                                            <td
                                                v-for="period in group.periods"
                                                :key="period.key"
                                                class="p-3 text-center align-top"
                                            >
                                                <div
                                                    v-if="
                                                        getPeriodGrade(
                                                            subjectGrade,
                                                            period.key,
                                                        )
                                                    "
                                                    class="flex min-h-11 w-full flex-col items-center justify-center gap-1"
                                                >
                                                    <span
                                                        class="text-[15px] font-semibold tabular-nums"
                                                    >
                                                        {{
                                                            formatScore(
                                                                getPeriodGrade(
                                                                    subjectGrade,
                                                                    period.key,
                                                                )!.score,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            getPeriodGrade(
                                                                subjectGrade,
                                                                period.key,
                                                            )?.remarks
                                                        "
                                                        class="max-w-32 truncate text-[12px] text-muted-foreground"
                                                        :title="
                                                            getPeriodGrade(
                                                                subjectGrade,
                                                                period.key,
                                                            )?.remarks ??
                                                            undefined
                                                        "
                                                    >
                                                        {{
                                                            getPeriodGrade(
                                                                subjectGrade,
                                                                period.key,
                                                            )?.remarks
                                                        }}
                                                    </span>
                                                </div>
                                                <span
                                                    v-else
                                                    class="flex min-h-11 w-full items-center justify-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock
                                                        class="h-3.5 w-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Pending
                                                </span>
                                            </td>
                                            <td
                                                class="sticky right-0 z-10 bg-card p-3 text-center align-top"
                                            >
                                                <div
                                                    v-if="
                                                        currentGrade(
                                                            subjectGrade,
                                                        ) !== null
                                                    "
                                                    class="flex flex-col items-center gap-1"
                                                >
                                                    <span
                                                        class="text-[20px] font-semibold tabular-nums"
                                                        :class="
                                                            gradeColor(
                                                                currentGrade(
                                                                    subjectGrade,
                                                                ),
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            formatGrade(
                                                                currentGrade(
                                                                    subjectGrade,
                                                                ),
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        class="text-[12px] text-muted-foreground"
                                                    >
                                                        {{
                                                            isSubjectComplete(
                                                                subjectGrade,
                                                            )
                                                                ? group.finalGradeLabel
                                                                : 'Current average'
                                                        }}
                                                    </span>
                                                </div>
                                                <span
                                                    v-else
                                                    class="inline-flex min-h-11 items-center gap-1 text-xs text-muted-foreground"
                                                >
                                                    <Clock
                                                        class="h-3.5 w-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Awaiting grades
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- First-visit walkthrough (per user, per device) -->
        <OnboardingTour
            tour-id="grades"
            :steps="gradesTourSteps"
            :can-start="!isLoading && !fetchError"
            :start-delay="900"
        />
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

/* PDF export always uses the complete desktop report — summary strip,
   comparison tables — regardless of the phone/desktop viewport or the
   student's current accordion state. */
.grades-export-mode .grades-mobile-layout {
    display: none !important;
}

.grades-export-mode .grades-overview-strip {
    display: block !important;
}

.grades-export-mode .grades-desktop-layout {
    display: block !important;
    overflow: visible !important;
}

.grades-export-mode .animate-section,
.grades-export-mode .animate-card,
.grades-export-mode .animate-group {
    opacity: 1 !important;
    transform: none !important;
}

.grades-export-mode [data-slot='card'],
.grades-export-mode table {
    break-inside: avoid;
}

@media (prefers-reduced-motion: reduce) {
    .student-ui *,
    .student-ui *::before,
    .student-ui *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
