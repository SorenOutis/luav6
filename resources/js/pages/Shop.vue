<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, ShoppingBag, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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

const selectedQuickViewMerch = ref<MerchItem | null>(null);
const isQuickViewOpen = ref(false);

interface HeroTab {
    key: string;
    name: string;
    imageUrl: string;
    merch: MerchItem;
}

const heroTabs = computed<HeroTab[]>(() => {
    const list = props.merches || [];
    if (list.length === 0) return [];

    // 1. If any merch has multiple variants (e.g. Hoodie with [Obsidian, Alabaster]), use its variants
    const multiVariantMerch = list.find(
        (m) => m.variants && m.variants.length > 1,
    );
    if (multiVariantMerch && multiVariantMerch.variants) {
        return multiVariantMerch.variants.map((v, idx) => ({
            key: `${multiVariantMerch.id}-variant-${idx}`,
            name: v.name,
            imageUrl:
                v.image_url ||
                multiVariantMerch.image_url ||
                '/images/merch/techwear-hoodie-black.jpg',
            merch: {
                ...multiVariantMerch,
                name: `${multiVariantMerch.name} (${v.name})`,
                image_url: v.image_url || multiVariantMerch.image_url,
            },
        }));
    }

    // 2. If merches each have 1 or more variants (e.g. 1 variant uploaded in each merch), aggregate them
    const merchesWithVariants = list.filter(
        (m) => m.variants && m.variants.length > 0,
    );
    if (merchesWithVariants.length > 0) {
        const tabs: HeroTab[] = [];
        for (const m of merchesWithVariants) {
            for (let idx = 0; idx < (m.variants?.length || 0); idx++) {
                const v = m.variants![idx];
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
        return tabs;
    }

    return [];
});

const selectedTabIndex = ref(0);

const setTabIndex = (index: number) => {
    selectedTabIndex.value = index;
    if (heroTabTimer) {
        clearInterval(heroTabTimer);
        heroTabTimer = setInterval(() => {
            if (heroTabs.value.length > 1) {
                selectedTabIndex.value =
                    (selectedTabIndex.value + 1) % heroTabs.value.length;
            }
        }, 8000);
    }
};

watch(
    heroTabs,
    (tabs) => {
        if (selectedTabIndex.value >= tabs.length) {
            selectedTabIndex.value = 0;
        }
    },
    { immediate: true },
);

let heroTabTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    heroTabTimer = setInterval(() => {
        if (heroTabs.value.length > 1) {
            selectedTabIndex.value =
                (selectedTabIndex.value + 1) % heroTabs.value.length;
        }
    }, 8000);
});

onUnmounted(() => {
    if (heroTabTimer) {
        clearInterval(heroTabTimer);
    }
});

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
};

const closeQuickView = () => {
    isQuickViewOpen.value = false;
    selectedQuickViewMerch.value = null;
};

const handleQuickViewSelect = (merch: MerchItem) => {
    selectedQuickViewMerch.value = merch;
};

// Eagerly preload and decode all variant graphics in memory so tab clicks swap instantaneously
const preloadedImages = new Set<string>();

const preloadHeroImages = () => {
    if (typeof window === 'undefined') return;

    heroTabs.value.forEach((tab) => {
        if (tab.imageUrl && !preloadedImages.has(tab.imageUrl)) {
            const img = new window.Image();
            img.src = tab.imageUrl;
            if ('decode' in img) {
                img.decode().catch(() => {});
            }
            preloadedImages.add(tab.imageUrl);
        }
    });
};

watch(heroTabs, preloadHeroImages, { immediate: true });

