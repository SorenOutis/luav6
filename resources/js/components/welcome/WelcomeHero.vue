<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import AssessmentArtifact from '@/components/welcome/AssessmentArtifact.vue';

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
        class="welcome-hero relative grid items-center gap-10 border-b border-border/70 pb-16 sm:gap-14 sm:pb-20 lg:min-h-[640px] lg:grid-cols-[1fr_0.9fr] lg:gap-20 lg:pb-24"
    >
        <Motion
            :initial="prefersReducedMotion ? false : { opacity: 0, y: 18 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="
                prefersReducedMotion
                    ? { duration: 0 }
                    : { duration: 0.55, easing: [0.23, 1, 0.32, 1] }
            "
            class="relative z-10 max-w-2xl py-4 sm:py-8"
        >
            <a
                href="#interactive-echo"
                class="group mb-6 inline-flex items-center gap-2.5 rounded-full border border-[#D97757]/25 bg-[#D97757]/[0.06] py-1 pr-4 pl-1.5 text-xs font-medium text-foreground shadow-xs backdrop-blur-sm transition-all hover:border-[#D97757]/50 hover:bg-[#D97757]/12"
            >
                <div
                    class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D97757]/15 text-[#D97757]"
                >
                    <ChatAiOrb
                        size="status"
                        animate-idle
                        color="#D97757"
                        class="h-4 w-4"
                    />
                </div>
                <span class="tracking-wide">
                    Meet Echo · Intelligent Study Companion
                </span>
                <span
                    class="font-mono text-[10px] text-[#D97757] transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                    >→</span
                >
            </a>
            <h1
                id="welcome-heading"
                class="max-w-2xl font-serif text-[3.25rem] leading-[0.95] tracking-[-0.055em] text-foreground sm:text-6xl lg:text-[5.4rem]"
            >
                Make every assessment count.
            </h1>
            <p
                class="mt-7 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
            >
                Create assessments, review responses, and plan what to teach
                next, with less work for teachers.
            </p>
            <p class="mt-4 text-sm font-medium text-foreground/80">
                Teacher-controlled · Built for schools · Human-in-the-loop AI
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Open dashboard
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#D97757] px-5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#D97757]/90 hover:shadow focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Create a free account
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    :href="login()"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border/80 px-4 text-sm font-medium text-foreground transition-colors hover:bg-secondary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Log in
                </Link>
            </div>
        </Motion>

        <Motion
            :initial="prefersReducedMotion ? false : { opacity: 0, y: 18 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="
                prefersReducedMotion
                    ? { duration: 0 }
                    : {
                          duration: 0.65,
                          easing: [0.23, 1, 0.32, 1],
                          delay: 0.08,
                      }
            "
            class="relative z-10 flex justify-center lg:justify-end"
        >
            <div class="relative w-full max-w-[520px]">
                <AssessmentArtifact />
            </div>
        </Motion>
    </section>
</template>
