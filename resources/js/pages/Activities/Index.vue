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
    ArrowRight,
    Timer,
    ChevronLeft,
    ChevronRight,
    Shield,
    XCircle,
    Zap,
    X,
    Layers,
    Award,
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
    // Schedule window; cards use these to show Starts / Opens / Ends instead
    // of relying on the (still admin-controlled) DB status alone.
    starts_at_iso?: string | null;
    ends_at_iso?: string | null;
    is_open_now?: boolean;
    is_upcoming?: boolean;
    has_ended?: boolean;
    // The set of the exam this student was handed (null until they open it).
    set?: { id: number; title: string } | null;
}
interface SeasonGroup {
    seasonName: string;
    exams: Exam[];
}

interface ActivityScore {
    id: number;
    title: string;
    section_name: string | null;
    score: number | null;
    submitted: boolean;
    state: 'completed' | 'in_progress' | 'open' | 'closed' | 'draft';
}
interface ScoreGroup {
    seasonName: string;
    exams: ActivityScore[];
}

const props = defineProps<{
    examsBySeason: SeasonGroup[];
    examPagination?: { hasMore: boolean; nextCursor: string | null };
    sectionTabs: { key: string; label: string; count: number }[];
    hubStats: {
        exams: { total: number; pending: number; completed: number };
    };
    activityScores?: ScoreGroup[];
}>();

