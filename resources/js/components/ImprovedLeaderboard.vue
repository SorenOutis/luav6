<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import gsap from 'gsap';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Trophy, Crown, TrendingUp, TrendingDown, Minus, Medal, Sparkles, User, Award, Search, Flame, Cpu, Terminal, Activity, History, Eye, Loader2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface LeaderboardUser {
    id: number;
    name: string;
    xp: number;
    avatar?: string;
    xpProgress: number;
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
const searchQuery = ref('');

// Persist active tab index in localStorage
const STORAGE_KEY = 'leaderboard_active_section_id';

onMounted(() => {
    const savedSectionId = localStorage.getItem(STORAGE_KEY);
    if (savedSectionId) {
        const index = props.sectionLeaderboards.findIndex(s => s.sectionId === parseInt(savedSectionId));
        if (index !== -1) {
            activeTabIndex.value = index;
        }
    }
});

watch(activeTabIndex, (newIndex) => {
    const section = props.sectionLeaderboards[newIndex];
    if (section) {
        localStorage.setItem(STORAGE_KEY, section.sectionId.toString());
    }
});

const activeLeaderboard = computed(() => {
    return props.sectionLeaderboards[activeTabIndex.value] || null;
});

const users = computed(() => activeLeaderboard.value?.users || []);

const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    const query = searchQuery.value.toLowerCase().trim();
    return users.value.filter(user => 
        user.name.toLowerCase().includes(query)
    );
});

const userRank = computed(() => activeLeaderboard.value?.userRank || 0);
const totalPlayers = computed(() => activeLeaderboard.value?.totalPlayers || 0);
const sectionName = computed(() => activeLeaderboard.value?.sectionName || '');

// Mock data extension for richer visuals
const top3 = computed(() => filteredUsers.value.slice(0, 3));

