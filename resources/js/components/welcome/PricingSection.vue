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
        class="welcome-pricing scroll-mt-32 border-b border-border/70 py-16 sm:py-20"
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
                    class="mt-4 max-w-2xl font-serif text-3xl leading-tight tracking-[-0.04em] text-foreground sm:text-4xl"
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
                        class="text-xs font-medium text-primary"
                        >For schools</span
                    >
                </div>
                <h3
                    class="mt-8 font-serif text-2xl tracking-[-0.03em] text-foreground"
                >
                    {{ tier.name }}
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ tier.audience }}
                </p>
                <p
                    class="mt-7 font-serif text-3xl tracking-[-0.03em] text-foreground"
                >
                    {{ tier.price }}
                </p>
                <p
                    class="mt-3 max-w-xs text-sm leading-relaxed text-muted-foreground"
                >
                    {{ tier.description }}
                </p>
                <ul class="mt-6 space-y-2 text-sm text-muted-foreground">
                    <li
                        v-for="feature in tier.features"
                        :key="feature"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"
                        ></span>
                        <span>{{ feature }}</span>
                    </li>
                </ul>
                <div class="mt-auto pt-8">
                    <Link
                        v-if="tier.price === 'Free' && !auth.user"
                        :href="props.register()"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-border px-4 text-sm font-semibold text-foreground transition-colors hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        Create a free account
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <Link
                        v-else-if="tier.price === 'Free'"
                        :href="props.dashboard()"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-border px-4 text-sm font-semibold text-foreground transition-colors hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        Open dashboard
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                    <a
                        v-else
                        href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        :class="
                            tier.featured
                                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                : 'border border-border text-foreground hover:border-primary hover:bg-primary/5'
                        "
                    >
                        Contact sales
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>
            </article>
        </div>
    </section>
</template>
