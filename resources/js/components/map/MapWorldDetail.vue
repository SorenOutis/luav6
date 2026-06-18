<script setup lang="ts">
import { Motion, Presence } from '@motionone/vue';
import {
    X,
    Lock,
    CheckCircle2,
    Play,
    Star,
    Zap,
    Trophy,
    Award,
    Flame,
    Info,
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
    world: WorldBiome | null;
    player: PlayerProgress;
    unlocked: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select-node', node: MapNodeDefinition): void;
}>();

const titleLookup = computed(() => {
    const map: Record<string, string> = {};
    if (!props.world) return map;
    for (const n of props.world.nodes) map[n.id] = n.title;
    return map;
});

const nodeSummaries = computed(() => {
    if (!props.world) return [];
    return props.world.nodes.map((node) => {
        const status = nodeStatus(node, props.player, titleLookup.value);
        const requirements = node.requirements?.length
            ? node.requirements
            : (node.dependsOn ?? []).map<UnlockRequirement>((slug) => ({
                  kind: 'node',
                  nodeSlug: slug,
              }));
        const evals = requirements.map((req) => ({
            req,
            ...evaluateRequirement(req, props.player, titleLookup.value),
        }));
        const met = evals.filter((e) => e.met).length;
        return { node, status, evals, met, total: evals.length };
    });
});

const totals = computed(() => {
    const s = { completed: 0, available: 0, locked: 0 };
    for (const n of nodeSummaries.value) s[n.status]++;
    return s;
});

const nextUp = computed(
    () =>
        nodeSummaries.value.find((n) => n.status === 'available') ??
        nodeSummaries.value.find((n) => n.status === 'locked'),
);

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

const summaryForReq = (req: UnlockRequirement): string => {
    switch (req.kind) {
        case 'node':
            return titleLookup.value[req.nodeSlug ?? ''] ?? req.nodeSlug ?? '—';
        case 'xp':
            return `${req.amount?.toLocaleString() ?? 0} XP`;
        case 'level':
            return `Level ${req.level ?? 1}`;
        case 'badge':
            return `Badge #${req.badgeId}`;
        case 'streak':
            return `${req.amount ?? 0}-day streak`;
    }
};

const typeIcon = (t: MapNodeDefinition['type']) => (t === 'boss' ? Star : Play);
</script>

