<script setup lang="ts">
import {
    Check,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Flame,
    GraduationCap,
    ListChecks,
    ShieldCheck,
    TrendingUp,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

withDefaults(
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

type StudioTab = 'builder' | 'review' | 'mastery';

const activeTab = ref<StudioTab>('builder');
const isApprovedInReview = ref(false);

const tabs = [
    {
        id: 'builder' as const,
        label: 'Assessment Builder',
        icon: ListChecks,
    },
    {
        id: 'review' as const,
        label: 'Echo Teacher Review',
        icon: ShieldCheck,
    },
    {
        id: 'mastery' as const,
        label: 'Mastery & Follow-Up',
        icon: TrendingUp,
    },
];
</script>

<template>
    <section
        id="features"
        class="welcome-studio scroll-mt-32 border-b border-border/60 py-16 sm:py-24"
        :class="{ 'lite-motion': prefersReducedMotion }"
        aria-labelledby="studio-heading"
    >
        <!-- Section Header: Clean Google Display Sans -->
        <div class="mx-auto max-w-3xl px-4 text-center">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3.5 py-1 text-xs font-semibold text-[#D97757]"
            >
                The Classroom Workflow
            </span>
            <h2
                id="studio-heading"
                class="mt-4 font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
            >
                Every assessment informs tomorrow’s lesson.
            </h2>
            <p
                class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed font-normal text-muted-foreground sm:text-base"
            >
                Stop losing evenings to grading stacks. LSI turns student
                responses into immediate formative feedback and automated lesson
                recommendations.
            </p>
        </div>

        <!-- Google Material 3 Filter Chips -->
        <div class="mt-8 flex justify-center px-4 sm:mt-10">
            <div
                class="inline-flex flex-wrap items-center justify-center gap-2 rounded-full border border-border/70 bg-secondary/30 p-1.5"
                role="tablist"
                aria-label="Classroom studio features"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.id"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full px-5 py-2 text-xs font-medium transition-all active:scale-[0.98] sm:text-sm"
                    :class="
                        activeTab === tab.id
                            ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                            : 'border border-transparent bg-transparent text-muted-foreground hover:bg-card/70 hover:text-foreground'
                    "
                    @click="activeTab = tab.id"
                >
                    <component
                        :is="tab.icon"
                        class="h-4 w-4"
                        :class="
                            activeTab === tab.id
                                ? 'text-primary-foreground'
                                : 'text-muted-foreground'
                        "
                    />
                    <span>{{ tab.label }}</span>
                </button>
            </div>
        </div>

        <!-- Studio Workspace Canvas -->
        <div class="mx-auto mt-8 max-w-4xl px-4 sm:px-6">
            <div
                class="surface-card relative overflow-hidden rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-8"
            >
                <!-- TAB 1: ITEM STUDIO & AUTO-SCORING -->
                <div v-if="activeTab === 'builder'" class="space-y-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-4"
                    >
                        <div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3 py-0.5 font-mono text-[11px] font-semibold text-[#D97757]"
                            >
                                DepEd Competency · M8AL-IIa-1
                            </span>
                            <h3
                                class="mt-2.5 font-sans text-xl font-semibold text-foreground sm:text-2xl"
                            >
                                Quadratic Equations & Factorization
                            </h3>
                        </div>
                        <span
                            class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                        >
                            Auto-Scoring Active
                        </span>
                    </div>

                    <!-- Question editor preview -->
                    <div
                        class="rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5"
                    >
                        <div
                            class="flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span
                                >Item 3 of 15 · Objective Multiple Choice</span
                            >
                            <span class="font-medium text-foreground"
                                >Standard Form: ax² + bx + c = 0</span
                            >
                        </div>
                        <p
                            class="mt-2 text-sm font-semibold text-foreground sm:text-base"
                        >
                            What are the solutions to the equation x² - 7x + 12
                            = 0?
                        </p>

                        <!-- Answer choices with diagnostic tags -->
                        <div class="mt-4 space-y-2">
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/60 bg-card px-3.5 py-2.5 text-xs"
                            >
                                <span>A. x = 2, x = 3</span>
                                <span
                                    class="font-mono text-[10px] text-muted-foreground"
                                    >Distractor · Factor sum error</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between rounded-xl border border-emerald-500/50 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
                            >
                                <span class="flex items-center gap-2">
                                    <CheckCircle2
                                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                                    />
                                    B. x = 3, x = 4 (Correct)
                                </span>
                                <span class="font-mono text-[10px]"
                                    >Zero-product property matched</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/60 bg-card px-3.5 py-2.5 text-xs"
                            >
                                <span>C. x = -3, x = -4</span>
                                <span
                                    class="font-mono text-[10px] text-muted-foreground"
                                    >Distractor · Sign error</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Footer telemetry -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-3 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1.5">
                            <Clock class="h-3.5 w-3.5 text-[#D97757]" />
                            Objective items scored in real-time on student
                            submit
                        </span>
                        <span class="font-medium text-foreground">
                            Curriculum Tagged ✓
                        </span>
                    </div>
                </div>

                <!-- TAB 2: ECHO TEACHER REVIEW -->
                <div v-else-if="activeTab === 'review'" class="space-y-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-4"
                    >
                        <div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3 py-0.5 text-[11px] font-semibold text-[#D97757]"
                            >
                                Teacher Review Boundary · Nonce-Protected
                            </span>
                            <h3
                                class="mt-2.5 font-sans text-xl font-semibold text-foreground sm:text-2xl"
                            >
                                Human-in-the-Loop AI Feedback
                            </h3>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition-colors"
                            :class="
                                isApprovedInReview
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                            "
                        >
                            {{
                                isApprovedInReview
                                    ? 'Dispatched to Gradebook ✓'
                                    : 'Awaiting Teacher Review'
                            }}
                        </span>
                    </div>

                    <!-- Response & Feedback Card -->
                    <div
                        class="space-y-4 rounded-2xl border border-border/70 bg-secondary/25 p-4 sm:p-5"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D97757]/15 text-[10px] font-bold text-[#D97757]"
                                >
                                    MS
                                </div>
                                <span class="font-semibold text-foreground"
                                    >Maria Santos</span
                                >
                                <span class="text-muted-foreground"
                                    >· Section Diamond</span
                                >
                            </div>
                            <span
                                class="font-mono text-[11px] text-muted-foreground"
                                >Item 4 Essay</span
                            >
                        </div>
                        <p
                            class="rounded-xl border border-border/50 bg-card p-3.5 text-xs text-foreground/90 italic sm:text-sm"
                        >
                            “The roots are x = 3 and x = 4 because when
                            multiplied together (-3) * (-4) gives +12 and added
                            gives -7.”
                        </p>

                        <!-- Echo draft suggestion -->
                        <div
                            class="rounded-xl border border-[#D97757]/30 bg-[#D97757]/[0.05] p-4"
                        >
                            <div
                                class="flex items-center justify-between text-xs"
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
                                    Echo Drafted Feedback
                                </span>
                                <span
                                    class="font-mono text-[11px] text-[#D97757]"
                                    >Rubric: 5/5 Points</span
                                >
                            </div>
                            <p
                                class="mt-2 text-xs leading-relaxed text-foreground sm:text-sm"
                            >
                                Spot-on factorization logic and clear root
                                identification. Explicitly mention the
                                zero-product property in your next recitation!
                            </p>
                        </div>
                    </div>

                    <!-- Interactive Approval Actions -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            Zero autonomous writes. Every remark requires
                            teacher signature.
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-border/80 px-4 py-1.5 text-xs font-medium text-foreground hover:bg-secondary/40 active:scale-[0.97]"
                                @click="isApprovedInReview = false"
                            >
                                Edit Text
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold text-white shadow-xs transition-all active:scale-[0.97]"
                                :class="
                                    isApprovedInReview
                                        ? 'bg-emerald-600 hover:bg-emerald-700'
                                        : 'bg-[#D97757] hover:bg-[#D97757]/90'
                                "
                                @click="
                                    isApprovedInReview = !isApprovedInReview
                                "
                            >
                                <Check
                                    v-if="isApprovedInReview"
                                    class="h-3.5 w-3.5"
                                />
                                <CheckCircle2 v-else class="h-3.5 w-3.5" />
                                {{
                                    isApprovedInReview
                                        ? 'Approved & Sent ✓'
                                        : 'Approve & Release (1-Click)'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: MASTERY & FOLLOW-UP -->
                <div v-else class="space-y-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-4"
                    >
                        <div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3 py-0.5 text-[11px] font-semibold text-[#D97757]"
                            >
                                Davao Central College · Section Diamond
                            </span>
                            <h3
                                class="mt-2.5 font-sans text-xl font-semibold text-foreground sm:text-2xl"
                            >
                                Real-Time Section Understanding
                            </h3>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                        >
                            <Flame
                                class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                            />
                            7-Day Class Streak
                        </span>
                    </div>

                    <!-- Diagnostic Mastery Progress -->
                    <div
                        class="space-y-4 rounded-2xl border border-border/70 bg-secondary/20 p-4 sm:p-5"
                    >
                        <div>
                            <div
                                class="flex items-center justify-between text-xs sm:text-sm"
                            >
                                <span class="font-semibold text-foreground"
                                    >Section Factorization Mastery</span
                                >
                                <span class="font-bold text-[#D97757]"
                                    >88% Class Average</span
                                >
                            </div>
                            <div
                                class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="h-full rounded-full bg-[#D97757] transition-all"
                                    style="width: 88%"
                                />
                            </div>
                        </div>

                        <!-- Actionable next lesson recommendation -->
                        <div
                            class="flex items-start gap-3 rounded-xl border border-border/60 bg-card p-4"
                        >
                            <GraduationCap
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#D97757]"
                            />
                            <div class="text-xs sm:text-sm">
                                <span class="font-semibold text-foreground"
                                    >Teacher Action Item for Tomorrow</span
                                >
                                <p class="mt-1 text-muted-foreground">
                                    6 students transposed negative signs on Item
                                    4. Spend 8 minutes reviewing sign rules
                                    before advancing to Quadratic Formula.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer actions: Library Hub & Reviewer -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-3 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1.5">
                            <FileSpreadsheet
                                class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                            />
                            1-Click DepEd Quarterly Form 137 / SF9 grade export
                        </span>
                        <span class="font-semibold text-[#D97757]">
                            Library Hub Reviewer Ready →
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
