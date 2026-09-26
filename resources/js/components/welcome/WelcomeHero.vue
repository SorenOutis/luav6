<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight, Compass } from 'lucide-vue-next';
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
</script>

<template>
    <section
        id="top"
        aria-labelledby="welcome-heading"
        data-hero-priority="high"
        fetchpriority="high"
        class="welcome-hero relative flex flex-col items-center border-b border-border/60 pt-4 pb-14 text-center sm:pt-6 sm:pb-20 lg:pt-8 lg:pb-28"
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
            <!-- Google Material 3 Assistant Chip -->
            <a
                href="#interactive-echo"
                class="group mb-3.5 inline-flex items-center gap-2.5 rounded-full border border-border/80 bg-card py-1.5 pr-4 pl-2 text-xs font-medium text-foreground shadow-xs transition-all hover:bg-secondary/50 active:scale-[0.98] sm:mb-4.5"
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
                    class="font-mono text-[10px] text-[#D97757] transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                    >→</span
                >
            </a>

            <!-- Confident Google-Style Display Headline -->
            <h1
                id="welcome-heading"
                class="max-w-3xl font-sans text-3xl leading-[1.1] font-semibold tracking-tight text-foreground sm:text-5xl sm:leading-[1.04] md:text-6xl lg:text-[4.75rem]"
            >
                Make every assessment count.
            </h1>

            <!-- Purposeful Subtitle -->
            <p
                class="mt-3 max-w-2xl text-sm leading-relaxed font-normal text-muted-foreground sm:mt-4 sm:text-base md:text-lg"
            >
                The formative assessment platform built for DepEd classrooms —
                where student practice directly informs tomorrow’s lesson.
            </p>

            <!-- Clean Google-Style Pill Action Buttons -->
            <div
                class="mt-6 flex w-full flex-col items-center justify-center gap-3 sm:mt-8 sm:w-auto sm:flex-row"
            >
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-primary px-8 text-sm font-semibold text-primary-foreground shadow-xs transition-all hover:shadow-md active:scale-[0.98] sm:w-auto sm:text-base"
                >
                    Open dashboard
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[#D97757] px-8 text-sm font-semibold text-white shadow-xs transition-all hover:bg-[#D97757]/90 hover:shadow-md active:scale-[0.98] sm:w-auto sm:text-base"
                >
                    Create free account
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <a
                    href="#interactive-echo"
                    class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full border border-border/80 bg-card px-7 text-sm font-medium text-foreground transition-all hover:bg-secondary/50 hover:shadow-xs active:scale-[0.98] sm:w-auto sm:text-base"
                >
                    <Compass class="h-4 w-4 text-[#D97757]" />
                    Explore interactive demo
                </a>
            </div>
        </Motion>
    </section>
</template>
