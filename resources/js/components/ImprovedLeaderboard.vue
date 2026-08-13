<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Trophy,
    Crown,
    Medal,
    Sparkles,
    User,
    Award,
    Search,
    Flame,
    Terminal,
    Activity,
    History,
    Eye,
    EyeOff,
    Loader2,
    ChevronDown,
    ChevronUp,
    TrendingUp,
    TrendingDown,
    Minus,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import type { Component } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useNumberAnimation } from '@/composables/useNumberAnimation';

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
    blurred?: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface Season {
    id: number;
    name: string;
}

interface Props {
    sectionLeaderboards: LeaderboardData[];
    activeSeasonName?: string;
    availableSeasons?: Season[];
    showViewButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    availableSeasons: () => [],
    showViewButton: false,
});

const emit = defineEmits<{
    'update:activeSeasonName': [name: string];
}>();

/** Fixed-length obscured string for blurred users — prevents
 *  guessing the name by length, copying, or inspecting the DOM. */
const BLURRED_NAME = '████████████████████';

const activeTabIndex = ref(0);
const searchQuery = ref('');
const STORAGE_KEY = 'leaderboard_active_section_id';
const BLUR_STORAGE_KEY = 'leaderboard_blurred';

// Local state for season-switching via API
const localLeaderboards = ref<LeaderboardData[]>(props.sectionLeaderboards);

// Find the best season match: first try the globally active season,
// then fall back to the first season the user is actually enrolled in.
// This prevents showing "2026-2027" for users who only have sections in 2025-2026.
const initialSeason =
    props.availableSeasons.find(
        (s) => s.name === (props.activeSeasonName || ''),
    ) || props.availableSeasons[0];

const selectedSeasonId = ref<number | null>(initialSeason?.id ?? null);
const selectedSeasonName = ref(initialSeason?.name || '');

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        const idx = props.sectionLeaderboards.findIndex(
            (s) => s.sectionId === parseInt(saved),
        );
        if (idx !== -1) activeTabIndex.value = idx;
    }

    // Sync blur state from localStorage (optimistic UI persistence)
    const savedBlurred = localStorage.getItem(BLUR_STORAGE_KEY);
    if (savedBlurred !== null) {
        const blurred = savedBlurred === 'true';
        localLeaderboards.value = localLeaderboards.value.map((lb) => ({
            ...lb,
            users: lb.users.map((u) =>
                u.isCurrentUser ? { ...u, blurred } : u,
            ),
        }));
    }
});

watch(activeTabIndex, (i) => {
    const s = localLeaderboards.value[i];
    if (s) localStorage.setItem(STORAGE_KEY, s.sectionId.toString());
});

const activeLeaderboard = computed(
    () => localLeaderboards.value[activeTabIndex.value] || null,
);
const users = computed(() => activeLeaderboard.value?.users || []);
const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    const q = searchQuery.value.toLowerCase().trim();
    // Hidden users remain in the normal ranking list, but must not be
    // discoverable through search.
    return users.value.filter(
        (u) => !u.blurred && u.name.toLowerCase().includes(q),
    );
});
const userRank = computed(() => activeLeaderboard.value?.userRank || 0);
const totalPlayers = computed(() => activeLeaderboard.value?.totalPlayers || 0);
const sectionName = computed(() => activeLeaderboard.value?.sectionName || '');
const currentUser = computed(
    () => users.value.find((u) => u.isCurrentUser) || null,
);

// Weekly trend indicators (computed server-side from real XP history)
const trendMeta: Record<
    'up' | 'down' | 'stable',
    { icon: Component; label: string; chip: string; iconColor: string }
> = {
    up: {
        icon: TrendingUp,
        label: 'Up',
        chip: 'bg-[#34C759]/10 text-[#34C759]',
        iconColor: 'text-[#34C759]',
    },
    down: {
        icon: TrendingDown,
        label: 'Down',
        chip: 'bg-[#FF3B30]/10 text-[#FF3B30]',
        iconColor: 'text-[#FF3B30]',
    },
    stable: {
        icon: Minus,
        label: 'Steady',
        chip: 'border-border/30 bg-muted/30 text-muted-foreground',
        iconColor: 'text-muted-foreground/40',
    },
};
const trendOf = (u: LeaderboardUser) => trendMeta[u.trend] ?? trendMeta.stable;

