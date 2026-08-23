<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import axios from 'axios';
import {
    Calendar,
    Clock,
    CheckCircle2,
    Search,
    Lock,
    BookOpen,
    ClipboardList,
    GraduationCap,
    ArrowRight,
    Timer,
    Layers,
    FileText,
    BookMarked,
    BarChart3,
    ChevronLeft,
    ChevronRight,
    Shield,
    XCircle,
    Zap,
    X,
} from 'lucide-vue-next';
import {
    ref,
    computed,
    watch,
    nextTick,
    onMounted,
    onBeforeUnmount,
} from 'vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { getLenis } from '@/composables/useLenis';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TourStep } from '@/lib/onboarding';
import { hasPageMountedBefore } from '@/lib/page-mount-state';
import { show as examsShow } from '@/routes/exams';
import type { BreadcrumbItem } from '@/types';

// ─── Types ──────────────────────────────────────────────────────────────────
interface ExamSubmission {
    id: number;
    exam_part_id: number;
    answers?: any[];
    status: string;
    score: string;
}
interface ExamPart {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    questions: any[] | null;
    points: number;
}
interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    duration_minutes: number;
    status: string;
    url: string | null;
    parts: ExamPart[];
    submitted_parts_count?: number;
    total_parts?: number;
    is_locked?: boolean;
    has_submissions?: boolean;
    results_available?: boolean;
    submissions?: ExamSubmission[];
    section_name?: string;
    season_name?: string;
    exam_date_iso?: string;
}
interface SeasonGroup {
    seasonName: string;
    exams: Exam[];
}
interface Assignment {
    id: number;
    title: string;
    description: string;
    due_date: string | null;
    due_date_iso: string | null;
    points_possible: number | string | null;
    group_rules: { min: number | null; max: number | null } | null;
    course: { id: number; name: string } | null;
    sections: { id: number; name: string }[];
    submission: {
        submitted: boolean;
        status: string;
        grade: string | null;
        file_url: string | null;
        submitted_at: string | null;
        points: number | string | null;
        xp_earned: number | string | null;
        feedback: string | null;
        graded_at: string | null;
        has_unseen_feedback?: boolean;
    } | null;
}
interface Course {
    id: number;
    name: string;
    description: string | null;
    cover_photo: string | null;
    totalLessons: number;
    completedLessons: number;
    progress: number;
    xpEarned: number;
    modulesCount: number;
}
interface UnifiedItem {
    kind: 'exam' | 'assignment' | 'course';
    id: number;
    title: string;
    description: string;
    due_at: string | null;
    section_name: string | null;
    season_name: string | null;
    is_completed: boolean;
    is_locked: boolean;
    status: string;
    meta: string | null;
    href: string;
    score?: number;
    points_possible?: any;
    submission?: any;
    progress?: number;
    cover_photo?: string | null;
}

const props = defineProps<{
    examsBySeason: SeasonGroup[];
    examPagination?: { hasMore: boolean; nextCursor: string | null };
    assignments: Assignment[];
    courses: Course[];
    sectionTabs: { key: string; label: string; count: number }[];
    unifiedTimeline: UnifiedItem[];
    hubStats: {
        total: number;
        pending: number;
        completed: number;
        exams: { total: number; pending: number; completed: number };
        assignments: { total: number; pending: number; completed: number };
        courses: { total: number; pending: number; completed: number };
    };
}>();

// ─── Polling + Visibility (reuse exam pattern) ──────────────────────────────
const { stop: stopPoll, start: startPoll } = usePoll(
    10000,
    {
        only: [
            'examsBySeason',
            'assignments',
            'courses',
            'unifiedTimeline',
            'hubStats',
        ],
    },
    { autoStart: false },
);
const refreshHub = () =>
    router.reload({
        only: [
            'examsBySeason',
            'assignments',
            'courses',
            'unifiedTimeline',
            'hubStats',
        ],
    });
const handleVisibilityChange = () => {
    if (!document.hidden) refreshHub();
};
onMounted(() => {
    startPoll();
    if (hasPageMountedBefore('activities') && !document.hidden) refreshHub();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});
onBeforeUnmount(() => {
    stopPoll();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

// ─── Local exam pagination state (for Exams tab load more) ──────────────────
const examGroups = ref<SeasonGroup[]>(
    props.examsBySeason.map((g) => ({ ...g, exams: [...g.exams] })),
);
const examCursor = ref<string | null>(props.examPagination?.nextCursor ?? null);
const hasLoadedMoreExams = ref(false);
const hasMoreExams = ref(props.examPagination?.hasMore ?? false);
const isLoadingMoreExams = ref(false);

const mergeExamGroups = (incoming: SeasonGroup[], prepend = false) => {
    const merged = new Map<string, Exam[]>();
    for (const group of examGroups.value)
        merged.set(group.seasonName, [...group.exams]);
    for (const group of incoming) {
        const existing = merged.get(group.seasonName) ?? [];
        const incomingIds = new Set(group.exams.map((e) => e.id));
        merged.set(
            group.seasonName,
            prepend
                ? [
                      ...group.exams,
                      ...existing.filter((e) => !incomingIds.has(e.id)),
                  ]
                : [
                      ...existing,
                      ...group.exams.filter(
                          (e) => !existing.some((c) => c.id === e.id),
                      ),
                  ],
        );
    }
    const preferredOrder = [
        ...incoming.map((g) => g.seasonName),
        ...examGroups.value.map((g) => g.seasonName),
    ];
    examGroups.value = [...new Set(preferredOrder)].map((name) => ({
        seasonName: name,
        exams: merged.get(name) ?? [],
    }));
};
watch(
    () => props.examsBySeason,
    (groups) => {
        if (!hasLoadedMoreExams.value) {
            examGroups.value = groups.map((g) => ({
                ...g,
                exams: [...g.exams],
            }));
            hasMoreExams.value = props.examPagination?.hasMore ?? false;
            examCursor.value = props.examPagination?.nextCursor ?? null;
        } else {
            mergeExamGroups(groups, true);
        }
    },
);
const loadMoreExams = async () => {
    if (!hasMoreExams.value || isLoadingMoreExams.value) return;
    isLoadingMoreExams.value = true;
    try {
        const res = await axios.get('/api/activities', {
            params: { cursor: examCursor.value },
        });
        // if response shape is examPage, use it
        if (res.data.data) {
            mergeExamGroups(res.data.data ?? []);
            examCursor.value = res.data.meta?.nextCursor ?? null;
            hasMoreExams.value = Boolean(res.data.meta?.hasMore);
        }
        hasLoadedMoreExams.value = true;
    } catch {
        // try legacy endpoint
        try {
            const res2 = await axios.get('/api/exams', {
                params: { cursor: examCursor.value },
            });
            mergeExamGroups(res2.data.data ?? []);
            hasLoadedMoreExams.value = true;
            examCursor.value = res2.data.meta?.nextCursor ?? null;
            hasMoreExams.value = Boolean(res2.data.meta?.hasMore);
        } catch (e) {
            console.error('Failed to load more exams', e);
        }
    } finally {
        isLoadingMoreExams.value = false;
    }
};

// ─── Filters ────────────────────────────────────────────────────────────────
const activeType = ref<'all' | 'exam' | 'assignment' | 'course'>('all');
const activeSection = ref('all');
const searchQuery = ref('');

const allExamsFlat = computed(() =>
    props.examsBySeason.flatMap((sg) => sg.exams),
);

const filteredUnified = computed(() => {
    let list = [...props.unifiedTimeline] as UnifiedItem[];
    // section
    if (activeSection.value !== 'all') {
        list = list.filter((i) => i.section_name === activeSection.value);
    }
    // type
    if (activeType.value !== 'all') {
        list = list.filter((i) => i.kind === activeType.value);
    }
    // search
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (i) =>
                i.title.toLowerCase().includes(q) ||
                i.description?.toLowerCase().includes(q) ||
                i.section_name?.toLowerCase().includes(q),
        );
    }
    return list;
});

const filteredExamsBySeason = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const section = activeSection.value;
    return examGroups.value
        .map((sg) => ({
            seasonName: sg.seasonName,
            exams: sg.exams.filter((e) => {
                if (section !== 'all' && (e.section_name ?? '') !== section)
                    return false;
                if (!q) return true;
                return (
                    e.title.toLowerCase().includes(q) ||
                    e.description?.toLowerCase().includes(q)
                );
            }),
        }))
        .filter((sg) => sg.exams.length > 0);
});

