<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    Calendar,
    Clock,
    AlertCircle,
    CheckCircle2,
    XCircle,
    Shield,

    ArrowRight,
    Zap,
    Timer,
    TrendingUp,
    Search,
} from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import {
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as examsShow } from '@/routes/exams';
import { getLenis } from '@/composables/useLenis';
import type { BreadcrumbItem } from '@/types';

usePoll(10000, {
    only: ['examsBySeason'],
});

interface ExamSubmission {
    id: number;
    exam_part_id: number;
    answers: any[];
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
}>();

const showReviewModal = ref(false);
const selectedExamForReview = ref<Exam | null>(null);
const selectedPartId = ref<number | null>(null);

// --- Filter State ---
const activeFilter = ref<'all' | 'active' | 'completed'>('all');
const activeSection = ref('all');
const searchQuery = ref('');

const getExamSectionName = (exam: Exam) =>
    exam.section_name?.trim() || 'General';

// Flatten all exams across all seasons for top-level filtering
const allExams = computed(() =>
    props.examsBySeason.flatMap((sg) => sg.exams),
);

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

    return props.examsBySeason
        .map((sg) => ({
            seasonName: sg.seasonName,
            exams: sg.exams.filter((e) => examIds.has(e.id)),
        }))
        .filter((sg) => sg.exams.length > 0);
});

