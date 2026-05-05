<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import gsap from 'gsap';
import MapNode from './MapNode.vue';
import MapPath from './MapPath.vue';
import type { WorldBiome, MapNodeDefinition, PlayerProgress, UnlockRequirement } from '@/config/mapConfig';
import { nodeStatus, evaluateRequirement } from '@/config/mapConfig';
import { Plus, Minus, Locate, CheckCircle2, Play, Lock } from 'lucide-vue-next';

interface Props {
    world: WorldBiome;
    player: PlayerProgress;
    nextNodeSlug?: string | null;
}

const props = withDefaults(defineProps<Props>(), { nextNodeSlug: null });
defineEmits<{ (e: 'node-click', node: MapNodeDefinition): void }>();

// slug → title, used when evaluating prerequisites across worlds.
const titleLookup = computed(() => {
    const map: Record<string, string> = {};
    for (const n of props.world.nodes) map[n.id] = n.title;
    return map;
});

/** Prereq node slugs for drawing path segments — from `requirements` of kind 'node' (or legacy `dependsOn`). */
const prereqSlugs = (node: MapNodeDefinition): string[] => {
    if (node.requirements?.length) {
        return node.requirements
            .filter(r => r.kind === 'node' && r.nodeSlug)
            .map(r => r.nodeSlug as string);
    }
    return node.dependsOn ?? [];
};

const viewport = ref<HTMLElement | null>(null);
const canvas = ref<HTMLElement | null>(null);
const isDragging = ref(false);
// Pointer position at drag start, and canvas translation at drag start
const startPointer = { x: 0, y: 0 };
const startOffset = { x: 0, y: 0 };
const offset = { x: 0, y: 0 };
const zoom = ref(1);
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 1.8;

// Evaluate a node's status via the shared helper (handles node/xp/level/badge/streak).
const getNodeStatus = (node: MapNodeDefinition) => nodeStatus(node, props.player, titleLookup.value);

/** For each node, how many of its unlock requirements are currently met. */
const getReqProgress = (node: MapNodeDefinition): { met: number; total: number } => {
    const reqs: UnlockRequirement[] = node.requirements?.length
        ? node.requirements
        : (node.dependsOn ?? []).map(slug => ({ kind: 'node', nodeSlug: slug }));
    const met = reqs.reduce((acc, r) => acc + (evaluateRequirement(r, props.player, titleLookup.value).met ? 1 : 0), 0);
    return { met, total: reqs.length };
};

// Deterministic starfield — seeded by world id so the sky is stable per biome.
const stars = computed(() => {
    const seedStr = props.world.id;
    let seed = 0;
    for (let i = 0; i < seedStr.length; i++) seed = (seed * 31 + seedStr.charCodeAt(i)) >>> 0;
    const rand = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 0xffffffff; };
    const out: { x: number; y: number; r: number; o: number; d: number }[] = [];
    for (let i = 0; i < 90; i++) {
        out.push({
            x: rand() * 100,
            y: rand() * 100,
            r: 0.4 + rand() * 1.6,
            o: 0.25 + rand() * 0.6,
            d: 2 + rand() * 4,
        });
    }
    return out;
});

const applyOffset = (animate = false) => {
    if (!canvas.value) return;
    if (animate) {
        gsap.to(canvas.value, { x: offset.x, y: offset.y, scale: zoom.value, duration: 0.6, ease: 'power3.out' });
    } else {
        gsap.set(canvas.value, { x: offset.x, y: offset.y, scale: zoom.value });
    }
};

const setZoom = (next: number, animate = true) => {
    const clamped = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, next));
    if (clamped === zoom.value) return;
    zoom.value = clamped;
    applyOffset(animate);
};

const zoomIn = () => setZoom(zoom.value + 0.2);
const zoomOut = () => setZoom(zoom.value - 0.2);

const handleMouseDown = (e: MouseEvent) => {
    isDragging.value = true;
    startPointer.x = e.clientX;
    startPointer.y = e.clientY;
    startOffset.x = offset.x;
    startOffset.y = offset.y;
};

