<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, School, UserRound } from 'lucide-vue-next';

const props = defineProps<{
    auth: { user: any };
    dashboard: () => string;
    register: () => string;
    isCoarsePointer?: boolean;
    prefersReducedMotion?: boolean;
}>();

const tiers = [
    {
        name: 'Starter',
        audience: 'For one teacher',
        price: 'Free',
        description:
            'The essentials for creating assessments and reviewing responses.',
        icon: UserRound,
        features: [
            'Create exams and quizzes',
            'Assignment submissions',
            'Class progress view',
        ],
        featured: false,
    },
    {
        name: 'Classroom',
        audience: 'For a class or school',
        price: 'Custom',
        description:
            'A fuller workflow for feedback, reporting, and school support.',
        icon: School,
        features: [
            'AI-assisted feedback',
            'Advanced reporting',
            'School support',
        ],
        featured: true,
    },
    {
        name: 'District',
        audience: 'For schools working together',
        price: 'Custom',
        description:
            'Shared visibility and administration across multiple schools.',
        icon: Building2,
        features: ['Role-based access', 'Custom branding', 'Priority support'],
        featured: false,
    },
];
</script>

<template>
    <section
        id="pricing"
        class="welcome-pricing scroll-mt-32 border-b border-border/70 py-20 sm:py-28"
        aria-labelledby="pricing-heading"
    >
        <div
            class="mb-10 flex flex-col gap-3 sm:mb-12 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-medium tracking-[0.16em] text-primary uppercase"
                >
                    Pricing
                </p>
                <h2
                    id="pricing-heading"
                    class="mt-4 max-w-2xl font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl"
                >
                    Start small. Grow with your school.
                </h2>
            </div>
            <p class="max-w-sm text-sm leading-relaxed text-muted-foreground">
                Begin with the essentials, then talk with us when your whole
                school is ready.
            </p>
        </div>

        <div
            class="grid divide-y divide-border/70 border-y border-border/70 md:grid-cols-3 md:divide-x md:divide-y-0"
        >
            <article
                v-for="tier in tiers"
                :key="tier.name"
                class="relative flex min-h-[360px] flex-col px-1 py-8 sm:px-6 md:py-9 lg:px-8"
                :class="tier.featured ? 'bg-secondary/35' : ''"
            >
                <div class="flex items-start justify-between gap-4">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-foreground/70 text-foreground"
                    >
                        <component
                            :is="tier.icon"
                            class="h-5 w-5"
                            stroke-width="1.5"
                            aria-hidden="true"
                        />
                    </div>
                    <span
                        v-if="tier.featured"
                        class="rounded-full bg-brand/15 px-2.5 py-0.5 text-xs font-semibold text-brand"
                        >Most popular for schools</span
                    >
                </div>
                <h3
                    class="mt-8 font-sans text-2xl font-medium tracking-[-0.03em] text-foreground"
                >
                    {{ tier.name }}
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ tier.audience }}
                </p>
                <p
                    class="mt-6 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground tabular-nums"
                >
                    {{ tier.price }}
                </p>
                <p
                    class="mt-3 max-w-xs text-xs leading-relaxed text-muted-foreground"
                >
                    {{ tier.description }}
                </p>
                <ul class="mt-6 space-y-2.5 text-xs text-muted-foreground">
                    <li
                        v-for="feature in tier.features"
                        :key="feature"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand"
                        ></span>
                        <span>{{ feature }}</span>
                    </li>
                </ul>
                <div class="mt-auto pt-8">
                    <Link
                        v-if="tier.price === 'Free' && !auth.user"
                        :href="props.register()"
                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-full border border-border/80 bg-card px-4 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
                    >
                        Create free account
                        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                    <Link
                        v-else-if="tier.price === 'Free'"
                        :href="props.dashboard()"
                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-full border border-border/80 bg-card px-4 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
                    >
                        Open dashboard
                        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                    <a
                        v-else
                        href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-full px-4 text-xs font-medium shadow-xs transition-all active:scale-[0.98]"
                        :class="
                            tier.featured
                                ? 'bg-foreground text-background hover:opacity-90'
                                : 'border border-border/80 bg-card text-foreground hover:border-foreground/30'
                        "
                    >
                        Contact sales
                        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </a>
                </div>
            </article>
        </div>
    </section>
</template>
