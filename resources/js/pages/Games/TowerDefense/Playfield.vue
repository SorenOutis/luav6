<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { TowerDefenseGame, type GameSnapshot } from './engine/Game';
import type { HudState, LevelPayload } from './engine/types';
import { Coins, Heart, Pause, Play, Shield, Star, Trophy, ArrowLeft, RotateCcw, Gauge, Users, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{ level: LevelPayload }>();

const breadcrumbs = [
    { title: 'Tower Defense', href: '/games/tower-defense' },
    { title: props.level.name, href: `/games/tower-defense/play/${props.level.slug}` },
];

const stageRef = ref<HTMLDivElement | null>(null);
const game = shallowRef<TowerDefenseGame | null>(null);
const hud = ref<HudState>({
    gold: props.level.starting_gold,
    lives: props.level.starting_lives,
    wave: 0,
    totalWaves: props.level.waves.length,
    score: 0,
    enemiesAlive: 0,
    status: 'idle',
    selectedTowerSlug: null,
    hoverTile: null,
    canBuild: false,
    selectedPlacedTowerId: null,
    speed: 1,
    awaitingWaveStart: true,
    nextWaveIdx: 1,
});

const selectedInfo = ref<ReturnType<TowerDefenseGame['getSelectedTowerInfo']> | null>(null);
const runId = ref<number | null>(null);
const endResult = ref<{ status: 'win' | 'lose'; score: number; waves: number; lives: number; goldSpent: number; duration: number } | null>(null);
const submitting = ref(false);
const leaderboard = ref<Array<{ id: number; user: { id: number; name: string; avatar: string | null }; score: number; waves_completed: number; duration_ms: number }>>([]);
const resumed = ref(false);
const startModalOpen = ref(false);
const resetModalOpen = ref(false);
const waveBanner = ref<{ n: number; total: number; key: number } | null>(null);
let waveBannerTimer: number | null = null;

const triggerWaveBanner = (n: number, total: number) => {
    waveBanner.value = { n, total, key: Date.now() };
    if (waveBannerTimer) window.clearTimeout(waveBannerTimer);
    waveBannerTimer = window.setTimeout(() => { waveBanner.value = null; waveBannerTimer = null; }, 2200);
};

interface SavedCheckpoint {
    snapshot: GameSnapshot;
    runId: number;
    savedAt: number;
}
const STORAGE_KEY = `td:checkpoint:${props.level.slug}`;

const readCheckpoint = (): SavedCheckpoint | null => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as SavedCheckpoint;
        if (parsed?.snapshot?.levelId !== props.level.id) return null;
        return parsed;
    } catch {
        return null;
    }
};
const writeCheckpoint = (cp: SavedCheckpoint) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cp)); } catch { /* quota */ }
};
const clearCheckpoint = () => {
    try { localStorage.removeItem(STORAGE_KEY); } catch { /* noop */ }
};

const startRun = async () => {
    try {
        const res = await axios.post('/games/tower-defense/runs', { level_id: props.level.id });
        runId.value = res.data.run_id;
    } catch (e) {
        console.error('Failed to start run', e);
    }
};

const persistCheckpoint = () => {
    if (!game.value || runId.value == null) return;
    if (hud.value.status === 'win' || hud.value.status === 'lose') return;
    writeCheckpoint({
        snapshot: game.value.snapshot(),
        runId: runId.value,
        savedAt: Date.now(),
    });
};

const finishRun = async (result: NonNullable<typeof endResult.value>) => {
    if (!runId.value) return;
    submitting.value = true;
    try {
        await axios.post(`/games/tower-defense/runs/${runId.value}/finish`, {
            status: result.status,
            waves_completed: result.waves,
            score: result.score,
            gold_spent: result.goldSpent,
            lives_remaining: result.lives,
            duration_ms: Math.floor(result.duration),
        });
        await loadLeaderboard();
    } catch (e) {
        console.error('Failed to finish run', e);
    } finally {
        submitting.value = false;
    }
};

const loadLeaderboard = async () => {
    try {
        const res = await axios.get(`/games/tower-defense/leaderboard/${props.level.slug}`);
        leaderboard.value = res.data.leaderboard;
    } catch { /* noop */ }
};

