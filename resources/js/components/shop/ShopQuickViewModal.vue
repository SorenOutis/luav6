<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    ShoppingBag,
    X,
    ZoomIn,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { MerchItem } from '@/types/merch';

const props = defineProps<{
    open: boolean;
    merch: MerchItem | null;
    items?: MerchItem[];
    backdropColors?: Record<string, string>;
}>();

const emit = defineEmits<{
    close: [];
    select: [merch: MerchItem];
}>();

const currentIndex = computed(() => {
    if (!props.merch || !props.items || props.items.length === 0) return -1;
    return props.items.findIndex((item) => item.id === props.merch?.id);
});

const totalItems = computed(() => props.items?.length ?? 0);

const hasMultiple = computed(() => totalItems.value > 1);

const isZooming = ref(false);
const zoomOrigin = ref('50% 50%');

const handleMouseMove = (event: MouseEvent) => {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    zoomOrigin.value = `${Math.max(0, Math.min(100, x))}% ${Math.max(0, Math.min(100, y))}%`;
};

const pointerCanHover = (): boolean =>
    typeof window === 'undefined' ||
    window.matchMedia?.('(hover: hover)')?.matches !== false;

const handleMouseEnter = () => {
    if (!pointerCanHover()) {
        return;
    }
    isZooming.value = true;
};

const handleMouseLeave = () => {
    isZooming.value = false;
    zoomOrigin.value = '50% 50%';
};

const selectedVariantIndex = ref(0);

watch(
    () => props.merch?.id,
    () => {
        isZooming.value = false;
        zoomOrigin.value = '50% 50%';
        selectedVariantIndex.value = 0;
    },
);

const modalDisplayImage = computed(() => {
    if (props.merch?.variants && props.merch.variants.length > 0) {
        const v = props.merch.variants[selectedVariantIndex.value];
        if (v?.image_url) return v.image_url;
    }
    return props.merch?.image_url || null;
});

const modalBackdropStyle = computed((): Record<string, string> => {
    const url = modalDisplayImage.value;
    const color = url ? props.backdropColors?.[url] : undefined;

    if (!color) {
        return {};
    }

    return { backgroundColor: color };
});

const modalDisplayName = computed(() => {
    if (!props.merch) return '';
    if (props.merch.variants && props.merch.variants.length > 0) {
        const v = props.merch.variants[selectedVariantIndex.value];
        if (
            v?.name &&
            !props.merch.name.toLowerCase().includes(v.name.toLowerCase())
        ) {
            return `${props.merch.name} (${v.name})`;
        }
    }
    return props.merch.name;
});

const stockTone = computed(() => {
    if (!props.merch) return '';
    if (props.merch.is_out_of_stock) return 'text-[var(--st-ink-mute)]';
    if (props.merch.stock <= 5)
        return 'font-medium text-[var(--st-accent-text)]';
    return 'text-[var(--st-ink)]';
});

const handlePrevious = () => {
    if (!props.items || props.items.length <= 1 || currentIndex.value === -1)
        return;
    const prevIndex =
        (currentIndex.value - 1 + props.items.length) % props.items.length;
    emit('select', props.items[prevIndex]);
};

const handleNext = () => {
    if (!props.items || props.items.length <= 1 || currentIndex.value === -1)
        return;
    const nextIndex = (currentIndex.value + 1) % props.items.length;
    emit('select', props.items[nextIndex]);
};

const handleKeydown = (event: KeyboardEvent) => {
    if (!props.open) return;

    if (event.key === 'Escape') {
        emit('close');
    } else if (event.key === 'ArrowLeft') {
        handlePrevious();
    } else if (event.key === 'ArrowRight') {
        handleNext();
    }
};