// --- Exam Time Info (countdown/overdue) ---
const getExamTimeInfo = (exam: Exam) => {
    if (!exam.exam_date && !exam.exam_date_iso) {
        return {
            label: 'NO_DEADLINE_SET',
            color: 'text-muted-foreground',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    const dateStr = exam.exam_date_iso || exam.exam_date;
    const examDate = new Date(dateStr);
    if (Number.isNaN(examDate.getTime())) {
        return {
            label: 'INVALID_DATE',
            color: 'text-muted-foreground',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    const now = new Date();
    const diff = examDate.getTime() - now.getTime();

    if (exam.is_locked) {
        return {
            label: `COMPLETED ${examDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
            color: 'text-emerald-500',
            isOverdue: false,
            isUpcoming: false,
        };
    }
    if (diff < 0) {
        return {
            label: 'OVERDUE',
            color: 'text-red-500',
            isOverdue: true,
            isUpcoming: false,
        };
    }
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    if (days > 0) {
        return {
            label: `${days}D ${hours}H REMAINING`,
            color: 'text-amber-500',
            isOverdue: false,
            isUpcoming: true,
        };
    }
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return {
        label: `${hours}H ${minutes}M REMAINING`,
        color: 'text-red-500',
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

    if (allPartsDone) return { label: 'Completed', color: 'bg-emerald-500' };
    if (exam.is_locked && exam.status === 'closed')
        return { label: 'Closed', color: 'bg-red-500' };
    if (exam.is_locked) return { label: 'In Progress', color: 'bg-amber-500' };
    if (exam.status === 'published')
        return { label: 'Published', color: 'bg-blue-500' };
    if (exam.status === 'closed')
        return { label: 'Closed', color: 'bg-red-500' };
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
        if (!Number.isNaN(examDate.getTime()) && examDate.getTime() < Date.now()) {
            return 'border-l-red-400/40 hover:border-l-red-400/60';
        }
    }

    if (allPartsDone) return 'border-l-emerald-400/40 hover:border-l-emerald-400/60';
    if (exam.is_locked) return '';
    return 'border-l-primary/20 hover:border-l-primary/40';
};

// --- Progress Percentage ---
const getProgressPercent = (exam: Exam) => {
    if (!exam.total_parts || exam.total_parts === 0) return 0;
    return ((exam.submitted_parts_count ?? 0) / exam.total_parts) * 100;
};

const answersRevealed = computed(() => selectedExamForReview.value?.status === 'closed');

const openReview = (exam: Exam) => {
    selectedExamForReview.value = exam;
    selectedPartId.value = exam.parts.length > 0 ? exam.parts[0].id : null;
    showReviewModal.value = true;
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

watch(sectionTabs, (tabs) => {
    if (!tabs.some((tab) => tab.key === activeSection.value)) {
        activeSection.value = 'all';
    }
});

// Lock body scroll, stop Lenis, & reset scroll position when review modal opens
watch(showReviewModal, async (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden';
        getLenis()?.stop();
        await nextTick();
        // Wait one frame for modal entrance animation to settle
        await new Promise((r) => requestAnimationFrame(r));
        if (scrollRef.value) {
            scrollRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } else {
        document.body.style.overflow = '';
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
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="examContainer"
            class="exam-theme-page relative flex h-full flex-1 flex-col gap-5 overflow-hidden bg-background p-4 perspective-[1000px] md:p-8"
        >
            <!-- Header Section -->
            <Motion
                :initial="{ opacity: 0, y: 20 }"
                :animate="{ opacity: 1, y: 0 }"
                :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }"
                class="space-y-2"
            >
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                        <TrendingUp class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Exams
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            View and take your assessments.
                        </p>
                    </div>
                </div>
            </Motion>

            <!-- Search Input -->
            <div class="relative">
                <Search class="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-muted-foreground/50" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search exams by title or description..."
                    class="w-full rounded-xl border border-border/50 bg-card py-3.5 pl-12 pr-4 text-base transition-all outline-none placeholder:text-muted-foreground/40 focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                />
            </div>

            <!-- Section Tabs (Sticky) -->
            <div
                v-if="sectionTabs.length > 1"
                class="no-scrollbar sticky top-0 z-20 -mx-4 flex items-center gap-3 overflow-x-auto border-b border-transparent bg-background/80 px-4 pb-4 pt-3 backdrop-blur-md md:-mx-8 md:px-8"
            >
                <button
                    v-for="section in sectionTabs"
                    :key="section.key"
                    @click="activeSection = section.key"
                    class="flex shrink-0 items-center gap-3 rounded-xl border px-4 py-3 text-left transition-all duration-200"
                    :class="
                        activeSection === section.key
                            ? 'border-primary/30 bg-primary/5 shadow-sm'
                            : 'border-border/40 bg-card hover:border-primary/20 hover:bg-muted/30'
                    "
                >
                    <span
                        class="text-sm font-medium"
                        :class="
                            activeSection === section.key
                                ? 'text-primary'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ section.label }}
                    </span>
                    <span
                        class="flex h-6 min-w-6 items-center justify-center rounded-md px-2 text-xs font-semibold"
                        :class="
                            activeSection === section.key
                                ? 'bg-primary text-primary-foreground'
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
                    :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: sIdx * 0.1 }"
                    class="space-y-5"
                >
                    <!-- Season Header -->
                    <div class="flex items-center gap-3 mb-1">
                        <div class="flex items-center gap-2">
                            <Calendar class="h-5 w-5 text-primary" />
                            <h2
                                class="text-lg font-semibold tracking-tight"
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
                            {{ seasonGroup.exams.length === 1 ? 'exam' : 'exams' }}
                        </span>
                    </div>

                    <!-- Exam Grid for this season -->
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
                    >
                        <Motion
                            v-for="(exam, eIdx) in seasonGroup.exams"
                            :key="exam.id"
                            :initial="{ opacity: 0, y: 20 }"
                            :in-view="{ opacity: 1, y: 0 }"
                            :in-view-options="{ once: true, margin: '-30px' }"
                            :transition="{ duration: 0.5, ease: [0.16, 1, 0.3, 1], delay: eIdx * 0.05 }"
                            class="exam-card flex min-w-0 flex-col sm:flex-col justify-between border-l-[3px] sm:border-l-[3px] sm:border-t sm:border-r sm:border-b rounded-none sm:rounded-xl p-4 sm:p-5 transition-all duration-300"
                            :class="[
                                exam.is_locked
                                    ? 'cursor-not-allowed opacity-60'
                                    : 'hover:bg-muted/30 sm:hover:-translate-y-0.5 sm:hover:shadow-md cursor-pointer',
                                getCardStatusClass(exam),
                            ]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <!-- Status Badge + Score -->
                                    <div class="mb-2 flex items-start justify-between gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold text-white shadow-sm sm:px-3 sm:py-1 sm:text-xs"
                                            :class="getStatusBadgeInfo(exam).color"
                                        >
                                            {{ getStatusBadgeInfo(exam).label }}
                                        </span>
                                        <span
                                            v-if="exam.is_locked"
                                            class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[10px] font-semibold text-primary tabular-nums sm:px-3 sm:py-1 sm:text-xs"
                                        >
                                            {{
                                                exam.submissions
                                                    ?.reduce(
                                                        (acc, s) =>
                                                            acc + parseFloat(s.score),
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
                                            class="text-[10px] font-medium text-muted-foreground/60 sm:text-xs"
                                        >
                                            {{ exam.section_name }}
                                        </div>

                                        <!-- Title -->
                                        <h2
                                            class="text-sm leading-tight font-semibold text-foreground sm:text-base"
                                        >
                                            {{ exam.title }}
                                        </h2>

                                        <p class="line-clamp-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                            {{ exam.description }}
                                        </p>

                                        <!-- Progress Bar -->
                                        <div
                                            v-if="exam.total_parts && exam.total_parts > 0"
                                            class="space-y-1.5 pt-1.5 sm:space-y-2 sm:pt-2"
                                        >
                                            <div class="flex items-center justify-between text-[10px] font-medium sm:text-xs">
                                                <span
                                                    :class="
                                                        exam.is_locked
                                                            ? 'text-emerald-600'
                                                            : 'text-muted-foreground'
                                                    "
                                                >
                                                    {{ exam.submitted_parts_count }} / {{ exam.total_parts }} parts
                                                </span>
                                                <span v-if="!exam.is_locked" class="text-muted-foreground">
                                                    {{ Math.round(getProgressPercent(exam)) }}%
                                                </span>
                                            </div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-muted sm:h-2">
                                                <div
                                                    class="h-full rounded-full transition-all duration-700"
                                                    :class="
                                                        exam.is_locked
                                                            ? 'bg-emerald-500'
                                                            : 'bg-primary'
                                                    "
                                                    :style="{
                                                        width: `${getProgressPercent(exam)}%`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>

                                        <!-- Meta Info -->
                                        <div class="flex items-center gap-2.5 pt-1.5 text-xs text-muted-foreground sm:gap-3 sm:pt-2 sm:text-sm">
                                            <div class="flex items-center gap-1">
                                                <Clock class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                                <span>{{ exam.duration_minutes }} min</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <Timer class="h-3.5 w-3.5 sm:h-4 sm:w-4" :class="getExamTimeInfo(exam).color" />
                                                <span :class="getExamTimeInfo(exam).color" class="text-[10px] font-medium sm:text-xs">
                                                    {{ getExamTimeInfo(exam).label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chevron for list style on mobile -->
                                <ArrowRight class="h-5 w-5 text-muted-foreground/50 flex-shrink-0 sm:hidden" />
                            </div>

                            <!-- Action Button (only show on sm+) -->
                            <div class="mt-3 hidden sm:block">
                                <button
                                    v-if="exam.is_locked"
                                    @click="openReview(exam)"
                                    class="w-full rounded-xl bg-primary/10 py-3 text-sm font-semibold text-primary transition-all hover:bg-primary/20 active:scale-[0.98]"
                                >
                                    Review Results
                                </button>
                                <a
                                    v-else-if="exam.url"
                                    :href="exam.url"
                                    target="_blank"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-[0.98]"
                                >
                                    Start
                                    <ArrowRight class="h-4 w-4" />
                                </a>
                                <Link
                                    v-else
                                    :href="examsShow(exam.id).url"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-[0.98]"
                                >
                                    Start
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </Motion>
                    </div>
                </Motion>
            </template>

            <!-- Empty State -->
            <Motion
                v-else
                :initial="{ opacity: 0, scale: 0.95 }"
                :animate="{ opacity: 1, scale: 1 }"
                :transition="{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }"
                class="surface-card flex flex-col items-center justify-center space-y-4 border-dashed py-20 text-center"
            >
                <div class="rounded-full bg-muted/30 p-4">
                    <Calendar class="h-12 w-12 text-muted-foreground/40" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold text-foreground">No exams found</h3>
                    <p
                        v-if="activeSection !== 'all'"
                        class="text-sm text-muted-foreground"
                    >
                        Try selecting a different section to see more exams.
                    </p>
                    <p v-else-if="searchQuery" class="text-sm text-muted-foreground">
                        No exams match your search. Try a different keyword.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Keep an eye out! Your instructor will post new exams
                        here.
                    </p>
                </div>
            </Motion>
        </div>
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
                    <span class="text-xs font-medium text-primary">Your Results</span>
                    <DialogTitle class="text-lg font-bold text-foreground">
                        {{ selectedExamForReview?.title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Review your answers and feedback.
                    </DialogDescription>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="rounded-lg bg-primary/5 px-3 py-1.5 text-right">
                        <span class="text-[9px] font-medium text-muted-foreground">Score</span>
                        <span class="ml-1.5 text-base font-bold text-foreground tabular-nums">
                            {{
                                selectedExamForReview?.submissions
                                    ?.reduce(
                                        (acc, s) =>
                                            acc + parseFloat(s.score),
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
            v-if="selectedExamForReview && selectedExamForReview.parts.length > 1"
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
                    class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                />
            </button>
        </div>

        <!-- Scrollable Content -->
        <div
            ref="scrollRef"
            @scroll="showScrollbar"
            data-lenis-prevent
            class="custom-scrollbar min-h-0 flex-1 overflow-y-auto overscroll-contain"
        >
            <div v-if="selectedExamForReview" class="space-y-8">
                <Motion
                    v-for="part in selectedExamForReview.parts"
                    :key="part.id"
                    v-show="selectedPartId === part.id"
                    :initial="{ opacity: 0, y: 10 }"
                    :animate="{ opacity: 1, y: 0 }"
                    :transition="{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }"
                    class="space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-foreground">
                            {{ part.title }}
                        </h3>
                        <span
                            v-if="getSubmissionForPart(selectedExamForReview, part.id)"
                            class="rounded-lg bg-muted/50 px-3 py-1 text-[11px] font-semibold text-foreground tabular-nums"
                        >
                            Score: {{ getSubmissionForPart(selectedExamForReview, part.id)?.score }}
                            /
                            {{ part.questions?.reduce((acc, q) => acc + (parseInt(q.points) || 1), 0) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Motion
                            v-for="(question, qIndex) in part.questions"
                            :key="qIndex"
                            :initial="{ opacity: 0, y: 15 }"
                            :animate="{ opacity: 1, y: 0 }"
                            :transition="{ duration: 0.4, delay: qIndex * 0.05, ease: [0.16, 1, 0.3, 1] }"
                            class="question-card relative overflow-hidden rounded-xl border p-5 transition-all"
                            :class="
                                !answersRevealed
                                    ? 'border-border/50 bg-card/40'
                                    : isAnswerCorrect(
                                        question,
                                        getAnswerForQuestion(
                                            getSubmissionForPart(selectedExamForReview, part.id)?.answers,
                                            qIndex + 1,
                                        ),
                                        getAnswerObjectForQuestion(
                                            getSubmissionForPart(selectedExamForReview, part.id)?.answers,
                                            qIndex + 1,
                                        ),
                                    )
                                        ? 'border-emerald-500/30 bg-emerald-500/[0.02]'
                                        : 'border-red-500/30 bg-red-500/[0.02]'
                            "
                        >
                            <!-- Privacy Overlay: visible by default, fades on hover to reveal content -->
                            <div
                                class="question-reveal-overlay pointer-events-none absolute inset-0 z-20 flex items-center justify-center opacity-100 transition-opacity duration-300"
                            >
                                <div class="flex items-center gap-2 rounded-xl border border-primary/20 bg-background/90 px-4 py-2 shadow-lg backdrop-blur-sm">
                                    <Shield class="h-4 w-4 text-primary" />
                                    <span class="text-[11px] font-medium text-primary">Hover to reveal</span>
                                </div>
                            </div>

                            <div
                                class="question-reveal-content space-y-3 transition-all duration-500 blur-sm select-none"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-medium text-muted-foreground">
                                            #{{ qIndex + 1 }}
                                        </span>
                                        <span class="rounded-md bg-muted/50 px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                            {{ question.type.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] font-medium">
                                        <template v-if="answersRevealed">
                                            <CheckCircle2
                                                v-if="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))"
                                                class="h-3.5 w-3.5 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="h-3.5 w-3.5 text-red-500"
                                            />
                                            <span :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-600' : 'text-red-600'">
                                                {{ question.type === 'essay'
                                                    ? 'Reviewed'
                                                    : isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))
                                                        ? 'Correct'
                                                        : 'Incorrect'
                                                }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground/40" />
                                            <span class="text-muted-foreground/60">Submitted</span>
                                        </template>
                                    </div>
                                </div>

                                <p class="text-sm leading-relaxed font-medium text-foreground">
                                    {{ question.text }}
                                </p>

                                <!-- Multiple Choice / True False -->
                                <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="space-y-1.5">
                                    <div
                                        v-for="(option, oIndex) in question.options"
                                        :key="oIndex"
                                        class="flex items-center justify-between rounded-lg border p-2.5 text-sm transition-all"
                                        :class="
                                            !answersRevealed
                                                ? parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex
                                                    ? 'border-border/60 bg-muted/40'
                                                    : 'border-border/40 bg-muted/10 opacity-50'
                                                : option.is_correct
                                                    ? 'border-emerald-500/50 bg-emerald-500/10'
                                                    : parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex
                                                        ? 'border-red-500/50 bg-red-500/10'
                                                        : 'border-border/50 bg-muted/20 opacity-60'
                                        "
                                    >
                                        <span class="text-sm">{{ option.text }}</span>
                                        <span
                                            v-if="parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex"
                                            class="ml-2 rounded-md bg-foreground/10 px-2 py-0.5 text-[10px] font-semibold text-foreground"
                                        >
                                            Your answer
                                        </span>
                                    </div>
                                </div>

                                <!-- Identification -->
                                <div v-else-if="question.type === 'identification'" class="space-y-2">
                                    <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <span class="text-[10px] font-medium text-muted-foreground">Your answer</span>
                                        <p class="mt-1 text-sm font-semibold" :class="answersRevealed && isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-600' : 'text-foreground'">
                                            {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No answer' }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="answersRevealed && question.correct_answer"
                                        class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3"
                                    >
                                        <span class="text-[10px] font-medium text-emerald-600">Correct answer</span>
                                        <p class="mt-1 text-sm font-semibold text-emerald-600">{{ question.correct_answer }}</p>
                                    </div>
                                </div>

                                <!-- Essay -->
                                <div v-else-if="question.type === 'essay'" class="space-y-3">
                                    <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                                        <span class="text-[10px] font-medium text-muted-foreground">Your response</span>
                                        <p class="mt-1 text-sm leading-relaxed text-foreground whitespace-pre-wrap">
                                            {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No response submitted' }}
                                        </p>
                                    </div>

                                    <!-- AI Feedback -->
                                    <div v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score !== undefined" class="rounded-lg border border-primary/20 bg-primary/5 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <Zap class="h-3.5 w-3.5 text-primary" />
                                                <span class="text-[11px] font-medium text-primary">AI Feedback</span>
                                            </div>
                                            <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary tabular-nums">
                                                Score: {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score }} / {{ question.points }}
                                            </span>
                                        </div>
                                        <p v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback" class="mt-2 text-sm leading-relaxed text-foreground/80">
                                            {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback }}
                                        </p>
                                    </div>

                                    <div v-else-if="getSubmissionForPart(selectedExamForReview, part.id)?.status === 'pending_ai'" class="flex items-center gap-2 rounded-lg border border-border/40 bg-muted/20 p-3">
                                        <Timer class="h-3.5 w-3.5 text-amber-500" />
                                        <span class="text-xs font-medium text-amber-600">Feedback pending</span>
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
                class="rounded-lg px-4 py-2 text-xs font-medium"
            >
                Close
            </Button>

            <div v-if="selectedExamForReview && selectedExamForReview.parts.length > 1" class="flex items-center gap-2">
                <button
                    v-if="selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) > 0"
                    @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) - 1].id"
                    class="rounded-lg border border-border/50 bg-muted/30 px-4 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                >
                    Previous
                </button>
                <button
                    v-if="selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) < selectedExamForReview.parts.length - 1"
                    @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) + 1].id"
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
    scrollbar-color: color-mix(in srgb, var(--color-primary) 30%, transparent) transparent;
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
