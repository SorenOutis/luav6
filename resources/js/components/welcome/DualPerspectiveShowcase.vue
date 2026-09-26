<script setup lang="ts">
import {
    Award,
    BarChart3,
    BookOpen,
    CheckCircle2,
    Flame,
    GraduationCap,
    School,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

type PerspectiveKey = 'teacher' | 'learner' | 'school';

const activeTab = ref<PerspectiveKey>('teacher');

const perspectives = [
    {
        key: 'teacher' as const,
        label: 'For Teachers',
        icon: GraduationCap,
        badge: 'Assessment & Feedback',
        tagline: 'Spend less time grading, more time teaching.',
        description:
            'Create curriculum-aligned assessments in minutes. AI drafts feedback based on your rubrics, but every score and suggestion stays in your review queue until you approve it.',
        metrics: [
            { label: 'Time saved on grading', value: '50%+' },
            { label: 'Teacher approval control', value: '100%' },
            { label: 'Turnaround to next lesson', value: 'Same day' },
        ],
    },
    {
        key: 'learner' as const,
        label: 'For Learners',
        icon: Flame,
        badge: 'Habits & Momentum',
        tagline: 'Feedback while the lesson is still fresh.',
        description:
            'Students get clear, constructive guidance immediately instead of waiting weeks. Gamified streaks, section leaderboards, and XP turn daily practice into a habit.',
        metrics: [
            { label: 'Active login streaks', value: '8.4 days' },
            { label: 'Feedback receipt speed', value: '< 2 hrs' },
            { label: 'Revision engagement', value: '3.2x higher' },
        ],
    },
    {
        key: 'school' as const,
        label: 'For Schools',
        icon: School,
        badge: 'Visibility & Governance',
        tagline: 'Clear diagnostic oversight across every section.',
        description:
            'Tenant-isolated workspaces give principals and department heads season-based progress, grade tracking, and full audit trails of all teacher-approved feedback.',
        metrics: [
            { label: 'Section data isolation', value: '100%' },
            { label: 'Quarterly grade export', value: '1-click' },
            { label: 'DepEd compliance', value: 'Formative' },
        ],
    },
];
</script>

<template>
    <section
        id="perspectives"
        class="welcome-perspectives scroll-mt-32 border-b border-border/70 py-16 sm:py-24"
        aria-labelledby="perspectives-heading"
    >
        <div class="text-center">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3.5 py-1 text-xs font-semibold text-[#D97757]"
            >
                Institutional Perspectives
            </span>
            <h2
                id="perspectives-heading"
                class="mt-4 font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
            >
                Clarity for teachers. Momentum for learners.
            </h2>
            <p
                class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
            >
                LSI connects formative assessment directly to student practice,
                ensuring every assessment informs the very next lesson.
            </p>
        </div>

        <!-- Perspective Switcher Tabs (Material 3 Filter Chips) -->
        <div class="mt-8 flex justify-center px-4 sm:mt-10">
            <div
                class="inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-border/70 bg-secondary/30 p-1.5"
                role="tablist"
                aria-label="Audience perspective selector"
            >
                <button
                    v-for="p in perspectives"
                    :key="p.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === p.key"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full px-5 py-2 text-xs font-medium transition-all active:scale-[0.98] sm:text-sm"
                    :class="
                        activeTab === p.key
                            ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                            : 'border border-transparent bg-transparent text-muted-foreground hover:bg-card/70 hover:text-foreground'
                    "
                    @click="activeTab = p.key"
                >
                    <component
                        :is="p.icon"
                        class="h-4 w-4"
                        :class="
                            activeTab === p.key
                                ? 'text-primary-foreground'
                                : 'text-muted-foreground'
                        "
                    />
                    <span>{{ p.label }}</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Content Display -->
        <div class="mt-10 lg:mt-14">
            <!-- TEACHER PERSPECTIVE -->
            <div
                v-if="activeTab === 'teacher'"
                class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12"
            >
                <div class="space-y-6 lg:col-span-5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-[#D97757]/10 px-3 py-0.5 text-xs font-semibold text-[#D97757]"
                    >
                        Teacher Command Center
                    </span>
                    <h3
                        class="font-sans text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        Turn assessment responses into tomorrow's plan.
                    </h3>
                    <p
                        class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        Instead of spending your evenings grading identical
                        worksheets, review auto-scored objective items and
                        approve AI-drafted feedback for open responses in
                        minutes.
                    </p>

                    <div
                        class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6"
                    >
                        <div
                            v-for="m in perspectives[0].metrics"
                            :key="m.label"
                            class="space-y-1"
                        >
                            <p
                                class="font-serif text-2xl font-bold text-foreground"
                            >
                                {{ m.value }}
                            </p>
                            <p
                                class="text-[11px] leading-tight text-muted-foreground"
                            >
                                {{ m.label }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Interactive Teacher UI Mockup -->
                <div class="lg:col-span-7">
                    <div
                        class="surface-card overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-7"
                    >
                        <!-- Top header of mockup -->
                        <div
                            class="flex items-center justify-between border-b border-border/60 pb-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#D97757]/15 text-[#D97757]"
                                >
                                    <BookOpen class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        Grade 8 Math · Section Diamond
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Mid-Quarter Formative Exam · 28 Learners
                                    </p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                24 of 28 Submitted
                            </span>
                        </div>

                        <!-- Teacher queue items -->
                        <div class="mt-5 space-y-4">
                            <!-- Pending action card -->
                            <div
                                class="rounded-xl border border-[#D97757]/30 bg-[#D97757]/[0.03] p-4"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="font-semibold text-foreground">
                                        Question 3: Quadratic Factorization
                                    </span>
                                    <span
                                        class="rounded-md bg-[#D97757]/15 px-2 py-0.5 text-[11px] font-semibold text-[#D97757]"
                                    >
                                        Pending Review
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Student:
                                    <span class="font-medium text-foreground"
                                        >Maria Santos</span
                                    >
                                    —
                                    <em
                                        >"Factors are (x-3)(x-4) = 0 so x=3 and
                                        x=4."</em
                                    >
                                </p>
                                <div
                                    class="mt-3 rounded-lg border border-border/60 bg-background/80 p-3"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] font-semibold text-[#D97757]"
                                    >
                                        <ChatAiOrb
                                            size="status"
                                            animate-idle
                                            color="#D97757"
                                            class="h-3.5 w-3.5"
                                        />
                                        Echo AI Feedback (Teacher approval
                                        required):
                                    </div>
                                    <p class="mt-1 text-xs text-foreground">
                                        "Accurate factoring! Connect this to the
                                        zero-product rule for roots."
                                    </p>
                                </div>
                                <div
                                    class="mt-3 flex items-center justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        class="rounded-md border border-border/80 px-2.5 py-1 text-xs font-medium text-muted-foreground hover:bg-muted/30"
                                    >
                                        Edit feedback
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-md bg-[#D97757] px-3 py-1 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                                    >
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        Approve & Release (1 click)
                                    </button>
                                </div>
                            </div>

                            <!-- Class understanding insight -->
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/60 bg-secondary/30 px-4 py-3 text-xs"
                            >
                                <div class="flex items-center gap-2.5">
                                    <BarChart3 class="h-4 w-4 text-[#D97757]" />
                                    <span class="text-foreground">
                                        <strong>Pattern spotted:</strong> 6
                                        learners missed negative signs on Item
                                        4.
                                    </span>
                                </div>
                                <span class="font-semibold text-[#D97757]">
                                    Added to Next Lesson →
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LEARNER PERSPECTIVE -->
            <div
                v-else-if="activeTab === 'learner'"
                class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12"
            >
                <div class="space-y-6 lg:col-span-5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        Learner Habit Engine
                    </span>
                    <h3
                        class="font-sans text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        Every quiz builds confidence and daily momentum.
                    </h3>
                    <p
                        class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        Learners aren't left waiting weeks for a letter grade.
                        Immediate feedback, daily streaks, and section
                        leaderboards motivate students to keep practicing and
                        mastering new skills.
                    </p>

                    <div
                        class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6"
                    >
                        <div
                            v-for="m in perspectives[1].metrics"
                            :key="m.label"
                            class="space-y-1"
                        >
                            <p
                                class="font-serif text-2xl font-bold text-foreground"
                            >
                                {{ m.value }}
                            </p>
                            <p
                                class="text-[11px] leading-tight text-muted-foreground"
                            >
                                {{ m.label }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Interactive Learner UI Mockup -->
                <div class="lg:col-span-7">
                    <div
                        class="surface-card overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-7"
                    >
                        <!-- Student Command Bar Header -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-[#D97757]/15 font-semibold text-[#D97757]"
                                >
                                    MS
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Maria Santos
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Section Diamond · Davao Central College
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    <Flame
                                        class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                                    />
                                    8-Day Streak
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#D97757]/10 px-2.5 py-1 text-xs font-semibold text-[#D97757]"
                                >
                                    Lv 12 · 1,450 XP
                                </span>
                            </div>
                        </div>

                        <!-- Progress card mock -->
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-xl border border-border/70 bg-secondary/20 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                    >
                                        Recent Feedback
                                    </span>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        15m ago
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    "Great step-by-step logic on factoring! Keep
                                    this clean notation for the exam."
                                </p>
                                <div
                                    class="mt-3 flex items-center justify-between text-[11px]"
                                >
                                    <span class="font-medium text-[#D97757]">
                                        +50 XP Earned
                                    </span>
                                    <span
                                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        Grade: 100%
                                    </span>
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-border/70 bg-secondary/20 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                    >
                                        Class Podium
                                    </span>
                                    <span
                                        class="text-[10px] font-medium text-[#D97757]"
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
                                        <span class="text-muted-foreground"
                                            >1,820 XP</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span class="text-foreground"
                                            >2. Chloe T.</span
                                        >
                                        <span class="text-muted-foreground"
                                            >1,610 XP</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center justify-between rounded-md bg-[#D97757]/10 px-1.5 py-0.5 text-xs font-semibold text-[#D97757]"
                                    >
                                        <span>3. You (Maria)</span>
                                        <span>1,450 XP</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active study task -->
                        <div
                            class="mt-3 flex items-center justify-between rounded-xl border border-border/60 bg-background/80 px-4 py-3 text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <Award class="h-4 w-4 text-[#D97757]" />
                                <span class="text-foreground">
                                    Next up: <strong>Web Basics Quiz</strong> —
                                    due in 2 hours
                                </span>
                            </div>
                            <span class="font-semibold text-primary">
                                Begin →
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SCHOOL PERSPECTIVE -->
            <div
                v-else
                class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12"
            >
                <div class="space-y-6 lg:col-span-5">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-950/60 dark:text-blue-300"
                    >
                        Institutional Governance
                    </span>
                    <h3
                        class="font-sans text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        Total diagnostic oversight across your entire school.
                    </h3>
                    <p
                        class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        Multi-tenant school workspaces isolate section data,
                        track curriculum pacing, and ensure that every AI action
                        is logged, reviewed, and safe for learner privacy.
                    </p>

                    <div
                        class="grid grid-cols-3 gap-4 border-t border-border/70 pt-6"
                    >
                        <div
                            v-for="m in perspectives[2].metrics"
                            :key="m.label"
                            class="space-y-1"
                        >
                            <p
                                class="font-serif text-2xl font-bold text-foreground"
                            >
                                {{ m.value }}
                            </p>
                            <p
                                class="text-[11px] leading-tight text-muted-foreground"
                            >
                                {{ m.label }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Interactive School UI Mockup -->
                <div class="lg:col-span-7">
                    <div
                        class="surface-card overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-7"
                    >
                        <div
                            class="flex items-center justify-between border-b border-border/60 pb-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                >
                                    <School class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        Davao Central College Workspace
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        14 Sections · 520 Enrolled Learners ·
                                        DepEd Aligned
                                    </p>
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-600 dark:text-blue-400"
                            >
                                Tenant Isolated
                            </span>
                        </div>

                        <!-- School analytics stats -->
                        <div class="mt-5 space-y-3">
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs"
                            >
                                <span class="font-medium text-foreground">
                                    Grade 8 Mathematics — Section Diamond
                                </span>
                                <span
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    86% Avg Mastery
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs"
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
                                class="flex items-center justify-between rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs"
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

                        <div
                            class="mt-4 flex items-center justify-between rounded-xl border border-border/60 bg-background/80 px-4 py-3 text-xs text-muted-foreground"
                        >
                            <span
                                >Audit Log: 100% human-verified AI grading
                                actions</span
                            >
                            <span class="font-semibold text-primary">
                                Export CSV / PDF →
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
