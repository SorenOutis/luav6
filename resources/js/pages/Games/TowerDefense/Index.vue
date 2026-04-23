<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Shield, Star, Zap, MapPin, Trophy, Target, Swords, Layers, Flame, ChevronRight, Gauge, Crosshair } from 'lucide-vue-next';

interface LevelProgress {
    best_score: number;
    best_waves: number;
    stars: number;
    plays: number;
    wins: number;
}

interface LevelItem {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    order: number;
    map: { name: string };
    difficulty: { name: string; slug: string };
    waves_count: number;
    progress: LevelProgress | null;
}

const props = defineProps<{ levels: LevelItem[] }>();

const breadcrumbs = [{ title: 'Tower Defense', href: '/games/tower-defense' }];

const stats = computed(() => {
    const total = props.levels.length;
    let cleared = 0;
    let totalStars = 0;
    const maxStars = total * 3;
    let bestScore = 0;
    let totalPlays = 0;
    let totalWins = 0;
    for (const l of props.levels) {
        if (!l.progress) continue;
        totalStars += l.progress.stars;
        totalPlays += l.progress.plays;
        totalWins += l.progress.wins;
        if (l.progress.wins > 0) cleared++;
        if (l.progress.best_score > bestScore) bestScore = l.progress.best_score;
    }
    const winRate = totalPlays > 0 ? Math.round((totalWins / totalPlays) * 100) : 0;
    return { total, cleared, totalStars, maxStars, bestScore, totalPlays, totalWins, winRate };
});

const completionPct = computed(() => {
    if (stats.value.maxStars === 0) return 0;
    return Math.round((stats.value.totalStars / stats.value.maxStars) * 100);
});

const nextObjective = computed(() => {
    return props.levels.find((l) => (l.progress?.stars ?? 0) < 3) ?? null;
});

interface DiffBucket { slug: string; name: string; count: number; cleared: number; stars: number; maxStars: number }
const byDifficulty = computed<DiffBucket[]>(() => {
    const map = new Map<string, DiffBucket>();
    for (const l of props.levels) {
        const key = l.difficulty.slug;
        if (!map.has(key)) {
            map.set(key, { slug: key, name: l.difficulty.name, count: 0, cleared: 0, stars: 0, maxStars: 0 });
        }
        const b = map.get(key)!;
        b.count++;
        b.maxStars += 3;
        if (l.progress) {
            b.stars += l.progress.stars;
            if (l.progress.wins > 0) b.cleared++;
        }
    }
    return [...map.values()];
});

const diffColor = (slug: string) => {
    switch (slug) {
        case 'easy': return 'text-emerald-400 border-emerald-500/40 bg-emerald-500/5';
        case 'normal': return 'text-sky-400 border-sky-500/40 bg-sky-500/5';
        case 'hard': return 'text-amber-400 border-amber-500/40 bg-amber-500/5';
        case 'nightmare': return 'text-rose-400 border-rose-500/40 bg-rose-500/5';
        default: return 'text-muted-foreground border-border';
    }
};

const diffAccent = (slug: string) => {
    switch (slug) {
        case 'easy': return 'from-emerald-500/60 via-emerald-500/20';
        case 'normal': return 'from-sky-500/60 via-sky-500/20';
        case 'hard': return 'from-amber-500/60 via-amber-500/20';
        case 'nightmare': return 'from-rose-500/60 via-rose-500/20';
        default: return 'from-border via-border/40';
    }
};

const diffFill = (slug: string) => {
    switch (slug) {
        case 'easy': return 'bg-emerald-500';
        case 'normal': return 'bg-sky-500';
        case 'hard': return 'bg-amber-500';
        case 'nightmare': return 'bg-rose-500';
        default: return 'bg-border';
    }
};
</script>

