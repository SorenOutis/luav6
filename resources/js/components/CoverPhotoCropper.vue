<script setup lang="ts">
import { Loader2, Move, ZoomIn, ZoomOut } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';

type Props = {
    /** The raw file the user picked from the file input. */
    file: File | null;
    /** Frame aspect ratio (width / height) the cover is rendered at. */
    aspectRatio?: number;
    /** Width of the exported image in pixels. Height follows the ratio. */
    outputWidth?: number;
};

const props = withDefaults(defineProps<Props>(), {
    aspectRatio: 3,
    outputWidth: 1600,
});

const emit = defineEmits<{
    /** Emitted with the cropped file once the user confirms. */
    cropped: [file: File, previewUrl: string];
    cancel: [];
}>();

const MIN_ZOOM = 1;
const MAX_ZOOM = 3;

const sourceUrl = ref<string | null>(null);
const naturalWidth = ref(0);
const naturalHeight = ref(0);
const isReady = ref(false);
const isSaving = ref(false);

const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);

const frame = ref<HTMLDivElement | null>(null);
const frameWidth = ref(0);

const isOpen = computed(() => Boolean(props.file));

const frameHeight = computed(() => frameWidth.value / props.aspectRatio);

/**
 * Scale needed for the image to fully cover the frame at zoom level 1.
 */
const baseScale = computed(() => {
    if (!naturalWidth.value || !naturalHeight.value || !frameWidth.value) {
        return 1;
    }

    return Math.max(
        frameWidth.value / naturalWidth.value,
        frameHeight.value / naturalHeight.value,
    );
});

const displayWidth = computed(
    () => naturalWidth.value * baseScale.value * zoom.value,
);
const displayHeight = computed(
    () => naturalHeight.value * baseScale.value * zoom.value,
);

const imageStyle = computed(() => ({
    width: `${displayWidth.value}px`,
    height: `${displayHeight.value}px`,
    transform: `translate3d(${offsetX.value}px, ${offsetY.value}px, 0)`,
}));

/**
 * Keep the image edges outside of the frame so no empty gap can appear.
 */
const clampOffsets = () => {
    const maxX = Math.max(0, (displayWidth.value - frameWidth.value) / 2);
    const maxY = Math.max(0, (displayHeight.value - frameHeight.value) / 2);

    offsetX.value = Math.min(maxX, Math.max(-maxX, offsetX.value));
    offsetY.value = Math.min(maxY, Math.max(-maxY, offsetY.value));
};

const revokeSource = () => {
    if (sourceUrl.value) {
        URL.revokeObjectURL(sourceUrl.value);
        sourceUrl.value = null;
    }
};

const measureFrame = () => {
    frameWidth.value = frame.value?.clientWidth ?? 0;
    clampOffsets();
};

/**
 * The modal animates in, so the frame has no width on the first tick.
 * Observing it keeps the crop maths correct through the transition and any
 * later resize / orientation change.
 */
let resizeObserver: ResizeObserver | null = null;

watch(frame, (element) => {
    resizeObserver?.disconnect();
    resizeObserver = null;

    if (!element || typeof ResizeObserver === 'undefined') return;

    resizeObserver = new ResizeObserver(() => measureFrame());
    resizeObserver.observe(element);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    revokeSource();
});

const resetTransform = () => {
    zoom.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
};

const onImageLoad = (event: Event) => {
    const image = event.target as HTMLImageElement;
    naturalWidth.value = image.naturalWidth;
    naturalHeight.value = image.naturalHeight;
    isReady.value = true;
    measureFrame();
};

watch(
    () => props.file,
    (file) => {
        revokeSource();
        isReady.value = false;
        naturalWidth.value = 0;
        naturalHeight.value = 0;
        resetTransform();

        if (file) {
            sourceUrl.value = URL.createObjectURL(file);
            // The frame only has a width once the modal is in the DOM.
            requestAnimationFrame(() => measureFrame());
        }
    },
    { immediate: true },
);

watch(zoom, () => clampOffsets());

// ── Dragging ────────────────────────────────────────────────────────
const isDragging = ref(false);
let dragStartX = 0;
let dragStartY = 0;
let originX = 0;
let originY = 0;

const startDrag = (event: PointerEvent) => {
    if (!isReady.value) return;

    isDragging.value = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    originX = offsetX.value;
    originY = offsetY.value;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
};

const onDrag = (event: PointerEvent) => {
    if (!isDragging.value) return;

    offsetX.value = originX + (event.clientX - dragStartX);
    offsetY.value = originY + (event.clientY - dragStartY);
    clampOffsets();
};

const endDrag = (event: PointerEvent) => {
    if (!isDragging.value) return;

    isDragging.value = false;
    (event.currentTarget as HTMLElement).releasePointerCapture(event.pointerId);
};

