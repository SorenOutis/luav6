<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { show as examsShow } from '@/routes/exams';
import { onMounted, ref, computed } from 'vue';

usePoll(10000, {
    only: ['exams']
});
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Calendar, Clock, ExternalLink, AlertCircle, Lock, Eye, CheckCircle2, XCircle, HelpCircle } from 'lucide-vue-next';
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
}

const props = defineProps<{
    exams: Exam[];
}>();

const showReviewModal = ref(false);
const selectedExamForReview = ref<Exam | null>(null);

const openReview = (exam: Exam) => {
    selectedExamForReview.value = exam;
    showReviewModal.value = true;
};

const getSubmissionForPart = (exam: Exam, partId: number) => {
    return exam.submissions?.find(s => s.exam_part_id === partId);
};

const getAnswerForQuestion = (answers: any, questionNumber: number) => {
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
    
    const entry = parsedAnswers.find((a: any) => a.question_number === questionNumber);
    return entry ? entry.answer : null;
};

const isAnswerCorrect = (question: any, submittedAnswer: any) => {
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

onMounted(() => {
    if (!examContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'expo.out', duration: 1.2 }
    });

    // Initial states - more controlled, less blur
    gsap.set('.exam-hero', { opacity: 0, x: -20 });
    gsap.set('.exam-card', {
        opacity: 0,
        y: 40,
        scale: 0.98,
        transformOrigin: 'center bottom'
    });
    gsap.set('.exam-card .absolute.top-0, .exam-card .absolute.bottom-0', { scale: 0 });

    // 1. Hero entrance
    tl.to('.exam-hero', { 
        opacity: 1, 
        x: 0, 
        duration: 1
    });

    // 2. Card entrance - Staggered slide and fade
    tl.to(
        '.exam-card',
        {
            opacity: 1,
            y: 0,
            scale: 1,
            stagger: 0.1,
            duration: 1.2,
            clearProps: 'filter'
        },
        '-=0.7'
    );

    // 3. Bracket reveal - "Locking in" effect
    tl.to(
        '.exam-card .absolute.top-0, .exam-card .absolute.bottom-0',
        {
            scale: 1,
            stagger: 0.05,
            duration: 0.8,
            ease: 'back.out(2)'
        },
        '-=1'
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
        <div ref="examContainer" class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Glassy background decorative orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Header Section -->
            <div class="animate-section exam-hero space-y-1 relative group/hero">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-[2px] bg-primary/40 rounded-full group-hover/hero:w-12 transition-all duration-500"></div>
                    <h1 class="text-2xl font-black tracking-tighter uppercase">Upcoming_Activities</h1>
                </div>
                <p class="text-muted-foreground text-sm font-medium pl-11 border-l-2 border-primary/10 group-hover/hero:border-primary/30 transition-colors">
                    Manage your assessments and upcoming academic challenges.
                </p>
            </div>

            <!-- Exam Grid -->
            <div v-if="exams.length > 0" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div 
                    v-for="exam in exams" 
                    :key="exam.id"
                    class="animate-section exam-card relative flex flex-col justify-between p-8 transition-all duration-500 overflow-hidden group/card border border-border bg-card dark:bg-zinc-900/40"
                    :class="exam.is_locked 
                        ? 'opacity-60 grayscale-[0.8] cursor-not-allowed bg-muted/10' 
                        : 'hover:shadow-2xl hover:-translate-y-1'"
                    @mousemove="handleMouseMove"
                >
                    <!-- Futuristic Corner Brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-foreground pointer-events-none"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-foreground pointer-events-none"></div>

                    <!-- Status & Score Overlay -->
                    <div v-if="exam.is_locked" class="absolute top-6 right-6 flex flex-col items-end gap-2 z-20">
                        <div class="px-3 py-1 bg-emerald-500 text-white dark:text-zinc-950 font-black text-[9px] uppercase tracking-[0.2em] transform -skew-x-12 shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                            <span class="inline-block skew-x-12">COMPLETED</span>
                        </div>
                        <div class="px-3 py-1 bg-foreground text-background font-black text-[10px] font-mono tracking-widest transform -skew-x-12">
                            <span class="inline-block skew-x-12">
                                SCORE: {{ exam.submissions?.reduce((acc, s) => acc + parseFloat(s.score), 0).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Center Diamond Icon -->
                    <div class="flex justify-center mb-6">
                        <div class="w-12 h-12 border-2 rotate-45 flex items-center justify-center transition-colors duration-500"
                            :class="exam.is_locked ? 'border-muted-foreground/30' : 'border-amber-500/40 group-hover/card:border-amber-500'">
                             <div class="w-2 h-2 rotate-45" :class="exam.is_locked ? 'bg-muted-foreground/30' : 'bg-amber-500 animate-pulse'"></div>
                        </div>
                    </div>

                    <div class="relative z-10 space-y-6 text-center">
                        <div class="space-y-2">
                            <h2 class="text-2xl font-black italic uppercase tracking-tight text-foreground leading-none">
                                {{ exam.title }}
                            </h2>
                            <div class="h-px w-12 bg-foreground/20 mx-auto"></div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-[0.2em]">
                                Initiating <span class="text-foreground underline underline-offset-4 decoration-2">Assessment Protocol</span>
                            </p>
                        </div>

                        <!-- System Alerts Box -->
                        <div class="bg-muted/30 dark:bg-zinc-950/40 p-5 space-y-3 text-left border border-border/50">
                            <div class="flex items-start gap-3">
                                <span class="text-amber-500 font-black text-[10px] shrink-0">[!]</span>
                                <p class="text-[9px] font-bold text-muted-foreground uppercase leading-relaxed tracking-wider">
                                    {{ exam.description }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-amber-500 font-black text-[10px] shrink-0">[!]</span>
                                <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider font-mono">
                                    TIME_LIMIT: {{ exam.duration_minutes }} MINUTES
                                </p>
                            </div>
                        </div>

                        <!-- Action Button (Slanted) -->
                        <div class="pt-4 space-y-4">
                            <button 
                                v-if="exam.is_locked"
                                @click="openReview(exam)"
                                class="relative w-full py-4 bg-foreground text-background font-black uppercase tracking-[0.3em] text-[11px] transition-all hover:bg-primary hover:text-primary-foreground transform -skew-x-12"
                            >
                                <span class="inline-block skew-x-12">Recap Assessment Answers</span>
                            </button>
                            
                            <a 
                                v-else-if="exam.url" 
                                :href="exam.url" 
                                target="_blank"
                                class="relative w-full py-4 bg-foreground text-background font-black uppercase tracking-[0.3em] text-[11px] transition-all hover:bg-primary hover:text-primary-foreground transform -skew-x-12 flex items-center justify-center gap-3"
                            >
                                <span class="inline-block skew-x-12">Initialize Now</span>
                                <ArrowRight class="w-4 h-4 skew-x-12" />
                            </a>
                            <Link 
                                v-else
                                :href="examsShow(exam.id).url"
                                class="relative w-full py-4 bg-foreground text-background font-black uppercase tracking-[0.3em] text-[11px] transition-all hover:bg-primary hover:text-primary-foreground transform -skew-x-12 flex items-center justify-center gap-3"
                            >
                                <span class="inline-block skew-x-12">Initialize Now</span>
                                <ArrowRight class="w-4 h-4 skew-x-12" />
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
                    <h3 class="text-xl font-semibold">No active exams found</h3>
                    <p class="text-muted-foreground">Keep an eye out! Your instructor will post new exams here.</p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Review Modal -->
    <Dialog v-model:open="showReviewModal">
        <DialogContent class="sm:max-w-[1000px] w-[95vw] max-h-[90vh] flex flex-col p-0 overflow-hidden bg-card dark:bg-zinc-900 border-border shadow-2xl rounded-none fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
            <!-- Futuristic Corner Brackets -->
            <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-foreground z-50 pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-foreground z-50 pointer-events-none"></div>

            <DialogHeader class="p-8 md:p-10 border-b border-border bg-muted/10 relative">
                <div class="flex flex-col md:flex-row md:items-center justify-center gap-6 text-center md:text-left">
                    <div class="flex items-center gap-5 justify-center md:justify-start">
                        <div class="w-12 h-12 border-2 border-amber-500 rotate-45 flex items-center justify-center shrink-0">
                             <div class="w-2 h-2 bg-amber-500 rotate-45 animate-pulse"></div>
                        </div>
                        <div class="space-y-1">
                            <span class="text-[10px] font-black text-primary uppercase tracking-[0.4em] font-mono">ASSESSMENT_DEBRIEF_PROTOCOL</span>
                            <DialogTitle class="text-3xl md:text-4xl font-black italic uppercase tracking-tighter text-foreground leading-none">
                                {{ selectedExamForReview?.title }}
                            </DialogTitle>
                            <DialogDescription class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">
                                Reviewing performance data and individual operative feedback.
                            </DialogDescription>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-muted/30 border border-border/50 relative overflow-hidden group/total mx-auto md:mx-0">
                        <div class="absolute top-0 left-0 w-1 h-full bg-primary/40 group-hover/total:bg-primary transition-colors"></div>
                        <span class="block text-[8px] font-black uppercase tracking-[0.3em] text-muted-foreground mb-1 font-mono">TOTAL_SCORE</span>
                        <span class="text-2xl font-black text-foreground font-mono tabular-nums">
                            {{ selectedExamForReview?.submissions?.reduce((acc, s) => acc + parseFloat(s.score), 0).toFixed(2) }}
                        </span>
                    </div>
                </div>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto p-8 md:p-10 custom-scrollbar bg-card/30">
                <div v-if="selectedExamForReview" class="space-y-16">
                    <div v-for="part in selectedExamForReview.parts" :key="part.id" class="space-y-8">
                        <div class="flex items-center justify-between border-b border-border/30 pb-4">
                            <div class="flex items-center gap-4">
                                <div class="w-1.5 h-6 bg-primary"></div>
                                <h3 class="text-xl font-black italic uppercase tracking-tight text-foreground">{{ part.title }}</h3>
                            </div>
                            <div v-if="getSubmissionForPart(selectedExamForReview, part.id)" 
                                class="px-4 py-2 bg-foreground text-background font-black text-[10px] uppercase tracking-[0.2em] transform -skew-x-12">
                                <span class="inline-block skew-x-12 font-mono">
                                    PART_SCORE: {{ getSubmissionForPart(selectedExamForReview, part.id)?.score }} / {{ part.questions?.reduce((acc, q) => acc + (parseInt(q.points) || 1), 0) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div 
                                v-for="(question, qIndex) in part.questions" 
                                :key="qIndex"
                                class="p-6 transition-all duration-500 relative overflow-hidden border bg-muted/5 group/question"
                                :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))
                                    ? 'border-emerald-500/20 shadow-[0_0_30px_-15px_rgba(16,185,129,0.1)]' 
                                    : 'border-red-500/20 shadow-[0_0_30px_-15px_rgba(239,68,68,0.1)]'"
                            >
                                <!-- Status Accent -->
                                <div class="absolute top-0 left-0 w-1 h-full"
                                    :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'bg-emerald-500' : 'bg-red-500'">
                                </div>

                                <div class="space-y-6">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <span class="text-[9px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">OP_{{ (qIndex + 1).toString().padStart(2, '0') }}</span>
                                                <span class="text-[8px] font-black text-primary uppercase tracking-widest px-2 py-0.5 border border-primary/20 font-mono">{{ question.type.replace('_', ' ') }}</span>
                                            </div>
                                            <div v-if="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))" 
                                                class="text-emerald-500 font-black text-[9px] font-mono uppercase tracking-widest flex items-center gap-1.5">
                                                <CheckCircle2 class="w-3.5 h-3.5" />
                                                SUCCESS
                                            </div>
                                            <div v-else class="text-red-500 font-black text-[9px] font-mono uppercase tracking-widest flex items-center gap-1.5">
                                                <XCircle class="w-3.5 h-3.5" />
                                                FAILED
                                            </div>
                                        </div>
                                        <p class="font-black italic tracking-tight text-sm text-foreground leading-snug whitespace-pre-wrap">{{ question.text }}</p>
                                    </div>

                                    <div class="space-y-4">
                                        <!-- Multiple Choice / True False -->
                                        <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="grid grid-cols-1 gap-3">
                                            <div 
                                                v-for="(option, oIndex) in question.options" 
                                                :key="oIndex"
                                                class="text-[10px] p-4 border flex items-center justify-between transition-all font-mono uppercase tracking-widest"
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
                                                    class="ml-3 px-2 py-1 bg-foreground text-background text-[7px] font-black uppercase tracking-[0.2em] transform -skew-x-12">
                                                    <span class="inline-block skew-x-12">USER_ANS</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'" class="space-y-3">
                                            <div class="p-4 bg-muted/30 border border-border/50 flex flex-col gap-1.5 relative overflow-hidden">
                                                <div class="absolute top-0 left-0 w-1 h-full" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'bg-emerald-500/40' : 'bg-red-500/40'"></div>
                                                <span class="text-[8px] font-black text-muted-foreground uppercase tracking-[0.3em] font-mono">USER_INPUT</span>
                                                <span class="font-black text-xs tracking-widest whitespace-pre-wrap" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-emerald-500' : 'text-red-500'">
                                                    {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'NULL_VALUE' }}
                                                </span>
                                            </div>
                                            <div class="p-4 bg-emerald-500/5 border border-emerald-500/30 flex flex-col gap-1.5">
                                                <span class="text-[8px] font-black text-emerald-500 uppercase tracking-[0.3em] font-mono">SYSTEM_REFERENCE</span>
                                                <span class="font-black text-xs tracking-widest text-emerald-600 whitespace-pre-wrap">
                                                    {{ question.correct_answer }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="p-8 md:p-10 border-t border-border bg-muted/10">
                <Button variant="secondary" @click="showReviewModal = false" 
                    class="w-full md:w-auto bg-foreground text-background font-black uppercase tracking-[0.3em] text-[10px] transform -skew-x-12 hover:bg-primary hover:text-primary-foreground px-12 h-12 rounded-none">
                    <span class="inline-block skew-x-12">Close Review</span>
                </Button>
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
    background: rgba(var(--primary), 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary), 0.2);
}
</style>
