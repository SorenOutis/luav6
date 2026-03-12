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
}

interface ExamPart {
    id: number;
    title: string;
    instructions: string | null;
    type: string;
    questions: Question[] | null;
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
    submissions: Record<number, string>;
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

const submittedPartsCount = computed(() =>
    Object.keys(props.submissions).length
);

const allPartsSubmitted = computed(() =>
    submittedPartsCount.value === props.exam.parts.length && props.exam.parts.length > 0
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
    examStarted.value = false;

    setTimeout(() => {
        gsap.fromTo(
            '.ready-card',
            { opacity: 0, y: 30, scale: 0.95 },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                duration: 0.6,
                ease: 'power3.out',
                clearProps: 'transform,opacity'
            }
        );
    }, 10);
};

const startPart = () => {
    examStarted.value = true;

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
            answer: answers[index] || null,
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

    // Hero stats
    tl.fromTo(
        '.exam-stat',
        { opacity: 0, y: 30, scale: 0.95 },
        { opacity: 1, y: 0, scale: 1, stagger: 0.12, clearProps: 'transform,opacity' },
        '-=0.5'
    );

    // Parts list cards (only visible when not selected)
    tl.fromTo(
        '.exam-part-card',
        { opacity: 0, y: 50, scale: 0.96, rotationX: -6, transformOrigin: 'center top' },
        {
            opacity: 1,
            y: 0,
            scale: 1,
            rotationX: 0,
            stagger: 0.08,
            clearProps: 'transform,opacity'
        },
        '-=0.3'
    );
});
</script>