const stepZoom = (delta: number) => {
    zoom.value = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom.value + delta));
};

// ── Export ──────────────────────────────────────────────────────────
const renderToCanvas = (image: HTMLImageElement): HTMLCanvasElement => {
    const outputWidth = props.outputWidth;
    const outputHeight = Math.round(outputWidth / props.aspectRatio);

    const canvas = document.createElement('canvas');
    canvas.width = outputWidth;
    canvas.height = outputHeight;

    const context = canvas.getContext('2d');
    if (!context) return canvas;

    // Scale factor between the on-screen frame and the exported canvas.
    const scale = outputWidth / frameWidth.value;

    const drawWidth = displayWidth.value * scale;
    const drawHeight = displayHeight.value * scale;
    const drawX =
        (frameWidth.value / 2 + offsetX.value) * scale - drawWidth / 2;
    const drawY =
        (frameHeight.value / 2 + offsetY.value) * scale - drawHeight / 2;

    context.imageSmoothingQuality = 'high';
    context.drawImage(image, drawX, drawY, drawWidth, drawHeight);

    return canvas;
};

const confirm = async () => {
    if (!props.file || !sourceUrl.value || !isReady.value) return;

    isSaving.value = true;

    try {
        const image = new Image();
        image.src = sourceUrl.value;
        await image.decode();

        const canvas = renderToCanvas(image);
        const blob = await new Promise<Blob | null>((resolve) => {
            canvas.toBlob((result) => resolve(result), 'image/jpeg', 0.92);
        });

        if (!blob) {
            isSaving.value = false;
            return;
        }

        const baseName = props.file.name.replace(/\.[^./\\]+$/, '');
        const cropped = new File([blob], `${baseName}-cover.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });

        emit('cropped', cropped, URL.createObjectURL(blob));
    } finally {
        isSaving.value = false;
    }
};

const cancel = () => {
    emit('cancel');
};
</script>

<template>
    <ResponsiveModal
        :open="isOpen"
        title="Position your cover photo"
        description="Drag to reposition and zoom until it fits the frame. This is exactly how it will appear on your profile."
        content-class="sm:max-w-2xl"
        @close="cancel"
    >
        <div class="space-y-4">
            <!-- Live cover frame -->
            <div
                ref="frame"
                class="relative w-full cursor-grab touch-none overflow-hidden rounded-2xl bg-muted select-none active:cursor-grabbing"
                :style="{ aspectRatio: String(aspectRatio) }"
                @pointerdown="startDrag"
                @pointermove="onDrag"
                @pointerup="endDrag"
                @pointercancel="endDrag"
            >
                <!-- Decorative: the surrounding dialog carries the label. -->
                <img
                    v-if="sourceUrl"
                    :src="sourceUrl"
                    alt=""
                    draggable="false"
                    class="absolute top-1/2 left-1/2 max-w-none -translate-x-1/2 -translate-y-1/2 will-change-transform"
                    :style="imageStyle"
                    @load="onImageLoad"
                />

                <div
                    v-if="!isReady"
                    class="absolute inset-0 flex items-center justify-center text-muted-foreground"
                >
                    <Loader2 class="h-5 w-5 animate-spin" />
                </div>

                <!-- Avatar ghost so users can see what the profile picture covers -->
                <div
                    class="pointer-events-none absolute bottom-0 left-4 aspect-square h-1/2 w-auto translate-y-1/3 rounded-full border-4 border-background/80 bg-background/20 backdrop-blur-[1px] sm:left-6"
                    aria-hidden="true"
                ></div>

                <div
                    class="pointer-events-none absolute top-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-black/45 px-3 py-1 text-[11px] font-medium text-white backdrop-blur-sm"
                >
                    <Move class="h-3 w-3" />
                    Drag to reposition
                </div>
            </div>

            <!-- Zoom control -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                    aria-label="Zoom out"
                    :disabled="zoom <= MIN_ZOOM"
                    @click="stepZoom(-0.1)"
                >
                    <ZoomOut class="h-4 w-4" />
                </button>

                <input
                    v-model.number="zoom"
                    type="range"
                    :min="MIN_ZOOM"
                    :max="MAX_ZOOM"
                    step="0.01"
                    class="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-muted accent-foreground"
                    aria-label="Zoom level"
                />

                <button
                    type="button"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                    aria-label="Zoom in"
                    :disabled="zoom >= MAX_ZOOM"
                    @click="stepZoom(0.1)"
                >
                    <ZoomIn class="h-4 w-4" />
                </button>
            </div>
        </div>

        <template #footer>
            <Button type="button" variant="ghost" @click="cancel">
                Cancel
            </Button>
            <Button
                type="button"
                :disabled="!isReady || isSaving"
                @click="confirm"
            >
                <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                Use photo
            </Button>
        </template>
    </ResponsiveModal>
</template>
