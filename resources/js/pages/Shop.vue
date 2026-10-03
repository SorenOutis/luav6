<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import {
    ExternalLink,
    Eye,
    LayoutGrid,
    Pause,
    Play,
    ShoppingBag,
    SlidersHorizontal,
    Sparkles,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from 'vue';
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
    return props.merches.filter((m) => !m.is_out_of_stock && m.stock > 0)
        .length;
});

const isManuallyPaused = ref(false);
const isHovered = ref(false);
const selectedQuickViewMerch = ref<MerchItem | null>(null);
const isQuickViewOpen = ref(false);

interface HeroTab {
    key: string;
    name: string;
    imageUrl: string;
    merch: MerchItem;
}

const activeViewMode = ref<'stream' | 'bento'>('stream');
const bentoActiveVariantImages = ref<Record<number, string>>({});

const setBentoVariant = (merchId: number, imageUrl: string) => {
    bentoActiveVariantImages.value[merchId] = imageUrl;
};

const getBentoImageUrl = (merch: MerchItem): string => {
    return (
        bentoActiveVariantImages.value[merch.id] ||
        merch.image_url ||
        '/images/merch/techwear-hoodie-black.jpg'
    );
};

const heroTabs = computed<HeroTab[]>(() => {
    const list = props.merches || [];
    if (list.length === 0) return [];

    const tabs: HeroTab[] = [];
    for (const m of list) {
        if (m.variants && m.variants.length > 0) {
            for (let idx = 0; idx < m.variants.length; idx++) {
                const v = m.variants[idx];
                tabs.push({
                    key: `${m.id}-variant-${idx}`,
                    name: v.name,
                    imageUrl:
                        v.image_url ||
                        m.image_url ||
                        '/images/merch/techwear-hoodie-black.jpg',
                    merch: {
                        ...m,
                        name: `${m.name} (${v.name})`,
                        image_url: v.image_url || m.image_url,
                    },
                });
            }
        }
    }

    return tabs;
});

const selectedTabIndex = ref(0);

const preloadTabImages = () => {
    if (typeof window === 'undefined') return;
    heroTabs.value.forEach((tab) => {
        if (tab.imageUrl) {
            const img = new Image();
            img.src = tab.imageUrl;
        }
    });
};

const setTabIndex = (index: number) => {
    selectedTabIndex.value = index;
    const target = heroTabs.value[index];
    if (target?.imageUrl && typeof window !== 'undefined') {
        const img = new Image();
        img.src = target.imageUrl;
    }
};

watch(
    heroTabs,
    (tabs) => {
        if (selectedTabIndex.value >= tabs.length) {
            selectedTabIndex.value = 0;
        }
        preloadTabImages();
    },
    { immediate: true },
);

const activeHeroTab = computed<HeroTab | null>(() => {
    if (!heroTabs.value.length) return null;
    return heroTabs.value[selectedTabIndex.value] || heroTabs.value[0];
});

const currentHeroMerch = computed<MerchItem>(() => {
    if (activeHeroTab.value) {
        return activeHeroTab.value.merch;
    }
    const list = props.merches || [];
    const hoodie = list.find((m) => m.name.toLowerCase().includes('hoodie'));
    if (hoodie) return hoodie;
    if (list[0]) return list[0];
    return {
        id: 2,
        name: 'KOAMISHIN BSIT Techwear Hoodie',
        description:
            '380 GSM heavyweight technical fleece with topographic contour sleeves, weatherproof angular pocket, and BSIT signature insignia.',
        price: 1650,
        currency: 'PHP',
        formatted_price: '₱1,650.00',
        image_url: '/images/merch/techwear-hoodie-black.jpg',
        stock: 12,
        is_out_of_stock: false,
        stock_label: '12 in stock',
        url: 'https://koamishin.com/',
    };
});

const currentHeroImageUrl = computed<string>(() => {
    if (activeHeroTab.value?.imageUrl) {
        return activeHeroTab.value.imageUrl;
    }
    return (
        currentHeroMerch.value.image_url ||
        '/images/merch/techwear-hoodie-black.jpg'
    );
});

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

const setViewMode = async (mode: 'stream' | 'bento') => {
    activeViewMode.value = mode;
    if (mode === 'stream') {
        await nextTick();
        setupMarquee();
    } else {
        if (marqueeTween) {
            marqueeTween.pause();
        }
    }
};