<template>

    <Head :title="`${exam.title} — Exam`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="container" class="min-h-full flex flex-col gap-0 relative overflow-hidden bg-background">
            <!-- Ambient background decorations -->
            <div
                class="fixed -top-64 -right-64 w-[600px] h-[600px] bg-primary/4 rounded-full blur-[140px] pointer-events-none">
            </div>
            <div
                class="fixed -bottom-64 -left-64 w-[500px] h-[500px] bg-violet-500/4 rounded-full blur-[140px] pointer-events-none">
            </div>

            <div class="flex-1 flex flex-col p-4 md:p-8 gap-6 relative z-10">

                <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                <div class="animate-up">
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

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <div
                    class="animate-up exam-hero relative overflow-hidden rounded-3xl border border-border/40 bg-gradient-to-br from-card via-card/90 to-primary/5 backdrop-blur-2xl p-7 md:p-9 shadow-[0_18px_55px_rgba(0,0,0,0.45)]">
                    <!-- Large decorative icon -->
                    <div class="pointer-events-none absolute -right-10 -top-10 opacity-[0.05] md:opacity-[0.06]">
                        <GraduationCap class="w-64 h-64 text-primary" />
                    </div>
                    <!-- Shimmer stripe -->
                    <div
                        class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-primary/40 to-transparent">
                    </div>

                    <div class="relative flex flex-col md:flex-row md:items-start md:justify-between gap-8">
                        <div class="space-y-4 flex-1 exam-hero-left">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/25 text-[11px] font-bold text-primary tracking-[0.16em] uppercase">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                                    {{ exam.status }}
                                </span>
                            </div>
                            <h1 class="text-3xl md:text-5xl font-black tracking-tight leading-tight">
                                {{ selectedPart ? selectedPart.title : exam.title }}
                            </h1>
                            <p class="text-muted-foreground text-base leading-relaxed max-w-xl">
                                {{ exam.description }}
                            </p>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground pt-1">
                                <Calendar class="w-4 h-4 text-primary" />
                                {{ formatDateTime(exam.exam_date) }}
                            </div>
                        </div>

                        <!-- Stats grid -->
                        <div class="exam-hero-stats grid grid-cols-3 md:grid-cols-1 gap-3 md:min-w-[170px]">
                            <div
                                class="exam-stat px-1 py-1 flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="flex items-center gap-1.5 text-xl md:text-2xl font-black">
                                    <Clock class="w-5 h-5 text-primary" />
                                    {{ exam.duration_minutes }}
                                </div>
                                <div class="text-[10px] text-muted-foreground uppercase tracking-widest">Minutes</div>
                            </div>
                            <div
                                class="exam-stat px-1 py-1 flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="text-xl md:text-2xl font-black">{{ exam.parts.length }}</div>
                                <div class="text-[10px] text-muted-foreground uppercase tracking-widest">Part{{
                                    exam.parts.length !== 1 ? 's' : '' }}</div>
                            </div>
                            <div
                                class="exam-stat px-1 py-1 flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="text-xl md:text-2xl font-black">{{ totalQuestions }}</div>
                                <div class="text-[10px] text-muted-foreground uppercase tracking-widest">Questions</div>
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

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="(part, index) in exam.parts" :key="part.id" @click="selectPart(part)"
                            class="exam-part-card animate-up relative overflow-hidden rounded-3xl border bg-gradient-to-br from-card via-card/80 to-muted group transition-all duration-300 h-full"
                            :class="[
                                isPartSubmitted(part.id) 
                                    ? 'opacity-60 cursor-not-allowed' 
                                    : 'cursor-pointer hover:scale-[1.01] hover:shadow-xl hover:shadow-black/10',
                                getPartColor(index)
                            ]">
                            <!-- Top shimmer on hover -->
                            <div
                                class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>

                            <!-- Silhouette background icon -->
                            <div
                                class="pointer-events-none absolute -right-4 -top-4 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                                <component :is="getPartIcon(part.type)" class="w-24 h-24 text-foreground" />
                            </div>

                            <div class="relative p-5 flex flex-col gap-3 h-full">
                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 justify-between mb-1">
                                        <div
                                            class="text-[10px] font-bold text-muted-foreground/80 uppercase tracking-widest">
                                            Part {{ index + 1 }}</div>
                                        <div v-if="isPartSubmitted(part.id)" class="flex items-center gap-1">
                                            <CheckSquare2 class="w-3.5 h-3.5 text-green-500" />
                                            <span class="text-[9px] font-semibold text-green-600 uppercase tracking-widest">Submitted</span>
                                        </div>
                                    </div>
                                    <h3
                                        class="text-base font-bold truncate transition-colors"
                                        :class="isPartSubmitted(part.id) ? 'text-muted-foreground' : 'text-foreground group-hover:text-primary'">
                                        {{ part.title }}</h3>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span v-for="type in getQuestionTypes(part)" :key="type"
                                            class="px-2 py-0.5 rounded-md bg-muted/80 text-[10px] font-semibold text-muted-foreground capitalize border border-border/50">
                                            {{ formatType(type) }}
                                        </span>
                                        <span class="text-[11px] text-muted-foreground/80">
                                            · {{ part.questions?.length ?? 0 }} question{{ (part.questions?.length ?? 0)
                                            !== 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow or Lock indicator -->
                                <div
                                    class="flex-shrink-0 self-end w-9 h-9 rounded-full flex items-center justify-center transition-all"
                                    :class="isPartSubmitted(part.id)
                                        ? 'bg-muted/50 border border-border/40 opacity-60'
                                        : 'bg-muted/70 border border-border/60 opacity-80 group-hover:opacity-100 group-hover:bg-primary/10 group-hover:border-primary/40'">
                                    <component :is="isPartSubmitted(part.id) ? Lock : ArrowRight"
                                        class="w-4 h-4 transition-colors"
                                        :class="isPartSubmitted(part.id)
                                            ? 'text-muted-foreground/70'
                                            : 'text-muted-foreground group-hover:text-primary group-hover:translate-x-0.5'" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions footer -->
                    <div
                        class="animate-up mt-2 rounded-xl border border-border/30 bg-muted/20 p-4 flex items-start gap-3">
                        <ListChecks class="w-5 h-5 text-muted-foreground/60 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-muted-foreground/70 leading-relaxed">
                            Select a part to begin. Each part may contain multiple question types. Read the instructions
                            carefully before starting each section.
                        </p>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PART DETAIL STATE (before start)                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else-if="!examStarted">
                    <div class="ready-card animate-up flex justify-center">
                        <button @click="startPart"
                            class="w-full max-w-md py-3.5 rounded-xl bg-primary text-primary-foreground font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 active:scale-[0.98] transition-all shadow-lg shadow-primary/20">
                            <PlayCircle class="w-5 h-5" />
                            Start This Part
                        </button>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  QUESTIONS STATE (after start)                          -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">{{ selectedPart!.title }}</h2>
                        <span
                            class="text-xs text-muted-foreground bg-muted/50 px-2.5 py-1 rounded-full border border-border/50">
                            {{ selectedPart!.questions?.length ?? 0 }} Question{{ (selectedPart!.questions?.length ?? 0)
                            !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2 lg:gap-6">
                        <div v-for="(question, qIndex) in selectedPart!.questions" :key="qIndex"
                            class="question-card rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl p-6 space-y-5">
                            <!-- Question header -->
                            <div class="flex items-start gap-4">
                                <div
                                    class="w-9 h-9 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0 text-sm font-bold text-primary">
                                    {{ qIndex + 1 }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span
                                            class="text-[10px] px-2 py-0.5 rounded bg-muted text-muted-foreground/70 capitalize font-medium">{{
                                            formatType(question.type) }}</span>
                                    </div>
                                    <p class="text-base font-semibold leading-relaxed">{{ question.text }}</p>
                                </div>
                            </div>

                            <!-- Multiple Choice / True-False -->
                            <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'"
                                class="grid grid-cols-1 md:grid-cols-2 gap-2 pl-13">
                                <label v-for="(option, oIndex) in question.options" :key="option.text"
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl border border-border/50 hover:border-primary/40 hover:bg-primary/5 cursor-pointer transition-all group has-[:checked]:border-primary/60 has-[:checked]:bg-primary/10">
                                    <div
                                        class="w-5 h-5 rounded-full border-2 border-border group-hover:border-primary/50 has-[:checked]:border-primary flex items-center justify-center flex-shrink-0">
                                        <input type="radio" :name="`q-${qIndex}`" :value="oIndex"
                                            v-model.number="answers[qIndex]" class="sr-only" />
                                        <div
                                            class="w-2.5 h-2.5 rounded-full bg-primary scale-0 has-[:checked]:scale-100 transition-transform">
                                        </div>
                                    </div>
                                    <span class="text-sm">{{ option.text }}</span>
                                </label>
                            </div>

                            <!-- Identification -->
                            <div v-else-if="question.type === 'identification'" class="pl-13">
                                <input v-model="answers[qIndex]" type="text" placeholder="Type your answer here..."
                                    class="w-full px-4 py-3 rounded-xl border border-border/50 bg-muted/30 hover:border-border focus:ring-2 focus:ring-primary/20 focus:border-primary/40 outline-none transition-all text-sm" />
                            </div>

                            <!-- Essay -->
                            <div v-else-if="question.type === 'essay'" class="pl-13">
                                <textarea v-model="answers[qIndex]" rows="5" placeholder="Write your answer here..."
                                    class="w-full px-4 py-3 rounded-xl border border-border/50 bg-muted/30 hover:border-border focus:ring-2 focus:ring-primary/20 focus:border-primary/40 outline-none transition-all text-sm resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-4 flex justify-end">
                        <button @click="submitPart" :disabled="isSubmitting"
                            class="px-10 py-3.5 rounded-2xl bg-primary text-primary-foreground font-bold shadow-2xl shadow-primary/30 hover:opacity-90 active:scale-[0.98] transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ isSubmitting ? 'Submitting...' : 'Submit Part' }}
                            <ChevronRight class="w-4 h-4" />
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
                                class="w-full mt-4 px-6 py-3 rounded-xl bg-primary text-primary-foreground font-semibold hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2">
                                {{ partsPendingCount > 0 ? 'Continue' : 'Back to Exams' }}
                                <ChevronRight class="w-4 h-4" />
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
