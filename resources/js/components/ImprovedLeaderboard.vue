<script setup lang="ts">
import { ref, computed } from 'vue';
import gsap from 'gsap';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Trophy, Crown, TrendingUp, TrendingDown, Minus, Medal, Sparkles, User, Award } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

interface LeaderboardUser {
    id: number;
    name: string;
    xp: number;
    avatar?: string;
    completionRate: number;
    streak: number;
    joinedAt: string;
    weeklyXp: number;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser?: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface Props {
    sectionLeaderboards: LeaderboardData[];
    activeSeasonName?: string;
}

const props = defineProps<Props>();

const activeTabIndex = ref(0);

const activeLeaderboard = computed(() => {
    return props.sectionLeaderboards[activeTabIndex.value] || null;
});

const users = computed(() => activeLeaderboard.value?.users || []);
const userRank = computed(() => activeLeaderboard.value?.userRank || 0);
const totalPlayers = computed(() => activeLeaderboard.value?.totalPlayers || 0);
const sectionName = computed(() => activeLeaderboard.value?.sectionName || '');

// Mock data extension for richer visuals
const top3 = computed(() => users.value.slice(0, 3));

const showAllRankings = ref(false);
const displayedUsers = computed(() => {
    return showAllRankings.value ? users.value : users.value.slice(0, 10);
});

// Animated XP for top 3
const animXP1 = useNumberAnimation(() => top3.value[0]?.xp || 0);
const animXP2 = useNumberAnimation(() => top3.value[1]?.xp || 0);
const animXP3 = useNumberAnimation(() => top3.value[2]?.xp || 0);

const getAnimXP = (idx: number) => {
    if (idx === 0) return animXP1;
    if (idx === 1) return animXP2;
    if (idx === 2) return animXP3;
    return { value: top3.value[idx]?.xp || 0 };
};

const handleMouseMove = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const handleMagnetic = (e: MouseEvent) => {
    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    
    gsap.to(btn, {
        x: x * 0.3,
        y: y * 0.3,
        duration: 0.3,
        ease: 'power2.out'
    });
};

const resetMagnetic = (e: MouseEvent) => {
    const btn = e.currentTarget as HTMLElement;
    gsap.to(btn, {
        x: 0,
        y: 0,
        duration: 0.5,
        ease: 'elastic.out(1, 0.3)'
    });
};
</script>

<template>
    <div class="space-y-8">
        <!-- Section Tabs (Only if more than 1 section) -->
        <div v-if="sectionLeaderboards.length > 1" class="flex items-center gap-2 overflow-x-auto pb-2 custom-scrollbar no-scrollbar">
            <button 
                v-for="(section, idx) in sectionLeaderboards" 
                :key="section.sectionId"
                @click="activeTabIndex = idx"
                :class="[
                    'px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border shrink-0',
                    activeTabIndex === idx 
                        ? 'bg-primary text-primary-foreground border-primary shadow-[0_8px_20px_-6px_rgba(var(--primary),0.4)] scale-105' 
                        : 'bg-card text-muted-foreground border-border/40 hover:border-primary/40 hover:text-foreground'
                ]"
            >
                {{ section.sectionName }}
            </button>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 px-2">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Trophy class="w-4 h-4 text-primary" />
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary">
                        {{ sectionName ? `${sectionName} Rankings` : 'Global Rankings' }}
                    </span>
                </div>
                <h2 class="text-3xl font-black tracking-tighter">
                    {{ sectionName ? 'Section Elite' : 'Elite Assembly' }}
                </h2>
                <p v-if="totalPlayers > 0" class="text-sm text-muted-foreground font-medium mt-1">
                    Competition is fierce. You are ranked <span class="text-foreground font-bold">#{{ userRank }}</span> out of {{ totalPlayers.toLocaleString() }}.
                </p>
                <p v-else class="text-sm text-muted-foreground font-medium mt-1">
                    Join a section to see your ranking.
                </p>
            </div>
            <button 
                @mousemove="handleMagnetic" 
                @mouseleave="resetMagnetic"
                class="text-xs font-bold px-4 py-2 rounded-xl border border-border/40 bg-card hover:bg-muted transition-colors relative z-10"
            >
                View All Time
            </button>
        </div>

        <!-- Empty State Filter -->
        <div v-if="users.length === 0" class="surface-card p-12 flex flex-col items-center justify-center text-center animate-fade-in border border-border/50 rounded-2xl">
            <div class="w-20 h-20 mb-6 bg-muted/30 rounded-full flex items-center justify-center">
                <Trophy class="w-10 h-10 text-muted-foreground/50" />
            </div>
            <h3 class="text-2xl font-black tracking-tighter mb-2">The Arena is Empty</h3>
            <p class="text-muted-foreground max-w-sm">No warriors have claimed their spot on the leaderboard yet. Be the first to earn XP and make your mark.</p>
        </div>

        <template v-else>
            <!-- Elite Top 3 Cards -->
            <div class="grid grid-cols-3 gap-2 sm:gap-6">
                <div v-for="(user, idx) in top3" :key="user.id"
                    class="relative surface-card p-2 sm:p-6 flex flex-col items-center text-center group transition-all duration-500 hover:-translate-y-2 animate-fade-up overflow-hidden"
                    :class="[
                        idx === 0 ? 'order-2 border-primary/20 scale-105 sm:scale-110 shadow-2xl shadow-primary/5 z-20' : (idx === 1 ? 'order-1' : 'order-3'),
                        `stagger-${idx + 1}`
                    ]"
                >
                    <!-- Rank Medal/Crown Overlay -->
                    <div class="absolute -top-3 sm:-top-4 px-2 sm:px-4 py-0.5 sm:py-1 rounded-full text-[7px] sm:text-[10px] font-black uppercase tracking-[0.1em] sm:tracking-[0.2em] shadow-xl whitespace-nowrap"
                        :class="idx === 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground border border-border/40'"
                    >
                        #{{ idx + 1 }}
                    </div>

                    <!-- Avatar Visual -->
                    <div class="relative mb-2 sm:mb-6 mt-1 sm:mt-2">
                        <div class="absolute inset-0 rounded-full blur-xl sm:blur-2xl opacity-20 transition-opacity group-hover:opacity-40"
                            :class="idx === 0 ? 'bg-primary' : 'bg-muted-foreground'"
                        ></div>
                        <Link :href="`/u/${user.id}`" class="block relative w-12 h-12 sm:w-24 sm:h-24 rounded-full border-2 p-0.5 sm:p-1 transition-transform duration-500 group-hover:scale-110"
                            :class="idx === 0 ? 'border-primary/50' : 'border-border/50'"
                        >
                            <div class="w-full h-full rounded-full bg-muted/30 flex items-center justify-center overflow-hidden">
                                <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                <User v-else class="w-5 h-5 sm:w-10 sm:h-10 text-muted-foreground/40" />
                            </div>
                        </Link>
                        <!-- Icon Overlay -->
                        <div v-if="idx === 0" class="absolute -bottom-1 -right-1 p-1 sm:p-2 bg-primary rounded-lg sm:rounded-xl shadow-lg animate-bounce">
                            <Crown class="w-3 h-3 sm:w-4 h-4 text-primary-foreground" />
                        </div>
                    </div>

                    <div class="space-y-0.5 sm:space-y-1 mb-2 sm:mb-6 w-full">
                        <h3 class="font-bold text-[10px] sm:text-lg truncate w-full px-1 sm:px-4"><Link :href="`/u/${user.id}`" class="hover:underline">{{ user.name }}</Link></h3>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-1.5 leading-none">
                            <span class="text-[8px] sm:text-xs font-bold text-primary">{{ getAnimXP(idx).value.toLocaleString() }} XP</span>
                            <div class="hidden sm:block w-1 h-1 rounded-full bg-muted-foreground/30"></div>
                            <span class="text-[7px] sm:text-[10px] font-bold uppercase tracking-wider text-muted-foreground opacity-60 sm:opacity-100">{{ activeSeasonName || 'Season 1' }}</span>
                        </div>
                    </div>

                    <!-- Stats Bar -->
                    <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-1 sm:gap-2 border-t border-border/10 sm:border-border/20 pt-2 sm:pt-4 mt-auto">
                        <div class="text-center sm:text-left">
                            <p class="text-[7px] sm:text-[8px] font-bold uppercase text-muted-foreground opacity-50">Completion</p>
                            <p class="text-[9px] sm:text-xs font-black">{{ user.completionRate }}%</p>
                        </div>
                        <div class="text-center sm:text-right hidden sm:block">
                            <p class="text-[8px] font-bold uppercase text-muted-foreground opacity-50">Streak</p>
                            <div class="flex items-center justify-center sm:justify-end gap-1">
                                <Sparkles class="w-2.5 h-2.5 text-primary" />
                                <span class="text-xs font-black">{{ user.streak }}d</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- High Density List Rankings -->
            <div class="surface-card overflow-hidden">
                <div class="px-6 py-4 border-b border-border/20 bg-muted/5 flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">All Rankings</span>
                    <div class="flex items-center gap-4">
                        <span class="text-[10px] font-bold text-muted-foreground">Sort: <span class="text-foreground">Monthly</span></span>
                    </div>
                </div>
                
                <div class="divide-y divide-border/10">
                    <div v-for="(user, idx) in displayedUsers" :key="user.id"
                        class="group px-6 py-4 flex items-center justify-between hover:bg-primary/[0.02] transition-colors cursor-default animate-fade-up relative overflow-hidden"
                        :class="[`stagger-${idx + 4}`, { 'bg-primary/[0.03]': user.isCurrentUser }]"
                        @mousemove="handleMouseMove"
                    >
                        <!-- Row Bloom Effect -->
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                            style="background: radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.05), transparent 40%)">
                        </div>

                        <div class="flex items-center gap-6">
                            <span class="text-sm font-black text-muted-foreground/40 w-6">#{{ idx + 1 }}</span>
                            <div class="flex items-center gap-3">
                                <Link :href="`/u/${user.id}`" class="w-10 h-10 rounded-xl bg-muted/30 border border-border/20 flex items-center justify-center overflow-hidden shrink-0 group-hover:scale-105 transition-transform block">
                                    <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                    <User v-else class="w-5 h-5 text-muted-foreground/40" />
                                </Link>
                                <div>
                                    <h4 class="text-sm font-bold flex items-center gap-2">
                                        {{ user.name }}
                                        <span v-if="user.isCurrentUser" class="text-[8px] uppercase px-1.5 py-0.5 rounded bg-primary text-primary-foreground font-black">You</span>
                                    </h4>
                                    <div class="flex items-center gap-2 text-[10px] text-muted-foreground font-medium">
                                        <span>Joined {{ user.joinedAt }}</span>
                                        <div class="w-0.5 h-0.5 rounded-full bg-muted-foreground/30"></div>
                                        <span class="flex items-center gap-1">
                                            <Award class="w-3 h-3 text-primary" /> Silver III
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-8">
                            <!-- Trend indicator -->
                            <div class="hidden sm:flex flex-col items-center">
                                <component :is="user.trend === 'up' ? TrendingUp : (user.trend === 'down' ? TrendingDown : Minus)" 
                                    class="w-4 h-4" 
                                    :class="user.trend === 'up' ? 'text-emerald-500' : (user.trend === 'down' ? 'text-destructive' : 'text-muted-foreground')"
                                />
                                <span class="text-[8px] font-bold uppercase mt-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">Trend</span>
                            </div>

                            <!-- Data Column -->
                            <div class="text-right min-w-[100px]">
                                <p class="text-sm font-black tabular-nums">{{ user.xp.toLocaleString() }} <span class="text-[10px] font-bold text-muted-foreground">XP</span></p>
                                <p class="text-[9px] font-bold text-emerald-500">+{{ user.weeklyXp >= 1000 ? (user.weeklyXp / 1000).toFixed(1) + 'k' : user.weeklyXp }} weekly</p>
                            </div>

                            <!-- Action -->
                            <Link :href="`/u/${user.id}`" class="flex p-2 rounded-xl text-muted-foreground hover:bg-primary/10 hover:text-primary transition-all opacity-0 group-hover:opacity-100">
                                 <TrendingUp class="w-4 h-4 rotate-45" />
                            </Link>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-muted/5 text-center" v-if="users.length > 10">
                    <button 
                        @click="showAllRankings = !showAllRankings"
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ showAllRankings ? 'Show Less Rankings' : 'Load More Rankings' }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* Custom Noise Texture Overlay for Cards */
.surface-card::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.015;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
}
</style>
