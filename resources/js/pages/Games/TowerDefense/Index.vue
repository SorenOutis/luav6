<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    Shield,
    Star,
    Zap,
    MapPin,
    Trophy,
    Target,
    Swords,
    Layers,
    Flame,
    ChevronRight,
    Gauge,
    Crosshair,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';

const { isVisible: isLoaderVisible } = useLoader();
const isBooted = ref(false);

onMounted(() => {
    // Sync isBooted with global loader
    if (!isLoaderVisible.value) {
        isBooted.value = true;
    }

    watch(
        isLoaderVisible,
        (visible) => {
            if (!visible) {
                isBooted.value = true;
            }
        },
        { immediate: true },
    );
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
        if (l.progress.best_score > bestScore)
            bestScore = l.progress.best_score;
    }
    const winRate =
        totalPlays > 0 ? Math.round((totalWins / totalPlays) * 100) : 0;
    return {
        total,
        cleared,
        totalStars,
        maxStars,
        bestScore,
        totalPlays,
        totalWins,
        winRate,
    };
});

const completionPct = computed(() => {
    if (stats.value.maxStars === 0) return 0;
    return Math.round((stats.value.totalStars / stats.value.maxStars) * 100);
});

const sortedLevels = computed(() => {
    const difficultyOrder: Record<string, number> = {
        easy: 1,
        normal: 2,
        hard: 3,
        nightmare: 4,
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

interface DiffBucket {
    slug: string;
    name: string;
    count: number;
    cleared: number;
    stars: number;
    maxStars: number;
}
const byDifficulty = computed<DiffBucket[]>(() => {
    const map = new Map<string, DiffBucket>();
    for (const l of props.levels) {
        const key = l.difficulty.slug;
        if (!map.has(key)) {
            map.set(key, {
                slug: key,
                name: l.difficulty.name,
                count: 0,
                cleared: 0,
                stars: 0,
                maxStars: 0,
            });
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
        case 'easy':
            return 'text-emerald-400 border-emerald-500/40 bg-emerald-500/5';
        case 'normal':
            return 'text-sky-400 border-sky-500/40 bg-sky-500/5';
        case 'hard':
            return 'text-amber-400 border-amber-500/40 bg-amber-500/5';
        case 'nightmare':
            return 'text-rose-400 border-rose-500/40 bg-rose-500/5';
        default:
            return 'text-muted-foreground border-border';
    }
};

const diffAccent = (slug: string) => {
    switch (slug) {
        case 'easy':
            return 'from-emerald-500/60 via-emerald-500/20';
        case 'normal':
            return 'from-sky-500/60 via-sky-500/20';
        case 'hard':
            return 'from-amber-500/60 via-amber-500/20';
        case 'nightmare':
            return 'from-rose-500/60 via-rose-500/20';
        default:
            return 'from-border via-border/40';
    }
};

const diffFill = (slug: string) => {
    switch (slug) {
        case 'easy':
            return 'bg-emerald-500';
        case 'normal':
            return 'bg-sky-500';
        case 'hard':
            return 'bg-amber-500';
        case 'nightmare':
            return 'bg-rose-500';
        default:
            return 'bg-border';
    }
};

const diffGlowColor = (
    slug: string,
): 'orange' | 'green' | 'purple' | 'blue' | 'red' | 'emerald' => {
    switch (slug) {
        case 'easy':
            return 'green';
        case 'normal':
            return 'blue';
        case 'hard':
            return 'orange';
        case 'nightmare':
            return 'red';
        default:
            return 'blue';
    }
};

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};
</script>

<template>
    <Head title="Tower Defense" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="container"
            class="relative mx-auto flex h-full max-w-[1400px] flex-1 flex-col gap-8 overflow-hidden bg-background p-4 perspective-[1000px] md:p-10"
        >
            <!-- Decorative Orbs -->
            <div
                class="orb pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>
            <div
                class="orb pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"
            ></div>

            <!-- Hero -->
            <Motion
                :initial="{ opacity: 0, y: 30 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{
                    duration: 1,
                    ease: [0.16, 1, 0.3, 1],
                    delay: 0.1,
                }"
                class="header-content group/hero relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-end"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <div
                            class="h-[2px] w-8 rounded-full bg-primary/40 transition-all duration-500 group-hover/hero:w-12"
                        ></div>
                        <h1
                            class="text-2xl font-black tracking-tighter uppercase"
                        >
                            Tower_Defense
                        </h1>
                    </div>
                    <p
                        class="border-l-2 border-primary/10 pl-11 text-sm text-[9px] font-medium tracking-widest text-muted-foreground uppercase transition-colors group-hover/hero:border-primary/30"
                    >
                        Arcade Strategy — deploy defenses, survive waves,
                        maximize integrity.
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <div
                        class="flex h-10 items-center gap-2 rounded-full border border-primary/10 bg-primary/5 px-4 py-1.5 font-mono"
                    >
                        <Shield class="h-3.5 w-3.5 text-primary" />
                        <span
                            class="text-[9px] font-black tracking-widest uppercase"
                            >DEFENSE:ACTIVE</span
                        >
                    </div>
                </div>
            </Motion>

            <!-- Stats Overview -->
            <div class="z-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                <Motion
                    v-for="(s, sIdx) in [
                        {
                            label: 'Deployment',
                            val: stats.cleared,
                            total: stats.total,
                            icon: MapPin,
                            glowColor: 'blue' as const,
                        },
                        {
                            label: 'Integrity',
                            val: stats.winRate,
                            unit: '%',
                            icon: Shield,
                            glowColor: 'green' as const,
                        },
                        {
                            label: 'Collection',
                            val: stats.totalStars,
                            total: stats.maxStars,
                            icon: Star,
                            glowColor: 'purple' as const,
                        },
                        {
                            label: 'Best Score',
                            val: stats.bestScore,
                            icon: Trophy,
                            glowColor: 'orange' as const,
                        },
                    ]"
                    :key="s.label"
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{
                        duration: 0.8,
                        ease: [0.16, 1, 0.3, 1],
                        delay: 0.2 + sIdx * 0.1,
                    }"
                >
                    <SpotlightCard
                        customSize
                        :glowColor="s.glowColor"
                        :spotlightSize="300"
                        className="stats-card p-4 relative group/stat premium-hover bg-card/40 flex flex-col justify-between"
                        @mousemove="handleMouseMove"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                        >
                            <div
                                class="absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-40 blur-3xl transition-opacity duration-700 group-hover/stat:opacity-70"
                                :class="{
                                    'bg-blue-500/30': s.glowColor === 'blue',
                                    'bg-emerald-500/30':
                                        s.glowColor === 'green',
                                    'bg-purple-500/30':
                                        s.glowColor === 'purple',
                                    'bg-orange-500/30':
                                        s.glowColor === 'orange',
                                }"
                            ></div>
                            <div
                                class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full opacity-25 blur-3xl transition-opacity duration-700 group-hover/stat:opacity-50"
                                :class="{
                                    'bg-blue-400/25': s.glowColor === 'blue',
                                    'bg-emerald-400/25':
                                        s.glowColor === 'green',
                                    'bg-purple-400/25':
                                        s.glowColor === 'purple',
                                    'bg-orange-400/25':
                                        s.glowColor === 'orange',
                                }"
                            ></div>
                            <div
                                class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/stat:opacity-100"
                            ></div>
                            <div
                                class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/stat:opacity-100"
                            ></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="flex items-center gap-2 text-muted-foreground/60"
                            >
                                <component :is="s.icon" class="h-3 w-3" />
                                <span
                                    class="font-mono text-[8px] font-black tracking-widest uppercase"
                                    >{{ s.label }}</span
                                >
                            </div>
                            <div class="mt-1 flex items-baseline gap-1">
                                <span
                                    class="text-xl font-black text-foreground tabular-nums"
                                    >{{ s.val }}</span
                                >
                                <span
                                    v-if="s.total"
                                    class="font-mono text-[10px] font-bold text-muted-foreground/40"
                                    >/{{ s.total }}</span
                                >
                                <span
                                    v-if="s.unit"
                                    class="font-mono text-[10px] font-bold text-primary"
                                    >{{ s.unit }}</span
                                >
                            </div>
                        </div>
                    </SpotlightCard>
                </Motion>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_340px]">
                <div class="flex flex-col gap-8">
                    <div
                        v-if="levels.length === 0"
                        class="surface-card flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/60 py-20 text-center"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/30"
                        >
                            <Shield class="h-8 w-8 text-muted-foreground/40" />
                        </div>
                        <p
                            class="text-sm font-black tracking-widest text-foreground uppercase"
                        >
                            No levels deployed
                        </p>
                        <p class="mt-1 max-w-xs text-xs text-muted-foreground">
                            The mission roster is currently empty. Contact
                            command for deployment.
                        </p>
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2 2xl:grid-cols-3"
                    >
                        <Motion
                            v-for="(level, lIdx) in sortedLevels"
                            :key="level.id"
                            :initial="{ opacity: 0, y: 40 }"
                            :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                            :in-view-options="{ once: true, margin: '-50px' }"
                            :transition="{
                                duration: 1,
                                ease: [0.16, 1, 0.3, 1],
                                delay: lIdx * 0.05,
                            }"
                            as="div"
                        >
                            <SpotlightCard
                                customSize
                                :as="Link"
                                :href="`/games/tower-defense/play/${level.slug}`"
                                :glowColor="
                                    diffGlowColor(level.difficulty.slug)
                                "
                                :spotlightSize="300"
                                className="level-card relative group/level premium-hover bg-card/40 !flex flex-col h-full min-w-0 w-full overflow-hidden"
                                @mousemove="handleMouseMove"
                            >
                                <div
                                    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                                >
                                    <div
                                        class="absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-40 blur-3xl transition-opacity duration-700 group-hover/level:opacity-70"
                                        :class="{
                                            'bg-emerald-500/30':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'green',
                                            'bg-blue-500/30':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'blue',
                                            'bg-orange-500/30':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'orange',
                                            'bg-red-500/30':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'red',
                                        }"
                                    ></div>
                                    <div
                                        class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full opacity-25 blur-3xl transition-opacity duration-700 group-hover/level:opacity-50"
                                        :class="{
                                            'bg-emerald-400/25':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'green',
                                            'bg-blue-400/25':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'blue',
                                            'bg-orange-400/25':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'orange',
                                            'bg-red-400/25':
                                                diffGlowColor(
                                                    level.difficulty.slug,
                                                ) === 'red',
                                        }"
                                    ></div>
                                    <div
                                        class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/level:opacity-100"
                                    ></div>
                                    <div
                                        class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/level:opacity-100"
                                    ></div>
                                    <div
                                        class="absolute inset-0 opacity-[0.03] transition-opacity group-hover/level:opacity-[0.05]"
                                    >
                                        <svg
                                            class="h-full w-full"
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="100%"
                                            height="100%"
                                        >
                                            <defs>
                                                <pattern
                                                    :id="`level-grid-${level.id}`"
                                                    width="15"
                                                    height="15"
                                                    patternUnits="userSpaceOnUse"
                                                >
                                                    <path
                                                        d="M 15 0 L 0 0 0 15"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="0.5"
                                                    />
                                                </pattern>
                                            </defs>
                                            <rect
                                                width="100%"
                                                height="100%"
                                                :fill="`url(#level-grid-${level.id})`"
                                            />
                                        </svg>
                                    </div>
                                    <div
                                        class="group-hover/level:animate-scan-horizontal absolute inset-0 h-full w-32 -translate-x-full bg-gradient-to-r from-transparent via-primary/5 to-transparent opacity-0 transition-opacity group-hover/level:opacity-100"
                                    ></div>
                                </div>

                                <div
                                    class="relative z-10 flex flex-col gap-3 border-b border-border/40 p-5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 shadow-sm transition-colors group-hover/level:border-primary/40"
                                        >
                                            <span
                                                class="text-sm font-black text-primary tabular-nums"
                                                >{{ level.order }}</span
                                            >
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h2
                                                class="truncate text-sm font-black tracking-widest text-foreground uppercase transition-colors group-hover/level:text-primary"
                                            >
                                                {{ level.name }}
                                            </h2>
                                            <p
                                                class="mt-0.5 truncate text-[9px] font-bold tracking-[0.2em] text-muted-foreground/60 uppercase"
                                            >
                                                {{ level.map.name }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 gap-0.5">
                                            <Star
                                                v-for="i in 3"
                                                :key="i"
                                                class="h-3 w-3 transition-all duration-500"
                                                :class="
                                                    i <=
                                                    (level.progress?.stars || 0)
                                                        ? 'fill-amber-400 text-amber-400 drop-shadow-[0_0_4px_rgba(251,191,36,0.6)] group-hover/level:scale-110'
                                                        : 'fill-muted-foreground/10 text-muted-foreground/50'
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="relative z-10 flex flex-col gap-4 p-5"
                                >
                                    <p
                                        class="line-clamp-2 h-8 text-[11px] leading-relaxed text-muted-foreground/80"
                                    >
                                        {{ level.description }}
                                    </p>

                                    <div
                                        class="flex items-center justify-between gap-2 border-t border-border/40 pt-4"
                                    >
                                        <div class="flex min-w-0 gap-3">
                                            <div
                                                class="flex min-w-0 flex-col gap-0.5"
                                            >
                                                <span
                                                    class="font-mono text-[7px] font-bold tracking-widest text-muted-foreground/50 uppercase"
                                                    >Difficulty</span
                                                >
                                                <span
                                                    class="truncate text-[9px] font-black tracking-wider uppercase"
                                                    :class="
                                                        diffColor(
                                                            level.difficulty
                                                                .slug,
                                                        )
                                                    "
                                                    >{{
                                                        level.difficulty.name
                                                    }}</span
                                                >
                                            </div>
                                            <div class="flex flex-col gap-0.5">
                                                <span
                                                    class="font-mono text-[7px] font-bold tracking-widest text-muted-foreground/50 uppercase"
                                                    >Waves</span
                                                >
                                                <span
                                                    class="text-[9px] font-black tracking-wider text-foreground uppercase"
                                                    >{{
                                                        level.waves_count
                                                    }}</span
                                                >
                                            </div>
                                        </div>

                                        <div
                                            v-if="level.progress?.wins"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 transition-all duration-500 group-hover/level:border-emerald-500/50 group-hover/level:bg-emerald-500/20"
                                            :title="'Cleared'"
                                        >
                                            <Swords
                                                class="h-3.5 w-3.5 transition-transform duration-300 group-hover/level:scale-110"
                                            />
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-primary/30 bg-primary/10 text-primary transition-all duration-500 group-hover/level:border-primary group-hover/level:bg-primary group-hover/level:text-primary-foreground"
                                            :title="'Deploy'"
                                        >
                                            <ChevronRight
                                                class="h-3.5 w-3.5 transition-all duration-300 group-hover/level:translate-x-0.5"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </SpotlightCard>
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
                    >
                        <SpotlightCard
                            customSize
                            :glowColor="
                                diffGlowColor(nextObjective.difficulty.slug)
                            "
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col"
                            @mousemove="handleMouseMove"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                    :class="{
                                        'bg-emerald-500/30':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'green',
                                        'bg-blue-500/30':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'blue',
                                        'bg-orange-500/30':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'orange',
                                        'bg-red-500/30':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'red',
                                    }"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                    :class="{
                                        'bg-emerald-400/25':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'green',
                                        'bg-blue-400/25':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'blue',
                                        'bg-orange-400/25':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'orange',
                                        'bg-red-400/25':
                                            diffGlowColor(
                                                nextObjective.difficulty.slug,
                                            ) === 'red',
                                    }"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10 p-6">
                                <div
                                    class="flex items-center gap-2 text-[10px] font-black tracking-[0.3em] text-primary uppercase"
                                >
                                    <Flame class="h-3.5 w-3.5 animate-pulse" />
                                    Current Objective
                                </div>
                                <h3
                                    class="mt-3 text-xl font-black tracking-tight text-foreground uppercase"
                                >
                                    {{ nextObjective.name }}
                                </h3>
                                <p
                                    class="mt-2 line-clamp-3 text-xs leading-relaxed text-muted-foreground/80"
                                >
                                    {{ nextObjective.description }}
                                </p>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        class="rounded border px-2 py-0.5 text-[9px] font-bold tracking-widest uppercase"
                                        :class="
                                            diffColor(
                                                nextObjective.difficulty.slug,
                                            )
                                        "
                                    >
                                        {{ nextObjective.difficulty.name }}
                                    </span>
                                    <span
                                        class="flex items-center gap-1 rounded border border-border/40 bg-muted/30 px-2 py-0.5 text-[9px] font-bold tracking-widest text-muted-foreground uppercase"
                                    >
                                        <Zap class="h-3 w-3" />
                                        {{ nextObjective.waves_count }} Waves
                                    </span>
                                </div>

                                <Link
                                    :href="`/games/tower-defense/play/${nextObjective.slug}`"
                                    class="group/btn relative mt-6 flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-primary px-4 py-3 text-[10px] font-black tracking-[0.2em] text-primary-foreground uppercase shadow-[0_0_20px_rgba(var(--primary-rgb),0.3)] transition-all duration-500 hover:scale-[1.02] hover:shadow-[0_0_35px_rgba(var(--primary-rgb),0.5)] active:scale-[0.98]"
                                >
                                    <span class="relative z-10"
                                        >Deploy Now</span
                                    >
                                    <ChevronRight
                                        class="relative z-10 h-3 w-3 transition-transform group-hover/btn:translate-x-1"
                                    />
                                    <div
                                        class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent transition-transform duration-700 group-hover/btn:translate-x-full"
                                    ></div>
                                </Link>
                            </div>
                        </SpotlightCard>
                    </Motion>

                    <!-- Tactical Guide -->
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.3 }"
                    >
                        <SpotlightCard
                            customSize
                            glowColor="purple"
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col"
                            @mousemove="handleMouseMove"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-purple-500/30 opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-purple-400/25 opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10 p-6">
                                <div
                                    class="mb-5 flex items-center gap-3 border-b border-border/40 pb-4"
                                >
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-primary/10 bg-primary/5"
                                    >
                                        <Target class="h-4 w-4 text-primary" />
                                    </div>
                                    <h3
                                        class="text-[11px] font-black tracking-[0.3em] uppercase"
                                    >
                                        Tactical Guide
                                    </h3>
                                </div>
                                <ul class="space-y-4">
                                    <li
                                        v-for="(tip, idx) in [
                                            'Use setup time to strategically place and upgrade your core defenses.',
                                            'Right-click tower slots to cancel or refund placements during the build phase.',
                                            'Stars are earned based on core integrity. Maintain 90%+ HP for a 3-star clear.',
                                            'Selling towers refunds 70% of total investment. Adapt your strategy mid-game.',
                                        ]"
                                        :key="idx"
                                        class="flex gap-4"
                                    >
                                        <div
                                            class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary/40"
                                        />
                                        <p
                                            class="text-[11px] leading-relaxed font-medium text-muted-foreground/80"
                                        >
                                            {{ tip }}
                                        </p>
                                    </li>
                                </ul>
                            </div>
                        </SpotlightCard>
                    </Motion>

                    <!-- Security Alert -->
                    <Motion
                        :initial="{ opacity: 0, x: 20 }"
                        :animate="isBooted ? { opacity: 1, x: 0 } : {}"
                        :transition="{ duration: 0.8, delay: 0.4 }"
                    >
                        <SpotlightCard
                            customSize
                            glowColor="green"
                            :spotlightSize="300"
                            className="sidebar-card relative group/sidebar premium-hover bg-card/40 flex flex-col p-6"
                            @mousemove="handleMouseMove"
                        >
                            <div
                                class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                            >
                                <div
                                    class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-emerald-500/30 opacity-40 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-70"
                                ></div>
                                <div
                                    class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-emerald-400/25 opacity-25 blur-3xl transition-opacity duration-700 group-hover/sidebar:opacity-50"
                                ></div>
                                <div
                                    class="absolute top-0 left-0 h-4 w-4 rounded-tl-lg border-t-2 border-l-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                                <div
                                    class="absolute right-0 bottom-0 h-4 w-4 rounded-br-lg border-r-2 border-b-2 border-primary/20 opacity-0 transition-opacity duration-500 group-hover/sidebar:opacity-100"
                                ></div>
                            </div>
                            <div class="relative z-10">
                                <div
                                    class="mb-4 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <Shield class="h-4 w-4 text-primary" />
                                        <h3
                                            class="text-[10px] font-black tracking-[0.2em] uppercase"
                                        >
                                            Integrity Protocol
                                        </h3>
                                    </div>
                                    <div
                                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                    ></div>
                                </div>
                                <p
                                    class="font-mono text-[10px] leading-relaxed text-muted-foreground/60 italic"
                                >
                                    Mission progress is encrypted and synced
                                    with the LSI core. Unauthorized modification
                                    will result in session termination.
                                </p>
                            </div>
                        </SpotlightCard>
                    </Motion>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