const padCount = (value: number): string => String(value).padStart(2, '0');

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document !== 'undefined') {
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }
    },
);

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && merch"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="`Quick view for ${merch.name}`"
            >
                <div
                    class="absolute inset-0 bg-[#05060a]/60 backdrop-blur-[2px]"
                    @click="emit('close')"
                ></div>

                <div
                    class="st-sheet relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[28px] border border-[var(--st-hairline)] bg-[var(--st-sheet-bg)] text-[var(--st-ink)] shadow-[var(--st-sheet-shadow)] backdrop-blur-xl backdrop-saturate-150"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-[var(--st-hairline)] px-4 py-3 sm:px-6"
                    >
                        <span class="st-label text-[var(--st-ink-mute)]">
                            {{ padCount(currentIndex + 1) }} /
                            {{ padCount(totalItems) }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="hasMultiple"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--st-capsule-line)] text-[var(--st-ink-mute)] transition-colors hover:bg-[var(--st-ghost-hover)] hover:text-[var(--st-ink)] focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none sm:h-9 sm:w-9"
                                title="Previous item (←)"
                                aria-label="Previous item"
                                @click="handlePrevious"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <button
                                v-if="hasMultiple"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--st-capsule-line)] text-[var(--st-ink-mute)] transition-colors hover:bg-[var(--st-ghost-hover)] hover:text-[var(--st-ink)] focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none sm:h-9 sm:w-9"
                                title="Next item (→)"
                                aria-label="Next item"
                                @click="handleNext"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-[var(--st-capsule-line)] text-[var(--st-ink-mute)] transition-colors hover:bg-[var(--st-ghost-hover)] hover:text-[var(--st-ink)] focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none sm:h-9 sm:w-9"
                                title="Close (Esc)"
                                aria-label="Close modal"
                                @click="emit('close')"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        class="grid flex-1 overflow-y-auto lg:grid-cols-[1.35fr_1fr]"
                    >
                        <div
                            class="relative flex min-h-[280px] items-center justify-center overflow-hidden border-b border-[var(--st-hairline)] bg-[var(--st-well-bg)] p-4 transition-colors duration-300 sm:min-h-[400px] sm:p-8 lg:border-r lg:border-b-0"
                            :style="modalBackdropStyle"
                        >
                            <span class="st-pool" aria-hidden="true"></span>

                            <div
                                v-if="modalDisplayImage"
                                class="relative z-10 flex max-h-[50vh] w-auto max-w-full cursor-zoom-in items-center justify-center overflow-hidden sm:max-h-[60vh]"
                                @mousemove="handleMouseMove"
                                @mouseenter="handleMouseEnter"
                                @mouseleave="handleMouseLeave"
                            >
                                <img
                                    :key="modalDisplayImage"
                                    :src="modalDisplayImage"
                                    :alt="modalDisplayName"
                                    class="st-swap pointer-events-none max-h-[50vh] w-auto max-w-full object-contain will-change-transform sm:max-h-[60vh]"
                                    :style="{
                                        transformOrigin: zoomOrigin,
                                        transform: isZooming
                                            ? 'scale(1.8)'
                                            : 'scale(1)',
                                        transition: isZooming
                                            ? 'transform 0.12s ease-out'
                                            : 'transform 0.3s ease-out',
                                    }"
                                />

                                <span
                                    class="st-label pointer-events-none absolute right-3 bottom-3 hidden items-center gap-1.5 rounded-full bg-[var(--st-chip-bg)] px-3 py-1.5 text-[var(--st-ink)] backdrop-blur-sm transition-opacity duration-200 pointer-fine:inline-flex"
                                    :class="{ 'opacity-0': isZooming }"
                                >
                                    <ZoomIn
                                        class="h-3.5 w-3.5 text-[#d97757]"
                                        aria-hidden="true"
                                    />
                                    <span>Hover to zoom</span>
                                </span>
                            </div>

                            <div
                                v-else
                                class="relative z-10 flex flex-col items-center justify-center p-8 text-center text-[var(--st-ink-mute)]"
                            >
                                <div
                                    class="flex h-20 w-20 items-center justify-center rounded-2xl border border-[var(--st-hairline-strong)]"
                                >
                                    <ShoppingBag
                                        class="h-10 w-10"
                                        aria-hidden="true"
                                    />
                                </div>
                                <span class="st-label mt-3"> KOAMISHIN </span>
                            </div>
                        </div>

                        <div class="flex flex-col justify-between p-5 sm:p-8">
                            <div>
                                <h3
                                    class="text-2xl font-semibold tracking-[-0.02em] text-[var(--st-ink)]"
                                >
                                    {{ modalDisplayName }}
                                </h3>
                                <p
                                    class="mt-2 text-3xl font-semibold tracking-[-0.02em] text-[var(--st-ink)]"
                                >
                                    {{ merch.formatted_price }}
                                </p>

                                <div class="mt-5">
                                    <div class="st-row">
                                        <span
                                            class="st-label text-[var(--st-ink-mute)]"
                                            >Stock</span
                                        >
                                        <span
                                            class="text-sm"
                                            :class="stockTone"
                                            >{{ merch.stock_label }}</span
                                        >
                                    </div>
                                    <div class="st-row">
                                        <span
                                            class="st-label text-[var(--st-ink-mute)]"
                                            >Colorway</span
                                        >
                                        <span
                                            class="text-sm text-[var(--st-ink)]"
                                        >
                                            {{
                                                merch.variants?.length
                                                    ? merch.variants[
                                                          selectedVariantIndex
                                                      ]?.name
                                                    : 'Single'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        merch.variants &&
                                        merch.variants.length > 0
                                    "
                                    class="mt-5"
                                >
                                    <div
                                        v-if="merch.variants.length > 1"
                                        class="flex flex-wrap gap-2"
                                        role="group"
                                        aria-label="Choose a colorway"
                                    >
                                        <button
                                            v-for="(
                                                variant, idx
                                            ) in merch.variants"
                                            :key="variant.name"
                                            type="button"
                                            class="st-capsule focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
                                            :aria-pressed="
                                                selectedVariantIndex === idx
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            @click="selectedVariantIndex = idx"
                                        >
                                            {{ variant.name }}
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <h4
                                        class="st-label text-[var(--st-ink-mute)]"
                                    >
                                        Overview
                                    </h4>
                                    <p
                                        class="mt-2 text-sm leading-relaxed text-[var(--st-ink-soft)] sm:text-base"
                                    >
                                        {{
                                            merch.description ||
                                            'Crafted with premium materials and signature styling for creators, educators, and students.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-6 flex flex-col gap-2.5 border-t border-[var(--st-hairline)] pt-5 sm:flex-row"
                            >
                                <a
                                    :href="
                                        merch.url || 'https://koamishin.com/'
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="st-pill st-pill--solid flex-1 focus-visible:ring-2 focus-visible:ring-[#d97757] focus-visible:outline-none"
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
                                    @click="emit('close')"
                                >
                                    <span>Close</span>
                                </button>
                            </div>

                            <p
                                class="st-label mt-3 text-[var(--st-ink-mute)] opacity-70"
                            >
                                Opens koamishin.com — external store
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped src="./studio.css"></style>

<style scoped>
.st-sheet {
    max-height: 92vh;
    --st-ink: #1a1a1e;
    --st-ink-soft: rgba(26, 26, 30, 0.75);
    --st-ink-mute: #68625a;
    --st-bg: var(--color-card, #faf7f2);
    --st-hairline: rgba(29, 29, 31, 0.12);
    --st-hairline-strong: rgba(29, 29, 31, 0.26);
    --st-capsule-line: rgba(29, 29, 31, 0.24);
    --st-accent: var(--color-brand, #d97757);
    --st-accent-strong: #c4643f;
    --st-accent-text: #a44a28;
    --st-on-accent: #191008;
    --st-ghost-hover: rgba(29, 29, 31, 0.05);
    --st-well-bg: var(--color-secondary, #ebe5dc);
    --st-pool-a: rgba(90, 74, 58, 0.035);
    --st-pool-b: rgba(90, 74, 58, 0.08);
    --st-chip-bg: color-mix(
        in srgb,
        var(--color-card, #faf7f2) 90%,
        transparent
    );
    --st-sheet-bg: color-mix(
        in srgb,
        var(--color-card, #faf7f2) 85%,
        transparent
    );
    --st-sheet-shadow: 0 40px 120px rgba(24, 20, 16, 0.25);
}

.dark .st-sheet {
    --st-ink: #f5f5f7;
    --st-ink-soft: rgba(245, 245, 247, 0.76);
    --st-ink-mute: #a1a1a6;
    --st-bg: #0b0b0d;
    --st-hairline: rgba(245, 245, 247, 0.14);
    --st-hairline-strong: rgba(245, 245, 247, 0.28);
    --st-capsule-line: rgba(245, 245, 247, 0.3);
    --st-accent-strong: #e08a6d;
    --st-accent-text: #d97757;
    --st-on-accent: #17100c;
    --st-ghost-hover: rgba(245, 245, 247, 0.08);
    --st-well-bg: #0e0f11;
    --st-pool-a: rgba(245, 245, 247, 0.11);
    --st-pool-b: rgba(245, 245, 247, 0.075);
    --st-chip-bg: rgba(11, 12, 13, 0.8);
    --st-sheet-bg: rgba(29, 29, 31, 0.78);
    --st-sheet-shadow: 0 40px 120px rgba(0, 0, 0, 0.55);
}

@supports (height: 100dvh) {
    .st-sheet {
        max-height: 92dvh;
    }
}
</style>
