<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Check,
    X,
    ArrowRight,
    Sparkles,
    Star,
    Crown,
    HelpCircle,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
    isCoarsePointer?: boolean;
    prefersReducedMotion?: boolean;
}>();

const pricingRef = ref<HTMLElement | null>(null);
let gsapCtx: gsap.Context | null = null;

interface PricingFeature {
    name: string;
    tooltip?: string;
    free: boolean | string;
    pro: boolean | string;
    enterprise: boolean | string;
}

type TierId = 'free' | 'pro' | 'enterprise';

const flatFeatures = computed(() => featureGroups.flatMap((g) => g.features));

const tiers = computed(() => [
    {
        id: 'free' as TierId,
        name: 'Starter',
        subtitle: 'Get started',
        price: 0,
        period: '',
        description:
            'Everything a teacher needs to try LSI — exams, assignments, and instant AI feedback.',
        icon: Sparkles,
        gradient: 'from-zinc-500/20 via-zinc-500/10 to-zinc-600/10',
        cta: props.auth.user ? 'Open Dashboard' : 'Create free account',
        href: props.auth.user ? props.dashboard() : props.register(),
        featured: false,
        highlight: 'Best to get started',
    },
    {
        id: 'pro' as TierId,
        name: 'Classroom',
        subtitle: 'For a school',
        price: 'Custom',
        period: '',
        description:
            'Roll LSI out across your classroom or whole school with engagement tools, analytics, and support.',
        icon: Star,
        gradient: 'from-primary/30 via-primary/15 to-primary/10',
        cta: 'Contact Sales',
        href: '#contact',
        featured: true,
        highlight: 'Most popular for schools',
    },
    {
        id: 'enterprise' as TierId,
        name: 'District',
        subtitle: 'For institutions',
        price: 'Custom',
        period: '',
        description:
            'A district-wide rollout with role-based access, custom branding, and a dedicated success manager.',
        icon: Crown,
        gradient: 'from-amber-500/20 via-amber-500/10 to-amber-600/10',
        cta: 'Contact Sales',
        href: '#contact',
        featured: false,
        highlight: 'Best for institutions',
    },
]);

const featureGroups: { group: string; features: PricingFeature[] }[] = [
    {
        group: 'Core Platform',
        features: [
            {
                name: 'Exam & Quiz Creation',
                free: true,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Multiple Question Types',
                free: 'Up to 3',
                pro: 'Up to 10',
                enterprise: 'Unlimited',
            },
            {
                name: 'AI-Powered Feedback',
                free: false,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Essay Grading (AI)',
                free: false,
                pro: '50/mo',
                enterprise: 'Unlimited',
            },
            {
                name: 'Course & Lesson System',
                free: true,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Assignment Submissions',
                free: true,
                pro: true,
                enterprise: true,
            },
        ],
    },
    {
        group: 'Analytics & Progress',
        features: [
            {
                name: 'Dashboard & Stats',
                free: true,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Student engagement tools',
                free: true,
                pro: true,
                enterprise: true,
            },
            { name: 'Learning Maps', free: false, pro: true, enterprise: true },
            {
                name: 'Advanced Reporting',
                free: false,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Grade Analytics',
                free: false,
                pro: false,
                enterprise: true,
            },
            {
                name: 'Section Performance Overview',
                free: false,
                pro: false,
                enterprise: true,
            },
        ],
    },
    {
        group: 'School tools & engagement',
        features: [
            {
                name: 'Section leaderboards',
                free: false,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Achievement & recognition',
                free: true,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Learning campaigns & events',
                free: false,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Custom achievements',
                free: false,
                pro: false,
                enterprise: true,
            },
        ],
    },
    {
        group: 'Administration',
        features: [
            {
                name: 'Student Management',
                free: false,
                pro: true,
                enterprise: true,
            },
            {
                name: 'Role-Based Access',
                free: false,
                pro: false,
                enterprise: true,
            },
            {
                name: 'Custom Branding',
                free: false,
                pro: false,
                enterprise: true,
            },
            {
                name: 'Priority Support',
                free: false,
                pro: false,
                enterprise: true,
            },
            {
                name: 'Dedicated Account Manager',
                free: false,
                pro: false,
                enterprise: true,
            },
            { name: 'API Access', free: false, pro: true, enterprise: true },
        ],
    },
];