const handleMouseMove = (e: MouseEvent) => {
    if (!isDragging.value) return;
    offset.x = startOffset.x + (e.clientX - startPointer.x);
    offset.y = startOffset.y + (e.clientY - startPointer.y);
    applyOffset(false);
};

const handleMouseUp = () => {
    isDragging.value = false;
};

// --- Touch support (single-finger pan) ---
const handleTouchStart = (e: TouchEvent) => {
    if (e.touches.length !== 1) return;
    isDragging.value = true;
    startPointer.x = e.touches[0].clientX;
    startPointer.y = e.touches[0].clientY;
    startOffset.x = offset.x;
    startOffset.y = offset.y;
};
const handleTouchMove = (e: TouchEvent) => {
    if (!isDragging.value || e.touches.length !== 1) return;
    offset.x = startOffset.x + (e.touches[0].clientX - startPointer.x);
    offset.y = startOffset.y + (e.touches[0].clientY - startPointer.y);
    applyOffset(false);
};
const handleTouchEnd = () => { isDragging.value = false; };

// --- Wheel zoom (centred on cursor) ---
const handleWheel = (e: WheelEvent) => {
    if (!viewport.value || !canvas.value) return;
    e.preventDefault();
    const rect = viewport.value.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const mouseY = e.clientY - rect.top;
    // World point under the cursor (before zoom change)
    const worldX = (mouseX - offset.x) / zoom.value;
    const worldY = (mouseY - offset.y) / zoom.value;

    const delta = -Math.sign(e.deltaY) * 0.12;
    const nextZoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoom.value + delta));
    if (nextZoom === zoom.value) return;

    // Keep the same world point under the cursor after zoom
    offset.x = mouseX - worldX * nextZoom;
    offset.y = mouseY - worldY * nextZoom;
    zoom.value = nextZoom;
    applyOffset(false);
};

const recenter = (animate = true) => {
    if (!viewport.value || props.world.nodes.length === 0) return;
    // Focus on the recommended next node when present, else first node.
    const focusNode = props.nextNodeSlug
        ? (props.world.nodes.find(n => n.id === props.nextNodeSlug) ?? props.world.nodes[0])
        : props.world.nodes[0];
    const rect = viewport.value.getBoundingClientRect();
    zoom.value = 1;
    offset.x = rect.width / 2 - focusNode.x * zoom.value;
    offset.y = rect.height / 2 - focusNode.y * zoom.value;
    applyOffset(animate);
};

const onKey = (e: KeyboardEvent) => {
    if (e.target && (e.target as HTMLElement).closest('input, textarea')) return;
    const step = 60;
    if (e.key === 'ArrowLeft')  { offset.x += step; applyOffset(true); }
    if (e.key === 'ArrowRight') { offset.x -= step; applyOffset(true); }
    if (e.key === 'ArrowUp')    { offset.y += step; applyOffset(true); }
    if (e.key === 'ArrowDown')  { offset.y -= step; applyOffset(true); }
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-' || e.key === '_') zoomOut();
    if (e.key === '0') recenter(true);
};

