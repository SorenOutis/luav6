<script setup lang="ts">
import {
    Download,
    FileSpreadsheet,
    FileText,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';

const reports = [
    {
        title: 'Student Progress Report',
        format: 'PDF',
        detail: 'Grades, badges, activity history, feedback highlights, and teacher remarks.',
        icon: FileText,
    },
    {
        title: 'Class Performance Summary',
        format: 'PDF / CSV',
        detail: 'Section averages, completion rates, top skills, and learners needing support.',
        icon: TrendingUp,
    },
    {
        title: 'Exam Analytics Export',
        format: 'CSV',
        detail: 'Scores, item trends, AI feedback status, skipped essays, and submission counts.',
        icon: FileSpreadsheet,
    },
    {
        title: 'At-Risk Learners List',
        format: 'PDF',
        detail: 'Low scores, missed work, weak engagement signals, and follow-up priorities.',
        icon: TrendingDown,
    },
];
</script>

<template>
    <section class="reveal-section mt-24 lg:mt-40">
        <div class="overflow-hidden border border-border/30 bg-card/60">
            <div
                class="grid gap-8 border-b border-border/20 p-6 lg:grid-cols-[0.85fr_1.15fr] lg:p-10"
            >
                <div>
                    <div class="mb-5 flex items-center gap-4">
                        <div class="h-px w-12 bg-primary"></div>
                        <p
                            class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                            data-scramble
                        >
                            School Reports
                        </p>
                    </div>
                    <h2
                        class="text-3xl font-black tracking-tight uppercase sm:text-4xl lg:text-5xl"
                    >
                        Turn activity into records schools can use.
                    </h2>
                </div>
            </div>

            <div class="grid gap-px bg-border/20 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="report in reports"
                    :key="report.title"
                    class="bg-background p-6 lg:p-8"
                >
                    <div class="mb-8 flex items-center justify-between gap-4">
                        <div
                            class="flex h-11 w-11 items-center justify-center border border-primary/20 bg-primary/10"
                        >
                            <component
                                :is="report.icon"
                                class="h-5 w-5 text-primary"
                            />
                        </div>
                        <span
                            class="border border-border/30 px-3 py-1 text-[8px] font-black tracking-[0.25em] text-muted-foreground uppercase"
                            >{{ report.format }}</span
                        >
                    </div>
                    <h3
                        class="mb-3 text-lg font-black tracking-tight uppercase"
                    >
                        {{ report.title }}
                    </h3>
                    <p
                        class="mb-8 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ report.detail }}
                    </p>
                    <button
                        class="inline-flex items-center gap-3 border border-border/40 px-4 py-3 text-[9px] font-black tracking-[0.25em] text-muted-foreground uppercase transition-colors hover:border-primary/40 hover:text-primary"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Preview Export
                    </button>
                </article>
            </div>
        </div>
    </section>
</template>