const setupMarquee = () => {
    if (
        activeViewMode.value !== 'stream' ||
        !trackRef.value ||
        normalizedMerches.value.length === 0
    ) {
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
        if (activeViewMode.value === 'stream') {
            await nextTick();
            setupMarquee();
        }
    },
    { deep: true },
);

onMounted(async () => {
    preloadTabImages();
    if (typeof window !== 'undefined') {
        const isDark =
            document.documentElement.classList.contains('dark') ||
            window.matchMedia?.('(prefers-color-scheme: dark)')?.matches;

        if (heroTabs.value.length > 0) {
            const preferredMatch = heroTabs.value.findIndex((tab) => {
                const lower = tab.name.toLowerCase();
                return isDark
                    ? lower.includes('obsidian') ||
                          lower.includes('black') ||
                          lower.includes('dark')
                    : lower.includes('alabaster') ||
                          lower.includes('white') ||
                          lower.includes('light');
            });
            if (preferredMatch !== -1) {
                selectedTabIndex.value = preferredMatch;
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const qvParam = urlParams.get('quickview');
        if (qvParam && props.merches && props.merches.length > 0) {
            const target =
                props.merches.find((m) => String(m.id) === qvParam) ||
                props.merches[0];
            openQuickView(target);
        }
    }

    await nextTick();
    setupMarquee();
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
            <!-- Breadcrumbs & Variant Switcher -->
            <div class="flex items-center justify-between gap-4">
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
                    <span class="text-muted-foreground/40" aria-hidden="true"
                        >/</span
                    >
                    <span
                        class="font-medium text-foreground"
                        aria-current="page"
                        >Shop</span
                    >
                </nav>

                <!-- Dynamic Variant Switcher (e.g. Obsidian / Alabaster) -->
                <div
                    v-if="heroTabs.length > 0"
                    class="inline-flex rounded-xl border border-border/80 bg-card/80 p-1 shadow-xs backdrop-blur-md"
                >
                    <button
                        v-for="(tab, idx) in heroTabs"
                        :key="tab.key"
                        type="button"
                        @click="setTabIndex(idx)"
                        class="rounded-lg px-2.5 py-1 text-xs transition-all"
                        :class="
                            selectedTabIndex === idx
                                ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                : 'font-medium text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ tab.name }}
                    </button>
                </div>
            </div>

            <!-- Hero Showcase Graphic (Clean, unencumbered presentation) -->
            <section
                class="relative mt-3 overflow-hidden rounded-2xl bg-muted/10 shadow-xl transition-all sm:mt-5 sm:rounded-3xl"
                :aria-label="`${currentHeroMerch.name} Showcase`"
            >
                <button
                    type="button"
                    @click="openQuickView(currentHeroMerch)"
                    class="group/hero relative block min-h-[260px] w-full cursor-zoom-in text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:min-h-[380px] md:min-h-[460px]"
                    :aria-label="`Quick view ${currentHeroMerch.name}`"
                >
                    <img
                        :src="currentHeroImageUrl"
                        :alt="`${currentHeroMerch.name} (Showcase View)`"
                        class="h-full w-full object-contain transition-transform duration-700 ease-out group-hover/hero:scale-[1.01]"
                        loading="eager"
                        decoding="sync"
                    />
                </button>
            </section>

            <!-- Store Inventory & Action Bar (Directly Above Carousel) -->
            <div
                class="mt-12 flex items-center justify-between border-b border-border/40 pb-4 text-xs font-medium text-muted-foreground sm:mt-16 sm:text-sm"
            >
                <!-- Left: Catalog Heading & Inventory Count -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <span
                        class="font-sans text-sm font-bold tracking-tight text-foreground sm:text-base"
                    >
                        Merchandise Catalog
                    </span>
                    <span class="text-muted-foreground/40">·</span>
                    <span class="font-semibold text-foreground">
                        {{ merches.length }}
                        {{ merches.length === 1 ? 'Item' : 'Items' }}
                    </span>
                    <span class="text-muted-foreground/50">·</span>
                    <span class="text-emerald-600 dark:text-emerald-400">
                        {{ totalInStock }} In Stock
                    </span>
                </div>

                <!-- Desktop Right: View Mode Toggle & Clean external link -->
                <div class="hidden items-center gap-4 sm:flex">
                    <div
                        class="inline-flex items-center rounded-xl border border-border/80 bg-card/80 p-0.5 shadow-xs backdrop-blur-md"
                        role="group"
                        aria-label="Layout view mode"
                    >
                        <button
                            type="button"
                            @click="setViewMode('stream')"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs transition-all"
                            :class="
                                activeViewMode === 'stream'
                                    ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                    : 'font-medium text-muted-foreground hover:text-foreground'
                            "
                            aria-label="Switch to stream carousel view"
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            />
                            <span>Stream</span>
                        </button>
                        <button
                            type="button"
                            @click="setViewMode('bento')"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs transition-all"
                            :class="
                                activeViewMode === 'bento'
                                    ? 'bg-primary font-semibold text-primary-foreground shadow-xs'
                                    : 'font-medium text-muted-foreground hover:text-foreground'
                            "
                            aria-label="Switch to bento grid view"
                        >
                            <LayoutGrid
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            />
                            <span>Bento Grid</span>
                        </button>
                    </div>

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

                <!-- Mobile Right: Pause control and view switcher -->
                <div class="flex items-center gap-2 sm:hidden">
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

                    <div
                        class="inline-flex items-center rounded-xl border border-border/80 bg-card/80 p-0.5 shadow-xs"
                        role="group"
                        aria-label="Layout view mode"
                    >
                        <button
                            type="button"
                            @click="setViewMode('stream')"
                            class="rounded-lg p-1 text-xs transition-all"
                            :class="
                                activeViewMode === 'stream'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground'
                            "
                            aria-label="Switch to stream carousel view"
                        >
                            <SlidersHorizontal
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            @click="setViewMode('bento')"
                            class="rounded-lg p-1 text-xs transition-all"
                            :class="
                                activeViewMode === 'bento'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground'
                            "
                            aria-label="Switch to bento grid view"
                        >
                            <LayoutGrid
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

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
                        v-show="activeViewMode === 'stream'"
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
                            class="merch-carousel-track flex w-max flex-nowrap"
                            :class="{ 'is-paused': isManuallyPaused }"
                        >
                            <!-- Set 1 (Base set of normalized cards) -->
                            <div
                                class="flex shrink-0 flex-nowrap gap-5 pr-5 sm:gap-6 sm:pr-6"
                            >
                                <article
                                    v-for="(merch, index) in normalizedMerches"
                                    :key="`track-1-${merch.id}-${index}`"
                                    class="merch-card group relative flex h-full w-[290px] shrink-0 flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 ease-out sm:w-[380px] sm:rounded-3xl md:w-[420px] lg:w-[440px]"
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
                                                <Eye
                                                    class="h-3.5 w-3.5 text-primary"
                                                />
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
                                            <div
                                                class="flex items-baseline gap-2"
                                            >
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
                                            :href="
                                                merch.url ||
                                                'https://koamishin.com/'
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98] sm:min-h-11 sm:py-2.5"
                                        >
                                            <span>Coming Soon...</span>
                                            <ExternalLink
                                                class="h-4 w-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </a>
                                    </div>
                                </article>
                            </div>

                            <!-- Set 2 (Identical duplicate for seamless 50% infinite loop) -->
                            <div
                                class="flex shrink-0 flex-nowrap gap-5 pr-5 sm:gap-6 sm:pr-6"
                                aria-hidden="true"
                            >
                                <article
                                    v-for="(merch, index) in normalizedMerches"
                                    :key="`track-2-${merch.id}-${index}`"
                                    class="merch-card group relative flex h-full w-[290px] shrink-0 flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 ease-out sm:w-[380px] sm:rounded-3xl md:w-[420px] lg:w-[440px]"
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
                                                <Eye
                                                    class="h-3.5 w-3.5 text-primary"
                                                />
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
                                            <div
                                                class="flex items-baseline gap-2"
                                            >
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
                                            :href="
                                                merch.url ||
                                                'https://koamishin.com/'
                                            "
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98] sm:min-h-11 sm:py-2.5"
                                        >
                                            <span>Coming Soon...</span>
                                            <ExternalLink
                                                class="h-4 w-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </a>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>

                    <!-- Mode 2: Bento Grid Layout -->
                    <div
                        v-if="activeViewMode === 'bento'"
                        class="merch-bento-grid py-6 sm:py-10"
                        aria-label="Bento grid catalog"
                    >
                        <div
                            class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                        >
                            <!-- Card 0: Spotlight Flagship Tile -->
                            <article
                                v-if="merches.length > 0"
                                :key="`bento-spotlight-${merches[0].id}`"
                                class="bento-card group relative col-span-1 flex flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 hover:border-primary/50 hover:shadow-xl sm:rounded-3xl md:col-span-2 md:row-span-2 lg:col-span-2"
                            >
                                <div class="relative flex flex-col">
                                    <!-- Large Image Container -->
                                    <button
                                        type="button"
                                        @click="openQuickView(merches[0])"
                                        class="group/img relative aspect-[16/10] w-full cursor-zoom-in overflow-hidden bg-muted/20 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:aspect-[16/9] md:aspect-[16/11]"
                                        :aria-label="`Quick view ${merches[0].name}`"
                                    >
                                        <!-- Ambient color aura -->
                                        <img
                                            v-if="getBentoImageUrl(merches[0])"
                                            :src="getBentoImageUrl(merches[0])"
                                            aria-hidden="true"
                                            alt=""
                                            class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-25 blur-2xl filter transition-opacity duration-500 group-hover/img:opacity-40"
                                        />

                                        <!-- Main Image -->
                                        <img
                                            :src="getBentoImageUrl(merches[0])"
                                            :alt="merches[0].name"
                                            loading="eager"
                                            decoding="async"
                                            class="relative z-10 h-full w-full object-contain p-4 transition-transform duration-500 ease-out group-hover/img:scale-105 sm:p-6"
                                        />

                                        <!-- Top Left Badge -->
                                        <div
                                            class="absolute top-3 left-3 z-20 sm:top-4 sm:left-4"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary backdrop-blur-md"
                                            >
                                                <Sparkles class="h-3 w-3" />
                                                <span>Flagship Drop</span>
                                            </span>
                                        </div>

                                        <!-- Top Right Stock Badge -->
                                        <div
                                            class="absolute top-3 right-3 z-20 sm:top-4 sm:right-4"
                                        >
                                            <span
                                                v-if="
                                                    merches[0].is_out_of_stock
                                                "
                                                class="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-[11px] font-semibold text-rose-500 backdrop-blur-md"
                                            >
                                                Out of stock
                                            </span>
                                            <span
                                                v-else-if="
                                                    merches[0].stock <= 5
                                                "
                                                class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-500 backdrop-blur-md"
                                            >
                                                Only
                                                {{ merches[0].stock }} left
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-500 backdrop-blur-md"
                                            >
                                                {{ merches[0].stock }} in stock
                                            </span>
                                        </div>

                                        <!-- Quick View Overlay -->
                                        <div
                                            class="absolute inset-0 z-20 flex items-center justify-center bg-black/25 opacity-0 backdrop-blur-[2px] transition-all duration-300 group-hover/img:opacity-100"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-black/75 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xl backdrop-blur-md"
                                            >
                                                <Eye
                                                    class="h-3.5 w-3.5 text-primary"
                                                />
                                                <span>Quick View</span>
                                            </span>
                                        </div>
                                    </button>

                                    <!-- Details -->
                                    <div class="p-5 sm:p-6">
                                        <h2
                                            class="font-sans text-lg font-bold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-2xl"
                                        >
                                            {{ merches[0].name }}
                                        </h2>
                                        <p
                                            class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                        >
                                            {{
                                                merches[0].description ||
                                                'Official KOAMISHIN gear with signature styling and premium technical quality.'
                                            }}
                                        </p>

                                        <!-- Interactive variant switcher on spotlight card -->
                                        <div
                                            v-if="
                                                merches[0].variants &&
                                                merches[0].variants.length > 1
                                            "
                                            class="mt-4 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                                            >
                                                Colorway:
                                            </span>
                                            <button
                                                v-for="v in merches[0].variants"
                                                :key="v.name"
                                                type="button"
                                                @click="
                                                    v.image_url &&
                                                    setBentoVariant(
                                                        merches[0].id,
                                                        v.image_url,
                                                    )
                                                "
                                                class="rounded-lg border px-2.5 py-1 text-xs font-medium transition-all"
                                                :class="
                                                    (bentoActiveVariantImages[
                                                        merches[0].id
                                                    ] ||
                                                        merches[0]
                                                            .image_url) ===
                                                    v.image_url
                                                        ? 'border-primary bg-primary font-semibold text-primary-foreground shadow-xs'
                                                        : 'border-border/70 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                                "
                                            >
                                                {{ v.name }}
                                            </button>
                                        </div>

                                        <!-- Price Row -->
                                        <div
                                            class="mt-4 flex items-baseline justify-between border-t border-border/50 pt-3"
                                        >
                                            <div
                                                class="flex items-baseline gap-2"
                                            >
                                                <span
                                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase sm:text-xs"
                                                >
                                                    Price
                                                </span>
                                                <span
                                                    class="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                                                >
                                                    {{
                                                        merches[0]
                                                            .formatted_price
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spotlight Card CTA -->
                                <div class="p-5 pt-0 sm:p-6 sm:pt-0">
                                    <a
                                        :href="
                                            merches[0].url ||
                                            'https://koamishin.com/'
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90 active:scale-[0.98]"
                                    >
                                        <span>Coming Soon...</span>
                                        <ExternalLink
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </div>
                            </article>

                            <!-- Remaining Merch Cards (Index 1+) -->
                            <article
                                v-for="(merch, index) in merches.slice(1)"
                                :key="`bento-${merch.id}`"
                                class="bento-card group relative col-span-1 flex flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card/90 shadow-md backdrop-blur-md transition-all duration-300 hover:border-primary/50 hover:shadow-xl sm:rounded-3xl"
                                :class="[
                                    index === 0
                                        ? 'col-span-1 md:col-span-2 lg:col-span-2'
                                        : 'col-span-1',
                                ]"
                            >
                                <div class="flex flex-col">
                                    <!-- Image Container -->
                                    <button
                                        type="button"
                                        @click="openQuickView(merch)"
                                        class="group/img relative aspect-video w-full cursor-zoom-in overflow-hidden bg-muted/20 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        :aria-label="`Quick view ${merch.name}`"
                                    >
                                        <!-- Ambient color aura -->
                                        <img
                                            v-if="getBentoImageUrl(merch)"
                                            :src="getBentoImageUrl(merch)"
                                            aria-hidden="true"
                                            alt=""
                                            class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-20 blur-2xl filter transition-opacity duration-500 group-hover/img:opacity-35"
                                        />

                                        <!-- Main image -->
                                        <img
                                            :src="getBentoImageUrl(merch)"
                                            :alt="merch.name"
                                            loading="lazy"
                                            decoding="async"
                                            class="relative z-10 h-full w-full object-contain p-3 transition-transform duration-500 ease-out group-hover/img:scale-105 sm:p-4"
                                        />

                                        <!-- Stock Badge -->
                                        <div
                                            class="absolute top-3 right-3 z-20"
                                        >
                                            <span
                                                v-if="merch.is_out_of_stock"
                                                class="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-rose-500 backdrop-blur-md"
                                            >
                                                Out of stock
                                            </span>
                                            <span
                                                v-else-if="merch.stock <= 5"
                                                class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-amber-500 backdrop-blur-md"
                                            >
                                                Only
                                                {{ merch.stock }} left
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-500 backdrop-blur-md"
                                            >
                                                {{ merch.stock }} in stock
                                            </span>
                                        </div>

                                        <!-- Quick View Overlay -->
                                        <div
                                            class="absolute inset-0 z-20 flex items-center justify-center bg-black/25 opacity-0 backdrop-blur-[2px] transition-all duration-300 group-hover/img:opacity-100"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-white/30 bg-black/75 px-3 py-1 text-xs font-semibold text-white shadow-xl backdrop-blur-md"
                                            >
                                                <Eye
                                                    class="h-3.5 w-3.5 text-primary"
                                                />
                                                <span>Quick View</span>
                                            </span>
                                        </div>
                                    </button>

                                    <!-- Details -->
                                    <div class="p-4 sm:p-5">
                                        <h3
                                            class="line-clamp-1 font-sans text-base font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary sm:text-lg"
                                        >
                                            {{ merch.name }}
                                        </h3>
                                        <p
                                            class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                        >
                                            {{
                                                merch.description ||
                                                'Official KOAMISHIN gear with signature styling and premium quality.'
                                            }}
                                        </p>

                                        <!-- Interactive variant switcher if variants exist -->
                                        <div
                                            v-if="
                                                merch.variants &&
                                                merch.variants.length > 1
                                            "
                                            class="mt-2.5 flex flex-wrap items-center gap-1.5"
                                        >
                                            <button
                                                v-for="v in merch.variants"
                                                :key="v.name"
                                                type="button"
                                                @click="
                                                    v.image_url &&
                                                    setBentoVariant(
                                                        merch.id,
                                                        v.image_url,
                                                    )
                                                "
                                                class="rounded-md border px-2 py-0.5 text-[10px] font-medium transition-all"
                                                :class="
                                                    (bentoActiveVariantImages[
                                                        merch.id
                                                    ] || merch.image_url) ===
                                                    v.image_url
                                                        ? 'border-primary bg-primary font-semibold text-primary-foreground shadow-2xs'
                                                        : 'border-border/70 bg-card/70 text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                                "
                                            >
                                                {{ v.name }}
                                            </button>
                                        </div>

                                        <!-- Price Row -->
                                        <div
                                            class="mt-3 flex items-baseline justify-between border-t border-border/50 pt-2.5"
                                        >
                                            <div
                                                class="flex items-baseline gap-2"
                                            >
                                                <span
                                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                                                >
                                                    Price
                                                </span>
                                                <span
                                                    class="font-sans text-lg font-bold tracking-tight text-foreground"
                                                >
                                                    {{ merch.formatted_price }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- CTA Button -->
                                <div class="p-4 pt-0 sm:p-5 sm:pt-0">
                                    <a
                                        :href="
                                            merch.url ||
                                            'https://koamishin.com/'
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98]"
                                    >
                                        <span>Coming Soon...</span>
                                        <ExternalLink
                                            class="h-4 w-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </div>
                            </article>

                            <!-- Studio Spec Bento Card (Editorial Accent Tile) -->
                            <article
                                class="bento-spec-card relative col-span-1 flex flex-col justify-between overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-card via-card/95 to-primary/5 p-5 shadow-md backdrop-blur-md transition-all duration-300 hover:border-primary/40 sm:rounded-3xl sm:p-6"
                            >
                                <div>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 text-[10px] font-semibold tracking-wider text-primary uppercase"
                                        >
                                            <Sparkles class="h-3 w-3" />
                                            <span>KOAMISHIN // IT</span>
                                        </span>
                                        <span
                                            class="font-mono text-[11px] text-muted-foreground"
                                        >
                                            2026 DROP
                                        </span>
                                    </div>

                                    <h3
                                        class="mt-4 font-sans text-base font-bold tracking-tight text-foreground sm:text-lg"
                                    >
                                        Technical Apparel & Gear
                                    </h3>
                                    <p
                                        class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                                    >
                                        Crafted for creators, programmers, and
                                        campus life. Heavyweight textiles
                                        engineered with ergonomic contour
                                        panelling and high-density embroidery.
                                    </p>

                                    <ul
                                        class="mt-4 space-y-2 text-xs text-muted-foreground"
                                    >
                                        <li class="flex items-center gap-2">
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-primary"
                                            ></span>
                                            <span
                                                >Heavyweight 240–380 GSM fleece
                                                & cotton</span
                                            >
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-primary"
                                            ></span>
                                            <span
                                                >Engineered weather-resistant
                                                details</span
                                            >
                                        </li>
                                        <li class="flex items-center gap-2">
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-primary"
                                            ></span>
                                            <span
                                                >Official distribution via
                                                koamishin.com</span
                                            >
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    class="mt-6 border-t border-border/40 pt-4"
                                >
                                    <a
                                        href="https://koamishin.com/"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-primary/30 bg-primary/10 px-4 py-2 text-xs font-semibold text-primary transition-all hover:bg-primary hover:text-primary-foreground active:scale-[0.98]"
                                    >
                                        <span>Visit Official Store</span>
                                        <ExternalLink class="h-3.5 w-3.5" />
                                    </a>
                                </div>
                            </article>
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
.shop-root {
    background-image:
        radial-gradient(
            ellipse at 50% 0%,
            rgba(0, 168, 135, 0.07) 0%,
            transparent 65%
        ),
        repeating-linear-gradient(
            -45deg,
            rgba(0, 168, 135, 0.025) 0px,
            rgba(0, 168, 135, 0.025) 1px,
            transparent 1px,
            transparent 48px
        );
}

.merch-carousel-track {
    will-change: transform;
}

/* Card hover zoom and elevate effect */
.merch-card {
    will-change: transform, box-shadow;
    transition:
        transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.35s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.3s ease;
}

.merch-card:hover {
    transform: scale(1.05) translateY(-8px) !important;
    z-index: 50 !important;
    border-color: rgba(0, 168, 135, 0.6) !important;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(0, 168, 135, 0.25) !important;
}

/* Smooth hero variant transition */
.hero-fade-enter-active,
.hero-fade-leave-active {
    transition:
        opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.hero-fade-enter-from {
    opacity: 0;
    transform: scale(0.995);
}
.hero-fade-leave-to {
    opacity: 0;
    transform: scale(1.005);
}

.bento-card {
    will-change: transform, box-shadow;
    transition:
        transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.3s ease;
}

.bento-card:hover {
    transform: translateY(-4px);
}
</style>
