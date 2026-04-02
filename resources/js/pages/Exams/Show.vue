<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onMounted, ref, computed, reactive } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    Calendar, Clock, ChevronLeft, ChevronRight, BookOpen,
    CheckCircle2, HelpCircle, FileText, Settings2, GraduationCap,
    PlayCircle, ArrowRight, Layers, ListChecks, Users2, Trophy, Lock, CheckSquare2,
    Flag, Zap, BarChart3, RotateCcw
} from 'lucide-vue-next';
import { watch } from 'vue';

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
const displayedScore = ref(0); // For GSAP counter animation
const flaggedQuestions = ref<Set<number>>(new Set());
const partStartTime = ref<number | null>(null);
const estimatedFinishMinutes = ref<number | null>(null);
const lastSavedAt = ref<string | null>(null);

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
    partStartTime.value = Date.now();
    timerInterval.value = setInterval(() => {
        if (timeLeftSeconds.value > 0) {
            timeLeftSeconds.value--;
            calculatePace();
        } else {
            stopTimer();
            if (examStarted.value && !isSubmitting.value) {
                submitPart(); // Auto-submit on timeout
            }
        }
    }, 1000);
};

const calculatePace = () => {
    if (!partStartTime.value || !selectedPart.value) return;
    
    const answeredCount = Object.keys(answers).length;
    if (answeredCount === 0) return;

    const elapsedSeconds = (Date.now() - partStartTime.value) / 1000;
    const avgSecondsPerQuestion = elapsedSeconds / answeredCount;
    const remainingQuestions = (selectedPart.value.questions?.length ?? 0) - answeredCount;
    
    if (remainingQuestions > 0) {
        estimatedFinishMinutes.value = Math.ceil((remainingQuestions * avgSecondsPerQuestion) / 60);
    } else {
        estimatedFinishMinutes.value = 0;
    }
};

const stopTimer = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
    }
};

// ─── PROGRESS NAVIGATOR LOGIC ──────────────────────────────────
const getQuestionStatus = (index: number) => {
    if (flaggedQuestions.value.has(index)) return 'flagged';
    if (answers[index] !== undefined && answers[index] !== '' && answers[index] !== null) return 'answered';
    return 'pending';
};

const toggleFlag = (index: number) => {
    if (flaggedQuestions.value.has(index)) {
        flaggedQuestions.value.delete(index);
    } else {
        flaggedQuestions.value.add(index);
    }
    saveDraft();
};

// ─── AUTO-SAVE LOGIC ───────────────────────────────────────
const DRAFT_KEY_PREFIX = 'exam_draft_';

const getDraftKey = () => `${DRAFT_KEY_PREFIX}${props.exam.id}_${selectedPart.value?.id}`;

const saveDraft = () => {
    if (!selectedPart.value) return;
    const draft = {
        answers: { ...answers },
        flagged: Array.from(flaggedQuestions.value),
        timeLeft: timeLeftSeconds.value,
        timestamp: Date.now()
    };
    localStorage.setItem(getDraftKey(), JSON.stringify(draft));
    lastSavedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const loadDraft = () => {
    if (!selectedPart.value) return;
    const saved = localStorage.getItem(getDraftKey());
    if (saved) {
        try {
            const draft = JSON.parse(saved);
            // Only load if recent (e.g., within the last 2 hours)
            if (Date.now() - draft.timestamp < 2 * 60 * 60 * 1000) {
                Object.assign(answers, draft.answers);
                flaggedQuestions.value = new Set(draft.flagged || []);
                // If the saved time is significantly different, we could sync it, 
                // but usually the server-side timer is authority. 
                // For now, we trust the props timer unless it's a refresh.
            }
        } catch (e) {
            console.error('Failed to load draft', e);
        }
    }
};

const clearDraft = () => {
    localStorage.removeItem(getDraftKey());
};

watch(answers, () => {
    saveDraft();
}, { deep: true });

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

const isExamPendingReview = computed(() => 
    Object.values(props.submissions).some(s => s.status === 'pending_review')
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

const isPartLocked = (index: number) => {
    if (index === 0) return false;
    const previousPart = props.exam.parts[index - 1];
    return !isPartSubmitted(previousPart.id);
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

const selectPart = (part: ExamPart, index: number) => {
    // Prevent selecting if exam is closed, part is already submitted, or part is locked
    if (props.exam.status === 'closed' || isPartSubmitted(part.id) || isPartLocked(index)) {
        return;
    }

    selectedPart.value = part;
    startPart(); // Directly start the part now
};

const startPart = () => {
    // Reset state for the new part
    Object.keys(answers).forEach(key => delete answers[Number(key)]);
    flaggedQuestions.value.clear();
    estimatedFinishMinutes.value = null;
    lastSavedAt.value = null;

    examStarted.value = true;
    startTimer(); // Start the countdown when the first section begins
    loadDraft(); // Load any saved progress

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
    document.addEventListener('keydown', handleGlobalKeydown);
});

const handleGlobalKeydown = (e: KeyboardEvent) => {
    if (!examStarted.value || isSubmitting.value) return;

    const activeElem = document.activeElement;
    const isInput = activeElem?.tagName === 'INPUT' || activeElem?.tagName === 'TEXTAREA';

    // Numbers 1-9 for picking MCQ options
    if (!isInput && /^[1-9]$/.test(e.key)) {
        const cards = document.querySelectorAll('.question-card');
        const middle = window.innerHeight / 2;
        let bestCard = null;
        let minDistance = Infinity;

        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const distance = Math.abs(rect.top + rect.height / 2 - middle);
            if (distance < minDistance) {
                minDistance = distance;
                bestCard = card;
            }
        });
        
        if (bestCard) {
            const idParts = (bestCard as HTMLElement).id.split('-');
            const qIndex = parseInt(idParts[1]);
            const optionIndex = parseInt(e.key) - 1;
            const question = selectedPart.value?.questions?.[qIndex];
            if (question && (question.type === 'multiple_choice' || question.type === 'true_false')) {
                if (question.options && optionIndex < question.options.length) {
                    answers[qIndex] = optionIndex;
                }
            }
        }
    }

    // 'F' for Flagging
    if (!isInput && e.key.toLowerCase() === 'f') {
        const cards = document.querySelectorAll('.question-card');
        const middle = window.innerHeight / 2;
        let bestCard = null;
        let minDistance = Infinity;

        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const distance = Math.abs(rect.top + rect.height / 2 - middle);
            if (distance < minDistance) {
                minDistance = distance;
                bestCard = card;
            }
        });

        if (bestCard) {
            const idParts = (bestCard as HTMLElement).id.split('-');
            const qIndex = parseInt(idParts[1]);
            toggleFlag(qIndex);
        }
    }
};

