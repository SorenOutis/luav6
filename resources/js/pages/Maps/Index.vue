<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Motion, Presence } from '@motionone/vue';
import { Trophy, Zap, Map as MapIcon, Target, Flame } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import MapNodeDetail from '@/components/map/MapNodeDetail.vue';
import MapWorld from '@/components/map/MapWorld.vue';
import MapWorldDetail from '@/components/map/MapWorldDetail.vue';
import WorldSwitcher from '@/components/map/WorldSwitcher.vue';
import {
    MAP_CONFIG
    
    
    
} from '@/config/mapConfig';
import type {MapNodeDefinition, WorldBiome, PlayerProgress} from '@/config/mapConfig';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
    worlds?: WorldBiome[];
    player?: PlayerProgress;
}

const props = withDefaults(defineProps<Props>(), {
    worlds: () => [],
    player: () => ({
        xp: 0,
        level: 1,
        points: 0,
        streakDays: 0,
        completedNodeSlugs: [],
        badgeIds: [],
    }),
});

// Fall back to hard-coded config until the DB is seeded.
const worlds = computed<WorldBiome[]>(() =>
    props.worlds.length ? props.worlds : MAP_CONFIG,
);

// A world is considered unlocked if ANY of its nodes are available/completed,
// OR if it's the first world. Computed from player progress.
const unlockedWorldIds = computed(() => {
    const ids = new Set<string>();
    worlds.value.forEach((w, i) => {
        if (i === 0) {
            ids.add(w.id);
            return;
        }
        const hasAvailable = w.nodes.some(
            (n) =>
                props.player.completedNodeSlugs.includes(n.id) ||
                (!n.requirements?.length && !n.dependsOn?.length),
        );
        if (hasAvailable) ids.add(w.id);
    });
    return Array.from(ids);
});

const currentWorldId = ref(worlds.value[0]?.id ?? 'origin-springs');

const currentWorld = computed<WorldBiome>(
    () =>
        worlds.value.find((w) => w.id === currentWorldId.value) ||
        worlds.value[0],
);

const selectedNode = ref<MapNodeDefinition | null>(null);

const handleNodeClick = (node: MapNodeDefinition) => {
    selectedNode.value = node;
};

const handleCloseDetail = () => {
    selectedNode.value = null;
};

