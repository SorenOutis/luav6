<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Building2, School, UserRound } from 'lucide-vue-next';
import RevealGroup from '@/components/welcome/RevealGroup.vue';

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
        <RevealGroup class="mx-auto max-w-3xl px-4 text-center">
            <span
                class="rv font-mono text-xs font-medium tracking-wider text-brand uppercase"
                :style="{ '--rv-i': 0 }"
            >
                Pricing
            </span>
            <h2
                id="pricing-heading"
                class="rv mt-4 font-sans text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
                :style="{ '--rv-i': 1 }"
            >
                Start small. Grow with your school.
            </h2>
            <p
                class="rv mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base"
                :style="{ '--rv-i': 2 }"
            >
                Begin with the essentials, then talk with us when your whole
                school is ready.
            </p>
        </RevealGroup>

        <RevealGroup
            :stagger="90"
            class="mx-auto mt-10 grid max-w-6xl gap-4 px-4 sm:mt-12 sm:px-6 md:grid-cols-3 lg:gap-6 xl:max-w-7xl"
        >
            <article
                v-for="(tier, index) in tiers"
                :key="tier.name"
                class="rv relative flex flex-col rounded-3xl border bg-card p-6 shadow-xs transition-all duration-300 hover:shadow-md sm:p-8"
                :style="{ '--rv-i': index }"
                :class="
                    tier.featured
                        ? 'border-foreground/40 ring-1 ring-foreground/20'
                        : 'border-border/80'
                "
            >
                <div class="flex items-start justify-between gap-4">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-border/80 bg-secondary/30 text-foreground"
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
                        class="rounded-full bg-foreground px-2.5 py-0.5 text-xs font-semibold text-background"
                        >Most popular</span
                    >
                </div>
                <h3
                    class="mt-6 font-sans text-xl font-medium tracking-[-0.03em] text-foreground"
                >
                    {{ tier.name }}
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ tier.audience }}
                </p>
                <p
                    class="mt-5 font-sans text-3xl font-semibold tracking-[-0.035em] text-foreground tabular-nums"
                >
                    {{ tier.price }}
                </p>
                <p class="mt-3 text-xs leading-relaxed text-muted-foreground">
                    {{ tier.description }}
                </p>
                <ul class="mt-5 space-y-2.5 text-xs text-muted-foreground">
                    <li
                        v-for="feature in tier.features"
                        :key="feature"
                        class="flex items-start gap-2"
                    >
                        <span
                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-foreground"
                        ></span>
                        <span>{{ feature }}</span>
                    </li>
                </ul>
                <div class="mt-auto pt-8">
                    <Link
                        v-if="tier.price === 'Free' && !auth.user"
                        :href="props.register()"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-border/80 bg-card px-4 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
                    >
                        Create free account
                        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                    <Link
                        v-else-if="tier.price === 'Free'"
                        :href="props.dashboard()"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-border/80 bg-card px-4 text-xs font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 active:scale-[0.98]"
                    >
                        Open dashboard
                        <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                    <a
                        v-else
                        href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full px-4 text-xs font-medium shadow-xs transition-all active:scale-[0.98]"
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
        </RevealGroup>
    </section>
</template>
