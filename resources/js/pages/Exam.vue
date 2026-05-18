<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { show as examsShow } from '@/routes/exams';
import { onMounted, ref, computed, watch } from 'vue';

usePoll(10000, {
    only: ['exams']
});
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Calendar, Clock, ExternalLink, AlertCircle, Lock, Eye, EyeOff, CheckCircle2, XCircle, HelpCircle, Shield, ShieldOff, ArrowRight, Zap, Timer, TrendingUp } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

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

const getExamSectionName = (exam: Exam) => exam.section_name?.trim() || 'General';

const statusFilteredExams = computed(() => {
    if (activeFilter.value === 'active') return props.exams.filter(e => !e.is_locked);
    if (activeFilter.value === 'completed') return props.exams.filter(e => e.is_locked);
    return props.exams;
});

const sectionTabs = computed(() => {
    const sections = new Map<string, number>();

    statusFilteredExams.value.forEach((exam) => {
        const sectionName = getExamSectionName(exam);
        sections.set(sectionName, (sections.get(sectionName) ?? 0) + 1);
    });

    return [
        { key: 'all', label: 'All sections', count: statusFilteredExams.value.length },
        ...Array.from(sections.entries())
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([label, count]) => ({ key: label, label, count })),
    ];
});

const filteredExams = computed(() => {
    if (activeSection.value === 'all') return statusFilteredExams.value;

    return statusFilteredExams.value.filter(exam => getExamSectionName(exam) === activeSection.value);
});

// --- Summary Stats ---
const activeCount = computed(() => props.exams.filter(e => !e.is_locked).length);
const completedCount = computed(() => props.exams.filter(e => e.is_locked).length);
const totalCount = computed(() => props.exams.length);

// --- Exam Time Info (countdown/overdue) ---
const getExamTimeInfo = (exam: Exam) => {
    if (!exam.exam_date && !exam.exam_date_iso) {
        return { label: 'NO_DEADLINE_SET', color: 'text-muted-foreground', isOverdue: false, isUpcoming: false };
    }
    const dateStr = exam.exam_date_iso || exam.exam_date;
    const examDate = new Date(dateStr);
    if (Number.isNaN(examDate.getTime())) {
        return { label: 'INVALID_DATE', color: 'text-muted-foreground', isOverdue: false, isUpcoming: false };
    }
    const now = new Date();
    const diff = examDate.getTime() - now.getTime();

    if (exam.is_locked) {
        return { label: `COMPLETED ${examDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`, color: 'text-emerald-500', isOverdue: false, isUpcoming: false };
    }
    if (diff < 0) {
        return { label: 'OVERDUE', color: 'text-red-500', isOverdue: true, isUpcoming: false };
    }
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    if (days > 0) {
        return { label: `${days}D ${hours}H REMAINING`, color: 'text-amber-500', isOverdue: false, isUpcoming: true };
    }
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return { label: `${hours}H ${minutes}M REMAINING`, color: 'text-red-500', isOverdue: false, isUpcoming: true };
};

