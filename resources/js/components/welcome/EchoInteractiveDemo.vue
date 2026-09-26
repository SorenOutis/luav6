<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    MessageSquare,
    Sparkles,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

const prompts = [
    {
        id: 'math',
        label: 'Explain factoring quadratics',
        question: 'How do I solve x² - 7x + 12 = 0 using factoring?',
        response:
            'Find two numbers that multiply to 12 and add to -7: that’s -3 and -4. Factor into (x - 3)(x - 4) = 0. By the zero-product property, x = 3 or x = 4.',
        tag: 'Mathematics · Formative Help',
    },
    {
        id: 'exam',
        label: 'What should I review for my exam?',
        question: 'Which topics do I need to review before Friday’s quiz?',
        response:
            'Based on your last 3 formative checks, focus on Factoring by Grouping (68% mastery) and Quadratic Word Problems. You’ve already mastered standard trinomials!',
        tag: 'Diagnostic Insight · Grade 8',
    },
    {
        id: 'streak',
        label: 'How does teacher review work?',
        question: 'Does Echo grade my essays automatically?',
        response:
            'Echo drafts suggested rubric scores and feedback, but your teacher reviews and approves every remark before it reaches your gradebook.',
        tag: 'Human-in-the-Loop AI · Verified',
    },
];

const selectedPrompt = ref(prompts[0]);
const orbState = ref<'idle' | 'thinking' | 'speaking'>('idle');
const displayedText = ref(prompts[0].response);
const isGenerating = ref(false);

async function selectPrompt(prompt: (typeof prompts)[number]) {
    if (isGenerating.value && selectedPrompt.value.id === prompt.id) return;
    selectedPrompt.value = prompt;
    isGenerating.value = true;
    displayedText.value = '';
    orbState.value = 'thinking';

    // Simulate quick intelligent thinking
    await new Promise((resolve) => setTimeout(resolve, 600));

    orbState.value = 'speaking';
    const text = prompt.response;
    let current = '';

    for (let i = 0; i < text.length; i += 2) {
        current += text.slice(i, i + 2);
        displayedText.value = current;
        await new Promise((resolve) => setTimeout(resolve, 14));
    }

    displayedText.value = text;
    orbState.value = 'idle';
    isGenerating.value = false;
}
</script>

<template>
    <section
        id="interactive-echo"
        class="welcome-echo-demo scroll-mt-32 border-b border-border/60 py-16 sm:py-24"
        aria-labelledby="echo-demo-heading"
    >
        <div class="text-center">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3.5 py-1 text-xs font-semibold text-[#D97757]"
            >
                <Sparkles class="h-3 w-3" />
                Live Assistant Preview
            </span>
            <h2
                id="echo-demo-heading"
                class="mt-4 font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
            >
                Meet Echo in action.
            </h2>
            <p
                class="mx-auto mt-4 max-w-xl text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
            >
                Our adaptive study companion supports learners during practice
                while ensuring teachers remain in total control of verified
                grades.
            </p>
        </div>

        <div
            class="surface-card relative mx-auto mt-12 max-w-3xl overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-7 md:p-8"
        >
            <!-- Top Row: Interactive Persona & Status -->
            <div
                class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left"
            >
                <div
                    class="relative flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl border border-border/80 bg-secondary/30 p-2"
                >
                    <ChatAiOrb
                        size="md"
                        :state="orbState"
                        :animate-idle="orbState === 'idle'"
                        color="#D97757"
                    />
                </div>
                <div class="flex-1">
                    <div
                        class="flex flex-wrap items-center justify-center gap-2 sm:justify-start"
                    >
                        <span
                            class="font-sans text-xl font-bold tracking-tight text-foreground"
                        >
                            Echo AI Companion
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-semibold"
                            :class="
                                orbState === 'thinking'
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                    : orbState === 'speaking'
                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                      : 'bg-[#D97757]/10 text-[#D97757]'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    orbState === 'thinking'
                                        ? 'animate-ping bg-amber-500'
                                        : orbState === 'speaking'
                                          ? 'animate-pulse bg-emerald-500'
                                          : 'bg-[#D97757]'
                                "
                            />
                            {{
                                orbState === 'thinking'
                                    ? 'Synthesizing rubric…'
                                    : orbState === 'speaking'
                                      ? 'Explaining to learner…'
                                      : 'Active · Tri-Form Companion'
                            }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Select a sample prompt below to observe how Echo
                        transitions between inquiry, thinking, and feedback.
                    </p>
                </div>
            </div>

            <!-- Google-Style Prompt Selector Chips -->
            <div class="mt-6 flex flex-wrap gap-2 pt-2">
                <button
                    v-for="prompt in prompts"
                    :key="prompt.id"
                    type="button"
                    :disabled="isGenerating && selectedPrompt.id === prompt.id"
                    class="cursor-pointer rounded-full border px-4 py-2 text-xs font-medium transition-all active:scale-[0.98]"
                    :class="
                        selectedPrompt.id === prompt.id
                            ? 'border-[#D97757] bg-[#D97757]/10 font-semibold text-[#D97757]'
                            : 'border-border/70 bg-secondary/30 text-muted-foreground hover:border-border hover:bg-secondary/60 hover:text-foreground'
                    "
                    @click="selectPrompt(prompt)"
                >
                    {{ prompt.label }}
                </button>
            </div>

            <!-- Live Dialogue Window (Material 3 Surface) -->
            <div
                class="mt-5 space-y-3 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground shadow-xs"
                    >
                        Q
                    </div>
                    <div class="text-xs">
                        <span class="font-semibold text-foreground"
                            >Learner Query</span
                        >
                        <p class="mt-1 text-sm font-medium text-foreground">
                            “{{ selectedPrompt.question }}”
                        </p>
                    </div>
                </div>

                <div class="border-t border-border/50 pt-3.5">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#D97757]/15 text-[#D97757]"
                        >
                            <ChatAiOrb
                                size="status"
                                :state="orbState"
                                color="#D97757"
                                class="h-4 w-4"
                            />
                        </div>
                        <div class="flex-1 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-[#D97757]">
                                    Echo Response
                                </span>
                                <span
                                    class="rounded-full bg-secondary/80 px-2 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    {{ selectedPrompt.tag }}
                                </span>
                            </div>
                            <p
                                class="mt-1.5 text-sm leading-relaxed text-foreground"
                            >
                                {{ displayedText }}
                                <span
                                    v-if="orbState === 'speaking'"
                                    class="inline-block h-3.5 w-1.5 animate-pulse rounded-xs bg-[#D97757] align-middle"
                                />
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action link to Chats -->
            <div
                class="mt-6 flex flex-col items-center justify-between gap-3 border-t border-border/60 pt-4 sm:flex-row"
            >
                <span
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <CheckCircle2
                        class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                    />
                    Full classroom chat experience included in student accounts.
                </span>
                <Link
                    href="/chats"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#D97757] hover:underline"
                >
                    <MessageSquare class="h-3.5 w-3.5" />
                    Open Echo Chats
                    <ArrowRight class="h-3 w-3" />
                </Link>
            </div>
        </div>
    </section>
</template>
