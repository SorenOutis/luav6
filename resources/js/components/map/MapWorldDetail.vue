<script setup lang="ts">
import { computed } from 'vue';
import { Motion, Presence } from '@motionone/vue';
import { X, Lock, CheckCircle2, Circle, Play, Star, Zap, Trophy, Award, Flame, Info } from 'lucide-vue-next';
import type { WorldBiome, MapNodeDefinition, PlayerProgress, UnlockRequirement } from '@/config/mapConfig';
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
    return props.world.nodes.map(node => {
        const status = nodeStatus(node, props.player, titleLookup.value);
        const requirements = node.requirements?.length
            ? node.requirements
            : (node.dependsOn ?? []).map<UnlockRequirement>(slug => ({ kind: 'node', nodeSlug: slug }));
        const evals = requirements.map(req => ({
            req,
            ...evaluateRequirement(req, props.player, titleLookup.value),
        }));
        const met = evals.filter(e => e.met).length;
        return { node, status, evals, met, total: evals.length };
    });
});

const totals = computed(() => {
    const s = { completed: 0, available: 0, locked: 0 };
    for (const n of nodeSummaries.value) s[n.status]++;
    return s;
});

const nextUp = computed(() =>
    nodeSummaries.value.find(n => n.status === 'available')
    ?? nodeSummaries.value.find(n => n.status === 'locked')
);

const iconForKind = (kind: UnlockRequirement['kind']) => {
    switch (kind) {
        case 'node':   return CheckCircle2;
        case 'xp':     return Zap;
        case 'level':  return Trophy;
        case 'badge':  return Award;
        case 'streak': return Flame;
    }
};

const summaryForReq = (req: UnlockRequirement): string => {
    switch (req.kind) {
        case 'node':   return titleLookup.value[req.nodeSlug ?? ''] ?? (req.nodeSlug ?? '—');
        case 'xp':     return `${req.amount?.toLocaleString() ?? 0} XP`;
        case 'level':  return `Level ${req.level ?? 1}`;
        case 'badge':  return `Badge #${req.badgeId}`;
        case 'streak': return `${req.amount ?? 0}-day streak`;
    }
};