const showTooltip = ref<string | null>(null);

const featureValue = (
    feature: PricingFeature,
    tierId: TierId,
): boolean | string => {
    return feature[tierId];
};

const initAnimations = () => {
    if (!pricingRef.value || props.prefersReducedMotion) return;

    gsapCtx = gsap.context(() => {
        const badge = pricingRef.value?.querySelector('.pricing-badge');
        const heading = pricingRef.value?.querySelector('.pricing-heading');
        const desc = pricingRef.value?.querySelector('.pricing-desc');

        if (badge) {
            gsap.fromTo(
                badge,
                { y: 30, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.8,
                    ease: 'expo.out',
                    scrollTrigger: {
                        trigger: badge,
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }
        if (heading) {
            gsap.fromTo(
                heading,
                { y: 40, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 1,
                    delay: 0.1,
                    ease: 'expo.out',
                    scrollTrigger: {
                        trigger: heading,
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }
        if (desc) {
            gsap.fromTo(
                desc,
                { y: 40, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 1,
                    delay: 0.2,
                    ease: 'expo.out',
                    scrollTrigger: {
                        trigger: desc,
                        start: 'top 85%',
                        toggleActions: 'play none none none',
                    },
                },
            );
        }

        const cards = pricingRef.value?.querySelectorAll('.pricing-tier-card');
        if (cards?.length) {
            gsap.fromTo(
                cards,
                { y: 80, opacity: 0, scale: 0.95 },
                {
                    y: 0,
                    opacity: 1,
                    scale: 1,
                    duration: 1,
                    stagger: 0.15,
                    ease: 'expo.out',
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
            gsap.fromTo(
                rows,
                { y: 20, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.6,
                    stagger: 0.04,
                    ease: 'power2.out',
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
        class="reveal-section mt-20 scroll-mt-32 sm:mt-32"
        :class="{ 'lite-motion': prefersReducedMotion }"
    >
        <div class="mb-12 flex flex-col gap-3">
            <div
                class="pricing-badge inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
            >
                <Sparkles class="h-3.5 w-3.5 text-primary" />
                <span class="text-sm font-medium text-primary">Pricing</span>
            </div>
            <h2
                class="pricing-heading text-3xl font-bold tracking-tight lg:text-5xl"
            >
                Transparent plans for schools
                <span class="text-primary">& districts</span>
            </h2>
            <p class="pricing-desc max-w-xl text-muted-foreground">
                From a free trial for one teacher to a full district rollout —
                no hidden fees, no surprises.
            </p>
        </div>

        <!-- Tier Cards -->
        <div class="grid gap-6 lg:grid-cols-3 lg:gap-4 xl:gap-6">
            <div
                v-for="tier in tiers"
                :key="tier.id"
                :class="[
                    'pricing-tier-card group relative flex flex-col overflow-hidden rounded-2xl border bg-card p-8 transition-all duration-500 hover:-translate-y-1.5 hover:shadow-2xl lg:p-10',
                    tier.featured
                        ? 'tier-pro featured-glow border-primary/40 lg:scale-105 lg:shadow-xl lg:shadow-primary/5'
                        : tier.id === 'enterprise'
                          ? 'tier-enterprise border-border/20'
                          : 'tier-free border-border/20',
                ]"
            >
                <!-- Shine sweep overlay -->
                <div
                    class="shine-overlay pointer-events-none absolute inset-0 z-20 opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                ></div>

                <!-- Animated gradient background -->
                <div
                    class="animated-gradient pointer-events-none absolute inset-0 bg-gradient-to-b opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                    :class="tier.gradient"
                ></div>

                <div
                    v-if="tier.featured"
                    class="absolute top-0 right-0 z-30 rounded-bl-xl bg-primary px-4 py-1.5 text-[9px] font-black tracking-[0.2em] text-primary-foreground uppercase shadow-lg"
                >
                    {{ tier.highlight }}
                </div>

                <div class="relative z-10 mb-6">
                    <div class="mb-3 flex items-center gap-3">
                        <div
                            class="icon-container flex h-10 w-10 items-center justify-center rounded-xl border border-border/30 bg-background/50 backdrop-blur-sm transition-all duration-500 group-hover:border-primary/40 group-hover:shadow-lg group-hover:shadow-primary/5"
                            :class="{ 'border-primary/30': tier.featured }"
                        >
                            <component
                                :is="tier.icon"
                                class="icon-svg h-5 w-5 transition-all duration-500"
                                :class="
                                    tier.featured
                                        ? 'text-primary'
                                        : 'text-muted-foreground group-hover:text-primary'
                                "
                            />
                        </div>
                        <div>
                            <h3
                                class="text-lg font-bold transition-colors duration-300 group-hover:text-primary"
                            >
                                {{ tier.name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ tier.subtitle }}
                            </p>
                        </div>
                    </div>

                    <div class="price-slot mt-4 flex items-baseline gap-1">
                        <span
                            class="price-value text-4xl font-black tracking-tight tabular-nums"
                        >
                            <template v-if="tier.id === 'free'">Free</template>
                            <template v-else>{{ tier.price }}</template>
                        </span>
                        <span
                            v-if="tier.period"
                            class="period-label text-sm font-medium text-muted-foreground transition-all duration-300"
                            >{{ tier.period }}</span
                        >
                    </div>
                    <p
                        class="mt-3 text-sm leading-relaxed text-muted-foreground transition-colors duration-300 group-hover:text-foreground/80"
                    >
                        {{ tier.description }}
                    </p>
                </div>

                <div class="relative z-10 mb-8">
                    <Link
                        :href="tier.href"
                        class="cta-button inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-bold tracking-wide transition-all duration-300"
                        :class="
                            tier.featured
                                ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90 hover:shadow-xl hover:shadow-primary/30'
                                : 'border border-border/30 text-foreground hover:border-primary/40 hover:bg-primary/5 hover:shadow-lg hover:shadow-primary/5'
                        "
                    >
                        {{ tier.cta }}
                        <ArrowRight
                            class="h-4 w-4 transition-all duration-300 group-hover:translate-x-1.5 group-hover:scale-110"
                        />
                    </Link>
                </div>

                <div class="relative z-10 flex-1 space-y-3">
                    <p
                        class="text-[9px] font-black tracking-[0.2em] text-muted-foreground/50 uppercase"
                    >
                        What's included
                    </p>
                    <ul class="space-y-2.5">
                        <li
                            v-for="(feature, fIdx) in flatFeatures.slice(0, 6)"
                            :key="feature.name"
                            class="feature-list-item flex items-start gap-2.5 text-xs transition-all duration-300"
                            :style="{ transitionDelay: `${fIdx * 30}ms` }"
                        >
                            <Check
                                v-if="
                                    featureValue(feature, tier.id) === true ||
                                    typeof featureValue(feature, tier.id) ===
                                        'string'
                                "
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary transition-all duration-300 group-hover:scale-110 group-hover:text-primary/80"
                            />
                            <X
                                v-else
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/30"
                            />
                            <span
                                :class="[
                                    'transition-colors duration-300',
                                    featureValue(feature, tier.id) === false
                                        ? 'text-muted-foreground/40'
                                        : 'text-muted-foreground group-hover:text-foreground/70',
                                ]"
                            >
                                {{ feature.name }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Full Feature Breakdown -->
        <div class="mt-20">
            <div class="mb-10 flex flex-col gap-3 text-center">
                <h3 class="text-2xl font-bold tracking-tight lg:text-3xl">
                    Full feature
                    <span class="text-primary">breakdown</span>
                </h3>
                <p class="text-sm text-muted-foreground">
                    Compare every feature across all plans to find the right
                    fit.
                </p>
            </div>

            <div
                class="overflow-hidden rounded-2xl border border-border/10 bg-card"
            >
                <div
                    class="grid grid-cols-12 gap-0 border-b border-border/10 bg-muted/30 px-4 py-4 lg:px-6"
                >
                    <div class="col-span-5 lg:col-span-6">
                        <span
                            class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase"
                            >Feature</span
                        >
                    </div>
                    <div
                        class="col-span-7 grid grid-cols-3 gap-2 text-center lg:col-span-6"
                    >
                        <span
                            class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase"
                            >Starter</span
                        >
                        <span
                            class="text-[10px] font-bold tracking-[0.15em] text-primary uppercase"
                            >Classroom</span
                        >
                        <span
                            class="text-[10px] font-bold tracking-[0.15em] text-muted-foreground/60 uppercase"
                            >District</span
                        >
                    </div>
                </div>

                <div v-for="(group, gIdx) in featureGroups" :key="group.group">
                    <div
                        class="border-b border-border/5 bg-muted/10 px-4 py-3 lg:px-6"
                    >
                        <span
                            class="text-[11px] font-bold tracking-[0.15em] text-muted-foreground/50 uppercase"
                        >
                            {{ group.group }}
                        </span>
                    </div>

                    <div
                        v-for="(feature, fIdx) in group.features"
                        :key="feature.name"
                        class="feature-row grid grid-cols-12 gap-0 border-b border-border/5 px-4 py-3.5 transition-colors hover:bg-muted/15 lg:px-6"
                        :class="{
                            'last:border-b-0':
                                gIdx === featureGroups.length - 1 &&
                                fIdx === group.features.length - 1,
                        }"
                    >
                        <div
                            class="col-span-5 flex items-center gap-1.5 lg:col-span-6"
                        >
                            <span
                                class="text-xs font-medium text-foreground/80"
                            >
                                {{ feature.name }}
                            </span>
                            <button
                                v-if="feature.tooltip"
                                type="button"
                                @click="
                                    showTooltip =
                                        showTooltip === feature.name
                                            ? null
                                            : feature.name
                                "
                                @mouseenter="showTooltip = feature.name"
                                @mouseleave="showTooltip = null"
                                class="relative shrink-0"
                            >
                                <HelpCircle
                                    class="h-3 w-3 text-muted-foreground/40 transition-colors hover:text-muted-foreground/70"
                                />
                                <div
                                    v-if="showTooltip === feature.name"
                                    class="bg-popover text-popover-foreground absolute top-1/2 left-5 z-20 w-48 -translate-y-1/2 rounded-lg border border-border/20 px-3 py-2 text-xs shadow-xl backdrop-blur-xl"
                                >
                                    {{ feature.tooltip }}
                                </div>
                            </button>
                        </div>
                        <div
                            class="col-span-7 grid grid-cols-3 gap-2 text-center lg:col-span-6"
                        >
                            <div
                                v-for="tierId in [
                                    'free',
                                    'pro',
                                    'enterprise',
                                ] as TierId[]"
                                :key="tierId"
                                class="flex items-center justify-center"
                            >
                                <Check
                                    v-if="
                                        featureValue(feature, tierId) === true
                                    "
                                    class="h-4 w-4 text-primary"
                                />
                                <X
                                    v-if="
                                        featureValue(feature, tierId) === false
                                    "
                                    class="h-4 w-4 text-muted-foreground/30"
                                />
                                <span
                                    v-if="
                                        typeof featureValue(feature, tierId) ===
                                        'string'
                                    "
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
                    Not sure which plan fits? All plans include a 14-day free
                    trial.
                </p>
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-8 py-4 text-sm font-bold text-primary-foreground shadow-xl shadow-primary/20 transition-all hover:bg-primary/90 hover:shadow-2xl hover:shadow-primary/30"
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

<style scoped>
/* ─── Animated Gradient ─── */
@keyframes gradient-shift {
    0% {
        background-position: 0% 0%;
    }
    25% {
        background-position: 100% 0%;
    }
    50% {
        background-position: 100% 100%;
    }
    75% {
        background-position: 0% 100%;
    }
    100% {
        background-position: 0% 0%;
    }
}

.animated-gradient {
    background-size: 200% 200%;
    animation: gradient-shift 6s ease infinite;
}

/* ─── Shine Sweep Effect ─── */
.shine-overlay {
    background: linear-gradient(
        105deg,
        transparent 30%,
        rgba(255, 255, 255, 0.06) 40%,
        rgba(255, 255, 255, 0.12) 45%,
        rgba(255, 255, 255, 0.06) 50%,
        transparent 60%
    );
    background-size: 300% 100%;
    animation: shine-sweep 2.5s ease infinite;
}

@keyframes shine-sweep {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* ─── Featured Card Glow (box-shadow pulse) ─── */
.featured-glow {
    animation: glow-pulse 3s ease-in-out infinite;
}

@keyframes glow-pulse {
    0%,
    100% {
        box-shadow:
            0 0 8px 0 hsl(var(--primary) / 0.15),
            0 0 0 1px hsl(var(--primary) / 0.3),
            0 20px 25px -5px hsl(var(--primary) / 0.08),
            0 8px 10px -6px hsl(var(--primary) / 0.05);
    }
    50% {
        box-shadow:
            0 0 20px 4px hsl(var(--primary) / 0.2),
            0 0 0 1px hsl(var(--primary) / 0.5),
            0 20px 25px -5px hsl(var(--primary) / 0.08),
            0 8px 10px -6px hsl(var(--primary) / 0.05);
    }
}

/* Override shadow on hover — keep the glow-pulse but add the hover shadow */
.featured-glow:hover {
    animation: glow-pulse-hover 3s ease-in-out infinite !important;
}

@keyframes glow-pulse-hover {
    0%,
    100% {
        box-shadow:
            0 0 12px 2px hsl(var(--primary) / 0.25),
            0 0 0 1.5px hsl(var(--primary) / 0.5),
            0 20px 25px -5px hsl(var(--primary) / 0.3);
    }
    50% {
        box-shadow:
            0 0 28px 6px hsl(var(--primary) / 0.3),
            0 0 0 1.5px hsl(var(--primary) / 0.6),
            0 20px 25px -5px hsl(var(--primary) / 0.3);
    }
}

/* ─── Icon Container ─── */
.icon-container {
    transition:
        border-color 0.4s ease,
        box-shadow 0.4s ease,
        transform 0.4s ease;
}

.group:hover .icon-container {
    transform: scale(1.05) rotate(-3deg);
}

.group:hover .icon-svg {
    transform: scale(1.1) rotate(3deg);
}

/* ─── Feature List Items ─── */
.feature-list-item {
    transform: translateX(0);
    transition:
        transform 0.3s ease,
        color 0.3s ease;
}

.group:hover .feature-list-item {
    transform: translateX(3px);
}

.group:hover .feature-list-item:nth-child(2) {
    transition-delay: 30ms;
}
.group:hover .feature-list-item:nth-child(3) {
    transition-delay: 60ms;
}
.group:hover .feature-list-item:nth-child(4) {
    transition-delay: 90ms;
}
.group:hover .feature-list-item:nth-child(5) {
    transition-delay: 120ms;
}
.group:hover .feature-list-item:nth-child(6) {
    transition-delay: 150ms;
}

/* ─── Price Slot ─── */
.price-slot .period-label {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

.group:hover .price-slot .period-label {
    opacity: 0.8;
}

/* ─── CTA Button micro animation ─── */
.cta-button {
    position: relative;
    overflow: hidden;
}

.cta-button::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.08) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cta-button:hover::after {
    opacity: 1;
    animation: cta-shine 1.2s ease infinite;
}

@keyframes cta-shine {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* ─── Tier Cards entrance animation for the featured badge ─── */
.pricing-tier-card {
    transition:
        border-color 0.4s ease,
        box-shadow 0.5s ease,
        transform 0.4s ease,
        opacity 0.5s ease;
}

/* ─── Feature row hover (full breakdown) ─── */
.feature-row {
    transition:
        background-color 0.2s ease,
        transform 0.2s ease;
}

.feature-row:hover {
    transform: translateX(4px);
}

/* ─── Reduced motion ─── */
@media (prefers-reduced-motion: reduce) {
    .animated-gradient {
        animation: none;
    }
    .shine-overlay {
        animation: none;
        opacity: 0 !important;
    }
    .featured-glow {
        animation: none;
    }
    .cta-button::after {
        animation: none;
    }
    .group:hover .icon-container {
        transform: none;
    }
    .group:hover .icon-svg {
        transform: none;
    }
    .group:hover .feature-list-item {
        transform: none;
    }
    .feature-row:hover {
        transform: none;
    }
}

.lite-motion .animated-gradient,
.lite-motion .shine-overlay,
.lite-motion .featured-glow,
.lite-motion .cta-button::after {
    animation: none !important;
}
.lite-motion .shine-overlay {
    opacity: 0 !important;
}
.lite-motion .featured-glow {
    box-shadow: none !important;
}
</style>
