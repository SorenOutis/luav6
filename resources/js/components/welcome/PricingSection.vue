<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Check, X, ArrowRight, Sparkles, Star, Crown, HelpCircle } from 'lucide-vue-next';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
}>();

const pricingRef = ref<HTMLElement | null>(null);
const isYearly = ref(false);
let gsapCtx: gsap.Context | null = null;

interface PricingFeature {
    name: string;
    tooltip?: string;
    free: boolean | string;
    pro: boolean | string;
    enterprise: boolean | string;
}

type TierId = 'free' | 'pro' | 'enterprise';

const flatFeatures = computed(() =>
    featureGroups.flatMap(g => g.features),
);

const tiers = computed(() => [
    {
        id: 'free' as TierId,
        name: 'Free',
        subtitle: 'Getting started',
        price: 0,
        period: '/mo',
        description: 'Everything you need to get started with the LUAV Learning Engine.',
        icon: Sparkles,
        gradient: 'from-zinc-500/20 to-zinc-600/10',
        cta: props.auth.user ? 'Get Started Free' : 'Join Free',
        href: props.auth.user ? props.dashboard() : props.register(),
        featured: false,
        highlight: 'Best for individual students',
    },
    {
        id: 'pro' as TierId,
        name: 'Pro',
        subtitle: 'For serious learners',
        price: isYearly.value ? 24 : 30,
        period: isYearly.value ? '/yr' : '/mo',
        description: 'Advanced tools, AI feedback, and deeper insights for motivated students.',
        icon: Star,
        gradient: 'from-primary/30 to-primary/10',
        cta: 'Start Pro Trial',
        href: props.auth.user ? props.dashboard() : props.register(),
        featured: true,
        highlight: 'Most popular',
    },
    {
        id: 'enterprise' as TierId,
        name: 'Enterprise',
        subtitle: 'For institutions',
        price: isYearly.value ? 40 : 50,
        period: isYearly.value ? '/yr' : '/mo',
        description: 'Full classroom deployment with analytics, priority support, and custom branding.',
        icon: Crown,
        gradient: 'from-amber-500/20 to-amber-600/10',
        cta: 'Contact Sales',
        href: '#contact',
        featured: false,
        highlight: 'Best for schools & districts',
    },
]);

const featureGroups: { group: string; features: PricingFeature[] }[] = [
    {
        group: 'Core Platform',
        features: [
            { name: 'Exam & Quiz Creation', free: true, pro: true, enterprise: true },
            { name: 'Multiple Question Types', free: 'Up to 3', pro: 'Up to 10', enterprise: 'Unlimited' },
            { name: 'AI-Powered Feedback', free: false, pro: true, enterprise: true },
            { name: 'Essay Grading (AI)', free: false, pro: '50/mo', enterprise: 'Unlimited' },
            { name: 'Course & Lesson System', free: true, pro: true, enterprise: true },
            { name: 'Assignment Submissions', free: true, pro: true, enterprise: true },
        ],
    },
    {
        group: 'Analytics & Progress',
        features: [
            { name: 'Dashboard & Stats', free: true, pro: true, enterprise: true },
            { name: 'Gamification (XP, Levels, Streaks)', free: true, pro: true, enterprise: true },
            { name: 'Learning Maps', free: false, pro: true, enterprise: true },
            { name: 'Advanced Reporting', free: false, pro: true, enterprise: true },
            { name: 'Grade Analytics', free: false, pro: false, enterprise: true },
            { name: 'Section Performance Overview', free: false, pro: false, enterprise: true },
        ],
    },
    {
        group: 'Community & Engagement',
        features: [
            { name: 'Anonymous Feedback (NGL)', free: true, pro: true, enterprise: true },
            { name: 'Leaderboards', free: false, pro: true, enterprise: true },
            { name: 'Badge & Award System', free: true, pro: true, enterprise: true },
            { name: 'Seasonal Events & Challenges', free: false, pro: true, enterprise: true },
            { name: 'Custom Badges', free: false, pro: false, enterprise: true },
        ],
    },
    {
        group: 'Administration',
        features: [
            { name: 'Student Management', free: false, pro: true, enterprise: true },
            { name: 'Role-Based Access', free: false, pro: false, enterprise: true },
            { name: 'Custom Branding', free: false, pro: false, enterprise: true },
            { name: 'Priority Support', free: false, pro: false, enterprise: true },
            { name: 'Dedicated Account Manager', free: false, pro: false, enterprise: true },
            { name: 'API Access', free: false, pro: true, enterprise: true },
        ],
    },
];

const showTooltip = ref<string | null>(null);

const featureValue = (feature: PricingFeature, tierId: TierId): boolean | string => {
    return feature[tierId];
};