// ─── Polling + Visibility (reuse exam pattern) ──────────────────────────────
const { stop: stopPoll, start: startPoll } = usePoll(
    10000,
    {
        only: ['examsBySeason', 'hubStats', 'sectionTabs', 'activityScores'],
    },
    { autoStart: false },
);
const refreshHub = () =>
    router.reload({
        only: ['examsBySeason', 'hubStats', 'sectionTabs', 'activityScores'],
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
const activeSection = ref('all');
const searchQuery = ref('');

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

// The section tabs describe the *whole* catalogue (`hubSummary`), but the
// grid only holds the pages loaded so far. Clicking a tab whose exams all sit
// past the last loaded page used to land on "No exams found" even though the
// tab badge counted those exams. Keep pulling pages while the current section
// filter matches nothing yet and the catalogue still has pages — stop as soon
// as a card appears or the pages are exhausted. An active search query is
// left to the user: auto-loading while a query is typed would race the
// keystrokes and could loop over unrelated pages.
watch([filteredExamsBySeason, activeSection, hasMoreExams], () => {
    if (searchQuery.value.trim() !== '') return;
    const shown = filteredExamsBySeason.value.reduce(
        (n, sg) => n + sg.exams.length,
        0,
    );
    if (shown === 0) loadMoreExams();
});

// ─── Stats ──────────────────────────────────────────────────────────────────
const completionRate = computed(() => {
    if (props.hubStats.exams.total === 0) return 0;
    return Math.round(
        (props.hubStats.exams.completed / props.hubStats.exams.total) * 100,
    );
});

// ─── My Scores drawer ───────────────────────────────────────────────────────
const showScoresDrawer = ref(false);
const scoreGroups = computed(() => props.activityScores ?? []);
const scoreAll = computed(() => scoreGroups.value.flatMap((g) => g.exams));
const gradedCount = computed(
    () => scoreAll.value.filter((a) => a.score !== null).length,
);
const SCORE_STATE_META: Record<
    ActivityScore['state'],
    { label: string; class: string }
> = {
    completed: { label: 'Completed', class: 'bg-[#4D9375]/10 text-[#4D9375]' },
    in_progress: {
        label: 'In progress',
        class: 'bg-[#E0AF68]/10 text-[#E0AF68]',
    },
    open: { label: 'Open', class: 'bg-[#D97757]/10 text-[#D97757]' },
    closed: { label: 'Closed', class: 'bg-[#CB7676]/10 text-[#CB7676]' },
    draft: { label: 'Draft', class: 'bg-muted text-muted-foreground' },
};
const scoreStateMeta = (state: ActivityScore['state']) =>
    SCORE_STATE_META[state] ?? SCORE_STATE_META.draft;
// Match the review modal: stop Lenis while the sheet is open so the page
// behind it cannot scroll.
watch(showScoresDrawer, (open) => {
    if (open) getLenis()?.stop();
    else getLenis()?.start();
});

// ─── Exam helpers (minimal copy from Exam.vue) ──────────────────────────────
const formatScheduleDate = (iso?: string | null) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getExamTimeInfo = (exam: Exam) => {
    // Scheduled exams show their open/close window first. Legacy exams without
    // a schedule keep the old countdown behaviour.
    if (exam.has_ended) {
        return {
            label: exam.ends_at_iso
                ? `Closed · ended ${formatScheduleDate(exam.ends_at_iso)}`
                : 'Closed',
            color: 'text-[#CB7676]',
            isOverdue: false,
        };
    }

    if (exam.status === 'closed' && !exam.has_submissions) {
        return {
            label: 'Closed',
            color: 'text-[#CB7676]',
            isOverdue: false,
        };
    }

    if (exam.is_upcoming && exam.starts_at_iso) {
        return {
            label: `Starts ${formatScheduleDate(exam.starts_at_iso)}`,
            color: 'text-[#E0AF68]',
            isOverdue: false,
        };
    }

    if (exam.is_open_now) {
        return {
            label: exam.ends_at_iso
                ? `Ends ${formatScheduleDate(exam.ends_at_iso)}`
                : 'Open',
            color: 'text-[#D97757]',
            isOverdue: false,
        };
    }

    return getLegacyExamTimeInfo(exam);
};

const getLegacyExamTimeInfo = (exam: Exam) => {
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
    if (exam.status === 'closed' || exam.has_ended)
        return { label: 'Closed', color: 'bg-[#CB7676]' };
    if (exam.is_upcoming) return { label: 'Upcoming', color: 'bg-[#E0AF68]' };
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

/**
 * Status key for the card's left accent stripe, rendered as `data-accent`.
 *
 * `resources/css/app.css` restyles `.exam-theme-page .exam-card` with an
 * unlayered `border: 1px solid … !important` shorthand, which also resets
 * `border-left-width` / `border-left-color`. Tailwind's `border-l-[3px]` and
 * `border-l-[#4D9375]/40` utilities live in `@layer utilities` without
 * `!important`, so they lose that cascade and every card came out with the
 * same flat neutral edge. A data attribute the stylesheet can target is the
 * only hook that survives the shorthand — and unlike `:style` it does not
 * collide with the `style` prop `Motion` declares.
 */
const getCardAccent = (exam: Exam): 'overdue' | 'done' | 'open' => {
    const total = exam.total_parts ?? exam.parts?.length ?? 0;
    const submitted =
        exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allDone = total > 0 && submitted >= total;
    const dateStr = exam.exam_date_iso || exam.exam_date;
    if (dateStr && !exam.is_locked) {
        const dd = new Date(dateStr);
        if (!Number.isNaN(dd.getTime()) && dd.getTime() < Date.now())
            return 'overdue';
    }
    return allDone ? 'done' : 'open';
};
const getProgressPercent = (exam: Exam) => {
    if (!exam.total_parts) return 0;
    return ((exam.submitted_parts_count ?? 0) / exam.total_parts) * 100;
};
const hasSubmitted = (exam: Exam) =>
    exam.has_submissions ?? (exam.submissions?.length ?? 0) > 0;
const canReviewResults = (exam: Exam) =>
    exam.results_available ??
    ((exam.status === 'closed' || exam.has_ended) && hasSubmitted(exam));
const isAwaitingClose = (exam: Exam) =>
    !canReviewResults(exam) && hasSubmitted(exam) && exam.is_locked === true;

// ─── Schedule window gate ───────────────────────────────────────────────────
// The server computes is_open_now / is_upcoming / has_ended against
// `starts_at` / `ends_at` (see ActivityHubController::examPage) and the hub
// re-polls every 10s, so a card unlocks on its own once the window opens.
// The fallbacks mirror Exams/Show.vue: a legacy payload (old cached page
// props) carrying none of the flags behaves like a published exam that is
// open, so a missing field never locks a student out.
const isUpcoming = (exam: Exam) => exam.is_upcoming ?? false;
const isOpenNow = (exam: Exam) =>
    exam.is_open_now ??
    (exam.status === 'published' && !isUpcoming(exam) && !exam.has_ended);
/**
 * Whether the card may take the student into the exam right now. Anything
 * that is locked (done / closed / ended) or has not opened yet is a dead end
 * on the exam page anyway — the server rejects start / save / submit outside
 * the window — so the hub must not offer a live "Start" for it.
 */
const canStart = (exam: Exam) =>
    !exam.is_locked && !isUpcoming(exam) && isOpenNow(exam);
/** The card is interactive when it can either start the exam or review it. */
const isCardInteractive = (exam: Exam) =>
    canStart(exam) || canReviewResults(exam);

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
        (selectedExamForReview.value.status === 'closed' ||
            selectedExamForReview.value.has_ended) &&
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
        // Desktop: reka-ui's DialogOverlay locks body scroll itself. Setting
        // body overflow here first would make reka snapshot 'hidden' as the
        // "previous" value and restore it when the dialog closes — permanently
        // scroll-locking the page. Only the custom mobile bottom sheet (which
        // has no reka overlay) needs a manual lock.
        if (isMobile.value) document.body.style.overflow = 'hidden';
        getLenis()?.stop();
        await nextTick();
        await new Promise((r) => requestAnimationFrame(r));
        scrollRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        if (isMobile.value) document.body.style.overflow = '';
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
    // Not open yet (or otherwise not accepting answers): the whole card is a
    // button, so guard here too — not just on the "Start" control.
    if (!canStart(exam)) return;
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
        body: 'A focused place to review your exams, deadlines, and results.',
    },
    {
        id: 'search',
        target: 'hub-search',
        title: 'Search everything',
        body: 'Find an exam by title or description instantly.',
    },
    {
        id: 'sections',
        target: 'hub-sections',
        title: 'Filter by section',
        body: 'If you are in multiple sections, filter activities per section.',
    },
];
</script>

<template>
    <Head title="Activities Hub" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="student-ui activities-ui-page mobile-ui-page exam-theme-page relative flex flex-col gap-3 overflow-x-hidden bg-background p-3 perspective-[1000px] sm:gap-5 sm:p-6 md:p-8"
        >
            <!-- Header -->
            <Motion
                class="mobile-existing-header space-y-2"
                :initial="{ opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.8, easing: [0.16, 1, 0.3, 1] }"
            >
                <div class="flex items-start justify-between gap-3 sm:gap-4">
                    <div>
                        <h1
                            class="dash-title text-[22px] text-foreground sm:text-[34px]"
                        >
                            Activities Hub
                        </h1>
                        <p
                            class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                        >
                            Review your exams, deadlines, and results.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0 items-center gap-2 rounded-full border-border/50 bg-card px-4 text-sm font-semibold text-foreground hover:bg-muted sm:h-10 sm:px-5"
                        @click="showScoresDrawer = true"
                    >
                        <Award class="h-4 w-4 text-[#D97757]" />
                        My Scores
                    </Button>
                </div>
            </Motion>

            <!-- Overview — quiet, editorial cards with clear hierarchy -->
            <Motion
                :initial="{ opacity: 0, y: 12 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.35, delay: 0.05 }"
                class="activities-mobile-stats hidden divide-x divide-border/70 overflow-hidden rounded-2xl border border-border/70 bg-card sm:grid sm:grid-cols-4"
            >
                <div class="min-w-0 p-3.5 sm:p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        All exams
                    </p>
                    <p
                        class="mt-2 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                    >
                        {{ hubStats.exams.total }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Available in your sections
                    </p>
                </div>
                <div class="min-w-0 p-3.5 sm:p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Needs attention
                    </p>
                    <p
                        class="mt-2 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                    >
                        {{ hubStats.exams.pending }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Ready to complete
                    </p>
                </div>
                <div class="min-w-0 p-3.5 sm:p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Completed
                    </p>
                    <p
                        class="mt-2 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                    >
                        {{ hubStats.exams.completed }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Completed exams
                    </p>
                </div>
                <div class="min-w-0 p-3.5 sm:p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Completion
                    </p>
                    <p
                        class="mt-2 text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"
                    >
                        {{ completionRate }}%
                    </p>
                    <div
                        class="mt-3 h-1 w-full overflow-hidden rounded-full bg-muted"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full rounded-full bg-foreground transition-[width] duration-500"
                            :style="{ width: `${completionRate}%` }"
                        ></div>
                    </div>
                </div>
            </Motion>

            <!-- Search -->
            <div
                class="activities-mobile-search relative"
                data-tour="hub-search"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/50 sm:left-4 sm:h-5 sm:w-5"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search exams..."
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

            <!-- Exams tab -->
            <template v-if="filteredExamsBySeason.length > 0">
                <Motion
                    v-for="(seasonGroup, sIdx) in filteredExamsBySeason"
                    :key="sIdx"
                    :initial="{ opacity: 0, y: 30 }"
                    :animate="{ opacity: 1, y: 0 }"
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
                            :animate="{ opacity: 1, y: 0 }"
                            :transition="{
                                duration: 0.5,
                                easing: [0.16, 1, 0.3, 1],
                                delay: eIdx * 0.05,
                            }"
                            class="exam-card flex min-h-[6.25rem] min-w-0 flex-col justify-between rounded-xl border border-l-[3px] bg-card p-3 transition-colors duration-200 sm:min-h-[7.5rem] sm:rounded-[1.25rem] sm:p-5"
                            :class="[
                                isCardInteractive(exam)
                                    ? 'cursor-pointer hover:bg-muted/30'
                                    : 'cursor-default opacity-80',
                                getCardStatusClass(exam),
                            ]"
                            :data-accent="getCardAccent(exam)"
                            role="button"
                            tabindex="0"
                            @click="openExam(exam)"
                            @keydown.enter.prevent="openExam(exam)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div
                                        class="mb-2 flex items-start justify-between gap-2"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[12px] font-semibold text-white sm:text-[13px]"
                                            :class="
                                                getStatusBadgeInfo(exam).color
                                            "
                                            >{{
                                                getStatusBadgeInfo(exam).label
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
                                                            parseFloat(s.score),
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

                                        <!-- Which version of the exam this
                                             student was handed. Populated once
                                             they open the exam. -->
                                        <div
                                            v-if="exam.set?.title"
                                            class="flex items-center gap-1.5"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full border border-[#D97757]/25 bg-[#D97757]/10 px-2 py-0.5 text-[12px] font-semibold text-[#D97757]"
                                            >
                                                <Layers class="h-3 w-3" />
                                                {{ exam.set.title }}
                                            </span>
                                        </div>
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
                                                        getExamTimeInfo(exam)
                                                            .color
                                                    "
                                                /><span
                                                    :class="
                                                        getExamTimeInfo(exam)
                                                            .color
                                                    "
                                                    class="text-[13px] font-medium"
                                                    >{{
                                                        getExamTimeInfo(exam)
                                                            .label
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <ArrowRight
                                    v-if="isCardInteractive(exam)"
                                    class="h-5 w-5 shrink-0 text-muted-foreground/50 sm:hidden"
                                />
                                <Lock
                                    v-else-if="
                                        isAwaitingClose(exam) ||
                                        isUpcoming(exam)
                                    "
                                    class="h-4 w-4 shrink-0 text-muted-foreground/50 sm:hidden"
                                />
                            </div>
                            <div class="mt-3 sm:block">
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
                                    <Lock class="h-3.5 w-3.5" /> Results locked
                                </button>
                                <span
                                    v-else-if="exam.is_locked"
                                    class="dash-btn flex w-full cursor-default items-center justify-center bg-muted/20 text-[15px] text-muted-foreground"
                                    >Closed</span
                                >
                                <!-- Scheduled but not open yet: no way in until starts_at. -->
                                <button
                                    v-else-if="!canStart(exam)"
                                    type="button"
                                    disabled
                                    :title="
                                        exam.starts_at_iso
                                            ? `This exam opens on ${formatScheduleDate(exam.starts_at_iso)}.`
                                            : 'This exam is not accepting answers right now.'
                                    "
                                    class="dash-btn flex w-full cursor-not-allowed items-center justify-center gap-1.5 bg-muted/20 text-[15px] text-muted-foreground"
                                    @click.stop
                                >
                                    <Lock class="h-3.5 w-3.5" />
                                    {{
                                        exam.starts_at_iso
                                            ? `Opens ${formatScheduleDate(exam.starts_at_iso)}`
                                            : 'Not yet open'
                                    }}
                                </button>
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
                            isLoadingMoreExams ? 'Loading…' : 'Load more exams'
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
        </div>

        <OnboardingTour
            tour-id="activities-hub"
            :steps="activitiesTourSteps"
            :can-start="!showReviewModal && !showScoresDrawer"
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
                                            data-lenis-prevent
                                            @wheel.stop
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
                                            data-lenis-prevent
                                            @wheel.stop
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

    <!-- My Scores drawer — every visible activity with its total score -->
    <Sheet :open="showScoresDrawer" @update:open="showScoresDrawer = $event">
        <SheetContent class="w-full gap-0 sm:max-w-md">
            <SheetHeader class="pb-3">
                <div class="flex items-center gap-2">
                    <Award class="h-5 w-5 text-[#D97757]" />
                    <SheetTitle class="text-lg font-bold text-foreground"
                        >My Scores</SheetTitle
                    >
                </div>
                <SheetDescription class="text-xs text-muted-foreground"
                    >{{ gradedCount }} of {{ scoreAll.length }} activities
                    graded</SheetDescription
                >
            </SheetHeader>
            <div
                class="custom-scrollbar flex-1 overflow-y-auto px-3 pb-4"
                data-lenis-prevent
            >
                <div
                    v-if="scoreAll.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-16 text-center"
                >
                    <Calendar class="h-10 w-10 text-muted-foreground/40" />
                    <p class="text-sm font-medium text-foreground">
                        No activities yet
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Your scores will appear here once exams are published.
                    </p>
                </div>
                <div v-else class="space-y-5">
                    <div
                        v-for="(group, gIdx) in scoreGroups"
                        :key="gIdx"
                        class="space-y-1.5"
                    >
                        <div class="flex items-center gap-2 px-1">
                            <Calendar class="h-3.5 w-3.5 text-primary" />
                            <h3
                                class="text-[13px] font-semibold text-foreground"
                            >
                                {{ group.seasonName }}
                            </h3>
                            <span
                                class="text-[11px] font-medium text-muted-foreground tabular-nums"
                                >{{ group.exams.length }}
                                {{
                                    group.exams.length === 1
                                        ? 'activity'
                                        : 'activities'
                                }}</span
                            >
                        </div>
                        <ul
                            class="overflow-hidden rounded-xl border border-border/60 bg-card"
                        >
                            <li
                                v-for="(activity, aIdx) in group.exams"
                                :key="activity.id"
                                class="flex items-center justify-between gap-3 px-3 py-2.5 sm:px-4"
                                :class="
                                    aIdx > 0 ? 'border-t border-border/50' : ''
                                "
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-[13px] font-medium text-foreground sm:text-sm"
                                    >
                                        {{ activity.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 truncate text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            activity.section_name ??
                                            'No section'
                                        }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            scoreStateMeta(activity.state).class
                                        "
                                        >{{
                                            scoreStateMeta(activity.state).label
                                        }}</span
                                    >
                                    <span
                                        v-if="activity.score !== null"
                                        class="w-12 text-right text-sm font-bold text-foreground tabular-nums"
                                        >{{ activity.score.toFixed(1) }}</span
                                    >
                                    <span
                                        v-else
                                        class="w-12 text-right text-sm text-muted-foreground/50"
                                        aria-label="No score yet"
                                        >—</span
                                    >
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
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