const getBentoColSpan = (index: number, total: number): string => {
    if (index === 0) {
        return total === 1
            ? 'md:col-span-2 lg:col-span-8'
            : 'md:col-span-2 lg:col-span-8';
    }
    return 'md:col-span-1 lg:col-span-4';
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        preloadHeroImages();

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
                    class="inline-flex rounded-xl border border-border/80 bg-card p-1 shadow-xs"
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
                class="relative mt-3 overflow-hidden rounded-2xl shadow-xl transition-all sm:mt-5 sm:rounded-3xl"
                :aria-label="`${currentHeroMerch.name} Showcase`"
            >
                <button
                    type="button"
                    @click="openQuickView(currentHeroMerch)"
                    class="group/hero block w-full cursor-zoom-in text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :aria-label="`Quick view ${currentHeroMerch.name}`"
                >
                    <Transition name="hero-swap" mode="out-in">
                        <img
                            :key="currentHeroImageUrl"
                            :src="currentHeroImageUrl"
                            :alt="`${currentHeroMerch.name} (Showcase View)`"
                            class="h-full w-full object-contain transition-transform duration-700 ease-out group-hover/hero:scale-[1.01]"
                            loading="eager"
                            decoding="sync"
                        />
                    </Transition>
                </button>
            </section>

            <!-- Store Inventory & Action Bar (Directly Above Bento Grid) -->
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

                <!-- Right: Visit Main Store Link -->
                <div>
                    <a
                        href="https://koamishin.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                    >
                        <span>Visit main store</span>
                        <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                    </a>
                </div>
            </div>

            <!-- Merch Bento Grid Section -->
            <section
                class="relative mt-6 sm:mt-8"
                aria-label="Merchandise collection"
            >
                <div
                    v-if="merches && merches.length > 0"
                    class="merch-bento-grid grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-12"
                >
                    <article
                        v-for="(merch, index) in merches"
                        :key="`bento-${merch.id}`"
                        class="merch-card group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card shadow-xs transition-all duration-300 ease-out hover:border-foreground/20 sm:rounded-3xl"
                        :class="getBentoColSpan(index, merches.length)"
                    >
                        <!-- Spec Header Badge -->
                        <div
                            class="flex items-center justify-between border-b border-border/50 px-5 pt-4 pb-3 sm:px-6 sm:pt-5 sm:pb-3.5"
                        >
                            <span
                                class="font-mono text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                {{
                                    index === 0
                                        ? 'Featured'
                                        : `Item 0${index + 1}`
                                }}
                            </span>

                            <!-- Stock Indicator -->
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

                        <!-- Image Container with Hover Quick View -->
                        <button
                            type="button"
                            @click="openQuickView(merch)"
                            class="group/img relative w-full cursor-zoom-in overflow-hidden bg-muted/20 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            :class="
                                index === 0
                                    ? 'aspect-video sm:aspect-[21/10]'
                                    : 'aspect-video sm:aspect-[4/3]'
                            "
                            :aria-label="`Quick view ${merch.name}`"
                        >
                            <!-- Sharp Product Image -->
                            <img
                                v-if="merch.image_url"
                                :src="merch.image_url"
                                :alt="merch.name"
                                loading="lazy"
                                class="relative z-10 h-full w-full object-contain p-4 transition-transform duration-500 ease-out group-hover/img:scale-105 sm:p-6"
                            />
                            <div
                                v-else
                                class="flex h-full w-full flex-col items-center justify-center p-6 text-center"
                            >
                                <div
                                    class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                                >
                                    <ShoppingBag class="h-8 w-8" />
                                </div>
                                <span
                                    class="mt-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    KOAMISHIN
                                </span>
                            </div>
                        </button>

                        <!-- Card Content -->
                        <div
                            class="flex flex-1 flex-col justify-between p-5 sm:p-6"
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

                                <!-- Variant Pills -->
                                <p
                                    v-if="
                                        merch.variants &&
                                        merch.variants.length > 0
                                    "
                                    class="mt-3 text-[11px] font-medium tracking-wide text-muted-foreground"
                                >
                                    {{
                                        merch.variants
                                            .map((v) => v.name)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>

                            <!-- Price & CTA -->
                            <div
                                class="mt-5 flex items-center justify-between border-t border-border/50 pt-4"
                            >
                                <span
                                    class="font-sans text-lg font-bold tracking-tight text-foreground sm:text-xl"
                                >
                                    {{ merch.formatted_price }}
                                </span>

                                <a
                                    :href="
                                        merch.url || 'https://koamishin.com/'
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-border/80 bg-secondary/60 px-4 py-2 text-xs font-medium text-foreground shadow-xs transition-all hover:bg-secondary hover:text-foreground active:scale-[0.98] sm:text-sm"
                                >
                                    <span>Visit store</span>
                                    <ExternalLink
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>
                        </div>
                    </article>

                    <!-- Atelier / Department Brand Spec Bento Tile -->
                    <article
                        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card p-6 shadow-xs transition-all duration-300 ease-out hover:border-foreground/20 sm:rounded-3xl sm:p-8 md:col-span-2 lg:col-span-4"
                    >
                        <div>
                            <span
                                class="font-mono text-[10px] font-medium tracking-wider text-primary uppercase"
                            >
                                Atelier
                            </span>

                            <h3
                                class="mt-4 font-sans text-lg font-bold tracking-tight text-foreground sm:text-xl"
                            >
                                KOAMISHIN × BSIT Department
                            </h3>

                            <p
                                class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                            >
                                Official student merchandise and technical gear
                                engineered exclusively for the BSIT student body
                                and innovators.
                            </p>

                            <ul
                                class="mt-4 space-y-2 text-xs text-muted-foreground"
                            >
                                <li class="flex items-center gap-2">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-primary"
                                    ></span>
                                    <span
                                        >240–380 GSM Heavyweight Technical
                                        Fleece</span
                                    >
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-primary"
                                    ></span>
                                    <span>High-Density Contrast Insignia</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-primary"
                                    ></span>
                                    <span
                                        >Campus Pickup & Nationwide
                                        Fulfillment</span
                                    >
                                </li>
                            </ul>
                        </div>

                        <div class="mt-6 border-t border-border/50 pt-4">
                            <a
                                href="https://koamishin.com/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:opacity-90 active:scale-[0.98] sm:text-sm"
                            >
                                <span>Explore Official Store</span>
                                <ExternalLink
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="mx-auto max-w-xl rounded-2xl border border-border/80 bg-card p-8 text-center sm:p-12"
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

        <!-- Hidden variant preloader so the browser fetches & caches all variant graphics immediately -->
        <div class="hidden" aria-hidden="true">
            <img
                v-for="tab in heroTabs"
                :key="`preload-${tab.key}`"
                :src="tab.imageUrl"
                loading="eager"
                decoding="sync"
                alt=""
            />
        </div>

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
.hero-swap-enter-active {
    transition:
        opacity 0.35s ease-out,
        transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}

.hero-swap-leave-active {
    transition: opacity 0.2s ease-in;
}

.hero-swap-enter-from {
    opacity: 0;
    transform: scale(1.02);
}

.hero-swap-leave-to {
    opacity: 0;
}

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

/* Card hover elevate effect */
.merch-card {
    will-change: transform, box-shadow;
    transition:
        transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.3s ease;
}

.merch-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 168, 135, 0.5);
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.15),
        0 8px 10px -6px rgba(0, 0, 0, 0.1);
}
</style>
