<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, ShoppingBag } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
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

const frames = computed<MerchItem[]>(() => props.merches ?? []);

const inStockCount = computed(
    () => frames.value.filter((m) => !m.is_out_of_stock && m.stock > 0).length,
);

const pad = (value: number): string => String(value).padStart(2, '0');

const pieceIndex = ref(0);
const variantIndex = ref(0);

const currentPiece = computed<MerchItem | null>(
    () => frames.value[pieceIndex.value] ?? null,
);

const variantOptions = computed(() => currentPiece.value?.variants ?? []);

const displayImageUrl = computed<string | null>(() => {
    const piece = currentPiece.value;
    if (!piece) {
        return null;
    }
    const variant = piece.variants?.[variantIndex.value];
    return variant?.image_url || piece.image_url;
});

let prefersDark = false;

const preferredVariantIndex = (merch: MerchItem): number => {
    const variants = merch.variants ?? [];
    if (variants.length === 0) {
        return 0;
    }
    const hints = prefersDark
        ? ['obsidian', 'black', 'dark']
        : ['alabaster', 'white', 'light'];
    const match = variants.findIndex((v) =>
        hints.some((hint) => v.name.toLowerCase().includes(hint)),
    );
    return match === -1 ? 0 : match;
};

const selectPiece = (index: number) => {
    pieceIndex.value = index;
    const piece = frames.value[index];
    variantIndex.value = piece ? preferredVariantIndex(piece) : 0;
};

const selectVariant = (index: number) => {
    variantIndex.value = index;
};

const toneFor = (merch: MerchItem): string => {
    if (merch.is_out_of_stock || merch.stock <= 0) {
        return 'text-[var(--st-ink-mute)]';
    }
    if (merch.stock <= 5) {
        return 'font-medium text-[var(--st-accent-text)]';
    }
    return 'text-[var(--st-ink-mute)]';
};

watch(
    frames,
    (list) => {
        if (pieceIndex.value >= list.length) {
            pieceIndex.value = 0;
            variantIndex.value = 0;
        }
    },
    { immediate: true },
);

const preloadImages = computed<string[]>(() => {
    const urls = frames.value.flatMap((frame) => [
        ...(frame.variants ?? []).map((v) => v.image_url),
        frame.image_url,
    ]);
    return [...new Set(urls.filter((url): url is string => Boolean(url)))];
});

const backdropByUrl = ref<Record<string, string>>({});

/**
 * Poster art carries its own background, which otherwise reads as a hard
 * rectangle against the stage surfaces. Average a few edge samples of every
 * loaded image and paint the surface behind it with that same tone, so each
 * piece blends into the well, bench, tile, and modal instead of stacking
 * another colored card on top of the theme.
 */
const onImageLoad = (event: Event): void => {
    const img = event.target as HTMLImageElement | null;
    const key = img?.getAttribute('src');

    if (!img || !key || backdropByUrl.value[key]) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 8;
    canvas.height = 8;
    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    try {
        context.drawImage(img, 0, 0, 8, 8);
        const { data } = context.getImageData(0, 0, 8, 8);
        const samples = [0, 7, 56, 63, 3, 24, 59, 31];
        let r = 0;
        let g = 0;
        let b = 0;
        let weight = 0;

        for (const index of samples) {
            const offset = index * 4;
            const alpha = data[offset + 3] / 255;

            if (alpha <= 0) {
                continue;
            }

            r += data[offset] * alpha;
            g += data[offset + 1] * alpha;
            b += data[offset + 2] * alpha;
            weight += alpha;
        }

        if (weight === 0) {
            return;
        }

        backdropByUrl.value = {
            ...backdropByUrl.value,
            [key]: `rgb(${Math.round(r / weight)}, ${Math.round(g / weight)}, ${Math.round(b / weight)})`,
        };
    } catch {
        // Cross-origin artwork without CORS headers taints the canvas;
        // the surface simply keeps its theme color.
    }
};

const backdropStyle = (url?: string | null): Record<string, string> =>
    url && backdropByUrl.value[url]
        ? { backgroundColor: backdropByUrl.value[url] }
        : {};

