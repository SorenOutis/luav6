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
        defaults: { ease: 'power4.out', duration: 1.1 }
    });

    // Initial states
    gsap.set('.exam-hero', { opacity: 0, y: 30, filter: 'blur(5px)' });
    gsap.set('.exam-card', {
        opacity: 0,
        y: 50,
        scale: 0.95,
        rotationX: -10,
        filter: 'blur(10px)',
        transformOrigin: 'center top'
    });

    // Hero entrance
    tl.to('.exam-hero', { 
        opacity: 1, 
        y: 0, 
        filter: 'blur(0px)', 
        duration: 0.8 
    });

    // Card entrance with depth and liquid stagger
    tl.to(
        '.exam-card',
        {
            opacity: 1,
            y: 0,
            scale: 1,
            rotationX: 0,
            filter: 'blur(0px)',
            stagger: {
                each: 0.08,
                from: "start",
                ease: "power2.inOut"
            },
            duration: 1.2,
            ease: 'elastic.out(1, 0.75)'
        },
        '-=0.5'
    );

    // Background orb animation
    const orbs = examContainer.value.querySelectorAll('.orb');
    orbs.forEach((orb, i) => {
        gsap.to(orb, {
            x: `random(-60, 60)`,
            y: `random(-60, 60)`,
            duration: 15 + i * 5,
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
                    class="animate-section exam-card surface-card p-4 md:p-5 flex flex-col justify-between transition-all duration-500 overflow-hidden relative group/card"
                    :class="exam.is_locked 
                        ? 'opacity-70 cursor-not-allowed' 
                        : 'hover:border-primary/50 hover:shadow-2xl hover:shadow-primary/10 hover:-translate-y-1'"
                    @mousemove="handleMouseMove"
                >
                    <!-- Tech Grid Background (New) -->
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none group-hover/card:opacity-[0.05] transition-opacity">
                        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                            <defs>
                                <pattern id="exam-grid" width="15" height="15" patternUnits="userSpaceOnUse">
                                    <path d="M 15 0 L 0 0 0 15" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#exam-grid)" />
                        </svg>
                    </div>

                    <!-- Tech Scanning Line (New) -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/10 to-transparent w-32 h-full -translate-x-full group-hover/card:animate-scan-horizontal pointer-events-none opacity-0 group-hover/card:opacity-100 transition-opacity"></div>

                    <!-- Card Shine/Bloom Effect (New) -->
                    <div class="absolute inset-0 opacity-0 group-hover/card:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.08), transparent 40%)">
                    </div>

                    <!-- Glossy accent -->
                    <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/5 rounded-full blur-3xl"
                        :class="!exam.is_locked && 'group-hover/card:bg-primary/10 transition-colors duration-500'">
                    </div>

                    <!-- Lock overlay for completed exams -->
                    <div v-if="exam.is_locked" class="absolute inset-0 rounded-2xl bg-gradient-to-br from-background/40 to-background/20 backdrop-blur-[2px] z-[5] pointer-events-none"></div>
                    
                    <div class="space-y-3 relative z-10">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2">
                                <div class="px-2.5 py-0.5 rounded-full bg-primary/10 border border-primary/20 text-[10px] font-black text-primary uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                                    <div v-if="exam.status === 'published' && !exam.is_locked" class="w-1 h-1 rounded-full bg-primary animate-pulse"></div>
                                    {{ exam.status }}
                                </div>
                                <div v-if="exam.is_locked" class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-yellow-500/10 border border-yellow-500/30 text-[9px] font-black text-yellow-600 uppercase tracking-widest shadow-sm">
                                    <Lock class="w-2.5 h-2.5" />
                                    DONE
                                </div>
                            </div>
                            <div class="flex items-center text-muted-foreground font-mono text-[9px] gap-1 px-2 py-0.5 rounded-md bg-muted/30 border border-border/50">
                                <Clock class="w-2.5 h-2.5" />
                                {{ exam.duration_minutes }}M_LIMIT
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-black tracking-tight group-hover/card:text-primary transition-colors duration-300">
                                {{ exam.title }}
                            </h3>
                            <p class="text-muted-foreground text-[10px] font-medium line-clamp-2 mt-0.5 leading-relaxed">{{ exam.description }}</p>
                        </div>

                        <!-- Progress bar for exam completion -->
                        <div v-if="exam.total_parts && exam.total_parts > 0" class="pt-1">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[8px] font-black text-muted-foreground/60 uppercase tracking-widest font-mono">
                                    >_PROGRESS_FEED
                                </span>
                                <span class="text-[9px] font-black text-primary font-mono tabular-nums">
                                    {{ Math.round(((exam.submitted_parts_count || 0) / exam.total_parts) * 100) }}%
                                </span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-muted/50 overflow-hidden border border-primary/10 relative p-[1px]">
                                <div class="h-full bg-gradient-to-r from-primary/80 to-primary rounded-full transition-all duration-1000 ease-out relative"
                                    :style="{ width: `${(exam.submitted_parts_count || 0) / exam.total_parts * 100}%` }">
                                    <!-- Glow Tip -->
                                    <div class="absolute right-0 top-0 h-full w-1.5 bg-white shadow-[0_0_8px_rgba(var(--primary),0.8)] rounded-full"></div>
                                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-1">
                            <div class="flex items-center gap-2.5 text-[10px] font-bold text-foreground/70 group-hover/card:text-foreground transition-colors">
                                <Calendar class="w-3 h-3 text-primary/70" />
                                <span class="font-mono uppercase tracking-tighter">{{ formatDateTime(exam.exam_date) }}</span>
                            </div>

                            <!-- Exam Parts Summary -->
                            <div v-if="exam.parts.length > 0" class="pt-3 space-y-2 border-t border-border/30">
                                <div class="flex items-center gap-2">
                                    <div class="w-1.5 h-[1px] bg-primary/40"></div>
                                    <p class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/50 font-mono">STRUCTURE_DATA</p>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <div 
                                        v-for="part in exam.parts" 
                                        :key="part.id"
                                        class="px-2 py-0.5 rounded-md bg-primary/5 border border-primary/10 text-[9px] font-bold flex items-center gap-2 hover:bg-primary/10 hover:border-primary/20 transition-all cursor-default group/part"
                                    >
                                        <div class="w-1 h-1 rounded-full bg-primary/40 group-hover/part:bg-primary transition-colors"></div>
                                        <span class="font-mono uppercase tracking-tighter">{{ part.title }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 relative z-10 flex flex-col gap-2">
                        <button 
                            v-if="exam.is_locked"
                            @click="openReview(exam)"
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-secondary/10 text-secondary-foreground font-black uppercase tracking-widest hover:bg-secondary/20 transition-all border border-secondary/20 shadow-sm text-[10px] group/btn"
                        >
                            <Eye class="w-3.5 h-3.5 transition-transform group-hover/btn:scale-110" />
                            Review_Data
                        </button>
                        
                        <button 
                            v-if="exam.is_locked"
                            disabled
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-muted/30 text-muted-foreground font-black uppercase tracking-widest cursor-not-allowed opacity-60 text-[10px] border border-border/50"
                        >
                            <Lock class="w-3 h-3" />
                            COMPLETED
                        </button>
                        <a 
                            v-else-if="exam.url" 
                            :href="exam.url" 
                            target="_blank"
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-primary text-primary-foreground font-black uppercase tracking-widest hover:opacity-90 transition-all shadow-lg shadow-primary/20 text-[10px] group/btn"
                        >
                            EXECUTE_START
                            <ExternalLink class="w-3 h-3 transition-transform group-hover/btn:translate-x-0.5 group-hover/btn:-translate-y-0.5" />
                        </a>
                        <Link 
                            v-else
                            :href="examsShow(exam.id).url"
                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-primary text-primary-foreground font-black uppercase tracking-widest hover:opacity-90 transition-all shadow-lg shadow-primary/20 text-[10px]"
                        >
                            EXECUTE_START
                        </Link>
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
        <DialogContent class="sm:max-w-[1000px] w-[95vw] max-h-[90vh] flex flex-col p-0 overflow-hidden bg-background border-primary/20 shadow-2xl rounded-2xl">
            <DialogHeader class="p-6 md:p-8 border-b bg-muted/20">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <DialogTitle class="text-2xl font-black bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent">
                            {{ selectedExamForReview?.title }}
                        </DialogTitle>
                        <DialogDescription class="text-muted-foreground font-medium">
                            Review your performance and individual question feedback.
                        </DialogDescription>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="px-4 py-2 rounded-xl bg-primary/10 border border-primary/20 text-center">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-primary/60">Total Score</span>
                            <span class="text-lg font-bold text-primary">
                                {{ selectedExamForReview?.submissions?.reduce((acc, s) => acc + parseFloat(s.score), 0).toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto p-6 md:p-8 custom-scrollbar bg-background/50">
                <div v-if="selectedExamForReview" class="space-y-10">
                    <div v-for="part in selectedExamForReview.parts" :key="part.id" class="space-y-6">
                        <div class="flex items-center justify-between border-b border-border/50 pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <HelpCircle class="w-4 h-4 text-primary" />
                                </div>
                                <h3 class="text-xl font-bold tracking-tight">{{ part.title }}</h3>
                            </div>
                            <div v-if="getSubmissionForPart(selectedExamForReview, part.id)" class="px-3 py-1 rounded-full bg-muted border text-xs font-bold">
                                Part Score: {{ getSubmissionForPart(selectedExamForReview, part.id)?.score }} / {{ part.questions?.reduce((acc, q) => acc + (parseInt(q.points) || 1), 0) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div 
                                v-for="(question, qIndex) in part.questions" 
                                :key="qIndex"
                                class="p-5 rounded-2xl border transition-all duration-300 relative overflow-hidden group"
                                :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))
                                    ? 'bg-green-500/5 border-green-500/20 hover:border-green-500/40' 
                                    : 'bg-red-500/5 border-red-500/20 hover:border-red-500/40'"
                            >
                                <!-- Corner Indicator -->
                                <div class="absolute top-0 right-0 w-12 h-12 overflow-hidden pointer-events-none">
                                    <div class="absolute top-2 right-2">
                                        <CheckCircle2 v-if="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1))" class="w-5 h-5 text-green-500 opacity-50" />
                                        <XCircle v-else class="w-5 h-5 text-red-500 opacity-50" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black text-muted-foreground uppercase tracking-widest">Question {{ qIndex + 1 }}</span>
                                            <Badge variant="outline" class="text-[9px] uppercase tracking-tighter px-1.5 py-0 h-4">{{ question.type.replace('_', ' ') }}</Badge>
                                        </div>
                                        <p class="font-bold text-sm leading-snug">{{ question.text }}</p>
                                    </div>

                                    <div class="space-y-3">
                                        <!-- Multiple Choice / True False -->
                                        <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="grid grid-cols-1 gap-2">
                                            <div 
                                                v-for="(option, oIndex) in question.options" 
                                                :key="oIndex"
                                                class="text-xs p-3 rounded-xl border flex items-center justify-between transition-all"
                                                :class="[
                                                    option.is_correct 
                                                        ? 'bg-green-500/10 border-green-500/30 text-green-700 dark:text-green-400 font-bold' 
                                                        : 'bg-background/50 border-border/50 text-muted-foreground',
                                                    parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex 
                                                        ? 'ring-2 ring-primary ring-offset-2 dark:ring-offset-zinc-950' 
                                                        : ''
                                                ]"
                                            >
                                                <span class="flex-1">{{ option.text }}</span>
                                                <div v-if="parseInt(getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) === oIndex" 
                                                    class="ml-2 px-1.5 py-0.5 rounded bg-primary text-primary-foreground text-[8px] font-black uppercase tracking-widest">
                                                    Your Answer
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'" class="space-y-2">
                                            <div class="p-3 rounded-xl border bg-background/50 flex flex-col gap-1">
                                                <span class="text-[9px] font-black text-muted-foreground uppercase tracking-widest">Your Answer</span>
                                                <span class="font-bold text-sm" :class="isAnswerCorrect(question, getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1)) ? 'text-green-600' : 'text-red-600'">
                                                    {{ getAnswerForQuestion(getSubmissionForPart(selectedExamForReview, part.id)?.answers, qIndex + 1) || 'No answer' }}
                                                </span>
                                            </div>
                                            <div class="p-3 rounded-xl border border-green-500/30 bg-green-500/10 flex flex-col gap-1">
                                                <span class="text-[9px] font-black text-green-600 uppercase tracking-widest">Correct Answer</span>
                                                <span class="font-bold text-sm text-green-700 dark:text-green-400">
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

            <DialogFooter class="p-4 md:p-6 border-t bg-muted/10">
                <Button variant="secondary" @click="showReviewModal = false" class="w-full md:w-auto font-bold uppercase tracking-wider text-xs px-8">
                    Close Review
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

@keyframes scan-horizontal {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(1000%); }
}

.animate-scan-horizontal {
    animation: scan-horizontal 3s linear infinite;
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
