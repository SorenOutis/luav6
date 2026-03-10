<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import {
    Calendar, Clock, ChevronLeft, ChevronRight, BookOpen,
    CheckCircle2, HelpCircle, FileText, Settings2, GraduationCap,
    PlayCircle, ArrowRight, Layers, ListChecks, Users2, Trophy
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

const props = defineProps<{ exam: Exam }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Exams', href: '/exams' },
    { title: props.exam.title, href: `/exams/${props.exam.id}` },
];

const selectedPart = ref<ExamPart | null>(null);
const examStarted = ref(false);
const container = ref<HTMLElement | null>(null);

const totalQuestions = computed(() =>
    props.exam.parts.reduce((sum, p) => sum + (p.questions?.length ?? 0), 0)
);

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
    selectedPart.value = part;
    examStarted.value = false;
    setTimeout(() => {
        gsap.fromTo('.part-detail', { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.4, ease: 'power3.out' });
    }, 10);
};

const startPart = () => {
    examStarted.value = true;
    setTimeout(() => {
        gsap.fromTo('.question-card', { opacity: 0, y: 20 }, { opacity: 1, y: 0, stagger: 0.08, duration: 0.45, ease: 'power3.out' });
    }, 10);
};

const goBackToList = () => {
    selectedPart.value = null;
    examStarted.value = false;
};

onMounted(() => {
    gsap.set('.animate-up', { opacity: 0, y: 25 });
    gsap.to('.animate-up', { opacity: 1, y: 0, stagger: 0.08, duration: 1, ease: 'power4.out' });
});
</script>

