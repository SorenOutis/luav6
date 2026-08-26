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
        class="welcome-hero relative grid items-center gap-12 overflow-hidden pb-16 lg:min-h-[620px] lg:grid-cols-[0.9fr_1.1fr] lg:gap-16 lg:pb-24"
    >
        <div class="relative z-10 max-w-2xl">
            <p
                class="mb-6 text-xs font-semibold tracking-[0.2em] text-primary uppercase"
            >
                A learning platform for schools
            </p>
            <h1
                id="welcome-heading"
                class="max-w-2xl font-serif text-5xl leading-[0.94] tracking-[-0.045em] text-foreground sm:text-6xl lg:text-8xl"
            >
                Make every assessment count.
            </h1>
            <p
                class="mt-7 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
            >
                See what learners understand, then plan what to teach next.
            </p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground transition-transform duration-150 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 active:scale-[0.98]"
                >
                    Open dashboard
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    v-else-if="canRegister"
                    :href="register()"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground transition-transform duration-150 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 active:scale-[0.98]"
                >
                    Create a free account
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    :href="login()"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Log in
                </Link>
            </div>

            <p class="mt-6 text-xs text-muted-foreground">
                Teacher-controlled · built for schools
            </p>
        </div>

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
            <div class="relative w-full max-w-[620px]">
                <div
                    aria-hidden="true"
                    class="absolute -inset-8 -z-10 rounded-full bg-primary/[0.04] blur-3xl"
                ></div>
                <AssessmentArtifact />
            </div>
        </Motion>
    </section>
</template>
