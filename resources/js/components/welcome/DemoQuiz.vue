<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Play, CheckCircle2, XCircle, Sparkles, ArrowRight, Trophy } from 'lucide-vue-next';

defineProps<{
    auth: { user: any };
    register: () => string;
    dashboard: () => string;
}>();

interface DemoQuestion {
    id: number;
    text: string;
    type: 'multiple_choice' | 'true_false';
    options: string[];
    correctIndex: number;
    explanation: string;
}

const demoQuestions: DemoQuestion[] = [
    {
        id: 1,
        text: 'What does HTML stand for?',
        type: 'multiple_choice',
        options: ['Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language'],
        correctIndex: 0,
        explanation: 'HTML stands for Hyper Text Markup Language — the standard language for creating web pages.'
    },
    {
        id: 2,
        text: 'JavaScript is a compiled programming language.',
        type: 'true_false',
        options: ['True', 'False'],
        correctIndex: 1,
        explanation: 'JavaScript is an interpreted (or JIT compiled) scripting language, not a traditionally compiled language.'
    },
    {
        id: 3,
        text: 'Which CSS property controls the font size?',
        type: 'multiple_choice',
        options: ['text-size', 'font-style', 'font-size', 'text-style'],
        correctIndex: 2,
        explanation: 'The font-size property is used to specify the size of the font in CSS.'
    },
];

const currentDemoQuestion = ref(0);
const selectedDemoAnswer = ref<number | null>(null);
const demoAnswered = ref(false);
const demoScore = ref(0);
const demoCompleted = ref(false);

const selectDemoAnswer = (index: number) => {
    if (demoAnswered.value) return;
    selectedDemoAnswer.value = index;
    demoAnswered.value = true;
    if (index === demoQuestions[currentDemoQuestion.value].correctIndex) {
        demoScore.value++;
    }
};

const nextDemoQuestion = () => {
    if (currentDemoQuestion.value < demoQuestions.length - 1) {
        currentDemoQuestion.value++;
        selectedDemoAnswer.value = null;
        demoAnswered.value = false;
    } else {
        demoCompleted.value = true;
    }
};

const resetDemoQuiz = () => {
    currentDemoQuestion.value = 0;
    selectedDemoAnswer.value = null;
    demoAnswered.value = false;
    demoScore.value = 0;
    demoCompleted.value = false;
};
</script>

