<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    Flame,
    GraduationCap,
    Lightbulb,
    ShieldCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

type ArtifactTab = 'review' | 'classwork' | 'grades';

const activeTab = ref<ArtifactTab>('review');
const isApproved = ref(false);

function toggleApproval() {
    isApproved.value = !isApproved.value;
}
</script>

<template>
    <div
        class="classroom-workbench relative mx-auto w-full max-w-3xl text-left"
    >
        <!-- Google Classroom / Material 3 Workspace Surface -->
        <div
            class="surface-card overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm transition-all sm:rounded-3xl"
        >
            <!-- Google Classroom Top Class Header Bar -->
            <div
                class="flex flex-col justify-between gap-3 border-b border-border/70 bg-secondary/30 px-4 py-3 sm:flex-row sm:items-center sm:px-6 sm:py-3.5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#D97757]/15 text-[#D97757] sm:h-9 sm:w-9"
                    >
                        <GraduationCap class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs font-semibold text-foreground sm:text-sm"
                            >
                                Grade 8 Mathematics
                            </span>
                            <span
                                class="hidden rounded-full bg-secondary px-2 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline"
                            >
                                Section Diamond
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Davao Central College · DepEd K-12 Curriculum
                        </p>
                    </div>
                </div>

                <!-- Google-style Segmented Classroom Tabs -->
                <div
                    class="flex scrollbar-none items-center gap-1 overflow-x-auto pb-1 sm:pb-0"
                >
                    <button
                        type="button"
                        class="cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-all active:scale-[0.98]"
                        :class="
                            activeTab === 'review'
                                ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                        "
                        @click="activeTab = 'review'"
                    >
                        Echo Review (1)
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-all active:scale-[0.98]"
                        :class="
                            activeTab === 'classwork'
                                ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                        "
                        @click="activeTab = 'classwork'"
                    >
                        Classwork
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-all active:scale-[0.98]"
                        :class="
                            activeTab === 'grades'
                                ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground'
                        "
                        @click="activeTab = 'grades'"
                    >
                        Grades
                    </button>
                </div>
            </div>

            <!-- Content Area: Tab 1 - Echo AI Review -->
            <div v-if="activeTab === 'review'" class="space-y-4 p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full bg-[#D97757]/10 px-2.5 py-0.5 font-mono text-[11px] font-semibold text-[#D97757]"
                        >
                            Competency · M8AL-IIa-1
                        </span>
                        <span
                            class="hidden text-xs text-muted-foreground sm:inline"
                        >
                            Question 3 · Formative Essay
                        </span>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="
                            isApproved
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                isApproved
                                    ? 'bg-emerald-600 dark:bg-emerald-400'
                                    : 'animate-pulse bg-amber-600 dark:bg-amber-400'
                            "
                        />
                        {{ isApproved ? '25/28 Completed' : '24/28 Reviewed' }}
                    </span>
                </div>

                <!-- Assessment Question -->
                <div>
                    <h4
                        class="text-sm font-semibold text-foreground sm:text-base"
                    >
                        Explain how to solve x² - 7x + 12 = 0 using factoring.
                    </h4>
                </div>

                <!-- Student Submission Row -->
                <div
                    class="space-y-1.5 rounded-xl border border-border/70 bg-secondary/25 p-3.5 sm:p-4"
                >
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D97757]/15 text-[11px] font-bold text-[#D97757]"
                            >
                                MS
                            </div>
                            <span class="font-semibold text-foreground"
                                >Maria Santos</span
                            >
                            <span class="hidden text-muted-foreground sm:inline"
                                >· Student Submission</span
                            >
                        </div>
                        <span
                            class="font-mono text-[11px] text-muted-foreground"
                            >15m ago</span
                        >
                    </div>
                    <p
                        class="pl-8 text-xs text-foreground/90 italic sm:text-sm"
                    >
                        “Find factors of 12 that add to -7: (-3) and (-4). So (x
                        - 3)(x - 4) = 0, which means x = 3 and x = 4.”
                    </p>
                </div>

                <!-- AI Drafted Feedback Card with Live Approval -->
                <div
                    class="rounded-xl border p-3.5 transition-colors sm:p-4"
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
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
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

                    <!-- Bottom Action Bar -->
                    <div
                        class="mt-3.5 flex items-center justify-between border-t border-border/50 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Rubric Score:
                            <strong class="text-foreground">5 / 5 pts</strong>
                        </span>
                        <button
                            type="button"
                            data-testid="approve-feedback-button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold shadow-xs transition-all active:scale-[0.98]"
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

                <!-- Footer Telemetry -->
                <div
                    class="flex items-center justify-between rounded-xl border border-border/60 bg-secondary/15 px-3.5 py-2 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-2">
                        <Flame
                            class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                        />
                        Maria’s 7-Day Study Streak Active
                    </span>
                    <span class="font-medium text-foreground">
                        +35 XP on dispatch
                    </span>
                </div>
            </div>

            <!-- Content Area: Tab 2 - Classwork (Objective Builder) -->
            <div
                v-else-if="activeTab === 'classwork'"
                class="space-y-4 p-4 sm:p-6"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3"
                >
                    <div>
                        <span
                            class="rounded-full bg-[#D97757]/10 px-2.5 py-0.5 font-mono text-[11px] font-semibold text-[#D97757]"
                        >
                            DepEd M8AL-IIa-1
                        </span>
                        <h4
                            class="mt-1.5 text-sm font-semibold text-foreground sm:text-base"
                        >
                            Item 3 · Objective Multiple Choice
                        </h4>
                    </div>
                    <span
                        class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        Auto-Scored
                    </span>
                </div>
                <p class="text-xs text-foreground sm:text-sm">
                    What are the solutions to the equation x² - 7x + 12 = 0?
                </p>
                <div class="space-y-2">
                    <div
                        class="flex items-center justify-between rounded-xl border border-border/60 bg-card p-3 text-xs"
                    >
                        <span>A. x = 2, x = 3</span>
                        <span
                            class="font-mono text-[10px] text-muted-foreground"
                            >Distractor · Factor sum error</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between rounded-xl border border-emerald-500/50 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
                    >
                        <span class="flex items-center gap-2">
                            <CheckCircle2
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                            />
                            B. x = 3, x = 4 (Correct Answer)
                        </span>
                        <span class="font-mono text-[10px]"
                            >Zero-product property matched</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between rounded-xl border border-border/60 bg-card p-3 text-xs"
                    >
                        <span>C. x = -3, x = -4</span>
                        <span
                            class="font-mono text-[10px] text-muted-foreground"
                            >Distractor · Sign transposition</span
                        >
                    </div>
                </div>
            </div>

            <!-- Content Area: Tab 3 - Grades & Analytics -->
            <div v-else class="space-y-4 p-4 sm:p-6">
                <div
                    class="flex items-center justify-between border-b border-border/60 pb-3"
                >
                    <h4
                        class="text-sm font-semibold text-foreground sm:text-base"
                    >
                        Section Diamond Diagnostic Understanding
                    </h4>
                    <span class="text-sm font-bold text-[#D97757]"
                        >88% Class Average</span
                    >
                </div>
                <div>
                    <div
                        class="h-2.5 w-full overflow-hidden rounded-full bg-secondary"
                    >
                        <div
                            class="h-full rounded-full bg-[#D97757]"
                            style="width: 88%"
                        />
                    </div>
                </div>
                <div
                    class="flex items-start gap-3 rounded-xl border border-border/60 bg-secondary/20 p-4"
                >
                    <Lightbulb class="mt-0.5 h-5 w-5 shrink-0 text-[#D97757]" />
                    <div class="text-xs sm:text-sm">
                        <span class="font-semibold text-foreground"
                            >Teacher Action Item for Tomorrow</span
                        >
                        <p class="mt-1 text-muted-foreground">
                            6 students missed negative signs on Item 4. Spend 8
                            minutes reviewing sign rules before advancing to
                            Quadratic Formula.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
