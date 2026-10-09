<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight, Check } from 'lucide-vue-next';
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
            :initial="
                prefersReducedMotion ? { opacity: 0 } : { opacity: 0, y: 16 }
            "
            :animate="{ opacity: 1, y: 0 }"
            :transition="
                prefersReducedMotion
                    ? { duration: 0.2, easing: 'ease-out' }
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
                    class="flex h-5 w-5 items-center justify-center rounded-full bg-brand/15 text-brand"
                >
                    <ChatAiOrb
                        size="status"
                        animate-idle
                        color="var(--color-brand)"
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
                The fluid formative assessment platform by KOAMISHIN — where
                student practice directly informs tomorrow’s lesson.
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
                <div
                    class="relative z-10 overflow-hidden rounded-2xl border border-border/80 bg-card shadow-[0_24px_48px_rgba(0,0,0,0.08),0_2px_6px_rgba(0,0,0,0.05)] transition-all sm:rounded-3xl"
                >
                    <!-- Inner Product Split Canvas -->
                    <div
                        class="grid grid-cols-1 gap-y-8 p-6 sm:p-10 lg:grid-cols-2 lg:gap-x-12"
                    >
                        <!-- Left: Live Student Response -->
                        <div class="space-y-4">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Student Response
                            </p>
                            <p
                                class="text-lg leading-snug font-medium text-foreground sm:text-xl"
                            >
                                What are the solutions to
                                <span class="font-mono">x² − 7x + 12 = 0</span>?
                            </p>
                            <p
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                Submitted answer —
                                <span class="font-medium text-foreground"
                                    >x = 3, x = 4</span
                                >
                                · auto-scored correct in under a second.
                            </p>
                        </div>

                        <!-- Right: Echo Draft with Teacher Approval -->
                        <div
                            class="space-y-4 border-t border-border/60 pt-8 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-12"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Echo Feedback Draft
                            </p>
                            <p
                                class="text-lg leading-snug font-medium text-foreground sm:text-xl"
                            >
                                “Spot-on factorization logic. Clear
                                identification of roots using the zero-product
                                property.”
                            </p>
                            <div class="flex items-center gap-4 pt-1">
                                <button
                                    type="button"
                                    @click="isHeroApproved = !isHeroApproved"
                                    class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium transition-opacity active:scale-[0.98]"
                                    :class="
                                        isHeroApproved
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-foreground hover:opacity-70'
                                    "
                                >
                                    <Check
                                        v-if="isHeroApproved"
                                        class="h-4 w-4"
                                    />
                                    <span>{{
                                        isHeroApproved
                                            ? 'Approved by teacher'
                                            : 'Approve draft'
                                    }}</span>
                                </button>
                                <span class="text-xs text-muted-foreground"
                                    >Teachers approve every AI draft</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <p
                    class="mt-4 text-center font-sans text-xs tracking-[-0.01em] text-muted-foreground"
                >
                    Questions, answers, and feedback — reviewed by a teacher
                    before they reach students.
                </p>
            </div>
        </Motion>
    </section>
</template>