const goBackToList = () => {
    selectedPart.value = null;
    examStarted.value = false;
};

const submitPart = async () => {
    if (!selectedPart.value) return;

    // Smart Review Nudge
    const unansweredCount = (selectedPart.value.questions?.length ?? 0) - Object.keys(answers).length;
    if (unansweredCount > 0 && !isSubmitting.value) {
        const confirmResult = confirm(`You have ${unansweredCount} unanswered questions in this section. Are you sure you want to proceed to the next part?`);
        if (!confirmResult) return;
    }

    isSubmitting.value = true;

    try {
        // Build detailed answers with question information
        const detailedAnswers = (selectedPart.value?.questions || []).map((question, index) => ({
            question_number: index + 1,
            question_text: question.text,
            question_type: question.type,
            points: question.points ?? selectedPart.value?.points ?? 1,
            answer: (answers[index] !== undefined && answers[index] !== null) ? answers[index] : null,
        }));

        router.post(`/exams/${props.exam.id}/parts/${selectedPart.value.id}/submit`, {
            answers: detailedAnswers,
        }, {
            onSuccess: () => {
                clearDraft(); // Clean up successfully submitted draft
                // Show success modal
                showSuccessModal.value = true;
                partsPendingCount.value = remainingPartsCount.value;

                // Animate modal
                setTimeout(() => {
                    if (successModalRef.value) {
                        gsap.fromTo(
                            successModalRef.value,
                            { opacity: 0, scale: 0.85, y: 30 },
                            { opacity: 1, scale: 1, y: 0, duration: 0.6, ease: 'back.out' }
                        );

                        // If all parts are done, animate the total score
                        if (partsPendingCount.value === 0) {
                            displayedScore.value = 0;
                            gsap.to(displayedScore, {
                                value: totalScore.value,
                                duration: 2,
                                ease: 'power4.out',
                                delay: 0.6,
                                onUpdate: () => {
                                    displayedScore.value = Math.floor(displayedScore.value);
                                }
                            });

                            // Decorative pop for the score box
                            gsap.fromTo('.final-score-box',
                                { scale: 0.8, opacity: 0, y: 20 },
                                { scale: 1, opacity: 1, y: 0, duration: 1.2, delay: 0.5, ease: 'elastic.out(1, 0.5)' }
                            );
                        }

                        // Bounce animation for checkmark
                        gsap.fromTo(
                            '.success-checkmark',
                            { scale: 0, rotate: -180 },
                            { scale: 1, rotate: 0, duration: 0.8, delay: 0.2, ease: 'elastic.out(1.2, 0.4)' }
                        );
                    }
                }, 10);

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
                Object.keys(answers).forEach(key => delete answers[Number(key)]);
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

                    <!-- Live Floating Timer & Smart Stats -->
                    <div v-if="examStarted" 
                        class="flex items-center gap-3 md:gap-5 px-4 py-2 rounded-2xl bg-black/40 border border-white/10 backdrop-blur-xl shadow-2xl transition-all duration-500">
                        
                        <!-- Draft Status (Desktop Only) -->
                        <div v-if="lastSavedAt" class="hidden md:flex items-center gap-1.5 text-[9px] font-black text-muted-foreground/60 uppercase tracking-widest border-r border-white/10 pr-4">
                            <RotateCcw class="w-3 h-3" />
                            Draft Saved {{ lastSavedAt }}
                        </div>

                        <!-- Pace Indicator -->
                        <div v-if="estimatedFinishMinutes !== null && estimatedFinishMinutes > 0" 
                            class="flex items-center gap-2 text-amber-400 font-bold text-xs uppercase tracking-wider">
                            <Zap class="w-3.5 h-3.5 fill-amber-400/20" />
                            <span class="hidden md:inline">Finish In</span> ~{{ estimatedFinishMinutes }}m
                        </div>

                        <div class="flex items-center gap-3" :class="timeLeftSeconds < 300 ? 'border-red-500/50 text-red-500 animate-pulse' : 'text-primary'">
                            <Clock class="w-4 h-4" />
                            <span class="font-black text-base tracking-widest tabular-nums">{{ formattedTime }}</span>
                        </div>
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
                        <div v-for="(part, index) in exam.parts" :key="part.id" @click="selectPart(part, index)"
                            class="exam-part-card animate-up surface-card premium-hover group h-full p-6 md:p-8"
                            :class="[
                                (isPartSubmitted(part.id) || exam.status === 'closed' || isPartLocked(index))
                                    ? 'opacity-60 cursor-not-allowed grayscale-[0.5]' 
                                    : 'cursor-pointer hover:border-primary/40',
                                getPartColor(index)
                            ]">
                            
                            <!-- Light Sweep Animation -->
                            <div v-if="!isPartLocked(index) && !isPartSubmitted(part.id)" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>

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
                                    <div v-if="isPartSubmitted(part.id) || exam.status === 'closed' || isPartLocked(index)" class="flex flex-col items-end gap-1">
                                        <div v-if="exam.status === 'closed' && !isPartSubmitted(part.id)" class="flex items-center gap-1.5 text-red-500">
                                            <Lock class="w-4 h-4" />
                                            <span class="text-[10px] font-black uppercase tracking-widest">LOCKED</span>
                                        </div>
                                        <div v-else-if="isPartLocked(index)" class="flex flex-col items-end gap-1 mt-0.5">
                                            <div class="flex items-center gap-1.5 text-muted-foreground/60">
                                                <Lock class="w-3.5 h-3.5" />
                                                <span class="text-[10px] font-black uppercase tracking-widest">LOCKED</span>
                                            </div>
                                            <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-wider opacity-60">
                                                Answer Part {{ index }} to unlock
                                            </span>
                                        </div>
                                        <div v-else-if="submissions[part.id]?.status === 'pending_review'" class="flex items-center gap-1.5 text-amber-500">
                                            <Clock class="w-4 h-4" />
                                            <span class="text-[10px] font-black uppercase tracking-widest">PENDING REVIEW</span>
                                        </div>
                                        <div v-else class="flex items-center gap-1.5 text-green-500">
                                            <CheckSquare2 class="w-4 h-4" />
                                            <span class="text-[10px] font-black uppercase tracking-widest">COMPLETED</span>
                                        </div>
                                        <div v-if="isPartSubmitted(part.id)" class="text-[10px] font-bold text-muted-foreground/80">
                                            Score: <span class="text-foreground">{{ submissions[part.id]?.score ?? 0 }}</span> / {{ part.questions?.reduce((sum, q) => sum + (q.points ?? part.points ?? 1), 0) ?? 0 }}
                                            <span v-if="submissions[part.id]?.status === 'pending_review'" class="text-[9px] opacity-70 ml-1">(+ Essay)</span>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-1.5 text-muted-foreground/40 group-hover:text-primary/60 transition-colors">
                                        <span class="text-[10px] font-bold uppercase tracking-widest">READY</span>
                                    </div>
                                </div>

                                <!-- Center: Title & Types -->
                                <div class="flex-1 space-y-3">
                                    <h3
                                        class="text-xl md:text-2xl font-black leading-tight transition-colors"
                                        :class="isPartSubmitted(part.id) || isPartLocked(index) ? 'text-muted-foreground' : 'text-foreground group-hover:text-primary'">
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
                                        class="flex items-center gap-2 px-4 py-2 rounded-xl transition-all"
                                        :class="isPartLocked(index) 
                                            ? 'bg-muted/40 text-muted-foreground/40 cursor-not-allowed border border-border/20' 
                                            : 'bg-primary text-primary-foreground font-bold text-xs shadow-lg shadow-primary/20 hover:scale-[1.05] active:scale-[0.95]'">
                                        <span class="text-xs font-bold">{{ isPartLocked(index) ? 'LOCKED' : 'START' }}</span>
                                        <Lock v-if="isPartLocked(index)" class="w-3.5 h-3.5" />
                                        <ArrowRight v-else class="w-3.5 h-3.5" />
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
                                        <!-- Smart Flag Button -->
                                        <button @click="toggleFlag(qIndex)"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center transition-all bg-muted border border-border/40 hover:bg-amber-500/10 hover:border-amber-500/50 group/flag"
                                            :class="flaggedQuestions.has(qIndex) ? 'bg-amber-500/20 border-amber-500/60 text-amber-500' : 'text-muted-foreground/40'">
                                            <Flag class="w-4 h-4" :class="flaggedQuestions.has(qIndex) ? 'fill-amber-500' : ''" />
                                        </button>
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
                                        class="aspect-square rounded-lg flex items-center justify-center text-xs font-black transition-all border border-border/40 relative shadow-sm"
                                        :class="[
                                            getQuestionStatus(qIndex) === 'answered'
                                                ? 'bg-primary text-primary-foreground border-primary shadow-primary/20'
                                                : getQuestionStatus(qIndex) === 'flagged'
                                                ? 'bg-amber-500 text-white border-amber-500 shadow-amber-500/20'
                                                : 'bg-muted/30 text-muted-foreground hover:border-primary/50'
                                        ]">
                                        {{ qIndex + 1 }}
                                        <!-- Flag indicator bubble -->
                                        <div v-if="flaggedQuestions.has(qIndex)" 
                                            class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-red-500 border border-white dark:border-black shadow-sm"></div>
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
                                class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black border border-white/5 transition-all relative"
                                :class="[
                                    getQuestionStatus(qIndex) === 'answered' ? 'bg-primary text-primary-foreground' : 
                                    getQuestionStatus(qIndex) === 'flagged' ? 'bg-amber-500 text-white' : 
                                    'bg-white/5 text-white/40'
                                ]">
                                {{ qIndex + 1 }}
                                <div v-if="flaggedQuestions.has(qIndex)" class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-red-500 border border-black shadow-sm"></div>
                            </div>
                         </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-6 flex justify-end pt-4">
                        <button @click="submitPart" :disabled="isSubmitting"
                            class="group px-6 py-3.5 md:px-12 md:py-5 rounded-2xl md:rounded-[2.5rem] bg-primary text-primary-foreground font-black shadow-[0_20px_40px_-10px_rgba(0,0,0,0.4)] hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-2 md:gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="text-sm md:text-base tracking-wide">{{ isSubmitting ? 'SECURELY SUBMITTING...' : 'FINISH SECTION' }}</span>
                            <div class="p-1 rounded-full bg-primary-foreground/10 group-hover:bg-primary-foreground/20 transition-colors">
                                <ArrowRight v-if="!isSubmitting" class="w-4 h-4 md:w-5 md:h-5 group-hover:translate-x-1 transition-transform" />
                                <div v-else class="w-4 h-4 md:w-5 md:h-5 border-2 border-primary-foreground/20 border-t-primary-foreground rounded-full animate-spin"></div>
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

                            <!-- All Complete Message & Score Reveal -->
                            <div v-else class="w-full pt-4 border-t border-border/30 flex flex-col items-center gap-4">
                                <div class="final-score-box flex flex-col items-center p-6 rounded-3xl bg-primary/5 border border-primary/20 shadow-inner w-full">
                                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-primary/60 mb-2">
                                        {{ isExamPendingReview ? 'Initial Exam Score' : 'Final Exam Score' }}
                                    </span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-6xl font-black text-primary tabular-nums tracking-tighter">{{ displayedScore }}</span>
                                        <span class="text-xl font-bold text-primary/40">/ {{ totalPossiblePoints }}</span>
                                    </div>
                                    <div class="mt-4 flex flex-col items-center gap-2">
                                        <div v-if="isExamPendingReview" class="flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20">
                                            <Clock class="w-3.5 h-3.5 text-amber-500" />
                                            <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Awaiting Teacher Review</span>
                                        </div>
                                        <div v-else class="flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20">
                                            <Trophy class="w-3.5 h-3.5 text-primary" />
                                            <span class="text-[10px] font-bold text-primary uppercase tracking-wider">Exam Completed!</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="isExamPendingReview" class="text-[11px] text-muted-foreground/70 font-medium text-center px-4">
                                    Some parts (like essays) need to be reviewed by your teacher. Your final score will be updated soon.
                                </p>
                                <p v-else class="text-[11px] text-muted-foreground/70 font-medium">Your total achievement across all sections</p>
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
