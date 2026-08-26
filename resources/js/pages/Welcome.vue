<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardList,
    MessageSquare,
    ListChecks,
} from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import PricingSection from '@/components/welcome/PricingSection.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import { useMobile } from '@/composables/useMobile';
import { dashboard, login, register } from '@/routes';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { prefersReducedMotion, isLowEndDevice } = useMobile();
const effectiveReducedMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);

const processSteps = [
    {
        number: '01',
        title: 'Create an assessment',
        description:
            'Choose questions that fit your class and publish when you are ready.',
        icon: ClipboardList,
    },
    {
        number: '02',
        title: 'Review responses',
        description:
            'See where learners are confident and where they need support.',
        icon: MessageSquare,
    },
    {
        number: '03',
        title: 'Plan the next lesson',
        description:
            'Use the evidence to assign focused follow-up and keep learning moving.',
        icon: ListChecks,
    },
];

const webSiteJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'LSI — KOAMISHIN',
    alternateName: 'LSI',
    description:
        'A school-ready learning platform that helps teachers turn assessments into clear next steps.',
};
</script>

<template>
    <Head title="LSI — KOAMISHIN | Make every assessment count" />
    <SeoHead
        description="LSI helps teachers see what learners understand, give useful feedback, and plan what to teach next."
        type="website"
        :jsonld="webSiteJsonLd"
    />

    <div
        class="welcome-root relative min-h-screen overflow-x-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[620px] bg-[radial-gradient(circle_at_78%_28%,color-mix(in_srgb,var(--color-primary)_7%,transparent),transparent_34%),radial-gradient(circle_at_20%_14%,color-mix(in_srgb,var(--color-secondary)_35%,transparent),transparent_30%)]"
        ></div>

        <WelcomeHeader
            :can-register="props.canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :is-booted="true"
        />

        <main
            class="relative z-10 mx-auto flex max-w-[1440px] flex-col px-4 pt-10 pb-16 sm:px-6 sm:pt-16 sm:pb-24 lg:px-16 lg:pt-20 lg:pb-32"
        >
            <WelcomeHero
                :can-register="props.canRegister"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
                :register="() => register().url"
                :is-booted="true"
                :prefers-reduced-motion="effectiveReducedMotion"
            />

            <section
                id="how-it-works"
                class="welcome-process scroll-mt-32 border-y border-border/70 py-16 sm:py-20"
                aria-labelledby="process-heading"
            >
                <div
                    class="mb-10 flex flex-col gap-3 sm:mb-12 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-primary uppercase"
                        >
                            How it works
                        </p>
                        <h2
                            id="process-heading"
                            class="mt-3 max-w-2xl font-serif text-3xl leading-tight tracking-[-0.03em] text-foreground sm:text-4xl"
                        >
                            From response to next lesson.
                        </h2>
                    </div>
                    <p
                        class="max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        A short, practical workflow for turning classroom
                        evidence into action.
                    </p>
                </div>

                <div
                    class="grid gap-px overflow-hidden rounded-2xl border border-border/70 bg-border/70 md:grid-cols-3"
                >
                    <article
                        v-for="step in processSteps"
                        :key="step.number"
                        class="min-h-56 bg-background p-6 sm:p-8"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary text-foreground"
                            >
                                <component
                                    :is="step.icon"
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >{{ step.number }}</span
                            >
                        </div>
                        <h3 class="mt-10 text-lg font-semibold text-foreground">
                            {{ step.title }}
                        </h3>
                        <p
                            class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ step.description }}
                        </p>
                    </article>
                </div>

                <div class="mt-8">
                    <Link
                        href="/how-it-works"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-foreground transition-colors hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        Read the full guide
                        <ArrowRight class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>
            </section>

            <FeatureCards
                class="mt-0"
                :is-coarse-pointer="isLowEndDevice"
                :prefers-reduced-motion="effectiveReducedMotion"
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :login="() => login().url"
            />

            <PricingSection
                :auth="$page.props.auth"
                :dashboard="() => dashboard().url"
                :register="() => register().url"
                :is-coarse-pointer="isLowEndDevice"
                :prefers-reduced-motion="effectiveReducedMotion"
            />

            <section
                id="contact"
                class="welcome-cta rounded-2xl bg-foreground px-6 py-12 text-background sm:px-10 sm:py-14 lg:px-16"
                aria-labelledby="cta-heading"
            >
                <div
                    class="flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="max-w-2xl">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-background/60 uppercase"
                        >
                            Ready when you are
                        </p>
                        <h2
                            id="cta-heading"
                            class="mt-3 max-w-xl font-serif text-3xl leading-tight tracking-[-0.03em] sm:text-4xl"
                        >
                            Know what to teach next.
                        </h2>
                        <p
                            class="mt-4 max-w-lg text-sm leading-relaxed text-background/70 sm:text-base"
                        >
                            Start with one class, one assessment, and a clearer
                            next step.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:min-w-56">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="dashboard().url"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-background px-5 text-sm font-semibold text-foreground transition-transform duration-150 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-background focus-visible:ring-offset-2 focus-visible:ring-offset-foreground active:scale-[0.98]"
                        >
                            Open dashboard
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <Link
                            v-else-if="props.canRegister"
                            :href="register().url"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-background px-5 text-sm font-semibold text-foreground transition-transform duration-150 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-background focus-visible:ring-offset-2 focus-visible:ring-offset-foreground active:scale-[0.98]"
                        >
                            Create a free account
                            <ArrowRight class="h-4 w-4" aria-hidden="true" />
                        </Link>
                        <a
                            href="mailto:hello@koamishin.dev?subject=LSI%20school%20pricing"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-background/35 px-5 text-sm font-medium text-background transition-colors hover:border-background hover:bg-background/10 focus-visible:ring-2 focus-visible:ring-background focus-visible:ring-offset-2 focus-visible:ring-offset-foreground"
                        >
                            Contact sales
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>