<template>
    <div class="reveal-section mt-24 lg:mt-40 relative">
        <div class="flex items-center gap-4 mb-8 sm:mb-10">
            <div class="h-px w-12 bg-primary"></div>
            <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]" data-scramble>Try a Sample Assessment</h2>
        </div>

        <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/80 dark:bg-background/70 backdrop-blur-xl shadow-lg dark:shadow-none gradient-border">
            <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-2 border-l-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>
            <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-2 border-r-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>

            <div class="border-b border-border/30 dark:border-border/15 p-4 sm:p-6 lg:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center border border-primary/30 bg-primary/10 rounded-lg shrink-0">
                        <Play class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary" />
                    </div>
                    <div>
                        <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-[0.3em] text-primary">Demo Protocol</p>
                        <h3 class="text-base sm:text-lg font-black uppercase tracking-tight">Quick Knowledge Check</h3>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                        <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Progress</span>
                        <span class="text-xs sm:text-sm font-black font-mono">{{ demoCompleted ? demoQuestions.length : currentDemoQuestion + 1 }}/{{ demoQuestions.length }}</span>
                    </div>
                    <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                        <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Score</span>
                        <span class="text-xs sm:text-sm font-black font-mono text-primary">{{ demoScore }}</span>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6 lg:p-12">
                <div v-if="!demoCompleted">
                    <div class="flex items-center gap-3 mb-4 sm:mb-6">
                        <span class="text-[9px] font-black tracking-[0.3em] text-primary font-mono">Q_{{ String(currentDemoQuestion + 1).padStart(2, '0') }}</span>
                        <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground px-2 py-0.5 border border-border/30 dark:border-border/20 rounded">
                            {{ demoQuestions[currentDemoQuestion].type.replace('_', ' ') }}
                        </span>
                    </div>
                    
                    <p class="text-base sm:text-lg lg:text-xl font-black tracking-tight mb-6 sm:mb-8 max-w-2xl">
                        {{ demoQuestions[currentDemoQuestion].text }}
                    </p>

                    <div class="grid gap-2.5 sm:gap-3 grid-cols-1" :class="demoQuestions[currentDemoQuestion].type === 'true_false' ? 'sm:grid-cols-2' : 'sm:grid-cols-2'">
                        <button
                            v-for="(option, oIndex) in demoQuestions[currentDemoQuestion].options"
                            :key="oIndex"
                            @click="selectDemoAnswer(oIndex)"
                            class="relative p-4 sm:p-5 border rounded-lg text-left transition-all duration-300 group/opt overflow-hidden"
                            :class="[
                                !demoAnswered ? 'border-border/40 dark:border-border/20 hover:border-primary/50 hover:bg-primary/5 dark:hover:bg-primary/10 cursor-pointer active:scale-[0.98] bg-muted/20 dark:bg-foreground/[0.03]' : '',
                                demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex ? 'border-emerald-500/60 bg-emerald-500/10 dark:bg-emerald-500/15' : '',
                                demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex ? 'border-red-500/60 bg-red-500/10 dark:bg-red-500/15' : '',
                                demoAnswered && oIndex !== demoQuestions[currentDemoQuestion].correctIndex && oIndex !== selectedDemoAnswer ? 'opacity-40' : '',
                            ]"
                            :disabled="demoAnswered"
                        >
                            <div class="flex items-center justify-between gap-3 sm:gap-4">
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <span class="text-[10px] font-black font-mono text-muted-foreground w-5 shrink-0">{{ String.fromCharCode(65 + oIndex) }}.</span>
                                    <span class="text-xs sm:text-sm font-bold">{{ option }}</span>
                                </div>
                                <CheckCircle2 v-if="demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-emerald-500 shrink-0" />
                                <XCircle v-if="demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-red-500 shrink-0" />
                            </div>
                        </button>
                    </div>

                    <Transition
                        enter-active-class="transition-all duration-500 ease-out"
                        enter-from-class="opacity-0 translate-y-4"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <div v-if="demoAnswered" class="mt-6 sm:mt-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4 sm:gap-6">
                            <div class="p-4 border border-border/30 dark:border-border/15 bg-muted/30 dark:bg-foreground/[0.04] max-w-lg rounded-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <Sparkles class="h-3 w-3 text-primary" />
                                    <span class="text-[8px] font-black uppercase tracking-[0.3em] text-primary">Explanation</span>
                                </div>
                                <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    {{ demoQuestions[currentDemoQuestion].explanation }}
                                </p>
                            </div>
                            <button
                                @click="nextDemoQuestion"
                                class="flex items-center justify-center sm:justify-start gap-4 bg-foreground text-background px-6 py-3.5 sm:py-4 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-primary hover:text-primary-foreground transition-all active:scale-95 shrink-0 rounded-lg"
                            >
                                {{ currentDemoQuestion < demoQuestions.length - 1 ? 'Next Question' : 'View Results' }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </div>
                    </Transition>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-8 lg:py-16 text-center">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center border-2 border-primary/30 bg-primary/10 mb-6 rotate-45 rounded-xl">
                        <Trophy class="h-6 w-6 sm:h-8 sm:w-8 text-primary -rotate-45" />
                    </div>
                    <h3 class="text-2xl sm:text-3xl lg:text-4xl font-black uppercase tracking-tight mb-2">Assessment Complete</h3>
                    <p class="text-muted-foreground text-xs sm:text-sm mb-2">Demo Protocol Finalized</p>
                    
                    <div class="flex items-baseline gap-2 my-4 sm:my-6">
                        <span class="text-4xl sm:text-5xl lg:text-6xl font-black text-primary font-mono">{{ demoScore }}</span>
                        <span class="text-lg sm:text-xl font-black text-muted-foreground/40">/{{ demoQuestions.length }}</span>
                    </div>
                    
                    <p class="text-xs sm:text-sm text-muted-foreground mb-6 sm:mb-8 max-w-md px-4">
                        {{ demoScore === demoQuestions.length ? 'Perfect score! You\'re ready to dominate.' : demoScore >= 2 ? 'Solid performance. The real assessments await.' : 'Room for growth. Sign up and sharpen your skills.' }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto px-4 sm:px-0">
                        <button @click="resetDemoQuiz" class="flex items-center justify-center gap-3 border border-border/40 dark:border-border/20 px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-muted/30 transition-all active:scale-95 rounded-lg">
                            Retry Assessment
                        </button>
                        <Link v-if="!auth.user" :href="register()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                            Create Account
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <Link v-else :href="dashboard()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                            Go to Dashboard
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>

            <div class="border-t border-border/30 dark:border-border/15 p-4 flex items-center justify-center gap-2">
                <div v-for="(q, qi) in demoQuestions" :key="qi" class="h-1.5 rounded-full transition-all duration-300"
                    :class="[
                        qi === currentDemoQuestion && !demoCompleted ? 'w-8 bg-primary' : 'w-1.5',
                        qi < currentDemoQuestion || demoCompleted ? 'bg-primary/50' : 'bg-muted dark:bg-foreground/15',
                    ]"
                ></div>
            </div>
        </div>
    </div>
</template>
