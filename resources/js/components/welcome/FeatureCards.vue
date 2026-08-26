<script setup lang="ts">
import { Check, ShieldCheck, UserRound } from 'lucide-vue-next';

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

const principles = [
    {
        icon: Check,
        title: 'Useful before impressive',
        description: 'Every feature should make classroom work clearer.',
    },
    {
        icon: UserRound,
        title: 'Teacher control',
        description:
            'Recommendations stay reviewable, adjustable, and yours to approve.',
    },
    {
        icon: ShieldCheck,
        title: 'Privacy by default',
        description:
            'Schools keep ownership of their content and learner data.',
    },
];
</script>

<template>
    <section
        id="features"
        class="welcome-principles scroll-mt-32 border-b border-border/70 py-14 sm:py-18"
        :class="{ 'lite-motion': prefersReducedMotion }"
        aria-labelledby="principles-heading"
    >
        <h2
            id="principles-heading"
            class="text-center font-serif text-3xl tracking-[-0.035em] text-foreground sm:text-4xl"
        >
            What guides the work.
        </h2>

        <div
            class="mt-10 grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
        >
            <article
                v-for="principle in principles"
                :key="principle.title"
                class="flex gap-5 px-2 py-7 sm:px-6 md:flex-col md:py-8 lg:px-8"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-foreground/70 text-foreground"
                >
                    <component
                        :is="principle.icon"
                        class="h-5 w-5"
                        stroke-width="1.5"
                        aria-hidden="true"
                    />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        {{ principle.title }}
                    </h3>
                    <p
                        class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ principle.description }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
