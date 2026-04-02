<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, ref, computed, reactive } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    Calendar, Clock, ChevronLeft, ChevronRight, BookOpen,
    CheckCircle2, HelpCircle, FileText, Settings2, GraduationCap,
    PlayCircle, ArrowRight, Layers, ListChecks, Users2, Trophy, Lock, CheckSquare2
} from 'lucide-vue-next';

interface Question {
    text: string;
    type: string;
    options: { text: string; is_correct: boolean }[] | null;
    correct_answer: string | null;
    points: number;
}

interface ExamPart {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    questions: Question[] | null;
    points: number;
}

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    duration_minutes: number;
    status: string;
    parts: ExamPart[];
}

const props = defineProps<{ 
    exam: Exam;
    submissions: Record<number, { status: string; score: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
    { title: props.exam.title, href: `/exams/${props.exam.id}` },
];

const selectedPart = ref<ExamPart | null>(null);
const examStarted = ref(false);
const container = ref<HTMLElement | null>(null);
const answers = reactive<Record<number, string | number>>({}); // Store answers by question index
const isSubmitting = ref(false);
const showSuccessModal = ref(false);
const successModalRef = ref<HTMLElement | null>(null);
const partsPendingCount = ref(0);

const totalQuestions = computed(() =>
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.length ?? 0), 0)
);

// ─── LIVE TIMER LOGIC ───────────────────────────────────────
const timeLeftSeconds = ref(props.exam.duration_minutes * 60);
const timerInterval = ref<ReturnType<typeof setInterval> | null>(null);

const formattedTime = computed(() => {
    const mins = Math.floor(timeLeftSeconds.value / 60);
    const secs = timeLeftSeconds.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const startTimer = () => {
    if (timerInterval.value) return;
    timerInterval.value = setInterval(() => {
        if (timeLeftSeconds.value > 0) {
            timeLeftSeconds.value--;
        } else {
            stopTimer();
            if (examStarted.value && !isSubmitting.value) {
                submitPart(); // Auto-submit on timeout
            }
        }
    }, 1000);
};

const stopTimer = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
};

// ─── PROGRESS NAVIGATOR LOGIC ──────────────────────────────────
const getQuestionStatus = (index: number) => {
    if (answers[index] !== undefined && answers[index] !== '') return 'answered';
    return 'pending';
};

// ─── INTEGRITY & ANTI-CHEATING ───────────────────────────────
const integrityWarnings = ref(0);
const showIntegrityAlert = ref(false);

const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden' && examStarted.value) {
        integrityWarnings.value++;
        showIntegrityAlert.value = true;
        
        // Auto-close alert after 5 seconds
        setTimeout(() => {
            showIntegrityAlert.value = false;
        }, 5000);
    }
};

const preventCheatingActions = (e: Event) => {
    if (examStarted.value) {
        e.preventDefault();
        return false;
    }
};

const submittedPartsCount = computed(() =>
    Object.keys(props.submissions).length
);

const allPartsSubmitted = computed(() =>
    submittedPartsCount.value === props.exam.parts.length && props.exam.parts.length > 0
);

const totalScore = computed(() => 
    Object.values(props.submissions).reduce((sum, s) => sum + (s.score ?? 0), 0)
);

const totalPossiblePoints = computed(() => 
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.reduce((qSum, q) => qSum + (q.points ?? p.points ?? 1), 0) ?? 0), 0)
);

const remainingPartsCount = computed(() =>
    props.exam.parts.length - submittedPartsCount.value
);

const isPartSubmitted = (partId: number) => {
    return !!props.submissions[partId];
};

const formatDateTime = (dateStr: string) => new Date(dateStr).toLocaleString('en-US', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
});

const getPartIcon = (type: string) => ({
    multiple_choice: HelpCircle,
    identification: CheckCircle2,
    essay: FileText,
    true_false: Settings2,
}[type] ?? BookOpen);

const getPartColor = (index: number) => {
    const colors = [
        'from-primary/8 to-primary/2 border-primary/15',
        'from-secondary/60 to-secondary/20 border-border/60',
        'from-primary/12 to-primary/4 border-primary/20',
        'from-muted/80 to-muted/30 border-border/50',
        'from-primary/6 to-transparent border-primary/10',
    ];
    return colors[index % colors.length];
};