onMounted(() => {
    recenter(false);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

// Recenter when switching worlds
watch(() => props.world.id, () => recenter(true));
</script>

<template>
    <div 
        ref="viewport"
        class="relative w-full h-full overflow-hidden cursor-grab active:cursor-grabbing select-none"
        @mousedown="handleMouseDown"
        @mousemove="handleMouseMove"
        @mouseup="handleMouseUp"
        @mouseleave="handleMouseUp"
        @touchstart.passive="handleTouchStart"
        @touchmove.passive="handleTouchMove"
        @touchend="handleTouchEnd"
        @wheel="handleWheel"
    >
        <!-- Background Layer (Parallax + Starfield) -->
        <div class="absolute inset-0 z-0 pointer-events-none">
            <div 
                class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_center,var(--tw-gradient-from)_0%,transparent_70%)]"
                :class="world.theme.background"
            ></div>
            <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 100">
                <circle
                    v-for="(s, i) in stars"
                    :key="i"
                    :cx="s.x"
                    :cy="s.y"
                    :r="s.r * 0.15"
                    :fill="world.theme.primary"
                    :opacity="s.o"
                    class="map-star"
                    :style="{ animationDuration: `${s.d}s` }"
                />
            </svg>
        </div>

        <!-- The Map Canvas -->
        <div ref="canvas" class="absolute z-10 w-[2000px] h-[2000px]">
            <!-- Render Paths First (Underneath Nodes) -->
            <template v-for="node in world.nodes" :key="'path-' + node.id">
                <template v-for="depId in prereqSlugs(node)" :key="`${depId}-${node.id}`">
                    <MapPath 
                        v-if="world.nodes.find(n => n.id === depId)"
                        :id="`${depId}-${node.id}`"
                        :startX="world.nodes.find(n => n.id === depId)!.x"
                        :startY="world.nodes.find(n => n.id === depId)!.y"
                        :endX="node.x"
                        :endY="node.y"
                        :status="getNodeStatus(node)"
                        :availableColor="world.theme.primary"
                    />
                </template>
            </template>

            <!-- Render Nodes -->
            <MapNode 
                v-for="node in world.nodes" 
                :key="node.id"
                :title="node.title"
                :type="node.type"
                :x="node.x"
                :y="node.y"
                :status="getNodeStatus(node)"
                :primaryColor="world.theme.primary"
                :rewardXp="node.rewards?.xp ?? 0"
                :metReqs="getReqProgress(node).met"
                :totalReqs="getReqProgress(node).total"
                :isNext="node.id === nextNodeSlug"
                @click="$emit('node-click', node)"
            />
        </div>

        <!-- Zoom / Recenter controls -->
        <div class="absolute right-4 bottom-24 z-30 flex flex-col gap-1.5 p-1 bg-white/5 border border-white/10 rounded-full backdrop-blur-md pointer-events-auto">
            <button type="button" @click="zoomIn" title="Zoom in (+)"
                class="w-8 h-8 flex items-center justify-center rounded-full text-white/70 hover:text-white hover:bg-white/10 transition">
                <Plus class="w-4 h-4" />
            </button>
            <button type="button" @click="zoomOut" title="Zoom out (-)"
                class="w-8 h-8 flex items-center justify-center rounded-full text-white/70 hover:text-white hover:bg-white/10 transition">
                <Minus class="w-4 h-4" />
            </button>
            <button type="button" @click="recenter(true)" title="Recenter on next objective (0)"
                class="w-8 h-8 flex items-center justify-center rounded-full text-white/70 hover:text-white hover:bg-white/10 transition">
                <Locate class="w-4 h-4" />
            </button>
        </div>

        <!-- Legend -->
        <div class="absolute left-4 bottom-24 z-30 flex flex-col gap-1.5 px-3 py-2 bg-white/5 border border-white/10 rounded-2xl backdrop-blur-md pointer-events-none">
            <div class="flex items-center gap-2 text-[10px] uppercase tracking-widest text-white/40 font-semibold">Legend</div>
            <div class="flex items-center gap-2 text-xs text-white/70">
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" /> Completed
            </div>
            <div class="flex items-center gap-2 text-xs text-white/70">
                <Play class="w-3.5 h-3.5" :style="{ color: world.theme.primary }" /> Available
            </div>
            <div class="flex items-center gap-2 text-xs text-white/70">
                <Lock class="w-3.5 h-3.5 text-white/40" /> Locked
            </div>
            <div class="mt-1 pt-1 border-t border-white/10 text-[10px] text-white/40 tracking-wide">
                Scroll to zoom · Drag to pan · Arrows / 0
            </div>
        </div>
    </div>
</template>

<style scoped>
.cursor-grab { cursor: grab; }
.cursor-grabbing { cursor: grabbing; }

.map-star {
    animation-name: twinkle;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
    transform-origin: center;
}
@keyframes twinkle {
    0%, 100% { opacity: 0.25; }
    50%      { opacity: 0.85; }
}
</style>
