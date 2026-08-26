<script setup lang="ts">
import { ArrowRight, CheckCircle2, FileText } from 'lucide-vue-next';
</script>

<template>
    <div
        class="relative mx-auto w-full max-w-[560px] rotate-[1.5deg] rounded-2xl border border-border/80 bg-card p-4 shadow-[0_24px_70px_-38px_rgba(26,26,30,0.45)] sm:p-6"
    >
        <div
            class="flex items-center justify-between border-b border-border/70 pb-4"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <FileText class="h-4 w-4" aria-hidden="true" />
                </div>
                <div>
                    <p class="text-xs font-semibold text-foreground">
                        Grade 8 Math
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        Quadratic equations · Check-in
                    </p>
                </div>
            </div>
            <span
                class="rounded-full bg-secondary px-2.5 py-1 text-[10px] font-medium text-secondary-foreground"
            >
                24 responses
            </span>
        </div>

        <div class="grid gap-5 py-5 sm:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-3">
                <p
                    class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Question 04
                </p>
                <p class="text-base leading-snug font-semibold text-foreground">
                    Solve x² − 5x + 6 = 0.
                </p>
                <div class="space-y-2 text-xs text-muted-foreground">
                    <div
                        class="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2"
                    >
                        <span>x = 2, 3</span>
                        <CheckCircle2
                            class="h-4 w-4 text-emerald-600"
                            aria-hidden="true"
                        />
                    </div>
                    <div
                        class="flex items-center justify-between rounded-lg border border-amber-500/40 bg-amber-500/5 px-3 py-2 text-foreground"
                    >
                        <span>x = −2, −3</span>
                        <span
                            class="h-2 w-2 rounded-full bg-amber-500"
                            aria-label="Needs review"
                        ></span>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2"
                    >
                        <span>x = 5, 6</span>
                        <span
                            class="h-2 w-2 rounded-full bg-border"
                            aria-hidden="true"
                        ></span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-secondary/55 p-4">
                <p
                    class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Next lesson
                </p>
                <p
                    class="mt-3 text-sm leading-snug font-semibold text-foreground"
                >
                    Factoring practice
                </p>
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    5 learners need a quick review before moving on.
                </p>
                <div
                    class="mt-5 flex items-center gap-1.5 text-xs font-semibold text-primary"
                >
                    Plan follow-up
                    <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                </div>
            </div>
        </div>

        <div
            class="flex items-center justify-between border-t border-border/70 pt-3 text-[11px] text-muted-foreground"
        >
            <span>Teacher review required</span>
            <span class="font-medium text-emerald-700 dark:text-emerald-400"
                >Ready to use</span
            >
        </div>
    </div>
</template>
