<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Package,
    ShoppingBag,
    Sparkles,
    X,
    ZoomIn,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { MerchItem } from '@/types/merch';

const props = defineProps<{
    open: boolean;
    merch: MerchItem | null;
    items?: MerchItem[];
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

// Interactive mouse-hover zoom lens state
const isZooming = ref(false);
const zoomOrigin = ref('50% 50%');

const handleMouseMove = (event: MouseEvent) => {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    zoomOrigin.value = `${Math.max(0, Math.min(100, x))}% ${Math.max(0, Math.min(100, y))}%`;
};

const handleMouseEnter = () => {
    isZooming.value = true;
};

const handleMouseLeave = () => {
    isZooming.value = false;
    zoomOrigin.value = '50% 50%';
};

watch(
    () => props.merch?.id,
    () => {
        isZooming.value = false;
        zoomOrigin.value = '50% 50%';
    },
);

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

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document !== 'undefined') {
            if (isOpen) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
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
                <!-- Backdrop with blur -->
                <div
                    class="absolute inset-0 bg-black/80 backdrop-blur-md transition-opacity"
                    @click="emit('close')"
                ></div>

                <!-- Modal Window -->
                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-border/80 bg-card/95 shadow-2xl backdrop-blur-xl sm:rounded-3xl"
                >
                    <!-- Header / Navigation Bar -->
                    <div
                        class="flex items-center justify-between border-b border-border/50 px-4 py-3 sm:px-6 sm:py-4"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
                            >
                                <Sparkles class="h-3.5 w-3.5" />
                                <span>Quick View</span>
                            </span>

                            <span
                                v-if="hasMultiple"
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ currentIndex + 1 }} of {{ totalItems }}
                            </span>
                        </div>

                        <!-- Controls: Prev / Next / Close -->
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <button
                                v-if="hasMultiple"
                                type="button"
                                @click="handlePrevious"
                                class="flex h-8 w-8 items-center justify-center rounded-full border border-border/60 bg-muted/40 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Previous item (←)"
                                aria-label="Previous item"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <button
                                v-if="hasMultiple"
                                type="button"
                                @click="handleNext"
                                class="flex h-8 w-8 items-center justify-center rounded-full border border-border/60 bg-muted/40 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Next item (→)"
                                aria-label="Next item"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                @click="emit('close')"
                                class="flex h-8 w-8 items-center justify-center rounded-full border border-border/60 bg-muted/40 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Close (Esc)"
                                aria-label="Close modal"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body (Grid on desktop) -->
                    <div
                        class="grid flex-1 overflow-y-auto lg:grid-cols-[1.35fr_1fr]"
                    >
                        <!-- Media Stage (Interactive Hover-to-Zoom Lens) -->
                        <div
                            class="relative flex min-h-[280px] items-center justify-center overflow-hidden border-b border-border/50 bg-black/40 p-4 sm:min-h-[400px] sm:p-8 lg:border-r lg:border-b-0"
                        >
                            <!-- Atmospheric Ambient Glow from image colors (stable backdrop) -->
                            <img
                                v-if="merch.image_url"
                                :src="merch.image_url"
                                aria-hidden="true"
                                alt=""
                                class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-25 blur-3xl filter"
                            />

                            <!-- Interactive Zoom Viewport -->
                            <div
                                v-if="merch.image_url"
                                class="relative z-10 flex max-h-[50vh] w-auto max-w-full cursor-zoom-in items-center justify-center overflow-hidden rounded-xl sm:max-h-[60vh]"
                                @mousemove="handleMouseMove"
                                @mouseenter="handleMouseEnter"
                                @mouseleave="handleMouseLeave"
                            >
                                <!-- Sharp merchandise image with mouse-following zoom -->
                                <img
                                    :src="merch.image_url"
                                    :alt="merch.name"
                                    class="pointer-events-none max-h-[50vh] w-auto max-w-full rounded-xl object-contain drop-shadow-2xl will-change-transform sm:max-h-[60vh]"
                                    :style="{
                                        transformOrigin: zoomOrigin,
                                        transform: isZooming
                                            ? 'scale(2.2)'
                                            : 'scale(1)',
                                        transition: isZooming
                                            ? 'transform 0.08s ease-out'
                                            : 'transform 0.3s ease-out',
                                    }"
                                />

                                <!-- Subtle 'Hover to zoom' hint pill -->
                                <span
                                    class="pointer-events-none absolute right-3 bottom-3 inline-flex items-center gap-1.5 rounded-full bg-black/75 px-2.5 py-1 text-[11px] font-medium text-white/90 shadow-md backdrop-blur-md transition-opacity duration-200"
                                    :class="{ 'opacity-0': isZooming }"
                                >
                                    <ZoomIn class="h-3.5 w-3.5 text-primary" />
                                    <span>Hover to zoom</span>
                                </span>
                            </div>

                            <div
                                v-else
                                class="relative z-10 flex flex-col items-center justify-center p-8 text-center text-muted-foreground"
                            >
                                <div
                                    class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                                >
                                    <ShoppingBag class="h-10 w-10" />
                                </div>
                                <span
                                    class="mt-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    KOAMISHIN
                                </span>
                            </div>
                        </div>

                        <!-- Product Details Column -->
                        <div class="flex flex-col justify-between p-5 sm:p-8">
                            <div class="space-y-5">
                                <div>
                                    <p
                                        class="text-[11px] font-bold tracking-wider text-primary uppercase"
                                    >
                                        Official Gear
                                    </p>
                                    <h3
                                        class="mt-1 font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                                    >
                                        {{ merch.name }}
                                    </h3>
                                </div>

                                <!-- Price Banner & Clean Stock Status Row -->
                                <div class="flex flex-wrap items-center gap-3">
                                    <div
                                        class="inline-flex items-baseline gap-2 rounded-xl border border-border/50 bg-muted/50 px-3.5 py-2"
                                    >
                                        <span
                                            class="text-xs font-medium text-muted-foreground uppercase"
                                        >
                                            Price
                                        </span>
                                        <span
                                            class="text-2xl font-black tracking-tight text-foreground sm:text-3xl"
                                        >
                                            {{ merch.formatted_price }}
                                        </span>
                                    </div>

                                    <!-- Clean, Non-vibecoded Stock Indicator -->
                                    <div class="text-xs font-medium">
                                        <span
                                            v-if="merch.is_out_of_stock"
                                            class="inline-flex items-center gap-1.5 font-semibold text-rose-500"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-rose-500"
                                            ></span>
                                            Out of stock
                                        </span>
                                        <span
                                            v-else-if="merch.stock <= 5"
                                            class="inline-flex items-center gap-1.5 font-semibold text-amber-600 dark:text-amber-400"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-amber-500"
                                            ></span>
                                            Only {{ merch.stock }} left in stock
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                            ></span>
                                            {{ merch.stock }} available
                                        </span>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div
                                    class="space-y-2 border-t border-border/50 pt-4"
                                >
                                    <h4
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Overview
                                    </h4>
                                    <p
                                        class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                                    >
                                        {{
                                            merch.description ||
                                            'Crafted with premium materials and signature styling for creators, educators, and students.'
                                        }}
                                    </p>
                                </div>

                                <!-- Features Pill List -->
                                <div
                                    class="grid grid-cols-2 gap-2 pt-2 text-xs text-muted-foreground"
                                >
                                    <div
                                        class="flex items-center gap-2 rounded-lg bg-muted/30 p-2"
                                    >
                                        <Package class="h-4 w-4 text-primary" />
                                        <span>Official Packaging</span>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 rounded-lg bg-muted/30 p-2"
                                    >
                                        <Sparkles
                                            class="h-4 w-4 text-primary"
                                        />
                                        <span>Limited Batch</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div
                                class="mt-6 flex flex-col gap-2.5 border-t border-border/50 pt-5 sm:flex-row"
                            >
                                <a
                                    :href="
                                        merch.url || 'https://koamishin.com/'
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 hover:shadow-md active:scale-[0.98]"
                                >
                                    <span>Coming Soon...</span>
                                    <ExternalLink class="h-4 w-4" />
                                </a>

                                <button
                                    type="button"
                                    @click="emit('close')"
                                    class="inline-flex items-center justify-center rounded-xl border border-border/70 px-4 py-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
