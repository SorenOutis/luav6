<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Play,
    CheckCircle2,
    XCircle,
    Sparkles,
    ArrowRight,
    Trophy,
} from 'lucide-vue-next';

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
        options: [
            'Hyper Text Markup Language',
            'High Tech Modern Language',
            'Hyper Transfer Markup Language',
            'Home Tool Markup Language',
        ],
        correctIndex: 0,
        explanation:
            'HTML stands for Hyper Text Markup Language — the standard language for creating web pages.',
    },
    {
        id: 2,
        text: 'JavaScript is a compiled programming language.',
        type: 'true_false',
        options: ['True', 'False'],
        correctIndex: 1,
        explanation:
            'JavaScript is an interpreted (or JIT compiled) scripting language, not a traditionally compiled language.',
    },
    {
        id: 3,
        text: 'Which CSS property controls the font size?',
        type: 'multiple_choice',
        options: ['text-size', 'font-style', 'font-size', 'text-style'],
        correctIndex: 2,
        explanation:
            'The font-size property is used to specify the size of the font in CSS.',
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
    <div class="reveal-section relative mt-24 lg:mt-40">
        <div class="mb-8 flex items-center gap-4 sm:mb-10">
            <div class="h-px w-12 bg-primary"></div>
            <h2
                class="text-[10px] font-black tracking-[0.4em] uppercase lg:text-xs"
                data-scramble
            >
                Try a Sample Assessment
            </h2>
        </div>

        <div
            class="gradient-border relative overflow-hidden rounded-2xl border border-border/40 bg-card/80 shadow-lg backdrop-blur-xl dark:border-border/20 dark:bg-background/70 dark:shadow-none"
        >
            <div
                class="pointer-events-none absolute top-0 left-0 z-10 h-6 w-6 border-t-2 border-l-2 border-foreground/20 sm:h-8 sm:w-8 dark:border-foreground/10"
            ></div>
            <div
                class="pointer-events-none absolute right-0 bottom-0 z-10 h-6 w-6 border-r-2 border-b-2 border-foreground/20 sm:h-8 sm:w-8 dark:border-foreground/10"
            ></div>

            <div
                class="flex flex-col justify-between gap-4 border-b border-border/30 p-4 sm:flex-row sm:items-center sm:p-6 lg:p-8 dark:border-border/15"
            >
                <div class="flex items-center gap-3 sm:gap-4">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-primary/30 bg-primary/10 sm:h-10 sm:w-10"
                    >
                        <Play class="h-3.5 w-3.5 text-primary sm:h-4 sm:w-4" />
                    </div>
                    <div>
                        <p
                            class="text-[8px] font-black tracking-[0.3em] text-primary uppercase sm:text-[9px]"
                        >
                            Demo Protocol
                        </p>
                        <h3
                            class="text-base font-black tracking-tight uppercase sm:text-lg"
                        >
                            Quick Knowledge Check
                        </h3>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <div
                        class="rounded-lg border border-border/40 bg-muted/30 px-3 py-2 sm:px-4 dark:border-border/20 dark:bg-foreground/[0.04]"
                    >
                        <span
                            class="block text-[7px] font-black tracking-[0.2em] text-muted-foreground uppercase sm:text-[8px]"
                            >Progress</span
                        >
                        <span class="font-mono text-xs font-black sm:text-sm"
                            >{{
                                demoCompleted
                                    ? demoQuestions.length
                                    : currentDemoQuestion + 1
                            }}/{{ demoQuestions.length }}</span
                        >
                    </div>
                    <div
                        class="rounded-lg border border-border/40 bg-muted/30 px-3 py-2 sm:px-4 dark:border-border/20 dark:bg-foreground/[0.04]"
                    >
                        <span
                            class="block text-[7px] font-black tracking-[0.2em] text-muted-foreground uppercase sm:text-[8px]"
                            >Score</span
                        >
                        <span
                            class="font-mono text-xs font-black text-primary sm:text-sm"
                            >{{ demoScore }}</span
                        >
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6 lg:p-12">
                <div v-if="!demoCompleted">
                    <div class="mb-4 flex items-center gap-3 sm:mb-6">
                        <span
                            class="font-mono text-[9px] font-black tracking-[0.3em] text-primary"
                            >Q_{{
                                String(currentDemoQuestion + 1).padStart(2, '0')
                            }}</span
                        >
                        <span
                            class="rounded border border-border/30 px-2 py-0.5 text-[8px] font-black tracking-widest text-muted-foreground uppercase sm:text-[9px] dark:border-border/20"
                        >
                            {{
                                demoQuestions[currentDemoQuestion].type.replace(
                                    '_',
                                    ' ',
                                )
                            }}
                        </span>
                    </div>

                    <p
                        class="mb-6 max-w-2xl text-base font-black tracking-tight sm:mb-8 sm:text-lg lg:text-xl"
                    >
                        {{ demoQuestions[currentDemoQuestion].text }}
                    </p>

                    <div
                        class="grid grid-cols-1 gap-2.5 sm:gap-3"
                        :class="
                            demoQuestions[currentDemoQuestion].type ===
                            'true_false'
                                ? 'sm:grid-cols-2'
                                : 'sm:grid-cols-2'
                        "
                    >
                        <button
                            v-for="(option, oIndex) in demoQuestions[
                                currentDemoQuestion
                            ].options"
                            :key="oIndex"
                            @click="selectDemoAnswer(oIndex)"
                            class="group/opt relative overflow-hidden rounded-lg border p-4 text-left transition-all duration-300 sm:p-5"
                            :class="[
                                !demoAnswered
                                    ? 'cursor-pointer border-border/40 bg-muted/20 hover:border-primary/50 hover:bg-primary/5 active:scale-[0.98] dark:border-border/20 dark:bg-foreground/[0.03] dark:hover:bg-primary/10'
                                    : '',
                                demoAnswered &&
                                oIndex ===
                                    demoQuestions[currentDemoQuestion]
                                        .correctIndex
                                    ? 'border-emerald-500/60 bg-emerald-500/10 dark:bg-emerald-500/15'
                                    : '',
                                demoAnswered &&
                                oIndex === selectedDemoAnswer &&
                                oIndex !==
                                    demoQuestions[currentDemoQuestion]
                                        .correctIndex
                                    ? 'border-red-500/60 bg-red-500/10 dark:bg-red-500/15'
                                    : '',
                                demoAnswered &&
                                oIndex !==
                                    demoQuestions[currentDemoQuestion]
                                        .correctIndex &&
                                oIndex !== selectedDemoAnswer
                                    ? 'opacity-40'
                                    : '',
                            ]"
                            :disabled="demoAnswered"
                        >
                            <div
                                class="flex items-center justify-between gap-3 sm:gap-4"
                            >
                                <div class="flex items-center gap-3 sm:gap-4">
                                    <span
                                        class="w-5 shrink-0 font-mono text-[10px] font-black text-muted-foreground"
                                        >{{
                                            String.fromCharCode(65 + oIndex)
                                        }}.</span
                                    >
                                    <span
                                        class="text-xs font-bold sm:text-sm"
                                        >{{ option }}</span
                                    >
                                </div>
                                <CheckCircle2
                                    v-if="
                                        demoAnswered &&
                                        oIndex ===
                                            demoQuestions[currentDemoQuestion]
                                                .correctIndex
                                    "
                                    class="h-4 w-4 shrink-0 text-emerald-500 sm:h-5 sm:w-5"
                                />
                                <XCircle
                                    v-if="
                                        demoAnswered &&
                                        oIndex === selectedDemoAnswer &&
                                        oIndex !==
                                            demoQuestions[currentDemoQuestion]
                                                .correctIndex
                                    "
                                    class="h-4 w-4 shrink-0 text-red-500 sm:h-5 sm:w-5"
                                />
                            </div>
                        </button>
                    </div>

                    <Transition
                        enter-active-class="transition-all duration-500 ease-out"
                        enter-from-class="opacity-0 translate-y-4"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <div
                            v-if="demoAnswered"
                            class="mt-6 flex flex-col justify-between gap-4 sm:mt-8 sm:flex-row sm:items-end sm:gap-6"
                        >
                            <div
                                class="max-w-lg rounded-lg border border-border/30 bg-muted/30 p-4 dark:border-border/15 dark:bg-foreground/[0.04]"
                            >
                                <div class="mb-2 flex items-center gap-2">
                                    <Sparkles class="h-3 w-3 text-primary" />
                                    <span
                                        class="text-[8px] font-black tracking-[0.3em] text-primary uppercase"
                                        >Explanation</span
                                    >
                                </div>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                >
                                    {{
                                        demoQuestions[currentDemoQuestion]
                                            .explanation
                                    }}
                                </p>
                            </div>
                            <button
                                @click="nextDemoQuestion"
                                class="flex shrink-0 items-center justify-center gap-4 rounded-lg bg-foreground px-6 py-3.5 text-[10px] font-black tracking-[0.3em] text-background uppercase transition-all hover:bg-primary hover:text-primary-foreground active:scale-95 sm:justify-start sm:py-4"
                            >
                                {{
                                    currentDemoQuestion <
                                    demoQuestions.length - 1
                                        ? 'Next Question'
                                        : 'View Results'
                                }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </div>
                    </Transition>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center py-8 text-center lg:py-16"
                >
                    <div
                        class="mb-6 flex h-16 w-16 rotate-45 items-center justify-center rounded-xl border-2 border-primary/30 bg-primary/10 sm:h-20 sm:w-20"
                    >
                        <Trophy
                            class="h-6 w-6 -rotate-45 text-primary sm:h-8 sm:w-8"
                        />
                    </div>
                    <h3
                        class="mb-2 text-2xl font-black tracking-tight uppercase sm:text-3xl lg:text-4xl"
                    >
                        Assessment Complete
                    </h3>
                    <p class="mb-2 text-xs text-muted-foreground sm:text-sm">
                        Demo Protocol Finalized
                    </p>

                    <div class="my-4 flex items-baseline gap-2 sm:my-6">
                        <span
                            class="font-mono text-4xl font-black text-primary sm:text-5xl lg:text-6xl"
                            >{{ demoScore }}</span
                        >
                        <span
                            class="text-lg font-black text-muted-foreground/40 sm:text-xl"
                            >/{{ demoQuestions.length }}</span
                        >
                    </div>

                    <p
                        class="mb-6 max-w-md px-4 text-xs text-muted-foreground sm:mb-8 sm:text-sm"
                    >
                        {{
                            demoScore === demoQuestions.length
                                ? "Perfect score! You're ready to dominate."
                                : demoScore >= 2
                                  ? 'Solid performance. The real assessments await.'
                                  : 'Room for growth. Sign up and sharpen your skills.'
                        }}
                    </p>

                    <div
                        class="flex w-full flex-col gap-3 px-4 sm:w-auto sm:flex-row sm:px-0"
                    >
                        <button
                            @click="resetDemoQuiz"
                            class="flex items-center justify-center gap-3 rounded-lg border border-border/40 px-6 py-3.5 text-[10px] font-black tracking-[0.3em] uppercase transition-all hover:bg-muted/30 active:scale-95 dark:border-border/20"
                        >
                            Retry Assessment
                        </button>
                        <Link
                            v-if="!auth.user"
                            :href="register()"
                            class="flex items-center justify-center gap-3 rounded-lg bg-primary px-6 py-3.5 text-[10px] font-black tracking-[0.3em] text-primary-foreground uppercase shadow-sm transition-all hover:gap-5 active:scale-95"
                        >
                            Create Account
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <Link
                            v-else
                            :href="dashboard()"
                            class="flex items-center justify-center gap-3 rounded-lg bg-primary px-6 py-3.5 text-[10px] font-black tracking-[0.3em] text-primary-foreground uppercase shadow-sm transition-all hover:gap-5 active:scale-95"
                        >
                            Go to Dashboard
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>

            <div
                class="flex items-center justify-center gap-2 border-t border-border/30 p-4 dark:border-border/15"
            >
                <div
                    v-for="(q, qi) in demoQuestions"
                    :key="qi"
                    class="h-1.5 rounded-full transition-all duration-300"
                    :class="[
                        qi === currentDemoQuestion && !demoCompleted
                            ? 'w-8 bg-primary'
                            : 'w-1.5',
                        qi < currentDemoQuestion || demoCompleted
                            ? 'bg-primary/50'
                            : 'bg-muted dark:bg-foreground/15',
                    ]"
                ></div>
            </div>
        </div>
    </div>
</template>
