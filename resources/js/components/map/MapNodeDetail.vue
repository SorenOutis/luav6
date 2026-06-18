<script setup lang="ts">
import { Motion, Presence } from '@motionone/vue';
import {
    X,
    Lock,
    CheckCircle2,
    Circle,
    Play,
    Star,
    ArrowRight,
    Zap,
    Trophy,
    Award,
    Flame,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type {
    WorldBiome,
    MapNodeDefinition,
    PlayerProgress,
    UnlockRequirement,
} from '@/config/mapConfig';
import { evaluateRequirement, nodeStatus } from '@/config/mapConfig';

interface Props {
    node: MapNodeDefinition | null;
    world: WorldBiome;
    player: PlayerProgress;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'start', node: MapNodeDefinition): void;
}>();

const titleLookup = computed(() => {
    const map: Record<string, string> = {};
    for (const n of props.world.nodes) map[n.id] = n.title;
    return map;
});

const status = computed<'locked' | 'available' | 'completed'>(() => {
    if (!props.node) return 'locked';
    return nodeStatus(props.node, props.player, titleLookup.value);
});

/** Normalised list of requirements — upgrades legacy `dependsOn` to `{ kind: 'node' }`. */
const requirements = computed<UnlockRequirement[]>(() => {
    if (!props.node) return [];
    if (props.node.requirements?.length) return props.node.requirements;
    return (props.node.dependsOn ?? []).map((slug) => ({
        kind: 'node',
        nodeSlug: slug,
    }));
});

const evaluated = computed(() =>
    requirements.value.map((req) => {
        const evalResult = evaluateRequirement(
            req,
            props.player,
            titleLookup.value,
        );
        return { req, ...evalResult };
    }),
);

const metCount = computed(() => evaluated.value.filter((e) => e.met).length);

const iconForKind = (kind: UnlockRequirement['kind']) => {
    switch (kind) {
        case 'node':
            return CheckCircle2;
        case 'xp':
            return Zap;
        case 'level':
            return Trophy;
        case 'badge':
            return Award;
        case 'streak':
            return Flame;
    }
};

const headlineForReq = (req: UnlockRequirement): string => {
    switch (req.kind) {
        case 'node':
            return (
                titleLookup.value[req.nodeSlug ?? ''] ??
                req.nodeSlug ??
                'Prerequisite'
            );
        case 'xp':
            return `Earn ${req.amount?.toLocaleString() ?? 0} XP`;
        case 'level':
            return `Reach level ${req.level ?? 1}`;
        case 'badge':
            return `Earn badge #${req.badgeId}`;
        case 'streak':
            return `Maintain a ${req.amount ?? 0}-day streak`;
    }
};

const typeLabel = (t: MapNodeDefinition['type']) =>
    t === 'boss' ? 'Boss Challenge' : t === 'exam' ? 'Exam' : 'Lesson';

const typeIcon = (t: MapNodeDefinition['type']) => (t === 'boss' ? Star : Play);

const statusLabel = computed(() => {
    if (status.value === 'completed') return 'Completed';
    if (status.value === 'available') return 'Ready to start';
    return 'Locked';
});

const primaryActionLabel = computed(() => {
    if (status.value === 'completed') return 'Review';
    if (props.node?.type === 'boss') return 'Challenge';
    return 'Begin';
});
</script>

