<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import {
    CheckCircle2,
    ExternalLink,
    Eye,
    Package,
    Pause,
    Play,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import ShopQuickViewModal from '@/components/shop/ShopQuickViewModal.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import { dashboard, login, register } from '@/routes';
import type { MerchItem } from '@/types/merch';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        merches?: MerchItem[];
    }>(),
    {
        canRegister: true,
        merches: () => [],
    },
);

const totalInStock = computed(() => {
    return props.merches.filter((m) => !m.is_out_of_stock && m.stock > 0).length;
});

const isManuallyPaused = ref(false);
const isHovered = ref(false);
const selectedQuickViewMerch = ref<MerchItem | null>(null);
const isQuickViewOpen = ref(false);

const openQuickView = (merch: MerchItem) => {
    selectedQuickViewMerch.value = merch;
    isQuickViewOpen.value = true;
    if (marqueeTween) {
        marqueeTween.pause();
    }
};

const closeQuickView = () => {
    isQuickViewOpen.value = false;
    selectedQuickViewMerch.value = null;
    if (marqueeTween && !isManuallyPaused.value && !isHovered.value) {
        marqueeTween.resume();
    }
};

const handleQuickViewSelect = (merch: MerchItem) => {
    selectedQuickViewMerch.value = merch;
};

const carouselContainerRef = ref<HTMLElement | null>(null);
const marqueeWrapperRef = ref<HTMLElement | null>(null);
const trackRef = ref<HTMLElement | null>(null);

let marqueeTween: gsap.core.Tween | null = null;
let gsapCtx: gsap.Context | null = null;

// Normalize merches so that a single copy has at least 8 items for a continuous, seamless loop across any screen size
const normalizedMerches = computed(() => {
    const list = props.merches || [];
    if (list.length === 0) return [];
    let repeated = [...list];
    while (repeated.length < 8) {
        repeated = [...repeated, ...list];
    }
    return repeated;
});

const setupMarquee = () => {
    if (!trackRef.value || normalizedMerches.value.length === 0) {
        return;
    }

    if (marqueeTween) {
        marqueeTween.kill();
        marqueeTween = null;
    }

    gsapCtx?.revert();

    gsapCtx = gsap.context(() => {
        if (!trackRef.value) return;

        // Reset to initial coordinate
        gsap.set(trackRef.value, { xPercent: 0, x: 0 });

        // Calculate a comfortable, natural scroll duration (approx 4.2s per card)
        const duration = Math.max(22, normalizedMerches.value.length * 4.2);

        // Infinite loop moving towards negative X (right to left)
        // -50% translates exactly one identical copy of the duplicated cards
        marqueeTween = gsap.to(trackRef.value, {
            xPercent: -50,
            duration,
            ease: 'none',
            repeat: -1,
        });

        if (isManuallyPaused.value || isHovered.value) {
            marqueeTween.pause();
        }
    }, carouselContainerRef.value ?? undefined);
};

const handleMouseEnter = () => {
    isHovered.value = true;
    if (marqueeTween && !isManuallyPaused.value) {
        marqueeTween.pause();
    }
};

const handleMouseLeave = () => {
    isHovered.value = false;
    if (marqueeTween && !isManuallyPaused.value) {
        marqueeTween.resume();
    }
};

const togglePause = () => {
    isManuallyPaused.value = !isManuallyPaused.value;
    if (!marqueeTween) return;

    if (isManuallyPaused.value) {
        marqueeTween.pause();
    } else {
        if (!isHovered.value) {
            marqueeTween.resume();
        }
    }
};

watch(
    () => props.merches,
    async () => {
        await nextTick();
        setupMarquee();
    },
    { deep: true },
);

onMounted(async () => {
    await nextTick();
    setupMarquee();

    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const qvParam = urlParams.get('quickview');
        if (qvParam && props.merches && props.merches.length > 0) {
            const target =
                props.merches.find((m) => String(m.id) === qvParam) ||
                props.merches[0];
            openQuickView(target);
        }
    }
});

