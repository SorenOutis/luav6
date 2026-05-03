<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { Trophy, Crown, Medal, Sparkles, User, Award, Search, Flame, Terminal, Activity, History, Eye, Loader2, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Dialog, DialogContent, DialogTitle,
} from '@/components/ui/dialog';
import { SpotlightCard } from '@/components/ui/spotlight-card';

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
const STORAGE_KEY = 'leaderboard_active_section_id';

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        const idx = props.sectionLeaderboards.findIndex(s => s.sectionId === parseInt(saved));
        if (idx !== -1) activeTabIndex.value = idx;
    }
});

watch(activeTabIndex, (i) => {
    const s = props.sectionLeaderboards[i];
    if (s) localStorage.setItem(STORAGE_KEY, s.sectionId.toString());
});

const activeLeaderboard = computed(() => props.sectionLeaderboards[activeTabIndex.value] || null);
const users = computed(() => activeLeaderboard.value?.users || []);
const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    const q = searchQuery.value.toLowerCase().trim();
    return users.value.filter(u => u.name.toLowerCase().includes(q));
});
const userRank = computed(() => activeLeaderboard.value?.userRank || 0);
const totalPlayers = computed(() => activeLeaderboard.value?.totalPlayers || 0);
const sectionName = computed(() => activeLeaderboard.value?.sectionName || '');

const top3 = computed(() => filteredUsers.value.slice(0, 3));
const showAllRankings = ref(false);
const restUsers = computed(() => {
    const rest = filteredUsers.value.slice(3);
    return showAllRankings.value ? rest : rest.slice(0, 7);
});

const animXP1 = useNumberAnimation(() => top3.value[0]?.xp || 0);
const animXP2 = useNumberAnimation(() => top3.value[1]?.xp || 0);
const animXP3 = useNumberAnimation(() => top3.value[2]?.xp || 0);
const getAnimXP = (i: number) => {
    if (i === 0) return animXP1;
    if (i === 1) return animXP2;
    if (i === 2) return animXP3;
    return { value: top3.value[i]?.xp || 0 };
};

// Podium ordering: on desktop, show 2nd-1st-3rd
const podiumOrder = computed(() => {
    const t = top3.value;
    if (t.length < 3) return t.map((u, i) => ({ user: u, origIdx: i }));
    return [
        { user: t[1], origIdx: 1 },
        { user: t[0], origIdx: 0 },
        { user: t[2], origIdx: 2 },
    ];
});

const rankMeta = [
    { label: '1st', icon: Crown, ring: 'ring-amber-400/60', glow: 'shadow-amber-400/30', accent: 'text-amber-400', bg: 'from-amber-400/20 via-amber-400/5 to-transparent', badge: 'bg-amber-400 text-black' },
    { label: '2nd', icon: Medal, ring: 'ring-slate-300/50', glow: 'shadow-slate-300/20', accent: 'text-slate-300', bg: 'from-slate-300/15 via-slate-300/5 to-transparent', badge: 'bg-slate-300 text-slate-900' },
    { label: '3rd', icon: Award, ring: 'ring-orange-400/50', glow: 'shadow-orange-400/20', accent: 'text-orange-400', bg: 'from-orange-400/15 via-orange-400/5 to-transparent', badge: 'bg-orange-400 text-black' },
];

const getNameSize = (name: string, isChamp: boolean) => {
    const l = name.length;
    if (isChamp) {
        if (l <= 14) return 'text-lg sm:text-xl';
        if (l <= 22) return 'text-base sm:text-lg';
        if (l <= 30) return 'text-sm sm:text-base';
        return 'text-xs sm:text-sm';
    }
    if (l <= 14) return 'text-base sm:text-lg';
    if (l <= 22) return 'text-sm sm:text-base';
    if (l <= 30) return 'text-xs sm:text-sm';
    return 'text-[11px] sm:text-xs';
};

// History Modal
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
        const r = await axios.get(`/users/${user.id}/xp-history`);
        xpHistory.value = r.data;
    } catch (e) {
        console.error('Failed to fetch XP history:', e);
    } finally {
        isLoadingHistory.value = false;
    }
};
</script>