<template>
    <Presence>
        <Motion
            v-if="world"
            :initial="{ opacity: 0 }"
            :animate="{ opacity: 1 }"
            :exit="{ opacity: 0 }"
            :transition="{ duration: 0.2 }"
            class="absolute inset-0 z-50 flex items-stretch justify-end bg-black/60 backdrop-blur-sm"
            @click.self="emit('close')"
        >
            <Motion
                :initial="{ x: '100%' }"
                :animate="{ x: 0 }"
                :exit="{ x: '100%' }"
                :transition="{ duration: 0.3 }"
                class="relative h-full w-full max-w-md overflow-y-auto border-l border-white/10 bg-[#0b0b0d] shadow-2xl"
            >
                <!-- Accent bar -->
                <div
                    class="sticky top-0 z-10 h-1"
                    :style="{ backgroundColor: world.theme.primary }"
                ></div>

                <button
                    class="absolute top-4 right-4 z-20 rounded-full p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <X class="h-4 w-4" />
                </button>

                <div class="space-y-6 p-6">
                    <!-- Header -->
                    <header>
                        <p
                            class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                        >
                            Biome
                        </p>
                        <h2
                            class="text-2xl font-semibold tracking-tight text-white"
                        >
                            {{ world.name }}
                        </h2>
                        <p
                            class="mt-1 text-xs font-medium"
                            :style="{ color: world.theme.primary }"
                        >
                            {{ unlocked ? 'Unlocked' : 'Locked biome' }}
                        </p>
                    </header>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            class="rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                            >
                                Done
                            </p>
                            <p
                                class="text-xl font-semibold text-emerald-400 tabular-nums"
                            >
                                {{ totals.completed }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                            >
                                Open
                            </p>
                            <p
                                class="text-xl font-semibold tabular-nums"
                                :style="{ color: world.theme.primary }"
                            >
                                {{ totals.available }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                            >
                                Locked
                            </p>
                            <p
                                class="text-xl font-semibold text-white/50 tabular-nums"
                            >
                                {{ totals.locked }}
                            </p>
                        </div>
                    </div>

                    <!-- How unlocking works -->
                    <section
                        class="rounded-xl border border-white/10 bg-white/5 p-4"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <Info class="h-4 w-4 text-white/60" />
                            <h3
                                class="text-[11px] font-semibold tracking-widest text-white/60 uppercase"
                            >
                                How unlocking works
                            </h3>
                        </div>
                        <ul
                            class="space-y-1.5 text-sm leading-relaxed text-white/70"
                        >
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span
                                    >Complete a node's lesson/exam to turn it
                                    <span class="font-medium text-emerald-400"
                                        >green</span
                                    >.</span
                                >
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span
                                    >Each node has requirements (prerequisite
                                    nodes, XP totals, level, badges, or daily
                                    streaks).
                                    <span class="font-medium text-white"
                                        >All</span
                                    >
                                    must be met to unlock it.</span
                                >
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span
                                    >Clearing a boss unlocks the next biome in
                                    the journey.</span
                                >
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span
                                    >Your current progress:
                                    <span
                                        class="font-medium text-white tabular-nums"
                                        >Lv {{ player.level }} &middot;
                                        {{ player.xp.toLocaleString() }} XP
                                        &middot; {{ player.streakDays }}d
                                        streak</span
                                    >.</span
                                >
                            </li>
                        </ul>
                    </section>

                    <!-- Next up -->
                    <section
                        v-if="nextUp"
                        class="rounded-xl border p-4"
                        :style="{
                            borderColor: `${world.theme.primary}55`,
                            backgroundColor: `${world.theme.primary}10`,
                        }"
                    >
                        <p
                            class="mb-1 text-[10px] font-semibold tracking-widest text-white/50 uppercase"
                        >
                            Next up
                        </p>
                        <button
                            class="flex w-full items-center gap-3 text-left"
                            @click="emit('select-node', nextUp.node)"
                        >
                            <component
                                :is="
                                    nextUp.status === 'locked'
                                        ? Lock
                                        : typeIcon(nextUp.node.type)
                                "
                                class="h-5 w-5 shrink-0"
                                :style="{ color: world.theme.primary }"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-white">
                                    {{ nextUp.node.title }}
                                </p>
                                <p class="text-xs text-white/60">
                                    {{
                                        nextUp.status === 'available'
                                            ? 'Ready to start'
                                            : `${nextUp.total - nextUp.met} requirement${nextUp.total - nextUp.met === 1 ? '' : 's'} remaining`
                                    }}
                                </p>
                            </div>
                        </button>
                    </section>

                    <!-- All nodes -->
                    <section>
                        <h3
                            class="mb-2 text-[11px] font-semibold tracking-widest text-white/40 uppercase"
                        >
                            All nodes
                        </h3>
                        <ul class="space-y-1.5">
                            <li
                                v-for="item in nodeSummaries"
                                :key="item.node.id"
                            >
                                <button
                                    class="flex w-full items-start gap-3 rounded-lg border px-3 py-2.5 text-left transition hover:bg-white/10"
                                    :class="
                                        item.status === 'completed'
                                            ? 'border-emerald-500/30 bg-emerald-500/10'
                                            : item.status === 'available'
                                              ? 'border-white/20 bg-white/5'
                                              : 'border-white/10 bg-white/[0.03]'
                                    "
                                    @click="emit('select-node', item.node)"
                                >
                                    <component
                                        :is="
                                            item.status === 'completed'
                                                ? CheckCircle2
                                                : item.status === 'locked'
                                                  ? Lock
                                                  : typeIcon(item.node.type)
                                        "
                                        class="mt-0.5 h-4 w-4 shrink-0"
                                        :class="
                                            item.status === 'completed'
                                                ? 'text-emerald-400'
                                                : item.status === 'locked'
                                                  ? 'text-white/40'
                                                  : ''
                                        "
                                        :style="
                                            item.status === 'available'
                                                ? { color: world.theme.primary }
                                                : {}
                                        "
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="truncate text-sm font-medium"
                                                :class="
                                                    item.status === 'locked'
                                                        ? 'text-white/60'
                                                        : 'text-white'
                                                "
                                                >{{ item.node.title }}</span
                                            >
                                            <span
                                                v-if="item.node.type === 'boss'"
                                                class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[9px] font-bold tracking-widest text-amber-300 uppercase"
                                                >Boss</span
                                            >
                                        </div>

                                        <!-- Requirement chips -->
                                        <div
                                            v-if="item.evals.length"
                                            class="mt-1 flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="(ev, i) in item.evals"
                                                :key="i"
                                                class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] tabular-nums"
                                                :class="
                                                    ev.met
                                                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                                                        : 'border-white/10 bg-white/5 text-white/60'
                                                "
                                            >
                                                <component
                                                    :is="
                                                        iconForKind(ev.req.kind)
                                                    "
                                                    class="h-3 w-3"
                                                />
                                                {{ summaryForReq(ev.req) }}
                                            </span>
                                        </div>
                                        <p
                                            v-else
                                            class="mt-0.5 text-[11px] text-white/40"
                                        >
                                            Entry point — no requirements.
                                        </p>
                                    </div>
                                    <span
                                        v-if="item.total"
                                        class="mt-0.5 shrink-0 text-[10px] tabular-nums"
                                        :class="
                                            item.status === 'completed'
                                                ? 'text-emerald-400'
                                                : 'text-white/40'
                                        "
                                        >{{ item.met }}/{{ item.total }}</span
                                    >
                                </button>
                            </li>
                        </ul>
                    </section>
                </div>
            </Motion>
        </Motion>
    </Presence>
</template>
