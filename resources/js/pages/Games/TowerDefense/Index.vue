<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Shield, Star, Zap, MapPin, Trophy, Target, Swords, Layers } from 'lucide-vue-next';

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
    let maxStars = total * 3;
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
    return { total, cleared, totalStars, maxStars, bestScore, totalPlays, totalWins };
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

const diffGlow = (slug: string) => {
    switch (slug) {
        case 'easy': return 'from-emerald-500/40';
        case 'normal': return 'from-sky-500/40';
        case 'hard': return 'from-amber-500/40';
        case 'nightmare': return 'from-rose-500/40';
        default: return 'from-border';
    }
};
</script>

<template>
    <Head title="Tower Defense" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-6xl p-6">
            <!-- Hero -->
            <div class="relative mb-6 overflow-hidden border border-border bg-card">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent" />
                <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
                <div class="relative flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center border border-primary/40 bg-primary/10">
                            <Shield class="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-primary">Arcade // Strategy</p>
                            <h1 class="mt-1 text-3xl font-black uppercase tracking-tight text-foreground">Tower Defense</h1>
                            <p class="mt-1 text-xs tracking-[0.25em] uppercase text-muted-foreground">Defend the node · Survive all waves</p>
                        </div>
                    </div>
                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                        <div class="border border-border bg-background/40 p-3 text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Layers class="h-3 w-3" /> Cleared
                            </div>
                            <div class="mt-1 text-xl font-black tabular-nums text-foreground">{{ stats.cleared }}<span class="text-muted-foreground">/{{ stats.total }}</span></div>
                        </div>
                        <div class="border border-border bg-background/40 p-3 text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Star class="h-3 w-3" /> Stars
                            </div>
                            <div class="mt-1 text-xl font-black tabular-nums text-amber-400">{{ stats.totalStars }}<span class="text-muted-foreground">/{{ stats.maxStars }}</span></div>
                        </div>
                        <div class="border border-border bg-background/40 p-3 text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Trophy class="h-3 w-3" /> Best
                            </div>
                            <div class="mt-1 text-xl font-black tabular-nums text-emerald-400">{{ stats.bestScore }}</div>
                        </div>
                        <div class="border border-border bg-background/40 p-3 text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground">
                                <Swords class="h-3 w-3" /> Runs
                            </div>
                            <div class="mt-1 text-xl font-black tabular-nums text-foreground">{{ stats.totalPlays }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section header -->
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-[0.3em] text-foreground">Levels</h2>
                    <p class="text-[10px] uppercase tracking-widest text-muted-foreground">Pick a sector to deploy into</p>
                </div>
                <div class="h-px flex-1 mx-6 bg-gradient-to-r from-border via-border to-transparent" />
                <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">{{ stats.total }} available</span>
            </div>

            <div v-if="levels.length === 0" class="border border-dashed border-border p-12 text-center text-muted-foreground">
                <Shield class="mx-auto mb-3 h-10 w-10 opacity-30" />
                <p class="text-sm font-bold uppercase tracking-widest">No levels deployed</p>
                <p class="mt-1 text-xs text-muted-foreground">Ask an admin to publish one via the Filament panel.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="level in levels"
                    :key="level.id"
                    :href="`/games/tower-defense/play/${level.slug}`"
                    class="group relative flex flex-col overflow-hidden border border-border bg-card transition hover:-translate-y-0.5 hover:border-primary"
                >
                    <!-- top accent bar -->
                    <div class="h-1 w-full bg-gradient-to-r to-transparent" :class="diffGlow(level.difficulty.slug)" />
                    <div class="flex flex-col gap-3 p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2 text-[10px] font-bold tracking-[0.3em] uppercase text-muted-foreground">
                                <span class="inline-flex h-5 min-w-5 items-center justify-center border border-border px-1 tabular-nums text-foreground">{{ level.order }}</span>
                                Sector
                            </div>
                            <span class="border px-2 py-0.5 text-[10px] font-bold tracking-widest uppercase" :class="diffColor(level.difficulty.slug)">
                                {{ level.difficulty.name }}
                            </span>
                        </div>

                        <h3 class="text-lg font-black uppercase leading-tight tracking-tight text-foreground group-hover:text-primary">{{ level.name }}</h3>
                        <p v-if="level.description" class="line-clamp-2 min-h-10 text-sm leading-snug text-muted-foreground">{{ level.description }}</p>

                        <!-- meta -->
                        <div class="grid grid-cols-2 gap-2 border-y border-border py-2 text-[10px] font-bold tracking-[0.2em] uppercase text-muted-foreground">
                            <span class="flex items-center gap-1.5"><MapPin class="h-3 w-3" /> {{ level.map.name }}</span>
                            <span class="flex items-center gap-1.5"><Zap class="h-3 w-3" /> {{ level.waves_count }} waves</span>
                        </div>

                        <!-- progress footer -->
                        <div class="flex items-end justify-between">
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

                        <div class="mt-1 flex items-center justify-between border-t border-border pt-3 text-[10px] font-bold uppercase tracking-widest">
                            <span class="text-muted-foreground">
                                <template v-if="level.progress?.wins">Cleared</template>
                                <template v-else-if="level.progress">Attempted</template>
                                <template v-else>Locked &amp; loaded</template>
                            </span>
                            <span class="flex items-center gap-1 text-primary group-hover:translate-x-0.5 transition-transform">
                                Deploy <span aria-hidden>→</span>
                            </span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