// --- Status Badge Info ---
const getStatusBadgeInfo = (exam: Exam) => {
    const totalParts = exam.total_parts ?? exam.parts?.length ?? 0;
    const submittedParts = exam.submitted_parts_count ?? exam.submissions?.length ?? 0;
    const allPartsDone = totalParts > 0 && submittedParts >= totalParts;

    if (allPartsDone) return { label: 'COMPLETED', color: 'bg-emerald-500' };
    if (exam.is_locked && exam.status === 'closed') return { label: 'CLOSED', color: 'bg-red-500' };
    if (exam.is_locked) return { label: 'IN PROGRESS', color: 'bg-amber-500' };
    if (exam.status === 'published') return { label: 'PUBLISHED', color: 'bg-blue-500' };
    if (exam.status === 'closed') return { label: 'CLOSED', color: 'bg-red-500' };
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
    return exam.submissions?.find(s => s.exam_part_id === partId);
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

const isAnswerCorrect = (question: any, submittedAnswer: any, answerObject: any = null) => {
    if (submittedAnswer === null || submittedAnswer === undefined) return false;

    if (question.type === 'multiple_choice' || question.type === 'true_false') {
        const correctIndex = question.options?.findIndex((opt: any) => opt.is_correct);
        return parseInt(submittedAnswer) === correctIndex;
    } else if (question.type === 'identification') {
        const correctAnswers = Array.isArray(question.correct_answers) 
            ? question.correct_answers 
            : [question.correct_answer];
        return correctAnswers.some((ans: string) => 
            ans?.toLowerCase().trim() === submittedAnswer?.toString().toLowerCase().trim()
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
        defaults: { ease: 'expo.out', duration: 0.8 }
    });

    tl.fromTo(
        '.exam-card',
        {
            opacity: 0,
            x: -40,
            skewX: -5,
            scale: 0.98
        },
        {
            opacity: 1,
            x: 0,
            skewX: 0,
            scale: 1,
            stagger: 0.08,
            duration: 1,
            ease: 'back.out(1.2)',
            clearProps: 'filter'
        },
        '-=0.1'
    );

    tl.fromTo(
        '.exam-card .bracket-corner',
        { scale: 0 },
        {
            scale: 1,
            stagger: 0.03,
            duration: 0.5,
            ease: 'back.out(2)'
        },
        '-=0.8'
    );
};

// Re-animate when filter changes
watch([activeFilter, activeSection], () => {
    setTimeout(animateCards, 50);
});

watch(sectionTabs, (tabs) => {
    if (!tabs.some(tab => tab.key === activeSection.value)) {
        activeSection.value = 'all';
    }
});

onMounted(() => {
    if (!examContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'expo.out', duration: 0.8 }
    });

    // 1. Hero entrance
    tl.fromTo('.exam-hero', 
        { opacity: 0, x: -20 },
        { opacity: 1, x: 0, duration: 0.6 }
    );

    // 2. Card entrance - Tactical slide-in with overshoot
    tl.fromTo(
        '.exam-card',
        {
            opacity: 0,
            x: -40,
            skewX: -5,
            scale: 0.98
        },
        {
            opacity: 1,
            x: 0,
            skewX: 0,
            scale: 1,
            stagger: 0.08,
            duration: 1,
            ease: 'back.out(1.2)',
            clearProps: 'filter'
        },
        '-=0.5'
    );

    // 3. Bracket reveal - "Locking in" effect
    tl.fromTo(
        '.exam-card .bracket-corner',
        { scale: 0 },
        {
            scale: 1,
            stagger: 0.03,
            duration: 0.5,
            ease: 'back.out(2)'
        },
        '-=0.8'
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
            ease: 'sine.inOut'
        });
    });
});
</script>

