<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Shield, Star, Zap, MapPin, Trophy, Target, Swords, Layers, Flame, ChevronRight, Gauge, Crosshair } from 'lucide-vue-next';
import { useLoader } from '@/composables/useLoader';
import { Motion } from '@motionone/vue';

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);

onMounted(() => {
    // Sync isBooted with global loader
    if (!isLoaderVisible.value) {
        isBooted.value = true;
    }

    watch(isLoaderVisible, (visible) => {
        if (!visible) {
            isBooted.value = true;
        }
    }, { immediate: true });
});

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
        <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-6 p-4">
            <!-- Hero -->
            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.1 }"
                class="relative overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
            >
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent opacity-50" />
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />

                <div class="relative grid gap-8 p-6 lg:grid-cols-[1fr_auto] lg:items-center lg:p-10">
                    <div class="flex items-center gap-6">
                        <div class="relative flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5 shadow-inner group/icon">
                            <Shield class="h-10 w-10 text-primary transition-transform duration-700 group-hover/icon:scale-110" />
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
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:gap-8">
                        <div v-for="s in [
                            { label: 'Deployment', val: stats.cleared, total: stats.total, icon: MapPin },
                            { label: 'Integrity', val: stats.winRate, unit: '%', icon: Shield },
                            { label: 'Collection', val: stats.totalStars, total: stats.maxStars, icon: Star },
                            { label: 'Rating', val: stats.bestScore, icon: Trophy }
                        ]" :key="s.label" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2 text-muted-foreground/60">
                                <component :is="s.icon" class="h-3 w-3" />
                                <span class="text-[8px] font-black uppercase tracking-widest font-mono">{{ s.label }}</span>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-black tabular-nums text-foreground">{{ s.val }}</span>
                                <span v-if="s.total" class="text-[10px] font-bold text-muted-foreground/40 font-mono">/{{ s.total }}</span>
                                <span v-if="s.unit" class="text-[10px] font-bold text-primary font-mono">{{ s.unit }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Motion>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
                <div class="flex flex-col gap-8">
                    <div v-if="levels.length === 0" class="flex flex-col items-center justify-center py-20 text-center surface-card rounded-2xl border border-dashed border-border/60">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/30 mb-4">
                            <Shield class="h-8 w-8 text-muted-foreground/40" />
                        </div>
                        <p class="text-sm font-black uppercase tracking-widest text-foreground">No levels deployed</p>
                        <p class="mt-1 text-xs text-muted-foreground max-w-xs">The mission roster is currently empty. Contact command for deployment.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2 2xl:grid-cols-3">
                        <Motion
                            v-for="(level, lIdx) in sortedLevels"
                            :key="level.id"
                            :initial="{ opacity: 0, y: 40 }"
                            :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                            :in-view-options="{ once: true, margin: '-50px' }"
                            :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: lIdx * 0.05 }"
                            as="div"
                        >
                            <Link
                                :href="`/games/tower-defense/play/${level.slug}`"
                                class="surface-card premium-hover group flex flex-col h-full rounded-xl border border-border"
                            >
                                <div class="relative flex items-center justify-between gap-4 border-b border-border/40 p-5">
                                    <div class="flex flex-1 min-w-0 items-center gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 shadow-sm group-hover:border-primary/40 transition-colors">
                                            <span class="text-sm font-black tabular-nums text-primary">{{ level.order }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <h2 class="truncate text-sm font-black uppercase tracking-widest text-foreground group-hover:text-primary transition-colors">{{ level.name }}</h2>
                                            <p class="mt-0.5 text-[9px] font-bold uppercase tracking-[0.2em] text-muted-foreground/60">{{ level.map.name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <Star 
                                            v-for="i in 3" :key="i"
                                            class="h-3 w-3 transition-all duration-500"
                                            :class="i <= (level.progress?.stars || 0) ? 'fill-primary text-primary group-hover:scale-110' : 'text-muted-foreground/20'"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-4 p-5">
                                    <p class="text-[11px] leading-relaxed text-muted-foreground/80 line-clamp-2 h-8">{{ level.description }}</p>
                                    
                                    <div class="flex items-center justify-between pt-4 border-t border-border/40">
                                        <div class="flex gap-4">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[7px] font-bold uppercase tracking-widest text-muted-foreground/50 font-mono">Difficulty</span>
                                                <span class="text-[9px] font-black uppercase tracking-wider" :class="diffColor(level.difficulty.slug)">{{ level.difficulty.name }}</span>
                                            </div>
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-[7px] font-bold uppercase tracking-widest text-muted-foreground/50 font-mono">Waves</span>
                                                <span class="text-[9px] font-black uppercase tracking-wider text-foreground">{{ level.waves_count }}</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="level.progress?.wins" class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                            <Swords class="h-4 w-4" />
                                        </div>
                                        <div v-else class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-primary-foreground border border-primary/20">
                                            <ChevronRight class="h-4 w-4" />
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </Motion>
                    </div>
                </div>

                <aside class="flex flex-col gap-8">
                    <!-- Current Objective -->
                    <Motion
                        v-if="nextObjective"
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.2 }"
                        class="surface-card relative overflow-hidden p-6 rounded-2xl border border-border bg-card shadow-xl"
                    >
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-50" />
                        <div class="relative">
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
                                class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary-foreground shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] hover:shadow-[0_0_30px_rgba(var(--primary-rgb),0.5)] transition-all duration-500 group/btn"
                            >
                                Deploy Now <ChevronRight class="h-3 w-3 transition-transform group-hover/btn:translate-x-1" />
                            </Link>
                        </div>
                    </Motion>

                    <!-- Tactical Guide -->
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.3 }"
                        class="surface-card p-6 rounded-2xl border border-border bg-card shadow-xl"
                    >
                        <div class="mb-5 flex items-center gap-3 border-b border-border/40 pb-4">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/5 border border-primary/10">
                                <Target class="h-4 w-4 text-primary" />
                            </div>
                            <h3 class="text-[11px] font-black uppercase tracking-[0.3em]">Tactical Guide</h3>
                        </div>
                        <ul class="space-y-4">
                            <li v-for="(tip, idx) in [
                                'Use setup time to strategically place and upgrade your core defenses.',
                                'Right-click tower slots to cancel or refund placements during the build phase.',
                                'Stars are earned based on core integrity. Maintain 90%+ HP for a 3-star clear.',
                                'Selling towers refunds 70% of total investment. Adapt your strategy mid-game.'
                            ]" :key="idx" class="flex gap-4">
                                <div class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary/40" />
                                <p class="text-[11px] leading-relaxed text-muted-foreground/80 font-medium">{{ tip }}</p>
                            </li>
                        </ul>
                    </Motion>

                    <!-- Security Alert -->
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.4 }"
                        class="surface-card p-6 rounded-2xl border border-border bg-primary/[0.02] shadow-xl"
                    >
                        <div class="mb-4 flex items-center justify-between">
                             <div class="flex items-center gap-2">
                                <Shield class="h-4 w-4 text-primary" />
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Integrity Protocol</h3>
                             </div>
                             <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        </div>
                        <p class="text-[10px] leading-relaxed text-muted-foreground/60 font-mono italic">
                            Mission progress is encrypted and synced with the LSI core. Unauthorized modification will result in session termination.
                        </p>
                    </Motion>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