const currentSeasonName = computed(() => {
    if (selectedSeasonName.value) return selectedSeasonName.value;
    return props.activeSeasonName || 'Season 1';
});

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
    {
        label: '1st',
        icon: Crown,
        ring: 'ring-[#FFD60A]/50',
        glow: 'shadow-none',
        accent: 'text-[#C7A000]',
        bg: 'from-[#FFD60A]/15 via-transparent to-transparent',
        badge: 'bg-[#FFD60A] text-black',
    },
    {
        label: '2nd',
        icon: Medal,
        ring: 'ring-slate-300/50',
        glow: 'shadow-slate-300/20',
        accent: 'text-slate-300',
        bg: 'from-slate-300/15 via-slate-300/5 to-transparent',
        badge: 'bg-slate-300 text-slate-900',
    },
    {
        label: '3rd',
        icon: Award,
        ring: 'ring-orange-400/50',
        glow: 'shadow-orange-400/20',
        accent: 'text-orange-400',
        bg: 'from-orange-400/15 via-orange-400/5 to-transparent',
        badge: 'bg-orange-400 text-black',
    },
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

// Blur toggle
const isTogglingBlur = ref(false);

const currentUserBlurred = computed(() => {
    const current = users.value.find((u) => u.isCurrentUser);
    return current?.blurred ?? false;
});

const toggleBlur = async () => {
    if (isTogglingBlur.value) return;

    // Compute the new optimisitic state
    const newBlurred = !currentUserBlurred.value;

    // Optimistically update the UI immediately
    isTogglingBlur.value = true;
    localLeaderboards.value = localLeaderboards.value.map((lb) => ({
        ...lb,
        users: lb.users.map((u) =>
            u.isCurrentUser ? { ...u, blurred: newBlurred } : u,
        ),
    }));
    localStorage.setItem(BLUR_STORAGE_KEY, String(newBlurred));

    try {
        const r = await axios.post('/api/leaderboard/toggle-blur');
        const serverBlurred = r.data.blur_leaderboard;

        // Sync with server truth (should match, but double-check)
        if (serverBlurred !== newBlurred) {
            localLeaderboards.value = localLeaderboards.value.map((lb) => ({
                ...lb,
                users: lb.users.map((u) =>
                    u.isCurrentUser ? { ...u, blurred: serverBlurred } : u,
                ),
            }));
            localStorage.setItem(BLUR_STORAGE_KEY, String(serverBlurred));
        }
    } catch (e) {
        console.error('Failed to toggle blur:', e);
        // Revert on failure
        localLeaderboards.value = localLeaderboards.value.map((lb) => ({
            ...lb,
            users: lb.users.map((u) =>
                u.isCurrentUser ? { ...u, blurred: !newBlurred } : u,
            ),
        }));
        localStorage.setItem(BLUR_STORAGE_KEY, String(!newBlurred));
    } finally {
        isTogglingBlur.value = false;
    }
};

// Season switching
const isSwitchingSeason = ref(false);

const changeSeason = async (seasonId: number) => {
    if (isSwitchingSeason.value) return;
    isSwitchingSeason.value = true;

    try {
        const r = await axios.get(`/api/leaderboard`, {
            params: { season_id: seasonId },
        });

        if (r.data.leaderboards) {
            localLeaderboards.value = r.data.leaderboards;
            activeTabIndex.value = 0;
        }

        if (r.data.selectedSeason) {
            selectedSeasonName.value = r.data.selectedSeason.name;
            selectedSeasonId.value = r.data.selectedSeason.id;
            emit('update:activeSeasonName', r.data.selectedSeason.name);
        }
    } catch (e) {
        console.error('Failed to fetch leaderboard:', e);
    } finally {
        isSwitchingSeason.value = false;
    }
};
</script>