const showAllRankings = ref(false);
const displayedUsers = computed(() => {
    return showAllRankings.value ? filteredUsers.value : filteredUsers.value.slice(0, 10);
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

const getTopRankMeta = (idx: number) => {
    if (idx === 0) {
        return {
            label: 'TOP 1',
            title: '1st',
            icon: Crown,
            orderClass: 'lg:order-2',
            cardClass: 'border-amber-400/50 shadow-[0_20px_70px_-30px_rgba(251,191,36,0.45)] lg:-translate-y-6 lg:scale-[1.04]',
            badgeClass: 'bg-amber-400 text-black border-amber-200/80 shadow-[0_10px_30px_-10px_rgba(251,191,36,0.8)]',
            frameClass: 'border-amber-400/60 bg-amber-400/10',
            accentClass: 'from-amber-300/80 via-amber-400/40 to-transparent',
            haloClass: 'bg-amber-300/20',
            pedestalClass: 'from-amber-300/70 via-amber-400/30 to-amber-300/5',
            textClass: 'text-amber-300',
        };
    }

    if (idx === 1) {
        return {
            label: 'TOP 2',
            title: '2nd',
            icon: Medal,
            orderClass: 'lg:order-1',
            cardClass: 'border-slate-300/30 shadow-[0_18px_50px_-35px_rgba(226,232,240,0.35)] lg:translate-y-4',
            badgeClass: 'bg-slate-200 text-slate-900 border-white/70 shadow-[0_10px_30px_-12px_rgba(226,232,240,0.6)]',
            frameClass: 'border-slate-300/40 bg-slate-200/10',
            accentClass: 'from-slate-200/60 via-slate-300/20 to-transparent',
            haloClass: 'bg-slate-200/10',
            pedestalClass: 'from-slate-200/50 via-slate-300/20 to-slate-200/5',
            textClass: 'text-slate-200',
        };
    }

    return {
        label: 'TOP 3',
        title: '3rd',
        icon: Award,
        orderClass: 'lg:order-3',
        cardClass: 'border-orange-400/30 shadow-[0_18px_50px_-35px_rgba(251,146,60,0.28)] lg:translate-y-8',
        badgeClass: 'bg-orange-400 text-black border-orange-200/70 shadow-[0_10px_30px_-12px_rgba(251,146,60,0.6)]',
        frameClass: 'border-orange-300/40 bg-orange-300/10',
        accentClass: 'from-orange-300/70 via-orange-400/20 to-transparent',
        haloClass: 'bg-orange-300/10',
        pedestalClass: 'from-orange-300/50 via-orange-400/20 to-orange-300/5',
        textClass: 'text-orange-300',
    };
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

// History Modal State
const isHistoryOpen = ref(false);
const selectedUser = ref<LeaderboardUser | null>(null);
const xpHistory = ref<any[]>([]);
const isLoadingHistory = ref(false);

const openHistory = async (user: LeaderboardUser) => {
    selectedUser.value = user;
    isHistoryOpen.value = true;
    isLoadingHistory.value = true;
    xpHistory.value = [];
    
    try {
        const response = await axios.get(`/users/${user.id}/xp-history`);
        xpHistory.value = response.data;
    } catch (error) {
        console.error('Failed to fetch XP history:', error);
    } finally {
        isLoadingHistory.value = false;
    }
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
                    <Trophy class="w-3.5 h-3.5 sm:w-4 h-4 text-primary" />
                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-primary">
                        {{ sectionName ? `${sectionName} Rankings` : 'Global Rankings' }}
                    </span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tighter">
                    {{ sectionName ? 'Section Elite' : 'Elite Assembly' }}
                </h2>
                <p v-if="totalPlayers > 0" class="text-xs sm:text-sm text-muted-foreground font-medium mt-1">
                    Competition is fierce. You are ranked <span class="text-foreground font-bold">#{{ userRank }}</span> out of {{ totalPlayers.toLocaleString() }}.
                </p>
                <p v-else class="text-xs sm:text-sm text-muted-foreground font-medium mt-1">
                    Join a section to see your ranking.
                </p>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Search Bar -->
                <div class="relative group flex-1 sm:flex-none">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <Search class="w-3.5 h-3.5 sm:w-4 h-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                    </div>
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Search users..."
                        class="pl-9 pr-4 py-2 bg-card border border-border/40 rounded-xl text-[10px] sm:text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all w-full sm:w-64 shadow-sm"
                    />
                </div>
                <button 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="text-[10px] sm:text-xs font-bold px-3 sm:px-4 py-2 rounded-xl border border-border/40 bg-card hover:bg-muted transition-colors relative z-10 whitespace-nowrap"
                >
                    All Time
                </button>
            </div>
        </div>

        <!-- Empty State Filter -->
        <div v-if="users.length === 0" class="surface-card p-8 sm:p-12 flex flex-col items-center justify-center text-center animate-fade-in border border-border/50 rounded-2xl">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mb-4 sm:mb-6 bg-muted/30 rounded-full flex items-center justify-center">
                <Trophy class="w-8 h-8 sm:w-10 sm:h-10 text-muted-foreground/50" />
            </div>
            <h3 class="text-xl sm:text-2xl font-black tracking-tighter mb-2">The Arena is Empty</h3>
            <p class="text-xs sm:text-sm text-muted-foreground max-w-xs sm:max-w-sm">No users have claimed their spot on the leaderboard yet. Be the first to earn XP and make your mark.</p>
        </div>

        <template v-else>
            <!-- No Search Results -->
            <div v-if="filteredUsers.length === 0" class="surface-card p-8 sm:p-12 flex flex-col items-center justify-center text-center animate-fade-in border border-border/50 rounded-2xl">
                <div class="w-12 h-12 sm:w-16 sm:h-16 mb-3 sm:mb-4 bg-muted/30 rounded-full flex items-center justify-center">
                    <Search class="w-6 h-6 sm:w-8 sm:h-8 text-muted-foreground/40" />
                </div>
                <h3 class="text-lg sm:text-xl font-bold tracking-tight mb-1">No users found</h3>
                <p class="text-xs sm:text-sm text-muted-foreground">We couldn't find anyone matching "{{ searchQuery }}". Try another name!</p>
                <button @click="searchQuery = ''" class="mt-4 text-[10px] sm:text-xs font-bold text-primary hover:underline transition-all hover:scale-105 active:scale-95">Clear search</button>
            </div>

            <template v-else>
                <!-- Elite Top 3 Podium -->
                <div class="flex flex-col gap-4 lg:grid lg:grid-cols-3 lg:items-end lg:gap-5">
                    <div
                        v-for="(user, idx) in top3"
                        :key="user.id"
                        class="relative surface-card group overflow-hidden border p-4 sm:p-5 lg:p-6 transition-all duration-700 animate-fade-up"
                        :class="[
                            getTopRankMeta(idx).orderClass,
                            getTopRankMeta(idx).cardClass,
                            `stagger-${idx + 1}`
                        ]"
                        @mousemove="handleMouseMove"
                    >
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none">
                            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                                <defs>
                                    <pattern :id="`grid-top-${idx}`" width="20" height="20" patternUnits="userSpaceOnUse">
                                        <path d="M 20 0 L 0 0 0 20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                    </pattern>
                                </defs>
                                <rect width="100%" height="100%" :fill="`url(#grid-top-${idx})`" />
                            </svg>
                        </div>

                        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r opacity-80" :class="getTopRankMeta(idx).accentClass"></div>
                        <div
                            class="absolute bottom-3 left-1/2 h-14 w-[78%] -translate-x-1/2 rounded-full bg-gradient-to-t blur-2xl opacity-60 pointer-events-none"
                            :class="getTopRankMeta(idx).pedestalClass"
                        ></div>
                        <div class="absolute -top-14 left-1/2 h-28 w-28 -translate-x-1/2 rounded-full blur-3xl pointer-events-none transition-opacity duration-500 opacity-70 group-hover:opacity-100" :class="getTopRankMeta(idx).haloClass"></div>

                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="mb-3 flex items-center gap-2 rounded-full border px-3 py-1 text-[9px] font-black uppercase tracking-[0.25em]" :class="getTopRankMeta(idx).badgeClass">
                                <component :is="getTopRankMeta(idx).icon" class="h-3.5 w-3.5" />
                                <span>{{ getTopRankMeta(idx).label }}</span>
                            </div>

                            <div class="mb-4 flex items-center gap-2">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full border text-sm font-black shadow-lg" :class="getTopRankMeta(idx).frameClass">
                                    {{ idx + 1 }}
                                </div>
                                <span class="text-[10px] font-mono font-black uppercase tracking-[0.28em]" :class="getTopRankMeta(idx).textClass">
                                    {{ getTopRankMeta(idx).title }}
                                </span>
                            </div>

                            <div class="relative mb-4 transition-transform duration-500 group-hover:scale-105">
                                <div class="absolute inset-0 rounded-full blur-2xl opacity-40" :class="getTopRankMeta(idx).haloClass"></div>
                                <Link
                                    :href="`/u/${user.id}`"
                                    class="relative block rounded-2xl border-2 p-1.5 overflow-hidden bg-card/70"
                                    :class="[
                                        getTopRankMeta(idx).frameClass,
                                        idx === 0 ? 'h-24 w-24 sm:h-28 sm:w-28' : 'h-20 w-20 sm:h-24 sm:w-24'
                                    ]"
                                >
                                    <div class="flex h-full w-full items-center justify-center overflow-hidden rounded-xl bg-muted/40">
                                        <img v-if="user.avatar" :src="user.avatar" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                        <User v-else class="h-10 w-10 text-muted-foreground/40" />
                                    </div>
                                </Link>

                                <div
                                    v-if="idx === 0"
                                    class="absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full border border-amber-200/80 bg-amber-400 text-black shadow-[0_10px_25px_-10px_rgba(251,191,36,0.8)]"
                                >
                                    <Crown class="h-4 w-4" />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <h3 :class="[idx === 0 ? 'text-xl sm:text-2xl lg:text-3xl' : 'text-lg sm:text-xl lg:text-2xl']" class="font-black tracking-tighter leading-tight max-w-[15rem] truncate">
                                    <Link :href="`/u/${user.id}`" class="hover:text-primary transition-colors">{{ user.name }}</Link>
                                </h3>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-[10px] font-mono font-black uppercase tracking-[0.28em] text-muted-foreground/70">Rank {{ idx + 1 }}</span>
                                    <span v-if="user.isCurrentUser" class="rounded-full bg-primary px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-primary-foreground">You</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-baseline gap-1.5 leading-none">
                                <span :class="[idx === 0 ? 'text-3xl sm:text-4xl lg:text-5xl' : 'text-2xl sm:text-3xl lg:text-4xl']" class="font-mono font-black text-foreground tabular-nums tracking-tighter">
                                    {{ getAnimXP(idx).value.toLocaleString() }}
                                </span>
                                <span class="text-[10px] font-mono font-bold uppercase tracking-[0.24em] text-primary">XP</span>
                            </div>

                            <div class="mt-3 flex items-center gap-2 rounded-full border border-border/40 bg-card/60 px-3 py-1 text-[8px] font-black uppercase tracking-[0.22em] text-muted-foreground">
                                <Terminal class="h-3 w-3 text-primary" />
                                <span>{{ activeSeasonName || 'Season 01' }}</span>
                            </div>

                            <div class="mt-5 w-full space-y-3 rounded-2xl border border-border/30 bg-background/40 p-3 sm:p-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-end justify-between">
                                        <p class="text-[8px] font-mono font-black uppercase tracking-[0.22em] text-muted-foreground">XP Progress</p>
                                        <p class="text-[10px] font-mono font-black text-primary">{{ user.xpProgress }}%</p>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full border border-primary/10 bg-muted/20 p-0.5">
                                        <div class="relative h-full rounded-full bg-primary transition-all duration-1500 ease-out" :style="{ width: `${user.xpProgress}%` }">
                                            <div class="absolute inset-0 bg-white/30 animate-pulse"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-border/20 bg-card/60 px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <Flame class="h-4 w-4 text-primary" />
                                        <span class="text-[8px] font-mono font-black uppercase tracking-[0.22em] text-muted-foreground">Streak</span>
                                    </div>
                                    <span class="text-sm font-mono font-black tabular-nums text-foreground">{{ user.streak }}D</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- High Density List Rankings -->
            <div class="space-y-3 sm:space-y-4">
                <div class="px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between bg-card/30 rounded-2xl border border-border/20 backdrop-blur-sm">
                    <div class="flex items-center gap-2">
                        <Activity class="w-3.5 h-3.5 sm:w-4 h-4 text-primary" />
                        <span class="text-[10px] sm:text-xs font-black uppercase tracking-[0.2em] text-foreground">Operational Rankings</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 px-2.5 py-1 rounded-full bg-primary/5 border border-primary/10">
                            <span class="text-[8px] sm:text-[10px] font-bold text-muted-foreground uppercase tracking-widest">Sort:</span>
                            <span class="text-[8px] sm:text-[10px] font-black text-primary uppercase tracking-widest">Monthly</span>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <div v-for="(user, idx) in displayedUsers" :key="user.id"
                        class="group relative px-3 sm:px-6 py-2.5 sm:py-3 flex items-center justify-between bg-card/40 hover:bg-card/60 border border-border/40 hover:border-primary/30 rounded-2xl transition-all duration-500 cursor-default animate-fade-up overflow-hidden"
                        :class="[`stagger-${idx + 4}`, { 'border-primary/40 bg-primary/[0.02] shadow-[0_0_20px_rgba(var(--primary),0.05)]': user.isCurrentUser }]"
                        @mousemove="handleMouseMove"
                    >
                        <!-- Left Accent Line -->
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 sm:w-1 h-2/3 bg-primary/20 rounded-r-full group-hover:h-full group-hover:bg-primary transition-all duration-500"></div>

                        <!-- Row Bloom Effect -->
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                            style="background: radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.08), transparent 40%)">
                        </div>

                        <div class="flex items-center gap-3 sm:gap-8 relative z-10">
                            <!-- Rank Badge -->
                            <div class="relative flex items-center justify-center w-7 h-7 sm:w-10 sm:h-10 shrink-0">
                                <div class="absolute inset-0 bg-primary/5 group-hover:bg-primary/10 rounded-lg rotate-45 transition-all duration-500 border border-primary/10 group-hover:rotate-90"></div>
                                <span class="relative text-[10px] sm:text-sm font-black text-foreground tabular-nums">#{{ idx + 1 }}</span>
                            </div>

                            <!-- User Info -->
                            <div class="flex items-center gap-2 sm:gap-4">
                                <div class="relative">
                                    <Link :href="`/u/${user.id}`" class="block w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-muted/30 border border-border/20 flex items-center justify-center overflow-hidden shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform">
                                        <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                        <User v-else class="w-4 h-4 sm:w-6 sm:h-6 text-muted-foreground/40" />
                                    </Link>
                                    <div v-if="user.trend === 'up'" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 rounded-full border-2 border-background animate-pulse"></div>
                                </div>
                                
                                <div>
                                    <div class="flex items-center gap-1.5 sm:gap-2">
                                        <h4 class="text-xs sm:text-base font-black tracking-tight text-foreground truncate max-w-[100px] sm:max-w-[200px]">
                                            {{ user.name }}
                                        </h4>
                                        <span v-if="user.isCurrentUser" class="text-[6px] sm:text-[8px] uppercase px-1.5 py-0.5 rounded-full bg-primary text-primary-foreground font-black shadow-lg shadow-primary/20">YOU</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <div class="flex items-center gap-1 px-1 py-0.5 rounded bg-muted/50 border border-border/40">
                                            <Award class="w-2 h-2 sm:w-2.5 sm:h-2.5 text-primary" />
                                            <span class="text-[7px] sm:text-[9px] font-bold text-muted-foreground uppercase tracking-tighter">Silver III</span>
                                        </div>
                                        <span class="text-[8px] text-muted-foreground/60 hidden sm:inline font-mono">ID_{{ user.id.toString().padStart(4, '0') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 sm:gap-10 relative z-10">
                            <!-- Trend Column (Desktop Only) -->
                            <div class="hidden md:flex flex-col items-end min-w-[60px]">
                                <div class="flex items-center gap-1.5">
                                    <component :is="user.trend === 'up' ? TrendingUp : (user.trend === 'down' ? TrendingDown : Minus)" 
                                        class="w-4 h-4" 
                                        :class="user.trend === 'up' ? 'text-emerald-500' : (user.trend === 'down' ? 'text-destructive' : 'text-muted-foreground')"
                                    />
                                    <span class="text-[10px] font-black uppercase tabular-nums" :class="user.trend === 'up' ? 'text-emerald-500' : (user.trend === 'down' ? 'text-destructive' : 'text-muted-foreground')">
                                        {{ user.trend === 'up' ? 'UP' : (user.trend === 'down' ? 'DOWN' : 'STABLE') }}
                                    </span>
                                </div>
                                <span class="text-[8px] font-bold uppercase text-muted-foreground/40 tracking-widest mt-0.5">Network Trend</span>
                            </div>

                            <!-- Data Column -->
                            <div class="text-right">
                                <div class="flex items-baseline justify-end gap-1">
                                    <span class="text-sm sm:text-xl font-black tabular-nums text-foreground tracking-tighter leading-none">{{ user.xp.toLocaleString() }}</span>
                                    <span class="text-[8px] sm:text-[10px] font-bold text-primary uppercase tracking-widest">XP</span>
                                </div>
                                <div class="flex items-center justify-end gap-1 mt-0.5">
                                    <Sparkles class="w-2 h-2 sm:w-2.5 h-2.5 text-primary/60" />
                                    <span class="text-[8px] sm:text-[9px] font-bold text-primary/80 tracking-tight">+{{ user.weeklyXp >= 1000 ? (user.weeklyXp / 1000).toFixed(1) + 'k' : user.weeklyXp }}</span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1 sm:gap-2 opacity-0 group-hover:opacity-100 translate-x-2 sm:translate-x-4 group-hover:translate-x-0 transition-all duration-500">
                                <Link :href="`/u/${user.id}`" 
                                    class="p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl bg-primary/5 text-primary hover:bg-primary hover:text-primary-foreground transition-all border border-primary/10 hover:border-primary shadow-sm hover:shadow-primary/20"
                                    title="View Profile"
                                >
                                     <Eye class="w-3.5 h-3.5 sm:w-4 h-4" />
                                </Link>
                                <button @click="openHistory(user)"
                                    class="p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl bg-primary/5 text-primary hover:bg-primary hover:text-primary-foreground transition-all border border-primary/10 hover:border-primary shadow-sm hover:shadow-primary/20"
                                    title="View History"
                                >
                                     <History class="w-3.5 h-3.5 sm:w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4 flex justify-center" v-if="users.length > 10">
                    <button 
                        @click="showAllRankings = !showAllRankings"
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="group flex items-center gap-3 px-8 py-3 rounded-2xl bg-card border border-border/40 hover:border-primary/40 text-[10px] font-black uppercase tracking-[0.3em] text-muted-foreground hover:text-primary transition-all shadow-sm hover:shadow-primary/10"
                    >
                        <div class="w-1 h-1 rounded-full bg-primary/40 group-hover:scale-[3] group-hover:bg-primary transition-all"></div>
                        {{ showAllRankings ? 'Collapse Data Stream' : 'Load More Rankings' }}
                        <div class="w-1 h-1 rounded-full bg-primary/40 group-hover:scale-[3] group-hover:bg-primary transition-all"></div>
                    </button>
                </div>
            </div>
        </template>
    </template>

    <!-- XP History Modal -->
    <Dialog v-model:open="isHistoryOpen">
        <DialogContent class="sm:max-w-[450px] p-0 overflow-hidden border-primary/20 bg-card/95 backdrop-blur-2xl shadow-2xl shadow-primary/10">
            <!-- Modal Header -->
            <div class="relative p-8 pb-6 border-b border-border/20 overflow-hidden">
                <!-- Background Decoration -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-primary/5 rounded-full blur-2xl -ml-12 -mb-12"></div>
                
                <div class="relative flex items-center gap-5">
                    <div class="relative group">
                        <div class="absolute -inset-1 bg-gradient-to-tr from-primary to-primary/20 rounded-2xl blur opacity-20 group-hover:opacity-40 transition-opacity"></div>
                        <div class="relative w-14 h-14 rounded-2xl bg-card border border-primary/20 flex items-center justify-center shrink-0">
                            <History class="w-7 h-7 text-primary" />
                        </div>
                    </div>
                    <div>
                        <DialogTitle class="text-2xl font-black tracking-tight text-foreground">{{ selectedUser?.name }}</DialogTitle>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="px-2 py-0.5 rounded bg-primary/10 border border-primary/20">
                                <span class="text-[9px] font-black text-primary uppercase tracking-[0.2em]">XP_LOG_STREAMS</span>
                            </div>
                            <span class="text-[10px] font-medium text-muted-foreground">Detailed activity history</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="max-h-[450px] overflow-y-auto custom-scrollbar bg-card/30">
                <div v-if="isLoadingHistory" class="flex flex-col items-center justify-center py-20 gap-4">
                    <div class="relative">
                        <Loader2 class="w-10 h-10 text-primary animate-spin" />
                        <div class="absolute inset-0 w-10 h-10 border-t-2 border-primary rounded-full animate-ping opacity-20"></div>
                    </div>
                    <p class="text-xs font-mono font-black uppercase tracking-[0.3em] text-primary/60 animate-pulse">Syncing Network Data...</p>
                </div>

                <div v-else-if="xpHistory.length === 0" class="flex flex-col items-center justify-center py-20 text-center px-10">
                    <div class="w-16 h-16 rounded-full bg-muted/20 border border-dashed border-muted-foreground/30 flex items-center justify-center mb-4">
                        <Activity class="w-8 h-8 text-muted-foreground/20" />
                    </div>
                    <p class="text-base font-black text-foreground">Zero Activity Detected</p>
                    <p class="text-xs text-muted-foreground mt-2 leading-relaxed">This node has not yet established a data footprint in the current season.</p>
                </div>

                <div v-else class="p-4 space-y-2">
                    <div v-for="(item, index) in xpHistory" :key="item.id" 
                        class="group relative flex items-center justify-between p-4 rounded-2xl bg-card/50 hover:bg-card border border-border/40 hover:border-primary/30 transition-all duration-300 animate-fade-up"
                        :style="{ animationDelay: `${index * 50}ms` }"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-muted/30 border border-border/20 flex items-center justify-center shrink-0 group-hover:bg-primary/10 group-hover:border-primary/20 transition-colors">
                                <component :is="item.reason.includes('Exam') ? Trophy : (item.reason.includes('Enroll') ? Sparkles : Award)" 
                                    class="w-5 h-5 text-muted-foreground group-hover:text-primary transition-colors" 
                                />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-black text-foreground tracking-tight">{{ item.reason }}</span>
                                <span v-if="item.description" class="text-[10px] text-muted-foreground font-medium line-clamp-1 group-hover:text-foreground transition-colors">{{ item.description }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[9px] font-mono text-muted-foreground/40 uppercase tracking-widest">{{ item.created_at }}</span>
                                    <span v-if="item.section_name" class="px-1.5 py-0.5 rounded-full bg-primary/5 text-[8px] font-black text-primary/60 uppercase tracking-tighter">{{ item.section_name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right pl-4">
                            <div class="flex items-baseline justify-end gap-1">
                                <span class="text-lg font-black tabular-nums tracking-tighter" :class="item.amount_xp >= 0 ? 'text-primary' : 'text-destructive'">
                                    {{ item.amount_xp >= 0 ? '+' : '' }}{{ item.amount_xp.toLocaleString() }}
                                </span>
                                <span class="text-[9px] font-bold text-muted-foreground/60 uppercase">XP</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 border-t border-border/20 bg-muted/5 flex justify-center">
                <button @click="isHistoryOpen = false" 
                    class="group relative px-10 py-2.5 rounded-xl bg-card border border-border/40 hover:border-primary/40 transition-all overflow-hidden"
                >
                    <div class="absolute inset-0 bg-primary/5 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                    <span class="relative text-[10px] font-black uppercase tracking-[0.3em] text-muted-foreground group-hover:text-primary transition-colors">Close Log</span>
                </button>
            </div>
        </DialogContent>
    </Dialog>
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

.animate-bounce-slow {
    animation: bounce-slow 3s infinite ease-in-out;
}

.tech-badge {
    clip-path: polygon(0% 0%, 100% 0%, 100% 70%, 70% 100%, 0% 100%);
}

.tech-badge-left {
    clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 30% 100%, 0% 70%);
}

@keyframes scan {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(500%); }
}

@keyframes scan-horizontal {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(1000%); }
}

@keyframes scan-fast {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}

@keyframes spin-very-slow {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes reverse-spin {
    0% { transform: rotate(360deg); }
    100% { transform: rotate(0deg); }
}

.animate-spin-very-slow {
    animation: spin-very-slow 20s linear infinite;
}

.animate-reverse-spin {
    animation: reverse-spin 10s linear infinite;
    transform-origin: center;
}

.animate-scan {
    animation: scan 4s linear infinite;
}

.animate-scan-fast {
    animation: scan-fast 1.5s linear infinite;
}

@keyframes bounce-slow {
    0%, 100% {
        transform: translateY(0) rotate(0);
    }
    50% {
        transform: translateY(-8px) rotate(5deg);
    }
}
</style>