<template>
    <Head title="Exams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="examContainer" class="exam-theme-page flex h-full flex-1 flex-col gap-4 p-3 md:p-8 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Glassy background decorative orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Header Section -->
            <div class="animate-section exam-hero space-y-1 relative group/hero">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-[2px] bg-primary/40 rounded-full group-hover/hero:w-12 transition-all duration-500"></div>
                            <h1 class="text-xl font-black tracking-tighter uppercase">Upcoming Exams</h1>
                </div>
                <p class="text-muted-foreground text-xs font-medium pl-11 border-l-2 border-primary/10 group-hover/hero:border-primary/30 transition-colors">
                    Manage your assessments and upcoming academic challenges.
                </p>
            </div>

            <!-- Summary Stats -->
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <div class="exam-stat relative px-3 sm:px-4 py-1.5 sm:py-2 border border-border/50 bg-muted/20 overflow-hidden group/stat">
                    <div class="absolute top-0 left-0 w-0.5 h-full bg-amber-500/60 group-hover/stat:bg-amber-500 transition-colors"></div>
                    <div class="flex items-center gap-2 skew-x-[-12deg]">
                        <AlertCircle class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-amber-500" />
                        <span class="text-[9px] sm:text-[10px] font-black text-muted-foreground uppercase tracking-[0.15em] font-mono">Active</span>
                        <span class="text-sm sm:text-lg font-black text-foreground font-mono">{{ activeCount }}</span>
                    </div>
                </div>
                <div class="exam-stat relative px-3 sm:px-4 py-1.5 sm:py-2 border border-border/50 bg-muted/20 overflow-hidden group/stat">
                    <div class="absolute top-0 left-0 w-0.5 h-full bg-emerald-500/60 group-hover/stat:bg-emerald-500 transition-colors"></div>
                    <div class="flex items-center gap-2 skew-x-[-12deg]">
                        <CheckCircle2 class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-500" />
                        <span class="text-[9px] sm:text-[10px] font-black text-muted-foreground uppercase tracking-[0.15em] font-mono">Completed</span>
                        <span class="text-sm sm:text-lg font-black text-foreground font-mono">{{ completedCount }}</span>
                    </div>
                </div>
                <div class="exam-stat relative px-3 sm:px-4 py-1.5 sm:py-2 border border-border/50 bg-muted/20 overflow-hidden group/stat">
                    <div class="absolute top-0 left-0 w-0.5 h-full bg-primary/60 group-hover/stat:bg-primary transition-colors"></div>
                    <div class="flex items-center gap-2 skew-x-[-12deg]">
                        <Calendar class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-primary" />
                        <span class="text-[9px] sm:text-[10px] font-black text-muted-foreground uppercase tracking-[0.15em] font-mono">Total</span>
                        <span class="text-sm sm:text-lg font-black text-foreground font-mono">{{ totalCount }}</span>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button
                    v-for="filter in ['all', 'active', 'completed']"
                    :key="filter"
                    @click="activeFilter = filter as 'all' | 'active' | 'completed'"
                    class="exam-filter-tab relative px-4 sm:px-6 py-1.5 sm:py-2 transition-all duration-300 transform -skew-x-12 shrink-0 group/tab border-2"
                    :class="activeFilter === filter 
                        ? 'bg-primary text-primary-foreground border-primary shadow-lg shadow-primary/30 z-10' 
                        : 'bg-muted/10 text-muted-foreground hover:bg-muted/30 hover:text-foreground border-transparent'"
                >
                    <div class="flex items-center gap-2 skew-x-12">
                        <component :is="filter === 'all' ? Calendar : filter === 'active' ? AlertCircle : CheckCircle2" class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                        <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-[0.2em] font-mono">{{ filter }}</span>
                    </div>
                    <!-- Active Indicator -->
                    <div v-if="activeFilter === filter" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 bg-primary-foreground rounded-full skew-x-12"></div>
                </button>
            </div>

            <!-- Section Tabs -->
            <div v-if="sectionTabs.length > 1" class="flex items-center gap-2 sm:gap-3 overflow-x-auto no-scrollbar pb-3">
                <button
                    v-for="section in sectionTabs"
                    :key="section.key"
                    @click="activeSection = section.key"
                    class="exam-section-tab relative flex min-w-[10rem] shrink-0 flex-col gap-2 border px-4 py-3.5 transition-all duration-500 sm:min-w-[12rem] sm:px-6 sm:py-5 group rounded-xl overflow-hidden"
                    :class="activeSection === section.key
                        ? 'border-primary/50 bg-primary/[0.04] shadow-lg shadow-primary/10'
                        : 'border-border/40 bg-card hover:border-primary/30 hover:bg-muted/30'"
                >
                    <div class="flex items-center justify-between w-full relative z-10">
                        <div class="min-w-0 text-left space-y-1">
                            <span class="block truncate text-[10px] font-black uppercase tracking-[0.2em] sm:text-[11px] transition-colors duration-300"
                                :class="activeSection === section.key ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground'">
                                {{ section.label }}
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="block text-[8px] font-bold uppercase tracking-[0.15em] opacity-50 sm:text-[9px]">
                                    {{ section.count }} {{ section.count === 1 ? 'exam' : 'exams' }}
                                </span>
                                <div v-if="activeSection === section.key" class="h-1 w-4 bg-primary/40 rounded-full animate-pulse"></div>
                            </div>
                        </div>
                        
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-[11px] font-black font-mono transition-all duration-500"
                            :class="activeSection === section.key 
                                ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/25 scale-110 rotate-3' 
                                : 'bg-muted/50 text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'"
                        >
                            {{ section.count }}
                        </div>
                    </div>

                    <!-- Decorative elements for active state -->
                    <div v-if="activeSection === section.key" 
                        class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                    <div v-if="activeSection === section.key" 
                        class="absolute -right-4 -bottom-4 w-12 h-12 bg-primary/5 rounded-full blur-2xl"></div>
                </button>
            </div>

            <!-- Exam Grid -->
            <div v-if="filteredExams.length > 0" class="grid gap-2 sm:gap-3 grid-cols-2 xl:grid-cols-3">
                <div 
                    v-for="exam in filteredExams" 
                    :key="exam.id"
                    class="animate-section exam-card relative flex flex-col justify-between p-2 sm:p-5 transition-all duration-500 overflow-hidden group/card border border-border bg-card min-w-0"
                    :class="exam.is_locked 
                        ? 'opacity-60 grayscale-[0.8] cursor-not-allowed bg-muted/10' 
                        : 'hover:-translate-y-1'"
                    @mousemove="handleMouseMove"
                >
                    <!-- Futuristic Corner Brackets -->
                    <div class="bracket-corner absolute top-0 left-0 w-3 h-3 sm:w-4 sm:h-4 border-t-2 border-l-2 border-foreground pointer-events-none"></div>
                    <div class="bracket-corner absolute bottom-0 right-0 w-3 h-3 sm:w-4 sm:h-4 border-b-2 border-r-2 border-foreground pointer-events-none"></div>

                    <!-- Status Badge (Top Left) -->
                    <div class="absolute top-2 left-2 sm:top-4 sm:left-4 z-20">
                        <div class="exam-status-pill px-1.5 sm:px-2.5 py-0.5 sm:py-0.5 font-black text-[7px] sm:text-[8px] uppercase tracking-[0.15em] sm:tracking-[0.2em] transform -skew-x-12"
                            :class="getStatusBadgeInfo(exam).color">
                            <span class="inline-block skew-x-12">{{ getStatusBadgeInfo(exam).label }}</span>
                        </div>
                    </div>

                    <!-- Score / Completion Badge (Top Right) -->
                    <div v-if="exam.is_locked" class="absolute top-2 right-2 sm:top-4 sm:right-4 flex flex-col items-end gap-1 sm:gap-1.5 z-20">
                        <div class="exam-score-pill px-1.5 sm:px-2.5 py-0.5 sm:py-0.5 bg-primary text-primary-foreground font-black text-[7px] sm:text-[9px] font-mono tracking-widest transform -skew-x-12">
                            <span class="inline-block skew-x-12">
                                {{ exam.submissions?.reduce((acc, s) => acc + parseFloat(s.score), 0).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Center Diamond Icon -->
                    <div class="exam-tactical-mark flex justify-center mb-2 sm:mb-3">
                        <div class="w-6 h-6 sm:w-8 sm:h-8 border-2 rotate-45 flex items-center justify-center transition-colors duration-500"
                            :class="exam.is_locked ? 'border-muted-foreground/30' : 'border-amber-500/40 group-hover/card:border-amber-500'">
                             <div class="w-1.5 h-1.5 rotate-45" :class="exam.is_locked ? 'bg-muted-foreground/30' : 'bg-amber-500 animate-pulse'"></div>
                        </div>
                    </div>

                    <div class="relative z-10 space-y-1.5 sm:space-y-3 text-center min-w-0">
                        <!-- Section Label -->
                        <div v-if="exam.section_name" class="text-[7px] sm:text-[8px] font-mono uppercase tracking-[0.25em] text-muted-foreground/70">
                            Section: {{ exam.section_name }}
                        </div>

                        <!-- Title -->
                        <div class="space-y-0.5 sm:space-y-1">
                            <h2 class="text-xs sm:text-lg font-black italic uppercase tracking-tight text-foreground leading-tight sm:leading-none break-words">
                                {{ exam.title }}
                            </h2>
                            <div class="h-px w-6 sm:w-8 bg-foreground/20 mx-auto"></div>
                        </div>

                        <!-- Progress Bar -->
                        <div v-if="exam.total_parts && exam.total_parts > 0" class="space-y-1">
                            <div class="flex items-center justify-center gap-1.5 text-[7px] sm:text-[9px] font-black uppercase tracking-widest font-mono">
                                <span :class="exam.is_locked ? 'text-emerald-500' : 'text-muted-foreground'">
                                    {{ exam.submitted_parts_count }} / {{ exam.total_parts }} PARTS
                                </span>
                            </div>
                            <div class="h-1 bg-muted/50 rounded-full overflow-hidden mx-auto max-w-[80%]">
                                <div class="h-full rounded-full transition-all duration-700 ease-out"
                                    :class="exam.is_locked ? 'bg-emerald-500 w-full' : 'bg-primary'"
                                    :style="{ width: `${getProgressPercent(exam)}%` }">
                                </div>
                            </div>
                        </div>

                        <!-- System Alerts Box -->
                        <div class="exam-alert-box bg-muted/30 p-1.5 sm:p-3 space-y-1 sm:space-y-1.5 text-left border border-border/50">
                            <div class="hidden sm:flex items-start gap-2">
                                <span class="text-amber-500 font-black text-[8px] shrink-0">[!]</span>
                                <p class="text-[8px] font-bold text-muted-foreground uppercase leading-relaxed tracking-wider">
                                    {{ exam.description }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <span class="text-amber-500 font-black text-[8px] sm:text-[9px] shrink-0">[!]</span>
                                <p class="text-[7px] sm:text-[8px] font-bold text-muted-foreground uppercase tracking-wider font-mono truncate">
                                    {{ exam.duration_minutes }} MIN
                                </p>
                            </div>
                            <!-- Date / Countdown -->
                            <div class="flex items-center gap-1.5 sm:gap-2">
                                <Clock class="w-2 h-2 sm:w-2.5 sm:h-2.5 shrink-0" :class="getExamTimeInfo(exam).color" />
                                <p class="text-[7px] sm:text-[8px] font-bold uppercase tracking-wider font-mono truncate"
                                    :class="getExamTimeInfo(exam).color">
                                    {{ getExamTimeInfo(exam).label }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Button (Slanted) -->
                        <div class="pt-1 sm:pt-2 space-y-1.5 sm:space-y-2">
                            <button 
                                v-if="exam.is_locked"
                                @click="openReview(exam)"
                                class="exam-action relative w-full py-1.5 sm:py-2.5 bg-primary text-primary-foreground font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] text-[8px] sm:text-[10px] transition-all hover:bg-foreground hover:text-background transform -skew-x-12"
                            >
                                <span class="inline-block skew-x-12">Review</span>
                            </button>
                            
                            <a 
                                v-else-if="exam.url" 
                                :href="exam.url" 
                                target="_blank"
                                class="exam-action relative w-full py-1.5 sm:py-2.5 bg-primary text-primary-foreground font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] text-[8px] sm:text-[10px] transition-all hover:bg-foreground hover:text-background transform -skew-x-12 flex items-center justify-center gap-1.5 sm:gap-2"
                            >
                                <span class="inline-block skew-x-12">Start</span>
                                <ArrowRight class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 skew-x-12" />
                            </a>
                            <Link 
                                v-else
                                :href="examsShow(exam.id).url"
                                class="exam-action relative w-full py-1.5 sm:py-2.5 bg-primary text-primary-foreground font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] text-[8px] sm:text-[10px] transition-all hover:bg-foreground hover:text-background transform -skew-x-12 flex items-center justify-center gap-1.5 sm:gap-2"
                            >
                                <span class="inline-block skew-x-12">Start</span>
                                <ArrowRight class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 skew-x-12" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="animate-section flex flex-col items-center justify-center py-20 text-center space-y-4 surface-card border-dashed">
                <div class="p-4 rounded-full bg-muted/30">
                    <AlertCircle class="w-12 h-12 text-muted-foreground/50" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold">No exams found</h3>
                    <p v-if="activeFilter !== 'all'" class="text-muted-foreground">Try changing the filter to see more exams.</p>
                    <p v-else class="text-muted-foreground">Keep an eye out! Your instructor will post new exams here.</p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Review Modal -->
    <Dialog v-model:open="showReviewModal">
        <DialogContent class="exam-review-modal sm:max-w-[1000px] w-[95vw] max-h-[90vh] flex flex-col p-0 overflow-hidden bg-card dark:bg-zinc-900 border-border shadow-2xl rounded-none fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
            <!-- Futuristic Corner Brackets -->
            <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-foreground z-50 pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-foreground z-50 pointer-events-none"></div>

            <DialogHeader class="p-5 md:p-10 border-b border-border bg-muted/10 relative">
                <div class="flex flex-col md:flex-row md:items-center justify-center gap-4 md:gap-6 text-center md:text-left">
                    <div class="flex items-center gap-3 md:gap-5 justify-center md:justify-start">
                        <div class="hidden sm:flex w-12 h-12 border-2 border-amber-500 rotate-45 items-center justify-center shrink-0">
                             <div class="w-2 h-2 bg-amber-500 rotate-45 animate-pulse"></div>
                        </div>
                            <div class="space-y-1">
                                <span class="exam-friendly-label text-[8px] md:text-[10px] font-black text-primary uppercase tracking-[0.4em] font-mono">Your results</span>
                                <DialogTitle class="text-xl md:text-3xl font-black italic uppercase tracking-tighter text-foreground leading-none">
                                    {{ selectedExamForReview?.title }}
                                </DialogTitle>
                                <DialogDescription class="hidden sm:block text-[10px] font-bold text-muted-foreground uppercase tracking-widest">
                                    Reviewing performance data and individual operative feedback.
                                </DialogDescription>
                            </div>
                    </div>

                    <div class="flex items-center gap-3 md:gap-4 mx-auto md:mx-0">
                        <!-- Privacy Toggle -->
                        <button 
                            @click="privacyMode = !privacyMode"
                            class="group/privacy flex flex-col items-center gap-1 px-3 md:px-4 py-1.5 md:py-2 border border-border/50 hover:border-primary/50 transition-all relative overflow-hidden bg-muted/20"
                        >
                            <div class="absolute top-0 left-0 w-full h-[1px] bg-primary/30 group-hover/privacy:bg-primary transition-colors"></div>
                            <div class="flex items-center gap-2">
                                <component :is="privacyMode ? Shield : ShieldOff" class="w-2.5 h-2.5 md:w-3 md:h-3" :class="privacyMode ? 'text-primary' : 'text-muted-foreground'" />
                                <span class="exam-friendly-label text-[7px] md:text-[8px] font-black uppercase tracking-[0.2em] font-mono" :class="privacyMode ? 'text-primary' : 'text-muted-foreground'">
                                    {{ privacyMode ? 'Hide answers' : 'Show answers' }}
                                </span>
                            </div>
                            <span class="exam-friendly-label hidden md:block text-[7px] font-bold text-muted-foreground/60 uppercase tracking-widest">Hover to reveal</span>
                        </button>

                        <div class="px-4 md:px-6 py-1.5 md:py-3 bg-muted/30 border border-border/50 relative overflow-hidden group/total">
                            <div class="absolute top-0 left-0 w-1 h-full bg-primary/40 group-hover/total:bg-primary transition-colors"></div>
                            <span class="exam-friendly-label block text-[7px] md:text-[8px] font-black uppercase tracking-[0.3em] text-muted-foreground mb-0.5 md:mb-1 font-mono leading-none">Score</span>
                            <span class="text-lg md:text-2xl font-black text-foreground font-mono tabular-nums leading-none">
                                {{ selectedExamForReview?.submissions?.reduce((acc, s) => acc + parseFloat(s.score), 0).toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <!-- Navigation Tabs for Parts -->
            <div v-if="selectedExamForReview && selectedExamForReview.parts.length > 1" class="px-5 md:px-10 py-4 border-b border-border/50 bg-muted/5 flex items-center gap-2 overflow-x-auto no-scrollbar">
                <button 
                    v-for="part in selectedExamForReview.parts" 
                    :key="part.id"
                    @click="selectedPartId = part.id"
                    class="relative px-6 py-2 transition-all duration-300 transform -skew-x-12 shrink-0 group"
                    :class="selectedPartId === part.id 
                        ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/30' 
                        : 'bg-muted/30 text-muted-foreground hover:bg-muted hover:text-foreground'"
                >
                    <div class="flex items-center gap-3 skew-x-12">
                        <span class="exam-friendly-label text-[8px] font-black uppercase tracking-[0.2em] font-mono opacity-60">
                            Part {{ selectedExamForReview.parts.indexOf(part) + 1 }}
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-widest whitespace-nowrap">
                            {{ part.title }}
                        </span>
                        <div v-if="getSubmissionForPart(selectedExamForReview, part.id)" 
                            class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]">
                        </div>
                    </div>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8 md:p-10 custom-scrollbar bg-card/30">
                <div v-if="selectedExamForReview" class="space-y-16">
                    <div v-for="part in selectedExamForReview.parts" :key="part.id" v-show="selectedPartId === part.id" class="space-y-8">
                        <div class="flex items-center justify-between border-b border-border/30 pb-6">
                            <div class="flex items-center gap-5">
                                <div class="w-2 h-8 bg-primary"></div>
                                <h3 class="text-2xl font-black italic uppercase tracking-tight text-foreground">{{ part.title }}</h3>
                            </div>
                            <div v-if="getSubmissionForPart(selectedExamForReview, part.id)" 
                                class="px-6 py-3 bg-foreground text-background font-black text-xs uppercase tracking-[0.2em] transform -skew-x-12">
                                <span class="inline-block skew-x-12 font-mono">
                                    Score: {{ getSubmissionForPart(selectedExamForReview, part.id)?.score }} / {{ part.questions?.reduce((acc, q) => acc + (parseInt(q.points) || 1), 0) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div 
                                v-for="(question, qIndex) in part.questions" 
                                :key="qIndex"
                                class="p-8 transition-all duration-500 relative overflow-hidden border bg-muted/5 group/question"
                                :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))
                                    ? 'border-emerald-500/20 shadow-[0_0_30px_-15px_rgba(16,185,129,0.1)]' 
                                    : 'border-red-500/20 shadow-[0_0_30px_-15px_rgba(239,68,68,0.1)]'"
                            >
                                <!-- Status Accent -->
                                <div class="absolute top-0 left-0 w-1.5 h-full"
                                    :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'bg-emerald-500' : 'bg-red-500'">
                                </div>

                                <!-- Privacy Overlay for blurred state -->
                                <div v-if="privacyMode" class="absolute inset-0 z-20 flex items-center justify-center opacity-100 group-hover/question:opacity-0 pointer-events-none transition-opacity duration-300">
                                    <div class="flex items-center gap-3 px-4 py-2 bg-background/80 border border-primary/20 backdrop-blur-sm shadow-xl transform rotate-[-2deg]">
                                        <Shield class="w-4 h-4 text-primary animate-pulse" />
                                        <span class="text-xs font-black text-primary uppercase tracking-[0.2em]">Hover to reveal</span>
                                    </div>
                                </div>

                                <div class="space-y-8 transition-all duration-500"
                                    :class="privacyMode ? 'blur-md group-hover/question:blur-0 select-none' : ''">
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-4">
                                                <span class="exam-friendly-label text-xs font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">Question {{ qIndex + 1 }}</span>
                                                <span class="text-[10px] font-black text-primary uppercase tracking-widest px-2 py-0.5 border border-primary/20 font-mono">{{ question.type.replace('_', ' ') }}</span>
                                            </div>
                                            <div v-if="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1), getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))" 
                                                class="text-emerald-500 font-black text-xs font-mono uppercase tracking-widest flex items-center gap-2">
                                                <CheckCircle2 class="w-4 h-4" />
                                                {{ question.type === 'essay' ? 'Reviewed' : 'Correct' }}
                                            </div>
                                            <div v-else class="text-red-500 font-black text-xs font-mono uppercase tracking-widest flex items-center gap-2">
                                                <XCircle class="w-4 h-4" />
                                                {{ question.type === 'essay' ? 'Needs work' : 'Incorrect' }}
                                            </div>
                                        </div>
                                        <p class="font-black italic tracking-tight text-lg text-foreground leading-snug whitespace-pre-wrap">{{ question.text }}</p>
                                    </div>

                                    <div class="space-y-6">
                                        <!-- Multiple Choice / True False -->
                                        <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="grid grid-cols-1 gap-4">
                                            <div 
                                                v-for="(option, oIndex) in question.options" 
                                                :key="oIndex"
                                                class="text-sm p-5 border flex items-center justify-between transition-all font-mono uppercase tracking-widest"
                                                :class="[
                                                    option.is_correct 
                                                        ? 'bg-emerald-500 text-white dark:text-zinc-950 border-emerald-500 font-black' 
                                                        : parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex 
                                                            ? 'bg-red-500/10 border-red-500/50 text-red-500'
                                                            : 'bg-muted/30 border-border/50 text-muted-foreground opacity-50',
                                                ]"
                                            >
                                                <span class="flex-1 whitespace-pre-wrap">{{ option.text }}</span>
                                                <div v-if="parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex" 
                                                    class="ml-4 px-3 py-1.5 bg-foreground text-background text-[10px] font-black uppercase tracking-[0.2em] transform -skew-x-12">
                                                    <span class="inline-block skew-x-12">Your answer</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'" class="space-y-4">
                                            <div class="p-5 bg-muted/30 border border-border/50 flex flex-col gap-2 relative overflow-hidden">
                                                <div class="absolute top-0 left-0 w-1.5 h-full" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'bg-emerald-500/40' : 'bg-red-500/40'"></div>
                                                <span class="exam-friendly-label text-[10px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">Your answer</span>
                                                <span class="font-black text-base tracking-widest whitespace-pre-wrap" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-500' : 'text-red-500'">
                                                    {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No answer' }}
                                                </span>
                                            </div>
                                            <div class="p-5 bg-emerald-500/5 border border-emerald-500/30 flex flex-col gap-2">
                                                <span class="exam-friendly-label text-[10px] font-black text-emerald-500 uppercase tracking-[0.3em] font-mono">Correct answer</span>
                                                <span class="font-black text-base tracking-widest text-emerald-600 whitespace-pre-wrap">
                                                    {{ question.correct_answer }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Essay -->
                                        <div v-else-if="question.type === 'essay'" class="space-y-6">
                                            <div class="p-5 bg-muted/30 border border-border/50 flex flex-col gap-2 relative overflow-hidden">
                                                <div class="absolute top-0 left-0 w-1.5 h-full bg-primary/40"></div>
                                                <span class="exam-friendly-label text-[10px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">Your response</span>
                                                <p class="font-bold text-base leading-relaxed tracking-tight text-foreground whitespace-pre-wrap">
                                                    {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No response submitted' }}
                                                </p>
                                            </div>

                                            <!-- AI Assessment Display for Essay -->
                                            <div v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score !== undefined" 
                                                class="p-5 bg-primary/5 border border-primary/20 flex flex-col gap-4 relative group/ai overflow-hidden">
                                                <div class="absolute top-0 right-0 w-16 h-16 bg-primary/5 -rotate-45 translate-x-8 -translate-y-8 group-hover/ai:bg-primary/10 transition-colors"></div>
                                                
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <Zap class="w-4 h-4 text-primary animate-pulse" />
                                                        <span class="exam-friendly-label text-[10px] font-black text-primary uppercase tracking-[0.3em] font-mono">Essay feedback</span>
                                                    </div>
                                                    <div class="px-3 py-1 bg-primary text-primary-foreground font-black text-[10px] font-mono tracking-widest transform -skew-x-12">
                                                        <span class="inline-block skew-x-12">Score: {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_score }} / {{ question.points }}</span>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback"
                                                    class="space-y-2 border-t border-primary/10 pt-4"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="exam-friendly-label text-[10px] font-black text-muted-foreground uppercase tracking-[0.25em] font-mono">Feedback</span>
                                                    </div>
                                                    <p class="text-sm leading-relaxed text-foreground/90 whitespace-pre-wrap">
                                                        {{ getAnswerObjectForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)?.ai_feedback }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                v-else-if="getSubmissionForPart(selectedExamForReview, part.id)?.status === 'pending_ai'"
                                                class="p-5 bg-muted/20 border border-border/40 flex items-center justify-between"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <Timer class="w-4 h-4 text-amber-500" />
                                                    <span class="exam-friendly-label text-[10px] font-black text-amber-500 uppercase tracking-[0.3em] font-mono">Feedback</span>
                                                </div>
                                                <span class="text-[10px] font-bold text-muted-foreground font-mono uppercase tracking-widest">Awaiting release</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="p-8 md:p-10 border-t border-border bg-muted/10 flex flex-col md:flex-row gap-4 items-center justify-between">
                <Button variant="secondary" @click="showReviewModal = false" 
                    class="w-full md:w-auto bg-muted/20 text-muted-foreground font-black uppercase tracking-[0.3em] text-[10px] transform -skew-x-12 hover:bg-red-500/10 hover:text-red-500 px-8 h-12 rounded-none border border-border/50">
                    <span class="inline-block skew-x-12">Close</span>
                </Button>

                <div v-if="selectedExamForReview && selectedExamForReview.parts.length > 1" class="flex items-center gap-4 w-full md:w-auto">
                    <button 
                        v-if="selectedExamForReview.parts.findIndex(p => p.id === selectedPartId) > 0"
                        @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex(p => p.id === selectedPartId) - 1].id"
                        class="flex-1 md:flex-none px-6 py-3 bg-muted/30 text-muted-foreground font-black uppercase tracking-[0.2em] text-[10px] transform -skew-x-12 border border-border/50 hover:bg-primary/10 hover:text-primary transition-all"
                    >
                        <span class="inline-block skew-x-12">Previous part</span>
                    </button>
                    
                    <button 
                        v-if="selectedExamForReview.parts.findIndex(p => p.id === selectedPartId) < selectedExamForReview.parts.length - 1"
                        @click="selectedPartId = selectedExamForReview.parts[selectedExamForReview.parts.findIndex(p => p.id === selectedPartId) + 1].id"
                        class="flex-1 md:flex-none px-10 py-3 bg-primary text-primary-foreground font-black uppercase tracking-[0.2em] text-[10px] transform -skew-x-12 shadow-lg shadow-primary/30 hover:shadow-xl hover:shadow-primary/50 transition-all"
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
    @apply bg-card/50 backdrop-blur-md border border-border/50 rounded-2xl;
}

.animate-section {
    will-change: transform, opacity;
}

.exam-card {
    opacity: 0;
}

@keyframes scan-vertical {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(1000%); }
}

.animate-scan-vertical {
    animation: scan-vertical 4s linear infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(200%); }
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
