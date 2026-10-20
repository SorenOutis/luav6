<script setup lang="ts">
import {
    Award,
    BarChart3,
    BookOpen,
    CheckCircle2,
    Flame,
    GraduationCap,
    School,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';
import { useDemoLoop, useInView } from '@/composables/useDemoLoop';

type RoleKey = 'teacher' | 'learner' | 'school';

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    {
        prefersReducedMotion: false,
    },
);

const roles = [
    { key: 'teacher' as const, label: 'Teachers', icon: GraduationCap },
    { key: 'learner' as const, label: 'Learners', icon: Flame },
    { key: 'school' as const, label: 'Schools', icon: School },
];

const metrics: Record<RoleKey, { label: string; value: string }[]> = {
    teacher: [
        { label: 'Time saved on grading', value: '50%+' },
        { label: 'Teacher approval control', value: '100%' },
        { label: 'Turnaround to next lesson', value: 'Same day' },
    ],
    learner: [
        { label: 'Active login streaks', value: '8.4 days' },
        { label: 'Feedback receipt speed', value: '< 2 hrs' },
        { label: 'Revision engagement', value: '3.2x higher' },
    ],
    school: [
        { label: 'Section data isolation', value: '100%' },
        { label: 'Quarterly grade export', value: '1-click' },
        { label: 'Assessment alignment', value: 'Formative' },
    ],
};

const activeKey = ref<RoleKey>('teacher');
const activeIndex = computed(() =>
    roles.findIndex((role) => role.key === activeKey.value),
);

/**
 * Scripted chat loop for the Teacher Command Center:
 * Phase 0: Echo thinking & analyzing response (1200ms)
 * Phase 1: Echo typing / streaming the feedback line (2000ms)
 * Phase 2: Ready for review / awaiting 1-click approval (2400ms)
 * Phase 3: Teacher approves with 1 click & releases to Joshua (3400ms)
 */
const feedbackText =
    'Accurate factoring! Connect this to the zero-product rule for roots.';
const PHASE_DURATIONS = [1200, 2000, 2400, 3400];

const cardRef = ref<HTMLElement | null>(null);
const inView = useInView(cardRef);

const { phase, start, stop, set } = useDemoLoop(PHASE_DURATIONS);

const typedChars = ref(0);
let typeInterval: ReturnType<typeof setInterval> | undefined;

function stopTypewriter() {
    if (typeInterval !== undefined) {
        clearInterval(typeInterval);
        typeInterval = undefined;
    }
}

function startTypewriter() {
    stopTypewriter();
    typedChars.value = 0;
    const total = feedbackText.length;
    typeInterval = setInterval(() => {
        if (typedChars.value < total) {
            typedChars.value += 1;
        } else {
            stopTypewriter();
        }
    }, 28);
}

watch(
    phase,
    (newPhase) => {
        if (newPhase === 0) {
            stopTypewriter();
            typedChars.value = 0;
        } else if (newPhase === 1) {
            startTypewriter();
        } else {
            stopTypewriter();
            typedChars.value = feedbackText.length;
        }
    },
    { immediate: true },
);

const enabled = computed(() => !props.prefersReducedMotion);