const getIconColor = (index: number) => {
    const colors = ['text-primary/70', 'text-foreground/50', 'text-primary/80', 'text-foreground/60', 'text-primary/60'];
    return colors[index % colors.length];
};

const getQuestionTypes = (part: ExamPart) =>
    [...new Set(part.questions?.map(q => q.type) ?? [])];

const formatType = (type: string) => type.replace(/_/g, ' ');

const selectPart = (part: ExamPart) => {
    // Prevent selecting if part is already submitted
    if (isPartSubmitted(part.id)) {
        return;
    }

    selectedPart.value = part;
    startPart(); // Directly start the part now
};

const startPart = () => {
    examStarted.value = true;
    startTimer(); // Start the countdown when the first section begins

    setTimeout(() => {
        gsap.fromTo(
            '.question-card',
            {
                opacity: 0,
                y: 35,
                scale: 0.97,
                rotationX: -4,
                transformOrigin: 'center top'
            },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                rotationX: 0,
                duration: 0.5,
                stagger: 0.08,
                ease: 'power3.out',
                clearProps: 'transform,opacity'
            }
        );
    }, 10);
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    document.addEventListener('contextmenu', preventCheatingActions);
    document.addEventListener('copy', preventCheatingActions);
});

const goBackToList = () => {
    selectedPart.value = null;
    examStarted.value = false;
};

