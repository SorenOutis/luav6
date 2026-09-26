<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    Flame,
    Lightbulb,
    ShieldCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

const isApproved = ref(false);

function toggleApproval() {
    isApproved.value = !isApproved.value;
}
</script>

<template>
    <div
        class="relative mx-auto w-full max-w-2xl text-left transition-all duration-300"
    >
        <!-- Google Classroom / Material 3 Card Surface -->
        <div
            class="surface-card relative overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs transition-shadow hover:shadow-md sm:p-7"
        >
            <!-- Header bar with curriculum competency chips -->
            <div
                class="flex flex-wrap items-center justify-between gap-2.5 border-b border-border/60 pb-3.5 sm:pb-4"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-foreground"
                    >
                        Grade 8 · Mathematics
                    </span>
                    <span
                        class="rounded-full bg-[#D97757]/10 px-2.5 py-1 font-mono text-[11px] font-semibold text-[#D97757]"
                    >
                        DepEd M8AL-IIa-1
                    </span>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition-colors"
                    :class="
                        isApproved
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                            : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                    "
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="
                            isApproved
                                ? 'bg-emerald-600 dark:bg-emerald-400'
                                : 'animate-pulse bg-emerald-600 dark:bg-emerald-400'
                        "
                    />
                    {{ isApproved ? '25/28 Completed' : '24/28 Reviewed' }}
                </span>
            </div>

            <!-- Formative Assessment Question -->
            <div class="mt-4 sm:mt-5">
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Question 3 · Formative Assessment
                    </span>
                    <span
                        class="rounded-full bg-secondary/80 px-2 py-0.5 text-xs font-medium text-foreground"
                    >
                        5 pts Rubric
                    </span>
                </div>
                <p
                    class="mt-2 text-sm font-semibold text-foreground sm:text-base"
                >
                    Explain how to solve x² - 7x + 12 = 0 using factoring.
                </p>
            </div>

            <!-- Student Answer Row -->
            <div
                class="mt-4 rounded-2xl border border-border/70 bg-secondary/30 p-3.5 sm:p-4"
            >
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D97757]/15 text-[11px] font-bold text-[#D97757]"
                        >
                            MS
                        </div>
                        <span class="font-medium text-foreground">
                            Maria Santos
                        </span>
                    </div>
                    <span class="text-[11px] text-muted-foreground">
                        Section Diamond · DCCP
                    </span>
                </div>
                <p
                    class="mt-2 text-xs leading-relaxed text-foreground/90 italic sm:text-sm"
                >
                    “Find factors of 12 that add to -7: (-3) and (-4). So (x -
                    3)(x - 4) = 0, which means x = 3 and x = 4.”
                </p>
            </div>

            <!-- AI-Drafted Feedback with Teacher Approval Guarantee -->
            <div
                class="mt-4 rounded-2xl border p-4 transition-colors sm:p-5"
                :class="
                    isApproved
                        ? 'border-emerald-500/40 bg-emerald-500/[0.04]'
                        : 'border-[#D97757]/30 bg-[#D97757]/[0.05]'
                "
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 text-xs"
                >
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
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold transition-colors"
                        :class="
                            isApproved
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'bg-[#D97757]/10 text-[#D97757]'
                        "
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        {{
                            isApproved
                                ? 'Verified by Teacher'
                                : 'Teacher Review Required'
                        }}
                    </span>
                </div>
                <p
                    class="mt-2 text-xs leading-relaxed text-foreground sm:text-sm"
                >
                    Spot-on factorization and root extraction. Great
                    demonstration of the zero-product property.
                </p>
                <div
                    class="mt-3.5 flex items-center justify-between border-t border-border/50 pt-3"
                >
                    <span class="text-xs text-muted-foreground">
                        Score:
                        <strong class="text-foreground">5 / 5 pts</strong>
                    </span>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold shadow-xs transition-all active:scale-[0.97]"
                        :class="
                            isApproved
                                ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                : 'bg-[#D97757] text-white hover:bg-[#D97757]/90'
                        "
                        @click="toggleApproval"
                    >
                        <Check v-if="isApproved" class="h-3.5 w-3.5" />
                        <CheckCircle2 v-else class="h-3.5 w-3.5" />
                        {{ isApproved ? 'Approved ✓' : 'Approve Feedback' }}
                    </button>
                </div>
            </div>

            <!-- Bottom Progress Banner -->
            <div
                class="mt-4 flex items-center justify-between rounded-2xl border border-border/60 bg-secondary/20 px-4 py-2.5 text-xs"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400"
                    >
                        <Flame
                            class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                        />
                        7-Day Streak
                    </span>
                    <span class="text-border">|</span>
                    <span class="font-semibold text-[#D97757]">
                        +35 XP Earned
                    </span>
                </div>
                <span class="hidden text-muted-foreground sm:inline">
                    Level 12 Learner · Rank #1
                </span>
            </div>
        </div>

        <!-- Floating Material Pill -->
        <div
            class="pointer-events-none absolute -bottom-3.5 left-4 z-20 hidden items-center gap-2 rounded-full border border-border/80 bg-card px-4 py-2 shadow-md sm:flex"
        >
            <div
                class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D97757]/15 text-[#D97757]"
            >
                <Lightbulb class="h-3.5 w-3.5" />
            </div>
            <div class="text-[11px]">
                <span class="font-semibold text-foreground"
                    >Teacher Action Item:
                </span>
                <span class="text-muted-foreground"
                    >Advance to Quadratic Formula</span
                >
            </div>
        </div>
    </div>
</template>
