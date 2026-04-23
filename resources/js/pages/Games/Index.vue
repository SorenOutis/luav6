<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Gamepad2, Shield, ChevronRight, Sparkles, Clock, Trophy } from 'lucide-vue-next';

interface GameStat { label: string; value: string }
interface GameCard {
    slug: string;
    name: string;
    tagline: string;
    description: string;
    status: 'live' | 'soon';
    href: string;
    accent: string;
    tags: string[];
    stats: GameStat[];
}

defineProps<{ games: GameCard[] }>();

const breadcrumbs = [{ title: 'Games', href: '/games' }];

const upcoming = [
    { name: 'Word Sprint', desc: 'Type fast. Climb the leaderboard.' },
    { name: 'Logic Grid', desc: 'Daily deduction puzzles.' },
    { name: 'Code Duel', desc: 'Head-to-head algorithm battles.' },
];
</script>

<template>
    <Head title="Games" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 xl:p-6">
            <!-- Hero -->
            <div class="relative overflow-hidden border border-border bg-card">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/15 via-transparent to-transparent" />
                <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-primary/60 via-primary/20 to-transparent" />
                <div class="relative flex flex-wrap items-center justify-between gap-6 p-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center border border-primary/40 bg-primary/10">
                            <Gamepad2 class="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-primary">Arcade</p>
                            <h1 class="text-2xl font-black uppercase tracking-tight">Games Hub</h1>
                            <p class="mt-1 text-sm text-muted-foreground">Pick a game, climb the leaderboard, earn stars.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 text-xs">
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Sparkles class="h-4 w-4 text-primary" />
                            <span class="font-bold uppercase tracking-widest">{{ games.length }} available</span>
                        </div>
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Clock class="h-4 w-4" />
                            <span class="font-bold uppercase tracking-widest">{{ upcoming.length }} coming soon</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
                <!-- Games list -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="game in games"
                        :key="game.slug"
                        :href="game.href"
                        class="group relative flex flex-col overflow-hidden border border-border bg-card transition hover:-translate-y-0.5 hover:border-primary"
                    >
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary/60 via-primary to-primary/60 opacity-70 group-hover:opacity-100" />
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-primary/10 via-transparent to-transparent opacity-0 transition group-hover:opacity-100" />

                        <div class="relative flex items-start justify-between gap-3 border-b border-border p-5">
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 items-center justify-center border border-primary/40 bg-primary/10">
                                    <Shield class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.3em] text-primary">{{ game.tagline }}</p>
                                    <h2 class="mt-0.5 text-lg font-black uppercase tracking-tight">{{ game.name }}</h2>
                                </div>
                            </div>
                            <span
                                class="border px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.25em]"
                                :class="game.status === 'live'
                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'
                                    : 'border-border bg-muted text-muted-foreground'"
                            >
                                {{ game.status === 'live' ? 'Live' : 'Soon' }}
                            </span>
                        </div>

                        <div class="relative flex flex-1 flex-col gap-4 p-5">
                            <p class="text-sm text-muted-foreground">{{ game.description }}</p>

                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="tag in game.tags"
                                    :key="tag"
                                    class="border border-border bg-muted px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest text-muted-foreground"
                                >
                                    {{ tag }}
                                </span>
                            </div>

                            <div class="mt-auto grid grid-cols-2 gap-2 border-t border-border pt-4 sm:grid-cols-3">
                                <div
                                    v-for="s in game.stats"
                                    :key="s.label"
                                    class="border border-border bg-muted/40 p-2"
                                >
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground">{{ s.label }}</p>
                                    <p class="mt-0.5 text-sm font-black tabular-nums">{{ s.value }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between border border-primary bg-primary p-2.5 text-[11px] font-black uppercase tracking-widest text-primary-foreground transition group-hover:opacity-90">
                                <span>Play {{ game.name }}</span>
                                <ChevronRight class="h-4 w-4" />
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-4">
                    <div class="border border-border bg-card">
                        <div class="flex items-center gap-2 border-b border-border px-4 py-2.5">
                            <Trophy class="h-4 w-4 text-primary" />
                            <h2 class="text-xs font-black uppercase tracking-[0.25em]">Featured</h2>
                        </div>
                        <div class="p-4">
                            <p class="text-sm font-black uppercase tracking-tight">Tower Defense</p>
                            <p class="mt-1 text-xs text-muted-foreground">Our flagship arcade experience — strategic tower placement across escalating difficulty tiers.</p>
                            <Link
                                href="/games/tower-defense"
                                class="mt-3 flex items-center justify-center gap-2 border border-primary bg-primary py-2 text-[11px] font-black uppercase tracking-widest text-primary-foreground hover:opacity-90"
                            >
                                Jump In <ChevronRight class="h-3 w-3" />
                            </Link>
                        </div>
                    </div>

                    <div class="border border-border bg-card">
                        <div class="flex items-center gap-2 border-b border-border px-4 py-2.5">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                            <h2 class="text-xs font-black uppercase tracking-[0.25em]">Coming Soon</h2>
                        </div>
                        <ul class="flex flex-col divide-y divide-border">
                            <li v-for="u in upcoming" :key="u.name" class="flex items-start gap-3 p-3">
                                <div class="mt-0.5 h-2 w-2 shrink-0 bg-muted-foreground/40" />
                                <div>
                                    <p class="text-xs font-black uppercase tracking-widest">{{ u.name }}</p>
                                    <p class="text-[11px] text-muted-foreground">{{ u.desc }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="border border-border bg-card p-4">
                        <div class="flex items-center gap-2">
                            <Sparkles class="h-4 w-4 text-primary" />
                            <h2 class="text-xs font-black uppercase tracking-[0.25em]">Tip</h2>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Earn stars by clearing levels at higher difficulty. Stars contribute to your seasonal rank.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