const initAnimations = () => {
    if (!pricingRef.value) return;

    gsapCtx = gsap.context(() => {
        const badge = pricingRef.value?.querySelector('.pricing-badge');
        const heading = pricingRef.value?.querySelector('.pricing-heading');
        const desc = pricingRef.value?.querySelector('.pricing-desc');

        if (badge) {
            gsap.fromTo(badge,
                { y: 30, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.8, ease: 'expo.out',
                    scrollTrigger: { trigger: badge, start: 'top 85%', toggleActions: 'play none none none' },
                },
            );
        }
        if (heading) {
            gsap.fromTo(heading,
                { y: 40, opacity: 0 },
                { y: 0, opacity: 1, duration: 1, delay: 0.1, ease: 'expo.out',
                    scrollTrigger: { trigger: heading, start: 'top 85%', toggleActions: 'play none none none' },
                },
            );
        }
        if (desc) {
            gsap.fromTo(desc,
                { y: 40, opacity: 0 },
                { y: 0, opacity: 1, duration: 1, delay: 0.2, ease: 'expo.out',
                    scrollTrigger: { trigger: desc, start: 'top 85%', toggleActions: 'play none none none' },
                },
            );
        }

        const cards = pricingRef.value?.querySelectorAll('.pricing-tier-card');
        if (cards?.length) {
            gsap.fromTo(cards,
                { y: 80, opacity: 0, scale: 0.95 },
                {
                    y: 0, opacity: 1, scale: 1,
                    duration: 1, stagger: 0.15, ease: 'expo.out',
                    scrollTrigger: {
                        trigger: cards[0],
                        start: 'top 80%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }

        const rows = pricingRef.value?.querySelectorAll('.feature-row');
        if (rows?.length) {
            gsap.fromTo(rows,
                { y: 20, opacity: 0 },
                {
                    y: 0, opacity: 1,
                    duration: 0.6, stagger: 0.04, ease: 'power2.out',
                    scrollTrigger: {
                        trigger: rows[0],
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }
    }, pricingRef.value);
};

onMounted(() => {
    nextTick(() => {
        initAnimations();
        ScrollTrigger.refresh();
    });
});

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <section
        ref="pricingRef"
        id="pricing"
        class="reveal-section scroll-mt-32 mt-32"
    >
        <div class="flex flex-col gap-3 mb-12">
            <div class="pricing-badge inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 self-start">
                <Sparkles class="h-3.5 w-3.5 text-primary" />
                <span class="text-sm font-medium text-primary">Pricing</span>
            </div>
            <h2 class="pricing-heading text-3xl font-bold tracking-tight lg:text-5xl">
                Simple, transparent
                <span class="text-primary">pricing</span>
            </h2>
            <p class="pricing-desc max-w-xl text-muted-foreground">
                Choose the plan that fits your learning journey. No hidden fees, no surprises.
            </p>

            <div class="pricing-desc flex items-center gap-3 mt-4">
                <button
                    type="button"
                    @click="isYearly = false"
                    class="relative px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                    :class="!isYearly ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground'"
                >
                    Monthly
                </button>
                <button
                    type="button"
                    @click="isYearly = true"
                    class="relative px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                    :class="isYearly ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground'"
                >
                    Yearly
                    <span class="absolute -top-2 -right-2 flex h-5 items-center rounded-full bg-primary px-2 text-[9px] font-bold text-primary-foreground tracking-wider">
                        -20%
                    </span>
                </button>
            </div>
        </div>

        <!-- Tier Cards -->
        <div class="grid gap-6 lg:grid-cols-3 lg:gap-4 xl:gap-6">
            <div
                v-for="tier in tiers"
                :key="tier.id"
                class="pricing-tier-card group relative flex flex-col overflow-hidden rounded-2xl border border-border/20 bg-card p-8 transition-all duration-500 hover:shadow-2xl hover:-translate-y-1 lg:p-10"
                :class="[
                    tier.featured
                        ? 'border-primary/40 lg:scale-105 lg:shadow-xl lg:shadow-primary/5'
                        : '',
                ]"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-b opacity-0 transition-opacity duration-700 group-hover:opacity-100"
                    :class="tier.gradient"
                ></div>

                <div
                    v-if="tier.featured"
                    class="absolute top-0 right-0 rounded-bl-xl bg-primary px-4 py-1.5 text-[9px] font-black tracking-[0.2em] text-primary-foreground uppercase shadow-lg"
                >
                    {{ tier.highlight }}
                </div>

                <div class="relative z-10 mb-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-border/30 bg-background/50 backdrop-blur-sm"
                            :class="{ 'border-primary/30': tier.featured }"
                        >
                            <component :is="tier.icon" class="h-5 w-5" :class="tier.featured ? 'text-primary' : 'text-muted-foreground'" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold">{{ tier.name }}</h3>
                            <p class="text-xs text-muted-foreground">{{ tier.subtitle }}</p>
                        </div>
                    </div>

                    <div class="flex items-baseline gap-1 mt-4">
                        <span class="text-4xl font-black tracking-tight">
                            {{ tier.price === 0 ? 'Free' : `$${tier.price}` }}
                        </span>
                        <span v-if="tier.price > 0" class="text-sm font-medium text-muted-foreground">{{ tier.period }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                        {{ tier.description }}
                    </p>
                </div>

                <div class="relative z-10 mb-8">
                    <Link
                        :href="tier.href"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-bold tracking-wide transition-all duration-300"
                        :class="tier.featured
                            ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 hover:bg-primary/90'
                            : 'border border-border/30 text-foreground hover:border-primary/40 hover:bg-primary/5'"
                    >
                        {{ tier.cta }}
                        <ArrowRight class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
                    </Link>
                </div>

                <div class="relative z-10 flex-1 space-y-3">
                    <p class="text-[9px] font-black tracking-[0.2em] text-muted-foreground/50 uppercase">
                        What's included
                    </p>
                    <ul class="space-y-2.5">
                        <li
                            v-for="feature in flatFeatures.slice(0, 6)"
                            :key="feature.name"
                            class="flex items-start gap-2.5 text-xs"
                        >
                            <Check
                                v-if="featureValue(feature, tier.id) === true || typeof featureValue(feature, tier.id) === 'string'"
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary"
                            />
                            <X
                                v-else
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/30"
                            />
                            <span :class="featureValue(feature, tier.id) === false ? 'text-muted-foreground/40' : 'text-muted-foreground'">
                                {{ feature.name }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Full Feature Breakdown -->
        <div class="mt-20">
            <div class="flex flex-col gap-3 mb-10 text-center">
                <h3 class="text-2xl font-bold tracking-tight lg:text-3xl">
                    Full feature
                    <span class="text-primary">breakdown</span>
                </h3>
                <p class="text-sm text-muted-foreground">
                    Compare every feature across all plans to find the right fit.
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-border/10 bg-card">
                <div class="grid grid-cols-12 gap-0 border-b border-border/10 bg-muted/30 px-4 py-4 lg:px-6">
                    <div class="col-span-5 lg:col-span-6">
                        <span class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase">Feature</span>
                    </div>
                    <div class="col-span-7 grid grid-cols-3 gap-2 text-center lg:col-span-6">
                        <span class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase">Free</span>
                        <span class="text-[10px] font-bold tracking-[0.15em] text-primary uppercase">Pro</span>
                        <span class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase">Enterprise</span>
                    </div>
                </div>

                <div v-for="(group, gIdx) in featureGroups" :key="group.group">
                    <div class="border-b border-border/5 bg-muted/10 px-4 py-3 lg:px-6">
                        <span class="text-[11px] font-bold tracking-[0.15em] text-muted-foreground/50 uppercase">
                            {{ group.group }}
                        </span>
                    </div>

                    <div
                        v-for="(feature, fIdx) in group.features"
                        :key="feature.name"
                        class="feature-row grid grid-cols-12 gap-0 border-b border-border/5 px-4 py-3.5 transition-colors hover:bg-muted/15 lg:px-6"
                        :class="{ 'last:border-b-0': gIdx === featureGroups.length - 1 && fIdx === group.features.length - 1 }"
                    >
                        <div class="col-span-5 flex items-center gap-1.5 lg:col-span-6">
                            <span class="text-xs font-medium text-foreground/80">
                                {{ feature.name }}
                            </span>
                            <button
                                v-if="feature.tooltip"
                                type="button"
                                @click="showTooltip = showTooltip === feature.name ? null : feature.name"
                                @mouseenter="showTooltip = feature.name"
                                @mouseleave="showTooltip = null"
                                class="relative shrink-0"
                            >
                                <HelpCircle class="h-3 w-3 text-muted-foreground/40 hover:text-muted-foreground/70 transition-colors" />
                                <div
                                    v-if="showTooltip === feature.name"
                                    class="absolute left-5 top-1/2 -translate-y-1/2 z-20 w-48 rounded-lg border border-border/20 bg-popover px-3 py-2 text-xs text-popover-foreground shadow-xl backdrop-blur-xl"
                                >
                                    {{ feature.tooltip }}
                                </div>
                            </button>
                        </div>
                        <div class="col-span-7 grid grid-cols-3 gap-2 text-center">
                            <div
                                v-for="tierId in (['free', 'pro', 'enterprise'] as TierId[])"
                                :key="tierId"
                                class="flex items-center justify-center"
                            >
                                <Check
                                    v-if="featureValue(feature, tierId) === true"
                                    class="h-4 w-4 text-primary"
                                />
                                <X
                                    v-if="featureValue(feature, tierId) === false"
                                    class="h-4 w-4 text-muted-foreground/30"
                                />
                                <span
                                    v-if="typeof featureValue(feature, tierId) === 'string'"
                                    class="text-[11px] font-semibold text-foreground/70"
                                >
                                    {{ featureValue(feature, tierId) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 text-center">
                <p class="mb-4 text-sm text-muted-foreground">
                    Not sure which plan fits? All plans include a 14-day free trial.
                </p>
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-8 py-4 text-sm font-bold text-primary-foreground shadow-xl shadow-primary/20 transition-all hover:shadow-2xl hover:shadow-primary/30 hover:bg-primary/90"
                >
                    Start your free trial
                    <ArrowRight class="h-4 w-4" />
                </Link>
                <Link
                    v-else
                    :href="register()"
                    class="inline-flex items-center gap-2 rounded-xl bg-foreground px-8 py-4 text-sm font-bold text-background transition-all hover:bg-primary hover:text-primary-foreground"
                >
                    Create free account
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </div>
        </div>
    </section>
</template>