const submitPart = async () => {
    if (!selectedPart.value) return;

    isSubmitting.value = true;

    try {
        // Build detailed answers with question information
        const detailedAnswers = (selectedPart.value.questions || []).map((question, index) => ({
            question_number: index + 1,
            question_text: question.text,
            question_type: question.type,
            answer: (answers[index] !== undefined && answers[index] !== null) ? answers[index] : null,
        }));

        router.post(`/exams/${props.exam.id}/parts/${selectedPart.value.id}/submit`, {
            answers: detailedAnswers,
        }, {
            onSuccess: () => {
                // Show success modal
                showSuccessModal.value = true;
                partsPendingCount.value = remainingPartsCount.value - 1;

                // Animate modal
                setTimeout(() => {
                    if (successModalRef.value) {
                        gsap.fromTo(
                            successModalRef.value,
                            { opacity: 0, scale: 0.85, y: 30 },
                            { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' }
                        );

                        // Bounce animation for checkmark
                        gsap.fromTo(
                            '.success-checkmark',
                            { scale: 0, rotate: -180 },
                            { scale: 1, rotate: 0, duration: 0.8, delay: 0.2, ease: 'elastic.out(1.2, 0.4)' }
                        );
                    }
                }, 10);

                // Auto close after 4 seconds
                setTimeout(() => {
                    closeSuccessModal();
                }, 4000);
            },
        });
    } finally {
        isSubmitting.value = false;
    }
};

const closeSuccessModal = () => {
    if (successModalRef.value) {
        gsap.to(successModalRef.value, {
            opacity: 0,
            scale: 0.85,
            y: 30,
            duration: 0.4,
            ease: 'power2.in',
            onComplete: () => {
                showSuccessModal.value = false;
                // Reset and go back to parts list
                Object.keys(answers).forEach(key => delete answers[key]);
                goBackToList();
            }
        });
    }
};

onMounted(() => {
    // Base state for subtle fade-up
    gsap.set('.animate-up', { opacity: 0, y: 25 });

    const tl = gsap.timeline({
        defaults: { ease: 'power4.out', duration: 1.0 }
    });

    // Hero: main card in first
    tl.to('.exam-hero', { opacity: 1, y: 0, duration: 0.9 });

    // Hero left content
    tl.fromTo(
        '.exam-hero-left',
        { opacity: 0, y: 30 },
        { opacity: 1, y: 0 },
        '-=0.6'
    );

    // Hero stats dashboard
    tl.fromTo(
        '.exam-hero-stats',
        { opacity: 0, scale: 0.9, y: 20 },
        { opacity: 1, scale: 1, y: 0, duration: 0.8 },
        '-=0.7'
    );

    // Individual stat items inside the dashboard
    tl.fromTo(
        '.exam-stat',
        { opacity: 0, x: -10 },
        { opacity: 1, x: 0, stagger: 0.1, duration: 0.5 },
        '-=0.4'
    );

    // Parts list cards
    tl.fromTo(
        '.exam-part-card',
        { opacity: 0, y: 40, scale: 0.98 },
        {
            opacity: 1,
            y: 0,
            scale: 1,
            stagger: 0.1,
            duration: 0.8,
            clearProps: 'transform,opacity'
        },
        '-=0.5'
    );
});
</script>

<template>

    <Head :title="`${exam.title} — Exam`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="container" class="min-h-full flex flex-col gap-0 relative overflow-hidden bg-background">
            <!-- Ambient background decorations -->
            <div
                class="fixed -top-64 -right-64 w-[800px] h-[800px] bg-primary/10 rounded-full blur-[180px] pointer-events-none opacity-50 dark:opacity-40 animate-pulse"
                style="animation-duration: 8s">
            </div>
            <div
                class="fixed top-1/4 -left-64 w-[600px] h-[600px] bg-violet-500/10 rounded-full blur-[160px] pointer-events-none opacity-30 dark:opacity-20 animate-pulse"
                style="animation-duration: 12s">
            </div>
            <div
                class="fixed -bottom-64 right-1/4 w-[700px] h-[700px] bg-blue-500/5 rounded-full blur-[150px] pointer-events-none opacity-20 dark:opacity-10">
            </div>

            <div class="flex-1 flex flex-col p-4 md:p-8 gap-6 relative z-10">

                <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                <div class="animate-up flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button v-if="selectedPart" @click="goBackToList"
                            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group px-3 py-1.5 rounded-lg hover:bg-muted/50">
                            <ChevronLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                            Back to Parts
                        </button>
                        <Link v-else href="/exams"
                            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group px-3 py-1.5 rounded-lg hover:bg-muted/50">
                            <ChevronLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                            All Exams
                        </Link>
                    </div>

                    <!-- Live Floating Timer -->
                    <div v-if="examStarted" 
                        class="flex items-center gap-3 px-4 py-2 rounded-2xl bg-black/40 border border-white/10 backdrop-blur-xl shadow-2xl transition-all duration-500"
                        :class="timeLeftSeconds < 300 ? 'border-red-500/50 text-red-500 animate-pulse' : 'text-primary'">
                        <Clock class="w-4 h-4" />
                        <span class="font-black text-base tracking-widest tabular-nums">{{ formattedTime }}</span>
                    </div>
                </div>

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <div
                    class="animate-up exam-hero relative overflow-hidden rounded-[1.5rem] md:rounded-[2rem] border border-white/10 bg-gradient-to-br from-card/80 via-card/40 to-primary/5 backdrop-blur-3xl p-5 md:px-10 md:py-8 shadow-[0_32px_64px_-16px_rgba(0,0,0,0.5)]">
                    <!-- Dynamic background glow -->
                    <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-primary/15 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <!-- Decorative Large Icon -->
                    <div class="pointer-events-none absolute -right-6 -top-6 opacity-5 md:opacity-10 rotate-12">
                        <GraduationCap class="w-72 h-72 text-primary" />
                    </div>

                    <!-- Inner Glow / Border Highlight -->
                    <div class="absolute inset-0 rounded-[2.5rem] border border-white/5 pointer-events-none"></div>

                    <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-8">
                        <div class="space-y-3 md:space-y-4 flex-1 exam-hero-left">
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="inline-flex items-center gap-2 px-3 md:px-4 py-1 md:py-1.5 rounded-full bg-primary/10 border border-primary/20 text-[9px] md:text-[10px] font-bold text-primary tracking-[0.2em] uppercase backdrop-blur-md">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                    {{ exam.status }}
                                </span>
                            </div>
                            
                            <h1 class="text-2xl md:text-5xl font-black tracking-tight leading-[1.1] premium-gradient-text max-w-3xl">
                                {{ selectedPart ? selectedPart.title : exam.title }}
                            </h1>
                            
                            <p class="text-xs md:text-sm text-muted-foreground leading-relaxed max-w-2xl opacity-80">
                                {{ exam.description || 'Quickly assess and master the material with our streamlined exam interface.' }}
                            </p>
                            
                            <div class="flex flex-wrap items-center gap-4 pt-1 md:pt-0">
                                <div class="flex items-center gap-2 md:gap-2.5 text-[10px] md:text-xs font-medium text-muted-foreground bg-muted/30 px-3 md:px-4 py-1.5 md:py-2 rounded-xl border border-border/40">
                                    <Calendar class="w-3.5 h-3.5 md:w-4 md:h-4 text-primary" />
                                    {{ formatDateTime(exam.exam_date) }}
                                </div>
                            </div>
                        </div>

                        <!-- Stats Dashboard Bar -->
                        <div class="exam-hero-stats grid grid-cols-3 md:flex md:flex-nowrap items-center gap-2 md:gap-4 lg:gap-6 bg-black/20 dark:bg-white/5 backdrop-blur-xl p-4 md:px-6 md:py-5 rounded-2xl md:rounded-[1.5rem] border border-white/5 shadow-inner self-stretch md:self-auto lg:self-center">
                            <div v-if="allPartsSubmitted" class="exam-stat group flex flex-col items-center md:items-start transition-all">
                                <div class="flex items-center gap-2 md:gap-3 mb-0.5 md:mb-1">
                                    <div class="p-1 md:p-1.5 rounded-lg bg-green-500/10 text-green-500">
                                        <Trophy class="w-3.5 h-3.5 md:w-4 md:h-4" />
                                    </div>
                                    <div class="text-lg md:text-2xl font-black text-green-500">{{ totalScore }}/{{ totalPossiblePoints }}</div>
                                </div>
                                <div class="text-[8px] md:text-[9px] text-muted-foreground font-bold uppercase tracking-widest opacity-60">Score</div>
                            </div>
                            
                            <div v-if="allPartsSubmitted" class="hidden md:block w-px h-10 bg-white/5"></div>

                            <div class="exam-stat group flex flex-col items-center md:items-start transition-all">
                                <div class="flex items-center gap-2 md:gap-3 mb-0.5 md:mb-1">
                                    <div class="p-1 md:p-1.5 rounded-lg bg-primary/10 text-primary">
                                        <Clock class="w-3.5 h-3.5 md:w-4 md:h-4" />
                                    </div>
                                    <div class="text-lg md:text-2xl font-black">{{ exam.duration_minutes }}</div>
                                </div>
                                <div class="text-[8px] md:text-[9px] text-muted-foreground font-bold uppercase tracking-widest opacity-60">Mins</div>
                            </div>

                            <div class="hidden md:block w-px h-10 bg-white/5"></div>

                            <div class="exam-stat group flex flex-col items-center md:items-start transition-all">
                                <div class="flex items-center gap-2 md:gap-3 mb-0.5 md:mb-1">
                                    <div class="p-1 md:p-1.5 rounded-lg bg-primary/10 text-primary">
                                        <Layers class="w-3.5 h-3.5 md:w-4 md:h-4" />
                                    </div>
                                    <div class="text-lg md:text-2xl font-black">{{ exam.parts.length }}</div>
                                </div>
                                <div class="text-[8px] md:text-[9px] text-muted-foreground font-bold uppercase tracking-widest opacity-60">Secs</div>
                            </div>

                            <div class="hidden md:block w-px h-10 bg-white/5"></div>

                            <div class="exam-stat group flex flex-col items-center md:items-start transition-all">
                                <div class="flex items-center gap-2 md:gap-3 mb-0.5 md:mb-1">
                                    <div class="p-1 md:p-1.5 rounded-lg bg-primary/10 text-primary">
                                        <ListChecks class="w-3.5 h-3.5 md:w-4 md:h-4" />
                                    </div>
                                    <div class="text-lg md:text-2xl font-black">{{ totalQuestions }}</div>
                                </div>
                                <div class="text-[8px] md:text-[9px] text-muted-foreground font-bold uppercase tracking-widest opacity-60">Ques</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PARTS LIST STATE                                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-if="!selectedPart">
                    <div class="animate-up flex items-center justify-between">
                        <h2 class="text-lg font-bold flex items-center gap-2">
                            <Layers class="w-5 h-5 text-primary" />
                            Exam Parts
                        </h2>
                        <span
                            class="text-xs text-muted-foreground bg-muted/50 px-2.5 py-1 rounded-full border border-border/50">
                            {{ exam.parts.length }} Section{{ exam.parts.length !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(part, index) in exam.parts" :key="part.id" @click="selectPart(part)"
                            class="exam-part-card animate-up surface-card premium-hover group h-full p-6 md:p-8"
                            :class="[
                                isPartSubmitted(part.id) 
                                    ? 'opacity-60 cursor-not-allowed grayscale-[0.5]' 
                                    : 'cursor-pointer hover:border-primary/40',
                                getPartColor(index)
                            ]">
                            
                            <!-- Light Sweep Animation -->
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>

                            <!-- Silhouette background icon -->
                            <div
                                class="pointer-events-none absolute -right-6 -bottom-6 opacity-5 group-hover:opacity-15 group-hover:scale-110 transition-all duration-700">
                                <component :is="getPartIcon(part.type)" class="w-40 h-40 text-foreground" />
                            </div>

                            <div class="relative flex flex-col gap-6 h-full">
                                <!-- Top: Part Label & Status -->
                                <div class="flex items-center justify-between">
                                    <div class="px-3 py-1 rounded-lg bg-primary/5 text-[10px] font-black text-primary/60 uppercase tracking-[0.2em] border border-primary/10">
                                        Part {{ index + 1 }}
                                    </div>
                                    <div v-if="isPartSubmitted(part.id)" class="flex flex-col items-end gap-1">
                                        <div class="flex items-center gap-1.5 text-green-500">
                                            <CheckSquare2 class="w-4 h-4" />
                                            <span class="text-[10px] font-black uppercase tracking-widest">COMPLETED</span>
                                        </div>
                                        <div class="text-[10px] font-bold text-muted-foreground/80">
                                            Score: <span class="text-foreground">{{ submissions[part.id]?.score ?? 0 }}</span> / {{ part.questions?.reduce((sum, q) => sum + (q.points ?? part.points ?? 1), 0) ?? 0 }}
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5 text-muted-foreground/40 group-hover:text-primary/60 transition-colors">
                                        <Lock v-if="false" class="w-3.5 h-3.5" />
                                        <span class="text-[10px] font-bold uppercase tracking-widest">READY</span>
                                    </div>
                                </div>

                                <!-- Center: Title & Types -->
                                <div class="flex-1 space-y-3">
                                    <h3
                                        class="text-xl md:text-2xl font-black leading-tight transition-colors"
                                        :class="isPartSubmitted(part.id) ? 'text-muted-foreground' : 'text-foreground group-hover:text-primary'">
                                        {{ part.title }}
                                    </h3>
                                    
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span v-for="type in getQuestionTypes(part)" :key="type"
                                            class="px-3 py-1 rounded-lg bg-muted text-[10px] font-bold text-muted-foreground/80 uppercase tracking-wider border border-border/40">
                                            {{ formatType(type).toUpperCase() }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Bottom: Footer Info & Action -->
                                <div class="flex items-center justify-between pt-4 border-t border-border/10">
                                    <div class="flex items-center gap-1.5 text-sm text-muted-foreground font-medium">
                                        <span class="text-foreground/80 font-black">{{ part.questions?.length ?? 0 }}</span>
                                        <span class="opacity-60">Tasks</span>
                                    </div>
                                    
                                    <div v-if="!isPartSubmitted(part.id)"
                                        class="flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-primary-foreground font-bold text-xs shadow-lg shadow-primary/20 hover:scale-[1.05] active:scale-[0.95] transition-all">
                                        START
                                        <ArrowRight class="w-3.5 h-3.5" />
                                    </div>
                                    <div v-else
                                        class="w-10 h-10 rounded-full flex items-center justify-center bg-muted border border-border/60">
                                        <CheckCircle2 class="w-5 h-5 text-green-500" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions footer -->
                    <div
                        class="animate-up mt-2 rounded-xl border border-border/30 bg-muted/20 p-4 flex items-start gap-3">
                        <ListChecks class="w-5 h-5 text-muted-foreground/60 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-muted-foreground/70 leading-relaxed">
                            Click <strong>START</strong> on a section to begin. Each part may contain multiple question types. Read the instructions
                            carefully before starting each section.
                        </p>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  QUESTIONS STATE (after start)                          -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else>
                    <div class="flex flex-col lg:flex-row lg:items-start gap-8">
                        <!-- Main Question List -->
                        <div class="flex-1 space-y-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-bold flex items-center gap-2">
                                    <Layers class="w-4 h-4 text-primary" />
                                    {{ selectedPart!.title }}
                                </h2>
                                <span
                                    class="text-[10px] font-black text-muted-foreground bg-muted/30 px-3 py-1 rounded-lg border border-border/40 uppercase tracking-widest">
                                    {{ selectedPart!.questions?.length ?? 0 }} Tasks
                                </span>
                            </div>

                            <div class="flex flex-col gap-3">
                                <div v-for="(question, qIndex) in selectedPart!.questions" :key="qIndex"
                                    :id="`q-${qIndex}`"
                                    class="question-card rounded-xl border border-border/40 bg-card/10 backdrop-blur-md p-4 md:p-5 flex flex-col md:flex-row gap-4 md:items-center transition-all"
                                    :class="getQuestionStatus(qIndex) === 'answered' ? 'border-primary/20 bg-primary/2' : ''">
                                    
                                    <!-- Question identifier -->
                                    <div class="flex items-center gap-3 flex-shrink-0">
                                        <div
                                            class="w-10 h-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-base font-black text-primary">
                                            {{ qIndex + 1 }}
                                        </div>
                                    </div>

                                    <!-- Question text -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="text-[9px] px-2 py-0.5 rounded-md bg-primary/5 text-primary/60 uppercase font-black tracking-[0.1em] border border-primary/10">{{
                                                formatType(question.type) }}</span>
                                            <span class="text-[9px] px-2 py-0.5 rounded-md bg-muted text-muted-foreground/80 uppercase font-black tracking-[0.1em] border border-border/40">
                                                {{ question.points ?? selectedPart!.points ?? 1 }} Pts
                                            </span>
                                        </div>
                                        <p class="text-base font-bold leading-tight text-foreground/90 truncate md:whitespace-normal">{{ question.text }}</p>
                                    </div>

                                    <!-- Answer Area -->
                                    <div class="flex-shrink-0 w-full md:w-auto md:min-w-[300px]">
                                        <!-- Multiple Choice / True-False -->
                                        <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'"
                                            class="flex flex-wrap gap-2">
                                            <label v-for="(option, oIndex) in question.options" :key="option.text"
                                                class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border/50 bg-muted/10 hover:border-primary/40 hover:bg-primary/5 cursor-pointer transition-all has-[:checked]:border-primary/60 has-[:checked]:bg-primary/10">
                                                <input type="radio" :name="`q-${qIndex}`" :value="oIndex"
                                                    v-model.number="answers[qIndex]" class="sr-only" />
                                                <span class="text-xs font-bold">{{ option.text }}</span>
                                            </label>
                                        </div>

                                        <!-- Identification -->
                                        <div v-else-if="question.type === 'identification'">
                                            <input v-model="answers[qIndex]" type="text" placeholder="Type answer..."
                                                class="w-full px-4 py-2 rounded-lg border border-border/40 bg-muted/20 focus:ring-2 focus:ring-primary/10 focus:border-primary/50 outline-none transition-all text-sm font-medium" />
                                        </div>

                                        <!-- Essay -->
                                        <div v-else-if="question.type === 'essay'">
                                            <textarea v-model="answers[qIndex]" rows="2" placeholder="Write response..."
                                                class="w-full px-4 py-2 rounded-lg border border-border/40 bg-muted/20 focus:ring-2 focus:ring-primary/10 focus:border-primary/50 outline-none transition-all text-sm font-medium resize-none"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Navigator (Mini-Map) -->
                        <div class="hidden lg:block sticky top-8 w-64 space-y-4">
                            <div class="p-5 rounded-2xl border border-white/5 bg-card/30 backdrop-blur-2xl shadow-xl">
                                <h3 class="text-xs font-black text-muted-foreground uppercase tracking-widest mb-4">Navigator</h3>
                                <div class="grid grid-cols-4 gap-2">
                                    <a v-for="(_, qIndex) in selectedPart!.questions" :key="qIndex"
                                        :href="`#q-${qIndex}`"
                                        class="aspect-square rounded-lg flex items-center justify-center text-xs font-black transition-all border border-border/40"
                                        :class="[
                                            getQuestionStatus(qIndex) === 'answered'
                                                ? 'bg-primary text-primary-foreground border-primary'
                                                : 'bg-muted/30 text-muted-foreground hover:border-primary/50'
                                        ]">
                                        {{ qIndex + 1 }}
                                    </a>
                                </div>
                                <div class="mt-6 pt-4 border-t border-border/10 space-y-2">
                                    <div class="flex items-center justify-between text-[10px] font-bold">
                                        <span class="text-muted-foreground uppercase opacity-60">Completion</span>
                                        <span>{{ Math.round((Object.keys(answers).length / selectedPart!.questions!.length) * 100) }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                                        <div class="h-full bg-primary transition-all duration-500" 
                                            :style="{ width: `${(Object.keys(answers).length / selectedPart!.questions!.length) * 100}%` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Bottom Bar Navigator (Small) -->
                    <div class="lg:hidden fixed bottom-0 left-0 right-0 p-4 z-40">
                         <div class="bg-black/80 backdrop-blur-2xl rounded-2xl border border-white/10 p-3 shadow-2xl flex items-center gap-3 overflow-x-auto no-scrollbar">
                            <div class="text-[9px] font-black uppercase text-muted-foreground vertical-writing rotate-180 mr-1">NAV</div>
                            <div v-for="(_, qIndex) in selectedPart!.questions" :key="qIndex"
                                class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black border border-white/5 transition-all"
                                :class="getQuestionStatus(qIndex) === 'answered' ? 'bg-primary text-primary-foreground' : 'bg-white/5 text-white/40'">
                                {{ qIndex + 1 }}
                            </div>
                         </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-6 flex justify-end pt-4">
                        <button @click="submitPart" :disabled="isSubmitting"
                            class="group px-12 py-5 rounded-[2rem] bg-primary text-primary-foreground font-black shadow-[0_20px_40px_-10px_rgba(0,0,0,0.4)] hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="text-base tracking-wide">{{ isSubmitting ? 'SECURELY SUBMITTING...' : 'FINISH SECTION' }}</span>
                            <div class="p-1 rounded-full bg-primary-foreground/10 group-hover:bg-primary-foreground/20 transition-colors">
                                <ArrowRight v-if="!isSubmitting" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                                <div v-else class="w-5 h-5 border-2 border-primary-foreground/20 border-t-primary-foreground rounded-full animate-spin"></div>
                            </div>
                        </button>
                    </div>
                </template>

            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!--  SUCCESS MODAL OVERLAY                                  -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <transition name="modal-fade">
                <div v-if="showSuccessModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div ref="successModalRef" 
                        class="relative max-w-md w-full rounded-3xl border border-border/40 bg-gradient-to-br from-card via-card/95 to-primary/5 backdrop-blur-2xl p-8 shadow-2xl overflow-hidden">
                        
                        <!-- Decorative background elements -->
                        <div class="absolute -top-20 -right-20 w-40 h-40 bg-primary/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
                        
                        <!-- Content -->
                        <div class="relative z-10 flex flex-col items-center gap-6 text-center">
                            <!-- Success Checkmark -->
                            <div class="success-checkmark relative w-20 h-20 flex items-center justify-center">
                                <div class="absolute inset-0 rounded-full bg-gradient-to-br from-primary/20 to-primary/5 dark:from-primary/30 dark:to-primary/10 animate-pulse"></div>
                                <div class="absolute inset-0 rounded-full border-2 border-primary/30 dark:border-primary/50"></div>
                                <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>

                            <!-- Message -->
                            <div class="space-y-2">
                                <h3 class="text-2xl font-bold text-foreground">Part Submitted</h3>
                                <p class="text-muted-foreground text-sm leading-relaxed">
                                    Great job! Your answers have been saved successfully.
                                </p>
                            </div>

                            <!-- Progress Info -->
                            <div v-if="partsPendingCount > 0" class="w-full pt-4 border-t border-border/30">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-primary/80 dark:bg-primary animate-pulse"></div>
                                    <span class="text-sm font-semibold text-primary/80 dark:text-primary">
                                        {{ partsPendingCount }} {{ partsPendingCount === 1 ? 'part' : 'parts' }} remaining
                                    </span>
                                </div>
                            </div>

                            <!-- All Complete Message -->
                            <div v-else class="w-full pt-4 border-t border-border/30">
                                <div class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-primary/5 dark:bg-primary/10 border border-primary/20 dark:border-primary/40">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span class="text-sm font-semibold text-primary">Exam Complete!</span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <button @click="closeSuccessModal"
                                class="w-full mt-4 px-6 py-4 rounded-2xl bg-primary text-primary-foreground font-black hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-3 shadow-lg shadow-primary/20">
                                <span class="tracking-widest uppercase">{{ partsPendingCount > 0 ? 'Next Section' : 'Return to Home' }}</span>
                                <ChevronRight class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../css/app.css";

.pl-13 {
    padding-left: 3.25rem;
}

.animate-up {
    will-change: transform, opacity;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-to,
.modal-fade-leave-from {
    opacity: 1;
}
</style>