<template>
    <div class="lb-root space-y-6">
        <!-- Section Tabs -->
        <div
            v-if="localLeaderboards.length > 1"
            class="flex scrollbar-none gap-2 overflow-x-auto pb-2"
        >
            <button
                v-for="(section, idx) in localLeaderboards"
                :key="section.sectionId"
                @click="activeTabIndex = idx"
                :class="['lb-tab', activeTabIndex === idx && 'lb-tab--active']"
            >
                {{ section.sectionName }}
            </button>
        </div>

        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <Trophy class="h-4 w-4 text-[#007AFF]" />
                    <span class="text-[13px] font-medium text-muted-foreground">
                        {{
                            sectionName ? `${sectionName} Rankings` : 'Rankings'
                        }}
                    </span>
                </div>
                <h2
                    class="text-[28px] font-semibold tracking-tight sm:text-[34px]"
                >
                    Leaderboard
                </h2>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Link
                    v-if="showViewButton"
                    href="/leaderboard"
                    class="dash-btn inline-flex shrink-0 items-center gap-2 bg-[#007AFF]/10 px-4 text-[14px] text-[#007AFF] transition hover:bg-[#007AFF]/15"
                >
                    <Trophy class="h-3.5 w-3.5" />
                    View Leaderboard
                </Link>
                <div class="relative flex-1 sm:flex-none">
                    <Search
                        class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search..."
                        class="lb-search w-full py-2 pr-4 pl-9 sm:w-52"
                    />
                </div>
                <!-- Season Dropdown -->
                <div v-if="availableSeasons.length > 1" class="relative">
                    <select
                        :value="selectedSeasonId || availableSeasons[0]?.id"
                        @change="
                            (e) =>
                                changeSeason(
                                    Number(
                                        (e.target as HTMLSelectElement).value,
                                    ),
                                )
                        "
                        :disabled="isSwitchingSeason"
                        class="lb-season-select cursor-pointer appearance-none pr-8"
                    >
                        <option
                            v-for="s in availableSeasons"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.name }}
                        </option>
                    </select>
                    <ChevronDown
                        class="pointer-events-none absolute top-1/2 right-3 h-3 w-3 -translate-y-1/2 text-muted-foreground"
                    />
                </div>
                <div v-else class="lb-season-pill">
                    <Terminal class="h-3 w-3 text-[#007AFF]" />
                    <span>{{ currentSeasonName }}</span>
                </div>

                <!-- Blur toggle -->
                <button
                    @click="toggleBlur"
                    :disabled="isTogglingBlur"
                    class="lb-blur-toggle shrink-0"
                    :title="
                        currentUserBlurred
                            ? 'You are hidden — click to appear'
                            : 'You are visible — click to hide'
                    "
                >
                    <EyeOff v-if="currentUserBlurred" class="h-3.5 w-3.5" />
                    <Eye v-else class="h-3.5 w-3.5" />
                    <span>{{ currentUserBlurred ? 'Hidden' : 'Visible' }}</span>
                </button>
            </div>
        </div>

        <!-- Your Rank Row -->
        <div v-if="currentUser && totalPlayers > 0" class="lb-rank-row">
            <div class="hidden" aria-hidden="true"></div>
            <div
                class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#007AFF]/10 text-[#007AFF]"
                    >
                        <Trophy class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[13px] font-medium text-muted-foreground"
                        >
                            Your rank
                        </p>
                        <div class="flex items-baseline gap-2">
                            <span
                                class="text-[28px] leading-none font-semibold tracking-tight tabular-nums sm:text-[32px]"
                                >#{{ userRank }}</span
                            >
                            <span
                                class="text-[13px] font-medium text-muted-foreground"
                                >of {{ totalPlayers }} players</span
                            >
                        </div>
                        <p class="mt-1 truncate text-xs text-muted-foreground">
                            {{
                                currentUser.blurred
                                    ? BLURRED_NAME
                                    : currentUser.name
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:flex-col sm:items-end">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[13px] font-medium"
                        :class="trendOf(currentUser).chip"
                    >
                        <component
                            :is="trendOf(currentUser).icon"
                            class="h-3 w-3"
                        />
                        {{ trendOf(currentUser).label }}
                    </span>
                    <span
                        class="text-[13px] font-medium text-muted-foreground tabular-nums"
                    >
                        +{{ currentUser.weeklyXp.toLocaleString() }} XP this
                        week
                    </span>
                </div>
            </div>
        </div>

        <!-- Loading indicator -->
        <div
            v-if="isSwitchingSeason"
            class="flex items-center justify-center py-12"
        >
            <Loader2 class="h-8 w-8 animate-spin text-[#007AFF]" />
        </div>

        <!-- Empty State -->
        <div v-else-if="users.length === 0" class="lb-empty">
            <Trophy class="mb-4 h-10 w-10 text-muted-foreground/30" />
            <h3 class="mb-1 text-[20px] font-semibold tracking-tight">
                No rankings yet
            </h3>
            <p class="text-xs text-muted-foreground">
                Be the first to earn XP!
            </p>
        </div>

        <template v-else>
            <!-- No search results -->
            <div v-if="filteredUsers.length === 0" class="lb-empty">
                <Search class="mb-3 h-8 w-8 text-muted-foreground/30" />
                <p class="text-sm font-bold">
                    No users found for "{{ searchQuery }}"
                </p>
                <button
                    @click="searchQuery = ''"
                    class="mt-3 text-[13px] font-medium text-[#007AFF] hover:underline"
                >
                    Clear search
                </button>
            </div>

            <template v-else>
                <!-- ═══════ PODIUM ═══════ -->
                <div class="lb-podium">
                    <SpotlightCard
                        v-for="{ user, origIdx } in podiumOrder"
                        :key="user.id"
                        customSize
                        :glowColor="
                            origIdx === 0
                                ? 'orange'
                                : origIdx === 1
                                  ? 'blue'
                                  : 'red'
                        "
                        :className="
                            [
                                'lb-podium-card animate-fade-up',
                                origIdx === 0 && 'lb-podium-card--champ',
                            ]
                                .filter(Boolean)
                                .join(' ')
                        "
                        :style="{
                            animationDelay: `${origIdx * 120}ms`,
                            backgroundColor: 'transparent',
                            borderColor: 'transparent',
                        }"
                    >
                        <!-- Inner container for decorative background glow -->
                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
                        >
                            <!-- Glow bg -->
                            <div
                                class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-b opacity-60"
                                :class="rankMeta[origIdx].bg"
                            ></div>
                        </div>

                        <div
                            class="relative z-10 flex flex-col items-center text-center"
                        >
                            <!-- Rank badge -->
                            <div
                                :class="[
                                    'lb-rank-badge',
                                    rankMeta[origIdx].badge,
                                ]"
                            >
                                <component
                                    :is="rankMeta[origIdx].icon"
                                    class="h-3 w-3"
                                />
                                <span>{{ origIdx + 1 }}</span>
                            </div>

                            <!-- Avatar -->
                            <div class="relative">
                                <Link
                                    v-if="!user.blurred"
                                    :href="`/u/${user.id}`"
                                    :class="[
                                        'lb-avatar',
                                        origIdx === 0
                                            ? 'h-20 w-20 sm:h-24 sm:w-24'
                                            : 'h-16 w-16 sm:h-20 sm:w-20',
                                        'ring-2',
                                        rankMeta[origIdx].ring,
                                    ]"
                                >
                                    <img
                                        v-if="user.avatar && !user.blurred"
                                        :src="user.avatar"
                                        :alt="`${user.name} avatar`"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                    />
                                    <User
                                        v-else
                                        class="h-8 w-8 text-muted-foreground/40"
                                    />
                                </Link>
                                <div
                                    v-else
                                    :class="[
                                        'lb-avatar',
                                        'lb-blurred',
                                        origIdx === 0
                                            ? 'h-20 w-20 sm:h-24 sm:w-24'
                                            : 'h-16 w-16 sm:h-20 sm:w-20',
                                        'ring-2',
                                        rankMeta[origIdx].ring,
                                    ]"
                                >
                                    <img
                                        v-if="user.avatar && !user.blurred"
                                        :src="user.avatar"
                                        :alt="`${user.name} avatar`"
                                        class="h-full w-full object-cover blur-sm"
                                    />
                                    <User
                                        v-else
                                        class="h-8 w-8 text-muted-foreground/40"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl bg-primary/[0.03] backdrop-blur-[2px]"
                                    >
                                        <EyeOff
                                            class="h-5 w-5 text-muted-foreground/40"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Name -->
                            <Link
                                v-if="!user.blurred"
                                :href="`/u/${user.id}`"
                                :class="[
                                    'mt-3 max-w-full text-center leading-snug font-semibold tracking-tight break-words transition-colors hover:text-[#007AFF]',
                                    getNameSize(user.name, origIdx === 0),
                                ]"
                            >
                                {{ user.name }}
                            </Link>
                            <span
                                v-else
                                :class="[
                                    'lb-blurred lb-blurred-text mt-3 max-w-full text-center leading-snug font-semibold tracking-tight break-words',
                                    getNameSize(user.name, origIdx === 0),
                                ]"
                                @contextmenu.prevent
                            >
                                <span>{{ BLURRED_NAME }}</span>
                            </span>
                            <span
                                v-if="user.isCurrentUser"
                                class="mt-1 rounded-full bg-[#007AFF] px-2 py-0.5 text-[11px] font-semibold text-white"
                                >You</span
                            >
                            <span
                                class="mt-1 text-[13px] font-medium text-muted-foreground"
                                :class="rankMeta[origIdx].accent"
                            >
                                {{ rankMeta[origIdx].label }}
                            </span>

                            <!-- XP -->
                            <div class="mt-3 flex items-baseline gap-1">
                                <span
                                    :class="[
                                        origIdx === 0
                                            ? 'text-3xl sm:text-4xl'
                                            : 'text-2xl sm:text-3xl',
                                    ]"
                                    class="font-semibold tracking-tight tabular-nums"
                                >
                                    {{
                                        getAnimXP(
                                            origIdx,
                                        ).value.toLocaleString()
                                    }}
                                </span>
                                <span
                                    class="text-[13px] font-medium text-muted-foreground"
                                    >XP</span
                                >
                            </div>

                            <!-- Stats row -->
                            <div
                                class="mt-3 flex items-center gap-3 text-[13px] text-muted-foreground"
                            >
                                <div class="flex items-center gap-1">
                                    <Flame class="h-3 w-3 text-orange-400" />
                                    <span class="font-bold"
                                        >{{ user.streak }}d</span
                                    >
                                </div>
                                <div class="h-3 w-px bg-border/60"></div>
                                <div class="flex items-center gap-1">
                                    <Sparkles class="h-3 w-3 text-[#007AFF]" />
                                    <span class="font-bold"
                                        >{{ user.xpProgress }}%</span
                                    >
                                </div>
                            </div>

                            <!-- XP bar -->
                            <div
                                class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted/30"
                            >
                                <div
                                    class="h-full rounded-full bg-[#007AFF] transition-all duration-700"
                                    :style="{ width: `${user.xpProgress}%` }"
                                ></div>
                            </div>
                        </div>
                    </SpotlightCard>
                </div>

                <!-- ═══════ LIST RANKINGS ═══════ -->
                <div v-if="filteredUsers.length > 3" class="space-y-2">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <Activity class="h-3.5 w-3.5 text-[#007AFF]" />
                            <span
                                class="text-[13px] font-medium text-muted-foreground"
                                >Rankings</span
                            >
                        </div>
                        <span
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ filteredUsers.length }} players</span
                        >
                    </div>

                    <div
                        v-for="(user, i) in restUsers"
                        :key="user.id"
                        class="lb-row group animate-fade-up"
                        :class="{
                            'lb-row--you': user.isCurrentUser,
                            'lb-row--blurred': user.blurred,
                        }"
                        :style="{ animationDelay: `${(i + 3) * 60}ms` }"
                    >
                        <!-- Rank -->
                        <div
                            class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4"
                        >
                            <div class="lb-row-rank shrink-0">
                                <span
                                    class="text-[13px] font-semibold tabular-nums sm:text-sm"
                                    >#{{ i + 4 }}</span
                                >
                            </div>

                            <!-- Avatar -->
                            <div class="relative shrink-0">
                                <Link
                                    v-if="!user.blurred"
                                    :href="`/u/${user.id}`"
                                    class="lb-row-avatar"
                                >
                                    <img
                                        v-if="user.avatar && !user.blurred"
                                        :src="user.avatar"
                                        :alt="`${user.name} avatar`"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                    />
                                    <User
                                        v-else
                                        class="h-4 w-4 text-muted-foreground/40"
                                    />
                                </Link>
                                <div v-else class="lb-row-avatar lb-blurred">
                                    <img
                                        v-if="user.avatar && !user.blurred"
                                        :src="user.avatar"
                                        :alt="`${user.name} avatar`"
                                        class="h-full w-full object-cover blur-sm"
                                    />
                                    <User
                                        v-else
                                        class="h-4 w-4 text-muted-foreground/40"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-lg bg-primary/[0.03] backdrop-blur-[1px]"
                                    >
                                        <EyeOff
                                            class="h-3 w-3 text-muted-foreground/30"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="lb-blurred-text text-xs font-bold tracking-tight break-words sm:text-sm"
                                        @contextmenu.prevent
                                        >{{
                                            user.blurred
                                                ? BLURRED_NAME
                                                : user.name
                                        }}</span
                                    >
                                    <span
                                        v-if="user.isCurrentUser"
                                        class="shrink-0 rounded-full bg-[#007AFF] px-1.5 py-0.5 text-[11px] font-semibold text-white"
                                        >YOU</span
                                    >
                                </div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <Flame
                                        class="h-2.5 w-2.5 text-orange-400/70"
                                    />
                                    <span
                                        class="text-[13px] font-medium text-muted-foreground"
                                        >{{ user.streak }}d streak</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Right side -->
                        <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                            <!-- XP -->
                            <div class="text-right">
                                <div class="flex items-baseline gap-0.5">
                                    <span
                                        class="text-[15px] font-semibold tracking-tight tabular-nums sm:text-base"
                                        >{{ user.xp.toLocaleString() }}</span
                                    >
                                    <span
                                        class="text-[12px] font-medium text-muted-foreground"
                                        >XP</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <component
                                        :is="trendOf(user).icon"
                                        class="h-2.5 w-2.5"
                                        :class="trendOf(user).iconColor"
                                    />
                                    <Sparkles
                                        class="h-2 w-2 text-[#007AFF]/70"
                                    />
                                    <span
                                        class="text-[12px] text-muted-foreground"
                                        >+{{
                                            user.weeklyXp >= 1000
                                                ? (
                                                      user.weeklyXp / 1000
                                                  ).toFixed(1) + 'k'
                                                : user.weeklyXp
                                        }}</span
                                    >
                                </div>
                            </div>

                            <!-- Actions (hover) -->
                            <div
                                v-if="!user.blurred"
                                class="hidden items-center gap-1 opacity-0 transition-opacity duration-300 group-hover:opacity-100 sm:flex"
                            >
                                <Link
                                    :href="`/u/${user.id}`"
                                    class="lb-action-btn"
                                    title="View Profile"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                </Link>
                                <button
                                    @click="openHistory(user)"
                                    class="lb-action-btn"
                                    title="XP History"
                                >
                                    <History class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Show more -->
                    <div
                        v-if="filteredUsers.length > 10"
                        class="flex justify-center pt-3"
                    >
                        <button
                            @click="showAllRankings = !showAllRankings"
                            class="lb-show-more"
                        >
                            <component
                                :is="showAllRankings ? ChevronUp : ChevronDown"
                                class="h-4 w-4"
                            />
                            <span>{{
                                showAllRankings
                                    ? 'Show Less'
                                    : `Show All (${filteredUsers.length - 3})`
                            }}</span>
                        </button>
                    </div>
                </div>
            </template>
        </template>

        <!-- XP History Modal -->
        <Dialog v-model:open="isHistoryOpen">
            <DialogContent
                class="overflow-hidden border-border/50 bg-card p-0 sm:max-w-[420px]"
            >
                <div class="border-b border-border/20 p-6 pb-4">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-[#007AFF]/10"
                        >
                            <History class="h-5 w-5 text-[#007AFF]" />
                        </div>
                        <div>
                            <DialogTitle
                                class="text-[17px] font-semibold tracking-tight"
                                >{{
                                    selectedUser?.blurred
                                        ? BLURRED_NAME
                                        : selectedUser?.name
                                }}</DialogTitle
                            >
                            <span
                                class="text-[13px] font-medium text-muted-foreground"
                                >XP History</span
                            >
                        </div>
                    </div>
                </div>

                <div class="max-h-[400px] scrollbar-none overflow-y-auto">
                    <div
                        v-if="isLoadingHistory"
                        class="flex flex-col items-center gap-3 py-16"
                    >
                        <Loader2 class="h-8 w-8 animate-spin text-[#007AFF]" />
                        <p
                            class="text-[13px] font-medium text-muted-foreground"
                        >
                            Loading...
                        </p>
                    </div>
                    <div
                        v-else-if="xpHistory.length === 0"
                        class="flex flex-col items-center px-8 py-16 text-center"
                    >
                        <Activity
                            class="mb-3 h-8 w-8 text-muted-foreground/20"
                        />
                        <p class="text-sm font-bold">No Activity Yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            No XP entries recorded.
                        </p>
                    </div>
                    <div v-else class="space-y-1.5 p-3">
                        <div
                            v-for="(item, index) in xpHistory"
                            :key="item.id"
                            class="animate-fade-up flex items-center justify-between rounded-xl p-3 transition-colors hover:bg-muted/30"
                            :style="{ animationDelay: `${index * 40}ms` }"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted/30"
                                >
                                    <component
                                        :is="
                                            item.reason.includes('Exam')
                                                ? Trophy
                                                : item.reason.includes('Enroll')
                                                  ? Sparkles
                                                  : Award
                                        "
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold">
                                        {{ item.reason }}
                                    </p>
                                    <p
                                        v-if="item.description"
                                        class="truncate text-[10px] text-muted-foreground"
                                    >
                                        {{ item.description }}
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono text-[8px] text-muted-foreground/50"
                                    >
                                        {{ item.created_at }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="shrink-0 pl-3 text-sm font-semibold tabular-nums"
                                :class="
                                    item.amount_xp >= 0
                                        ? 'text-emerald-400'
                                        : 'text-red-400'
                                "
                            >
                                {{ item.amount_xp >= 0 ? '+' : ''
                                }}{{ item.amount_xp }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center border-t border-border/20 p-4">
                    <button
                        @click="isHistoryOpen = false"
                        class="dash-btn px-6 text-[15px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
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
    @apply shrink-0 rounded-full border border-border/50 bg-card px-4 py-2 text-[13px] font-medium text-muted-foreground transition-colors;
    min-height: 44px;
}
.lb-tab--active {
    @apply border-transparent bg-[#007AFF] text-white;
}
.lb-search {
    @apply rounded-full border border-border/50 bg-muted/40 text-[15px] font-normal transition-colors focus:border-[#007AFF]/40 focus:ring-2 focus:ring-[#007AFF]/20 focus:outline-none;
    min-height: 44px;
}
.lb-season-pill {
    @apply flex shrink-0 items-center gap-1.5 rounded-full border border-border/50 bg-card px-3 py-2 text-[13px] font-medium text-muted-foreground;
    min-height: 44px;
}
.lb-season-select {
    @apply rounded-full border border-border/50 bg-card px-3 py-2 text-[13px] font-medium text-muted-foreground transition-colors focus:border-[#007AFF]/40 focus:ring-2 focus:ring-[#007AFF]/20 focus:outline-none;
    min-height: 44px;
}
.lb-empty {
    @apply flex flex-col items-center justify-center rounded-[1.25rem] border border-border/40 bg-card px-6 py-16 text-center;
}

.lb-rank-row {
    @apply relative overflow-hidden rounded-[1.25rem] border border-border/40 bg-card p-4 sm:p-5;
}

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
    @apply relative rounded-[1.25rem] border border-border/40 bg-card p-5 transition-colors sm:p-6;
}
.lb-podium-card:hover {
    @apply bg-muted/30;
}
.lb-podium-card--champ {
    @apply border-[#007AFF]/20;
}
@media (min-width: 640px) {
    .lb-podium-card--champ {
        padding-top: 2rem;
        padding-bottom: 2rem;
    }
}
.lb-rank-badge {
    @apply mb-3 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold;
}
.lb-avatar {
    @apply block flex items-center justify-center overflow-hidden rounded-full bg-muted/40 transition-transform duration-300;
}
.lb-avatar:hover {
    transform: scale(1.03);
}

.lb-row {
    @apply flex items-center justify-between rounded-[1.1rem] border border-border/30 bg-card px-3 py-3 transition-colors hover:bg-muted/30 sm:px-4 sm:py-3.5;
}
.lb-row--you {
    @apply border-[#007AFF]/20 bg-[#007AFF]/[0.04];
}
.lb-row-rank {
    @apply flex h-9 w-9 items-center justify-center rounded-full bg-muted/50 sm:h-10 sm:w-10;
}
.lb-row-avatar {
    @apply flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-border/30 bg-muted/30 sm:h-10 sm:w-10;
}
.lb-action-btn {
    @apply rounded-full p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground;
    min-height: 36px;
    min-width: 36px;
}
.lb-show-more {
    @apply flex items-center gap-2 rounded-full border border-border/50 bg-card px-6 py-2.5 text-[14px] font-medium text-muted-foreground transition-colors hover:bg-muted;
    min-height: 44px;
}

.lb-blur-toggle {
    @apply flex items-center gap-1.5 rounded-full border border-border/50 bg-card px-3 py-2 text-[13px] font-medium transition-colors hover:bg-muted disabled:opacity-50;
    min-height: 44px;
}

.lb-blurred {
    @apply overflow-hidden;
}
.lb-blurred-text {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}
.lb-row--blurred {
    @apply border-muted/20 bg-muted/[0.02];
}
.lb-blurred-text::selection {
    background: transparent;
}
.lb-blurred-text::-moz-selection {
    background: transparent;
}
</style>