const handleStartNode = (node: MapNodeDefinition) => {
    // If the node is linked to an Exam, navigate to it — completion is then
    // driven server-side by ExamSubmission. Otherwise fall back to the
    // dev completion endpoint (useful for placeholder/lesson nodes).
    if (node.target && node.target.type === 'Exam') {
        router.visit(`/exams/${node.target.id}`);
        return;
    }

    router.post(
        `/maps/nodes/${node.id}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                selectedNode.value = null;
            },
        },
    );
};

// Totals for the HUD
const totalNodes = computed(() =>
    worlds.value.reduce((sum, w) => sum + w.nodes.length, 0),
);
const completedCount = computed(() => props.player.completedNodeSlugs.length);

// Next-recommended node — falls back to first available if backend didn't hint.
const nextNode = computed<MapNodeDefinition | null>(() => {
    const hinted = props.player.nextNodeSlug;
    for (const w of worlds.value) {
        if (hinted) {
            const hit = w.nodes.find((n) => n.id === hinted);
            if (hit) return hit;
        }
    }
    // Fallback scan: first uncompleted node with no reqs.
    for (const w of worlds.value) {
        for (const n of w.nodes) {
            if (props.player.completedNodeSlugs.includes(n.id)) continue;
            const reqs = n.requirements?.length
                ? n.requirements
                : (n.dependsOn ?? []).map((s) => ({
                      kind: 'node' as const,
                      nodeSlug: s,
                  }));
            if (!reqs.length) return n;
        }
    }
    return null;
});

const focusNextNode = () => {
    if (!nextNode.value) return;
    const owner = worlds.value.find((w) =>
        w.nodes.some((n) => n.id === nextNode.value!.id),
    );
    if (
        owner &&
        owner.id !== currentWorldId.value &&
        unlockedWorldIds.value.includes(owner.id)
    ) {
        currentWorldId.value = owner.id;
    }
    selectedNode.value = nextNode.value;
};

// XP bar: fraction inside the current level bracket (0..1).
const xpBarProgress = computed(() => {
    const span = props.player.xpForNextLevel ?? 0;
    const into = props.player.xpIntoLevel ?? 0;
    if (span <= 0) return 0;
    return Math.max(0, Math.min(1, into / span));
});

const previewWorld = ref<WorldBiome | null>(null);

const handleWorldSelect = (worldId: string) => {
    const target = worlds.value.find((w) => w.id === worldId);
    if (!target) return;

    // Locked biome → open preview panel instead of switching.
    if (!unlockedWorldIds.value.includes(worldId)) {
        previewWorld.value = target;
        return;
    }

    // Clicking the current biome → open its summary. Otherwise switch biomes.
    if (worldId === currentWorldId.value) {
        previewWorld.value = target;
        return;
    }

    currentWorldId.value = worldId;
};

const openCurrentWorldDetail = () => {
    previewWorld.value = currentWorld.value;
};

const handleWorldDetailNode = (node: MapNodeDefinition) => {
    // If the user taps a node from the summary panel, focus on it.
    // If it's in a different biome, switch to that biome first.
    const owner = worlds.value.find((w) =>
        w.nodes.some((n) => n.id === node.id),
    );
    if (
        owner &&
        owner.id !== currentWorldId.value &&
        unlockedWorldIds.value.includes(owner.id)
    ) {
        currentWorldId.value = owner.id;
    }
    previewWorld.value = null;
    selectedNode.value = node;
};
</script>

<template>
    <AppLayout title="Learning Journey">
        <div
            class="relative h-[calc(100dvh-4rem-5rem)] w-full overflow-hidden bg-[#050505] md:h-[calc(100dvh-4rem)]"
        >
            <!-- Top HUD: progress + streak (left), current world (right) -->
            <div
                class="pointer-events-none absolute top-4 right-4 left-4 z-40 flex items-center justify-between gap-4"
            >
                <div class="pointer-events-auto flex items-center gap-2">
                    <div
                        class="flex items-center gap-2.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 backdrop-blur-lg"
                    >
                        <Trophy class="h-4 w-4 shrink-0 text-emerald-400" />
                        <span
                            class="text-[10px] font-semibold tracking-widest text-white/50 uppercase"
                            >Done</span
                        >
                        <span
                            class="text-sm font-medium text-white tabular-nums"
                            >{{ completedCount }} / {{ totalNodes }}</span
                        >
                    </div>

                    <!-- Level + XP bar toward next level -->
                    <div
                        class="group flex min-w-[180px] items-center gap-2.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 backdrop-blur-lg"
                        :title="`${(player.xpIntoLevel ?? 0).toLocaleString()} of ${(player.xpForNextLevel ?? 0).toLocaleString()} XP to level ${player.level + 1}`"
                    >
                        <span
                            class="text-[10px] font-semibold tracking-widest text-white/50 uppercase"
                            >Level</span
                        >
                        <span
                            class="text-sm font-medium text-white tabular-nums"
                            >{{ player.level }}</span
                        >
                        <div
                            class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-white/10"
                        >
                            <div
                                class="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-emerald-400 to-emerald-300 transition-[width] duration-700"
                                :style="{
                                    width: `${Math.round(xpBarProgress * 100)}%`,
                                }"
                            ></div>
                        </div>
                        <span class="text-[10px] text-white/60 tabular-nums"
                            >{{ player.xp.toLocaleString() }} XP</span
                        >
                    </div>

                    <div
                        class="flex items-center gap-2.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 backdrop-blur-lg"
                    >
                        <Flame class="h-4 w-4 shrink-0 text-amber-400" />
                        <span
                            class="text-[10px] font-semibold tracking-widest text-white/50 uppercase"
                            >Streak</span
                        >
                        <span
                            class="text-sm font-medium text-white tabular-nums"
                            >{{ player.streakDays }}d</span
                        >
                    </div>

                    <!-- Next-up hint: jumps the camera + opens that node's detail. -->
                    <button
                        v-if="nextNode"
                        type="button"
                        @click="focusNextNode"
                        class="flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/15 px-3 py-1.5 transition hover:bg-emerald-500/25"
                        title="Go to your next step"
                    >
                        <Target class="h-4 w-4 shrink-0 text-emerald-300" />
                        <span
                            class="text-[10px] font-semibold tracking-widest text-emerald-300/80 uppercase"
                            >Up next</span
                        >
                        <span
                            class="max-w-[200px] truncate text-sm font-medium tracking-tight text-white"
                            >{{ nextNode.title }}</span
                        >
                    </button>
                </div>

                <button
                    type="button"
                    class="pointer-events-auto flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 backdrop-blur-xl transition hover:bg-white/15"
                    @click="openCurrentWorldDetail"
                    :title="`See what's in ${currentWorld.name}`"
                >
                    <MapIcon class="h-4 w-4 shrink-0 text-white" />
                    <span
                        class="text-sm font-medium tracking-tight text-white"
                        >{{ currentWorld.name }}</span
                    >
                    <span
                        class="text-[10px] font-semibold tracking-widest text-white/50 uppercase"
                        >Details</span
                    >
                </button>
            </div>

            <!-- The World Map Container -->
            <Presence exit-before-enter>
                <Motion
                    :key="currentWorldId"
                    :initial="{ opacity: 0, scale: 1.1 }"
                    :animate="{ opacity: 1, scale: 1 }"
                    :exit="{ opacity: 0, scale: 0.9 }"
                    :transition="{ duration: 0.8 }"
                    class="h-full w-full"
                >
                    <MapWorld
                        :world="currentWorld"
                        :player="player"
                        :nextNodeSlug="nextNode?.id ?? null"
                        @node-click="handleNodeClick"
                    />
                </Motion>
            </Presence>

            <!-- World Navigation -->
            <WorldSwitcher
                :worlds="worlds"
                :currentWorldId="currentWorldId"
                :unlockedWorldIds="unlockedWorldIds"
                @select="handleWorldSelect"
            />

            <!-- World Summary / Unlock Context -->
            <MapWorldDetail
                :world="previewWorld"
                :player="player"
                :unlocked="
                    previewWorld
                        ? unlockedWorldIds.includes(previewWorld.id)
                        : false
                "
                @close="previewWorld = null"
                @select-node="handleWorldDetailNode"
            />

            <!-- Node Detail / Unlock Breakdown -->
            <MapNodeDetail
                :node="selectedNode"
                :world="currentWorld"
                :player="player"
                @close="handleCloseDetail"
                @start="handleStartNode"
            />
        </div>
    </AppLayout>
</template>

<style>
/* Global map styles to ensure the "Quiet Luxury" look */
body {
    background-color: #050505;
}

/* Custom scrollbar for map if needed */
::-webkit-scrollbar {
    width: 0px;
    background: transparent;
}
</style>
