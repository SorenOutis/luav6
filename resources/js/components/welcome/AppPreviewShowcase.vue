<script setup lang="ts">
import {
    Activity,
    BarChart3,
    ClipboardCheck,
    Gamepad2,
    Map,
    Star,
} from 'lucide-vue-next';

const previews = [
    {
        title: 'Student Dashboard',
        label: 'Daily Learning View',
        icon: Activity,
        rows: [
            'Next activity: Web Basics Quiz',
            'Current streak: 8 days',
            'Section rank: 4 of 38',
        ],
        accent: 'bg-emerald-500',
    },
    {
        title: 'Teacher Analytics',
        label: 'Class Monitoring',
        icon: BarChart3,
        rows: [
            'Average score: 86%',
            'Needs review: 6 learners',
            'Submissions checked: 124',
        ],
        accent: 'bg-primary',
    },
    {
        title: 'Assessment Flow',
        label: 'AI Feedback',
        icon: ClipboardCheck,
        rows: [
            'Essay feedback: queued',
            'Rubric trace: visible',
            'Skipped answers: flagged',
        ],
        accent: 'bg-amber-500',
    },
    {
        title: 'Learning Map',
        label: 'Mastery Path',
        icon: Map,
        rows: [
            'World: Fundamentals',
            'Unlocked nodes: 12',
            'Next pass score: 80%',
        ],
        accent: 'bg-cyan-500',
    },
    {
        title: 'Game Hub',
        label: 'Motivation Layer',
        icon: Gamepad2,
        rows: [
            'Tower defense level: 5',
            'XP reward: active',
            'Leaderboard: section scoped',
        ],
        accent: 'bg-rose-500',
    },
    {
        title: 'Grades',
        label: 'Progress Record',
        icon: Star,
        rows: [
            'Quarter grade: ready',
            'Semester view: supported',
            'Remarks: teacher visible',
        ],
        accent: 'bg-violet-500',
    },
];
</script>

<template>
    <section class="reveal-section mt-24 lg:mt-40">
        <div
            class="mb-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <div class="mb-5 flex items-center gap-4">
                    <div class="h-px w-12 bg-primary"></div>
                    <p
                        class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                        data-scramble
                    >
                        Product Preview
                    </p>
                </div>
                <h2
                    class="max-w-3xl text-3xl font-black tracking-tight uppercase sm:text-4xl lg:text-6xl"
                >
                    Show the real school workflow.
                </h2>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="preview in previews"
                :key="preview.title"
                class="overflow-hidden border border-border/30 bg-card/60"
            >
                <div
                    class="flex items-center justify-between border-b border-border/20 bg-muted/20 px-5 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center border border-border/30 bg-background"
                        >
                            <component
                                :is="preview.icon"
                                class="h-4 w-4 text-primary"
                            />
                        </div>
                        <div>
                            <p
                                class="text-[8px] font-black tracking-[0.25em] text-primary uppercase"
                            >
                                {{ preview.label }}
                            </p>
                            <h3
                                class="text-sm font-black tracking-widest uppercase"
                            >
                                {{ preview.title }}
                            </h3>
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full bg-muted-foreground/30"
                        ></span>
                        <span
                            class="h-2 w-2 rounded-full bg-muted-foreground/30"
                        ></span>
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="preview.accent"
                        ></span>
                    </div>
                </div>

                <div class="space-y-3 p-5">
                    <div
                        v-for="row in preview.rows"
                        :key="row"
                        class="flex items-center justify-between gap-4 border border-border/20 bg-background/50 px-4 py-3"
                    >
                        <span class="text-xs font-bold text-muted-foreground">{{
                            row
                        }}</span>
                        <span
                            class="h-2 w-12 rounded-full bg-primary/20"
                        ></span>
                    </div>
                    <div
                        class="mt-5 h-16 overflow-hidden border border-border/20 bg-muted/20 p-3"
                    >
                        <div class="flex h-full items-end gap-1.5">
                            <span
                                v-for="i in 18"
                                :key="i"
                                class="flex-1 rounded-t-sm bg-primary/25"
                                :style="{ height: `${28 + ((i * 17) % 52)}%` }"
                            ></span>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