const filteredAssignments = computed(() => {
    let list = [...props.assignments];
    if (activeSection.value !== 'all') {
        list = list.filter((a) =>
            a.sections.some((s) => s.name === activeSection.value),
        );
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (a) =>
                a.title.toLowerCase().includes(q) ||
                a.description?.toLowerCase().includes(q) ||
                a.course?.name.toLowerCase().includes(q),
        );
    }
    return list;
});

const filteredCourses = computed(() => {
    let list = [...props.courses];
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.description?.toLowerCase().includes(q),
        );
    }
    return list;
});

// ─── Stats ──────────────────────────────────────────────────────────────────
const completionRate = computed(() => {
    if (props.hubStats.total === 0) return 0;
    return Math.round((props.hubStats.completed / props.hubStats.total) * 100);
});
const overdueUnifiedCount = computed(
    () =>
        props.unifiedTimeline.filter(
            (i) => !i.is_completed && i.due_at && isOverdue(i.due_at),
        ).length,
);

// ─── Exam helpers (minimal copy from Exam.vue) ──────────────────────────────
const getExamTimeInfo = (exam: Exam) => {
    if (!exam.exam_date && !exam.exam_date_iso) {
        return {
            label: 'No deadline',
            color: 'text-muted-foreground',
            isOverdue: false,
        };
    }
    const dateStr = exam.exam_date_iso || exam.exam_date;
    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime()))
        return {
            label: 'Invalid',
            color: 'text-muted-foreground',
            isOverdue: false,
        };
    if (exam.is_locked) {
        if (exam.status === 'closed' && !exam.has_submissions)
            return {
                label: 'Closed',
                color: 'text-[#CB7676]',
                isOverdue: false,
            };
        return {
            label: `Done ${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
            color: 'text-[#4D9375]',
            isOverdue: false,
        };
    }
    const diff = d.getTime() - Date.now();
    if (diff < 0)
        return { label: 'Overdue', color: 'text-[#CB7676]', isOverdue: true };
    const days = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    if (days > 0)
        return {
            label: `${days}d ${hours}h left`,
            color: 'text-[#E0AF68]',
            isOverdue: false,
        };
    const mins = Math.floor((diff % 3600000) / 60000);
    return {
        label: `${hours}h ${mins}m left`,
        color: 'text-[#CB7676]',
        isOverdue: false,
    };
};
const getStatusBadgeInfo = (exam: Exam) => {
    const total = exam.total_parts ?? exam.parts?.length ?? 0;
    const submitted =
        exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allDone = total > 0 && submitted >= total;
    if (allDone) return { label: 'Completed', color: 'bg-[#4D9375]' };
    if (exam.is_locked && exam.status === 'closed')
        return { label: 'Closed', color: 'bg-[#CB7676]' };
    if (exam.is_locked) return { label: 'In progress', color: 'bg-[#E0AF68]' };
    if (exam.status === 'published')
        return { label: 'Open', color: 'bg-[#D97757]' };
    if (exam.status === 'closed')
        return { label: 'Closed', color: 'bg-[#CB7676]' };
    return { label: 'Draft', color: 'bg-muted text-muted-foreground' };
};
const getCardStatusClass = (exam: Exam) => {
    const total = exam.total_parts ?? exam.parts?.length ?? 0;
    const submitted =
        exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allDone = total > 0 && submitted >= total;
    const dateStr = exam.exam_date_iso || exam.exam_date;
    if (dateStr && !exam.is_locked) {
        const dd = new Date(dateStr);
        if (!Number.isNaN(dd.getTime()) && dd.getTime() < Date.now())
            return 'border-l-[#CB7676]/40 hover:border-l-[#CB7676]/60';
    }
    if (allDone) return 'border-l-[#4D9375]/40 hover:border-l-[#4D9375]/60';
    return 'border-l-primary/20 hover:border-l-primary/40';
};
const getProgressPercent = (exam: Exam) => {
    if (!exam.total_parts) return 0;
    return ((exam.submitted_parts_count ?? 0) / exam.total_parts) * 100;
};
const hasSubmitted = (exam: Exam) =>
    exam.has_submissions ?? (exam.submissions?.length ?? 0) > 0;
const canReviewResults = (exam: Exam) =>
    exam.results_available ?? (exam.status === 'closed' && hasSubmitted(exam));
const isAwaitingClose = (exam: Exam) =>
    !canReviewResults(exam) && hasSubmitted(exam) && exam.is_locked === true;

// ─── Assignment helpers ─────────────────────────────────────────────────────
const isOverdue = (due: string | null) => {
    if (!due) return false;
    const d = new Date(due);
    return !Number.isNaN(d.getTime()) && d.getTime() < Date.now();
};
const getAssignmentBadge = (a: Assignment) => {
    if (a.submission?.submitted) {
        if (a.submission.status === 'Graded' || a.submission.grade) {
            return {
                label: a.submission.grade
                    ? `Graded · ${a.submission.grade}`
                    : 'Graded',
                classes:
                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
            };
        }
        return {
            label: 'Submitted',
            classes:
                'bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20',
        };
    }
    if (isOverdue(a.due_date))
        return {
            label: 'Overdue',
            classes:
                'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
        };
    return {
        label: 'Pending',
        classes:
            'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
    };
};

// ─── Unified helpers ────────────────────────────────────────────────────────
const kindIcon = (k: string) => {
    if (k === 'exam') return GraduationCap;
    if (k === 'assignment') return ClipboardList;
    return BookOpen;
};
const kindColor = (k: string) => {
    if (k === 'exam')
        return 'text-[#D97757] bg-[#D97757]/10 border-[#D97757]/20';
    if (k === 'assignment')
        return 'text-amber-600 bg-amber-500/10 border-amber-500/20';
    return 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20';
};
const formatDue = (iso: string | null) => {
    if (!iso) return 'No due date';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return 'No due date';
    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

// ─── Review modal (exams) ───────────────────────────────────────────────────
const showReviewModal = ref(false);
const selectedExamForReview = ref<Exam | null>(null);
const selectedPartId = ref<number | null>(null);
const selectedQuestionIndex = ref(0);
const isLoadingReview = ref(false);
const { isMobile } = useMobile();
const scrollRef = ref<HTMLElement | null>(null);
let scrollTimer: ReturnType<typeof setTimeout> | null = null;
function showScrollbar() {
    if (!scrollRef.value) return;
    scrollRef.value.classList.add('scrolling');
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = setTimeout(
        () => scrollRef.value?.classList.remove('scrolling'),
        1500,
    );
}
const answersRevealed = computed(
    () =>
        selectedExamForReview.value !== null &&
        selectedExamForReview.value.status === 'closed' &&
        hasSubmitted(selectedExamForReview.value),
);
const selectedPartQuestions = computed(
    () =>
        selectedExamForReview.value?.parts.find(
            (p) => p.id === selectedPartId.value,
        )?.questions ?? [],
);
const canGoToPrevQuestion = computed(() => selectedQuestionIndex.value > 0);
const canGoToNextQuestion = computed(
    () => selectedQuestionIndex.value < selectedPartQuestions.value.length - 1,
);
const scrollReviewToTop = () => {
    nextTick(() => {
        scrollRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
        const wrapper = scrollRef.value?.parentElement;
        if (wrapper && wrapper !== scrollRef.value)
            wrapper.scrollTo({ top: 0, behavior: 'smooth' });
    });
};
const goToPrevQuestion = () => {
    if (!canGoToPrevQuestion.value) return;
    selectedQuestionIndex.value--;
    scrollReviewToTop();
};
const goToNextQuestion = () => {
    if (!canGoToNextQuestion.value) return;
    selectedQuestionIndex.value++;
    scrollReviewToTop();
};
watch(selectedPartId, () => {
    selectedQuestionIndex.value = 0;
    scrollReviewToTop();
});
watch(showReviewModal, async (open) => {
    if (open) {
        document.body.style.overflow = 'hidden';
        getLenis()?.stop();
        await nextTick();
        await new Promise((r) => requestAnimationFrame(r));
        scrollRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        document.body.style.overflow = '';
        getLenis()?.start();
    }
});
const openReview = async (exam: Exam) => {
    if (!canReviewResults(exam) || isLoadingReview.value) return;
    selectedExamForReview.value = exam;
    selectedPartId.value = exam.parts.length > 0 ? exam.parts[0].id : null;
    selectedQuestionIndex.value = 0;
    showReviewModal.value = true;
    const alreadyLoaded =
        exam.parts.some((p) => (p.questions?.length ?? 0) > 0) &&
        (exam.submissions ?? []).some((s) => Array.isArray(s.answers));
    if (alreadyLoaded) return;
    isLoadingReview.value = true;
    try {
        const res = await axios.get(`/exams/${exam.id}/review`);
        const reviewedExam = res.data.exam as Exam;
        selectedExamForReview.value = {
            ...exam,
            ...reviewedExam,
            submissions: res.data.submissions ?? [],
        };
        selectedPartId.value = reviewedExam.parts?.[0]?.id ?? null;
    } catch (e) {
        console.error(e);
        showReviewModal.value = false;
    } finally {
        isLoadingReview.value = false;
    }
};
const openExam = (exam: Exam) => {
    if (exam.is_locked) {
        if (canReviewResults(exam)) openReview(exam);
        return;
    }
    if (exam.url) {
        window.open(exam.url, '_blank', 'noopener');
        return;
    }
    router.visit(examsShow(exam.id).url);
};
const getSubmissionForPart = (exam: Exam, partId: number) =>
    exam.submissions?.find((s) => s.exam_part_id === partId);
const getAnswerObjectForQuestion = (answers: any, questionNumber: number) => {
    let parsed = answers;
    if (typeof answers === 'string') {
        try {
            parsed = JSON.parse(answers);
        } catch {
            return null;
        }
    }
    if (!Array.isArray(parsed)) return null;
    return parsed.find((a: any) => a.question_number === questionNumber);
};
const getAnswerForQuestion = (answers: any, questionNumber: number) => {
    const entry = getAnswerObjectForQuestion(answers, questionNumber);
    return entry ? entry.answer : null;
};
const isAnswerCorrect = (
    question: any,
    submittedAnswer: any,
    answerObject: any = null,
) => {
    if (submittedAnswer === null || submittedAnswer === undefined) return false;
    if (question.type === 'multiple_choice' || question.type === 'true_false') {
        const correctIndex = question.options?.findIndex(
            (opt: any) => opt.is_correct,
        );
        return parseInt(submittedAnswer) === correctIndex;
    } else if (question.type === 'identification') {
        const correctAnswers = Array.isArray(question.correct_answers)
            ? question.correct_answers
            : [question.correct_answer];
        return correctAnswers.some(
            (ans: string) =>
                ans?.toLowerCase().trim() ===
                submittedAnswer?.toString().toLowerCase().trim(),
        );
    } else if (question.type === 'essay') {
        return (answerObject?.ai_score ?? 0) > 0;
    }
    return false;
};

// ─── Breadcrumbs ────────────────────────────────────────────────────────────
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activities Hub', href: '/activities' },
];

// ─── Tour ───────────────────────────────────────────────────────────────────
const activitiesTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Welcome to Activities Hub',
        body: 'Your unified place for exams, assignments, and courses — everything due in one minimalist view.',
    },
    {
        id: 'search',
        target: 'hub-search',
        title: 'Search everything',
        body: 'Search across exams, assignments, and courses instantly.',
    },
    {
        id: 'types',
        target: 'hub-types',
        title: 'Filter by type',
        body: 'Switch between All, Exams, Assignments, and Courses. Counts update live.',
    },
    {
        id: 'sections',
        target: 'hub-sections',
        title: 'Filter by section',
        body: 'If you are in multiple sections, filter activities per section.',
    },
    {
        id: 'timeline',
        target: 'hub-timeline',
        title: 'Timeline',
        body: 'All tab shows the most urgent items first — overdue, due today, then upcoming. Tap any card to take action.',
    },
];
</script>

<template>
    <Head title="Activities Hub" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="student-ui relative flex h-full flex-1 flex-col gap-3 overflow-hidden bg-background p-3 perspective-[1000px] sm:gap-5 sm:p-6 md:p-8"
        >
            <!-- Header -->
            <Motion
                :initial="{ opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.8, easing: [0.16, 1, 0.3, 1] }"
                class="space-y-2"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h1
                            class="dash-title text-[22px] text-foreground sm:text-[34px]"
                        >
                            Activities Hub
                        </h1>
                        <p
                            class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                        >
                            Exams, assignments, and courses — all in one place.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            href="/assignments"
                            class="dash-btn hidden h-9 items-center gap-1.5 rounded-full border border-border/50 bg-card px-3.5 text-[13px] font-medium text-muted-foreground hover:bg-muted sm:inline-flex"
                        >
                            <ClipboardList class="h-3.5 w-3.5" /> Assignments
                        </Link>
                        <Link
                            href="/courses"
                            class="dash-btn hidden h-9 items-center gap-1.5 rounded-full border border-border/50 bg-card px-3.5 text-[13px] font-medium text-muted-foreground hover:bg-muted sm:inline-flex"
                        >
                            <BookOpen class="h-3.5 w-3.5" /> Courses
                        </Link>
                    </div>
                </div>
            </Motion>

            <!-- Stats — minimalist 4 cards, 2 col mobile -->
            <Motion
                :initial="{ opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{
                    duration: 0.8,
                    delay: 0.05,
                    easing: [0.16, 1, 0.3, 1],
                }"
                class="grid grid-cols-2 gap-2.5 sm:gap-4 lg:grid-cols-4"
            >
                <div
                    class="surface-card rounded-xl border border-border/50 p-3 sm:rounded-2xl sm:p-5"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-widest text-muted-foreground/60 uppercase sm:text-[11px]"
                            >Total</span
                        >
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary sm:h-7 sm:w-7"
                        >
                            <Layers class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                    </div>
                    <div
                        class="mt-2 text-[22px] leading-none font-bold tracking-tight sm:mt-3 sm:text-[28px]"
                    >
                        {{ hubStats.total }}
                    </div>
                    <p
                        class="mt-1 text-[11px] text-muted-foreground sm:text-[13px]"
                    >
                        {{ hubStats.exams.total }} exams ·
                        {{ hubStats.assignments.total }} assignments ·
                        {{ hubStats.courses.total }} courses
                    </p>
                </div>
                <div
                    class="surface-card rounded-xl border border-border/50 p-3 sm:rounded-2xl sm:p-5"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-widest text-muted-foreground/60 uppercase sm:text-[11px]"
                            >Pending</span
                        >
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 sm:h-7 sm:w-7 dark:text-amber-400"
                        >
                            <Clock class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                    </div>
                    <div
                        class="mt-2 text-[22px] leading-none font-bold tracking-tight sm:mt-3 sm:text-[28px]"
                    >
                        {{ hubStats.pending }}
                    </div>
                    <p
                        class="mt-1 text-[11px] text-muted-foreground sm:text-[13px]"
                    >
                        {{ hubStats.exams.pending }} exams ·
                        {{ hubStats.assignments.pending }} assignments
                    </p>
                </div>
                <div
                    class="surface-card rounded-xl border border-border/50 p-3 sm:rounded-2xl sm:p-5"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-widest text-muted-foreground/60 uppercase sm:text-[11px]"
                            >Completed</span
                        >
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 sm:h-7 sm:w-7 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                    </div>
                    <div
                        class="mt-2 text-[22px] leading-none font-bold tracking-tight text-emerald-700 sm:mt-3 sm:text-[28px] dark:text-emerald-400"
                    >
                        {{ hubStats.completed }}
                    </div>
                    <p
                        class="mt-1 text-[11px] text-muted-foreground sm:text-[13px]"
                    >
                        Finished & submitted
                    </p>
                </div>
                <div
                    class="surface-card col-span-2 rounded-xl border border-border/50 p-3 sm:rounded-2xl sm:p-5 lg:col-span-1"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-widest text-muted-foreground/60 uppercase sm:text-[11px]"
                            >Progress</span
                        >
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary sm:h-7 sm:w-7"
                        >
                            <BarChart3 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <div
                            class="text-[22px] leading-none font-bold tracking-tight sm:text-[28px]"
                        >
                            {{ completionRate }}%
                        </div>
                        <span
                            class="text-[11px] text-muted-foreground sm:text-[13px]"
                            >{{ hubStats.completed }}/{{ hubStats.total }}</span
                        >
                    </div>
                    <div
                        class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="
                                completionRate === 100
                                    ? 'bg-emerald-500'
                                    : 'bg-primary'
                            "
                            :style="{ width: `${completionRate}%` }"
                        ></div>
                    </div>
                </div>
            </Motion>

            <!-- Search -->
            <div class="relative" data-tour="hub-search">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/50 sm:left-4 sm:h-5 sm:w-5"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search activities, assignments, courses..."
                    class="min-h-11 w-full rounded-full border border-border/50 bg-card py-2 pr-4 pl-10 text-[16px] outline-none placeholder:text-muted-foreground/50 focus:border-[#D97757]/40 focus:ring-2 focus:ring-[#D97757]/20 sm:py-3 sm:pl-12"
                />
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''"
                    class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full p-1 text-muted-foreground/60 hover:bg-muted hover:text-foreground sm:right-4"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Type Tabs — 3 core + All — minimalist pill, mobile-first large touch targets -->
            <div
                data-tour="hub-types"
                class="no-scrollbar flex items-center gap-1.5 overflow-x-auto rounded-full border border-border/50 bg-card p-1 sm:gap-2 sm:p-1.5"
            >
                <button
                    @click="activeType = 'all'"
                    class="dash-btn flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-4"
                    :class="
                        activeType === 'all'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <span>All</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px]"
                        :class="
                            activeType === 'all'
                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ filteredUnified.length }}</span
                    >
                </button>
                <button
                    @click="activeType = 'exam'"
                    class="dash-btn flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-4"
                    :class="
                        activeType === 'exam'
                            ? 'bg-[#D97757] text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <GraduationCap class="h-3.5 w-3.5" />
                    <span>Exams</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px]"
                        :class="
                            activeType === 'exam'
                                ? 'bg-white/20 text-white'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{
                            activeType === 'exam'
                                ? filteredExamsBySeason.reduce(
                                      (a, g) => a + g.exams.length,
                                      0,
                                  )
                                : hubStats.exams.total
                        }}</span
                    >
                </button>
                <button
                    @click="activeType = 'assignment'"
                    class="dash-btn flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-4"
                    :class="
                        activeType === 'assignment'
                            ? 'bg-amber-600 text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <ClipboardList class="h-3.5 w-3.5" />
                    <span>Assignments</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px]"
                        :class="
                            activeType === 'assignment'
                                ? 'bg-white/20 text-white'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{
                            activeType === 'assignment'
                                ? filteredAssignments.length
                                : hubStats.assignments.total
                        }}</span
                    >
                </button>
                <button
                    @click="activeType = 'course'"
                    class="dash-btn flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-[13px] font-semibold transition-all active:scale-95 sm:h-9 sm:px-4"
                    :class="
                        activeType === 'course'
                            ? 'bg-emerald-600 text-white shadow-xs'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <BookOpen class="h-3.5 w-3.5" />
                    <span>Courses</span>
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[11px]"
                        :class="
                            activeType === 'course'
                                ? 'bg-white/20 text-white'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{
                            activeType === 'course'
                                ? filteredCourses.length
                                : hubStats.courses.total
                        }}</span
                    >
                </button>
            </div>

            <!-- Section Tabs -->
            <div
                v-if="sectionTabs.length > 1"
                data-tour="hub-sections"
                class="no-scrollbar sticky top-0 z-20 -mx-3 flex items-center gap-2 overflow-x-auto border-b border-transparent bg-background/80 px-3 pt-1.5 pb-2 backdrop-blur-md sm:gap-3 sm:pt-2 sm:pb-3 md:-mx-8 md:px-8"
            >
                <button
                    v-for="sec in sectionTabs"
                    :key="sec.key"
                    @click="activeSection = sec.key"
                    class="dash-btn flex shrink-0 items-center gap-2 border px-4 text-left"
                    :class="
                        activeSection === sec.key
                            ? 'border-transparent bg-[#D97757] text-white'
                            : 'border-border/50 bg-card text-muted-foreground hover:bg-muted'
                    "
                >
                    <span
                        class="text-sm font-medium"
                        :class="
                            activeSection === sec.key
                                ? 'text-white'
                                : 'text-muted-foreground'
                        "
                        >{{ sec.label }}</span
                    >
                    <span
                        class="flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-[13px] font-semibold"
                        :class="
                            activeSection === sec.key
                                ? 'bg-white/20 text-white'
                                : 'bg-muted/50 text-muted-foreground'
                        "
                        >{{ sec.count }}</span
                    >
                </button>
            </div>

            <!-- ─── Content ──────────────────────────────────────────────────── -->
            <!-- Overdue alert — only in All view, minimalist -->
            <div
                v-if="activeType === 'all' && overdueUnifiedCount > 0"
                class="flex items-center gap-2 rounded-xl border border-red-500/20 bg-red-500/5 px-3.5 py-2.5 text-[13px] font-medium text-red-700 dark:text-red-400"
            >
                <Clock class="h-4 w-4 shrink-0" />
                <span
                    >{{ overdueUnifiedCount }} overdue
                    {{ overdueUnifiedCount === 1 ? 'activity' : 'activities' }}
                    — take action now</span
                >
            </div>

            <!-- All tab — unified timeline -->
            <div
                v-if="activeType === 'all'"
                data-tour="hub-timeline"
                class="space-y-3"
            >
                <div
                    v-if="filteredUnified.length > 0"
                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <Motion
                        v-for="(item, idx) in filteredUnified"
                        :key="`${item.kind}-${item.id}`"
                        :initial="{ opacity: 0, y: 20 }"
                        :in-view="{ opacity: 1, y: 0 }"
                        :in-view-options="{ once: true, margin: '-30px' }"
                        :transition="{
                            duration: 0.5,
                            easing: [0.16, 1, 0.3, 1],
                            delay: idx * 0.03,
                        }"
                        class="group flex min-h-[7rem] flex-col justify-between rounded-xl border border-l-[3px] bg-card p-3 transition-all duration-200 hover:shadow-md sm:min-h-[8rem] sm:rounded-[1.25rem] sm:p-5"
                        :class="[
                            item.is_completed
                                ? 'border-l-emerald-500/30 opacity-80'
                                : item.kind === 'exam'
                                  ? 'border-l-[#D97757]/40 hover:border-l-[#D97757]/60'
                                  : item.kind === 'assignment'
                                    ? 'border-l-amber-500/40 hover:border-l-amber-500/60'
                                    : 'border-l-emerald-500/30 hover:border-l-emerald-500/50',
                            'cursor-pointer hover:bg-muted/30',
                        ]"
                        @click="
                            item.kind === 'exam'
                                ? openExam(
                                      allExamsFlat.find(
                                          (e) => e.id === item.id,
                                      ) ?? ({ id: item.id } as any),
                                  )
                                : router.visit(item.href)
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                                        :class="kindColor(item.kind)"
                                    >
                                        <component
                                            :is="kindIcon(item.kind)"
                                            class="h-3 w-3"
                                        />
                                        {{ item.kind }}
                                    </span>
                                    <span
                                        v-if="item.is_completed"
                                        class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                                        >Completed</span
                                    >
                                    <span
                                        v-else-if="
                                            item.due_at &&
                                            isOverdue(item.due_at)
                                        "
                                        class="inline-flex items-center rounded-full bg-red-500/10 px-2 py-0.5 text-[10px] font-semibold text-red-600"
                                        >Overdue</span
                                    >
                                </div>
                                <h3
                                    class="line-clamp-2 text-[15px] leading-tight font-semibold tracking-tight group-hover:text-primary sm:text-[16px]"
                                >
                                    {{ item.title }}
                                </h3>
                                <p
                                    v-if="item.description"
                                    class="line-clamp-2 text-[13px] leading-relaxed text-muted-foreground sm:text-[14px]"
                                >
                                    {{ item.description }}
                                </p>
                                <div
                                    class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-muted-foreground sm:text-xs"
                                >
                                    <span
                                        v-if="item.meta"
                                        class="inline-flex items-center gap-1"
                                        ><Clock class="h-3 w-3" />
                                        {{ item.meta }}</span
                                    >
                                    <span
                                        v-if="item.due_at"
                                        class="inline-flex items-center gap-1"
                                        ><Calendar class="h-3 w-3" />
                                        {{ formatDue(item.due_at) }}</span
                                    >
                                </div>
                            </div>
                            <ArrowRight
                                class="h-4 w-4 shrink-0 text-muted-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:text-primary sm:h-5 sm:w-5"
                            />
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[11px] text-muted-foreground">{{
                                item.section_name ?? item.season_name ?? ''
                            }}</span>
                            <span
                                v-if="
                                    item.kind === 'exam' &&
                                    item.score !== undefined &&
                                    item.score > 0
                                "
                                class="rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[11px] font-semibold text-[#D97757] tabular-nums"
                                >{{ item.score.toFixed(1) }}</span
                            >
                        </div>
                    </Motion>
                </div>
                <div
                    v-else
                    class="surface-card flex flex-col items-center justify-center rounded-2xl border-dashed py-16 text-center sm:py-24"
                >
                    <div class="mb-4 rounded-full bg-muted/40 p-4">
                        <Search class="h-8 w-8 text-muted-foreground/40" />
                    </div>
                    <h3 class="text-[18px] font-semibold tracking-tight">
                        No activities found
                    </h3>
                    <p class="mt-1 max-w-sm text-[14px] text-muted-foreground">
                        Try a different search or section filter.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-4 rounded-full"
                        @click="
                            searchQuery = '';
                            activeSection = 'all';
                            activeType = 'all';
                        "
                        >Clear filters</Button
                    >
                </div>
            </div>

            <!-- Exams tab -->
            <template v-if="activeType === 'exam'">
                <template v-if="filteredExamsBySeason.length > 0">
                    <Motion
                        v-for="(seasonGroup, sIdx) in filteredExamsBySeason"
                        :key="sIdx"
                        :initial="{ opacity: 0, y: 30 }"
                        :in-view="{ opacity: 1, y: 0 }"
                        :in-view-options="{ once: true, margin: '-40px' }"
                        :transition="{
                            duration: 0.8,
                            easing: [0.16, 1, 0.3, 1],
                            delay: sIdx * 0.08,
                        }"
                        class="space-y-3 sm:space-y-5"
                    >
                        <div
                            class="mb-0.5 flex items-center gap-2 sm:mb-1 sm:gap-3"
                        >
                            <div class="flex items-center gap-2">
                                <Calendar
                                    class="h-4 w-4 text-primary sm:h-5 sm:w-5"
                                />
                                <h2
                                    class="dash-title text-[15px] text-foreground sm:text-[20px]"
                                >
                                    {{ seasonGroup.seasonName }}
                                </h2>
                            </div>
                            <div
                                class="h-px flex-1 bg-gradient-to-r from-border/60 to-transparent"
                            />
                            <span
                                class="text-xs font-medium text-muted-foreground tabular-nums"
                                >{{ seasonGroup.exams.length }}
                                {{
                                    seasonGroup.exams.length === 1
                                        ? 'exam'
                                        : 'exams'
                                }}</span
                            >
                        </div>
                        <div
                            class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 xl:grid-cols-4"
                        >
                            <Motion
                                v-for="(exam, eIdx) in seasonGroup.exams"
                                :key="exam.id"
                                :initial="{ opacity: 0, y: 20 }"
                                :in-view="{ opacity: 1, y: 0 }"
                                :in-view-options="{
                                    once: true,
                                    margin: '-30px',
                                }"
                                :transition="{
                                    duration: 0.5,
                                    easing: [0.16, 1, 0.3, 1],
                                    delay: eIdx * 0.05,
                                }"
                                class="exam-card flex min-h-[6.25rem] min-w-0 flex-col justify-between rounded-xl border border-l-[3px] p-3 transition-colors duration-200 sm:min-h-[7.5rem] sm:rounded-[1.25rem] sm:p-5"
                                :class="[
                                    exam.is_locked && !canReviewResults(exam)
                                        ? 'cursor-default opacity-80'
                                        : 'cursor-pointer hover:bg-muted/30',
                                    getCardStatusClass(exam),
                                ]"
                                role="button"
                                tabindex="0"
                                @click="openExam(exam)"
                                @keydown.enter.prevent="openExam(exam)"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="flex-1">
                                        <div
                                            class="mb-2 flex items-start justify-between gap-2"
                                        >
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-[12px] font-semibold text-white sm:text-[13px]"
                                                :class="
                                                    getStatusBadgeInfo(exam)
                                                        .color
                                                "
                                                >{{
                                                    getStatusBadgeInfo(exam)
                                                        .label
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    exam.is_locked &&
                                                    hasSubmitted(exam)
                                                "
                                                class="inline-flex items-center rounded-full bg-[#D97757]/10 px-2.5 py-1 text-[13px] font-semibold text-[#D97757] tabular-nums"
                                                >{{
                                                    exam.submissions
                                                        ?.reduce(
                                                            (acc, s) =>
                                                                acc +
                                                                parseFloat(
                                                                    s.score,
                                                                ),
                                                            0,
                                                        )
                                                        .toFixed(1)
                                                }}</span
                                            >
                                        </div>
                                        <div class="flex-1 space-y-1.5">
                                            <div
                                                v-if="exam.section_name"
                                                class="text-[13px] font-medium text-muted-foreground"
                                            >
                                                {{ exam.section_name }}
                                            </div>
                                            <h2
                                                class="text-[16px] leading-tight font-semibold tracking-tight text-foreground sm:text-[17px]"
                                            >
                                                {{ exam.title }}
                                            </h2>
                                            <p
                                                class="line-clamp-2 text-[14px] leading-relaxed text-muted-foreground sm:text-[15px]"
                                            >
                                                {{ exam.description }}
                                            </p>
                                            <div
                                                v-if="
                                                    exam.total_parts &&
                                                    exam.total_parts > 0
                                                "
                                                class="space-y-1.5 pt-1.5 sm:space-y-2 sm:pt-2"
                                            >
                                                <div
                                                    class="flex items-center justify-between text-[13px] font-medium"
                                                >
                                                    <span
                                                        :class="
                                                            exam.is_locked
                                                                ? 'text-[#4D9375]'
                                                                : 'text-muted-foreground'
                                                        "
                                                        >{{
                                                            exam.submitted_parts_count
                                                        }}/{{
                                                            exam.total_parts
                                                        }}
                                                        parts</span
                                                    >
                                                    <span
                                                        v-if="!exam.is_locked"
                                                        class="text-muted-foreground"
                                                        >{{
                                                            Math.round(
                                                                getProgressPercent(
                                                                    exam,
                                                                ),
                                                            )
                                                        }}%</span
                                                    >
                                                </div>
                                                <div
                                                    class="h-1.5 overflow-hidden rounded-full bg-muted sm:h-2"
                                                >
                                                    <div
                                                        class="h-full rounded-full transition-all duration-700"
                                                        :class="
                                                            exam.is_locked
                                                                ? 'bg-[#4D9375]'
                                                                : 'bg-primary'
                                                        "
                                                        :style="{
                                                            width: `${getProgressPercent(exam)}%`,
                                                        }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center gap-2.5 pt-1.5 text-xs text-muted-foreground sm:gap-3 sm:pt-2 sm:text-sm"
                                            >
                                                <div
                                                    class="flex items-center gap-1"
                                                >
                                                    <Clock
                                                        class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                                    /><span
                                                        >{{
                                                            exam.duration_minutes
                                                        }}
                                                        min</span
                                                    >
                                                </div>
                                                <div
                                                    class="flex items-center gap-1"
                                                >
                                                    <Timer
                                                        class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                                        :class="
                                                            getExamTimeInfo(
                                                                exam,
                                                            ).color
                                                        "
                                                    /><span
                                                        :class="
                                                            getExamTimeInfo(
                                                                exam,
                                                            ).color
                                                        "
                                                        class="text-[13px] font-medium"
                                                        >{{
                                                            getExamTimeInfo(
                                                                exam,
                                                            ).label
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <ArrowRight
                                        v-if="
                                            !exam.is_locked ||
                                            canReviewResults(exam)
                                        "
                                        class="h-5 w-5 shrink-0 text-muted-foreground/50 sm:hidden"
                                    />
                                    <Lock
                                        v-else-if="isAwaitingClose(exam)"
                                        class="h-4 w-4 shrink-0 text-muted-foreground/50 sm:hidden"
                                    />
                                </div>
                                <div class="mt-3 hidden sm:block">
                                    <button
                                        v-if="canReviewResults(exam)"
                                        type="button"
                                        class="dash-btn w-full bg-[#D97757]/10 text-[15px] text-[#D97757] hover:bg-[#D97757]/15"
                                        @click.stop="openReview(exam)"
                                    >
                                        Review results
                                    </button>
                                    <button
                                        v-else-if="isAwaitingClose(exam)"
                                        type="button"
                                        disabled
                                        class="dash-btn flex w-full cursor-not-allowed items-center justify-center gap-1.5 bg-muted/20 text-[15px] text-muted-foreground"
                                        @click.stop
                                    >
                                        <Lock class="h-3.5 w-3.5" /> Results
                                        locked
                                    </button>
                                    <span
                                        v-else-if="exam.is_locked"
                                        class="dash-btn flex w-full cursor-default items-center justify-center bg-muted/20 text-[15px] text-muted-foreground"
                                        >Closed</span
                                    >
                                    <a
                                        v-else-if="exam.url"
                                        :href="exam.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="dash-btn flex w-full items-center justify-center gap-2 bg-[#D97757] text-[15px] text-white hover:bg-[#D97757]/90"
                                        @click.stop
                                        >Start <ArrowRight class="h-4 w-4"
                                    /></a>
                                    <Link
                                        v-else
                                        :href="examsShow(exam.id).url"
                                        class="dash-btn flex w-full items-center justify-center gap-2 bg-[#D97757] text-[15px] text-white hover:bg-[#D97757]/90"
                                        @click.stop
                                        >Start <ArrowRight class="h-4 w-4"
                                    /></Link>
                                </div>
                            </Motion>
                        </div>
                    </Motion>
                    <div v-if="hasMoreExams" class="flex justify-center py-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="min-w-44 rounded-xl"
                            :disabled="isLoadingMoreExams"
                            @click="loadMoreExams"
                            >{{
                                isLoadingMoreExams
                                    ? 'Loading…'
                                    : 'Load more exams'
                            }}</Button
                        >
                    </div>
                </template>
                <div
                    v-else
                    class="surface-card flex flex-col items-center justify-center space-y-4 border-dashed py-20 text-center"
                >
                    <div class="rounded-full bg-muted/30 p-4">
                        <Calendar class="h-12 w-12 text-muted-foreground/40" />
                    </div>
                    <div class="space-y-1">
                        <h3
                            class="text-[20px] font-semibold tracking-tight text-foreground"
                        >
                            No exams found
                        </h3>
                        <p class="text-[15px] text-muted-foreground">
                            Try a different search or section.
                        </p>
                    </div>
                </div>
            </template>

            <!-- Assignments tab — minimalist cards -->
            <template v-if="activeType === 'assignment'">
                <div
                    v-if="filteredAssignments.length > 0"
                    class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="assignment in filteredAssignments"
                        :key="assignment.id"
                        class="surface-card group flex flex-col gap-3 overflow-hidden rounded-xl border border-l-[3px] p-3.5 transition-all duration-300 hover:shadow-md sm:rounded-2xl sm:p-5"
                        :class="
                            assignment.submission?.submitted
                                ? assignment.submission.status === 'Graded' ||
                                  assignment.submission.grade
                                    ? 'border-l-emerald-500/50'
                                    : 'border-l-orange-500/50'
                                : isOverdue(assignment.due_date)
                                  ? 'border-l-red-500/50'
                                  : 'border-l-amber-500/50'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3
                                class="line-clamp-2 min-w-0 flex-1 text-[15px] font-semibold tracking-tight group-hover:text-primary sm:text-[16px]"
                            >
                                {{ assignment.title }}
                            </h3>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                :class="getAssignmentBadge(assignment).classes"
                                >{{
                                    getAssignmentBadge(assignment).label
                                }}</span
                            >
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 text-[12px] text-muted-foreground"
                        >
                            <span
                                v-if="assignment.course?.name"
                                class="inline-flex items-center gap-1"
                                ><BookOpen class="h-3 w-3" />
                                {{ assignment.course.name }}</span
                            >
                            <span
                                v-if="assignment.sections?.[0]?.name"
                                class="inline-flex items-center gap-1"
                                ><Layers class="h-3 w-3" />
                                {{ assignment.sections[0].name }}</span
                            >
                        </div>
                        <p
                            v-if="assignment.description"
                            class="line-clamp-2 text-[13px] leading-relaxed text-muted-foreground"
                        >
                            {{ assignment.description }}
                        </p>
                        <div
                            class="flex items-center justify-between rounded-lg bg-muted/20 px-3 py-2 text-[12px]"
                        >
                            <span class="text-muted-foreground">Due</span>
                            <span
                                class="font-medium"
                                :class="
                                    assignment.submission?.submitted
                                        ? 'text-foreground'
                                        : isOverdue(assignment.due_date)
                                          ? 'text-red-600'
                                          : 'text-amber-700'
                                "
                                >{{
                                    assignment.due_date
                                        ? formatDue(assignment.due_date)
                                        : 'No deadline'
                                }}</span
                            >
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <Link
                                href="/assignments"
                                class="dash-btn inline-flex h-8 items-center gap-1.5 rounded-full border border-border/60 bg-card px-3 text-[12px] font-medium hover:bg-muted"
                                ><FileText class="h-3.5 w-3.5" /> View</Link
                            >
                            <Link
                                v-if="!assignment.submission?.submitted"
                                href="/assignments"
                                class="dash-btn inline-flex h-8 items-center gap-1.5 rounded-full bg-[#D97757] px-3.5 text-[12px] font-semibold text-white hover:bg-[#D97757]/90"
                                ><ArrowRight class="h-3.5 w-3.5" /> Submit</Link
                            >
                            <span
                                v-else
                                class="text-[11px] font-medium text-emerald-600"
                                >Submitted
                                {{
                                    assignment.submission?.submitted_at
                                        ? new Date(
                                              assignment.submission
                                                  .submitted_at,
                                          ).toLocaleDateString()
                                        : ''
                                }}</span
                            >
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="surface-card flex flex-col items-center justify-center rounded-2xl border-dashed py-16 text-center"
                >
                    <div class="mb-4 rounded-full bg-muted/30 p-4">
                        <ClipboardList
                            class="h-10 w-10 text-muted-foreground/40"
                        />
                    </div>
                    <h3 class="text-[18px] font-semibold">
                        No assignments found
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Try a different filter or check back later.
                    </p>
                </div>
            </template>

            <!-- Courses tab -->
            <template v-if="activeType === 'course'">
                <div
                    v-if="filteredCourses.length > 0"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="course in filteredCourses"
                        :key="course.id"
                        :href="`/courses/${course.id}`"
                        class="group surface-card flex flex-col overflow-hidden rounded-2xl border border-border/40 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                    >
                        <div
                            class="relative h-36 overflow-hidden bg-gradient-to-br from-primary/20 via-primary/10 to-muted"
                        >
                            <img
                                v-if="course.cover_photo"
                                :src="course.cover_photo"
                                :alt="course.name"
                                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center"
                            >
                                <BookOpen class="h-12 w-12 text-primary/20" />
                            </div>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-background/80 via-background/20 to-transparent"
                            />
                            <div class="absolute top-3 right-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[10px] font-bold backdrop-blur-sm"
                                    :class="
                                        course.progress >= 100
                                            ? 'bg-emerald-500/90 text-white'
                                            : 'bg-background/70 text-foreground'
                                    "
                                    >{{
                                        course.progress >= 100
                                            ? '✓ Done'
                                            : `${course.progress}%`
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-4">
                            <h3
                                class="line-clamp-1 text-[16px] font-bold tracking-tight group-hover:text-primary"
                            >
                                {{ course.name }}
                            </h3>
                            <p
                                v-if="course.description"
                                class="mt-1 line-clamp-2 text-[12px] leading-relaxed text-muted-foreground"
                            >
                                {{ course.description }}
                            </p>
                            <div
                                class="mt-3 flex items-center gap-3 text-[11px] text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1"
                                    ><BookMarked class="h-3 w-3" />
                                    {{ course.modulesCount }} modules</span
                                >
                                <span class="inline-flex items-center gap-1"
                                    ><BarChart3 class="h-3 w-3" />
                                    {{ course.completedLessons }}/{{
                                        course.totalLessons
                                    }}</span
                                >
                            </div>
                            <div
                                class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="
                                        course.progress >= 100
                                            ? 'bg-emerald-500'
                                            : 'bg-primary'
                                    "
                                    :style="{ width: `${course.progress}%` }"
                                ></div>
                            </div>
                        </div>
                    </Link>
                </div>
                <div
                    v-else
                    class="surface-card flex flex-col items-center justify-center rounded-2xl border-dashed py-16 text-center"
                >
                    <div class="mb-4 rounded-full bg-muted/30 p-4">
                        <BookOpen class="h-10 w-10 text-muted-foreground/40" />
                    </div>
                    <h3 class="text-[18px] font-semibold">No courses found</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        You are not enrolled in any courses yet.
                    </p>
                    <Link
                        href="/courses"
                        class="dash-btn mt-4 inline-flex h-9 items-center gap-1.5 rounded-full bg-primary px-4 text-[13px] font-semibold text-primary-foreground"
                        >Browse courses</Link
                    >
                </div>
            </template>
        </div>

        <OnboardingTour
            tour-id="activities-hub"
            :steps="activitiesTourSteps"
            :can-start="!showReviewModal"
            :start-delay="900"
        />
    </AppLayout>

    <!-- Exam Review Modal (reused) -->
    <ResponsiveModal
        :open="showReviewModal"
        custom-header
        content-class="exam-review-modal flex flex-col overflow-hidden sm:max-h-[85vh] sm:max-w-[1000px]"
        @close="showReviewModal = false"
    >
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0 space-y-0.5">
                    <span class="text-xs font-medium text-primary"
                        >Your Results</span
                    >
                    <DialogTitle class="text-lg font-bold text-foreground">{{
                        selectedExamForReview?.title
                    }}</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground"
                        >Review your answers and feedback.</DialogDescription
                    >
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <div class="rounded-lg bg-primary/5 px-3 py-1.5 text-right">
                        <span
                            class="text-[9px] font-medium text-muted-foreground"
                            >Score</span
                        >
                        <span
                            class="ml-1.5 text-base font-bold text-foreground tabular-nums"
                            >{{
                                selectedExamForReview?.submissions
                                    ?.reduce(
                                        (acc, s) => acc + parseFloat(s.score),
                                        0,
                                    )
                                    .toFixed(1)
                            }}</span
                        >
                    </div>
                </div>
            </div>
        </template>

        <div
            v-if="
                selectedExamForReview && selectedExamForReview.parts.length > 1
            "
            class="no-scrollbar flex items-center gap-1.5 overflow-x-auto border-b border-border/50 bg-muted/5 px-6 py-2.5"
        >
            <button
                v-for="part in selectedExamForReview.parts"
                :key="part.id"
                @click="selectedPartId = part.id"
                class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-medium transition-all"
                :class="
                    selectedPartId === part.id
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'bg-muted/30 text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                "
            >
                <span>{{ part.title }}</span>
                <span
                    v-if="getSubmissionForPart(selectedExamForReview, part.id)"
                    class="h-1.5 w-1.5 rounded-full bg-[#4D9375]"
                />
            </button>
        </div>

        <div
            ref="scrollRef"
            @scroll="showScrollbar"
            data-lenis-prevent
            class="custom-scrollbar min-h-0 sm:flex-1 sm:overflow-y-auto sm:overscroll-contain"
        >
            <div
                v-if="isLoadingReview"
                class="flex min-h-64 flex-col items-center justify-center gap-3 p-8 text-center"
            >
                <Timer class="h-7 w-7 animate-pulse text-primary" />
                <p class="text-sm font-medium text-muted-foreground">
                    Loading your review…
                </p>
            </div>
            <div v-else-if="selectedExamForReview" class="space-y-8 p-6">
                <Motion
                    v-for="part in selectedExamForReview.parts"
                    :key="part.id"
                    v-show="selectedPartId === part.id"
                    :initial="{ opacity: 0, y: 10 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.4, easing: [0.16, 1, 0.3, 1] }"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-foreground">
                            {{ part.title }}
                        </h3>
                        <span
                            v-if="
                                getSubmissionForPart(
                                    selectedExamForReview,
                                    part.id,
                                )
                            "
                            class="rounded-lg bg-muted/50 px-3 py-1 text-[11px] font-semibold text-foreground tabular-nums"
                            >Score:
                            {{
                                getSubmissionForPart(
                                    selectedExamForReview,
                                    part.id,
                                )?.score
                            }}
                            /
                            {{
                                part.questions?.reduce(
                                    (acc, q) => acc + (parseInt(q.points) || 1),
                                    0,
                                )
                            }}</span
                        >
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Motion
                            v-for="(question, qIndex) in part.questions"
                            :key="qIndex"
                            v-show="
                                !isMobile || qIndex === selectedQuestionIndex
                            "
                            :initial="{ opacity: 0, y: 15 }"
                            :animate="{ opacity: 1, y: 0 }"
                            :transition="{
                                duration: 0.4,
                                delay: qIndex * 0.05,
                                easing: [0.16, 1, 0.3, 1],
                            }"
                            class="question-card relative overflow-hidden rounded-xl border p-5 transition-all"
                            :class="
                                !answersRevealed
                                    ? 'border-border/50 bg-card/40'
                                    : isAnswerCorrect(
                                            question,
                                            getAnswerForQuestion(
                                                getSubmissionForPart(
                                                    selectedExamForReview,
                                                    part.id,
                                                )?.answers,
                                                qIndex + 1,
                                            ),
                                            getAnswerObjectForQuestion(
                                                getSubmissionForPart(
                                                    selectedExamForReview,
                                                    part.id,
                                                )?.answers,
                                                qIndex + 1,
                                            ),
                                        )
                                      ? 'border-[#4D9375]/30 bg-[#4D9375]/[0.02]'
                                      : 'border-[#CB7676]/30 bg-[#CB7676]/[0.02]'
                            "
                        >
                            <div
                                class="question-reveal-overlay pointer-events-none absolute inset-0 z-20 hidden items-center justify-center opacity-100 transition-opacity duration-300 md:flex"
                            >
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-primary/20 bg-background/90 px-4 py-2 shadow-lg backdrop-blur-sm"
                                >
                                    <Shield class="h-4 w-4 text-primary" /><span
                                        class="text-[11px] font-medium text-primary"
                                        >Hover to reveal</span
                                    >
                                </div>
                            </div>
                            <div
                                class="question-reveal-content space-y-3 transition-all duration-500 select-none md:blur-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-[11px] font-medium text-muted-foreground"
                                            >#{{ qIndex + 1 }}</span
                                        ><span
                                            class="rounded-md bg-muted/50 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                            >{{
                                                question.type.replace('_', ' ')
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] font-medium"
                                    >
                                        <template v-if="answersRevealed">
                                            <CheckCircle2
                                                v-if="
                                                    isAnswerCorrect(
                                                        question,
                                                        getAnswerForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        ),
                                                        getAnswerObjectForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        ),
                                                    )
                                                "
                                                class="h-3.5 w-3.5 text-[#4D9375]"
                                            /><XCircle
                                                v-else
                                                class="h-3.5 w-3.5 text-[#CB7676]"
                                            />
                                            <span
                                                :class="
                                                    isAnswerCorrect(
                                                        question,
                                                        getAnswerForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        ),
                                                        getAnswerObjectForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        ),
                                                    )
                                                        ? 'text-[#4D9375]'
                                                        : 'text-[#CB7676]'
                                                "
                                                >{{
                                                    question.type === 'essay'
                                                        ? 'Reviewed'
                                                        : isAnswerCorrect(
                                                                question,
                                                                getAnswerForQuestion(
                                                                    getSubmissionForPart(
                                                                        selectedExamForReview,
                                                                        part.id,
                                                                    )?.answers,
                                                                    qIndex + 1,
                                                                ),
                                                                getAnswerObjectForQuestion(
                                                                    getSubmissionForPart(
                                                                        selectedExamForReview,
                                                                        part.id,
                                                                    )?.answers,
                                                                    qIndex + 1,
                                                                ),
                                                            )
                                                          ? 'Correct'
                                                          : 'Incorrect'
                                                }}</span
                                            >
                                        </template>
                                        <template v-else
                                            ><CheckCircle2
                                                class="h-3.5 w-3.5 text-muted-foreground/40"
                                            /><span
                                                class="text-muted-foreground/60"
                                                >Submitted</span
                                            ></template
                                        >
                                    </div>
                                </div>
                                <p
                                    class="text-sm leading-relaxed font-medium text-foreground"
                                >
                                    {{ question.text }}
                                </p>
                                <div
                                    v-if="
                                        question.type === 'multiple_choice' ||
                                        question.type === 'true_false'
                                    "
                                    class="space-y-1.5"
                                >
                                    <div
                                        v-for="(
                                            option, oIndex
                                        ) in question.options"
                                        :key="oIndex"
                                        class="flex items-center justify-between rounded-lg border p-2.5 text-sm transition-all"
                                        :class="
                                            !answersRevealed
                                                ? parseInt(
                                                      getAnswerForQuestion(
                                                          getSubmissionForPart(
                                                              selectedExamForReview,
                                                              part.id,
                                                          )?.answers,
                                                          qIndex + 1,
                                                      ),
                                                  ) === oIndex
                                                    ? 'border-border/60 bg-muted/40'
                                                    : 'border-border/40 bg-muted/10 opacity-50'
                                                : option.is_correct
                                                  ? 'border-[#4D9375]/50 bg-[#4D9375]/10'
                                                  : parseInt(
                                                          getAnswerForQuestion(
                                                              getSubmissionForPart(
                                                                  selectedExamForReview,
                                                                  part.id,
                                                              )?.answers,
                                                              qIndex + 1,
                                                          ),
                                                      ) === oIndex
                                                    ? 'border-[#CB7676]/50 bg-[#CB7676]/10'
                                                    : 'border-border/50 bg-muted/20 opacity-60'
                                        "
                                    >
                                        <span class="text-sm">{{
                                            option.text
                                        }}</span
                                        ><span
                                            v-if="
                                                parseInt(
                                                    getAnswerForQuestion(
                                                        getSubmissionForPart(
                                                            selectedExamForReview,
                                                            part.id,
                                                        )?.answers,
                                                        qIndex + 1,
                                                    ),
                                                ) === oIndex
                                            "
                                            class="ml-2 rounded-md bg-foreground/10 px-2 py-0.5 text-[10px] font-semibold text-foreground"
                                            >Your answer</span
                                        >
                                    </div>
                                </div>
                                <div
                                    v-else-if="
                                        question.type === 'identification'
                                    "
                                    class="space-y-2"
                                >
                                    <div
                                        class="rounded-lg border border-border/50 bg-muted/20 p-3"
                                    >
                                        <span
                                            class="text-[10px] font-medium text-muted-foreground"
                                            >Your answer</span
                                        >
                                        <p
                                            class="mt-1 text-sm font-semibold"
                                            :class="
                                                answersRevealed &&
                                                isAnswerCorrect(
                                                    question,
                                                    getAnswerForQuestion(
                                                        getSubmissionForPart(
                                                            selectedExamForReview,
                                                            part.id,
                                                        )?.answers,
                                                        qIndex + 1,
                                                    ),
                                                )
                                                    ? 'text-[#4D9375]'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{
                                                getAnswerForQuestion(
                                                    getSubmissionForPart(
                                                        selectedExamForReview,
                                                        part.id,
                                                    )?.answers,
                                                    qIndex + 1,
                                                ) || 'No answer'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            answersRevealed &&
                                            question.correct_answer
                                        "
                                        class="rounded-lg border border-[#4D9375]/30 bg-[#4D9375]/5 p-3"
                                    >
                                        <span
                                            class="text-[10px] font-medium text-[#4D9375]"
                                            >Correct answer</span
                                        >
                                        <p
                                            class="mt-1 text-sm font-semibold text-[#4D9375]"
                                        >
                                            {{ question.correct_answer }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-else-if="question.type === 'essay'"
                                    class="space-y-3"
                                >
                                    <div
                                        class="rounded-lg border border-border/50 bg-muted/20 p-3"
                                    >
                                        <span
                                            class="text-[10px] font-medium text-muted-foreground"
                                            >Your response</span
                                        >
                                        <p
                                            class="custom-scrollbar mt-1 text-sm leading-relaxed whitespace-pre-wrap text-foreground sm:max-h-52 sm:overflow-y-auto"
                                        >
                                            {{
                                                getAnswerForQuestion(
                                                    getSubmissionForPart(
                                                        selectedExamForReview,
                                                        part.id,
                                                    )?.answers,
                                                    qIndex + 1,
                                                ) || 'No response submitted'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            getAnswerObjectForQuestion(
                                                getSubmissionForPart(
                                                    selectedExamForReview,
                                                    part.id,
                                                )?.answers,
                                                qIndex + 1,
                                            )?.ai_score !== undefined
                                        "
                                        class="rounded-lg border border-primary/20 bg-primary/5 p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <Zap
                                                    class="h-3.5 w-3.5 text-primary"
                                                /><span
                                                    class="text-[11px] font-medium text-primary"
                                                    >AI Feedback</span
                                                >
                                            </div>
                                            <span
                                                class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary tabular-nums"
                                                >Score:
                                                {{
                                                    getAnswerObjectForQuestion(
                                                        getSubmissionForPart(
                                                            selectedExamForReview,
                                                            part.id,
                                                        )?.answers,
                                                        qIndex + 1,
                                                    )?.ai_score
                                                }}
                                                / {{ question.points }}</span
                                            >
                                        </div>
                                        <p
                                            v-if="
                                                getAnswerObjectForQuestion(
                                                    getSubmissionForPart(
                                                        selectedExamForReview,
                                                        part.id,
                                                    )?.answers,
                                                    qIndex + 1,
                                                )?.ai_feedback
                                            "
                                            class="custom-scrollbar mt-2 text-sm leading-relaxed text-foreground/80 sm:max-h-52 sm:overflow-y-auto"
                                        >
                                            {{
                                                getAnswerObjectForQuestion(
                                                    getSubmissionForPart(
                                                        selectedExamForReview,
                                                        part.id,
                                                    )?.answers,
                                                    qIndex + 1,
                                                )?.ai_feedback
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </Motion>
                    </div>
                </Motion>
            </div>
        </div>

        <template #footer>
            <Button
                variant="secondary"
                @click="showReviewModal = false"
                class="w-full rounded-lg px-4 py-2 text-xs font-medium sm:w-auto"
                >Close</Button
            >
            <div
                v-if="isMobile && selectedPartQuestions.length > 1"
                class="flex w-full items-center justify-between gap-2"
            >
                <button
                    @click="goToPrevQuestion"
                    :disabled="!canGoToPrevQuestion"
                    class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[11px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                >
                    <ChevronLeft class="h-3.5 w-3.5" /> Prev
                </button>
                <span
                    class="text-[11px] font-black tracking-widest text-muted-foreground/60 uppercase"
                    >{{ selectedQuestionIndex + 1 }} /
                    {{ selectedPartQuestions.length }}</span
                >
                <button
                    @click="goToNextQuestion"
                    :disabled="!canGoToNextQuestion"
                    class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[11px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                >
                    Next <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
            <div
                v-if="
                    !isMobile &&
                    selectedExamForReview &&
                    selectedExamForReview.parts.length > 1
                "
                class="flex items-center gap-2"
            >
                <button
                    v-if="
                        selectedExamForReview.parts.findIndex(
                            (p) => p.id === selectedPartId,
                        ) > 0
                    "
                    @click="
                        selectedPartId =
                            selectedExamForReview.parts[
                                selectedExamForReview.parts.findIndex(
                                    (p) => p.id === selectedPartId,
                                ) - 1
                            ].id
                    "
                    class="rounded-lg border border-border/50 bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                >
                    Previous
                </button>
                <button
                    v-if="
                        selectedExamForReview.parts.findIndex(
                            (p) => p.id === selectedPartId,
                        ) <
                        selectedExamForReview.parts.length - 1
                    "
                    @click="
                        selectedPartId =
                            selectedExamForReview.parts[
                                selectedExamForReview.parts.findIndex(
                                    (p) => p.id === selectedPartId,
                                ) + 1
                            ].id
                    "
                    class="rounded-lg bg-primary px-5 py-2 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    Next
                </button>
            </div>
        </template>
    </ResponsiveModal>
</template>

<style scoped>
@reference "../../../css/app.css";
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--color-primary) 30%, transparent)
        transparent;
    scroll-behavior: smooth;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--color-primary) 15%, transparent);
    border-radius: 12px;
    border: 1px solid transparent;
    background-clip: padding-box;
    transition: background-color 0.6s ease;
}
.custom-scrollbar.scrolling::-webkit-scrollbar-thumb,
.custom-scrollbar:hover::-webkit-scrollbar-thumb,
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.custom-scrollbar.scrolling::-webkit-scrollbar-thumb:active,
.custom-scrollbar::-webkit-scrollbar-thumb:active {
    background: color-mix(in srgb, var(--color-primary) 55%, transparent);
}
.custom-scrollbar::-webkit-scrollbar-button {
    display: none;
}
.overscroll-contain {
    overscroll-behavior: contain;
}
.question-card:hover .question-reveal-overlay {
    opacity: 0 !important;
}
.question-card:hover .question-reveal-content {
    filter: none !important;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
