<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import HeroDemoWindow from '@/components/welcome/HeroDemoWindow.vue';

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
</script>

<template>
    <section
        id="top"
        aria-labelledby="welcome-heading"
        data-hero-priority="high"
        fetchpriority="high"
        class="welcome-hero relative flex flex-col items-center border-b border-border/60 pt-6 pb-16 text-center sm:pt-10 sm:pb-24 lg:pt-12 lg:pb-28"
    >
        <Motion
            :initial="false"
            :animate="{ opacity: 1 }"
            :transition="
                prefersReducedMotion
                    ? { duration: 0.2, easing: 'ease-out' }
                    : { duration: 0.45, easing: [0.23, 1, 0.32, 1] }
            "
            class="relative z-10 mx-auto flex w-full max-w-6xl flex-col items-center px-4 sm:px-6 lg:px-8 xl:max-w-7xl"
        >
            <!-- Echo pill -->
            <a
                href="#how-it-works"
                style="--stagger-index: 0"
                class="hero-rise group mb-5 inline-flex items-center gap-2.5 rounded-full border border-border/80 bg-card py-1.5 pr-3.5 pl-2 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
            >
                <span
                    class="flex h-5 w-5 items-center justify-center rounded-full bg-brand/15 text-base leading-none text-brand"
                >
                    <ChatAiOrb
                        size="inline"
                        animate-idle
                        color="var(--color-brand)"
                    />
                </span>
                <span class="font-medium tracking-tight text-foreground">
                    Meet Echo · Intelligent Study Companion
                </span>
                <span
                    class="font-mono text-[10px] text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                    >→</span
                >
            </a>

            <!-- Headline: word-by-word lift, orb lands as the closing mark -->
            <h1
                id="welcome-heading"
                class="max-w-4xl font-sans text-4xl leading-[1.08] font-medium tracking-[-0.035em] text-balance text-foreground sm:text-5xl md:text-6xl lg:text-[4.5rem]"
                style="perspective: 1200px"
            >
                <span class="sr-only">Make every assessment count.</span>
                <span aria-hidden="true">
                    <span class="hero-word mr-[0.25em]" style="--word-index: 0"
                        >Make</span
                    >
                    <span class="hero-word mr-[0.25em]" style="--word-index: 1"
                        >every</span
                    >
                    <span class="hero-word mr-[0.25em]" style="--word-index: 2"
                        >assessment</span
                    >
                    <span class="hero-word" style="--word-index: 3">count</span>
                    <span
                        class="hero-orb-in ml-[0.16em] align-baseline text-[0.5em] leading-none"
                        ><ChatAiOrb
                            size="inline"
                            state="micro"
                            :delay="600"
                            color="var(--color-brand)"
                    /></span>
                </span>
            </h1>

            <!-- Lede -->
            <p
                style="--stagger-index: 1"
                class="hero-rise mt-5 max-w-[50ch] text-base leading-relaxed font-normal text-balance text-muted-foreground sm:text-lg md:text-xl"
            >
                The fluid formative assessment platform by KOAMISHIN — where
                student practice directly informs tomorrow’s lesson.
            </p>

            <!-- Actions -->
            <div
                style="--stagger-index: 2"
                class="hero-rise mt-8 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row"
            >
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="group inline-flex min-h-12 w-full items-center justify-center gap-2.5 rounded-full bg-foreground px-8 text-sm font-medium text-background shadow-xs transition-all hover:opacity-90 active:scale-[0.98] sm:w-auto"
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
                    class="group inline-flex min-h-12 w-full items-center justify-center gap-2.5 rounded-full bg-foreground px-8 text-sm font-medium text-background shadow-xs transition-all hover:opacity-90 active:scale-[0.98] sm:w-auto"
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
                    class="inline-flex min-h-12 w-full items-center justify-center rounded-full border border-border/80 bg-card px-7 text-sm font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98] sm:w-auto"
                >
                    Log in
                </Link>
            </div>

            <!-- Scripted product vignette -->
            <div
                class="relative mt-12 w-full max-w-6xl text-left sm:mt-16 xl:max-w-7xl"
            >
                <HeroDemoWindow
                    :prefers-reduced-motion="prefersReducedMotion"
                />

                <p
                    style="--stagger-index: 3"
                    class="hero-rise mt-4 text-center font-sans text-xs tracking-[-0.01em] text-muted-foreground"
                >
                    Questions, answers, and feedback — reviewed by a teacher
                    before they reach students.
                </p>
            </div>
        </Motion>
    </section>
</template>