<template>
    <Head title="Tower Defense" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-col gap-4 p-4 xl:p-6">
            <!-- Hero -->
            <div class="relative overflow-hidden border border-border bg-card">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-transparent" />
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/10 blur-3xl" />
                <div class="pointer-events-none absolute -left-20 -bottom-20 h-60 w-60 rounded-full bg-primary/5 blur-3xl" />

                <div class="relative grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,auto)] lg:items-center lg:gap-8 lg:p-6">
                    <div class="flex items-center gap-4">
                        <div class="relative flex h-16 w-16 items-center justify-center border border-primary/50 bg-primary/10">
                            <Shield class="h-8 w-8 text-primary" />
                            <div class="pointer-events-none absolute -inset-px border border-primary/20" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-primary">Arcade // Strategy</p>
                            <h1 class="mt-1 text-3xl font-black uppercase tracking-tight text-foreground lg:text-4xl">Tower Defense</h1>
                            <p class="mt-1 text-[11px] font-bold tracking-[0.25em] uppercase text-muted-foreground">Defend the node · Survive all waves</p>
                        </div>
                    </div>

                    <!-- Stats (wider: 6 cells) -->
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:gap-2">
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Layers class="h-3 w-3" /> Cleared
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-foreground">{{ stats.cleared }}<span class="text-muted-foreground/60">/{{ stats.total }}</span></div>
                        </div>
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Star class="h-3 w-3" /> Stars
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-amber-400">{{ stats.totalStars }}<span class="text-muted-foreground/60">/{{ stats.maxStars }}</span></div>
                        </div>
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Trophy class="h-3 w-3" /> Best
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-emerald-400">{{ stats.bestScore || '—' }}</div>
                        </div>
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Swords class="h-3 w-3" /> Runs
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-foreground">{{ stats.totalPlays }}</div>
                        </div>
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Crosshair class="h-3 w-3" /> Win Rate
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-sky-400">{{ stats.winRate }}%</div>
                        </div>
                        <div class="border border-border bg-background/50 p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Gauge class="h-3 w-3" /> Progress
                            </div>
                            <div class="mt-0.5 text-lg font-black tabular-nums text-primary">{{ completionPct }}%</div>
                        </div>
                    </div>
                </div>

                <!-- Overall progress bar -->
                <div class="relative h-1 w-full bg-background/60">
                    <div class="h-full bg-gradient-to-r from-primary via-primary/80 to-primary/40 transition-all" :style="{ width: completionPct + '%' }" />
                </div>
            </div>

            <!-- Main content grid: levels + aside -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <!-- Levels column -->
                <section class="flex flex-col gap-3">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0">
                            <h2 class="text-sm font-black uppercase tracking-[0.3em] text-foreground">Levels</h2>
                            <p class="text-[10px] uppercase tracking-widest text-muted-foreground">Pick a sector to deploy into</p>
                        </div>
                        <div class="h-px flex-1 bg-gradient-to-r from-border via-border/60 to-transparent" />
                        <span class="shrink-0 border border-border px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                            {{ stats.total }} available
                        </span>
                    </div>

                    <div v-if="levels.length === 0" class="border border-dashed border-border p-12 text-center text-muted-foreground">
                        <Shield class="mx-auto mb-3 h-10 w-10 opacity-30" />
                        <p class="text-sm font-bold uppercase tracking-widest">No levels deployed</p>
                        <p class="mt-1 text-xs text-muted-foreground">Ask an admin to publish one via the Filament panel.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        <Link
                            v-for="level in levels"
                            :key="level.id"
                            :href="`/games/tower-defense/play/${level.slug}`"
                            class="group relative flex flex-col overflow-hidden border border-border bg-card transition hover:-translate-y-0.5 hover:border-primary"
                        >
                            <!-- top accent gradient -->
                            <div class="h-1 w-full bg-gradient-to-r to-transparent" :class="diffAccent(level.difficulty.slug)" />

                            <div class="flex flex-1 flex-col gap-3 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2 text-[10px] font-bold tracking-[0.3em] uppercase text-muted-foreground">
                                        <span class="inline-flex h-5 min-w-5 items-center justify-center border border-border px-1 tabular-nums text-foreground">{{ level.order }}</span>
                                        Sector
                                    </div>
                                    <span class="border px-2 py-0.5 text-[10px] font-bold tracking-widest uppercase" :class="diffColor(level.difficulty.slug)">
                                        {{ level.difficulty.name }}
                                    </span>
                                </div>

                                <h3 class="text-base font-black uppercase leading-tight tracking-tight text-foreground group-hover:text-primary">{{ level.name }}</h3>
                                <p v-if="level.description" class="line-clamp-2 text-xs leading-snug text-muted-foreground">{{ level.description }}</p>

                                <!-- meta -->
                                <div class="grid grid-cols-2 gap-2 border-y border-border py-2 text-[10px] font-bold tracking-[0.2em] uppercase text-muted-foreground">
                                    <span class="flex items-center gap-1.5 truncate"><MapPin class="h-3 w-3 shrink-0" /> {{ level.map.name }}</span>
                                    <span class="flex items-center gap-1.5"><Zap class="h-3 w-3" /> {{ level.waves_count }} waves</span>
                                </div>

                                <!-- stars + best -->
                                <div class="mt-auto flex items-end justify-between">
                                    <div>
                                        <div class="flex gap-0.5">
                                            <Star
                                                v-for="i in 3"
                                                :key="i"
                                                class="h-4 w-4"
                                                :class="(level.progress?.stars ?? 0) >= i ? 'fill-amber-400 text-amber-400' : 'text-border'"
                                            />
                                        </div>
                                        <p class="mt-1 text-[10px] font-bold tracking-widest uppercase text-muted-foreground">
                                            <template v-if="level.progress">{{ level.progress.plays }} run{{ level.progress.plays === 1 ? '' : 's' }}</template>
                                            <template v-else>Not deployed</template>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex items-center justify-end gap-1 text-[10px] font-bold tracking-widest uppercase text-muted-foreground">
                                            <Target class="h-3 w-3" /> Best
                                        </div>
                                        <div class="mt-0.5 font-black tabular-nums text-emerald-400">{{ level.progress?.best_score ?? '—' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between border-t border-border pt-2.5 text-[10px] font-bold uppercase tracking-widest">
                                    <span class="text-muted-foreground">
                                        <template v-if="level.progress?.wins">Cleared</template>
                                        <template v-else-if="level.progress">Attempted</template>
                                        <template v-else>Locked &amp; loaded</template>
                                    </span>
                                    <span class="flex items-center gap-1 text-primary transition-transform group-hover:translate-x-0.5">
                                        Deploy <ChevronRight class="h-3 w-3" />
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- Aside: progression + objective -->
                <aside class="flex flex-col gap-4">
                    <!-- Next objective -->
                    <div v-if="nextObjective" class="relative overflow-hidden border border-border bg-card">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-transparent" />
                        <div class="relative p-4">
                            <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.3em] text-primary">
                                <Flame class="h-3 w-3" /> Next Objective
                            </div>
                            <h3 class="mt-2 text-lg font-black uppercase tracking-tight text-foreground">{{ nextObjective.name }}</h3>
                            <p v-if="nextObjective.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ nextObjective.description }}</p>
                            <div class="mt-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">
                                <span class="border px-1.5 py-0.5" :class="diffColor(nextObjective.difficulty.slug)">{{ nextObjective.difficulty.name }}</span>
                                <span class="flex items-center gap-1"><MapPin class="h-3 w-3" />{{ nextObjective.map.name }}</span>
                                <span class="flex items-center gap-1"><Zap class="h-3 w-3" />{{ nextObjective.waves_count }}</span>
                            </div>
                            <div class="mt-3 flex items-center gap-1">
                                <Star
                                    v-for="i in 3"
                                    :key="i"
                                    class="h-4 w-4"
                                    :class="(nextObjective.progress?.stars ?? 0) >= i ? 'fill-amber-400 text-amber-400' : 'text-border'"
                                />
                                <span class="ml-auto text-[10px] font-bold uppercase tracking-widest text-muted-foreground">{{ nextObjective.progress?.stars ?? 0 }} / 3</span>
                            </div>
                            <Link
                                :href="`/games/tower-defense/play/${nextObjective.slug}`"
                                class="mt-4 flex items-center justify-center gap-2 border border-primary bg-primary py-2.5 text-[11px] font-black uppercase tracking-widest text-primary-foreground hover:opacity-90"
                            >
                                Deploy Now <ChevronRight class="h-3 w-3" />
                            </Link>
                        </div>
                    </div>

                    <!-- Difficulty breakdown -->
                    <div v-if="byDifficulty.length" class="border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-foreground">Progression</h3>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">By tier</span>
                        </div>
                        <ul class="divide-y divide-border">
                            <li v-for="b in byDifficulty" :key="b.slug" class="flex flex-col gap-1.5 px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <span class="border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-widest" :class="diffColor(b.slug)">
                                        {{ b.name }}
                                    </span>
                                    <span class="text-[10px] font-bold uppercase tracking-widest tabular-nums text-muted-foreground">
                                        {{ b.cleared }}<span class="text-muted-foreground/60">/{{ b.count }}</span>
                                        <span class="ml-2 text-amber-400">{{ b.stars }}<span class="text-muted-foreground/60">/{{ b.maxStars }}★</span></span>
                                    </span>
                                </div>
                                <div class="h-1 w-full overflow-hidden bg-background/80">
                                    <div
                                        class="h-full transition-all"
                                        :class="diffFill(b.slug)"
                                        :style="{ width: (b.maxStars ? (b.stars / b.maxStars) * 100 : 0) + '%' }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Tips -->
                    <div class="border border-border bg-card">
                        <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-foreground">Field Manual</h3>
                        </div>
                        <ul class="space-y-1.5 p-4 text-xs text-muted-foreground">
                            <li class="flex gap-2"><span class="text-primary">▸</span> Use setup time before each wave to place and upgrade towers.</li>
                            <li class="flex gap-2"><span class="text-primary">▸</span> Right-click a tower slot to cancel placement.</li>
                            <li class="flex gap-2"><span class="text-primary">▸</span> Keep more than 50% core HP for a 2-star, 90% for 3-star clear.</li>
                            <li class="flex gap-2"><span class="text-primary">▸</span> Sell refunds 70% of total invested cost.</li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
