<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight, Check, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
        auth: { user: any };
        dashboard: () => string;
        login: () => string;
        register: () => string;
        isBooted?: boolean;
        prefersReducedMotion?: boolean;
        branding?: {
            name?: string;
            tagline?: string;
            logoUrl?: string | null;
            accentColor?: string;
        };
    }>(),
    {
        isBooted: true,
        prefersReducedMotion: false,
        branding: undefined,
    },
);

const isHeroApproved = ref(true);
</script>

<template>
    <section
        id="top"
        aria-labelledby="welcome-heading"
        data-hero-priority="high"
        fetchpriority="high"
        class="welcome-hero relative flex flex-col items-center border-b border-border/60 pt-6 pb-16 text-center sm:pt-10 sm:pb-24 lg:pt-12 lg:pb-28"
    >
        <!-- Centered Main Content Area -->
        <Motion
            :initial="prefersReducedMotion ? false : { opacity: 0, y: 16 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="
                prefersReducedMotion
                    ? { duration: 0 }
                    : { duration: 0.5, easing: [0.23, 1, 0.32, 1] }
            "
            class="relative z-10 mx-auto flex w-full max-w-4xl flex-col items-center px-4"
        >
            <!-- Bendy-Style Assistant Status Chip -->
            <a
                href="#interactive-echo"
                class="group mb-5 inline-flex items-center gap-2.5 rounded-full border border-border/80 bg-card py-1.5 pr-3.5 pl-2 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
            >
                <div
                    class="flex h-5 w-5 items-center justify-center rounded-full bg-[#D97757]/15 text-[#D97757]"
                >
                    <ChatAiOrb
                        size="status"
                        animate-idle
                        color="#D97757"
                        class="h-3.5 w-3.5"
                    />
                </div>
                <span class="font-medium tracking-tight text-foreground">
                    Meet Echo · Intelligent Study Companion
                </span>
                <span
                    class="font-mono text-[10px] text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                    >→</span
                >
            </a>

            <!-- Bendy-Style Confident Display Headline -->
            <h1
                id="welcome-heading"
                class="max-w-3xl font-sans text-4xl leading-[1.08] font-medium tracking-[-0.035em] text-balance text-foreground sm:text-5xl md:text-6xl lg:text-[4.25rem]"
            >
                Make every assessment count.
            </h1>

            <!-- Purposeful Minimalist Lede -->
            <p
                class="mt-4 max-w-[42ch] text-base leading-relaxed font-normal text-balance text-muted-foreground sm:text-lg md:text-xl"
            >
                The fluid formative assessment platform for DepEd classrooms —
                where student practice directly informs tomorrow’s lesson.
            </p>

            <!-- Bendy Signature Action Row -->
            <div
                class="mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row"
            >
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="group inline-flex min-h-11 w-full items-center justify-center gap-2.5 rounded-full bg-foreground px-7 text-sm font-medium text-background shadow-xs transition-all hover:opacity-90 active:scale-[0.98] sm:w-auto"
                >
                    Open dashboard
                    <ArrowRight
                        class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="group inline-flex min-h-11 w-full items-center justify-center gap-2.5 rounded-full bg-foreground px-7 text-sm font-medium text-background shadow-xs transition-all hover:opacity-90 active:scale-[0.98] sm:w-auto"
                >
                    Create free account
                    <ArrowRight
                        class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
                <Link
                    v-if="!auth.user"
                    :href="login()"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-border/80 bg-card px-6 text-sm font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98] sm:w-auto"
                >
                    Log in
                </Link>
            </div>

            <!-- Bendy Centerpiece Application Frame (.figure) -->
            <div class="relative mt-12 w-full max-w-4xl text-left sm:mt-16">
                <!-- Subtle Ambient Glow -->
                <div
                    class="pointer-events-none absolute -inset-4 z-0 rounded-3xl bg-gradient-to-b from-[#D97757]/10 via-transparent to-transparent blur-2xl"
                    aria-hidden="true"
                ></div>

                <div
                    class="relative z-10 overflow-hidden rounded-2xl border border-border/80 bg-card/95 shadow-[0_24px_48px_rgba(0,0,0,0.14),0_2px_6px_rgba(0,0,0,0.08)] backdrop-blur-sm transition-all sm:rounded-3xl"
                >
                    <!-- macOS Window Top Bar -->
                    <div
                        class="flex items-center justify-between border-b border-border/70 bg-secondary/35 px-4 py-3 sm:px-6"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="h-3 w-3 rounded-full bg-border/90"
                            ></span>
                            <span
                                class="h-3 w-3 rounded-full bg-border/90"
                            ></span>
                            <span
                                class="h-3 w-3 rounded-full bg-border/90"
                            ></span>
                            <span
                                class="ml-3 hidden font-mono text-[11px] text-muted-foreground sm:inline-block"
                            >
                                lsi.koamishin.com / grade-8-math / diamond
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 font-mono text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                Live Assessment · Section Diamond
                            </span>
                        </div>
                    </div>

                    <!-- Inner Product Split Canvas -->
                    <div
                        class="grid grid-cols-1 divide-y divide-border/60 p-4 sm:p-6 lg:grid-cols-2 lg:divide-x lg:divide-y-0"
                    >
                        <!-- Left: Live Student Response -->
                        <div class="space-y-3 pb-4 lg:pr-6 lg:pb-0">
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span class="font-mono"
                                    >Item 3 of 15 · Quadratic Equations</span
                                >
                                <span
                                    class="rounded bg-secondary/80 px-2 py-0.5 font-mono text-[10px]"
                                >
                                    Auto-Scored 0s
                                </span>
                            </div>
                            <p class="text-sm font-medium text-foreground">
                                What are the solutions to
                                <span class="font-mono">x² - 7x + 12 = 0</span>?
                            </p>
                            <div
                                class="flex items-center justify-between rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3.5 py-2.5 text-xs"
                            >
                                <span
                                    class="font-medium text-emerald-700 dark:text-emerald-300"
                                >
                                    ✓ Student submitted: x = 3, x = 4
                                </span>
                                <span
                                    class="font-mono text-[10px] text-emerald-600 dark:text-emerald-400"
                                >
                                    100% Correct
                                </span>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Objective items auto-grade instantaneously with
                                zero manual cross-referencing.
                            </p>
                        </div>

                        <!-- Right: Echo Draft with Teacher Approval -->
                        <div class="space-y-3 pt-4 lg:pt-0 lg:pl-6">
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span
                                    class="flex items-center gap-1.5 font-medium text-[#D97757]"
                                >
                                    <Sparkles class="h-3.5 w-3.5" />
                                    Echo Formative Feedback Draft
                                </span>
                                <span
                                    class="font-mono text-[10px] text-muted-foreground"
                                >
                                    Rubric: 5/5 Points
                                </span>
                            </div>
                            <p
                                class="text-xs leading-relaxed text-foreground sm:text-sm"
                            >
                                “Spot-on factorization logic. Clear
                                identification of roots using the zero-product
                                property.”
                            </p>
                            <div class="flex items-center justify-between pt-1">
                                <div
                                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                >
                                    <ShieldCheck
                                        class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <span>Teacher-in-the-Loop</span>
                                </div>
                                <button
                                    type="button"
                                    @click="isHeroApproved = !isHeroApproved"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3.5 py-1 text-xs font-medium transition-all active:scale-95"
                                    :class="
                                        isHeroApproved
                                            ? 'bg-emerald-600 text-white'
                                            : 'border border-border/80 bg-secondary/80 text-foreground hover:bg-secondary'
                                    "
                                >
                                    <Check
                                        v-if="isHeroApproved"
                                        class="h-3.5 w-3.5"
                                    />
                                    <span>{{
                                        isHeroApproved
                                            ? 'Approved by Teacher ✓'
                                            : 'Click to Approve'
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Minimalist Bendy Caption -->
                <p
                    class="mt-4 text-center font-sans text-xs tracking-[-0.01em] text-muted-foreground"
                >
                    Formative assessment meets instant human-in-the-loop review.
                    Teachers retain 100% approval authority.
                </p>
            </div>
        </Motion>
    </section>
</template>
