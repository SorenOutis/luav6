<script setup lang="ts">
import {
    CheckCircle2,
    ListChecks,
    ShieldCheck,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import MiniDemo from '@/components/welcome/MiniDemo.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

const props = withDefaults(
    defineProps<{
        isCoarsePointer?: boolean;
        prefersReducedMotion?: boolean;
        auth?: { user: any };
        dashboard?: () => string;
        login?: () => string;
    }>(),
    {
        isCoarsePointer: false,
        prefersReducedMotion: false,
        auth: undefined,
        dashboard: undefined,
        login: undefined,
    },
);

/**
 * Mini-demos run as quick scripted loops. They park on their finished
 * frame on low-end devices and whenever motion is reduced.
 */
const demosEnabled = computed(
    () => !props.prefersReducedMotion && !props.isCoarsePointer,
);

const autoScoreRows = [
    { name: 'Maria S.', detail: 'x = 3, x = 4' },
    { name: 'Jun P.', detail: 'x = 3, x = 4' },
    { name: 'Althea R.', detail: 'x = 2, x = 3' },
];

const builderOptions = [
    { label: 'x = 2, x = 3', tag: 'factor sum', correct: false },
    { label: 'x = 3, x = 4', tag: 'zero-product', correct: true },
    { label: 'x = -3, x = -4', tag: 'sign error', correct: false },
];
</script>

<template>
    <section
        id="features"
        class="welcome-studio scroll-mt-32 border-b border-border/60 py-20 sm:py-28"
        :class="{
            'lite-motion': prefersReducedMotion || isCoarsePointer,
        }"
        aria-labelledby="studio-heading"
    >
        <RevealGroup class="mx-auto max-w-3xl px-4 text-center">
            <span
                class="rv font-mono text-xs font-medium tracking-wider text-brand uppercase"
                :style="{ '--rv-i': 0 }"
            >
                The Classroom Loop
            </span>
            <h2
                id="studio-heading"
                class="rv mt-4 font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
                :style="{ '--rv-i': 1 }"
            >
                From first answer to next lesson.
            </h2>
            <p
                class="rv mx-auto mt-4 max-w-2xl text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
                :style="{ '--rv-i': 2 }"
            >
                Four moves, one loop — assessment, feedback, and the plan for
                tomorrow, with a teacher approving every AI draft.
            </p>
        </RevealGroup>

        <RevealGroup
            :stagger="90"
            class="mx-auto mt-10 grid max-w-6xl gap-6 px-4 sm:mt-12 sm:grid-cols-2 sm:px-6 lg:gap-8 xl:max-w-7xl"
        >
            <!-- 01 · Create -->
            <article
                class="rv flex flex-col overflow-hidden rounded-3xl border border-border/80 bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                :style="{ '--rv-i': 0 }"
            >
                <div
                    class="flex h-64 items-center justify-center overflow-hidden border-b border-border/60 bg-secondary/40 p-4 sm:p-5 dark:bg-secondary/30"
                >
                    <MiniDemo
                        v-slot="{ phase, cycle }"
                        :durations="[1500, 1200, 1400, 2900]"
                        :enabled="demosEnabled"
                        :static-phase="3"
                        class="w-full"
                    >
                        <div
                            :key="cycle"
                            class="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-border/80 bg-card text-foreground shadow-md sm:max-w-md dark:border-border/80 dark:bg-card dark:text-foreground dark:shadow-xl"
                        >
                            <!-- Mini macOS chrome -->
                            <div
                                class="flex items-center justify-between border-b border-border/80 bg-secondary/80 px-3 py-2 text-[10px] dark:border-border/80 dark:bg-secondary/80"
                            >
                                <div
                                    class="flex items-center gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FF5F56]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FFBD2E]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#27C93F]/80"
                                    ></span>
                                </div>
                                <span
                                    class="font-mono text-[9px] text-muted-foreground dark:text-muted-foreground"
                                    >Curriculum Tag · Quadratic Equations</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-medium"
                                    :class="
                                        phase >= 3
                                            ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'
                                            : 'border border-brand/30 bg-brand/15 text-brand dark:bg-brand/20'
                                    "
                                >
                                    <span
                                        v-if="phase < 3"
                                        class="mr-0.5 inline-block animate-pulse"
                                        >●</span
                                    >
                                    {{ phase >= 3 ? 'Ready' : 'Working' }}
                                </span>
                            </div>

                            <div class="space-y-2.5 p-3.5 text-left">
                                <div
                                    class="fd-in flex items-center justify-between text-[10px] font-medium"
                                >
                                    <span class="font-mono text-brand"
                                        >Grade 8 Math</span
                                    >
                                    <span
                                        class="text-zinc-500 dark:text-zinc-400"
                                        >Section Diamond</span
                                    >
                                </div>
                                <p
                                    class="fd-type-quick text-[11px] leading-snug font-medium text-zinc-900 dark:text-white"
                                >
                                    What are the solutions to x² − 7x + 12 = 0?
                                </p>
                                <div class="space-y-1.5">
                                    <div
                                        v-for="(
                                            option, index
                                        ) in builderOptions"
                                        :key="option.label"
                                        class="fd-in flex items-center justify-between rounded-lg border px-2.5 py-1.5 text-[10px]"
                                        :class="[
                                            option.correct && phase >= 2
                                                ? 'border-emerald-500/50 bg-emerald-500/15 font-semibold text-emerald-800 dark:border-emerald-500/60 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                : 'border-border/80 bg-secondary/50 text-foreground',
                                            { 'fd-correct': option.correct },
                                        ]"
                                        :style="{
                                            '--d': `${1.05 + index * 0.12}s`,
                                        }"
                                    >
                                        <span>{{ option.label }}</span>
                                        <span
                                            class="font-mono text-[9px] opacity-70"
                                            >{{ option.tag }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t border-zinc-200/70 pt-2 dark:border-white/[0.08]"
                                >
                                    <span
                                        class="font-mono text-[9px] text-zinc-500 dark:text-zinc-400"
                                        >Objective + essay</span
                                    >
                                    <span
                                        class="fd-chip inline-flex items-center gap-1 rounded-full border border-brand/30 bg-brand/15 px-2 py-0.5 text-[9px] font-semibold text-brand dark:bg-brand/20"
                                        :class="{ 'is-on': phase >= 3 }"
                                    >
                                        15 items ready
                                    </span>
                                </div>
                            </div>
                        </div>
                    </MiniDemo>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-6 sm:p-7">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-brand"
                            >01 · Create</span
                        >
                        <ListChecks class="h-4 w-4 text-brand" />
                    </div>
                    <h3
                        class="font-sans text-lg font-semibold tracking-tight text-foreground"
                    >
                        Assess what you taught
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Build section-targeted exams from curriculum tags —
                        objective and essay, in one flow.
                    </p>
                </div>
            </article>

            <!-- 02 · Auto-score -->
            <article
                class="rv flex flex-col overflow-hidden rounded-3xl border border-border/80 bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                :style="{ '--rv-i': 1 }"
            >
                <div
                    class="flex h-64 items-center justify-center overflow-hidden border-b border-border/60 bg-secondary/40 p-4 sm:p-5 dark:bg-secondary/30"
                >
                    <MiniDemo
                        v-slot="{ phase, cycle }"
                        :durations="[1500, 1600, 2800]"
                        :enabled="demosEnabled"
                        :static-phase="2"
                        class="w-full"
                    >
                        <div
                            :key="cycle"
                            class="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-border/80 bg-card text-foreground shadow-md sm:max-w-md dark:border-border/80 dark:bg-card dark:text-foreground dark:shadow-xl"
                        >
                            <!-- Mini macOS chrome -->
                            <div
                                class="flex items-center justify-between border-b border-border/80 bg-secondary/80 px-3 py-2 text-[10px] dark:border-border/80 dark:bg-secondary/80"
                            >
                                <div
                                    class="flex items-center gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FF5F56]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FFBD2E]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#27C93F]/80"
                                    ></span>
                                </div>
                                <span
                                    class="font-mono text-[9px] text-muted-foreground dark:text-muted-foreground"
                                    >Live Scoring Engine</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-medium"
                                    :class="
                                        phase >= 2
                                            ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'
                                            : 'border border-blue-500/30 bg-blue-500/15 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'
                                    "
                                >
                                    <span
                                        v-if="phase < 2"
                                        class="mr-0.5 inline-block animate-spin"
                                        >◐</span
                                    >
                                    {{ phase >= 2 ? 'Scored' : 'Working' }}
                                </span>
                            </div>

                            <div class="space-y-2.5 p-3.5 text-left">
                                <div
                                    class="fd-in flex items-center justify-between text-[10px] font-medium"
                                >
                                    <span class="font-mono text-brand"
                                        >Live Submissions</span
                                    >
                                    <span
                                        class="text-zinc-500 dark:text-zinc-400"
                                        >Item 3 · Multiple Choice</span
                                    >
                                </div>
                                <div class="space-y-1.5">
                                    <div
                                        v-for="(row, index) in autoScoreRows"
                                        :key="row.name"
                                        class="fd-in flex items-center justify-between rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1.5 text-[10px]"
                                        :style="{ '--i': index + 1 }"
                                    >
                                        <span
                                            class="font-medium text-foreground"
                                            >{{ row.name }}</span
                                        >
                                        <span class="flex items-center gap-2">
                                            <span
                                                class="font-mono text-[9px] text-muted-foreground"
                                                >{{ row.detail }}</span
                                            >
                                            <span
                                                class="fd-dot h-2 w-2 rounded-full"
                                                :class="
                                                    phase >= 1
                                                        ? 'is-scored bg-emerald-500'
                                                        : 'animate-pulse bg-muted'
                                                "
                                                :style="{
                                                    '--row-index': index,
                                                }"
                                            ></span>
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 border-t border-border/70 pt-2"
                                >
                                    <div
                                        class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-[width] duration-700 ease-out"
                                            :class="
                                                phase >= 2 ? 'w-full' : 'w-0'
                                            "
                                        ></div>
                                    </div>
                                    <span
                                        class="font-mono text-[9px] text-zinc-500 dark:text-zinc-400"
                                    >
                                        {{
                                            phase >= 2
                                                ? '28 of 28 · avg 4.2s'
                                                : 'Scoring as they submit…'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </MiniDemo>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-6 sm:p-7">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-brand"
                            >02 · Auto-score</span
                        >
                        <Zap class="h-4 w-4 text-brand" />
                    </div>
                    <h3
                        class="font-sans text-lg font-semibold tracking-tight text-foreground"
                    >
                        Score the second they submit
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Objective items graded automatically, with
                        per-distractor diagnostics for your reteach list.
                    </p>
                </div>
            </article>

            <!-- 03 · Review -->
            <article
                class="rv flex flex-col overflow-hidden rounded-3xl border border-border/80 bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                :style="{ '--rv-i': 2 }"
            >
                <div
                    class="flex h-64 items-center justify-center overflow-hidden border-b border-border/60 bg-secondary/40 p-4 sm:p-5 dark:bg-secondary/30"
                >
                    <MiniDemo
                        v-slot="{ phase, cycle }"
                        :durations="[1400, 1900, 2700]"
                        :enabled="demosEnabled"
                        :static-phase="2"
                        class="w-full"
                    >
                        <div
                            :key="cycle"
                            class="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-border/80 bg-card text-foreground shadow-md sm:max-w-md dark:border-border/80 dark:bg-card dark:text-foreground dark:shadow-xl"
                        >
                            <!-- Mini macOS chrome -->
                            <div
                                class="flex items-center justify-between border-b border-border/80 bg-secondary/80 px-3 py-2 text-[10px] dark:border-border/80 dark:bg-secondary/80"
                            >
                                <div
                                    class="flex items-center gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FF5F56]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FFBD2E]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#27C93F]/80"
                                    ></span>
                                </div>
                                <span
                                    class="font-mono text-[9px] text-muted-foreground dark:text-muted-foreground"
                                    >Teacher Review Queue</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-medium"
                                    :class="
                                        phase >= 2
                                            ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'
                                            : 'border border-amber-500/30 bg-amber-500/15 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'
                                    "
                                >
                                    <span
                                        v-if="phase < 2"
                                        class="mr-0.5 inline-block animate-pulse"
                                        >●</span
                                    >
                                    {{ phase >= 2 ? 'Approved' : 'Needs OK' }}
                                </span>
                            </div>

                            <div class="space-y-2.5 p-3.5 text-left">
                                <p
                                    class="fd-in rounded-lg border border-border/80 bg-secondary/50 px-2.5 py-1.5 text-[10px] text-muted-foreground italic"
                                >
                                    “The roots are 3 and 4 because they multiply
                                    to 12 and add to −7.”
                                </p>
                                <div
                                    class="fd-in flex items-center gap-2"
                                    style="--d: 0.25s"
                                >
                                    <span
                                        class="inline-flex text-sm leading-none"
                                        aria-hidden="true"
                                    >
                                        <ChatAiOrb
                                            size="inline"
                                            :state="
                                                phase === 1
                                                    ? 'thinking'
                                                    : 'micro'
                                            "
                                            :delay="400"
                                            color="var(--color-brand)"
                                        />
                                    </span>
                                    <span
                                        class="font-mono text-[9px] font-semibold tracking-wide text-brand uppercase"
                                        >Echo Draft</span
                                    >
                                </div>
                                <div class="relative">
                                    <div
                                        class="fd-skeleton space-y-1.5"
                                        :class="{ 'is-off': phase >= 1 }"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="h-2 w-full animate-pulse rounded-full bg-muted"
                                        ></div>
                                        <div
                                            class="h-2 w-4/5 animate-pulse rounded-full bg-muted"
                                        ></div>
                                    </div>
                                    <p
                                        class="fd-type-slow text-[10px] leading-relaxed text-foreground"
                                    >
                                        Spot-on factorization. Call out the
                                        zero-product property next time.
                                    </p>
                                </div>
                                <div
                                    class="fd-chip inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:text-emerald-400"
                                    :class="{ 'is-on': phase >= 2 }"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    Approved by teacher
                                </div>
                            </div>
                        </div>
                    </MiniDemo>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-6 sm:p-7">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-brand"
                            >03 · Review</span
                        >
                        <ShieldCheck class="h-4 w-4 text-brand" />
                    </div>
                    <h3
                        class="font-sans text-lg font-semibold tracking-tight text-foreground"
                    >
                        Echo drafts, you decide
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Echo drafts essay feedback; every word waits for your
                        approval before it reaches a learner.
                    </p>
                </div>
            </article>

            <!-- 04 · Grow -->
            <article
                class="rv flex flex-col overflow-hidden rounded-3xl border border-border/80 bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                :style="{ '--rv-i': 3 }"
            >
                <div
                    class="flex h-64 items-center justify-center overflow-hidden border-b border-border/60 bg-secondary/40 p-4 sm:p-5 dark:bg-secondary/30"
                >
                    <MiniDemo
                        v-slot="{ phase, cycle }"
                        :durations="[1400, 1700, 2700]"
                        :enabled="demosEnabled"
                        :static-phase="2"
                        class="w-full"
                    >
                        <div
                            :key="cycle"
                            class="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-border/80 bg-card text-foreground shadow-md sm:max-w-md dark:border-border/80 dark:bg-card dark:text-foreground dark:shadow-xl"
                        >
                            <!-- Mini macOS chrome -->
                            <div
                                class="flex items-center justify-between border-b border-border/80 bg-secondary/80 px-3 py-2 text-[10px] dark:border-border/80 dark:bg-secondary/80"
                            >
                                <div
                                    class="flex items-center gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FF5F56]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#FFBD2E]/80"
                                    ></span>
                                    <span
                                        class="h-2 w-2 rounded-full bg-[#27C93F]/80"
                                    ></span>
                                </div>
                                <span
                                    class="font-mono text-[9px] text-muted-foreground dark:text-muted-foreground"
                                    >Mastery Telemetry</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[9px] font-medium"
                                    :class="
                                        phase >= 2
                                            ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'
                                            : 'border border-brand/30 bg-brand/15 text-brand dark:bg-brand/20'
                                    "
                                >
                                    {{ phase >= 2 ? 'Ready' : 'Tracking' }}
                                </span>
                            </div>

                            <div class="space-y-2.5 p-3.5 text-left">
                                <div
                                    class="fd-in flex items-center justify-between text-[10px]"
                                >
                                    <span class="font-medium text-foreground"
                                        >Factorization · Diamond</span
                                    >
                                    <span
                                        class="font-mono font-bold text-brand"
                                        >{{ phase >= 1 ? '88%' : '62%' }}</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-brand transition-[width] duration-1000 ease-out"
                                        :class="
                                            phase >= 1 ? 'w-[88%]' : 'w-[62%]'
                                        "
                                    ></div>
                                </div>
                                <div
                                    class="fd-chip flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1.5 text-[10px] text-amber-800 dark:text-amber-300"
                                    :class="{ 'is-on': phase >= 1 }"
                                >
                                    <span class="mt-0.5 font-bold">6</span>
                                    <span
                                        >students transposed signs — reteach
                                        before Quadratic Formula.</span
                                    >
                                </div>
                                <div
                                    class="fd-chip inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:text-emerald-400"
                                    :class="{ 'is-on': phase >= 2 }"
                                >
                                    <TrendingUp class="h-3 w-3" />
                                    Tomorrow · 8-min reteach ready
                                </div>
                            </div>
                        </div>
                    </MiniDemo>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-6 sm:p-7">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-brand"
                            >04 · Grow</span
                        >
                        <TrendingUp class="h-4 w-4 text-brand" />
                    </div>
                    <h3
                        class="font-sans text-lg font-semibold tracking-tight text-foreground"
                    >
                        See who's ready to move on
                    </h3>
                    <p
                        class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Live mastery per section, plus exactly what to reteach —
                        decided before the bell rings.
                    </p>
                </div>
            </article>
        </RevealGroup>
    </section>
</template>

<style scoped>
/* ── Shared mini-demo choreography ── */
.fd-in {
    animation: fd-in 0.5s var(--ease-out-expo) both;
    animation-delay: var(--d, calc(var(--i, 0) * 90ms));
}

@keyframes fd-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fd-type-quick,
.fd-type-slow {
    clip-path: inset(0 100% 0 0);
    animation: fd-type 0.9s steps(26, end) both;
    animation-delay: 0.25s;
}

.fd-type-slow {
    animation-duration: 1s;
    animation-delay: 1.45s;
}

@keyframes fd-type {
    from {
        clip-path: inset(0 100% 0 0);
    }
    to {
        clip-path: inset(0 0 0 0);
    }
}

.fd-chip {
    opacity: 0;
    transform: scale(0.7) translateY(3px);
    transition:
        opacity var(--dur-pop) var(--ease-pop),
        transform var(--dur-pop) var(--ease-pop);
}

.fd-chip.is-on {
    opacity: 1;
    transform: none;
}

.fd-dot {
    transition:
        transform 0.35s var(--ease-pop),
        background-color 0.25s ease;
}

.fd-dot.is-scored {
    transform: scale(1.35);
    transition-delay: calc(var(--row-index, 0) * 140ms);
}

.fd-skeleton {
    transition: opacity 0.26s var(--ease-out-expo);
}

.fd-skeleton.is-off {
    opacity: 0;
}

.fd-correct {
    transition:
        border-color 0.3s ease,
        background-color 0.3s ease,
        color 0.3s ease;
}

@media (prefers-reduced-motion: reduce) {
    .fd-in,
    .fd-type-quick,
    .fd-type-slow {
        animation: none;
        clip-path: inset(0 0 0 0);
    }

    .fd-chip,
    .fd-dot,
    .fd-skeleton,
    .fd-correct {
        transition: none;
    }
}
</style>
