<script setup lang="ts">
import { CheckCircle2, Flame, Lightbulb } from 'lucide-vue-next';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
</script>

<template>
    <div
        class="relative mx-auto w-full max-w-[500px] transition-transform duration-300 ease-out hover:rotate-0 sm:rotate-[0.5deg]"
    >
        <!-- Ambient warmth glow behind artifact -->
        <div
            class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-tr from-[#D97757]/10 via-[#D97757]/5 to-transparent blur-2xl"
            aria-hidden="true"
        ></div>

        <!-- Main Card Surface (matches dashboard surface-card styling) -->
        <div
            class="surface-card relative overflow-hidden rounded-2xl border border-border/80 bg-card/95 p-5 shadow-[0_20px_40px_-15px_rgba(26,26,30,0.12)] backdrop-blur-md sm:p-6 dark:shadow-[0_24px_48px_-15px_rgba(0,0,0,0.6)]"
        >
            <!-- Window header bar -->
            <div
                class="flex items-center justify-between border-b border-border/60 pb-3.5"
            >
                <div class="flex items-center gap-2">
                    <div class="flex gap-1.5" aria-hidden="true">
                        <span class="h-2 w-2 rounded-full bg-border"></span>
                        <span class="h-2 w-2 rounded-full bg-border"></span>
                        <span class="h-2 w-2 rounded-full bg-border"></span>
                    </div>
                    <span
                        class="ml-2 text-xs font-semibold tracking-tight text-foreground"
                    >
                        Grade 8 · Mathematics
                    </span>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                    ></span>
                    24/28 Reviewed
                </span>
            </div>

            <!-- Formative Assessment Question -->
            <div class="mt-4">
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Question 3 · Formative Check
                    </span>
                    <span class="text-xs font-medium text-[#D97757]">
                        5 Points
                    </span>
                </div>
                <p class="mt-1 text-sm font-semibold text-foreground">
                    Explain how to solve x² - 7x + 12 = 0 using factoring.
                </p>
            </div>

            <!-- Student Answer Row -->
            <div
                class="mt-3.5 rounded-xl border border-border/70 bg-secondary/30 p-3.5"
            >
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-[#D97757]/15 text-[10px] font-bold text-[#D97757]"
                        >
                            MS
                        </div>
                        <span class="font-medium text-foreground">
                            Maria Santos
                        </span>
                    </div>
                    <span class="text-[11px] text-muted-foreground">
                        Section Diamond
                    </span>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-foreground italic">
                    “Find factors of 12 that add to -7: (-3) and (-4). So (x -
                    3)(x - 4) = 0, which means x = 3 and x = 4.”
                </p>
            </div>

            <!-- AI-Drafted Feedback with Teacher Approval Guarantee -->
            <div
                class="mt-3.5 rounded-xl border border-[#D97757]/30 bg-[#D97757]/[0.04] p-3.5"
            >
                <div class="flex items-center justify-between text-[11px]">
                    <span
                        class="flex items-center gap-1.5 font-bold text-[#D97757]"
                    >
                        <ChatAiOrb
                            size="status"
                            animate-idle
                            color="#D97757"
                            class="h-3.5 w-3.5"
                        />
                        Echo AI Drafted Feedback
                    </span>
                    <span
                        class="rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[10px] font-semibold text-[#D97757]"
                    >
                        Teacher Approval Required
                    </span>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-foreground">
                    Spot-on factorization and root extraction. Great
                    demonstration of the zero-product property.
                </p>
                <div
                    class="mt-3 flex items-center justify-between border-t border-[#D97757]/20 pt-2.5"
                >
                    <span class="text-[11px] text-muted-foreground">
                        Awarded:
                        <strong class="text-foreground">5 / 5 pts</strong>
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#D97757] px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 hover:shadow"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Approve Feedback
                    </button>
                </div>
            </div>

            <!-- Bottom Progress Banner (Mirroring the Dashboard streak & XP) -->
            <div
                class="mt-4 flex items-center justify-between rounded-lg border border-border/60 bg-background px-3 py-2 text-xs"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                    >
                        <Flame
                            class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                        />
                        7-Day Streak
                    </span>
                    <span class="text-border">|</span>
                    <span class="text-[11px] font-semibold text-[#D97757]">
                        +35 XP Earned
                    </span>
                </div>
                <span class="text-[11px] font-medium text-muted-foreground">
                    Level 12 Learner
                </span>
            </div>
        </div>

        <!-- Floating Accent Pill: Next Lesson Insight -->
        <div
            class="pointer-events-none absolute -bottom-4 -left-3 z-20 flex items-center gap-2 rounded-xl border border-border/80 bg-card/95 px-3.5 py-2 shadow-lg backdrop-blur-sm sm:-left-6"
        >
            <div
                class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#D97757]/15 text-[#D97757]"
            >
                <Lightbulb class="h-3.5 w-3.5" />
            </div>
            <div class="text-[11px]">
                <p class="font-semibold text-foreground">Next Lesson Insight</p>
                <p class="text-muted-foreground">
                    Class ready for quadratic formula
                </p>
            </div>
        </div>
    </div>
</template>
