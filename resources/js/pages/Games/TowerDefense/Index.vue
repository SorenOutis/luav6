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

const sortedLevels = computed(() => {
    const difficultyOrder: Record<string, number> = {
        'easy': 1,
        'normal': 2,
        'hard': 3,
        'nightmare': 4
    };

    return [...props.levels].sort((a, b) => {
        const orderA = difficultyOrder[a.difficulty.slug] || 99;
        const orderB = difficultyOrder[b.difficulty.slug] || 99;
        if (orderA !== orderB) return orderA - orderB;
        return a.order - b.order;
    });
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
        <div class="flex w-full flex-col gap-8 p-4 xl:p-8">
            <!-- Hero -->
            <div class="surface-card relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent" />
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />

                <div class="relative grid gap-8 p-6 lg:grid-cols-[1fr_auto] lg:items-center lg:p-10">
                    <div class="flex items-center gap-6">
                        <div class="relative flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5 shadow-inner">
                            <Shield class="h-10 w-10 text-primary" />
                            <div class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[10px] font-black text-primary-foreground shadow-lg">
                                <Zap class="h-3 w-3" />
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
                                <p class="text-[10px] font-bold uppercase tracking-[0.4em] text-primary/80">Arcade Strategy</p>
                            </div>
                            <h1 class="mt-1 text-4xl font-black uppercase tracking-tight text-foreground lg:text-5xl">Tower Defense</h1>
                            <p class="mt-2 text-sm font-medium text-muted-foreground/80">Defend the node · Survive all waves · Earn stars</p>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="flex flex-wrap items-center gap-4 lg:gap-8">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-foreground">
                                <Layers class="h-4 w-4 text-primary/60" />
                                <span class="text-xl font-black tabular-nums">{{ stats.cleared }}<span class="text-muted-foreground/40">/{{ stats.total }}</span></span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Sectors Cleared</span>
                        </div>
                        <div class="h-10 w-px bg-border/40 hidden sm:block" />
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-amber-500">
                                <Star class="h-4 w-4 fill-amber-500/20" />
                                <span class="text-xl font-black tabular-nums">{{ stats.totalStars }}<span class="text-muted-foreground/40">/{{ stats.maxStars }}</span></span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Stars Collected</span>
                        </div>
                        <div class="h-10 w-px bg-border/40 hidden sm:block" />
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-emerald-500">
                                <Trophy class="h-4 w-4 text-emerald-500/60" />
                                <span class="text-xl font-black tabular-nums">{{ stats.bestScore || '0' }}</span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Global Best</span>
                        </div>
                        <div class="h-10 w-px bg-border/40 hidden sm:block" />
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-sky-500">
                                <Gauge class="h-4 w-4 text-sky-500/60" />
                                <span class="text-xl font-black tabular-nums">{{ completionPct }}%</span>
                            </div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">Progression</span>
                        </div>
                    </div>
                </div>

                <!-- Overall progress bar -->
                <div class="relative h-1.5 w-full bg-background/40">
                    <div class="h-full bg-primary transition-all duration-1000 ease-out" :style="{ width: completionPct + '%' }" />
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent animate-shimmer" />
                </div>
            </div>

            <!-- Main content grid -->
            <div class="grid grid-cols-1 gap-8 items-start xl:grid-cols-[1fr_340px]">
                <!-- Levels column -->
                <section class="flex flex-col gap-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-black uppercase tracking-tight">Available Sectors</h2>
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground/80">Select a combat zone to deploy</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-primary/5 border border-primary/10 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-primary">
                                {{ stats.total }} Maps Deployed
                            </span>
                        </div>
                    </div>

                    <div v-if="levels.length === 0" class="surface-card flex flex-col items-center justify-center py-20 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/30 mb-4">
                            <Shield class="h-8 w-8 text-muted-foreground/40" />
                        </div>
                        <p class="text-sm font-black uppercase tracking-widest text-foreground">No levels deployed</p>
                        <p class="mt-1 text-xs text-muted-foreground max-w-xs">The mission roster is currently empty. Contact command for deployment.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2 2xl:grid-cols-3">
                        <Link
                            v-for="level in sortedLevels"
                            :key="level.id"
                            :href="`/games/tower-defense/play/${level.slug}`"
                            class="surface-card premium-hover group flex flex-col h-full"
                        >
                            <div class="relative flex items-center justify-between gap-4 border-b border-border/40 p-5">
                                <div class="flex flex-1 min-w-0 items-center gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 shadow-sm">
                                        <span class="text-sm font-black tabular-nums text-primary">{{ level.order }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-black uppercase tracking-tight truncate group-hover:text-primary transition-colors">{{ level.name }}</h3>
                                        <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-muted-foreground/60 truncate">{{ level.map.name }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[8px] font-black uppercase tracking-widest shadow-sm border" :class="diffColor(level.difficulty.slug)">
                                    {{ level.difficulty.name }}
                                </span>
                            </div>

                            <div class="relative flex flex-1 flex-col p-5">
                                <p v-if="level.description" class="text-[13px] leading-relaxed text-muted-foreground/90 line-clamp-2 min-h-[2.5rem]">{{ level.description }}</p>
                                
                                <div class="mt-4 flex items-center gap-4 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">
                                    <div class="flex items-center gap-1.5">
                                        <Zap class="h-3 w-3 text-primary/60" />
                                        {{ level.waves_count }} Waves
                                    </div>
                                    <div class="h-3 w-px bg-border/60" />
                                    <div class="flex items-center gap-1.5">
                                        <Swords class="h-3 w-3 text-primary/60" />
                                        {{ level.progress?.plays ?? 0 }} Runs
                                    </div>
                                </div>

                                <!-- Stars & Best Score -->
                                <div class="mt-6 flex items-center justify-between rounded-xl bg-muted/30 p-3">
                                    <div class="flex gap-1">
                                        <Star
                                            v-for="i in 3"
                                            :key="i"
                                            class="h-4 w-4"
                                            :class="(level.progress?.stars ?? 0) >= i ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/20'"
                                        />
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[7px] font-bold uppercase tracking-widest text-muted-foreground/60">Record Score</p>
                                        <p class="text-xs font-black tabular-nums text-emerald-500">{{ level.progress?.best_score ?? '0' }}</p>
                                    </div>
                                </div>

                                <div class="mt-auto pt-6">
                                    <div class="flex items-center justify-between rounded-xl bg-primary px-4 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary-foreground shadow-lg transition group-hover:bg-primary/90">
                                        <span>
                                            <template v-if="level.progress?.wins">Re-Deploy</template>
                                            <template v-else-if="level.progress">Continue</template>
                                            <template v-else>Deploy Now</template>
                                        </span>
                                        <ChevronRight class="h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-6">
                    <!-- Next objective -->
                    <div v-if="nextObjective" class="surface-card relative overflow-hidden group">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent" />
                        <div class="relative p-6">
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.3em] text-primary">
                                <Flame class="h-3.5 w-3.5 animate-pulse" /> Current Objective
                            </div>
                            <h3 class="mt-3 text-xl font-black uppercase tracking-tight text-foreground">{{ nextObjective.name }}</h3>
                            <p class="mt-2 text-xs leading-relaxed text-muted-foreground/80 line-clamp-3">{{ nextObjective.description }}</p>
                            
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest" :class="diffColor(nextObjective.difficulty.slug)">
                                    {{ nextObjective.difficulty.name }}
                                </span>
                                <span class="rounded border border-border/40 bg-muted/30 px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest text-muted-foreground flex items-center gap-1">
                                    <Zap class="h-3 w-3" /> {{ nextObjective.waves_count }} Waves
                                </span>
                            </div>

                            <Link
                                :href="`/games/tower-defense/play/${nextObjective.slug}`"
                                class="mt-6 flex items-center justify-center gap-2 rounded-xl bg-primary py-3 text-[11px] font-black uppercase tracking-[0.2em] text-primary-foreground shadow-lg hover:bg-primary/90 transition-all duration-300"
                            >
                                Launch Mission <ChevronRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <!-- Difficulty breakdown -->
                    <div v-if="byDifficulty.length" class="surface-card">
                        <div class="flex items-center justify-between border-b border-border/40 px-6 py-4">
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-foreground">Tier Progression</h3>
                            <Trophy class="h-4 w-4 text-primary/60" />
                        </div>
                        <ul class="flex flex-col">
                            <li v-for="b in byDifficulty" :key="b.slug" class="flex flex-col gap-3 p-6 border-b border-border/40 last:border-0">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase tracking-widest" :class="diffColor(b.slug).split(' ')[0]">
                                        {{ b.name }}
                                    </span>
                                    <span class="text-[10px] font-black uppercase tracking-widest tabular-nums text-muted-foreground/80">
                                        {{ b.cleared }} / {{ b.count }}
                                    </span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/40 p-0.5">
                                    <div
                                        class="h-full rounded-full transition-all duration-1000 shadow-[0_0_10px_-2px_rgba(0,0,0,0.1)]"
                                        :class="diffFill(b.slug)"
                                        :style="{ width: (b.maxStars ? (b.stars / b.maxStars) * 100 : 0) + '%' }"
                                    />
                                </div>
                                <div class="flex items-center justify-between text-[8px] font-black uppercase tracking-widest text-muted-foreground/60">
                                    <span>Stars</span>
                                    <span class="text-amber-500/80">{{ b.stars }} / {{ b.maxStars }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Manual / Tips -->
                    <div class="surface-card p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/5 border border-primary/10">
                                <Target class="h-4 w-4 text-primary" />
                            </div>
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em]">Tactical Guide</h3>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="(tip, idx) in [
                                'Use setup time to strategically place and upgrade your core defenses.',
                                'Right-click tower slots to cancel or refund placements during the build phase.',
                                'Stars are earned based on core integrity. Maintain 90%+ HP for a 3-star clear.',
                                'Selling towers refunds 70% of total investment. Adapt your strategy mid-game.'
                            ]" :key="idx" class="flex gap-3">
                                <div class="mt-1 h-1 w-1 shrink-0 rounded-full bg-primary/40" />
                                <p class="text-[11px] leading-relaxed text-muted-foreground/80">{{ tip }}</p>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