const typeIcon = (t: MapNodeDefinition['type']) =>
    t === 'boss' ? Star : Play;
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
                class="relative w-full max-w-md h-full overflow-y-auto border-l border-white/10 bg-[#0b0b0d] shadow-2xl"
            >
                <!-- Accent bar -->
                <div
                    class="sticky top-0 z-10 h-1"
                    :style="{ backgroundColor: world.theme.primary }"
                ></div>

                <button
                    class="absolute top-4 right-4 p-1.5 rounded-full text-white/60 hover:text-white hover:bg-white/10 transition z-20"
                    @click="emit('close')"
                    aria-label="Close"
                >
                    <X class="w-4 h-4" />
                </button>

                <div class="p-6 space-y-6">
                    <!-- Header -->
                    <header>
                        <p class="text-[10px] uppercase tracking-widest text-white/40 font-semibold">Biome</p>
                        <h2 class="text-2xl font-semibold text-white tracking-tight">{{ world.name }}</h2>
                        <p
                            class="text-xs font-medium mt-1"
                            :style="{ color: world.theme.primary }"
                        >
                            {{ unlocked ? 'Unlocked' : 'Locked biome' }}
                        </p>
                    </header>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-xl bg-white/5 border border-white/10 p-3">
                            <p class="text-[10px] uppercase tracking-widest text-white/40 font-semibold">Done</p>
                            <p class="text-xl font-semibold text-emerald-400 tabular-nums">{{ totals.completed }}</p>
                        </div>
                        <div class="rounded-xl bg-white/5 border border-white/10 p-3">
                            <p class="text-[10px] uppercase tracking-widest text-white/40 font-semibold">Open</p>
                            <p
                                class="text-xl font-semibold tabular-nums"
                                :style="{ color: world.theme.primary }"
                            >{{ totals.available }}</p>
                        </div>
                        <div class="rounded-xl bg-white/5 border border-white/10 p-3">
                            <p class="text-[10px] uppercase tracking-widest text-white/40 font-semibold">Locked</p>
                            <p class="text-xl font-semibold text-white/50 tabular-nums">{{ totals.locked }}</p>
                        </div>
                    </div>

                    <!-- How unlocking works -->
                    <section class="rounded-xl bg-white/5 border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <Info class="w-4 h-4 text-white/60" />
                            <h3 class="text-[11px] uppercase tracking-widest text-white/60 font-semibold">How unlocking works</h3>
                        </div>
                        <ul class="text-sm text-white/70 space-y-1.5 leading-relaxed">
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span>Complete a node's lesson/exam to turn it <span class="text-emerald-400 font-medium">green</span>.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span>Each node has requirements (prerequisite nodes, XP totals, level, badges, or daily streaks). <span class="font-medium text-white">All</span> must be met to unlock it.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span>Clearing a boss unlocks the next biome in the journey.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-white/30">&bull;</span>
                                <span>Your current progress: <span class="text-white font-medium tabular-nums">Lv {{ player.level }} &middot; {{ player.xp.toLocaleString() }} XP &middot; {{ player.streakDays }}d streak</span>.</span>
                            </li>
                        </ul>
                    </section>

                    <!-- Next up -->
                    <section v-if="nextUp" class="rounded-xl border p-4"
                        :style="{ borderColor: `${world.theme.primary}55`, backgroundColor: `${world.theme.primary}10` }"
                    >
                        <p class="text-[10px] uppercase tracking-widest text-white/50 font-semibold mb-1">Next up</p>
                        <button
                            class="w-full flex items-center gap-3 text-left"
                            @click="emit('select-node', nextUp.node)"
                        >
                            <component
                                :is="nextUp.status === 'locked' ? Lock : typeIcon(nextUp.node.type)"
                                class="w-5 h-5 shrink-0"
                                :style="{ color: world.theme.primary }"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-white font-semibold truncate">{{ nextUp.node.title }}</p>
                                <p class="text-xs text-white/60">
                                    {{ nextUp.status === 'available' ? 'Ready to start' : `${nextUp.total - nextUp.met} requirement${nextUp.total - nextUp.met === 1 ? '' : 's'} remaining` }}
                                </p>
                            </div>
                        </button>
                    </section>

                    <!-- All nodes -->
                    <section>
                        <h3 class="text-[11px] uppercase tracking-widest text-white/40 font-semibold mb-2">All nodes</h3>
                        <ul class="space-y-1.5">
                            <li
                                v-for="item in nodeSummaries"
                                :key="item.node.id"
                            >
                                <button
                                    class="w-full flex items-start gap-3 px-3 py-2.5 rounded-lg border transition text-left hover:bg-white/10"
                                    :class="item.status === 'completed'
                                        ? 'bg-emerald-500/10 border-emerald-500/30'
                                        : item.status === 'available'
                                            ? 'bg-white/5 border-white/20'
                                            : 'bg-white/[0.03] border-white/10'"
                                    @click="emit('select-node', item.node)"
                                >
                                    <component
                                        :is="item.status === 'completed' ? CheckCircle2 : item.status === 'locked' ? Lock : typeIcon(item.node.type)"
                                        class="w-4 h-4 shrink-0 mt-0.5"
                                        :class="item.status === 'completed' ? 'text-emerald-400' : item.status === 'locked' ? 'text-white/40' : ''"
                                        :style="item.status === 'available' ? { color: world.theme.primary } : {}"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-sm font-medium truncate"
                                                :class="item.status === 'locked' ? 'text-white/60' : 'text-white'"
                                            >{{ item.node.title }}</span>
                                            <span
                                                v-if="item.node.type === 'boss'"
                                                class="text-[9px] uppercase tracking-widest font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300"
                                            >Boss</span>
                                        </div>

                                        <!-- Requirement chips -->
                                        <div v-if="item.evals.length" class="mt-1 flex flex-wrap gap-1">
                                            <span
                                                v-for="(ev, i) in item.evals"
                                                :key="i"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] tabular-nums border"
                                                :class="ev.met
                                                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                                                    : 'bg-white/5 border-white/10 text-white/60'"
                                            >
                                                <component :is="iconForKind(ev.req.kind)" class="w-3 h-3" />
                                                {{ summaryForReq(ev.req) }}
                                            </span>
                                        </div>
                                        <p v-else class="mt-0.5 text-[11px] text-white/40">Entry point — no requirements.</p>
                                    </div>
                                    <span
                                        v-if="item.total"
                                        class="text-[10px] tabular-nums shrink-0 mt-0.5"
                                        :class="item.status === 'completed' ? 'text-emerald-400' : 'text-white/40'"
                                    >{{ item.met }}/{{ item.total }}</span>
                                </button>
                            </li>
                        </ul>
                    </section>
                </div>
            </Motion>
        </Motion>
    </Presence>
</template>