<template>
    <Presence>
        <Motion
            v-if="node"
            :initial="{ opacity: 0 }"
            :animate="{ opacity: 1 }"
            :exit="{ opacity: 0 }"
            :transition="{ duration: 0.2 }"
            class="absolute inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="emit('close')"
        >
            <Motion
                :initial="{ opacity: 0, y: 20, scale: 0.96 }"
                :animate="{ opacity: 1, y: 0, scale: 1 }"
                :exit="{ opacity: 0, y: 20, scale: 0.96 }"
                :transition="{ duration: 0.25 }"
                class="relative w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-[#0b0b0d] shadow-2xl"
            >
                <!-- Accent bar -->
                <div
                    class="absolute inset-x-0 top-0 h-1"
                    :style="{ backgroundColor: world.theme.primary }"
                ></div>

                <button
                    class="absolute top-3 right-3 rounded-full p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="p-6 pt-7">
                    <!-- Header -->
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2"
                            :style="{
                                borderColor:
                                    status === 'locked'
                                        ? '#475569'
                                        : status === 'completed'
                                          ? '#10b981'
                                          : world.theme.primary,
                                backgroundColor:
                                    status === 'completed'
                                        ? 'rgba(16,185,129,0.1)'
                                        : 'rgba(255,255,255,0.04)',
                            }"
                        >
                            <component
                                :is="
                                    status === 'locked'
                                        ? Lock
                                        : status === 'completed'
                                          ? CheckCircle2
                                          : typeIcon(node.type)
                                "
                                class="h-5 w-5"
                                :style="{
                                    color:
                                        status === 'locked'
                                            ? '#64748b'
                                            : status === 'completed'
                                              ? '#10b981'
                                              : world.theme.primary,
                                }"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                            >
                                {{ typeLabel(node.type) }} · {{ world.name }}
                            </p>
                            <h2
                                class="truncate text-lg font-semibold tracking-tight text-white"
                            >
                                {{ node.title }}
                            </h2>
                            <p
                                class="mt-0.5 text-xs font-medium"
                                :style="{
                                    color:
                                        status === 'locked'
                                            ? '#94a3b8'
                                            : status === 'completed'
                                              ? '#10b981'
                                              : world.theme.primary,
                                }"
                            >
                                {{ statusLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Prerequisites -->
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <h3
                                class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                            >
                                Unlock Requirements
                            </h3>
                            <span
                                v-if="evaluated.length"
                                class="text-[10px] text-white/50 tabular-nums"
                            >
                                {{ metCount }} / {{ evaluated.length }}
                            </span>
                        </div>

                        <div
                            v-if="!evaluated.length"
                            class="rounded-lg border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-white/60"
                        >
                            No requirements — this node is an entry point.
                        </div>

                        <ul v-else class="space-y-1.5">
                            <li
                                v-for="(item, idx) in evaluated"
                                :key="idx"
                                class="flex flex-col gap-2 rounded-lg border px-3 py-2.5 transition"
                                :class="
                                    item.met
                                        ? 'border-emerald-500/30 bg-emerald-500/10'
                                        : 'border-white/10 bg-white/5'
                                "
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="
                                            item.met
                                                ? CheckCircle2
                                                : iconForKind(item.req.kind)
                                        "
                                        class="h-4 w-4 shrink-0"
                                        :class="
                                            item.met
                                                ? 'text-emerald-400'
                                                : 'text-white/50'
                                        "
                                    />
                                    <span
                                        class="flex-1 truncate text-sm"
                                        :class="
                                            item.met
                                                ? 'text-white'
                                                : 'text-white/80'
                                        "
                                    >
                                        {{ headlineForReq(item.req) }}
                                    </span>
                                    <span
                                        class="text-[10px] font-semibold tracking-widest uppercase tabular-nums"
                                        :class="
                                            item.met
                                                ? 'text-emerald-400'
                                                : 'text-white/40'
                                        "
                                    >
                                        {{ item.met ? 'Done' : item.detail }}
                                    </span>
                                </div>
                                <!-- Progress bar for partial requirements (xp/level/streak) -->
                                <div
                                    v-if="
                                        !item.met &&
                                        item.progress > 0 &&
                                        item.req.kind !== 'node' &&
                                        item.req.kind !== 'badge'
                                    "
                                    class="h-1 overflow-hidden rounded-full bg-white/5"
                                >
                                    <div
                                        class="h-full rounded-full transition-[width] duration-500"
                                        :style="{
                                            width: `${Math.round(item.progress * 100)}%`,
                                            backgroundColor:
                                                world.theme.primary,
                                        }"
                                    ></div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Action -->
                    <div class="mt-6 flex items-center gap-2">
                        <button
                            v-if="status !== 'locked'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-black transition hover:brightness-110"
                            :style="{
                                backgroundColor:
                                    status === 'completed'
                                        ? '#10b981'
                                        : world.theme.primary,
                            }"
                            @click="emit('start', node)"
                        >
                            {{ primaryActionLabel }}
                            <ArrowRight class="h-4 w-4" />
                        </button>
                        <div
                            v-else
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white/50"
                        >
                            <Lock class="h-4 w-4" />
                            {{ evaluated.length - metCount }} requirement{{
                                evaluated.length - metCount === 1 ? '' : 's'
                            }}
                            remaining
                        </div>
                        <button
                            class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white/70 transition hover:bg-white/10"
                            @click="emit('close')"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </Motion>
        </Motion>
    </Presence>
</template>