onMounted(async () => {
    if (!stageRef.value) return;
    const g = new TowerDefenseGame(stageRef.value, props.level);
    game.value = g;
    g.on('hud', (state) => {
        hud.value = state;
        selectedInfo.value = g.getSelectedTowerInfo();
    });
    g.on('end', async (result) => {
        endResult.value = result;
        clearCheckpoint();
        await finishRun(result);
    });
    g.on('waveComplete', () => persistCheckpoint());
    g.on('waveStarting', ({ waveIdx, totalWaves }) => {
        triggerWaveBanner(waveIdx + 1, totalWaves);
    });
    await g.init();

    const cp = readCheckpoint();
    if (cp) {
        runId.value = cp.runId;
        g.restore(cp.snapshot);
        resumed.value = true;
        g.start();
    } else {
        // Fresh run — let the player see the briefing before starting.
        g.pause(); // ensures status stays non-playing
        startModalOpen.value = true;
    }
    await loadLeaderboard();
});

const confirmStart = async () => {
    if (!game.value) return;
    if (runId.value == null) await startRun();
    startModalOpen.value = false;
    game.value.start();
    // Engine now sits in setup phase; player clicks "Start Wave" to launch wave 1.
};

const startNextWave = () => {
    game.value?.startNextWave();
};

onBeforeUnmount(() => {
    if (waveBannerTimer) window.clearTimeout(waveBannerTimer);
    game.value?.destroy();
});

const selectTower = (slug: string) => {
    if (!game.value) return;
    game.value.selectTower(hud.value.selectedTowerSlug === slug ? null : slug);
};

const togglePause = () => {
    if (!game.value) return;
    if (hud.value.status === 'playing') game.value.pause();
    else if (hud.value.status === 'paused') game.value.resume();
};

const setSpeed = (s: 1 | 2 | 3) => game.value?.setSpeed(s);
const sell = () => game.value?.sellSelected();
const upgrade = () => game.value?.upgradeSelected();

const formatDuration = (ms: number) => `${(ms / 1000).toFixed(1)}s`;

const retry = () => {
    clearCheckpoint();
    router.reload();
};
const backToIndex = () => router.visit('/games/tower-defense');

const resetGame = () => {
    if (hud.value.status === 'win' || hud.value.status === 'lose') {
        retry();
        return;
    }
    game.value?.pause();
    resetModalOpen.value = true;
};

const confirmReset = async () => {
    resetModalOpen.value = false;
    try {
        if (runId.value != null) {
            await axios.post(`/games/tower-defense/runs/${runId.value}/finish`, {
                status: 'abandoned',
                waves_completed: Math.max(0, hud.value.wave - 1),
                score: hud.value.score,
                gold_spent: 0,
                lives_remaining: hud.value.lives,
                duration_ms: 0,
            });
        }
    } catch { /* noop */ }
    clearCheckpoint();
    window.location.reload();
};

const cancelReset = () => {
    resetModalOpen.value = false;
    if (hud.value.status === 'paused') game.value?.resume();
};
</script>