const selectedQuickViewMerch = ref<MerchItem | null>(null);
const isQuickViewOpen = ref(false);

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

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    prefersDark =
        document.documentElement.classList.contains('dark') ||
        window.matchMedia?.('(prefers-color-scheme: dark)')?.matches;

    const lead = frames.value.findIndex(
        (frame) => (frame.variants?.length ?? 0) > 1,
    );
    selectPiece(lead === -1 ? 0 : lead);

    const urlParams = new URLSearchParams(window.location.search);
    const qvParam = urlParams.get('quickview');
    if (qvParam && frames.value.length > 0) {
        const target =
            frames.value.find((m) => String(m.id) === qvParam) ??
            frames.value[0];
        openQuickView(target);
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
        class="shop-root min-h-screen overflow-x-hidden bg-background font-sans text-[var(--st-ink)] selection:bg-[#d97757]/25"
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
            class="mx-auto flex max-w-[1440px] flex-col px-4 pt-4 pb-16 sm:px-6 sm:pt-8 sm:pb-24 lg:px-16 lg:pt-10 lg:pb-28"
        >
            <nav
                aria-label="Breadcrumb"
                class="st-label flex items-center gap-2 text-[var(--st-ink-mute)]"
            >
                <Link
                    href="/"
                    class="transition-colors hover:text-[var(--st-ink)] focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                >
                    Home
                </Link>
                <span aria-hidden="true">/</span>
                <span class="text-[var(--st-ink)]" aria-current="page"
                    >Shop</span
                >
            </nav>

            <section
                v-if="currentPiece"
                class="studio-stage mt-4 overflow-hidden rounded-3xl border border-[var(--st-hairline)] bg-[var(--st-stage-bg)] sm:rounded-[32px]"
                aria-label="The studio — preview the line"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 sm:px-10 lg:px-12"
                >
                    <p class="st-label text-[var(--st-ink-mute)]">
                        KOAMISHIN — MERCH LINE
                    </p>
                    <p class="st-label text-[var(--st-ink-mute)]">
                        {{ pad(frames.length) }} items ·
                        {{ pad(inStockCount) }} in stock
                    </p>
                </div>

                <div
                    class="mx-auto max-w-2xl px-6 pt-10 pb-2 text-center sm:pt-14"
                >
                    <h1
                        class="stage-title text-4xl font-semibold tracking-[-0.025em] text-[var(--st-ink)] sm:text-5xl lg:text-[3.5rem] lg:leading-[1.06]"
                    >
                        The merch line.
                    </h1>
                    <p
                        class="mt-4 text-[15px] leading-relaxed text-[var(--st-ink-mute)] sm:text-base"
                    >
                        Official KOAMISHIN apparel for educators, learners, and
                        creators. Specs, stock, and colorways live here —
                        checkout and fulfillment happen on koamishin.com.
                    </p>
                </div>

                <div
                    class="grid gap-2 px-4 pt-8 pb-10 sm:px-8 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:gap-8 lg:px-12 lg:pt-12"
                >
                    <button
                        type="button"
                        class="group/garment relative block min-h-[320px] w-full cursor-zoom-in overflow-hidden rounded-[24px] bg-[var(--st-well-bg)] transition-colors duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:ring-inset sm:min-h-[420px] lg:min-h-[520px]"
                        :style="backdropStyle(displayImageUrl)"
                        :aria-label="`Open details for ${currentPiece.name}`"
                        @click="openQuickView(currentPiece)"
                    >
                        <span class="st-pool" aria-hidden="true"></span>
                        <img
                            v-if="displayImageUrl"
                            :key="displayImageUrl"
                            :src="displayImageUrl"
                            :alt="currentPiece.name"
                            class="stage-garment st-swap relative z-10 mx-auto h-full w-full object-contain p-6 transition-transform duration-700 ease-out group-hover/garment:scale-[1.015] sm:p-10 lg:p-12"
                            loading="eager"
                            @load="onImageLoad"
                        />
                        <span
                            v-else
                            class="relative z-10 flex h-full min-h-[320px] w-full items-center justify-center text-[var(--st-ink-mute)]"
                        >
                            <ShoppingBag class="h-10 w-10" aria-hidden="true" />
                        </span>
                    </button>

                    <div
                        class="flex flex-col justify-center rounded-[24px] border border-[var(--st-hairline)] bg-[var(--st-panel-bg)] p-6 sm:p-8 lg:p-9"
                    >
                        <p class="st-label text-[var(--st-ink-mute)]">
                            On stage
                        </p>
                        <h2
                            class="mt-3 text-2xl font-semibold tracking-[-0.02em] text-[var(--st-ink)] sm:text-[1.75rem]"
                        >
                            {{ currentPiece.name }}
                        </h2>
                        <p
                            v-if="currentPiece.description"
                            class="mt-3 text-[15px] leading-relaxed text-[var(--st-ink-mute)]"
                        >
                            {{ currentPiece.description }}
                        </p>

                        <div class="mt-6">
                            <div class="st-row">
                                <span class="st-label text-[var(--st-ink-mute)]"
                                    >Price</span
                                >
                                <span
                                    class="text-xl font-semibold tracking-[-0.01em] text-[var(--st-ink)]"
                                    >{{ currentPiece.formatted_price }}</span
                                >
                            </div>
                            <div class="st-row">
                                <span class="st-label text-[var(--st-ink-mute)]"
                                    >Stock</span
                                >
                                <span
                                    class="text-sm"
                                    :class="toneFor(currentPiece)"
                                    >{{ currentPiece.stock_label }}</span
                                >
                            </div>
                            <div class="st-row">
                                <span class="st-label text-[var(--st-ink-mute)]"
                                    >Colorway</span
                                >
                                <span class="text-sm text-[var(--st-ink)]">
                                    {{
                                        variantOptions.length > 0
                                            ? variantOptions[variantIndex]?.name
                                            : 'Single'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="variantOptions.length > 0"
                            class="mt-5 flex flex-wrap gap-2"
                            role="group"
                            aria-label="Choose a colorway"
                        >
                            <button
                                v-for="(variant, index) in variantOptions"
                                :key="variant.name"
                                type="button"
                                class="colorway-capsule st-capsule focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                                :aria-pressed="
                                    index === variantIndex ? 'true' : 'false'
                                "
                                @click="selectVariant(index)"
                            >
                                {{ variant.name }}
                            </button>
                        </div>

                        <div class="mt-7 flex flex-wrap gap-3">
                            <a
                                :href="
                                    currentPiece.url || 'https://koamishin.com/'
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="st-pill st-pill--solid min-w-[10rem] flex-1 focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                            >
                                <span>Visit store</span>
                                <ExternalLink
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                />
                            </a>
                            <button
                                type="button"
                                class="st-pill st-pill--ghost focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                                @click="openQuickView(currentPiece)"
                            >
                                <span>Details</span>
                            </button>
                        </div>
                        <p
                            class="st-label mt-4 text-[var(--st-ink-mute)] opacity-80"
                        >
                            Prices in PHP · Opens koamishin.com
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="studio-stage empty-stage mt-4 overflow-hidden rounded-3xl border border-[var(--st-hairline)] bg-[var(--st-stage-bg)] px-6 py-16 text-center sm:rounded-[32px] sm:py-24"
                aria-label="The studio is empty"
            >
                <h2
                    class="text-2xl font-semibold tracking-[-0.02em] text-[var(--st-ink)] sm:text-3xl"
                >
                    Nothing in the studio yet.
                </h2>
                <p
                    class="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-[var(--st-ink-mute)]"
                >
                    New merch drops arriving soon. We're preparing the latest
                    run of KOAMISHIN apparel and community goods — check out the
                    official shop in the meantime.
                </p>
                <a
                    href="https://koamishin.com/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="st-pill st-pill--solid mt-7 inline-flex focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                >
                    <span>Visit store</span>
                    <ExternalLink class="h-4 w-4" aria-hidden="true" />
                </a>
            </section>

            <section
                v-if="frames.length > 0"
                id="collection"
                class="mt-12 scroll-mt-28 sm:mt-16"
                aria-label="The collection"
            >
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2
                        class="text-2xl font-semibold tracking-[-0.02em] text-[var(--st-ink)] sm:text-[1.75rem]"
                    >
                        The collection.
                    </h2>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <span class="st-label text-[var(--st-ink-mute)]">
                            {{ pad(frames.length) }} items ·
                            {{ pad(inStockCount) }} in stock
                        </span>
                        <a
                            href="https://koamishin.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="st-label inline-flex items-center gap-1.5 text-[var(--st-accent-text)] transition-colors hover:text-[var(--st-ink)] focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                        >
                            <span>Visit store</span>
                            <ExternalLink
                                class="h-3.5 w-3.5"
                                aria-hidden="true"
                            />
                        </a>
                    </div>
                </div>
                <p class="st-label mt-3 text-[var(--st-ink-mute)] opacity-80">
                    Prices shown for reference · checkout &amp; fulfillment on
                    koamishin.com
                </p>

                <div
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5"
                >
                    <article
                        v-for="merch in frames"
                        :key="merch.id"
                        class="piece-tile"
                    >
                        <button
                            type="button"
                            class="tile-open group/tile block w-full cursor-pointer overflow-hidden rounded-3xl bg-[var(--st-tile)] text-left transition-transform duration-300 ease-out hover:-translate-y-1 focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                            :aria-label="`Open details for ${merch.name}`"
                            @click="openQuickView(merch)"
                        >
                            <span
                                class="block aspect-square w-full overflow-hidden transition-colors duration-300"
                                :style="backdropStyle(merch.image_url)"
                            >
                                <img
                                    v-if="merch.image_url"
                                    :src="merch.image_url"
                                    :alt="merch.name"
                                    loading="lazy"
                                    class="h-full w-full object-contain p-6 transition-transform duration-500 ease-out group-hover/tile:scale-[1.03]"
                                    @load="onImageLoad"
                                />
                                <span
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[var(--st-ink-mute)]"
                                >
                                    <ShoppingBag
                                        class="h-8 w-8"
                                        aria-hidden="true"
                                    />
                                </span>
                            </span>
                            <span class="block px-5 pb-5">
                                <span
                                    class="block text-[15px] font-semibold tracking-[-0.01em] text-[var(--st-ink)]"
                                    >{{ merch.name }}</span
                                >
                                <span
                                    class="mt-1 block text-sm text-[var(--st-ink-mute)]"
                                    >{{ merch.formatted_price }}</span
                                >
                                <span
                                    class="mt-1 block text-xs"
                                    :class="toneFor(merch)"
                                    >{{ merch.stock_label }}</span
                                >
                            </span>
                        </button>
                    </article>
                </div>
            </section>
        </main>

        <div class="hidden" aria-hidden="true">
            <img
                v-for="url in preloadImages"
                :key="`preload-${url}`"
                :src="url"
                loading="eager"
                alt=""
                @load="onImageLoad"
            />
        </div>

        <ShopQuickViewModal
            :open="isQuickViewOpen"
            :merch="selectedQuickViewMerch"
            :items="props.merches"
            :backdrop-colors="backdropByUrl"
            @close="closeQuickView"
            @select="handleQuickViewSelect"
        />

        <WelcomeFooter />
    </div>
</template>

<style scoped src="../components/shop/studio.css"></style>

<style scoped>
.shop-root {
    --st-ink: #1a1a1e;
    --st-ink-mute: #68625a;
    --st-bg: var(--color-card, #faf7f2);
    --st-tile: var(--color-secondary, #ebe5dc);
    --st-hairline: rgba(29, 29, 31, 0.1);
    --st-hairline-strong: rgba(29, 29, 31, 0.22);
    --st-capsule-line: rgba(29, 29, 31, 0.24);
    --st-accent: var(--color-brand, #d97757);
    --st-accent-strong: #c4643f;
    --st-accent-text: #a44a28;
    --st-on-accent: #191008;
    --st-ghost-hover: rgba(29, 29, 31, 0.05);
    --st-stage-bg: var(--color-card, #faf7f2);
    --st-well-bg: var(--color-secondary, #ebe5dc);
    --st-panel-bg: var(--color-card, #faf7f2);
    --st-pool-a: rgba(90, 74, 58, 0.035);
    --st-pool-b: rgba(90, 74, 58, 0.1);
}

.dark .shop-root {
    --st-ink: #f5f5f7;
    --st-ink-mute: #a1a1a6;
    --st-bg: #0b0b0d;
    --st-tile: #1d1d1f;
    --st-hairline: rgba(245, 245, 247, 0.12);
    --st-hairline-strong: rgba(245, 245, 247, 0.26);
    --st-capsule-line: rgba(245, 245, 247, 0.3);
    --st-accent-strong: #e08a6d;
    --st-accent-text: #d97757;
    --st-on-accent: #17100c;
    --st-ghost-hover: rgba(245, 245, 247, 0.08);
    --st-stage-bg: #0b0b0d;
    --st-well-bg: #0e0f11;
    --st-panel-bg: rgba(19, 20, 22, 0.6);
    --st-pool-a: rgba(245, 245, 247, 0.11);
    --st-pool-b: rgba(245, 245, 247, 0.075);
}
</style>
