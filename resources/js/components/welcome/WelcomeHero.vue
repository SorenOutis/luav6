<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { ArrowRight } from 'lucide-vue-next';
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
            <p
                class="mb-7 text-xs font-medium tracking-[0.16em] text-primary uppercase"
            >
                A school-ready learning platform
            </p>
            <h1
                id="welcome-heading"
                class="max-w-2xl font-serif text-[3.25rem] leading-[0.95] tracking-[-0.055em] text-foreground sm:text-6xl lg:text-[5.6rem]"
            >
                Make every assessment count.
            </h1>
            <p
                class="mt-8 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
            >
                Create assessments, review responses, and plan what to teach
                next, with less work for teachers.
            </p>
            <p class="mt-5 text-sm font-medium text-foreground/80">
                Teacher-controlled. Built for schools.
            </p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Open dashboard
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Create a free account
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    :href="login()"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
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
                <div
                    aria-hidden="true"
                    class="absolute -inset-8 -z-10 rounded-full bg-primary/[0.035] blur-3xl"
                ></div>
                <AssessmentArtifact />
            </div>
        </Motion>
    </section>
</template>