watch(
    [inView, enabled, activeKey],
    ([visible, isEnabled, key]) => {
        if (!isEnabled) {
            stop();
            stopTypewriter();
            set(3); // Park on static approved state
            typedChars.value = feedbackText.length;
            return;
        }

        if (visible && key === 'teacher') {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (enabled.value && activeKey.value === 'teacher') {
        start();
    }
});

function handleToggleApprove() {
    stopTypewriter();
    if (phase.value === 3) {
        set(0);
    } else {
        typedChars.value = feedbackText.length;
        set(3);
    }
}

onBeforeUnmount(() => {
    stopTypewriter();
    stop();
});
</script>

<template>
    <section
        id="perspectives"
        class="welcome-perspectives scroll-mt-32 border-b border-border/60 py-24 sm:py-32 lg:py-36"
        aria-labelledby="perspectives-heading"
    >
        <RevealGroup class="mx-auto max-w-3xl px-4 text-center">
            <span
                class="rv font-mono text-[11px] font-semibold tracking-[0.2em] text-brand uppercase"
                :style="{ '--rv-i': 0 }"
            >
                ONE LOOP, THREE VIEWS
            </span>
            <h2
                id="perspectives-heading"
                class="rv mt-4 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl"
                :style="{ '--rv-i': 1 }"
            >
                Clarity for teachers. Momentum for learners.
            </h2>
            <p
                class="rv mx-auto mt-4 max-w-xl text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
                :style="{ '--rv-i': 2 }"
            >
                The same assessment loop, seen from the classroom, the desk, and
                the principal's office.
            </p>
        </RevealGroup>

        <!-- Sliding pill switcher -->
        <RevealGroup class="mt-8 flex justify-center px-4 sm:mt-10">
            <div
                class="rv rv-pop rp-track relative grid w-full max-w-md grid-cols-3 rounded-full border border-border/80 bg-secondary/80 p-1 dark:border-[#2c2520] dark:bg-[#1e1813]"
                :style="{ '--rv-i': 0 }"
                role="tablist"
                aria-label="Audience perspective selector"
            >
                <span
                    class="rp-thumb pointer-events-none absolute top-1 bottom-1 left-1 rounded-full bg-foreground shadow-xs"
                    :style="{
                        width: 'calc((100% - 0.5rem) / 3)',
                        transform: `translateX(${activeIndex * 100}%)`,
                    }"
                    aria-hidden="true"
                ></span>
                <button
                    v-for="role in roles"
                    :key="role.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeKey === role.key"
                    class="relative z-10 inline-flex cursor-pointer items-center justify-center gap-2 rounded-full py-2 text-xs font-medium transition-colors duration-200 active:scale-[0.98] sm:text-sm"
                    :class="
                        activeKey === role.key
                            ? 'font-semibold text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeKey = role.key"
                >
                    <component :is="role.icon" class="h-4 w-4" />
                    <span>{{ role.label }}</span>
                </button>
            </div>
        </RevealGroup>

        <!-- Crossfading role panels -->
        <RevealGroup
            class="mx-auto mt-12 w-full max-w-6xl px-4 sm:px-6 lg:mt-16 xl:max-w-7xl"
        >
            <div ref="cardRef" class="rv grid" :style="{ '--rv-i': 0 }">
                <!-- TEACHER -->
                <div
                    class="rp-panel grid items-center gap-10 lg:grid-cols-12 lg:gap-14"
                    :class="{ 'is-active': activeKey === 'teacher' }"
                    :aria-hidden="activeKey !== 'teacher'"
                    :inert="activeKey !== 'teacher'"
                >
                    <div class="space-y-6 px-4 lg:col-span-5 lg:px-0">
                        <span
                            class="font-mono text-[11px] font-semibold tracking-[0.18em] text-brand uppercase"
                        >
                            Teacher Command Center
                        </span>
                        <h3
                            class="font-sans text-2xl font-semibold tracking-[-0.03em] text-foreground sm:text-3xl lg:text-[34px] lg:leading-tight"
                        >
                            Turn assessment responses into tomorrow's plan.
                        </h3>
                        <p
                            class="text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
                        >
                            Review auto-scored objective items and approve
                            AI-drafted feedback in minutes — not evenings.
                        </p>

                        <div
                            class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6 dark:border-[#2c2520]"
                        >
                            <div
                                v-for="m in metrics.teacher"
                                :key="m.label"
                                class="space-y-1"
                            >
                                <p
                                    class="font-sans text-2xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
                                >
                                    {{ m.value }}
                                </p>
                                <p
                                    class="text-xs leading-snug text-muted-foreground"
                                >
                                    {{ m.label }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 lg:col-span-7 lg:px-0">
                        <div
                            class="surface-card group/card overflow-hidden rounded-3xl border border-border/80 bg-card p-6 shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md sm:p-7 dark:border-[#2c2520] dark:bg-[#1a1410]"
                        >
                            <!-- Card Header: Clean & Minimal -->
                            <div
                                class="flex items-center justify-between border-b border-border/60 pb-3.5 dark:border-[#2c2520]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-foreground dark:bg-[#221b15]"
                                    >
                                        <BookOpen class="h-4 w-4" />
                                    </div>
                                    <p
                                        class="text-xs font-semibold text-foreground sm:text-sm"
                                    >
                                        Grade 8 Math · Section Diamond
                                    </p>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                    ></span>
                                    24 of 28 submitted
                                </span>
                            </div>

                            <!-- Chat Stream: Minimalist 2-bubble flow -->
                            <div class="mt-5 space-y-4">
                                <!-- Student Bubble -->
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-secondary text-[11px] font-bold text-foreground dark:bg-[#221b15]"
                                    >
                                        JR
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <span
                                                class="text-xs font-semibold text-foreground"
                                            >
                                                Joshua Reyes
                                            </span>
                                            <span
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                3:42 PM
                                            </span>
                                        </div>
                                        <div
                                            class="mt-1.5 rounded-2xl rounded-tl-sm border border-border/50 bg-secondary/50 px-3.5 py-2.5 text-xs text-foreground/90 sm:text-sm dark:border-[#2c2520] dark:bg-[#14100d]/80"
                                        >
                                            <p
                                                class="font-mono text-xs sm:text-sm"
                                            >
                                                Factors are (x-3)(x-4) = 0 so
                                                x=3 and x=4.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Echo AI Bubble -->
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                                    >
                                        <ChatAiOrb
                                            size="status"
                                            :state="
                                                phase === 0
                                                    ? 'thinking'
                                                    : 'micro'
                                            "
                                            color="var(--color-brand)"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <span
                                                class="text-xs font-semibold text-brand"
                                            >
                                                Echo AI
                                            </span>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-[10px] font-medium transition-colors"
                                                :class="
                                                    phase === 3
                                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                        : phase === 0
                                                          ? 'bg-brand/10 text-brand'
                                                          : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                                "
                                            >
                                                {{
                                                    phase === 3
                                                        ? '✓ Approved'
                                                        : phase === 0
                                                          ? 'Thinking...'
                                                          : 'Pending review'
                                                }}
                                            </span>
                                        </div>

                                        <!-- Bubble Box -->
                                        <div
                                            class="mt-1.5 rounded-2xl rounded-tl-sm border border-border/80 bg-card p-3.5 shadow-xs transition-all dark:border-[#2c2520] dark:bg-[#1e1813]"
                                        >
                                            <!-- Phase 0: Thinking -->
                                            <div
                                                v-if="phase === 0"
                                                class="flex items-center gap-2 py-1 text-xs text-muted-foreground"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 animate-ping rounded-full bg-brand"
                                                ></span>
                                                <span class="text-xs italic">
                                                    Echo is drafting feedback...
                                                </span>
                                            </div>

                                            <!-- Phase 1, 2, 3: Streamed text -->
                                            <div v-else>
                                                <p
                                                    class="font-sans text-xs leading-relaxed text-foreground sm:text-sm"
                                                >
                                                    "{{
                                                        feedbackText.slice(
                                                            0,
                                                            typedChars,
                                                        )
                                                    }}<span
                                                        v-if="
                                                            phase === 1 &&
                                                            typedChars <
                                                                feedbackText.length
                                                        "
                                                        class="ml-0.5 inline-block h-3.5 w-0.5 animate-pulse bg-brand align-middle"
                                                        aria-hidden="true"
                                                    ></span
                                                    >"
                                                </p>
                                            </div>

                                            <!-- 1-Click Approval Action -->
                                            <div
                                                class="mt-3 flex items-center justify-end"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold shadow-xs transition-all active:scale-[0.98]"
                                                    :class="
                                                        phase === 3
                                                            ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-black'
                                                            : 'bg-foreground text-background hover:opacity-90'
                                                    "
                                                    @click="handleToggleApprove"
                                                >
                                                    <CheckCircle2
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    <span>
                                                        {{
                                                            phase === 3
                                                                ? 'Approved & Released ✓'
                                                                : 'Approve & Release (1 click)'
                                                        }}
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Minimalist Bottom Strip -->
                                <div
                                    class="flex items-center justify-between border-t border-border/60 pt-3 text-[11px] text-muted-foreground dark:border-[#2c2520]"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <BarChart3
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        <span>
                                            Pattern: 6 learners missed negative
                                            signs
                                        </span>
                                    </div>
                                    <span
                                        class="cursor-pointer font-medium text-foreground hover:text-brand"
                                    >
                                        Added to next lesson →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LEARNER -->
                <div
                    class="rp-panel grid items-center gap-10 lg:grid-cols-12 lg:gap-14"
                    :class="{ 'is-active': activeKey === 'learner' }"
                    :aria-hidden="activeKey !== 'learner'"
                    :inert="activeKey !== 'learner'"
                >
                    <div class="space-y-6 px-4 lg:col-span-5 lg:px-0">
                        <span
                            class="font-mono text-[11px] font-semibold tracking-[0.18em] text-brand uppercase"
                        >
                            Learner Habit Engine
                        </span>
                        <h3
                            class="font-sans text-2xl font-semibold tracking-[-0.03em] text-foreground sm:text-3xl lg:text-[34px] lg:leading-tight"
                        >
                            Every quiz builds confidence and daily momentum.
                        </h3>
                        <p
                            class="text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
                        >
                            Immediate feedback, daily streaks, and section
                            leaderboards keep students practicing while the
                            lesson is still fresh.
                        </p>

                        <div
                            class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6 dark:border-[#2c2520]"
                        >
                            <div
                                v-for="m in metrics.learner"
                                :key="m.label"
                                class="space-y-1"
                            >
                                <p
                                    class="font-sans text-2xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
                                >
                                    {{ m.value }}
                                </p>
                                <p
                                    class="text-xs leading-snug text-muted-foreground"
                                >
                                    {{ m.label }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 lg:col-span-7 lg:px-0">
                        <div
                            class="surface-card group/card overflow-hidden rounded-3xl border border-border/80 bg-card p-6 shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md sm:p-7 dark:border-[#2c2520] dark:bg-[#1a1410]"
                        >
                            <!-- Card Header -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3.5 dark:border-[#2c2520]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-xs font-bold text-foreground dark:bg-[#221b15]"
                                    >
                                        JR
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-foreground sm:text-sm"
                                        >
                                            Joshua Reyes
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-400"
                                    >
                                        <Flame
                                            class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                                        />
                                        8-Day Streak
                                    </span>
                                    <span
                                        class="rounded-full border border-border/80 bg-secondary px-2.5 py-0.5 font-mono text-[11px] font-medium text-muted-foreground dark:border-[#2c2520] dark:bg-[#221b15]"
                                    >
                                        1,450 XP
                                    </span>
                                </div>
                            </div>

                            <!-- Inner Blocks: Minimalist -->
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-2xl border border-border/70 bg-secondary/40 p-4 dark:border-[#2c2520] dark:bg-[#14100d]/70"
                                >
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            Recent Feedback
                                        </span>
                                        <span
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            From Ms. Santos
                                        </span>
                                    </div>
                                    <p
                                        class="mt-2 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        "Accurate factoring! Connect this to the
                                        zero-product rule for roots."
                                    </p>
                                    <div
                                        class="mt-3 flex items-center justify-between text-[11px]"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1 font-medium text-brand"
                                        >
                                            <Sparkles class="h-3 w-3" />
                                            +50 XP
                                        </span>
                                        <span
                                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            Grade: 100%
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="rounded-2xl border border-border/70 bg-secondary/40 p-4 dark:border-[#2c2520] dark:bg-[#14100d]/70"
                                >
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            Class Podium
                                        </span>
                                        <span
                                            class="font-mono text-[11px] font-medium text-brand"
                                        >
                                            Rank #3
                                        </span>
                                    </div>
                                    <div class="mt-2.5 space-y-1.5">
                                        <div
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span class="text-foreground"
                                                >1. Gabriel R.</span
                                            >
                                            <span
                                                class="text-muted-foreground tabular-nums"
                                                >1,820 XP</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span class="text-foreground"
                                                >2. Chloe T.</span
                                            >
                                            <span
                                                class="text-muted-foreground tabular-nums"
                                                >1,610 XP</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center justify-between rounded-lg border border-brand/20 bg-brand/10 px-2 py-1 text-xs font-semibold text-brand"
                                        >
                                            <span>3. You (Joshua)</span>
                                            <span class="tabular-nums"
                                                >1,450 XP</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Action -->
                            <div
                                class="mt-3.5 flex items-center justify-between rounded-xl border border-border/70 bg-secondary/40 px-4 py-2.5 text-xs dark:border-[#2c2520] dark:bg-[#14100d]/70"
                            >
                                <div class="flex items-center gap-2">
                                    <Award
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <span class="text-muted-foreground">
                                        Next up:
                                        <strong
                                            class="font-semibold text-foreground"
                                            >Quadratic Word Problems</strong
                                        >
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-foreground px-3 py-1 text-xs font-semibold text-background transition-all hover:opacity-90 active:scale-[0.98]"
                                >
                                    <span>Begin</span>
                                    <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SCHOOL -->
                <div
                    class="rp-panel grid items-center gap-10 lg:grid-cols-12 lg:gap-14"
                    :class="{ 'is-active': activeKey === 'school' }"
                    :aria-hidden="activeKey !== 'school'"
                    :inert="activeKey !== 'school'"
                >
                    <div class="space-y-6 px-4 lg:col-span-5 lg:px-0">
                        <span
                            class="font-mono text-[11px] font-semibold tracking-[0.18em] text-brand uppercase"
                        >
                            Institutional Governance
                        </span>
                        <h3
                            class="font-sans text-2xl font-semibold tracking-[-0.03em] text-foreground sm:text-3xl lg:text-[34px] lg:leading-tight"
                        >
                            Total diagnostic oversight across your entire
                            school.
                        </h3>
                        <p
                            class="text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
                        >
                            Multi-tenant workspaces isolate section data, track
                            curriculum pacing, and log every teacher-approved AI
                            action.
                        </p>

                        <div
                            class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6 dark:border-[#2c2520]"
                        >
                            <div
                                v-for="m in metrics.school"
                                :key="m.label"
                                class="space-y-1"
                            >
                                <p
                                    class="font-sans text-2xl font-bold tracking-tight text-foreground tabular-nums sm:text-3xl"
                                >
                                    {{ m.value }}
                                </p>
                                <p
                                    class="text-xs leading-snug text-muted-foreground"
                                >
                                    {{ m.label }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 lg:col-span-7 lg:px-0">
                        <div
                            class="surface-card group/card overflow-hidden rounded-3xl border border-border/80 bg-card p-6 shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md sm:p-7 dark:border-[#2c2520] dark:bg-[#1a1410]"
                        >
                            <!-- Card Header -->
                            <div
                                class="flex items-center justify-between border-b border-border/60 pb-3.5 dark:border-[#2c2520]"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary text-foreground dark:bg-[#221b15]"
                                    >
                                        <School class="h-4 w-4" />
                                    </div>
                                    <p
                                        class="text-xs font-semibold text-foreground sm:text-sm"
                                    >
                                        School Workspace · 14 Sections
                                    </p>
                                </div>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-border/80 bg-secondary px-2.5 py-0.5 text-xs font-medium text-muted-foreground dark:border-[#2c2520] dark:bg-[#221b15]"
                                >
                                    Tenant Isolated
                                </span>
                            </div>

                            <!-- Sections List: Clean & Minimal -->
                            <div class="mt-5 space-y-2.5">
                                <div
                                    class="flex items-center justify-between rounded-xl border border-brand/30 bg-brand/5 p-3 text-xs dark:border-brand/20 dark:bg-brand/10"
                                >
                                    <span class="font-medium text-foreground">
                                        Grade 8 Mathematics — Section Diamond
                                        (Ms. Santos)
                                    </span>
                                    <span
                                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        86% Avg Mastery
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl border border-border/70 bg-secondary/40 p-3 text-xs dark:border-[#2c2520] dark:bg-[#14100d]/70"
                                >
                                    <span class="font-medium text-foreground">
                                        Grade 8 Mathematics — Section Ruby
                                    </span>
                                    <span
                                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        82% Avg Mastery
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between rounded-xl border border-border/70 bg-secondary/40 p-3 text-xs dark:border-[#2c2520] dark:bg-[#14100d]/70"
                                >
                                    <span class="font-medium text-foreground">
                                        Grade 7 Science — Section Sapphire
                                    </span>
                                    <span
                                        class="font-semibold text-amber-600 dark:text-amber-400"
                                    >
                                        74% (Support Flagged)
                                    </span>
                                </div>
                            </div>

                            <!-- Bottom Info -->
                            <div
                                class="mt-4 flex items-center justify-between border-t border-border/60 pt-3 text-[11px] text-muted-foreground dark:border-[#2c2520]"
                            >
                                <span>
                                    Audit Log: 100% human-verified AI actions
                                </span>
                                <span
                                    class="cursor-pointer font-medium text-foreground hover:text-brand"
                                >
                                    Export CSV / PDF →
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </RevealGroup>
    </section>
</template>

<style scoped>
.rp-thumb {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.rp-panel {
    grid-area: 1 / 1;
    opacity: 0;
    transform: translateY(8px);
    transition:
        opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
}

.rp-panel.is-active {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

@media (prefers-reduced-motion: reduce) {
    .rp-thumb,
    .rp-panel {
        transition: none;
    }

    .rp-panel {
        transform: none;
    }
}
</style>
