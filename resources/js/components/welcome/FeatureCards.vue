<script setup lang="ts">
import { FileText, MessageSquare, TrendingUp } from 'lucide-vue-next';

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

const features = [
    {
        icon: FileText,
        eyebrow: '01',
        title: 'Assessments',
        description: 'Create quizzes and assignments that fit your class.',
    },
    {
        icon: MessageSquare,
        eyebrow: '02',
        title: 'Feedback',
        description:
            'Review, adjust, and share feedback before learners see it.',
    },
    {
        icon: TrendingUp,
        eyebrow: '03',
        title: 'Class progress',
        description: 'Keep track of what needs attention over time.',
    },
];
</script>

<template>
    <section
        id="features"
        class="welcome-features scroll-mt-32 border-y border-border/70 py-16 sm:py-20"
        :class="{ 'lite-motion': prefersReducedMotion }"
        aria-labelledby="features-heading"
    >
        <div
            class="mb-10 flex flex-col gap-3 sm:mb-12 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-primary uppercase"
                >
                    The essentials
                </p>
                <h2
                    id="features-heading"
                    class="mt-3 max-w-2xl font-serif text-3xl leading-tight tracking-[-0.03em] text-foreground sm:text-4xl"
                >
                    Everything around an assessment, in one place.
                </h2>
            </div>
            <p class="max-w-sm text-sm leading-relaxed text-muted-foreground">
                Keep the work simple for teachers and the next step clear for
                learners.
            </p>
        </div>

        <div
            class="grid gap-px overflow-hidden rounded-2xl border border-border/70 bg-border/70 md:grid-cols-3"
        >
            <article
                v-for="feature in features"
                :key="feature.title"
                class="flex min-h-56 flex-col justify-between bg-background p-6 sm:p-8"
            >
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary text-foreground"
                    >
                        <component
                            :is="feature.icon"
                            class="h-5 w-5"
                            aria-hidden="true"
                        />
                    </div>
                    <span class="text-xs font-medium text-muted-foreground">{{
                        feature.eyebrow
                    }}</span>
                </div>
                <div class="mt-10">
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ feature.title }}
                    </h3>
                    <p
                        class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ feature.description }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