<template>
    <Head :title="`${level.name} — Tower Defense`" />
    <AppLayout :breadcrumbs="breadcrumbs" hide-sidebar>
        <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-4 p-4">
            <!-- Top bar -->
            <div class="relative overflow-hidden border border-border bg-card">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-primary/60 via-primary/20 to-transparent" />
                <div class="flex flex-wrap items-center justify-between gap-4 p-3">
                    <div class="flex items-center gap-4">
                        <Link href="/games/tower-defense" class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-muted-foreground hover:text-foreground">
                            <ArrowLeft class="h-4 w-4" /> Levels
                        </Link>
                        <div class="h-6 w-px bg-border" />
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-[0.3em] text-muted-foreground">Sector {{ level.id }}</p>
                            <h1 class="text-sm font-black uppercase tracking-widest">{{ level.name }}</h1>
                        </div>
                        <span class="border border-border bg-background/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                            {{ level.difficulty.name }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 border border-amber-500/30 bg-amber-500/5 px-3 py-1.5 text-sm font-bold tabular-nums text-amber-400">
                            <Coins class="h-4 w-4" /> {{ hud.gold }}
                        </div>
                        <div class="flex items-center gap-2 border border-rose-500/30 bg-rose-500/5 px-3 py-1.5 text-sm font-bold tabular-nums text-rose-400">
                            <Heart class="h-4 w-4" /> {{ hud.lives }}
                        </div>
                        <div class="flex items-center gap-2 border border-sky-500/30 bg-sky-500/5 px-3 py-1.5 text-sm font-bold tabular-nums text-sky-400">
                            <Shield class="h-4 w-4" /> {{ hud.wave }}/{{ hud.totalWaves }}
                        </div>
                        <div class="flex items-center gap-2 border border-emerald-500/30 bg-emerald-500/5 px-3 py-1.5 text-sm font-bold tabular-nums text-emerald-400">
                            <Trophy class="h-4 w-4" /> {{ hud.score }}
                        </div>
                        <div class="flex items-center gap-2 border border-border px-3 py-1.5 text-sm font-bold tabular-nums">
                            <Users class="h-4 w-4 text-fuchsia-400" /> {{ hud.enemiesAlive }}
                        </div>
                        <button
                            @click="togglePause"
                            class="flex items-center gap-2 border border-border px-3 py-1.5 text-xs font-bold uppercase tracking-widest hover:bg-muted"
                        >
                            <component :is="hud.status === 'paused' ? Play : Pause" class="h-4 w-4" />
                            {{ hud.status === 'paused' ? 'Resume' : 'Pause' }}
                        </button>
                        <button
                            @click="resetGame"
                            class="flex items-center gap-2 border border-rose-500/40 bg-rose-500/5 px-3 py-1.5 text-xs font-bold uppercase tracking-widest text-rose-400 hover:bg-rose-500/15"
                            title="Reset the current run"
                        >
                            <RefreshCw class="h-4 w-4" />
                            Reset
                        </button>
                        <div class="flex border border-border">
                            <button
                                v-for="s in [1, 2, 3] as const"
                                :key="s"
                                @click="setSpeed(s)"
                                class="flex items-center gap-1 px-2 py-1.5 text-xs font-bold"
                                :class="hud.speed === s ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                            >
                                <Gauge v-if="s === 1" class="h-3 w-3" />{{ s }}x
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Wave progress + Start Wave control -->
                <div class="flex items-stretch border-t border-border">
                    <div class="flex h-8 flex-1 overflow-hidden bg-background/50">
                        <div
                            v-for="i in hud.totalWaves"
                            :key="i"
                            class="flex flex-1 items-center justify-center border-r border-border/60 text-[10px] font-black tracking-widest uppercase last:border-r-0 transition"
                            :class="[
                                i < hud.wave ? 'bg-emerald-500/20 text-emerald-400' : '',
                                i === hud.wave && !hud.awaitingWaveStart ? 'bg-primary/25 text-primary animate-pulse' : '',
                                i === hud.nextWaveIdx && hud.awaitingWaveStart ? 'bg-amber-500/15 text-amber-400' : '',
                                i > hud.wave && i !== hud.nextWaveIdx ? 'text-muted-foreground/60' : '',
                            ]"
                        >{{ i }}</div>
                    </div>
                    <button
                        v-if="hud.awaitingWaveStart && hud.status !== 'win' && hud.status !== 'lose' && !startModalOpen"
                        @click="startNextWave"
                        class="flex items-center gap-2 border-l border-emerald-500/40 bg-emerald-500/15 px-4 text-xs font-black uppercase tracking-widest text-emerald-400 hover:bg-emerald-500/25"
                    >
                        <Play class="h-4 w-4" />
                        {{ hud.wave === 0 ? `Start Wave 1` : `Start Wave ${hud.nextWaveIdx}` }}
                    </button>
                </div>
                <div
                    v-if="resumed"
                    class="flex items-center justify-between border-t border-emerald-500/30 bg-emerald-500/5 px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-emerald-400"
                >
                    <span class="flex items-center gap-2"><RotateCcw class="h-3 w-3" /> Run restored from checkpoint · Wave {{ hud.wave }}</span>
                    <button @click="resumed = false" class="text-muted-foreground hover:text-foreground">Dismiss</button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px]">
                <!-- Canvas (PixiJS mounts here) -->
                <div class="relative flex items-start justify-center overflow-hidden border border-border bg-black p-2">
                    <div ref="stageRef" class="[image-rendering:pixelated]" />

                    <!-- Wave announcement banner (centered over canvas) -->
                    <Transition name="td-wave">
                        <div
                            v-if="waveBanner"
                            :key="waveBanner.key"
                            class="pointer-events-none absolute inset-0 z-40 flex items-center justify-center"
                        >
                            <div class="td-wave-banner relative flex flex-col items-center">
                                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-transparent via-primary/20 to-transparent blur-2xl" />
                                <p class="text-[10px] font-bold uppercase tracking-[0.5em] text-primary drop-shadow-[0_0_8px_rgba(0,0,0,0.6)]">Incoming</p>
                                <h2 class="td-wave-text text-7xl font-black uppercase tracking-tight text-white">
                                    Wave <span class="td-wave-accent">{{ waveBanner.n }}</span>
                                </h2>
                                <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.4em] text-white/70 drop-shadow-[0_0_8px_rgba(0,0,0,0.6)]">
                                    of {{ waveBanner.total }}
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Sidebar: Tower shop + selected tower -->
                <aside class="flex flex-col gap-4">
                    <div class="border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-3 py-2">
                            <h2 class="text-xs font-black uppercase tracking-[0.25em] text-foreground">Arsenal</h2>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Right-click · cancel</span>
                        </div>
                        <div class="flex flex-col gap-1.5 p-2">
                            <button
                                v-for="tower in level.towers"
                                :key="tower.slug"
                                @click="selectTower(tower.slug)"
                                :disabled="hud.gold < tower.cost"
                                class="group flex items-center gap-3 border p-2.5 text-left transition disabled:opacity-40"
                                :class="hud.selectedTowerSlug === tower.slug ? 'border-primary bg-primary/10 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.05)]' : 'border-border hover:border-muted-foreground hover:bg-muted/30'"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center border border-border bg-background" :style="{ boxShadow: `inset 0 0 0 4px ${tower.color}33` }">
                                    <span class="h-4 w-4" :style="{ background: tower.color, boxShadow: `0 0 8px ${tower.color}` }" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="truncate text-xs font-black uppercase tracking-wider">{{ tower.name }}</div>
                                        <span class="flex shrink-0 items-center gap-1 text-xs font-bold tabular-nums text-amber-400">
                                            <Coins class="h-3 w-3" />{{ tower.cost }}
                                        </span>
                                    </div>
                                    <div class="mt-1 grid grid-cols-3 gap-1 text-[9px] font-bold tracking-widest uppercase text-muted-foreground">
                                        <span>DMG <span class="text-foreground tabular-nums">{{ tower.damage }}</span></span>
                                        <span>RNG <span class="text-foreground tabular-nums">{{ tower.range }}</span></span>
                                        <span>RoF <span class="text-foreground tabular-nums">{{ tower.fire_rate }}</span></span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedInfo" class="border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-3 py-2">
                            <h2 class="text-xs font-black uppercase tracking-[0.25em] text-foreground">{{ selectedInfo.name }}</h2>
                            <span class="border border-amber-500/40 bg-amber-500/10 px-1.5 text-[9px] font-black tracking-widest uppercase text-amber-400">T{{ selectedInfo.tier + 1 }}</span>
                        </div>
                        <div class="p-3">
                        <div class="grid grid-cols-3 gap-2 text-center text-[10px] uppercase tracking-widest">
                            <div class="border border-border p-2">
                                <div class="text-muted-foreground">DMG</div>
                                <div class="text-sm font-black text-foreground">{{ selectedInfo.damage }}</div>
                            </div>
                            <div class="border border-border p-2">
                                <div class="text-muted-foreground">RNG</div>
                                <div class="text-sm font-black text-foreground">{{ selectedInfo.range.toFixed(1) }}</div>
                            </div>
                            <div class="border border-border p-2">
                                <div class="text-muted-foreground">RATE</div>
                                <div class="text-sm font-black text-foreground">{{ selectedInfo.fireRate }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button
                                v-if="selectedInfo.nextUpgrade"
                                @click="upgrade"
                                :disabled="hud.gold < selectedInfo.nextUpgrade.cost"
                                class="flex-1 border border-emerald-500/50 bg-emerald-500/10 p-2 text-xs font-black uppercase tracking-widest text-emerald-400 hover:bg-emerald-500/20 disabled:opacity-40"
                            >
                                Upgrade ({{ selectedInfo.nextUpgrade.cost }}g)
                            </button>
                            <button
                                @click="sell"
                                class="flex-1 border border-rose-500/50 bg-rose-500/10 p-2 text-xs font-black uppercase tracking-widest text-rose-400 hover:bg-rose-500/20"
                            >
                                Sell (+{{ selectedInfo.sellRefund }}g)
                            </button>
                        </div>
                        </div>
                    </div>

                    <div class="border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-3 py-2">
                            <h2 class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.25em] text-foreground">
                                <Trophy class="h-3.5 w-3.5 text-amber-400" /> Leaderboard
                            </h2>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Top {{ Math.min(10, leaderboard.length) }}</span>
                        </div>
                        <ol v-if="leaderboard.length" class="flex flex-col text-xs">
                            <li
                                v-for="(row, i) in leaderboard.slice(0, 10)"
                                :key="row.id"
                                class="flex items-center justify-between border-b border-border/50 px-3 py-1.5 last:border-b-0"
                                :class="i === 0 ? 'bg-amber-500/5' : i === 1 ? 'bg-slate-500/5' : i === 2 ? 'bg-orange-500/5' : ''"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center border text-[10px] font-black tabular-nums"
                                        :class="i === 0 ? 'border-amber-400/50 text-amber-400' : i === 1 ? 'border-slate-400/50 text-slate-300' : i === 2 ? 'border-orange-400/50 text-orange-400' : 'border-border text-muted-foreground'"
                                    >{{ i + 1 }}</span>
                                    <span class="truncate font-bold">{{ row.user.name }}</span>
                                </span>
                                <span class="shrink-0 font-black tabular-nums text-emerald-400">{{ row.score }}</span>
                            </li>
                        </ol>
                        <p v-else class="p-4 text-center text-xs text-muted-foreground">No runs yet — be the first.</p>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Start modal -->
        <div v-if="startModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 backdrop-blur">
            <div class="w-full max-w-lg border border-border bg-card">
                <div class="relative overflow-hidden border-b border-border">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/20 via-transparent to-transparent" />
                    <div class="relative flex items-center gap-4 p-5">
                        <div class="flex h-12 w-12 items-center justify-center border border-primary/40 bg-primary/10">
                            <Shield class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-primary">Briefing</p>
                            <h2 class="text-xl font-black uppercase tracking-tight">{{ level.name }}</h2>
                            <p class="text-[10px] uppercase tracking-widest text-muted-foreground">
                                {{ level.difficulty.name }} · {{ level.map.name }} · {{ level.waves.length }} waves
                            </p>
                        </div>
                    </div>
                </div>
                <div class="space-y-4 p-5">
                    <p v-if="level.description" class="text-sm text-muted-foreground">{{ level.description }}</p>
                    <div class="grid grid-cols-3 gap-2 text-center text-[10px] uppercase tracking-widest">
                        <div class="border border-amber-500/30 bg-amber-500/5 p-3">
                            <div class="flex items-center justify-center gap-1 text-muted-foreground"><Coins class="h-3 w-3" /> Gold</div>
                            <div class="mt-1 text-xl font-black tabular-nums text-amber-400">{{ level.starting_gold }}</div>
                        </div>
                        <div class="border border-rose-500/30 bg-rose-500/5 p-3">
                            <div class="flex items-center justify-center gap-1 text-muted-foreground"><Heart class="h-3 w-3" /> Core HP</div>
                            <div class="mt-1 text-xl font-black tabular-nums text-rose-400">{{ level.starting_lives }}</div>
                        </div>
                        <div class="border border-sky-500/30 bg-sky-500/5 p-3">
                            <div class="flex items-center justify-center gap-1 text-muted-foreground"><Shield class="h-3 w-3" /> Waves</div>
                            <div class="mt-1 text-xl font-black tabular-nums text-sky-400">{{ level.waves.length }}</div>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-2 text-[10px] font-black uppercase tracking-[0.25em] text-muted-foreground">Available Towers</h3>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in level.towers" :key="t.slug" class="flex items-center gap-1.5 border border-border bg-background/40 px-2 py-1 text-[10px] font-bold uppercase tracking-widest">
                                <span class="h-2.5 w-2.5" :style="{ background: t.color }" />
                                {{ t.name }} <span class="text-amber-400 tabular-nums">{{ t.cost }}g</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 border-t border-border p-4">
                    <button
                        @click="backToIndex"
                        class="flex-1 border border-border p-3 text-xs font-black uppercase tracking-widest hover:bg-muted"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmStart"
                        class="flex flex-[2] items-center justify-center gap-2 border border-primary bg-primary p-3 text-xs font-black uppercase tracking-widest text-primary-foreground hover:opacity-90"
                    >
                        <Play class="h-4 w-4" /> Start Defense
                    </button>
                </div>
            </div>
        </div>

        <!-- Reset confirm modal -->
        <div v-if="resetModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 backdrop-blur">
            <div class="w-full max-w-sm border border-border bg-card">
                <div class="flex items-center gap-3 border-b border-border p-4">
                    <div class="flex h-10 w-10 items-center justify-center border border-rose-500/40 bg-rose-500/10">
                        <RefreshCw class="h-5 w-5 text-rose-400" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-rose-400">Confirm</p>
                        <h2 class="text-base font-black uppercase tracking-tight">Reset Run?</h2>
                    </div>
                </div>
                <div class="p-4 text-sm text-muted-foreground">
                    All progress in this session will be lost. Your run will be marked as abandoned.
                </div>
                <div class="flex gap-2 border-t border-border p-4">
                    <button
                        @click="cancelReset"
                        class="flex-1 border border-border p-2.5 text-xs font-black uppercase tracking-widest hover:bg-muted"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmReset"
                        class="flex-1 border border-rose-500/60 bg-rose-500/10 p-2.5 text-xs font-black uppercase tracking-widest text-rose-400 hover:bg-rose-500/20"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- End modal -->
        <div v-if="endResult" class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 backdrop-blur">
            <div class="w-full max-w-md border border-border bg-card p-8">
                <div class="mb-4 flex items-center gap-3">
                    <component
                        :is="endResult.status === 'win' ? Trophy : Shield"
                        class="h-8 w-8"
                        :class="endResult.status === 'win' ? 'text-emerald-400' : 'text-rose-400'"
                    />
                    <h2 class="text-2xl font-black uppercase tracking-tight">
                        {{ endResult.status === 'win' ? 'Victory' : 'Defeat' }}
                    </h2>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="border border-border p-3">
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground">Score</div>
                        <div class="text-xl font-black tabular-nums text-emerald-400">{{ endResult.score }}</div>
                    </div>
                    <div class="border border-border p-3">
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground">Waves</div>
                        <div class="text-xl font-black tabular-nums">{{ endResult.waves }}/{{ level.waves.length }}</div>
                    </div>
                    <div class="border border-border p-3">
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground">Lives</div>
                        <div class="text-xl font-black tabular-nums text-rose-400">{{ endResult.lives }}</div>
                    </div>
                    <div class="border border-border p-3">
                        <div class="text-[10px] uppercase tracking-widest text-muted-foreground">Time</div>
                        <div class="text-xl font-black tabular-nums">{{ formatDuration(endResult.duration) }}</div>
                    </div>
                </div>
                <div v-if="endResult.status === 'win'" class="mt-4 flex justify-center gap-1">
                    <Star
                        v-for="i in 3"
                        :key="i"
                        class="h-8 w-8"
                        :class="(endResult.lives / level.starting_lives) >= (i === 3 ? 0.9 : i === 2 ? 0.5 : 0.01) ? 'fill-amber-400 text-amber-400' : 'text-border'"
                    />
                </div>
                <div class="mt-6 flex gap-2">
                    <button
                        @click="retry"
                        :disabled="submitting"
                        class="flex flex-1 items-center justify-center gap-2 border border-primary bg-primary p-3 text-xs font-black uppercase tracking-widest text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        <RotateCcw class="h-4 w-4" /> Retry
                    </button>
                    <button
                        @click="backToIndex"
                        class="flex flex-1 items-center justify-center gap-2 border border-border p-3 text-xs font-black uppercase tracking-widest hover:bg-muted"
                    >
                        Levels
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.td-wave-enter-active,
.td-wave-leave-active {
    transition: opacity 350ms ease, transform 500ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.td-wave-enter-from {
    opacity: 0;
    transform: translateY(-24px) scale(0.92);
}
.td-wave-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(1.04);
}

.td-wave-banner {
    animation: td-wave-pop 2.2s ease-out forwards;
}
.td-wave-text {
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.85), 0 0 32px rgba(0, 0, 0, 0.7), 0 0 64px hsl(var(--primary) / 0.6);
}
.td-wave-accent {
    color: #fbbf24;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.9), 0 0 28px rgba(251, 191, 36, 0.75), 0 0 56px rgba(251, 191, 36, 0.45);
}
@keyframes td-wave-pop {
    0% { transform: translateY(0) scale(1); }
    10% { transform: translateY(-2px) scale(1.06); }
    50% { transform: translateY(0) scale(1); }
    100% { transform: translateY(0) scale(1); }
}
</style>