<template>
    <div class="lb-root space-y-6">
        <!-- Section Tabs -->
        <div v-if="sectionLeaderboards.length > 1" class="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
            <button v-for="(section, idx) in sectionLeaderboards" :key="section.sectionId"
                @click="activeTabIndex = idx"
                :class="['lb-tab', activeTabIndex === idx && 'lb-tab--active']">
                {{ section.sectionName }}
            </button>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Trophy class="w-4 h-4 text-amber-400" />
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-400">
                        {{ sectionName ? `${sectionName} Rankings` : 'Rankings' }}
                    </span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight">Leaderboard</h2>
                <p v-if="totalPlayers > 0" class="text-xs text-muted-foreground mt-1">
                    You are ranked <span class="text-foreground font-bold">#{{ userRank }}</span> of {{ totalPlayers }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative flex-1 sm:flex-none">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                    <input v-model="searchQuery" type="text" placeholder="Search..."
                        class="lb-search pl-9 pr-4 py-2 w-full sm:w-52" />
                </div>
                <div class="lb-season-pill">
                    <Terminal class="w-3 h-3 text-amber-400" />
                    <span>{{ activeSeasonName || 'Season 1' }}</span>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="users.length === 0" class="lb-empty">
            <Trophy class="w-10 h-10 text-muted-foreground/30 mb-4" />
            <h3 class="text-xl font-black mb-1">No Rankings Yet</h3>
            <p class="text-xs text-muted-foreground">Be the first to earn XP!</p>
        </div>

        <template v-else>
            <!-- No search results -->
            <div v-if="filteredUsers.length === 0" class="lb-empty">
                <Search class="w-8 h-8 text-muted-foreground/30 mb-3" />
                <p class="text-sm font-bold">No users found for "{{ searchQuery }}"</p>
                <button @click="searchQuery = ''" class="mt-3 text-xs font-bold text-amber-400 hover:underline">Clear search</button>
            </div>

            <template v-else>
                <!-- ═══════ PODIUM ═══════ -->
                <div class="lb-podium">
                    <SpotlightCard v-for="({ user, origIdx }) in podiumOrder" :key="user.id"
                        customSize
                        :glowColor="origIdx === 0 ? 'orange' : origIdx === 1 ? 'blue' : 'red'"
                        :className="[
                            'lb-podium-card animate-fade-up',
                            origIdx === 0 && 'lb-podium-card--champ'
                        ].filter(Boolean).join(' ')"
                        :style="{ 
                            animationDelay: `${origIdx * 120}ms`,
                            backgroundColor: 'transparent',
                            borderColor: 'transparent'
                        }">

                        <!-- Inner container for decorative background glow -->
                        <div class="absolute inset-0 overflow-hidden rounded-[inherit] pointer-events-none">
                            <!-- Glow bg -->
                            <div class="absolute inset-0 bg-gradient-to-b rounded-2xl opacity-60 pointer-events-none"
                                :class="rankMeta[origIdx].bg"></div>
                        </div>

                        <div class="relative z-10 flex flex-col items-center text-center">
                            <!-- Rank badge -->
                            <div :class="['lb-rank-badge', rankMeta[origIdx].badge]">
                                <component :is="rankMeta[origIdx].icon" class="w-3 h-3" />
                                <span>{{ origIdx + 1 }}</span>
                            </div>

                            <!-- Avatar -->
                            <Link :href="`/u/${user.id}`"
                                :class="['lb-avatar', origIdx === 0 ? 'w-20 h-20 sm:w-24 sm:h-24' : 'w-16 h-16 sm:w-20 sm:h-20', 'ring-2', rankMeta[origIdx].ring]">
                                <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                <User v-else class="w-8 h-8 text-muted-foreground/40" />
                            </Link>

                            <!-- Name -->
                            <Link :href="`/u/${user.id}`"
                                :class="['mt-3 font-black tracking-tight leading-snug text-center break-words max-w-full hover:text-amber-400 transition-colors', getNameSize(user.name, origIdx === 0)]">
                                {{ user.name }}
                            </Link>
                            <span v-if="user.isCurrentUser" class="mt-1 text-[8px] uppercase px-2 py-0.5 rounded-full bg-amber-400 text-black font-black">You</span>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-muted-foreground mt-1" :class="rankMeta[origIdx].accent">
                                {{ rankMeta[origIdx].label }}
                            </span>

                            <!-- XP -->
                            <div class="mt-3 flex items-baseline gap-1">
                                <span :class="[origIdx === 0 ? 'text-3xl sm:text-4xl' : 'text-2xl sm:text-3xl']" class="font-black tabular-nums tracking-tighter">
                                    {{ getAnimXP(origIdx).value.toLocaleString() }}
                                </span>
                                <span class="text-[10px] font-bold uppercase text-amber-400">XP</span>
                            </div>

                            <!-- Stats row -->
                            <div class="mt-3 flex items-center gap-3 text-[9px] text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <Flame class="w-3 h-3 text-orange-400" />
                                    <span class="font-bold">{{ user.streak }}d</span>
                                </div>
                                <div class="w-px h-3 bg-border/60"></div>
                                <div class="flex items-center gap-1">
                                    <Sparkles class="w-3 h-3 text-amber-400" />
                                    <span class="font-bold">{{ user.xpProgress }}%</span>
                                </div>
                            </div>

                            <!-- XP bar -->
                            <div class="mt-2 w-full h-1.5 rounded-full bg-muted/30 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500 transition-all duration-1000" :style="{ width: `${user.xpProgress}%` }"></div>
                            </div>
                        </div>
                    </SpotlightCard>
                </div>

                <!-- ═══════ LIST RANKINGS ═══════ -->
                <div v-if="filteredUsers.length > 3" class="space-y-2">
                    <div class="flex items-center justify-between px-1 mb-3">
                        <div class="flex items-center gap-2">
                            <Activity class="w-3.5 h-3.5 text-amber-400" />
                            <span class="text-[10px] font-black uppercase tracking-[0.2em]">All Rankings</span>
                        </div>
                        <span class="text-[10px] text-muted-foreground font-mono">{{ filteredUsers.length }} players</span>
                    </div>

                    <div v-for="(user, i) in restUsers" :key="user.id"
                        class="lb-row group animate-fade-up"
                        :class="{ 'lb-row--you': user.isCurrentUser }"
                        :style="{ animationDelay: `${(i + 3) * 60}ms` }">
                        <!-- Rank -->
                        <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                            <div class="lb-row-rank shrink-0">
                                <span class="text-xs sm:text-sm font-black tabular-nums">#{{ i + 4 }}</span>
                            </div>

                            <!-- Avatar -->
                            <Link :href="`/u/${user.id}`" class="lb-row-avatar shrink-0">
                                <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                <User v-else class="w-4 h-4 text-muted-foreground/40" />
                            </Link>

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs sm:text-sm font-bold tracking-tight break-words">{{ user.name }}</span>
                                    <span v-if="user.isCurrentUser" class="text-[7px] uppercase px-1.5 py-0.5 rounded-full bg-amber-400 text-black font-black shrink-0">YOU</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <Flame class="w-2.5 h-2.5 text-orange-400/70" />
                                    <span class="text-[9px] text-muted-foreground font-medium">{{ user.streak }}d streak</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right side -->
                        <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                            <!-- XP -->
                            <div class="text-right">
                                <div class="flex items-baseline gap-0.5">
                                    <span class="text-sm sm:text-base font-black tabular-nums tracking-tight">{{ user.xp.toLocaleString() }}</span>
                                    <span class="text-[8px] font-bold text-amber-400 uppercase">XP</span>
                                </div>
                                <div class="flex items-center gap-1 justify-end">
                                    <Sparkles class="w-2 h-2 text-amber-400/60" />
                                    <span class="text-[8px] text-muted-foreground">+{{ user.weeklyXp >= 1000 ? (user.weeklyXp / 1000).toFixed(1) + 'k' : user.weeklyXp }}</span>
                                </div>
                            </div>

                            <!-- Actions (hover) -->
                            <div class="hidden sm:flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <Link :href="`/u/${user.id}`" class="lb-action-btn" title="View Profile">
                                    <Eye class="w-3.5 h-3.5" />
                                </Link>
                                <button @click="openHistory(user)" class="lb-action-btn" title="XP History">
                                    <History class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Show more -->
                    <div v-if="filteredUsers.length > 10" class="flex justify-center pt-3">
                        <button @click="showAllRankings = !showAllRankings" class="lb-show-more">
                            <component :is="showAllRankings ? ChevronUp : ChevronDown" class="w-4 h-4" />
                            <span>{{ showAllRankings ? 'Show Less' : `Show All (${filteredUsers.length - 3})` }}</span>
                        </button>
                    </div>
                </div>
            </template>
        </template>

        <!-- XP History Modal -->
        <Dialog v-model:open="isHistoryOpen">
            <DialogContent class="sm:max-w-[420px] p-0 overflow-hidden border-amber-400/20 bg-card/95 backdrop-blur-2xl">
                <div class="p-6 pb-4 border-b border-border/20">
                    <div class="flex items-center gap-4">
                        <div class="w-11 h-11 rounded-xl bg-amber-400/10 border border-amber-400/20 flex items-center justify-center">
                            <History class="w-5 h-5 text-amber-400" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-black tracking-tight">{{ selectedUser?.name }}</DialogTitle>
                            <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">XP History</span>
                        </div>
                    </div>
                </div>

                <div class="max-h-[400px] overflow-y-auto scrollbar-none">
                    <div v-if="isLoadingHistory" class="flex flex-col items-center py-16 gap-3">
                        <Loader2 class="w-8 h-8 text-amber-400 animate-spin" />
                        <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground animate-pulse">Loading...</p>
                    </div>
                    <div v-else-if="xpHistory.length === 0" class="flex flex-col items-center py-16 text-center px-8">
                        <Activity class="w-8 h-8 text-muted-foreground/20 mb-3" />
                        <p class="text-sm font-bold">No Activity Yet</p>
                        <p class="text-xs text-muted-foreground mt-1">No XP entries recorded.</p>
                    </div>
                    <div v-else class="p-3 space-y-1.5">
                        <div v-for="(item, index) in xpHistory" :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl hover:bg-muted/30 transition-colors animate-fade-up"
                            :style="{ animationDelay: `${index * 40}ms` }">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-lg bg-muted/30 flex items-center justify-center shrink-0">
                                    <component :is="item.reason.includes('Exam') ? Trophy : (item.reason.includes('Enroll') ? Sparkles : Award)" class="w-4 h-4 text-muted-foreground" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold truncate">{{ item.reason }}</p>
                                    <p v-if="item.description" class="text-[10px] text-muted-foreground truncate">{{ item.description }}</p>
                                    <p class="text-[8px] text-muted-foreground/50 font-mono mt-0.5">{{ item.created_at }}</p>
                                </div>
                            </div>
                            <span class="text-sm font-black tabular-nums shrink-0 pl-3" :class="item.amount_xp >= 0 ? 'text-emerald-400' : 'text-red-400'">
                                {{ item.amount_xp >= 0 ? '+' : '' }}{{ item.amount_xp }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-border/20 flex justify-center">
                    <button @click="isHistoryOpen = false" class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors px-6 py-2 rounded-lg hover:bg-muted/30">
                        Close
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
@reference "../../css/app.css";
.lb-tab {
    @apply px-4 py-2 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all duration-300 border border-border/30 bg-card/40 text-muted-foreground shrink-0;
}
.lb-tab--active {
    @apply bg-amber-400 text-black border-amber-400 shadow-[0_4px_20px_-4px_rgba(251,191,36,0.5)];
}
.lb-search {
    @apply bg-card/50 border border-border/30 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400/40 transition-all;
}
.lb-season-pill {
    @apply flex items-center gap-1.5 px-3 py-2 rounded-xl border border-border/30 bg-card/40 text-[10px] font-bold uppercase tracking-widest text-muted-foreground shrink-0;
}
.lb-empty {
    @apply flex flex-col items-center justify-center text-center py-16 px-6 rounded-2xl border border-border/30 bg-card/20;
}

/* ═══ Podium ═══ */
.lb-podium {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .lb-podium {
        grid-template-columns: 1fr 1.15fr 1fr;
        align-items: end;
        gap: 1rem;
    }
}
.lb-podium-card {
    @apply relative rounded-2xl border border-border/30 bg-card/30 backdrop-blur-sm p-5 sm:p-6 transition-all duration-500;
}
.lb-podium-card:hover {
    @apply border-border/60 bg-card/50;
    transform: translateY(-2px);
}
.lb-podium-card--champ {
    @apply border-amber-400/30 bg-card/40;
}
@media (min-width: 640px) {
    .lb-podium-card--champ {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
}
.lb-rank-badge {
    @apply flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black mb-3;
}
.lb-avatar {
    @apply block rounded-xl overflow-hidden bg-muted/30 flex items-center justify-center transition-transform duration-500;
}
.lb-avatar:hover {
    transform: scale(1.05);
}

/* ═══ List rows ═══ */
.lb-row {
    @apply flex items-center justify-between px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl border border-border/20 bg-card/20 hover:bg-card/40 hover:border-border/40 transition-all duration-300;
}
.lb-row--you {
    @apply border-amber-400/30 bg-amber-400/[0.03];
}
.lb-row-rank {
    @apply w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-muted/20 border border-border/20 flex items-center justify-center;
}
.lb-row-avatar {
    @apply w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-muted/20 border border-border/20 flex items-center justify-center overflow-hidden;
}
.lb-action-btn {
    @apply p-2 rounded-lg text-muted-foreground hover:text-amber-400 hover:bg-amber-400/10 transition-all;
}
.lb-show-more {
    @apply flex items-center gap-2 px-6 py-2.5 rounded-xl border border-border/30 bg-card/30 text-[10px] font-bold uppercase tracking-widest text-muted-foreground hover:text-amber-400 hover:border-amber-400/30 transition-all;
}
</style>