<template>
    <Head :title="`${exam.title} — Exam`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="container" class="min-h-full flex flex-col gap-0 relative overflow-hidden bg-background">
            <!-- Ambient background decorations -->
            <div class="fixed -top-64 -right-64 w-[600px] h-[600px] bg-primary/4 rounded-full blur-[140px] pointer-events-none"></div>
            <div class="fixed -bottom-64 -left-64 w-[500px] h-[500px] bg-violet-500/4 rounded-full blur-[140px] pointer-events-none"></div>

            <div class="flex-1 flex flex-col p-4 md:p-8 gap-6 relative z-10">

                <!-- ─── BREADCRUMB NAV ─────────────────────────────────── -->
                <div class="animate-up">
                    <button
                        v-if="selectedPart"
                        @click="goBackToList"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group px-3 py-1.5 rounded-lg hover:bg-muted/50"
                    >
                        <ChevronLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                        Back to Parts
                    </button>
                    <Link
                        v-else
                        href="/exams"
                        class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors group px-3 py-1.5 rounded-lg hover:bg-muted/50"
                    >
                        <ChevronLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                        All Exams
                    </Link>
                </div>

                <!-- ─── HERO BANNER ─────────────────────────────────────── -->
                <div class="animate-up relative overflow-hidden rounded-2xl border border-border/40 bg-gradient-to-br from-card/80 via-card/60 to-primary/5 backdrop-blur-xl p-8 md:p-10">
                    <!-- Large decorative icon -->
                    <div class="absolute -right-6 -top-6 opacity-[0.04]">
                        <GraduationCap class="w-56 h-56" />
                    </div>
                    <!-- Shimmer stripe -->
                    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-primary/40 to-transparent"></div>

                    <div class="relative flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                        <div class="space-y-3 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-3 py-1 rounded-full bg-primary/15 border border-primary/25 text-[11px] font-bold text-primary tracking-widest uppercase">
                                    {{ exam.status }}
                                </span>
                            </div>
                            <h1 class="text-3xl md:text-5xl font-black tracking-tight">{{ exam.title }}</h1>
                            <p class="text-muted-foreground text-base leading-relaxed max-w-xl">{{ exam.description }}</p>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground pt-1">
                                <Calendar class="w-4 h-4 text-primary" />
                                {{ formatDateTime(exam.exam_date) }}
                            </div>
                        </div>

                        <!-- Stats grid -->
                        <div class="grid grid-cols-3 md:grid-cols-1 gap-3 md:min-w-[140px]">
                            <div class="flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="flex items-center gap-1.5 text-2xl font-black">
                                    <Clock class="w-5 h-5 text-primary" />
                                    {{ exam.duration_minutes }}
                                </div>
                                <div class="text-[10px] text-muted-foreground uppercase tracking-widest">Minutes</div>
                            </div>
                            <div class="flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="text-2xl font-black">{{ exam.parts.length }}</div>
                                <div class="text-[10px] text-muted-foreground uppercase tracking-widest">Part{{ exam.parts.length !== 1 ? 's' : '' }}</div>
                            </div>
                            <div class="flex flex-col items-center md:items-end text-center md:text-right">
                                <div class="text-2xl font-black">{{ totalQuestions }}</div>
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
                        <span class="text-xs text-muted-foreground bg-muted/50 px-2.5 py-1 rounded-full border border-border/50">
                            {{ exam.parts.length }} Section{{ exam.parts.length !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="(part, index) in exam.parts"
                            :key="part.id"
                            @click="selectPart(part)"
                            class="animate-up relative overflow-hidden rounded-2xl border bg-gradient-to-br cursor-pointer group transition-all duration-300 hover:scale-[1.01] hover:shadow-xl hover:shadow-black/20"
                            :class="getPartColor(index)"
                        >
                            <!-- Top shimmer on hover -->
                            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>

                            <div class="p-5 flex items-center gap-5">
                                <!-- Icon box -->
                                <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-black/20 border border-white/5 flex items-center justify-center">
                                    <component :is="getPartIcon(part.type)" class="w-7 h-7 transition-colors" :class="getIconColor(index)" />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="text-[10px] font-bold text-white/40 uppercase tracking-widest mb-1">Part {{ index + 1 }}</div>
                                    <h3 class="text-base font-bold truncate group-hover:text-white transition-colors">{{ part.title }}</h3>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span
                                            v-for="type in getQuestionTypes(part)"
                                            :key="type"
                                            class="px-2 py-0.5 rounded-md bg-black/20 text-[10px] font-semibold text-white/60 capitalize border border-white/5"
                                        >
                                            {{ formatType(type) }}
                                        </span>
                                        <span class="text-[11px] text-white/40">
                                            · {{ part.questions?.length ?? 0 }} question{{ (part.questions?.length ?? 0) !== 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Arrow indicator -->
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-black/20 border border-white/5 flex items-center justify-center opacity-60 group-hover:opacity-100 group-hover:bg-white/10 transition-all">
                                    <ArrowRight class="w-4 h-4 text-white group-hover:translate-x-0.5 transition-transform" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions footer -->
                    <div class="animate-up mt-2 rounded-xl border border-border/30 bg-muted/20 p-4 flex items-start gap-3">
                        <ListChecks class="w-5 h-5 text-muted-foreground/60 flex-shrink-0 mt-0.5" />
                        <p class="text-sm text-muted-foreground/70 leading-relaxed">
                            Select a part to begin. Each part may contain multiple question types. Read the instructions carefully before starting each section.
                        </p>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  PART DETAIL STATE (before start)                       -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else-if="!examStarted">
                    <div class="part-detail grid md:grid-cols-3 gap-6">
                        <!-- Left: Part info -->
                        <div class="md:col-span-2 space-y-4">
                            <div class="rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl p-7 space-y-5">
                                <div>
                                    <div class="text-[10px] font-bold text-primary/80 uppercase tracking-widest mb-2">Section Overview</div>
                                    <h2 class="text-2xl font-black">{{ selectedPart.title }}</h2>
                                </div>

                                <div v-if="selectedPart.instructions" class="p-4 rounded-xl bg-muted/40 border border-border/30">
                                    <p class="text-sm text-muted-foreground leading-relaxed italic">"{{ selectedPart.instructions }}"</p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="type in getQuestionTypes(selectedPart)"
                                        :key="type"
                                        class="px-3 py-1.5 rounded-lg bg-primary/10 border border-primary/20 text-xs font-semibold text-primary capitalize"
                                    >
                                        {{ formatType(type) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Question preview list -->
                            <div class="rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl overflow-hidden">
                                <div class="px-6 py-4 border-b border-border/30 flex items-center justify-between">
                                    <span class="text-sm font-semibold">Questions Preview</span>
                                    <span class="text-xs text-muted-foreground">{{ selectedPart.questions?.length ?? 0 }} total</span>
                                </div>
                                <div class="divide-y divide-border/20">
                                    <div
                                        v-for="(q, i) in selectedPart.questions"
                                        :key="i"
                                        class="px-6 py-3.5 flex items-center gap-4"
                                    >
                                        <div class="w-7 h-7 rounded-lg bg-muted/50 flex items-center justify-center text-xs font-bold text-muted-foreground flex-shrink-0">
                                            {{ i + 1 }}
                                        </div>
                                        <p class="text-sm text-muted-foreground flex-1 truncate">{{ q.text }}</p>
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-muted/50 text-muted-foreground/60 capitalize flex-shrink-0">
                                            {{ formatType(q.type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Start card -->
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent p-7 space-y-6 sticky top-6">
                                <div class="w-14 h-14 rounded-2xl bg-primary/20 border border-primary/30 flex items-center justify-center">
                                    <PlayCircle class="w-7 h-7 text-primary" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-lg">Ready to begin?</h3>
                                    <p class="text-sm text-muted-foreground mt-1">Once started, answer every question to the best of your ability.</p>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Questions</span>
                                        <span class="font-semibold">{{ selectedPart.questions?.length ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Part</span>
                                        <span class="font-semibold truncate max-w-[120px] text-right">{{ selectedPart.title }}</span>
                                    </div>
                                </div>
                                <button
                                    @click="startPart"
                                    class="w-full py-3.5 rounded-xl bg-primary text-primary-foreground font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 active:scale-[0.98] transition-all shadow-lg shadow-primary/20"
                                >
                                    <PlayCircle class="w-5 h-5" />
                                    Start This Part
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!--  QUESTIONS STATE (after start)                          -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <template v-else>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">{{ selectedPart!.title }}</h2>
                        <span class="text-xs text-muted-foreground bg-muted/50 px-2.5 py-1 rounded-full border border-border/50">
                            {{ selectedPart!.questions?.length ?? 0 }} Question{{ (selectedPart!.questions?.length ?? 0) !== 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="grid gap-5">
                        <div
                            v-for="(question, qIndex) in selectedPart!.questions"
                            :key="qIndex"
                            class="question-card rounded-2xl border border-border/40 bg-card/60 backdrop-blur-xl p-6 space-y-5"
                        >
                            <!-- Question header -->
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center flex-shrink-0 text-sm font-bold text-primary">
                                    {{ qIndex + 1 }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-[10px] px-2 py-0.5 rounded bg-muted text-muted-foreground/70 capitalize font-medium">{{ formatType(question.type) }}</span>
                                    </div>
                                    <p class="text-base font-semibold leading-relaxed">{{ question.text }}</p>
                                </div>
                            </div>

                            <!-- Multiple Choice / True-False -->
                            <div v-if="question.type === 'multiple_choice' || question.type === 'true_false'" class="grid grid-cols-1 md:grid-cols-2 gap-2 pl-13">
                                <label
                                    v-for="(option, oIndex) in question.options"
                                    :key="option.text"
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl border border-border/50 hover:border-primary/40 hover:bg-primary/5 cursor-pointer transition-all group has-[:checked]:border-primary/60 has-[:checked]:bg-primary/10"
                                >
                                    <div class="w-5 h-5 rounded-full border-2 border-border group-hover:border-primary/50 has-[:checked]:border-primary flex items-center justify-center flex-shrink-0">
                                        <input type="radio" :name="`q-${qIndex}`" :value="oIndex" class="sr-only" />
                                        <div class="w-2.5 h-2.5 rounded-full bg-primary scale-0 has-[:checked]:scale-100 transition-transform"></div>
                                    </div>
                                    <span class="text-sm">{{ option.text }}</span>
                                </label>
                            </div>

                            <!-- Identification -->
                            <div v-else-if="question.type === 'identification'" class="pl-13">
                                <input
                                    type="text"
                                    placeholder="Type your answer here..."
                                    class="w-full px-4 py-3 rounded-xl border border-border/50 bg-muted/30 hover:border-border focus:ring-2 focus:ring-primary/20 focus:border-primary/40 outline-none transition-all text-sm"
                                />
                            </div>

                            <!-- Essay -->
                            <div v-else-if="question.type === 'essay'" class="pl-13">
                                <textarea
                                    rows="5"
                                    placeholder="Write your answer here..."
                                    class="w-full px-4 py-3 rounded-xl border border-border/50 bg-muted/30 hover:border-border focus:ring-2 focus:ring-primary/20 focus:border-primary/40 outline-none transition-all text-sm resize-none"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit bar -->
                    <div class="sticky bottom-4 flex justify-end">
                        <button class="px-10 py-3.5 rounded-2xl bg-primary text-primary-foreground font-bold shadow-2xl shadow-primary/30 hover:opacity-90 active:scale-[0.98] transition-all flex items-center gap-2">
                            Submit Part
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </template>

            </div>
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
</style>
