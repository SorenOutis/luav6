<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import axios from 'axios';
import {
    Calendar,
    Clock,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    XCircle,
    Shield,
    ArrowRight,
    Zap,
    Timer,
    Search,
    Lock,
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

// The server data behind this page can go stale the moment a student submits
// an exam elsewhere (success-modal redirect, browser back/forward, or an exam
// that opens in a new tab). The 10s poll eventually catches up, but we also
// refresh the instant the page is shown again so the exam cards reflect the
// latest submission without a hard refresh.
const { stop: stopPoll, start: startPoll } = usePoll(
    10000,
    { only: ['examsBySeason'] },
    { autoStart: false },
);

const refreshExams = () => {
    router.reload({ only: ['examsBySeason'] });
};

// Covers tab switches back to the Activities tab — e.g. exams that open in a
// new tab (`target="_blank"`) leave this page mounted underneath with stale
// props, so returning to the tab must re-fetch immediately.
const handleVisibilityChange = () => {
    if (!document.hidden) {
        refreshExams();
    }
};

onMounted(() => {
    startPoll();
    // Skip the refresh on the session's very first mount: the server just
    // rendered fresh props, so a reload would be a wasted request. Every
    // later remount may be a stale restore (prefetch cache / history state /
    // back-nav), so fetch fresh data right away instead of waiting for the
    // first poll tick. Skipped while the tab is hidden — the visibility
    // handler refreshes as soon as it becomes visible again.
    if (hasPageMountedBefore('exams') && !document.hidden) {
        refreshExams();
    }
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    stopPoll();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

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

const props = defineProps<{
    examsBySeason: SeasonGroup[];
    examPagination?: {
        hasMore: boolean;
        nextCursor: string | null;
    };
}>();

const examGroups = ref<SeasonGroup[]>(
    props.examsBySeason.map((group) => ({
        ...group,
        exams: [...group.exams],
    })),
);
const examCursor = ref<string | null>(props.examPagination?.nextCursor ?? null);
const hasLoadedMoreExams = ref(false);
const hasMoreExams = ref(props.examPagination?.hasMore ?? false);
const isLoadingMoreExams = ref(false);
const isLoadingReview = ref(false);

const mergeExamGroups = (incoming: SeasonGroup[], prepend = false) => {
    const merged = new Map<string, Exam[]>();

    for (const group of examGroups.value) {
        merged.set(group.seasonName, [...group.exams]);
    }

    for (const group of incoming) {
        const existing = merged.get(group.seasonName) ?? [];
        const incomingIds = new Set(group.exams.map((exam) => exam.id));
        merged.set(
            group.seasonName,
            prepend
                ? [
                      ...group.exams,
                      ...existing.filter((exam) => !incomingIds.has(exam.id)),
                  ]
                : [
                      ...existing,
                      ...group.exams.filter(
                          (exam) =>
                              !existing.some(
                                  (current) => current.id === exam.id,
                              ),
                      ),
                  ],
        );
    }

    const preferredOrder = [
        ...incoming.map((group) => group.seasonName),
        ...examGroups.value.map((group) => group.seasonName),
    ];
    examGroups.value = [...new Set(preferredOrder)].map((seasonName) => ({
        seasonName,
        exams: merged.get(seasonName) ?? [],
    }));
};

watch(
    () => props.examsBySeason,
    (groups) => {
        if (!hasLoadedMoreExams.value) {
            examGroups.value = groups.map((group) => ({
                ...group,
                exams: [...group.exams],
            }));
            hasMoreExams.value = props.examPagination?.hasMore ?? false;
            examCursor.value = props.examPagination?.nextCursor ?? null;
        } else {
            mergeExamGroups(groups, true);
        }
    },
);

const showReviewModal = ref(false);
const selectedExamForReview = ref<Exam | null>(null);
const selectedPartId = ref<number | null>(null);
// One-question-at-a-time review on mobile, mirroring the exam-taking carousel.
const selectedQuestionIndex = ref(0);
const { isMobile } = useMobile();

// --- Filter State ---
const activeFilter = ref<'all' | 'active' | 'completed'>('all');
const activeSection = ref('all');
const searchQuery = ref('');

const getExamSectionName = (exam: Exam) =>
    exam.section_name?.trim() || 'General';

// Flatten all exams across all seasons for top-level filtering
const allExams = computed(() => props.examsBySeason.flatMap((sg) => sg.exams));

const statusFilteredExams = computed(() => {
    if (activeFilter.value === 'active')
        return allExams.value.filter((e) => !e.is_locked);
    if (activeFilter.value === 'completed')
        return allExams.value.filter((e) => e.is_locked);
    return allExams.value;
});

const sectionTabs = computed(() => {
    const sections = new Map<string, number>();

    statusFilteredExams.value.forEach((exam) => {
        const sectionName = getExamSectionName(exam);
        sections.set(sectionName, (sections.get(sectionName) ?? 0) + 1);
    });

    return [
        {
            key: 'all',
            label: 'All sections',
            count: statusFilteredExams.value.length,
        },
        ...Array.from(sections.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([label, count]) => ({ key: label, label, count })),
    ];
});

const filteredExams = computed(() => {
    let exams = statusFilteredExams.value;

    // Filter by section
    if (activeSection.value !== 'all') {
        exams = exams.filter(
            (exam) => getExamSectionName(exam) === activeSection.value,
        );
    }

    // Filter by search query
    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        exams = exams.filter(
            (exam) =>
                exam.title.toLowerCase().includes(query) ||
                exam.description?.toLowerCase().includes(query),
        );
    }

    return exams;
});

// Group filtered exams back by season for display
const filteredExamsBySeason = computed(() => {
    const filtered = filteredExams.value;
    const examIds = new Set(filtered.map((e) => e.id));

    return examGroups.value
        .map((sg) => ({
            seasonName: sg.seasonName,
            exams: sg.exams.filter((e) => examIds.has(e.id)),
        }))
        .filter((sg) => sg.exams.length > 0);
});

// A student may only review an exam they actually answered. The server stops
// serializing the answer key (and the questions) for a closed exam the student
// never took, so treat "no submissions" as "nothing to review" here too.
const hasSubmitted = (exam: Exam) =>
    exam.has_submissions ?? (exam.submissions?.length ?? 0) > 0;

// Results stay sealed until the exam closes, even for a student who already
// finished every part — otherwise they could pass the questions and their
// answers to classmates who are still working. `is_locked` is true as soon as
// the last part is submitted, so it is NOT sufficient on its own. The server
// enforces the same rule; the `??` fallback covers cached page props served
// during a rolling deploy.
const canReviewResults = (exam: Exam) =>
    exam.results_available ?? (exam.status === 'closed' && hasSubmitted(exam));

// Finished, but the exam is still open for everyone else.
const isAwaitingClose = (exam: Exam) =>
    !canReviewResults(exam) && hasSubmitted(exam) && exam.is_locked === true;

// --- Exam Time Info (countdown/overdue) ---
const getExamTimeInfo = (exam: Exam) => {
    if (!exam.exam_date && !exam.exam_date_iso) {
        return {
            label: 'No deadline',
            color: 'text-muted-foreground',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    const dateStr = exam.exam_date_iso || exam.exam_date;
    const examDate = new Date(dateStr);
    if (Number.isNaN(examDate.getTime())) {
        return {
            label: 'Invalid date',
            color: 'text-muted-foreground',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    const now = new Date();
    const diff = examDate.getTime() - now.getTime();

    if (exam.is_locked) {
        // A closed exam the student never answered is not "Completed" — it is
        // simply closed and has nothing for them to review.
        if (exam.status === 'closed' && !hasSubmitted(exam)) {
            return {
                label: 'Closed',
                color: 'text-[#CB7676]',
                isOverdue: false,
                isUpcoming: false,
            };
        }

        return {
            label: `Completed ${examDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
            color: 'text-[#4D9375]',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    if (diff < 0) {
        return {
            label: 'Overdue',
            color: 'text-[#CB7676]',
            isOverdue: true,
            isUpcoming: false,
        };
    }
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    if (days > 0) {
        return {
            label: `${days}d ${hours}h left`,
            color: 'text-[#E0AF68]',
            isOverdue: false,
            isUpcoming: true,
        };
    }
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return {
        label: `${hours}h ${minutes}m left`,
        color: 'text-[#CB7676]',
        isOverdue: false,
        isUpcoming: true,
    };
};

// --- Status Badge Info ---
const getStatusBadgeInfo = (exam: Exam) => {
    const totalParts = exam.total_parts ?? exam.parts?.length ?? 0;
    const submittedParts =
        exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allPartsDone = totalParts > 0 && submittedParts >= totalParts;

    if (allPartsDone) return { label: 'Completed', color: 'bg-[#4D9375]' };
    if (exam.is_locked && exam.status === 'closed')
        return { label: 'Closed', color: 'bg-[#CB7676]' };
    if (exam.is_locked) return { label: 'In progress', color: 'bg-[#E0AF68]' };
    if (exam.status === 'published')
        return { label: 'Open', color: 'bg-[#D97757]' };
    if (exam.status === 'closed')
        return { label: 'Closed', color: 'bg-[#CB7676]' };
    return { label: 'Draft', color: 'bg-muted text-muted-foreground' };
};

// Status-based card border styling
const getCardStatusClass = (exam: Exam) => {
    const totalParts = exam.total_parts ?? exam.parts?.length ?? 0;
    const submittedParts =
        exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allPartsDone = totalParts > 0 && submittedParts >= totalParts;

    // Check if overdue
    const dateStr = exam.exam_date_iso || exam.exam_date;
    if (dateStr && !exam.is_locked) {
        const examDate = new Date(dateStr);
        if (
            !Number.isNaN(examDate.getTime()) &&
            examDate.getTime() < Date.now()
        ) {
            return 'border-l-[#CB7676]/40 hover:border-l-[#CB7676]/60';
        }
    }

    if (allPartsDone)
        return 'border-l-[#4D9375]/40 hover:border-l-[#4D9375]/60';
    if (exam.is_locked) return '';
    return 'border-l-primary/20 hover:border-l-primary/40';
};

// --- Progress Percentage ---
const getProgressPercent = (exam: Exam) => {
    if (!exam.total_parts || exam.total_parts === 0) return 0;
    return ((exam.submitted_parts_count ?? 0) / exam.total_parts) * 100;
};

const answersRevealed = computed(
    () =>
        selectedExamForReview.value !== null &&
        selectedExamForReview.value.status === 'closed' &&
        hasSubmitted(selectedExamForReview.value),
);

const loadMoreExams = async () => {
    if (!hasMoreExams.value || isLoadingMoreExams.value) return;

    isLoadingMoreExams.value = true;
    try {
        const response = await axios.get('/api/exams', {
            params: { cursor: examCursor.value },
        });
        mergeExamGroups(response.data.data ?? []);
        hasLoadedMoreExams.value = true;
        examCursor.value = response.data.meta?.nextCursor ?? null;
        hasMoreExams.value = Boolean(response.data.meta?.hasMore);
    } catch (error) {
        console.error('Failed to load more exams:', error);
    } finally {
        isLoadingMoreExams.value = false;
    }
};

const openReview = async (exam: Exam) => {
    if (!canReviewResults(exam) || isLoadingReview.value) {
        return;
    }

    selectedExamForReview.value = exam;
    selectedPartId.value = exam.parts.length > 0 ? exam.parts[0].id : null;
    selectedQuestionIndex.value = 0;
    showReviewModal.value = true;

    // Keep compatibility with cached/rolling-deploy page props that already
    // contain a complete review payload. New responses use the lighter cards.
    const alreadyLoaded =
        exam.parts.some((part) => (part.questions?.length ?? 0) > 0) &&
        (exam.submissions ?? []).some((submission) =>
            Array.isArray(submission.answers),
        );
    if (alreadyLoaded) {
        return;
    }

    isLoadingReview.value = true;

    try {
        const response = await axios.get(`/exams/${exam.id}/review`);
        const reviewedExam = response.data.exam as Exam;
        selectedExamForReview.value = {
            ...exam,
            ...reviewedExam,
            submissions: response.data.submissions ?? [],
        };
        selectedPartId.value = reviewedExam.parts?.[0]?.id ?? null;
    } catch (error) {
        console.error('Failed to load exam review:', error);
        showReviewModal.value = false;
    } finally {
        isLoadingReview.value = false;
    }
};

const openExam = (exam: Exam) => {
    if (exam.is_locked) {
        // Only a closed exam the student actually answered has results to
        // show. A closed exam they skipped would leak the questions, and an
        // exam they merely finished is still live for their classmates.
        if (canReviewResults(exam)) {
            openReview(exam);
        }
        return;
    }
    if (exam.url) {
        window.open(exam.url, '_blank', 'noopener');
        return;
    }
    router.visit(examsShow(exam.id).url);
};

const getSubmissionForPart = (exam: Exam, partId: number) => {
    return exam.submissions?.find((s) => s.exam_part_id === partId);
};

const getAnswerForQuestion = (answers: any, questionNumber: number) => {
    const entry = getAnswerObjectForQuestion(answers, questionNumber);
    return entry ? entry.answer : null;
};

const getAnswerObjectForQuestion = (answers: any, questionNumber: number) => {
    // If answers is a string (JSON), parse it
    let parsedAnswers = answers;
    if (typeof answers === 'string') {
        try {
            parsedAnswers = JSON.parse(answers);
        } catch {
            return null;
        }
    }

    if (!Array.isArray(parsedAnswers)) return null;

    return parsedAnswers.find((a: any) => a.question_number === questionNumber);
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
        // For essays, we consider it "correct" if it has an AI score > 0
        return (answerObject?.ai_score ?? 0) > 0;
    }
    return false;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
];

// ─── Onboarding tour ────────────────────────────────────────────────────────
// Per user + per device (localStorage): a login on a new device replays the
// walkthrough. Steps whose target isn't on screen (no exams yet, single
// section) are skipped automatically. Held while the review modal is open.
const activitiesTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Welcome to Activities',
        body: 'All your exams and assessments live here, grouped by season. Here’s a quick look around.',
    },
    {
        id: 'search',
        target: 'exams-search',
        title: 'Find an exam fast',
        body: 'Search by title to jump straight to the exam you need — handy once a season fills up.',
    },
    {
        id: 'sections',
        target: 'exams-sections',
        title: 'Filter by section',
        body: 'Enrolled in more than one section? Use these tabs to see only that section’s activities.',
    },
    {
        id: 'seasons',
        target: 'exams-season',
        title: 'Grouped by season',
        body: 'Exams are organized under their season so you always know which grading period they belong to.',
    },
    {
        id: 'card',
        target: 'exams-card',
        title: 'Everything on the card',
        body: 'Each card shows status, parts submitted, duration and score. Tap a card to open the exam — once your teacher closes it, the card opens a full review of your answers.',
    },
];

const isExamsTourActive = ref(false);

watch(sectionTabs, (tabs) => {
    if (!tabs.some((tab) => tab.key === activeSection.value)) {
        activeSection.value = 'all';
    }
});

// Lock body scroll, stop Lenis, & reset scroll position when review modal opens
watch(showReviewModal, async (isOpen) => {
    if (isOpen) {
        // Desktop: reka-ui's DialogOverlay locks body scroll itself. Setting
        // body overflow here first would make reka snapshot 'hidden' as the
        // "previous" value and restore it when the dialog closes — permanently
        // scroll-locking the page. Only the custom mobile bottom sheet (which
        // has no reka overlay) needs a manual lock.
        if (isMobile.value) document.body.style.overflow = 'hidden';
        getLenis()?.stop();
        await nextTick();
        // Wait one frame for modal entrance animation to settle
        await new Promise((r) => requestAnimationFrame(r));
        if (scrollRef.value) {
            scrollRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } else {
        if (isMobile.value) document.body.style.overflow = '';
        getLenis()?.start();
    }
});

// --- Scrollbar auto-hide: visible while scrolling, fades out after 1.5s idle ---
const scrollRef = ref<HTMLElement | null>(null);
let scrollTimer: ReturnType<typeof setTimeout> | null = null;

function showScrollbar() {
    if (!scrollRef.value) return;
    scrollRef.value.classList.add('scrolling');
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
        scrollRef.value?.classList.remove('scrolling');
    }, 1500);
}

// ─── Mobile: one-question-at-a-time review navigation ─────────────
// Mirrors the mobile carousel in the exam-taking screen so reviewing results
// feels consistent: a single question card with Prev / Next controls plus the
// part tab bar for jumping between sections.
const selectedPartQuestions = computed(
    () =>
        selectedExamForReview.value?.parts.find(
            (part) => part.id === selectedPartId.value,
        )?.questions ?? [],
);

const canGoToPrevQuestion = computed(() => selectedQuestionIndex.value > 0);

const canGoToNextQuestion = computed(
    () => selectedQuestionIndex.value < selectedPartQuestions.value.length - 1,
);

const scrollReviewToTop = () => {
    nextTick(() => {
        // On desktop `scrollRef` is the scroll container; on mobile the bottom
        // sheet wraps it in another scrollable div, so reset both.
        scrollRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
        const wrapper = scrollRef.value?.parentElement;
        if (wrapper && wrapper !== scrollRef.value) {
            wrapper.scrollTo({ top: 0, behavior: 'smooth' });
        }
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

// Keep the question index in bounds when switching parts (tabs / footer nav).
watch(selectedPartId, () => {
    selectedQuestionIndex.value = 0;
    scrollReviewToTop();
});
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="examContainer"
            class="student-ui exam-theme-page relative flex h-full flex-1 flex-col gap-3 overflow-hidden bg-background p-3 perspective-[1000px] sm:gap-5 sm:p-6 md:p-8"
        >
            <!-- Header Section -->
            <Motion
                :initial="{ opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.8, easing: [0.16, 1, 0.3, 1] }"
                class="space-y-2"
            >
                <div>
                    <h1
                        class="dash-title text-[22px] text-foreground sm:text-[34px]"
                    >
                        Activities
                    </h1>
                    <p
                        class="mt-0.5 text-[13px] text-muted-foreground sm:mt-1 sm:text-[17px]"
                    >
                        View and take your assessments.
                    </p>
                </div>
            </Motion>

            <!-- Search Input -->
            <div class="relative" data-tour="exams-search">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground/50 sm:left-4 sm:h-5 sm:w-5"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search exams"
                    class="min-h-11 w-full rounded-full border border-border/50 bg-card py-2 pr-4 pl-10 text-[16px] outline-none placeholder:text-muted-foreground/50 focus:border-[#D97757]/40 focus:ring-2 focus:ring-[#D97757]/20 sm:py-3 sm:pl-12"
                />
            </div>

            <!-- Section Tabs (Sticky) -->
            <div
                v-if="sectionTabs.length > 1"
                data-tour="exams-sections"
                class="no-scrollbar sticky top-0 z-20 -mx-3 flex items-center gap-2 overflow-x-auto border-b border-transparent bg-background/80 px-3 pt-1.5 pb-2 backdrop-blur-md sm:gap-3 sm:pt-3 sm:pb-4 md:-mx-8 md:px-8"
            >
                <button
                    v-for="section in sectionTabs"
                    :key="section.key"
                    @click="activeSection = section.key"
                    class="dash-btn flex shrink-0 items-center gap-2 border px-4 text-left"
                    :class="
                        activeSection === section.key
                            ? 'border-transparent bg-[#D97757] text-white'
                            : 'border-border/50 bg-card text-muted-foreground hover:bg-muted'
                    "
                >
                    <span
                        class="text-sm font-medium"
                        :class="
                            activeSection === section.key
                                ? 'text-white'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ section.label }}
                    </span>
                    <span
                        class="flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-[13px] font-semibold"
                        :class="
                            activeSection === section.key
                                ? 'bg-white/20 text-white'
                                : 'bg-muted/50 text-muted-foreground'
                        "
                    >
                        {{ section.count }}
                    </span>
                </button>
            </div>

            <!-- Season-Grouped Exam Grids -->
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
                        delay: sIdx * 0.1,
                    }"
                    class="space-y-3 sm:space-y-5"
                >
                    <!-- Season Header -->
                    <div
                        :data-tour="sIdx === 0 ? 'exams-season' : undefined"
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
                        >
                            {{ seasonGroup.exams.length }}
                            {{
                                seasonGroup.exams.length === 1
                                    ? 'exam'
                                    : 'exams'
                            }}
                        </span>
                    </div>

                    <!-- Exam Grid for this season -->
                    <div
                        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 xl:grid-cols-4"
                    >
                        <Motion
                            v-for="(exam, eIdx) in seasonGroup.exams"
                            :key="exam.id"
                            :initial="{ opacity: 0, y: 20 }"
                            :in-view="{ opacity: 1, y: 0 }"
                            :in-view-options="{ once: true, margin: '-30px' }"
                            :transition="{
                                duration: 0.5,
                                easing: [0.16, 1, 0.3, 1],
                                delay: eIdx * 0.05,
                            }"
                            :data-tour="
                                sIdx === 0 && eIdx === 0
                                    ? 'exams-card'
                                    : undefined
                            "
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
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <!-- Status Badge + Score -->
                                    <div
                                        class="mb-2 flex items-start justify-between gap-2"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[12px] font-semibold text-white sm:text-[13px]"
                                            :class="
                                                getStatusBadgeInfo(exam).color
                                            "
                                        >
                                            {{ getStatusBadgeInfo(exam).label }}
                                        </span>
                                        <span
                                            v-if="
                                                exam.is_locked &&
                                                hasSubmitted(exam)
                                            "
                                            class="inline-flex items-center rounded-full bg-[#D97757]/10 px-2.5 py-1 text-[13px] font-semibold text-[#D97757] tabular-nums"
                                        >
                                            {{
                                                exam.submissions
                                                    ?.reduce(
                                                        (acc, s) =>
                                                            acc +
                                                            parseFloat(s.score),
                                                        0,
                                                    )
                                                    .toFixed(1)
                                            }}
                                        </span>
                                    </div>

                                    <div class="flex-1 space-y-1.5">
                                        <!-- Section Label -->
                                        <div
                                            v-if="exam.section_name"
                                            class="text-[13px] font-medium text-muted-foreground"
                                        >
                                            {{ exam.section_name }}
                                        </div>

                                        <!-- Title -->
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

                                        <!-- Progress Bar -->
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
                                                >
                                                    {{
                                                        exam.submitted_parts_count
                                                    }}
                                                    /
                                                    {{ exam.total_parts }} parts
                                                </span>
                                                <span
                                                    v-if="!exam.is_locked"
                                                    class="text-muted-foreground"
                                                >
                                                    {{
                                                        Math.round(
                                                            getProgressPercent(
                                                                exam,
                                                            ),
                                                        )
                                                    }}%
                                                </span>
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

                                        <!-- Meta Info -->
                                        <div
                                            class="flex items-center gap-2.5 pt-1.5 text-xs text-muted-foreground sm:gap-3 sm:pt-2 sm:text-sm"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <Clock
                                                    class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                                />
                                                <span
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
                                                />
                                                <span
                                                    :class="
                                                        getExamTimeInfo(exam)
                                                            .color
                                                    "
                                                    class="text-[13px] font-medium"
                                                >
                                                    {{
                                                        getExamTimeInfo(exam)
                                                            .label
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chevron for list style on mobile. Swapped for
                                     a lock when the card has nothing to open yet
                                     (finished, but the exam is still running). -->
                                <ArrowRight
                                    v-if="
                                        !exam.is_locked ||
                                        canReviewResults(exam)
                                    "
                                    class="h-5 w-5 flex-shrink-0 text-muted-foreground/50 sm:hidden"
                                />
                                <Lock
                                    v-else-if="isAwaitingClose(exam)"
                                    class="h-4 w-4 flex-shrink-0 text-muted-foreground/50 sm:hidden"
                                />
                            </div>

                            <!-- Action Button (only show on sm+) -->
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
                                    title="Your answers unlock once your teacher closes this exam."
                                    class="dash-btn flex w-full cursor-not-allowed items-center justify-center gap-1.5 bg-muted/20 text-[15px] text-muted-foreground"
                                    @click.stop
                                >
                                    <Lock class="h-3.5 w-3.5" />
                                    Results locked
                                </button>
                                <span
                                    v-else-if="exam.is_locked"
                                    class="dash-btn flex w-full cursor-default items-center justify-center bg-muted/20 text-[15px] text-muted-foreground"
                                >
                                    Closed
                                </span>
                                <a
                                    v-else-if="exam.url"
                                    :href="exam.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="dash-btn flex w-full items-center justify-center gap-2 bg-[#D97757] text-[15px] text-white hover:bg-[#D97757]/90"
                                    @click.stop
                                >
                                    Start
                                    <ArrowRight class="h-4 w-4" />
                                </a>
                                <Link
                                    v-else
                                    :href="examsShow(exam.id).url"
                                    class="dash-btn flex w-full items-center justify-center gap-2 bg-[#D97757] text-[15px] text-white hover:bg-[#D97757]/90"
                                    @click.stop
                                >
                                    Start
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
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
                    >
                        {{
                            isLoadingMoreExams
                                ? 'Loading activities…'
                                : 'Load more activities'
                        }}
                    </Button>
                </div>
            </template>

            <!-- Empty State -->
            <Motion
                v-else
                :initial="{ opacity: 0, scale: 0.95 }"
                :animate="{ opacity: 1, scale: 1 }"
                :transition="{ duration: 0.6, easing: [0.16, 1, 0.3, 1] }"
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
                    <p
                        v-if="activeSection !== 'all'"
                        class="text-[15px] text-muted-foreground"
                    >
                        Try selecting a different section to see more exams.
                    </p>
                    <p
                        v-else-if="searchQuery"
                        class="text-[15px] text-muted-foreground"
                    >
                        No exams match your search. Try a different keyword.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Keep an eye out! Your instructor will post new exams
                        here.
                    </p>
                </div>
            </Motion>
        </div>

        <!-- First-visit walkthrough (per user, per device) -->
        <OnboardingTour
            tour-id="activities"
            :steps="activitiesTourSteps"
            :can-start="!showReviewModal"
            :start-delay="900"
            @start="isExamsTourActive = true"
            @finish="isExamsTourActive = false"
            @skip="isExamsTourActive = false"
        />
    </AppLayout>

    <!-- Review Modal -->
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
                    <DialogTitle class="text-lg font-bold text-foreground">
                        {{ selectedExamForReview?.title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Review your answers and feedback.
                    </DialogDescription>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="rounded-lg bg-primary/5 px-3 py-1.5 text-right">
                        <span
                            class="text-[9px] font-medium text-muted-foreground"
                            >Score</span
                        >
                        <span
                            class="ml-1.5 text-base font-bold text-foreground tabular-nums"
                        >
                            {{
                                selectedExamForReview?.submissions
                                    ?.reduce(
                                        (acc, s) => acc + parseFloat(s.score),
                                        0,
                                    )
                                    .toFixed(1)
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Part Navigation Tabs -->
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

        <!-- Scrollable Content -->
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
            <div v-else-if="selectedExamForReview" class="space-y-8">
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
                        >
                            Score:
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
                            }}
                        </span>
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
                            <!-- Privacy Overlay: visible by default, fades on hover to reveal content -->
                            <div
                                class="question-reveal-overlay pointer-events-none absolute inset-0 z-20 hidden items-center justify-center opacity-100 transition-opacity duration-300 md:flex"
                            >
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-primary/20 bg-background/90 px-4 py-2 shadow-lg backdrop-blur-sm"
                                >
                                    <Shield class="h-4 w-4 text-primary" />
                                    <span
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
                                        >
                                            #{{ qIndex + 1 }}
                                        </span>
                                        <span
                                            class="rounded-md bg-muted/50 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            {{
                                                question.type.replace('_', ' ')
                                            }}
                                        </span>
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
                                            />
                                            <XCircle
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
                                            >
                                                {{
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
                                                }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <CheckCircle2
                                                class="h-3.5 w-3.5 text-muted-foreground/40"
                                            />
                                            <span
                                                class="text-muted-foreground/60"
                                                >Submitted</span
                                            >
                                        </template>
                                    </div>
                                </div>

                                <p
                                    class="text-sm leading-relaxed font-medium text-foreground"
                                >
                                    {{ question.text }}
                                </p>

                                <!-- Multiple Choice / True False -->
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
                                        }}</span>
                                        <span
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
                                        >
                                            Your answer
                                        </span>
                                    </div>
                                </div>

                                <!-- Identification -->
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

                                <!-- Essay -->
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
                                        <!-- The essay text must NOT be a scroll container on
                                             mobile: `overflow-y: auto` + `overscroll-behavior: contain`
                                             cuts the scroll chain even when the element has nothing to
                                             scroll (modern Chrome/Safari), so a touch drag started on the
                                             answer goes dead instead of scrolling the bottom sheet. It
                                             flows at full height on mobile; only the desktop modal (sm+)
                                             bounds it, and without `contain` so scrolling chains up. -->
                                        <p
                                            data-test="essay-response"
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

                                    <!-- AI Feedback -->
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
                                                />
                                                <span
                                                    class="text-[11px] font-medium text-primary"
                                                    >AI Feedback</span
                                                >
                                            </div>
                                            <span
                                                class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary tabular-nums"
                                            >
                                                Score:
                                                {{
                                                    getAnswerObjectForQuestion(
                                                        getSubmissionForPart(
                                                            selectedExamForReview,
                                                            part.id,
                                                        )?.answers,
                                                        qIndex + 1,
                                                    )?.ai_score
                                                }}
                                                / {{ question.points }}
                                            </span>
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
                                            data-test="essay-feedback"
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

                                    <div
                                        v-else-if="
                                            getSubmissionForPart(
                                                selectedExamForReview,
                                                part.id,
                                            )?.status === 'pending_ai' ||
                                            getSubmissionForPart(
                                                selectedExamForReview,
                                                part.id,
                                            )?.status === 'pending_review'
                                        "
                                        class="flex items-center gap-2 rounded-lg border border-border/40 bg-muted/20 p-3"
                                    >
                                        <Timer
                                            class="h-3.5 w-3.5 text-[#E0AF68]"
                                        />
                                        <span
                                            class="text-xs font-medium text-[#E0AF68]"
                                            >{{
                                                getSubmissionForPart(
                                                    selectedExamForReview,
                                                    part.id,
                                                )?.status === 'pending_review'
                                                    ? 'Awaiting teacher grading'
                                                    : 'Automatic AI grading pending'
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </Motion>
                    </div>
                </Motion>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <Button
                variant="secondary"
                @click="showReviewModal = false"
                class="w-full rounded-lg px-4 py-2 text-xs font-medium sm:w-auto"
            >
                Close
            </Button>

            <!-- Mobile: Prev / Next question navigation, pinned in the sheet
                 footer so long essay answers can't push it off screen. The
                 sheet footer is flex-col-reverse, so this renders above the
                 Close button. -->
            <div
                v-if="isMobile && selectedPartQuestions.length > 1"
                class="flex w-full items-center justify-between gap-2"
            >
                <button
                    @click="goToPrevQuestion"
                    :disabled="!canGoToPrevQuestion"
                    class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[11px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                    Prev
                </button>

                <span
                    class="text-[11px] font-black tracking-widest text-muted-foreground/60 uppercase"
                >
                    {{ selectedQuestionIndex + 1 }} /
                    {{ selectedPartQuestions.length }}
                </span>

                <button
                    @click="goToNextQuestion"
                    :disabled="!canGoToNextQuestion"
                    class="flex items-center gap-1.5 rounded-lg border border-border/40 px-3 py-2 text-[11px] font-bold text-muted-foreground transition-all enabled:hover:border-primary/40 enabled:hover:text-primary disabled:opacity-30"
                >
                    Next
                    <ChevronRight class="h-3.5 w-3.5" />
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
@reference "../../css/app.css";

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
