<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import gsap from 'gsap';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Trophy, Crown, TrendingUp, TrendingDown, Minus, Medal, Sparkles, User, Award, Search, Flame, Cpu, Terminal, Activity } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

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
            <div class="flex items-center gap-3">
                <!-- Search Bar -->
                <div class="relative group">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <Search class="w-4 h-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                    </div>
                    <input 
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Search users..."
                        class="pl-10 pr-4 py-2 bg-card border border-border/40 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all w-full sm:w-64 shadow-sm"
                    />
                </div>
                <button 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="text-xs font-bold px-4 py-2 rounded-xl border border-border/40 bg-card hover:bg-muted transition-colors relative z-10 whitespace-nowrap"
                >
                    View All Time
                </button>
            </div>
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
            <!-- No Search Results -->
            <div v-if="filteredUsers.length === 0" class="surface-card p-12 flex flex-col items-center justify-center text-center animate-fade-in border border-border/50 rounded-2xl">
                <div class="w-16 h-16 mb-4 bg-muted/30 rounded-full flex items-center justify-center">
                    <Search class="w-8 h-8 text-muted-foreground/40" />
                </div>
                <h3 class="text-xl font-bold tracking-tight mb-1">No warriors found</h3>
                <p class="text-sm text-muted-foreground">We couldn't find anyone matching "{{ searchQuery }}". Try another name!</p>
                <button @click="searchQuery = ''" class="mt-4 text-xs font-bold text-primary hover:underline transition-all hover:scale-105 active:scale-95">Clear search</button>
            </div>

            <template v-else>
                <!-- Elite Top 3 Cards (Landscape Tech Mode) -->
                <div class="flex flex-col gap-4">
                    <div v-for="(user, idx) in top3" :key="user.id"
                    class="relative surface-card p-4 sm:p-6 flex flex-col sm:flex-row items-center gap-6 group transition-all duration-700 hover:translate-x-2 animate-fade-up overflow-hidden"
                    :class="[
                        idx === 0 ? 'border-primary/40 shadow-2xl shadow-primary/10 z-20 scale-[1.02]' : 'border-border/40',
                        `stagger-${idx + 1}`
                    ]"
                    @mousemove="handleMouseMove"
                >
                    <!-- Tech Grid Background -->
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none">
                        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                            <defs>
                                <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#grid)" />
                        </svg>
                    </div>

                    <!-- Tech Scanning Line -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary/5 to-transparent w-40 h-full -translate-x-full group-hover:animate-scan-horizontal pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>

                    <!-- Card Shine/Bloom Effect -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        style="background: radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(var(--primary), 0.08), transparent 40%)">
                    </div>

                    <!-- Background Decorative Element for Top 1 -->
                    <div v-if="idx === 0" class="absolute -top-20 -right-20 w-60 h-60 bg-primary/[0.05] rounded-full blur-3xl pointer-events-none transition-colors group-hover:bg-primary/[0.08]"></div>

                    <!-- Tech Rank Badge (Clipped Corner) -->
                    <div class="absolute top-0 left-0 z-30">
                        <div class="flex items-center justify-center w-10 h-10 sm:w-14 sm:h-14 font-mono font-black text-xs sm:text-xl shadow-lg border backdrop-blur-md transition-all group-hover:scale-110 tech-badge-left"
                            :class="[
                                idx === 0 ? 'bg-primary text-primary-foreground border-primary/50 shadow-primary/20' : 
                                'bg-card/80 text-foreground border-border/50 shadow-black/10'
                            ]"
                        >
                            {{ idx + 1 }}
                        </div>
                    </div>

                    <!-- Left Side: Avatar with Tech Frame -->
                    <div class="relative group-hover:scale-105 transition-transform duration-500 shrink-0 ml-4 sm:ml-8">
                        <!-- Frame Accents -->
                        <div class="absolute -inset-2 border-t-2 border-l-2 border-primary/20 rounded-tl-xl transition-all group-hover:-inset-1 group-hover:border-primary"></div>
                        <div class="absolute -inset-2 border-b-2 border-r-2 border-primary/20 rounded-br-xl transition-all group-hover:-inset-1 group-hover:border-primary"></div>
                        
                        <div class="absolute inset-0 rounded-full blur-2xl opacity-20 transition-opacity group-hover:opacity-50"
                            :class="idx === 0 ? 'bg-primary' : 'bg-muted-foreground'"
                        ></div>
                        
                        <Link :href="`/u/${user.id}`" class="block relative w-16 h-16 sm:w-24 sm:h-24 rounded-xl border-2 p-1 transition-all duration-700 group-hover:rounded-lg overflow-hidden"
                            :class="idx === 0 ? 'border-primary shadow-xl shadow-primary/20 bg-primary/5' : 'border-border/50 bg-muted/20'"
                        >
                            <div class="w-full h-full rounded-lg bg-muted/40 flex items-center justify-center overflow-hidden transition-all duration-700">
                                <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                <User v-else class="w-8 h-8 sm:w-12 sm:h-12 text-muted-foreground/40" />
                            </div>
                        </Link>

                        <!-- Icon Overlay (Tech Style) -->
                        <div v-if="idx === 0" class="absolute -bottom-2 -right-2 p-1.5 sm:p-2 bg-primary rounded-lg shadow-xl shadow-primary/40 animate-bounce-slow z-30 border border-white/20">
                            <Cpu class="w-3 h-3 sm:w-5 sm:h-5 text-primary-foreground" />
                        </div>
                    </div>

                    <!-- Middle: Identity & XP -->
                    <div class="flex-1 text-center sm:text-left space-y-2 z-10 relative group/middle">
                        <!-- Tech Animation Centerpiece (New) -->
                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] h-[300px] opacity-[0.03] group-hover/middle:opacity-[0.08] transition-opacity duration-1000 pointer-events-none hidden sm:block overflow-hidden">
                            <!-- Circuit Node Animation -->
                            <svg viewBox="0 0 100 100" class="w-full h-full animate-spin-very-slow">
                                <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="0.2" stroke-dasharray="1 3" />
                                <circle cx="50" cy="50" r="35" fill="none" stroke="currentColor" stroke-width="0.5" stroke-dasharray="10 5" class="animate-pulse" />
                                <g class="text-primary">
                                    <circle cx="50" cy="15" r="1.5" fill="currentColor" />
                                    <circle cx="50" cy="85" r="1.5" fill="currentColor" />
                                    <circle cx="15" cy="50" r="1.5" fill="currentColor" />
                                    <circle cx="85" cy="50" r="1.5" fill="currentColor" />
                                    <path d="M 50 15 L 50 35 M 50 65 L 50 85 M 15 50 L 35 50 M 65 50 L 85 50" stroke="currentColor" stroke-width="0.5" />
                                </g>
                                <!-- Rotating data rings -->
                                <circle cx="50" cy="50" r="25" fill="none" stroke="currentColor" stroke-width="0.2" stroke-dasharray="2 10" class="animate-reverse-spin" />
                            </svg>
                            <!-- Pulsing data core -->
                             <div class="absolute inset-0 flex items-center justify-center">
                                 <div class="w-2 h-2 bg-primary rounded-full blur-[2px] animate-ping"></div>
                                 <div class="absolute w-4 h-4 bg-primary/20 rounded-full blur-sm animate-pulse"></div>
                             </div>
                         </div>

                        <!-- Animated Data Stream (New) -->
                        <div class="absolute inset-0 pointer-events-none hidden sm:block">
                            <div v-for="n in 3" :key="n" 
                                class="absolute h-[1px] bg-gradient-to-r from-transparent via-primary/20 to-transparent w-full opacity-0 group-hover:opacity-100 transition-opacity duration-1000"
                                :style="{ 
                                    top: `${20 + n * 25}%`, 
                                    animation: `scan-horizontal ${2 + n}s linear infinite`,
                                    animationDelay: `${n * 0.5}s`
                                }"
                            ></div>
                        </div>

                        <div class="flex items-center justify-center sm:justify-start gap-2 opacity-40 group-hover:opacity-100 transition-opacity">
                            <Terminal class="w-3 h-3 text-primary" />
                            <span class="text-[8px] sm:text-[10px] font-mono font-black uppercase tracking-widest flex items-center gap-2">
                                RANK_{{ idx + 1 }}
                                <span v-if="idx === 0" class="text-primary/50 animate-pulse hidden lg:inline-block">// UPDATING_CORE_NODE...</span>
                            </span>
                        </div>
                        
                        <h3 class="font-black text-lg sm:text-3xl truncate leading-tight tracking-tighter">
                            <Link :href="`/u/${user.id}`" class="hover:text-primary transition-colors">{{ user.name }}</Link>
                        </h3>
                        
                        <div class="flex items-center justify-center sm:justify-start gap-3">
                            <div class="flex items-baseline gap-2 leading-none">
                                <span class="text-xl sm:text-4xl font-mono font-black text-foreground tabular-nums tracking-tighter">{{ getAnimXP(idx).value.toLocaleString() }}</span>
                                <span class="text-[10px] sm:text-sm font-mono font-bold text-primary uppercase">XP</span>
                            </div>
                            <div class="px-2 py-0.5 rounded bg-primary/10 border border-primary/20 text-[8px] sm:text-xs font-mono font-black uppercase tracking-[0.2em] text-primary/70">
                                {{ activeSeasonName || 'SEASON_01' }}
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Stats Panel -->
                    <div class="w-full sm:w-64 space-y-4 sm:border-l sm:border-primary/10 sm:pl-8 z-10">
                        <!-- XP Progress -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-end">
                                <p class="text-[8px] sm:text-[10px] font-mono font-black uppercase text-muted-foreground tracking-tighter">XP_PROGRESS</p>
                                <p class="text-[10px] sm:text-sm font-mono font-black text-primary">{{ user.xpProgress }}%</p>
                            </div>
                            <div class="h-2 sm:h-3 w-full bg-muted/20 rounded-sm overflow-hidden p-0.5 border border-primary/10 relative">
                                <div class="h-full bg-primary rounded-sm transition-all duration-1500 ease-out relative"
                                    :style="{ width: `${user.xpProgress}%` }"
                                >
                                    <div class="absolute inset-0 bg-white/30 animate-pulse"></div>
                                    <div class="absolute right-0 top-0 h-full w-1 bg-white shadow-[0_0_8px_rgba(255,255,255,0.8)]"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Streak Indicator -->
                        <div class="flex items-center justify-between">
                            <p class="text-[8px] sm:text-[10px] font-mono font-black uppercase text-muted-foreground tracking-tighter">STREAK</p>
                            <div class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 rounded-xl bg-primary/[0.03] border border-primary/10 text-foreground hover:bg-primary/[0.05] hover:border-primary/20 transition-all group/streak relative overflow-hidden shadow-sm">
                                <Flame class="w-3 h-3 sm:w-5 h-5 fill-primary/10 text-primary group-hover/streak:scale-110 transition-transform" />
                                <span class="text-xs sm:text-lg font-mono font-black tabular-nums">{{ user.streak }}D</span>
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
                                    :class="user.trend === 'up' ? 'text-primary' : (user.trend === 'down' ? 'text-destructive' : 'text-muted-foreground')"
                                />
                                <span class="text-[8px] font-bold uppercase mt-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">Trend</span>
                            </div>

                            <!-- Data Column -->
                            <div class="text-right min-w-[100px]">
                                <p class="text-sm font-black tabular-nums">{{ user.xp.toLocaleString() }} <span class="text-[10px] font-bold text-muted-foreground">XP</span></p>
                                <p class="text-[9px] font-bold text-primary opacity-80">+{{ user.weeklyXp >= 1000 ? (user.weeklyXp / 1000).toFixed(1) + 'k' : user.weeklyXp }} weekly</p>
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
