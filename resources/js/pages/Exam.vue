<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { show as examsShow } from '@/routes/exams';
import { onMounted, ref, computed, watch } from 'vue';

usePoll(10000, {
    only: ['exams'],
});
import gsap from 'gsap';
import {
    Calendar,
    Clock,
    ExternalLink,
    AlertCircle,
    Lock,
    Eye,
    EyeOff,
    CheckCircle2,
    XCircle,
    HelpCircle,
    Shield,
    ShieldOff,
    ArrowRight,
    Zap,
    Timer,
    TrendingUp,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
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
import type { BreadcrumbItem } from '@/types';

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

// --- Summary Stats ---
const activeCount = computed(
    () => props.exams.filter((e) => !e.is_locked).length,
);
const completedCount = computed(
    () => props.exams.filter((e) => e.is_locked).length,
);
const totalCount = computed(() => props.exams.length);

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
        } catch (e) {
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

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const formatDateTime = (dateStr: string) => {
    return new Date(dateStr).toLocaleString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// --- GSAP Animation ---
const animateCards = () => {
    if (!examContainer.value) return;

    gsap.killTweensOf('.exam-card');
    gsap.set('.exam-card', { opacity: 0, x: -40, skewX: -5, scale: 0.98 });
    gsap.set('.exam-hero', { opacity: 1, x: 0 });
    gsap.set('.exam-card .bracket-corner', { scale: 0 });

    const tl = gsap.timeline({
        defaults: { ease: 'expo.out', duration: 0.8 },
    });

    tl.fromTo(
        '.exam-card',
        {
            opacity: 0,
            x: -40,
            skewX: -5,
            scale: 0.98,
        },
        {
            opacity: 1,
            x: 0,
            skewX: 0,
            scale: 1,
            stagger: 0.08,
            duration: 1,
            ease: 'back.out(1.2)',
            clearProps: 'filter',
        },
        '-=0.1',
    );

    tl.fromTo(
        '.exam-card .bracket-corner',
        { scale: 0 },
        {
            scale: 1,
            stagger: 0.03,
            duration: 0.5,
            ease: 'back.out(2)',
        },
        '-=0.8',
    );
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
    if (!examContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'expo.out', duration: 0.8 },
    });

    // 1. Hero entrance
    tl.fromTo(
        '.exam-hero',
        { opacity: 0, x: -20 },
        { opacity: 1, x: 0, duration: 0.6 },
    );

    // 2. Card entrance - Tactical slide-in with overshoot
    tl.fromTo(
        '.exam-card',
        {
            opacity: 0,
            x: -40,
            skewX: -5,
            scale: 0.98,
        },
        {
            opacity: 1,
            x: 0,
            skewX: 0,
            scale: 1,
            stagger: 0.08,
            duration: 1,
            ease: 'back.out(1.2)',
            clearProps: 'filter',
        },
        '-=0.5',
    );

    // 3. Bracket reveal - "Locking in" effect
    tl.fromTo(
        '.exam-card .bracket-corner',
        { scale: 0 },
        {
            scale: 1,
            stagger: 0.03,
            duration: 0.5,
            ease: 'back.out(2)',
        },
        '-=0.8',
    );

    // Background orb animation refinement
    const orbs = examContainer.value.querySelectorAll('.orb');
    orbs.forEach((orb, i) => {
        gsap.to(orb, {
            x: `random(-100, 100)`,
            y: `random(-100, 100)`,
            duration: 12 + i * 4,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
    });
});
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="examContainer"
            class="exam-theme-page relative flex h-full flex-1 flex-col gap-4 overflow-hidden bg-background p-3 perspective-[1000px] md:p-8"
        >
            <!-- Glassy background decorative orbs -->
            <div
                class="orb pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>
            <div
                class="orb pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>

            <!-- Header Section -->
            <div
                class="animate-section exam-hero group/hero relative space-y-1"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-[2px] w-8 rounded-full bg-primary/40 transition-all duration-500 group-hover/hero:w-12"
                    ></div>
                    <h1 class="text-xl font-black tracking-tighter uppercase">
                        Upcoming Exams
                    </h1>
                </div>
                <p
                    class="border-l-2 border-primary/10 pl-11 text-xs font-medium text-muted-foreground transition-colors group-hover/hero:border-primary/30"
                >
                    Manage your assessments and upcoming academic challenges.
                </p>
            </div>

            <!-- Summary Stats -->
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <div
                    class="exam-stat group/stat relative overflow-hidden border border-border/50 bg-muted/20 px-3 py-1.5 sm:px-4 sm:py-2"
                >
                    <div
                        class="absolute top-0 left-0 h-full w-0.5 bg-amber-500/60 transition-colors group-hover/stat:bg-amber-500"
                    ></div>
                    <div class="flex skew-x-[-12deg] items-center gap-2">
                        <AlertCircle
                            class="h-3 w-3 text-amber-500 sm:h-3.5 sm:w-3.5"
                        />
                        <span
                            class="font-mono text-[9px] font-black tracking-[0.15em] text-muted-foreground uppercase sm:text-[10px]"
                            >Active</span
                        >
                        <span
                            class="font-mono text-sm font-black text-foreground sm:text-lg"
                            >{{ activeCount }}</span
                        >
                    </div>
                </div>
                <div
                    class="exam-stat group/stat relative overflow-hidden border border-border/50 bg-muted/20 px-3 py-1.5 sm:px-4 sm:py-2"
                >
                    <div
                        class="absolute top-0 left-0 h-full w-0.5 bg-emerald-500/60 transition-colors group-hover/stat:bg-emerald-500"
                    ></div>
                    <div class="flex skew-x-[-12deg] items-center gap-2">
                        <CheckCircle2
                            class="h-3 w-3 text-emerald-500 sm:h-3.5 sm:w-3.5"
                        />
                        <span
                            class="font-mono text-[9px] font-black tracking-[0.15em] text-muted-foreground uppercase sm:text-[10px]"
                            >Completed</span
                        >
                        <span
                            class="font-mono text-sm font-black text-foreground sm:text-lg"
                            >{{ completedCount }}</span
                        >
                    </div>
                </div>
                <div
                    class="exam-stat group/stat relative overflow-hidden border border-border/50 bg-muted/20 px-3 py-1.5 sm:px-4 sm:py-2"
                >
                    <div
                        class="absolute top-0 left-0 h-full w-0.5 bg-primary/60 transition-colors group-hover/stat:bg-primary"
                    ></div>
                    <div class="flex skew-x-[-12deg] items-center gap-2">
                        <Calendar
                            class="h-3 w-3 text-primary sm:h-3.5 sm:w-3.5"
                        />
                        <span
                            class="font-mono text-[9px] font-black tracking-[0.15em] text-muted-foreground uppercase sm:text-[10px]"
                            >Total</span
                        >
                        <span
                            class="font-mono text-sm font-black text-foreground sm:text-lg"
                            >{{ totalCount }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button
                    v-for="filter in ['all', 'active', 'completed']"
                    :key="filter"
                    @click="
                        activeFilter = filter as 'all' | 'active' | 'completed'
                    "
                    class="exam-filter-tab group/tab relative shrink-0 -skew-x-12 transform border-2 px-4 py-1.5 transition-all duration-300 sm:px-6 sm:py-2"
                    :class="
                        activeFilter === filter
                            ? 'z-10 border-primary bg-primary text-primary-foreground shadow-lg shadow-primary/30'
                            : 'border-transparent bg-muted/10 text-muted-foreground hover:bg-muted/30 hover:text-foreground'
                    "
                >
                    <div class="flex skew-x-12 items-center gap-2">
                        <component
                            :is="
                                filter === 'all'
                                    ? Calendar
                                    : filter === 'active'
                                      ? AlertCircle
                                      : CheckCircle2
                            "
                            class="h-3 w-3 sm:h-3.5 sm:w-3.5"
                        />
                        <span
                            class="font-mono text-[10px] font-black tracking-[0.2em] uppercase sm:text-[11px]"
                            >{{ filter }}</span
                        >
                    </div>
                    <!-- Active Indicator -->
                    <div
                        v-if="activeFilter === filter"
                        class="absolute -bottom-1 left-1/2 h-1 w-1 -translate-x-1/2 skew-x-12 rounded-full bg-primary-foreground"
                    ></div>
                </button>
            </div>

            <!-- Section Tabs -->
            <div
                v-if="sectionTabs.length > 1"
                class="no-scrollbar flex items-center gap-2 overflow-x-auto pb-3 sm:gap-3"
            >
                <button
                    v-for="section in sectionTabs"
                    :key="section.key"
                    @click="activeSection = section.key"
                    class="exam-section-tab group relative flex min-w-[10rem] shrink-0 flex-col gap-2 overflow-hidden rounded-xl border px-4 py-3.5 transition-all duration-500 sm:min-w-[12rem] sm:px-6 sm:py-5"
                    :class="
                        activeSection === section.key
                            ? 'border-primary/50 bg-primary/[0.04] shadow-lg shadow-primary/10'
                            : 'border-border/40 bg-card hover:border-primary/30 hover:bg-muted/30'
                    "
                >
                    <div
                        class="relative z-10 flex w-full items-center justify-between"
                    >
                        <div class="min-w-0 space-y-1 text-left">
                            <span
                                class="block truncate text-[10px] font-black tracking-[0.2em] uppercase transition-colors duration-300 sm:text-[11px]"
                                :class="
                                    activeSection === section.key
                                        ? 'text-primary'
                                        : 'text-muted-foreground group-hover:text-foreground'
                                "
                            >
                                {{ section.label }}
                            </span>
                            <div class="flex items-center gap-2">
                                <span
                                    class="block text-[8px] font-bold tracking-[0.15em] uppercase opacity-50 sm:text-[9px]"
                                >
                                    {{ section.count }}
                                    {{ section.count === 1 ? 'exam' : 'exams' }}
                                </span>
                                <div
                                    v-if="activeSection === section.key"
                                    class="h-1 w-4 animate-pulse rounded-full bg-primary/40"
                                ></div>
                            </div>
                        </div>

                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg font-mono text-[11px] font-black transition-all duration-500"
                            :class="
                                activeSection === section.key
                                    ? 'scale-110 rotate-3 bg-primary text-primary-foreground shadow-lg shadow-primary/25'
                                    : 'bg-muted/50 text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'
                            "
                        >
                            {{ section.count }}
                        </div>
                    </div>

                    <!-- Decorative elements for active state -->
                    <div
                        v-if="activeSection === section.key"
                        class="absolute top-0 left-0 h-full w-1 bg-primary"
                    ></div>
                    <div
                        v-if="activeSection === section.key"
                        class="absolute -right-4 -bottom-4 h-12 w-12 rounded-full bg-primary/5 blur-2xl"
                    ></div>
                </button>
            </div>

            <!-- Exam Grid -->
            <div
                v-if="filteredExams.length > 0"
                class="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-3"
            >
                <div
                    v-for="exam in filteredExams"
                    :key="exam.id"
                    class="animate-section exam-card group/card relative flex min-w-0 flex-col justify-between overflow-hidden border border-border bg-card p-2 transition-all duration-500 sm:p-5"
                    :class="
                        exam.is_locked
                            ? 'cursor-not-allowed bg-muted/10 opacity-60 grayscale-[0.8]'
                            : 'hover:-translate-y-1'
                    "
                    @mousemove="handleMouseMove"
                >
                    <!-- Futuristic Corner Brackets -->
                    <div
                        class="bracket-corner pointer-events-none absolute top-0 left-0 h-3 w-3 border-t-2 border-l-2 border-foreground sm:h-4 sm:w-4"
                    ></div>
                    <div
                        class="bracket-corner pointer-events-none absolute right-0 bottom-0 h-3 w-3 border-r-2 border-b-2 border-foreground sm:h-4 sm:w-4"
                    ></div>

                    <!-- Status Badge (Top Left) -->
                    <div class="absolute top-2 left-2 z-20 sm:top-4 sm:left-4">
                        <div
                            class="exam-status-pill -skew-x-12 transform px-1.5 py-0.5 text-[7px] font-black tracking-[0.15em] uppercase sm:px-2.5 sm:py-0.5 sm:text-[8px] sm:tracking-[0.2em]"
                            :class="getStatusBadgeInfo(exam).color"
                        >
                            <span class="inline-block skew-x-12">{{
                                getStatusBadgeInfo(exam).label
                            }}</span>
                        </div>
                    </div>

                    <!-- Score / Completion Badge (Top Right) -->
                    <div
                        v-if="exam.is_locked"
                        class="absolute top-2 right-2 z-20 flex flex-col items-end gap-1 sm:top-4 sm:right-4 sm:gap-1.5"
                    >
                        <div
                            class="exam-score-pill -skew-x-12 transform bg-primary px-1.5 py-0.5 font-mono text-[7px] font-black tracking-widest text-primary-foreground sm:px-2.5 sm:py-0.5 sm:text-[9px]"
                        >
                            <span class="inline-block skew-x-12">
                                {{
                                    exam.submissions
                                        ?.reduce(
                                            (acc, s) =>
                                                acc + parseFloat(s.score),
                                            0,
                                        )
                                        .toFixed(2)
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Center Diamond Icon -->
                    <div
                        class="exam-tactical-mark mb-2 flex justify-center sm:mb-3"
                    >
                        <div
                            class="flex h-6 w-6 rotate-45 items-center justify-center border-2 transition-colors duration-500 sm:h-8 sm:w-8"
                            :class="
                                exam.is_locked
                                    ? 'border-muted-foreground/30'
                                    : 'border-amber-500/40 group-hover/card:border-amber-500'
                            "
                        >
                            <div
                                class="h-1.5 w-1.5 rotate-45"
                                :class="
                                    exam.is_locked
                                        ? 'bg-muted-foreground/30'
                                        : 'animate-pulse bg-amber-500'
                                "
                            ></div>
                        </div>
                    </div>

                    <div
                        class="relative z-10 min-w-0 space-y-1.5 text-center sm:space-y-3"
                    >
                        <!-- Section Label -->
                        <div
                            v-if="exam.section_name"
                            class="font-mono text-[7px] tracking-[0.25em] text-muted-foreground/70 uppercase sm:text-[8px]"
                        >
                            Section: {{ exam.section_name }}
                        </div>

                        <!-- Title -->
                        <div class="space-y-0.5 sm:space-y-1">
                            <h2
                                class="text-xs leading-tight font-black tracking-tight break-words text-foreground uppercase italic sm:text-lg sm:leading-none"
                            >
                                {{ exam.title }}
                            </h2>
                            <div
                                class="mx-auto h-px w-6 bg-foreground/20 sm:w-8"
                            ></div>
                        </div>

                        <!-- Progress Bar -->
                        <div
                            v-if="exam.total_parts && exam.total_parts > 0"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-center gap-1.5 font-mono text-[7px] font-black tracking-widest uppercase sm:text-[9px]"
                            >
                                <span
                                    :class="
                                        exam.is_locked
                                            ? 'text-emerald-500'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ exam.submitted_parts_count }} /
                                    {{ exam.total_parts }} PARTS
                                </span>
                            </div>
                            <div
                                class="mx-auto h-1 max-w-[80%] overflow-hidden rounded-full bg-muted/50"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :class="
                                        exam.is_locked
                                            ? 'w-full bg-emerald-500'
                                            : 'bg-primary'
                                    "
                                    :style="{
                                        width: `${getProgressPercent(exam)}%`,
                                    }"
                                ></div>
                            </div>
                        </div>

                        <!-- System Alerts Box -->
                        <div
                            class="exam-alert-box space-y-1 border border-border/50 bg-muted/30 p-1.5 text-left sm:space-y-1.5 sm:p-3"
                        >
                            <div class="hidden items-start gap-2 sm:flex">
                                <span
                                    class="shrink-0 text-[8px] font-black text-amber-500"
                                    >[!]</span
                                >
                                <p
                                    class="text-[8px] leading-relaxed font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{ exam.description }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <span
                                    class="shrink-0 text-[8px] font-black text-amber-500 sm:text-[9px]"
                                    >[!]</span
                                >
                                <p
                                    class="truncate font-mono text-[7px] font-bold tracking-wider text-muted-foreground uppercase sm:text-[8px]"
                                >
                                    {{ exam.duration_minutes }} MIN
                                </p>
                            </div>
                            <!-- Date / Countdown -->
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <Clock
                                    class="h-2 w-2 shrink-0 sm:h-2.5 sm:w-2.5"
                                    :class="getExamTimeInfo(exam).color"
                                />
                                <p
                                    class="truncate font-mono text-[7px] font-bold tracking-wider uppercase sm:text-[8px]"
                                    :class="getExamTimeInfo(exam).color"
                                >
                                    {{ getExamTimeInfo(exam).label }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Button (Slanted) -->
                        <div class="space-y-1.5 pt-1 sm:space-y-2 sm:pt-2">
                            <button
                                v-if="exam.is_locked"
                                @click="openReview(exam)"
                                class="exam-action relative w-full -skew-x-12 transform bg-primary py-1.5 text-[8px] font-black tracking-[0.2em] text-primary-foreground uppercase transition-all hover:bg-foreground hover:text-background sm:py-2.5 sm:text-[10px] sm:tracking-[0.3em]"
                            >
                                <span class="inline-block skew-x-12"
                                    >Review</span
                                >
                            </button>

                            <a
                                v-else-if="exam.url"
                                :href="exam.url"
                                target="_blank"
                                class="exam-action relative flex w-full -skew-x-12 transform items-center justify-center gap-1.5 bg-primary py-1.5 text-[8px] font-black tracking-[0.2em] text-primary-foreground uppercase transition-all hover:bg-foreground hover:text-background sm:gap-2 sm:py-2.5 sm:text-[10px] sm:tracking-[0.3em]"
                            >
                                <span class="inline-block skew-x-12"
                                    >Start</span
                                >
                                <ArrowRight
                                    class="h-2.5 w-2.5 skew-x-12 sm:h-3.5 sm:w-3.5"
                                />
                            </a>
                            <Link
                                v-else
                                :href="examsShow(exam.id).url"
                                class="exam-action relative flex w-full -skew-x-12 transform items-center justify-center gap-1.5 bg-primary py-1.5 text-[8px] font-black tracking-[0.2em] text-primary-foreground uppercase transition-all hover:bg-foreground hover:text-background sm:gap-2 sm:py-2.5 sm:text-[10px] sm:tracking-[0.3em]"
                            >
                                <span class="inline-block skew-x-12"
                                    >Start</span
                                >
                                <ArrowRight
                                    class="h-2.5 w-2.5 skew-x-12 sm:h-3.5 sm:w-3.5"
                                />
                            </Link>
                        </div>
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
            class="exam-review-modal fixed top-1/2 left-1/2 flex max-h-[90vh] w-[95vw] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-none border-border bg-card p-0 shadow-2xl sm:max-w-[1000px] dark:bg-zinc-900"
        >
            <!-- Futuristic Corner Brackets -->
            <div
                class="pointer-events-none absolute top-0 left-0 z-50 h-8 w-8 border-t-2 border-l-2 border-foreground"
            ></div>
            <div
                class="pointer-events-none absolute right-0 bottom-0 z-50 h-8 w-8 border-r-2 border-b-2 border-foreground"
            ></div>

            <DialogHeader
                class="relative border-b border-border bg-muted/10 p-5 md:p-10"
            >
                <div
                    class="flex flex-col justify-center gap-4 text-center md:flex-row md:items-center md:gap-6 md:text-left"
                >
                    <div
                        class="flex items-center justify-center gap-3 md:justify-start md:gap-5"
                    >
                        <div
                            class="hidden h-12 w-12 shrink-0 rotate-45 items-center justify-center border-2 border-amber-500 sm:flex"
                        >
                            <div
                                class="h-2 w-2 rotate-45 animate-pulse bg-amber-500"
                            ></div>
                        </div>
                        <div class="space-y-1">
                            <span
                                class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.4em] text-primary uppercase md:text-[10px]"
                                >Your results</span
                            >
                            <DialogTitle
                                class="text-xl leading-none font-black tracking-tighter text-foreground uppercase italic md:text-3xl"
                            >
                                {{ selectedExamForReview?.title }}
                            </DialogTitle>
                            <DialogDescription
                                class="hidden text-[10px] font-bold tracking-widest text-muted-foreground uppercase sm:block"
                            >
                                Reviewing performance data and individual
                                operative feedback.
                            </DialogDescription>
                        </div>
                    </div>

                    <div
                        class="mx-auto flex items-center gap-3 md:mx-0 md:gap-4"
                    >
                        <!-- Privacy Toggle -->
                        <button
                            @click="privacyMode = !privacyMode"
                            class="group/privacy relative flex flex-col items-center gap-1 overflow-hidden border border-border/50 bg-muted/20 px-3 py-1.5 transition-all hover:border-primary/50 md:px-4 md:py-2"
                        >
                            <div
                                class="absolute top-0 left-0 h-[1px] w-full bg-primary/30 transition-colors group-hover/privacy:bg-primary"
                            ></div>
                            <div class="flex items-center gap-2">
                                <component
                                    :is="privacyMode ? Shield : ShieldOff"
                                    class="h-2.5 w-2.5 md:h-3 md:w-3"
                                    :class="
                                        privacyMode
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <span
                                    class="exam-friendly-label font-mono text-[7px] font-black tracking-[0.2em] uppercase md:text-[8px]"
                                    :class="
                                        privacyMode
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        privacyMode
                                            ? 'Hide answers'
                                            : 'Show answers'
                                    }}
                                </span>
                            </div>
                            <span
                                class="exam-friendly-label hidden text-[7px] font-bold tracking-widest text-muted-foreground/60 uppercase md:block"
                                >Hover to reveal</span
                            >
                        </button>

                        <div
                            class="group/total relative overflow-hidden border border-border/50 bg-muted/30 px-4 py-1.5 md:px-6 md:py-3"
                        >
                            <div
                                class="absolute top-0 left-0 h-full w-1 bg-primary/40 transition-colors group-hover/total:bg-primary"
                            ></div>
                            <span
                                class="exam-friendly-label mb-0.5 block font-mono text-[7px] leading-none font-black tracking-[0.3em] text-muted-foreground uppercase md:mb-1 md:text-[8px]"
                                >Score</span
                            >
                            <span
                                class="font-mono text-lg leading-none font-black text-foreground tabular-nums md:text-2xl"
                            >
                                {{
                                    selectedExamForReview?.submissions
                                        ?.reduce(
                                            (acc, s) =>
                                                acc + parseFloat(s.score),
                                            0,
                                        )
                                        .toFixed(2)
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
                class="no-scrollbar flex items-center gap-2 overflow-x-auto border-b border-border/50 bg-muted/5 px-5 py-4 md:px-10"
            >
                <button
                    v-for="part in selectedExamForReview.parts"
                    :key="part.id"
                    @click="selectedPartId = part.id"
                    class="group relative shrink-0 -skew-x-12 transform px-6 py-2 transition-all duration-300"
                    :class="
                        selectedPartId === part.id
                            ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/30'
                            : 'bg-muted/30 text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <div class="flex skew-x-12 items-center gap-3">
                        <span
                            class="exam-friendly-label font-mono text-[8px] font-black tracking-[0.2em] uppercase opacity-60"
                        >
                            Part
                            {{ selectedExamForReview.parts.indexOf(part) + 1 }}
                        </span>
                        <span
                            class="text-[10px] font-black tracking-widest whitespace-nowrap uppercase"
                        >
                            {{ part.title }}
                        </span>
                        <div
                            v-if="
                                getSubmissionForPart(
                                    selectedExamForReview,
                                    part.id,
                                )
                            "
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]"
                        ></div>
                    </div>
                </button>
            </div>

            <div
                class="custom-scrollbar flex-1 overflow-y-auto bg-card/30 p-8 md:p-10"
            >
                <div v-if="selectedExamForReview" class="space-y-16">
                    <div
                        v-for="part in selectedExamForReview.parts"
                        :key="part.id"
                        v-show="selectedPartId === part.id"
                        class="space-y-8"
                    >
                        <div
                            class="flex items-center justify-between border-b border-border/30 pb-6"
                        >
                            <div class="flex items-center gap-5">
                                <div class="h-8 w-2 bg-primary"></div>
                                <h3
                                    class="text-2xl font-black tracking-tight text-foreground uppercase italic"
                                >
                                    {{ part.title }}
                                </h3>
                            </div>
                            <div
                                v-if="
                                    getSubmissionForPart(
                                        selectedExamForReview,
                                        part.id,
                                    )
                                "
                                class="-skew-x-12 transform bg-foreground px-6 py-3 text-xs font-black tracking-[0.2em] text-background uppercase"
                            >
                                <span class="inline-block skew-x-12 font-mono">
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
                                            (acc, q) =>
                                                acc + (parseInt(q.points) || 1),
                                            0,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                            <div
                                v-for="(question, qIndex) in part.questions"
                                :key="qIndex"
                                class="group/question relative overflow-hidden border bg-muted/5 p-8 transition-all duration-500"
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
                                        ? 'border-emerald-500/20 shadow-[0_0_30px_-15px_rgba(16,185,129,0.1)]'
                                        : 'border-red-500/20 shadow-[0_0_30px_-15px_rgba(239,68,68,0.1)]'
                                "
                            >
                                <!-- Status Accent -->
                                <div
                                    class="absolute top-0 left-0 h-full w-1.5"
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
                                            ? 'bg-emerald-500'
                                            : 'bg-red-500'
                                    "
                                ></div>

                                <!-- Privacy Overlay for blurred state -->
                                <div
                                    v-if="privacyMode"
                                    class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center opacity-100 transition-opacity duration-300 group-hover/question:opacity-0"
                                >
                                    <div
                                        class="flex rotate-[-2deg] transform items-center gap-3 border border-primary/20 bg-background/80 px-4 py-2 shadow-xl backdrop-blur-sm"
                                    >
                                        <Shield
                                            class="h-4 w-4 animate-pulse text-primary"
                                        />
                                        <span
                                            class="text-xs font-black tracking-[0.2em] text-primary uppercase"
                                            >Hover to reveal</span
                                        >
                                    </div>
                                </div>

                                <div
                                    class="space-y-8 transition-all duration-500"
                                    :class="
                                        privacyMode
                                            ? 'group-hover/question:blur-0 blur-md select-none'
                                            : ''
                                    "
                                >
                                    <div class="space-y-4">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-4"
                                            >
                                                <span
                                                    class="exam-friendly-label font-mono text-xs font-black tracking-[0.3em] text-muted-foreground uppercase"
                                                    >Question
                                                    {{ qIndex + 1 }}</span
                                                >
                                                <span
                                                    class="border border-primary/20 px-2 py-0.5 font-mono text-[10px] font-black tracking-widest text-primary uppercase"
                                                    >{{
                                                        question.type.replace(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
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
                                                class="flex items-center gap-2 font-mono text-xs font-black tracking-widest text-emerald-500 uppercase"
                                            >
                                                <CheckCircle2 class="h-4 w-4" />
                                                {{
                                                    question.type === 'essay'
                                                        ? 'Reviewed'
                                                        : 'Correct'
                                                }}
                                            </div>
                                            <div
                                                v-else
                                                class="flex items-center gap-2 font-mono text-xs font-black tracking-widest text-red-500 uppercase"
                                            >
                                                <XCircle class="h-4 w-4" />
                                                {{
                                                    question.type === 'essay'
                                                        ? 'Needs work'
                                                        : 'Incorrect'
                                                }}
                                            </div>
                                        </div>
                                        <p
                                            class="text-lg leading-snug font-black tracking-tight whitespace-pre-wrap text-foreground italic"
                                        >
                                            {{ question.text }}
                                        </p>
                                    </div>

                                    <div class="space-y-6">
                                        <!-- Multiple Choice / True False -->
                                        <div
                                            v-if="
                                                question.type ===
                                                    'multiple_choice' ||
                                                question.type === 'true_false'
                                            "
                                            class="grid grid-cols-1 gap-4"
                                        >
                                            <div
                                                v-for="(
                                                    option, oIndex
                                                ) in question.options"
                                                :key="oIndex"
                                                class="flex items-center justify-between border p-5 font-mono text-sm tracking-widest uppercase transition-all"
                                                :class="[
                                                    option.is_correct
                                                        ? 'border-emerald-500 bg-emerald-500 font-black text-white dark:text-zinc-950'
                                                        : parseInt(
                                                                getAnswerForQuestion(
                                                                    getSubmissionForPart(
                                                                        selectedExamForReview,
                                                                        part.id,
                                                                    )?.answers,
                                                                    qIndex + 1,
                                                                ),
                                                            ) === oIndex
                                                          ? 'border-red-500/50 bg-red-500/10 text-red-500'
                                                          : 'border-border/50 bg-muted/30 text-muted-foreground opacity-50',
                                                ]"
                                            >
                                                <span
                                                    class="flex-1 whitespace-pre-wrap"
                                                    >{{ option.text }}</span
                                                >
                                                <div
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
                                                    class="ml-4 -skew-x-12 transform bg-foreground px-3 py-1.5 text-[10px] font-black tracking-[0.2em] text-background uppercase"
                                                >
                                                    <span
                                                        class="inline-block skew-x-12"
                                                        >Your answer</span
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Identification -->
                                        <div
                                            v-else-if="
                                                question.type ===
                                                'identification'
                                            "
                                            class="space-y-4"
                                        >
                                            <div
                                                class="relative flex flex-col gap-2 overflow-hidden border border-border/50 bg-muted/30 p-5"
                                            >
                                                <div
                                                    class="absolute top-0 left-0 h-full w-1.5"
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
                                                        )
                                                            ? 'bg-emerald-500/40'
                                                            : 'bg-red-500/40'
                                                    "
                                                ></div>
                                                <span
                                                    class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                                    >Your answer</span
                                                >
                                                <span
                                                    class="text-base font-black tracking-widest whitespace-pre-wrap"
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
                                                        )
                                                            ? 'text-emerald-500'
                                                            : 'text-red-500'
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
                                                </span>
                                            </div>
                                            <div
                                                class="flex flex-col gap-2 border border-emerald-500/30 bg-emerald-500/5 p-5"
                                            >
                                                <span
                                                    class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.3em] text-emerald-500 uppercase"
                                                    >Correct answer</span
                                                >
                                                <span
                                                    class="text-base font-black tracking-widest whitespace-pre-wrap text-emerald-600"
                                                >
                                                    {{
                                                        question.correct_answer
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Essay -->
                                        <div
                                            v-else-if="
                                                question.type === 'essay'
                                            "
                                            class="space-y-6"
                                        >
                                            <div
                                                class="relative flex flex-col gap-2 overflow-hidden border border-border/50 bg-muted/30 p-5"
                                            >
                                                <div
                                                    class="absolute top-0 left-0 h-full w-1.5 bg-primary/40"
                                                ></div>
                                                <span
                                                    class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                                    >Your response</span
                                                >
                                                <p
                                                    class="text-base leading-relaxed font-bold tracking-tight whitespace-pre-wrap text-foreground"
                                                >
                                                    {{
                                                        getAnswerForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        ) ||
                                                        'No response submitted'
                                                    }}
                                                </p>
                                            </div>

                                            <!-- AI Assessment Display for Essay -->
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
                                                class="group/ai relative flex flex-col gap-4 overflow-hidden border border-primary/20 bg-primary/5 p-5"
                                            >
                                                <div
                                                    class="absolute top-0 right-0 h-16 w-16 translate-x-8 -translate-y-8 -rotate-45 bg-primary/5 transition-colors group-hover/ai:bg-primary/10"
                                                ></div>

                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <Zap
                                                            class="h-4 w-4 animate-pulse text-primary"
                                                        />
                                                        <span
                                                            class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.3em] text-primary uppercase"
                                                            >Essay
                                                            feedback</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="-skew-x-12 transform bg-primary px-3 py-1 font-mono text-[10px] font-black tracking-widest text-primary-foreground"
                                                    >
                                                        <span
                                                            class="inline-block skew-x-12"
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
                                                            /
                                                            {{
                                                                question.points
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        getAnswerObjectForQuestion(
                                                            getSubmissionForPart(
                                                                selectedExamForReview,
                                                                part.id,
                                                            )?.answers,
                                                            qIndex + 1,
                                                        )?.ai_feedback
                                                    "
                                                    class="space-y-2 border-t border-primary/10 pt-4"
                                                >
                                                    <div
                                                        class="flex items-center justify-between"
                                                    >
                                                        <span
                                                            class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.25em] text-muted-foreground uppercase"
                                                            >Feedback</span
                                                        >
                                                    </div>
                                                    <p
                                                        class="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90"
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

                                            <div
                                                v-else-if="
                                                    getSubmissionForPart(
                                                        selectedExamForReview,
                                                        part.id,
                                                    )?.status === 'pending_ai'
                                                "
                                                class="flex items-center justify-between border border-border/40 bg-muted/20 p-5"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <Timer
                                                        class="h-4 w-4 text-amber-500"
                                                    />
                                                    <span
                                                        class="exam-friendly-label font-mono text-[10px] font-black tracking-[0.3em] text-amber-500 uppercase"
                                                        >Feedback</span
                                                    >
                                                </div>
                                                <span
                                                    class="font-mono text-[10px] font-bold tracking-widest text-muted-foreground uppercase"
                                                    >Awaiting release</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter
                class="flex flex-col items-center justify-between gap-4 border-t border-border bg-muted/10 p-8 md:flex-row md:p-10"
            >
                <Button
                    variant="secondary"
                    @click="showReviewModal = false"
                    class="h-12 w-full -skew-x-12 transform rounded-none border border-border/50 bg-muted/20 px-8 text-[10px] font-black tracking-[0.3em] text-muted-foreground uppercase hover:bg-red-500/10 hover:text-red-500 md:w-auto"
                >
                    <span class="inline-block skew-x-12">Close</span>
                </Button>

                <div
                    v-if="
                        selectedExamForReview &&
                        selectedExamForReview.parts.length > 1
                    "
                    class="flex w-full items-center gap-4 md:w-auto"
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
                        class="flex-1 -skew-x-12 transform border border-border/50 bg-muted/30 px-6 py-3 text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-all hover:bg-primary/10 hover:text-primary md:flex-none"
                    >
                        <span class="inline-block skew-x-12"
                            >Previous part</span
                        >
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
                        class="flex-1 -skew-x-12 transform bg-primary px-10 py-3 text-[10px] font-black tracking-[0.2em] text-primary-foreground uppercase shadow-lg shadow-primary/30 transition-all hover:shadow-xl hover:shadow-primary/50 md:flex-none"
                    >
                        <span class="inline-block skew-x-12">Next part</span>
                    </button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@reference "../../css/app.css";

.perspective-\[1000px\] {
    perspective: 1000px;
}

.surface-card {
    @apply rounded-2xl border border-border/50 bg-card/50 backdrop-blur-md;
}

.animate-section {
    will-change: transform, opacity;
}

.exam-card {
    opacity: 0;
}

@keyframes scan-vertical {
    0% {
        transform: translateY(-100%);
    }
    100% {
        transform: translateY(1000%);
    }
}

.animate-scan-vertical {
    animation: scan-vertical 4s linear infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.animate-shimmer {
    animation: shimmer 2s infinite;
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
