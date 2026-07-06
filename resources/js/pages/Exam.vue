<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import gsap from 'gsap';
import {
    Calendar,
    Clock,
    AlertCircle,
    CheckCircle2,
    XCircle,
    Shield,
    ShieldOff,
    ArrowRight,
    Zap,
    Timer,
} from 'lucide-vue-next';
import { onMounted, ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as examsShow } from '@/routes/exams';
import type { BreadcrumbItem } from '@/types';

usePoll(10000, {
    only: ['exams'],
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
    exam_date_iso?: string;
}

const props = defineProps<{
    exams: Exam[];
}>();

const showReviewModal = ref(false);
const selectedExamForReview = ref<Exam | null>(null);
const selectedPartId = ref<number | null>(null);
const privacyMode = ref(true);

// --- Filter State ---
const activeFilter = ref<'all' | 'active' | 'completed'>('all');
const activeSection = ref('all');

const getExamSectionName = (exam: Exam) =>
    exam.section_name?.trim() || 'General';

const statusFilteredExams = computed(() => {
    if (activeFilter.value === 'active')
        return props.exams.filter((e) => !e.is_locked);
    if (activeFilter.value === 'completed')
        return props.exams.filter((e) => e.is_locked);
    return props.exams;
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
    if (activeSection.value === 'all') return statusFilteredExams.value;

    return statusFilteredExams.value.filter(
        (exam) => getExamSectionName(exam) === activeSection.value,
    );
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

    if (allPartsDone) return { label: 'COMPLETED', color: 'bg-emerald-500' };
    if (exam.is_locked && exam.status === 'closed')
        return { label: 'CLOSED', color: 'bg-red-500' };
    if (exam.is_locked) return { label: 'IN PROGRESS', color: 'bg-amber-500' };
    if (exam.status === 'published')
        return { label: 'PUBLISHED', color: 'bg-blue-500' };
    if (exam.status === 'closed')
        return { label: 'CLOSED', color: 'bg-red-500' };
    return { label: 'DRAFT', color: 'bg-muted text-muted-foreground' };
};

// --- Progress Percentage ---
const getProgressPercent = (exam: Exam) => {
    if (!exam.total_parts || exam.total_parts === 0) return 0;
    return ((exam.submitted_parts_count ?? 0) / exam.total_parts) * 100;
};

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

const examContainer = ref<HTMLElement | null>(null);

// --- GSAP Animation ---
const animateCards = () => {
    if (!examContainer.value) return;

    gsap.killTweensOf('.exam-card');
    gsap.set('.exam-card', { opacity: 0, y: 20 });
    gsap.set('.exam-hero', { opacity: 1, y: 0 });

    gsap.to('.exam-card', {
        opacity: 1,
        y: 0,
        stagger: 0.06,
        duration: 0.5,
        ease: 'power2.out',
    });
};

// Re-animate when filter changes
watch([activeFilter, activeSection], () => {
    setTimeout(animateCards, 50);
});

watch(sectionTabs, (tabs) => {
    if (!tabs.some((tab) => tab.key === activeSection.value)) {
        activeSection.value = 'all';
    }
});

onMounted(() => {
    setTimeout(animateCards, 100);
});
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="examContainer"
            class="exam-theme-page relative flex h-full flex-1 flex-col gap-4 overflow-hidden bg-background p-3 perspective-[1000px] md:p-8"
        >


            <!-- Header Section -->
            <div
                class="animate-section exam-hero group/hero relative space-y-1"
            >
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold tracking-tight">
                        Upcoming Exams
                    </h1>
                </div>
                <p
                    class="text-sm text-muted-foreground"
                >
                    View and take your assessments.
                </p>
            </div>



            <!-- Section Tabs -->
            <div
                v-if="sectionTabs.length > 1"
                class="no-scrollbar flex items-center gap-2 overflow-x-auto pb-3"
            >
                <button
                    v-for="section in sectionTabs"
                    :key="section.key"
                    @click="activeSection = section.key"
                    class="flex shrink-0 items-center gap-3 rounded-xl border px-4 py-3 text-left transition-all duration-200"
                    :class="
                        activeSection === section.key
                            ? 'border-primary/40 bg-primary/5'
                            : 'border-border/40 bg-card hover:border-primary/20 hover:bg-muted/30'
                    "
                >
                    <div class="min-w-0">
                        <span
                            class="block text-xs font-medium"
                            :class="
                                activeSection === section.key
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ section.label }}
                        </span>
                        <span class="text-[10px] text-muted-foreground/60">
                            {{ section.count }}
                            {{ section.count === 1 ? 'exam' : 'exams' }}
                        </span>
                    </div>
                    <div
                        class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold"
                        :class="
                            activeSection === section.key
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted/50 text-muted-foreground'
                        "
                    >
                        {{ section.count }}
                    </div>
                </button>
            </div>

            <!-- Exam Grid -->
            <div
                v-if="filteredExams.length > 0"
                class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="exam in filteredExams"
                    :key="exam.id"
                    class="animate-section exam-card surface-card flex min-w-0 flex-col justify-between p-4 transition-all duration-300"
                    :class="
                        exam.is_locked
                            ? 'cursor-not-allowed opacity-60'
                            : 'hover:-translate-y-0.5 hover:shadow-md'
                    "
                >
                    <!-- Status Badge -->
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold text-white"
                            :class="getStatusBadgeInfo(exam).color"
                        >
                            {{ getStatusBadgeInfo(exam).label }}
                        </div>
                        <div
                            v-if="exam.is_locked"
                            class="rounded-lg bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary tabular-nums"
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
                        </div>
                    </div>

                    <div class="flex-1 space-y-2">
                        <!-- Section Label -->
                        <div
                            v-if="exam.section_name"
                            class="text-[10px] font-medium text-muted-foreground/60"
                        >
                            {{ exam.section_name }}
                        </div>

                        <!-- Title -->
                        <h2
                            class="text-sm leading-tight font-semibold text-foreground"
                        >
                            {{ exam.title }}
                        </h2>

                        <p class="line-clamp-2 text-xs text-muted-foreground">
                            {{ exam.description }}
                        </p>

                        <!-- Progress Bar -->
                        <div
                            v-if="exam.total_parts && exam.total_parts > 0"
                            class="space-y-1 pt-1"
                        >
                            <div class="flex items-center justify-between text-[10px] font-medium">
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
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
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
                        <div class="flex items-center gap-3 pt-1 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <Clock class="h-3 w-3" />
                                <span>{{ exam.duration_minutes }} min</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <Timer class="h-3 w-3" :class="getExamTimeInfo(exam).color" />
                                <span :class="getExamTimeInfo(exam).color" class="text-[10px] font-medium">
                                    {{ getExamTimeInfo(exam).label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="mt-3">
                        <button
                            v-if="exam.is_locked"
                            @click="openReview(exam)"
                            class="w-full rounded-xl bg-primary/10 py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary/20"
                        >
                            Review Results
                        </button>
                        <a
                            v-else-if="exam.url"
                            :href="exam.url"
                            target="_blank"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary py-2 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Start
                            <ArrowRight class="h-3.5 w-3.5" />
                        </a>
                        <Link
                            v-else
                            :href="examsShow(exam.id).url"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary py-2 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Start
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="animate-section surface-card flex flex-col items-center justify-center space-y-4 border-dashed py-20 text-center"
            >
                <div class="rounded-full bg-muted/30 p-4">
                    <AlertCircle class="h-12 w-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No exams found</h3>
                    <p
                        v-if="activeFilter !== 'all'"
                        class="text-muted-foreground"
                    >
                        Try changing the filter to see more exams.
                    </p>
                    <p v-else class="text-muted-foreground">
                        Keep an eye out! Your instructor will post new exams
                        here.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Review Modal -->
    <Dialog v-model:open="showReviewModal">
        <DialogContent
            class="fixed top-1/2 left-1/2 flex max-h-[85vh] w-[95vw] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-border bg-card p-0 shadow-2xl sm:max-w-[1000px]"
        >
            <DialogHeader class="border-b border-border p-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <span class="text-xs font-medium text-primary">Your Results</span>
                        <DialogTitle class="text-xl font-bold text-foreground">
                            {{ selectedExamForReview?.title }}
                        </DialogTitle>
                        <DialogDescription class="text-sm text-muted-foreground">
                            Review your answers and feedback.
                        </DialogDescription>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Privacy Toggle -->
                        <button
                            @click="privacyMode = !privacyMode"
                            class="rounded-xl border border-border/50 bg-muted/20 px-3 py-2 text-xs font-medium transition-colors hover:border-primary/30"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    :is="privacyMode ? Shield : ShieldOff"
                                    class="h-3.5 w-3.5"
                                    :class="
                                        privacyMode
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <span>{{ privacyMode ? 'Hide answers' : 'Show answers' }}</span>
                            </div>
                        </button>

                        <div class="rounded-xl bg-primary/5 px-4 py-2">
                            <span class="text-[10px] font-medium text-muted-foreground">Score</span>
                            <span class="ml-2 text-lg font-bold text-foreground tabular-nums">
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
            </DialogHeader>

            <!-- Navigation Tabs for Parts -->
            <div
                v-if="
                    selectedExamForReview &&
                    selectedExamForReview.parts.length > 1
                "
                class="no-scrollbar flex items-center gap-2 overflow-x-auto border-b border-border/50 bg-muted/5 px-6 py-3"
            >
                <button
                    v-for="part in selectedExamForReview.parts"
                    :key="part.id"
                    @click="selectedPartId = part.id"
                    class="shrink-0 rounded-lg px-4 py-2 text-xs font-medium transition-all"
                    :class="
                        selectedPartId === part.id
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted/30 text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                >
                    <div class="flex items-center gap-2">
                        <span class="opacity-60">Part {{ selectedExamForReview.parts.indexOf(part) + 1 }}</span>
                        <span>{{ part.title }}</span>
                        <div
                            v-if="getSubmissionForPart(selectedExamForReview, part.id)"
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                        ></div>
                    </div>
                </button>
            </div>

            <div class="custom-scrollbar flex-1 overflow-y-auto p-6">
                <div v-if="selectedExamForReview" class="space-y-12">
                    <div
                        v-for="part in selectedExamForReview.parts"
                        :key="part.id"
                        v-show="selectedPartId === part.id"
                        class="space-y-6"
                    >
                        <div class="flex items-center justify-between border-b border-border/30 pb-4">
                            <h3 class="text-lg font-bold text-foreground">
                                {{ part.title }}
                            </h3>
                            <div
                                v-if="getSubmissionForPart(selectedExamForReview, part.id)"
                                class="rounded-lg bg-foreground/10 px-4 py-1.5 text-xs font-bold text-foreground"
                            >
                                Score: {{ getSubmissionForPart(selectedExamForReview, part.id)?.score }}
                                /
                                {{ part.questions?.reduce((acc, q) => acc + (parseInt(q.points) || 1), 0) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div
                                v-for="(question, qIndex) in part.questions"
                                :key="qIndex"
                                class="group/question relative overflow-hidden rounded-xl border p-6 transition-all"
                                :class="
                                    isAnswerCorrect(
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
                                <!-- Privacy Overlay -->
                                <div
                                    v-if="privacyMode"
                                    class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center opacity-100 transition-opacity duration-300 group-hover/question:opacity-0"
                                >
                                    <div class="flex items-center gap-2 rounded-xl border border-primary/20 bg-background/90 px-4 py-2 shadow-lg backdrop-blur-sm">
                                        <Shield class="h-4 w-4 text-primary" />
                                        <span class="text-xs font-medium text-primary">Hover to reveal</span>
                                    </div>
                                </div>

                                <div
                                    class="space-y-4 transition-all duration-500"
                                    :class="privacyMode ? 'group-hover/question:blur-0 blur-sm select-none' : ''"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs font-medium text-muted-foreground">
                                                Question {{ qIndex + 1 }}
                                            </span>
                                            <span class="rounded-md border border-primary/20 px-2 py-0.5 text-[10px] font-medium text-primary">
                                                {{ question.type.replace('_', ' ') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs font-semibold">
                                            <CheckCircle2
                                                v-if="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))"
                                                class="h-4 w-4 text-emerald-500"
                                            />
                                            <XCircle
                                                v-else
                                                class="h-4 w-4 text-red-500"
                                            />
                                            <span :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-600' : 'text-red-600'">
                                                {{ question.type === 'essay'
                                                    ? 'Reviewed'
                                                    : isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))
                                                        ? 'Correct'
                                                        : 'Incorrect'
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <p class="text-base leading-relaxed font-medium text-foreground">
                                        {{ question.text }}
                                    </p>

                                    <!-- Multiple Choice / True False -->
                                    <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="space-y-2">
                                        <div
                                            v-for="(option, oIndex) in question.options"
                                            :key="oIndex"
                                            class="flex items-center justify-between rounded-lg border p-3 text-sm transition-all"
                                            :class="[
                                                option.is_correct
                                                    ? 'border-emerald-500/50 bg-emerald-500/10'
                                                    : parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex
                                                        ? 'border-red-500/50 bg-red-500/10'
                                                        : 'border-border/50 bg-muted/20 opacity-60',
                                            ]"
                                        >
                                            <span>{{ option.text }}</span>
                                            <div
                                                v-if="parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex"
                                                class="ml-2 rounded-md bg-foreground/10 px-2 py-0.5 text-[10px] font-semibold text-foreground"
                                            >
                                                Your answer
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Identification -->
                                    <div v-else-if="question.type === 'identification'" class="space-y-3">
                                        <div class="rounded-lg border border-border/50 bg-muted/20 p-4">
                                            <span class="text-[10px] font-medium text-muted-foreground">Your answer</span>
                                            <p class="mt-1 text-sm font-semibold" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-600' : 'text-red-600'">
                                                {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No answer' }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4">
                                            <span class="text-[10px] font-medium text-emerald-600">Correct answer</span>
                                            <p class="mt-1 text-sm font-semibold text-emerald-600">{{ question.correct_answer }}</p>
                                        </div>
                                    </div>

                                    <!-- Essay -->
                                    <div v-else-if="question.type === 'essay'" class="space-y-4">
                                        <div class="rounded-lg border border-border/50 bg-muted/20 p-4">
                                            <span class="text-[10px] font-medium text-muted-foreground">Your response</span>
                                            <p class="mt-1 text-sm leading-relaxed text-foreground">
                                                {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No response submitted' }}
                                            </p>
                                        </div>

                                        <!-- AI Feedback -->
                                        <div v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score !== undefined" class="rounded-lg border border-primary/20 bg-primary/5 p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <Zap class="h-4 w-4 text-primary" />
                                                    <span class="text-xs font-medium text-primary">AI Feedback</span>
                                                </div>
                                                <div class="rounded-md bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
                                                    Score: {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score }} / {{ question.points }}
                                                </div>
                                            </div>
                                            <p v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback" class="mt-3 text-sm leading-relaxed text-foreground/80">
                                                {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback }}
                                            </p>
                                        </div>

                                        <div v-else-if="getSubmissionForPart(selectedExamForReview, part.id)?.status === 'pending_ai'" class="flex items-center gap-2 rounded-lg border border-border/40 bg-muted/20 p-4">
                                            <Timer class="h-4 w-4 text-amber-500" />
                                            <span class="text-xs font-medium text-amber-600">Feedback pending</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-between gap-3 border-t border-border bg-muted/5 p-4">
                <Button
                    variant="secondary"
                    @click="showReviewModal = false"
                    class="rounded-xl px-5 py-2.5 text-xs font-medium"
                >
                    Close
                </Button>

                <div v-if="selectedExamForReview && selectedExamForReview.parts.length > 1" class="flex items-center gap-3">
                    <button
                        v-if="selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) > 0"
                        @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) - 1].id"
                        class="rounded-xl border border-border/50 bg-muted/30 px-5 py-2.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                    >
                        Previous part
                    </button>
                    <button
                        v-if="selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) < selectedExamForReview.parts.length - 1"
                        @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex((p) => p.id === selectedPartId) + 1].id"
                        class="rounded-xl bg-primary px-6 py-2.5 text-xs font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                    >
                        Next part
                    </button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@reference "../../css/app.css";

.animate-section {
    will-change: transform, opacity;
}

.exam-card {
    opacity: 0;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--color-primary);
    opacity: 0.1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar:hover {
    background: var(--color-primary);
    opacity: 0.2;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
