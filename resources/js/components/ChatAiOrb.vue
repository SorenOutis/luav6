<script setup lang="ts">
import type { Rive } from '@rive-app/webgl2';
import wasmUrl from '@rive-app/webgl2/rive.wasm?url';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg' | 'status';
        state?: 'idle' | 'listening' | 'thinking' | 'speaking' | 'asleep';
        animateIdle?: boolean;
        color?: string;
    }>(),
    { size: 'md', state: 'idle', animateIdle: false },
);

// Command asset and input contract from Vercel AI Elements Persona.
const source =
    'https://ejiidnob33g9ap1r.public.blob.vercel-storage.com/command-2.0.riv';
const canvas = ref<HTMLCanvasElement>();
const ready = ref(false);
const reducedMotion = ref(false);
let rive: Rive | undefined;
let disposed = false;
let visible = true;
let initializing = false;
let failed = false;
let motionQuery: MediaQueryList | undefined;
let resizeObserver: ResizeObserver | undefined;
let visibilityObserver: IntersectionObserver | undefined;
let themeObserver: MutationObserver | undefined;

let welcomeTimer: ReturnType<typeof setTimeout> | undefined;

function parseRgb(colorStr?: string): [number, number, number] | null {
    if (!colorStr) return null;
    const clean = colorStr.trim();
    const hexMatch = clean.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (hexMatch) {
        const hex = hexMatch[1];
        if (hex.length === 3) {
            return [
                parseInt(hex[0] + hex[0], 16),
                parseInt(hex[1] + hex[1], 16),
                parseInt(hex[2] + hex[2], 16),
            ];
        }
        return [
            parseInt(hex.slice(0, 2), 16),
            parseInt(hex.slice(2, 4), 16),
            parseInt(hex.slice(4, 6), 16),
        ];
    }
    const rgbMatch = clean.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (rgbMatch) {
        return [
            parseInt(rgbMatch[1], 10),
            parseInt(rgbMatch[2], 10),
            parseInt(rgbMatch[3], 10),
        ];
    }
    return null;
}

function clearWelcomeTimer() {
    clearTimeout(welcomeTimer);
    welcomeTimer = undefined;
}

function applyState(state: typeof props.state) {
    if (!ready.value || !rive) return;
    for (const input of rive.stateMachineInputs('default') ?? []) {
        if (
            ['listening', 'thinking', 'speaking', 'asleep'].includes(input.name)
        ) {
            input.value = input.name === state;
        }
    }
}

function syncState() {
    clearWelcomeTimer();
    const canCycle =
        ready.value &&
        props.animateIdle &&
        props.state === 'idle' &&
        !reducedMotion.value &&
        !document.hidden &&
        visible &&
        !disposed;
    if (!canCycle) {
        applyState(props.state);
        return;
    }

    // Welcome preview uses Rive's own transitions, not a microphone state.
    function cycle(listening: boolean) {
        applyState(listening ? 'listening' : 'idle');
        welcomeTimer = setTimeout(
            () => cycle(!listening),
            listening ? 2000 : 500,
        );
    }
    cycle(true);
}

function syncTheme() {
    const custom = parseRgb(props.color);
    if (custom) {
        rive?.viewModelInstance
            ?.color('color')
            ?.rgb(custom[0], custom[1], custom[2]);
        return;
    }
    const color = document.documentElement.classList.contains('dark') ? 255 : 0;
    rive?.viewModelInstance?.color('color')?.rgb(color, color, color);
}

function syncPlayback() {
    syncState();
    if (!ready.value || !rive) return;
    if (reducedMotion.value || document.hidden || !visible) {
        rive.stopRendering();
    } else {
        rive.startRendering();
    }
}

function handleFailure() {
    clearWelcomeTimer();
    failed = true;
    ready.value = false;
    rive?.cleanup();
    rive = undefined;
}

async function initialize() {
    if (disposed || initializing || rive || failed || reducedMotion.value)
        return;
    initializing = true;
    try {
        const { Rive, RuntimeLoader } = await import('@rive-app/webgl2');
        if (disposed || reducedMotion.value || !canvas.value) return;
        RuntimeLoader.setWasmUrl(wasmUrl);
        RuntimeLoader.setWasmFallbackUrl(null);
        rive = new Rive({
            canvas: canvas.value,
            src: source,
            stateMachines: 'default',
            autoBind: true,
            autoplay: true,
            onLoad: () => {
                if (disposed || !rive) return;
                ready.value = true;
                rive.resizeDrawingSurfaceToCanvas();
                syncTheme();
                syncPlayback();
            },
            onLoadError: handleFailure,
        });
    } catch {
        handleFailure();
    } finally {
        initializing = false;
    }
}

function syncMotion() {
    reducedMotion.value = motionQuery?.matches ?? false;
    syncPlayback();
    if (!reducedMotion.value) void initialize();
}

function resize() {
    rive?.resizeDrawingSurfaceToCanvas();
}

watch(() => [props.state, props.animateIdle], syncState);
watch(() => props.color, syncTheme);

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    motionQuery.addEventListener('change', syncMotion);
    document.addEventListener('visibilitychange', syncPlayback);
    window.addEventListener('resize', resize);
    themeObserver = new MutationObserver(syncTheme);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    if (canvas.value) {
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(canvas.value);
        visibilityObserver = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            syncPlayback();
        });
        visibilityObserver.observe(canvas.value);
    }
    syncMotion();
});

onBeforeUnmount(() => {
    disposed = true;
    clearWelcomeTimer();
    motionQuery?.removeEventListener('change', syncMotion);
    document.removeEventListener('visibilitychange', syncPlayback);
    window.removeEventListener('resize', resize);
    resizeObserver?.disconnect();
    visibilityObserver?.disconnect();
    themeObserver?.disconnect();
    rive?.cleanup();
    rive = undefined;
});
</script>

<template>
    <div
        class="relative shrink-0 text-foreground select-none"
        :class="{
            'h-24 w-24 sm:h-28 sm:w-28': size === 'md',
            'h-20 w-20': size === 'sm',
            'h-32 w-32': size === 'lg',
            'h-7 w-7': size === 'status',
        }"
        aria-hidden="true"
    >
        <canvas
            ref="canvas"
            class="h-full w-full"
            :class="{
                invisible: !ready || reducedMotion,
                'scale-150': size === 'lg',
            }"
        />
        <span
            v-if="!ready || reducedMotion"
            class="absolute inset-0 flex items-center justify-center font-mono text-[1.5em]"
            :style="props.color ? { color: props.color } : undefined"
            data-persona-fallback
            >E</span
        >
    </div>
</template>