onBeforeUnmount(() => {
    marqueeTween?.kill();
    marqueeTween = null;
});

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <Head title="Shop - KOAMISHIN | Official Merchandise & Gear" />
    <SeoHead
        title="Shop - KOAMISHIN | Official Merchandise & Gear"
        description="Browse official KOAMISHIN apparel, accessories, and gear for educators, learners, and creators. Exclusively fulfilled on koamishin.com."
        type="website"
    />

    <div
        class="shop-root min-h-screen overflow-x-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <WelcomeHeader
            :can-register="props.canRegister"
            :auth="$page.props.auth"
            :dashboard="() => dashboard().url"
            :login="() => login().url"
            :register="() => register().url"
            :branding="$page.props.schoolBranding"
            :is-booted="true"
        />

        <main
            class="mx-auto flex max-w-[1440px] flex-col px-4 pt-4 pb-16 sm:px-6 sm:pt-10 sm:pb-24 lg:px-16 lg:pt-12 lg:pb-32"
        >
            <!-- Breadcrumbs -->
            <nav
                aria-label="Breadcrumb"
                class="flex items-center gap-2 text-xs text-muted-foreground sm:text-sm"
            >
                <Link
                    href="/"
                    class="transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Home
                </Link>
                <span class="text-muted-foreground/40" aria-hidden="true">/</span>
                <span class="font-medium text-foreground" aria-current="page">Shop</span>
            </nav>

            <!-- Hero Section -->
            <section
                class="mt-4 border-b border-border/70 pb-8 sm:mt-8 sm:pb-16"
                aria-labelledby="shop-heading"
            >
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-3 py-1 text-xs font-semibold text-[#D97757]"
                    >
                        <ShoppingBag class="h-3.5 w-3.5" aria-hidden="true" />
                        Official Merchandise & Goods
                    </span>

                    <h1
                        id="shop-heading"
                        class="mt-3 font-sans text-2xl font-semibold tracking-tight text-foreground sm:mt-4 sm:text-4xl lg:text-5xl"
                    >
                        Wear the mission.
                    </h1>

                    <p
                        class="mx-auto mt-3 max-w-2xl text-xs leading-relaxed font-normal text-muted-foreground sm:mt-4 sm:text-base"
                    >
                        Original KOAMISHIN apparel, accessories, and classroom
                        essentials crafted for educators, learners, and builders.
                        Every purchase directly powers community projects and
                        learning tools, fulfilled via koamishin.com.
                    </p>

                    <!-- Trust Pill Badges -->
                    <div
                        class="mt-4 flex flex-wrap items-center justify-center gap-2.5 text-xs text-muted-foreground sm:mt-8 sm:gap-6"
                    >
                        <div class="inline-flex items-center gap-1.5">
                            <CheckCircle2
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                                aria-hidden="true"
                            />
                            <span>Premium heavyweight materials</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <Package
                                class="h-4 w-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Authentic KOAMISHIN drops</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <ShieldCheck
                                class="h-4 w-4 text-sky-600 dark:text-sky-400"
                                aria-hidden="true"
                            />
                            <span>Secure checkout on koamishin.com</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Store Inventory & Action Bar -->
            <div
                class="mt-6 flex items-center justify-between border-b border-border/40 pb-4 text-xs font-medium text-muted-foreground sm:mt-8 sm:text-sm"
            >
                <!-- Left: Inventory Count -->
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-foreground">
                        {{ merches.length }} {{ merches.length === 1 ? 'Item' : 'Items' }}
                    </span>
                    <span class="text-muted-foreground/50">·</span>
                    <span class="text-emerald-600 dark:text-emerald-400">
                        {{ totalInStock }} In Stock
                    </span>
                </div>

                <!-- Desktop Right: Clean external link only (controls removed per request) -->
                <div class="hidden items-center gap-4 sm:flex">
                    <a
                        href="https://koamishin.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <span>Visit main store</span>
                        <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                    </a>
                </div>

                <!-- Mobile Right: Simplified single-line control row -->
                <div class="flex items-center gap-2.5 sm:hidden">
                    <button
                        v-if="merches.length > 0"
                        @click="togglePause"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border border-border/70 bg-card/90 px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition-all active:scale-95"
                        :title="isManuallyPaused ? 'Play' : 'Pause'"
                    >
                        <component
                            :is="isManuallyPaused ? Play : Pause"
                            class="h-3 w-3 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ isManuallyPaused ? 'Play' : 'Pause' }}</span>
                    </button>

                    <a
                        href="https://koamishin.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs transition-colors hover:text-foreground"
                    >
                        <span>Store</span>
                        <ExternalLink class="h-3 w-3" aria-hidden="true" />
                    </a>
                </div>
            </div>

            <!-- Merch Showcase Section -->
            <section
                ref="carouselContainerRef"
                class="relative mt-4 sm:mt-6"
                aria-label="Merchandise collection"
            >
                <template v-if="merches && merches.length > 0">
                    <div
                        ref="marqueeWrapperRef"
                        class="merch-carousel-wrapper relative overflow-hidden py-6 sm:py-12"
                        @mouseenter="handleMouseEnter"
                        @mouseleave="handleMouseLeave"
                    >
                        <!-- Edge Fade Masks (desktop) -->
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 z-20 w-8 bg-gradient-to-r from-background via-background/80 to-transparent sm:w-28"
                        ></div>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 z-20 w-8 bg-gradient-to-l from-background via-background/80 to-transparent sm:w-28"
                        ></div>

                        <!-- Continuous Hardware-Accelerated GSAP Marquee Track -->
                        <div
                            ref="trackRef"
                            class="merch-carousel-track flex flex-nowrap w-max"
                            :class="{ 'is-paused': isManuallyPaused }"
                        >
                            <!-- Set 1 (Base set of normalized cards) -->
                            <div class="flex shrink-0 flex-nowrap gap-5 pr-5 sm:gap-6 sm:pr-6">
                                <article
                                    v-for="(merch, index) in normalizedMerches"
                                    :key="`track-1-${merch.id}-${index}`"
                                    class="merch-card group relative flex h-full w-[290px] shrink-0 flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 ease-out sm:w-[380px] md:w-[420px] lg:w-[440px] sm:rounded-3xl"
                                >
                                    <!-- Landscape Image Container (16:9 widescreen) -->
                                    <button
                                        type="button"
                                        @click="openQuickView(merch)"
                                        class="group/img relative aspect-video w-full cursor-zoom-in overflow-hidden bg-muted/30 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        :aria-label="`Quick view ${merch.name}`"
                                    >
                                        <!-- Ambient color aura (blurred background for landscape images) -->
                                        <img
                                            v-if="merch.image_url"
                                            :src="merch.image_url"
                                            aria-hidden="true"
                                            alt=""
                                            class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-30 blur-2xl filter transition-opacity duration-500 group-hover/img:opacity-45 dark:opacity-20"
                                        />

                                        <!-- Sharp, uncropped merchandise image -->
                                        <img
                                            v-if="merch.image_url"
                                            :src="merch.image_url"
                                            :alt="merch.name"
                                            loading="lazy"
                                            class="relative z-10 h-full w-full object-contain p-2.5 transition-transform duration-500 ease-out group-hover/img:scale-105 sm:p-3"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-muted/60 via-muted/30 to-background p-6 text-center"
                                        >
                                            <div
                                                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110"
                                            >
                                                <ShoppingBag class="h-8 w-8" />
                                            </div>
                                            <span
                                                class="mt-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                            >
                                                KOAMISHIN
                                            </span>
                                        </div>

                                        <!-- Quick View Hover Overlay Pill -->
                                        <div
                                            class="absolute inset-0 z-20 flex items-center justify-center bg-black/25 opacity-0 backdrop-blur-[2px] transition-all duration-300 group-hover/img:opacity-100"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-black/75 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl backdrop-blur-md transition-transform duration-300 group-hover/img:scale-105"
                                            >
                                                <Eye class="h-3.5 w-3.5 text-primary" />
                                                <span>Quick View</span>
                                            </span>
                                        </div>
                                    </button>

                                    <!-- Details and Price -->
                                    <div
                                        class="flex flex-1 flex-col justify-between p-4 sm:p-6"
                                    >
                                        <div>
                                            <h2
                                                class="line-clamp-1 font-sans text-base font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-lg"
                                            >
                                                {{ merch.name }}
                                            </h2>
                                            <p
                                                class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground sm:mt-2 sm:text-sm"
                                            >
                                                {{
                                                    merch.description ||
                                                    'Official KOAMISHIN gear with signature styling and premium quality.'
                                                }}
                                            </p>
                                        </div>

                                        <!-- Price and Stock Row -->
                                        <div
                                            class="mt-3 flex items-baseline justify-between border-t border-border/50 pt-2.5 sm:mt-4 sm:pt-3"
                                        >
                                            <div class="flex items-baseline gap-2">
                                                <span
                                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase sm:text-xs"
                                                >
                                                    Price
                                                </span>
                                                <span
                                                    class="font-sans text-lg font-bold tracking-tight text-foreground sm:text-2xl"
                                                >
                                                    {{ merch.formatted_price }}
                                                </span>
                                            </div>

                                            <!-- Clean Stock Indicator (non-vibecoded) -->
                                            <span
                                                v-if="merch.is_out_of_stock"
                                                class="text-xs font-medium text-rose-500"
                                            >
                                                Out of stock
                                            </span>
                                            <span
                                                v-else-if="merch.stock <= 5"
                                                class="text-xs font-medium text-amber-600 dark:text-amber-400"
                                            >
                                                Only {{ merch.stock }} left
                                            </span>
                                            <span
                                                v-else
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                {{ merch.stock }} in stock
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Coming Soon CTA Button -->
                                    <div class="p-4 pt-0 sm:p-6 sm:pt-0">
                                        <a
                                            :href="merch.url || 'https://koamishin.com/'"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98] sm:min-h-11 sm:py-2.5"
                                        >
                                            <span>Coming Soon...</span>
                                            <ExternalLink class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                        </a>
                                    </div>
                                </article>
                            </div>

                            <!-- Set 2 (Identical duplicate for seamless 50% infinite loop) -->
                            <div class="flex shrink-0 flex-nowrap gap-5 pr-5 sm:gap-6 sm:pr-6" aria-hidden="true">
                                <article
                                    v-for="(merch, index) in normalizedMerches"
                                    :key="`track-2-${merch.id}-${index}`"
                                    class="merch-card group relative flex h-full w-[290px] shrink-0 flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 ease-out sm:w-[380px] md:w-[420px] lg:w-[440px] sm:rounded-3xl"
                                >
                                    <!-- Landscape Image Container (16:9 widescreen) -->
                                    <button
                                        type="button"
                                        @click="openQuickView(merch)"
                                        class="group/img relative aspect-video w-full cursor-zoom-in overflow-hidden bg-muted/30 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        :aria-label="`Quick view ${merch.name}`"
                                    >
                                        <!-- Ambient color aura (blurred background for landscape images) -->
                                        <img
                                            v-if="merch.image_url"
                                            :src="merch.image_url"
                                            aria-hidden="true"
                                            alt=""
                                            class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-30 blur-2xl filter transition-opacity duration-500 group-hover/img:opacity-45 dark:opacity-20"
                                        />

                                        <!-- Sharp, uncropped merchandise image -->
                                        <img
                                            v-if="merch.image_url"
                                            :src="merch.image_url"
                                            :alt="merch.name"
                                            loading="lazy"
                                            class="relative z-10 h-full w-full object-contain p-2.5 transition-transform duration-500 ease-out group-hover/img:scale-105 sm:p-3"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-muted/60 via-muted/30 to-background p-6 text-center"
                                        >
                                            <div
                                                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110"
                                            >
                                                <ShoppingBag class="h-8 w-8" />
                                            </div>
                                            <span
                                                class="mt-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                            >
                                                KOAMISHIN
                                            </span>
                                        </div>

                                        <!-- Quick View Hover Overlay Pill -->
                                        <div
                                            class="absolute inset-0 z-20 flex items-center justify-center bg-black/25 opacity-0 backdrop-blur-[2px] transition-all duration-300 group-hover/img:opacity-100"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-black/75 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl backdrop-blur-md transition-transform duration-300 group-hover/img:scale-105"
                                            >
                                                <Eye class="h-3.5 w-3.5 text-primary" />
                                                <span>Quick View</span>
                                            </span>
                                        </div>
                                    </button>

                                    <!-- Details and Price -->
                                    <div
                                        class="flex flex-1 flex-col justify-between p-4 sm:p-6"
                                    >
                                        <div>
                                            <h2
                                                class="line-clamp-1 font-sans text-base font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-lg"
                                            >
                                                {{ merch.name }}
                                            </h2>
                                            <p
                                                class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground sm:mt-2 sm:text-sm"
                                            >
                                                {{
                                                    merch.description ||
                                                    'Official KOAMISHIN gear with signature styling and premium quality.'
                                                }}
                                            </p>
                                        </div>

                                        <!-- Price and Stock Row -->
                                        <div
                                            class="mt-3 flex items-baseline justify-between border-t border-border/50 pt-2.5 sm:mt-4 sm:pt-3"
                                        >
                                            <div class="flex items-baseline gap-2">
                                                <span
                                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase sm:text-xs"
                                                >
                                                    Price
                                                </span>
                                                <span
                                                    class="font-sans text-lg font-bold tracking-tight text-foreground sm:text-2xl"
                                                >
                                                    {{ merch.formatted_price }}
                                                </span>
                                            </div>

                                            <!-- Clean Stock Indicator (non-vibecoded) -->
                                            <span
                                                v-if="merch.is_out_of_stock"
                                                class="text-xs font-medium text-rose-500"
                                            >
                                                Out of stock
                                            </span>
                                            <span
                                                v-else-if="merch.stock <= 5"
                                                class="text-xs font-medium text-amber-600 dark:text-amber-400"
                                            >
                                                Only {{ merch.stock }} left
                                            </span>
                                            <span
                                                v-else
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                {{ merch.stock }} in stock
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Coming Soon CTA Button -->
                                    <div class="p-4 pt-0 sm:p-6 sm:pt-0">
                                        <a
                                            :href="merch.url || 'https://koamishin.com/'"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98] sm:min-h-11 sm:py-2.5"
                                        >
                                            <span>Coming Soon...</span>
                                            <ExternalLink class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                        </a>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div
                    v-else
                    class="mx-auto max-w-xl rounded-3xl border border-dashed border-border/80 bg-card/40 p-8 text-center sm:p-12"
                >
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Sparkles class="h-7 w-7" />
                    </div>
                    <h2
                        class="mt-4 font-sans text-lg font-semibold text-foreground sm:text-xl"
                    >
                        New merch drops arriving soon
                    </h2>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        We’re preparing the latest run of KOAMISHIN apparel and
                        community goods. Check out the official shop in the
                        meantime.
                    </p>
                    <div class="mt-6 flex justify-center">
                        <a
                            href="https://koamishin.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90 hover:shadow-md active:scale-[0.98]"
                        >
                            <span>Visit koamishin.com</span>
                            <ExternalLink class="h-4 w-4" aria-hidden="true" />
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <!-- Quick View Lightbox Modal for Uncropped Viewing -->
        <ShopQuickViewModal
            :open="isQuickViewOpen"
            :merch="selectedQuickViewMerch"
            :items="props.merches"
            @close="closeQuickView"
            @select="handleQuickViewSelect"
        />

        <WelcomeFooter />
    </div>
</template>

<style scoped>
.merch-carousel-track {
    will-change: transform;
}

/* Card hover zoom and elevate effect */
.merch-card {
    will-change: transform, box-shadow;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.3s ease;
}

.merch-card:hover {
    transform: scale(1.05) translateY(-8px) !important;
    z-index: 50 !important;
    border-color: rgba(217, 119, 87, 0.6) !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(217, 119, 87, 0.25) !important;
}
</style>
