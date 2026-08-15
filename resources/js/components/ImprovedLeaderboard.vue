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
    Users,
    Plus,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import type { Component } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
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

interface RankGroup {
    rank: number;
    xp: number;
    xpProgress: number;
    users: LeaderboardUser[];
    hasCurrentUser: boolean;
    totalWeeklyXp: number;
    maxStreak: number;
}

interface Props {
    sectionLeaderboards: LeaderboardData[];
    activeSeasonName?: string;
    availableSeasons?: Season[];
    showViewButton?: boolean;
    /** Show a "Join Section" button in the section-tabs row (used on the dashboard). */
    showJoinButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    availableSeasons: () => [],
    showViewButton: false,
    showJoinButton: false,
});

const emit = defineEmits<{
    'update:activeSeasonName': [name: string];
    'open-section-modal': [];
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

// Count how many peers share the current user's exact XP score
const tiedWithCount = computed(() => {
    if (!currentUser.value) return 0;
    return users.value.filter(
        (u) => u.id !== currentUser.value?.id && u.xp === currentUser.value?.xp,
    ).length;
});

// True 1-based rank for every user (ties share a rank), computed over the
// FULL list. This is used so that search results keep their real position
// instead of being renumbered to the top of the filtered set.
const trueRankById = computed<Record<number, number>>(() => {
    const map: Record<number, number> = {};
    let rank = 1;
    let prevXp: number | null = null;
    users.value.forEach((u, i) => {
        if (prevXp === null || u.xp !== prevXp) {
            rank = i + 1;
        }
        map[u.id] = rank;
        prevXp = u.xp;
    });
    return map;
});

// Group filtered users by XP score so tied students share a single rank card.
// The group's rank comes from its members' true rank (not the filtered index),
// so a searched user keeps their actual placement.
const rankGroups = computed<RankGroup[]>(() => {
    const list = filteredUsers.value;
    if (!list.length) return [];

    const groups: RankGroup[] = [];
    let currentGroup: RankGroup | null = null;

    for (const u of list) {
        if (!currentGroup || currentGroup.xp !== u.xp) {
            currentGroup = {
                rank: trueRankById.value[u.id],
                xp: u.xp,
                xpProgress: u.xpProgress,
                users: [u],
                hasCurrentUser: Boolean(u.isCurrentUser),
                totalWeeklyXp: u.weeklyXp,
                maxStreak: u.streak,
            };
            groups.push(currentGroup);
        } else {
            currentGroup.users.push(u);
            if (u.isCurrentUser) {
                currentGroup.hasCurrentUser = true;
            }
            currentGroup.totalWeeklyXp += u.weeklyXp;
            if (u.streak > currentGroup.maxStreak) {
                currentGroup.maxStreak = u.streak;
            }
        }
    }

    return groups;
});

// Weekly trend indicators (computed server-side from real XP history)
const trendMeta: Record<
    'up' | 'down' | 'stable',
    { icon: Component; label: string; chip: string; iconColor: string }
> = {
    up: {
        icon: TrendingUp,
        label: 'Up',
        chip: 'bg-[#4D9375]/10 text-[#4D9375]',
        iconColor: 'text-[#4D9375]',
    },
    down: {
        icon: TrendingDown,
        label: 'Down',
        chip: 'bg-[#CB7676]/10 text-[#CB7676]',
        iconColor: 'text-[#CB7676]',
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

const top3Groups = computed(() => rankGroups.value.slice(0, 3));
const showAllRankings = ref(false);
const restGroups = computed(() => {
    const rest = rankGroups.value.slice(3);
    return showAllRankings.value ? rest : rest.slice(0, 7);
});

// Expanded states for tied list row cards (> 3 students)
const expandedGroupRanks = ref<number[]>([]);
const isGroupExpanded = (rank: number) =>
    expandedGroupRanks.value.includes(rank);
const toggleExpandGroup = (rank: number) => {
    const idx = expandedGroupRanks.value.indexOf(rank);
    if (idx === -1) {
        expandedGroupRanks.value.push(rank);
    } else {
        expandedGroupRanks.value.splice(idx, 1);
    }
};

const animXP1 = useNumberAnimation(() => top3Groups.value[0]?.xp || 0);
const animXP2 = useNumberAnimation(() => top3Groups.value[1]?.xp || 0);
const animXP3 = useNumberAnimation(() => top3Groups.value[2]?.xp || 0);
const getAnimXP = (i: number) => {
    if (i === 0) return animXP1;
    if (i === 1) return animXP2;
    if (i === 2) return animXP3;
    return { value: top3Groups.value[i]?.xp || 0 };
};

// Podium ordering: on desktop, show 2nd-1st-3rd
const podiumOrder = computed(() => {
    const t = top3Groups.value;
    if (t.length < 3) return t.map((group, i) => ({ group, origIdx: i }));
    return [
        { group: t[1], origIdx: 1 },
        { group: t[0], origIdx: 0 },
        { group: t[2], origIdx: 2 },
    ];
});

// Limits for tied users displayed inside podium cards
const PODIUM_TIED_AVATAR_LIMIT = 8;
const PODIUM_NAME_LIMIT = 3;

const rankMeta = [
    {
        label: '1st',
        icon: Crown,
        ring: 'ring-[#D97757]/50',
        glow: 'shadow-[#D97757]/20',
        accent: 'text-[#D97757]',
        bg: 'from-[#D97757]/15 via-transparent to-transparent',
        badge: 'bg-[#D97757] text-white',
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
        ring: 'ring-[#E0AF68]/50',
        glow: 'shadow-[#E0AF68]/20',
        accent: 'text-[#E0AF68]',
        bg: 'from-[#E0AF68]/15 via-[#E0AF68]/5 to-transparent',
        badge: 'bg-[#E0AF68] text-black',
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

// Tied Users Modal
const isTiedModalOpen = ref(false);
const selectedTiedGroup = ref<RankGroup | null>(null);
const selectedTiedOrigIdx = ref<number | null>(null);

const openTiedModal = (group: RankGroup, origIdx?: number) => {
    selectedTiedGroup.value = group;
    selectedTiedOrigIdx.value = origIdx !== undefined ? origIdx : null;
    isTiedModalOpen.value = true;
};

const openCurrentUserTiedModal = () => {
    if (!currentUser.value) return;
    const group = rankGroups.value.find((g) => g.hasCurrentUser);
    if (group) {
        const podiumIdx = top3Groups.value.findIndex(
            (g) => g.rank === group.rank,
        );
        openTiedModal(group, podiumIdx !== -1 ? podiumIdx : undefined);
    }
};

const getGroupRankMeta = (group: RankGroup | null) => {
    if (!group) return null;
    if (group.rank === 1) return rankMeta[0];
    if (group.rank === 2) return rankMeta[1];
    if (group.rank === 3) return rankMeta[2];
    return {
        label: `#${group.rank}`,
        icon: Trophy,
        ring: 'ring-muted-foreground/30',
        glow: '',
        accent: 'text-muted-foreground',
        bg: 'from-muted/20 via-transparent to-transparent',
        badge: 'bg-muted text-muted-foreground',
    };
};

// Blur toggle
const isTogglingBlur = ref(false);

const currentUserBlurred = computed(() => {
    const current = users.value.find((u) => u.isCurrentUser);
    return current?.blurred ?? false;
});

const toggleBlur = async () => {
    if (isTogglingBlur.value) return;

    // Compute the new optimistic state
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
    <div class="lb-root max-w-full min-w-0 space-y-6">
        <!-- Section Tabs -->
        <div
            v-if="localLeaderboards.length > 1 || showJoinButton"
            class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-2"
        >
            <button
                v-for="(section, idx) in localLeaderboards"
                :key="section.sectionId"
                @click="activeTabIndex = idx"
                :class="['lb-tab', activeTabIndex === idx && 'lb-tab--active']"
            >
                {{ section.sectionName }}
            </button>

            <!-- Join Section: aligned to the right of the section tabs (desktop) -->
            <button
                v-if="showJoinButton"
                type="button"
                class="dash-btn ml-auto hidden shrink-0 items-center gap-2 self-center rounded-full border border-border/60 bg-card px-4 py-2 text-[13px] font-semibold text-foreground shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-colors hover:bg-muted active:scale-95 lg:inline-flex lg:text-[15px]"
                @click="emit('open-section-modal')"
            >
                <Plus class="h-4 w-4 shrink-0" />
                <span>Join Section</span>
            </button>
        </div>

        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
        >
            <div>
                <div class="mb-1 flex items-center gap-2">
                    <Trophy class="h-4 w-4 text-[#D97757]" />
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
            <div
                class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end"
            >
                <!-- Search: full-width row on mobile, fixed width on sm+ -->
                <div class="relative w-full sm:w-52">
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

                <!-- Action controls share an equal-width row beneath search on mobile -->
                <div
                    class="flex w-full gap-2 sm:w-auto sm:flex-none sm:justify-end"
                >
                    <Link
                        v-if="showViewButton"
                        href="/leaderboard"
                        class="dash-btn flex flex-1 items-center justify-center gap-2 bg-[#D97757]/10 px-4 text-[14px] text-[#D97757] transition hover:bg-[#D97757]/15 sm:flex-none"
                    >
                        <Trophy class="h-3.5 w-3.5" />
                        View Leaderboard
                    </Link>
                    <!-- Season Dropdown -->
                    <div
                        v-if="availableSeasons.length > 1"
                        class="relative max-w-full min-w-0 flex-1 sm:flex-none"
                    >
                        <select
                            :value="selectedSeasonId || availableSeasons[0]?.id"
                            @change="
                                (e) =>
                                    changeSeason(
                                        Number(
                                            (e.target as HTMLSelectElement)
                                                .value,
                                        ),
                                    )
                            "
                            :disabled="isSwitchingSeason"
                            class="lb-season-select w-full min-w-0 cursor-pointer appearance-none truncate pr-8"
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
                    <div
                        v-else
                        class="lb-season-pill flex min-w-0 flex-1 items-center justify-center gap-1.5 sm:flex-none"
                    >
                        <Terminal class="h-3 w-3 shrink-0 text-[#D97757]" />
                        <span class="truncate">{{ currentSeasonName }}</span>
                    </div>

                    <!-- Blur toggle -->
                    <button
                        @click="toggleBlur"
                        :disabled="isTogglingBlur"
                        class="lb-blur-toggle flex min-w-0 flex-1 items-center justify-center gap-1.5 sm:flex-none"
                        :title="
                            currentUserBlurred
                                ? 'You are hidden — click to appear'
                                : 'You are visible — click to hide'
                        "
                    >
                        <EyeOff
                            v-if="currentUserBlurred"
                            class="h-3.5 w-3.5 shrink-0"
                        />
                        <Eye v-else class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{
                            currentUserBlurred ? 'Hidden' : 'Visible'
                        }}</span>
                    </button>
                </div>
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
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                    >
                        <Trophy class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[13px] font-medium text-muted-foreground"
                        >
                            Your rank
                        </p>
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span
                                class="text-[28px] leading-none font-semibold tracking-tight tabular-nums sm:text-[32px]"
                                >#{{ userRank }}</span
                            >
                            <button
                                v-if="tiedWithCount > 0"
                                type="button"
                                @click="openCurrentUserTiedModal"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-[#D97757]/15 px-2 py-0.5 text-xs font-semibold text-[#D97757] transition-colors hover:bg-[#D97757]/25 focus:outline-none"
                                title="View tied players"
                            >
                                <Users class="h-3 w-3" />
                                Tied with {{ tiedWithCount }} other{{
                                    tiedWithCount > 1 ? 's' : ''
                                }}
                            </button>
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
            <Loader2 class="h-8 w-8 animate-spin text-[#D97757]" />
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
                    class="mt-3 text-[13px] font-medium text-[#D97757] hover:underline"
                >
                    Clear search
                </button>
            </div>

            <template v-else>
                <!-- ═══════ PODIUM ═══════ -->
                <div class="lb-podium min-w-0">
                    <SpotlightCard
                        v-for="{ group, origIdx } in podiumOrder"
                        :key="group.rank"
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
                                origIdx === 0 &&
                                    'lb-podium-card--champ order-1 sm:order-2',
                                origIdx === 1 && 'order-2 sm:order-1',
                                origIdx === 2 && 'order-3 sm:order-3',
                                group.hasCurrentUser && 'lb-podium-card--you',
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
                                    getGroupRankMeta(group)?.badge,
                                ]"
                            >
                                <component
                                    :is="getGroupRankMeta(group)?.icon"
                                    class="h-3 w-3"
                                />
                                <span>{{
                                    getGroupRankMeta(group)?.label
                                }}</span>
                                <button
                                    v-if="group.users.length > 1"
                                    type="button"
                                    @click.stop="openTiedModal(group, origIdx)"
                                    class="ml-1 cursor-pointer text-[11px] font-normal opacity-90 hover:underline focus:outline-none"
                                    title="View all tied players"
                                >
                                    • Tied ({{ group.users.length }})
                                </button>
                            </div>

                            <!-- Single User Avatar -->
                            <div
                                v-if="group.users.length === 1"
                                class="relative"
                            >
                                <Link
                                    v-if="!group.users[0].blurred"
                                    :href="`/u/${group.users[0].id}`"
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
                                        v-if="
                                            group.users[0].avatar &&
                                            !group.users[0].blurred
                                        "
                                        :src="group.users[0].avatar"
                                        :alt="`${group.users[0].name} avatar`"
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
                                    <User
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

                            <!-- Multiple Tied Users: avatars (limited) -->
                            <div
                                v-else
                                class="flex flex-wrap items-center justify-center gap-1.5 py-1"
                            >
                                <template
                                    v-for="u in group.users.slice(
                                        0,
                                        PODIUM_TIED_AVATAR_LIMIT,
                                    )"
                                    :key="u.id"
                                >
                                    <div class="relative">
                                        <Link
                                            v-if="!u.blurred"
                                            :href="`/u/${u.id}`"
                                            :title="u.name"
                                            :class="[
                                                'lb-avatar ring-2 ring-background transition-transform hover:z-20 hover:scale-110',
                                                origIdx === 0
                                                    ? 'h-10 w-10 sm:h-12 sm:w-12'
                                                    : 'h-9 w-9 sm:h-10 sm:w-10',
                                                rankMeta[origIdx].ring,
                                            ]"
                                        >
                                            <img
                                                v-if="u.avatar"
                                                :src="u.avatar"
                                                :alt="`${u.name} avatar`"
                                                loading="lazy"
                                                decoding="async"
                                                class="h-full w-full object-cover"
                                            />
                                            <User
                                                v-else
                                                class="h-5 w-5 text-muted-foreground/40"
                                            />
                                        </Link>
                                        <div
                                            v-else
                                            :class="[
                                                'lb-avatar lb-blurred ring-2 ring-background',
                                                origIdx === 0
                                                    ? 'h-10 w-10 sm:h-12 sm:w-12'
                                                    : 'h-9 w-9 sm:h-10 sm:w-10',
                                                rankMeta[origIdx].ring,
                                            ]"
                                        >
                                            <User
                                                class="h-5 w-5 text-muted-foreground/40"
                                            />
                                            <div
                                                class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-primary/[0.03] backdrop-blur-[2px]"
                                            >
                                                <EyeOff
                                                    class="h-3.5 w-3.5 text-muted-foreground/40"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <button
                                    v-if="
                                        group.users.length >
                                        PODIUM_TIED_AVATAR_LIMIT
                                    "
                                    @click.stop="openTiedModal(group, origIdx)"
                                    class="inline-flex items-center justify-center rounded-full border border-border/40 bg-muted/40 px-2 py-1 text-[11px] font-bold text-muted-foreground transition-colors hover:bg-muted/60"
                                >
                                    +{{
                                        group.users.length -
                                        PODIUM_TIED_AVATAR_LIMIT
                                    }}
                                </button>
                            </div>

                            <!-- Single User Name -->
                            <template v-if="group.users.length === 1">
                                <Link
                                    v-if="!group.users[0].blurred"
                                    :href="`/u/${group.users[0].id}`"
                                    :class="[
                                        'mt-3 max-w-full text-center leading-snug font-semibold tracking-tight break-words transition-colors hover:text-[#D97757]',
                                        getNameSize(
                                            group.users[0].name,
                                            origIdx === 0,
                                        ),
                                    ]"
                                >
                                    {{ group.users[0].name }}
                                </Link>
                                <span
                                    v-else
                                    :class="[
                                        'lb-blurred lb-blurred-text mt-3 max-w-full text-center leading-snug font-semibold tracking-tight break-words',
                                        getNameSize(
                                            group.users[0].name,
                                            origIdx === 0,
                                        ),
                                    ]"
                                    @contextmenu.prevent
                                >
                                    <span>{{ BLURRED_NAME }}</span>
                                </span>
                                <span
                                    v-if="group.users[0].isCurrentUser"
                                    class="mt-1 rounded-full bg-[#D97757] px-2 py-0.5 text-[11px] font-semibold text-white"
                                    >You</span
                                >
                            </template>

                            <!-- Multiple Tied Users: Names List (limited) -->
                            <div
                                v-else
                                class="mt-2.5 flex max-w-full flex-wrap items-center justify-center gap-x-1.5 gap-y-1 px-1 text-center"
                            >
                                <template
                                    v-for="(u, uIdx) in group.users.slice(
                                        0,
                                        PODIUM_NAME_LIMIT,
                                    )"
                                    :key="u.id"
                                >
                                    <div class="inline-flex items-center gap-1">
                                        <Link
                                            v-if="!u.blurred"
                                            :href="`/u/${u.id}`"
                                            class="text-xs font-semibold tracking-tight transition-colors hover:text-[#D97757] sm:text-sm"
                                        >
                                            {{ u.name }}
                                        </Link>
                                        <span
                                            v-else
                                            class="lb-blurred-text text-xs font-semibold sm:text-sm"
                                        >
                                            {{ BLURRED_NAME }}
                                        </span>
                                        <span
                                            v-if="u.isCurrentUser"
                                            class="py-0.2 rounded-full bg-[#D97757] px-1.5 text-[10px] font-semibold text-white"
                                            >YOU</span
                                        >
                                        <span
                                            v-if="
                                                uIdx <
                                                Math.min(
                                                    group.users.length,
                                                    PODIUM_NAME_LIMIT,
                                                ) -
                                                    1
                                            "
                                            class="text-xs text-muted-foreground/60"
                                            >,</span
                                        >
                                    </div>
                                </template>
                                <span
                                    v-if="
                                        group.users.length > PODIUM_NAME_LIMIT
                                    "
                                    class="text-xs text-muted-foreground"
                                    >and
                                    {{ group.users.length - PODIUM_NAME_LIMIT }}
                                    more</span
                                >
                            </div>

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
                                    <Flame class="h-3 w-3 text-[#D97757]" />
                                    <span class="font-bold"
                                        >{{ group.maxStreak }}d</span
                                    >
                                </div>
                                <div class="h-3 w-px bg-border/60"></div>
                                <div class="flex items-center gap-1">
                                    <Sparkles class="h-3 w-3 text-[#D97757]" />
                                    <span class="font-bold"
                                        >{{ group.xpProgress }}%</span
                                    >
                                </div>
                            </div>

                            <!-- XP bar -->
                            <div
                                class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted/30"
                            >
                                <div
                                    class="h-full rounded-full bg-[#D97757] transition-all duration-700"
                                    :style="{ width: `${group.xpProgress}%` }"
                                ></div>
                            </div>
                        </div>
                    </SpotlightCard>
                </div>

                <!-- ═══════ LIST RANKINGS ═══════ -->
                <div v-if="rankGroups.length > 3" class="space-y-2">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <Activity class="h-3.5 w-3.5 text-[#D97757]" />
                            <span
                                class="text-[13px] font-medium text-muted-foreground"
                                >Rankings</span
                            >
                        </div>
                        <span
                            class="font-mono text-[10px] text-muted-foreground"
                            >{{ filteredUsers.length }} players ·
                            {{ rankGroups.length }} ranks</span
                        >
                    </div>

                    <div
                        v-for="(group, i) in restGroups"
                        :key="group.rank"
                        class="lb-row group animate-fade-up"
                        :class="{
                            'lb-row--you': group.hasCurrentUser,
                        }"
                        :style="{ animationDelay: `${(i + 3) * 60}ms` }"
                    >
                        <!-- Left: Rank Number & Details -->
                        <div
                            class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4"
                        >
                            <div class="lb-row-rank shrink-0">
                                <span
                                    class="text-[13px] font-semibold tabular-nums sm:text-sm"
                                    >#{{ group.rank }}</span
                                >
                            </div>

                            <!-- Single User Case -->
                            <template v-if="group.users.length === 1">
                                <!-- Avatar -->
                                <div class="relative shrink-0">
                                    <Link
                                        v-if="!group.users[0].blurred"
                                        :href="`/u/${group.users[0].id}`"
                                        class="lb-row-avatar"
                                    >
                                        <img
                                            v-if="
                                                group.users[0].avatar &&
                                                !group.users[0].blurred
                                            "
                                            :src="group.users[0].avatar"
                                            :alt="`${group.users[0].name} avatar`"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-full w-full object-cover"
                                        />
                                        <User
                                            v-else
                                            class="h-4 w-4 text-muted-foreground/40"
                                        />
                                    </Link>
                                    <div
                                        v-else
                                        class="lb-row-avatar lb-blurred"
                                    >
                                        <User
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
                                        <Link
                                            v-if="!group.users[0].blurred"
                                            :href="`/u/${group.users[0].id}`"
                                            class="text-xs font-bold tracking-tight break-words transition-colors hover:text-[#D97757] sm:text-sm"
                                        >
                                            {{ group.users[0].name }}
                                        </Link>
                                        <span
                                            v-else
                                            class="lb-blurred-text text-xs font-bold tracking-tight break-words sm:text-sm"
                                            @contextmenu.prevent
                                        >
                                            {{ BLURRED_NAME }}
                                        </span>
                                        <span
                                            v-if="group.users[0].isCurrentUser"
                                            class="shrink-0 rounded-full bg-[#D97757] px-1.5 py-0.5 text-[11px] font-semibold text-white"
                                            >YOU</span
                                        >
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-2">
                                        <Flame
                                            class="h-2.5 w-2.5 text-[#D97757]/70"
                                        />
                                        <span
                                            class="text-[13px] font-medium text-muted-foreground"
                                            >{{ group.users[0].streak }}d
                                            streak</span
                                        >
                                    </div>
                                </div>
                            </template>

                            <!-- Multiple Tied Users Case -->
                            <template v-else>
                                <div class="min-w-0 flex-1 py-1">
                                    <div class="mb-1.5 flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="openTiedModal(group)"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[11px] font-semibold text-[#D97757] transition-colors hover:bg-[#D97757]/20 focus:outline-none"
                                            title="View all tied students"
                                        >
                                            <Users class="h-3 w-3" />
                                            Tied ({{ group.users.length }}
                                            students)
                                        </button>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <div
                                            v-for="u in isGroupExpanded(
                                                group.rank,
                                            )
                                                ? group.users
                                                : group.users.slice(0, 3)"
                                            :key="u.id"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-border/40 bg-muted/30 px-2.5 py-1.5 transition-colors hover:bg-muted/50"
                                        >
                                            <div
                                                class="lb-row-avatar h-6 w-6 shrink-0"
                                            >
                                                <img
                                                    v-if="
                                                        u.avatar && !u.blurred
                                                    "
                                                    :src="u.avatar"
                                                    :alt="`${u.name} avatar`"
                                                    loading="lazy"
                                                    decoding="async"
                                                    class="h-full w-full object-cover"
                                                />
                                                <User
                                                    v-else
                                                    class="h-3.5 w-3.5 text-muted-foreground/40"
                                                />
                                            </div>
                                            <Link
                                                v-if="!u.blurred"
                                                :href="`/u/${u.id}`"
                                                class="text-xs font-semibold hover:text-[#D97757]"
                                            >
                                                {{ u.name }}
                                            </Link>
                                            <span
                                                v-else
                                                class="lb-blurred-text text-xs font-semibold"
                                            >
                                                {{ BLURRED_NAME }}
                                            </span>
                                            <span
                                                v-if="u.isCurrentUser"
                                                class="py-0.2 rounded-full bg-[#D97757] px-1 text-[9px] font-bold text-white"
                                                >YOU</span
                                            >
                                            <button
                                                v-if="!u.blurred"
                                                @click="openHistory(u)"
                                                class="inline-flex items-center justify-center rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
                                                title="XP History"
                                                aria-label="XP History"
                                            >
                                                <History class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <!-- Expand/Collapse toggle if more than 3 students -->
                                        <button
                                            v-if="group.users.length > 3"
                                            @click="
                                                toggleExpandGroup(group.rank)
                                            "
                                            class="rounded-lg border border-border/40 bg-muted/20 px-3 py-1.5 text-xs font-medium text-[#D97757] transition-colors hover:bg-muted/40"
                                        >
                                            {{
                                                isGroupExpanded(group.rank)
                                                    ? 'Show less'
                                                    : `+${group.users.length - 3} more`
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Right side -->
                        <div
                            class="flex shrink-0 items-center gap-2 pl-2 sm:gap-4"
                        >
                            <!-- XP -->
                            <div class="text-right">
                                <div
                                    class="flex items-baseline justify-end gap-0.5"
                                >
                                    <span
                                        class="text-[15px] font-semibold tracking-tight tabular-nums sm:text-base"
                                        >{{ group.xp.toLocaleString() }}</span
                                    >
                                    <span
                                        class="text-[12px] font-medium text-muted-foreground"
                                        >XP</span
                                    >
                                </div>
                                <div
                                    v-if="group.users.length === 1"
                                    class="flex items-center justify-end gap-1"
                                >
                                    <component
                                        :is="trendOf(group.users[0]).icon"
                                        class="h-2.5 w-2.5"
                                        :class="
                                            trendOf(group.users[0]).iconColor
                                        "
                                    />
                                    <Sparkles
                                        class="h-2 w-2 text-[#D97757]/70"
                                    />
                                    <span
                                        class="text-[12px] text-muted-foreground"
                                        >+{{
                                            group.users[0].weeklyXp >= 1000
                                                ? (
                                                      group.users[0].weeklyXp /
                                                      1000
                                                  ).toFixed(1) + 'k'
                                                : group.users[0].weeklyXp
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-else
                                    class="text-[11px] font-medium text-muted-foreground"
                                >
                                    Same score
                                </div>
                            </div>

                            <!-- Actions (hover) for single user -->
                            <div
                                v-if="
                                    group.users.length === 1 &&
                                    !group.users[0].blurred
                                "
                                class="hidden items-center gap-1 opacity-0 transition-opacity duration-300 group-hover:opacity-100 sm:flex"
                            >
                                <Link
                                    :href="`/u/${group.users[0].id}`"
                                    class="lb-action-btn"
                                    title="View Profile"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                </Link>
                                <button
                                    @click="openHistory(group.users[0])"
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
                        v-if="rankGroups.length > 10"
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
                                    : `Show All (${rankGroups.length - 3} more ranks)`
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
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-[#D97757]/10"
                        >
                            <History class="h-5 w-5 text-[#D97757]" />
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
                            <DialogDescription
                                class="text-[13px] font-medium text-muted-foreground"
                                >XP History</DialogDescription
                            >
                        </div>
                    </div>
                </div>

                <div class="max-h-[400px] scrollbar-none overflow-y-auto">
                    <div
                        v-if="isLoadingHistory"
                        class="flex flex-col items-center gap-3 py-16"
                    >
                        <Loader2 class="h-8 w-8 animate-spin text-[#D97757]" />
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
                                        ? 'text-[#4D9375]'
                                        : 'text-[#CB7676]'
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

        <!-- Tied Players Modal -->
        <Dialog v-model:open="isTiedModalOpen">
            <DialogContent
                class="overflow-hidden border-border/50 bg-card p-0 sm:max-w-[540px]"
            >
                <div class="border-b border-border/20 p-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            :class="
                                selectedTiedGroup?.rank === 1
                                    ? 'bg-[#D97757]/15 text-[#D97757]'
                                    : selectedTiedGroup?.rank === 2
                                      ? 'bg-slate-300/15 text-slate-300'
                                      : selectedTiedGroup?.rank === 3
                                        ? 'bg-[#E0AF68]/15 text-[#E0AF68]'
                                        : 'bg-muted text-muted-foreground'
                            "
                        >
                            <component
                                :is="
                                    getGroupRankMeta(selectedTiedGroup)?.icon ||
                                    Trophy
                                "
                                class="h-5 w-5"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <DialogTitle
                                class="truncate text-[17px] font-semibold tracking-tight"
                            >
                                {{
                                    selectedTiedGroup?.rank === 1
                                        ? '1st Place'
                                        : selectedTiedGroup?.rank === 2
                                          ? '2nd Place'
                                          : selectedTiedGroup?.rank === 3
                                            ? '3rd Place'
                                            : `Rank #${selectedTiedGroup?.rank}`
                                }}
                                · Tied Players ({{
                                    selectedTiedGroup?.users.length || 0
                                }})
                            </DialogTitle>
                            <DialogDescription
                                class="mt-0.5 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground"
                            >
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    {{ selectedTiedGroup?.xp.toLocaleString() }}
                                    XP
                                </span>
                                <span>•</span>
                                <span>
                                    {{ selectedTiedGroup?.users.length }}
                                    students sharing this score
                                </span>
                            </DialogDescription>
                        </div>
                    </div>
                </div>

                <!-- 5 Profiles per Layer (Row) Grid -->
                <div
                    class="max-h-[60vh] scrollbar-none overflow-y-auto px-4 py-5 sm:px-6"
                >
                    <div
                        class="grid grid-cols-5 justify-items-center gap-x-2 gap-y-5 sm:gap-x-4 sm:gap-y-6"
                    >
                        <div
                            v-for="u in selectedTiedGroup?.users"
                            :key="u.id"
                            class="group flex w-full max-w-[80px] flex-col items-center text-center sm:max-w-[90px]"
                        >
                            <!-- Circle Profile Avatar -->
                            <div class="relative">
                                <Link
                                    v-if="!u.blurred"
                                    :href="`/u/${u.id}`"
                                    :title="u.name"
                                    :class="[
                                        'lb-avatar h-12 w-12 ring-2 transition-transform duration-200 group-hover:scale-105 sm:h-14 sm:w-14',
                                        getGroupRankMeta(selectedTiedGroup)
                                            ?.ring,
                                        u.isCurrentUser && 'ring-[#D97757]',
                                    ]"
                                >
                                    <img
                                        v-if="u.avatar"
                                        :src="u.avatar"
                                        :alt="`${u.name} avatar`"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                    />
                                    <User
                                        v-else
                                        class="h-6 w-6 text-muted-foreground/40"
                                    />
                                </Link>
                                <div
                                    v-else
                                    :class="[
                                        'lb-avatar lb-blurred h-12 w-12 ring-2 sm:h-14 sm:w-14',
                                        getGroupRankMeta(selectedTiedGroup)
                                            ?.ring,
                                    ]"
                                >
                                    <User
                                        class="h-6 w-6 text-muted-foreground/40"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-primary/[0.03] backdrop-blur-[2px]"
                                    >
                                        <EyeOff
                                            class="h-4 w-4 text-muted-foreground/40"
                                        />
                                    </div>
                                </div>

                                <!-- Current User Badge -->
                                <span
                                    v-if="u.isCurrentUser"
                                    class="py-0.2 absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-[#D97757] px-1.5 text-[8px] font-bold text-white shadow-sm ring-1 ring-background"
                                >
                                    YOU
                                </span>
                            </div>

                            <!-- Name below circle profile -->
                            <Link
                                v-if="!u.blurred"
                                :href="`/u/${u.id}`"
                                class="mt-2 line-clamp-2 w-full text-center text-[11px] leading-tight font-semibold break-words transition-colors hover:text-[#D97757] sm:text-xs"
                                :title="u.name"
                            >
                                {{ u.name }}
                            </Link>
                            <span
                                v-else
                                class="lb-blurred-text mt-2 line-clamp-1 w-full text-center text-[11px] leading-tight font-semibold sm:text-xs"
                                @contextmenu.prevent
                            >
                                {{ BLURRED_NAME }}
                            </span>

                            <!-- Streak / XP Info -->
                            <div
                                class="mt-1 flex items-center gap-0.5 text-[10px] text-muted-foreground"
                            >
                                <Flame class="h-2.5 w-2.5 text-[#D97757]" />
                                <span>{{ u.streak }}d</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border/20 px-5 py-3.5 sm:px-6"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ selectedTiedGroup?.users.length }} players listed
                    </span>
                    <button
                        @click="isTiedModalOpen = false"
                        class="dash-btn px-5 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
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
    @apply border-transparent bg-[#D97757] text-white;
}
.lb-search {
    @apply rounded-full border border-border/50 bg-muted/40 text-[15px] font-normal transition-colors focus:border-[#D97757]/40 focus:ring-2 focus:ring-[#D97757]/20 focus:outline-none;
    min-height: 44px;
}
.lb-season-pill {
    @apply flex items-center gap-1.5 rounded-full border border-border/50 bg-card px-3 py-2 text-[13px] font-medium text-muted-foreground;
    min-height: 44px;
}
.lb-season-select {
    @apply rounded-full border border-border/50 bg-card px-3 py-2 text-[13px] font-medium text-muted-foreground transition-colors focus:border-[#D97757]/40 focus:ring-2 focus:ring-[#D97757]/20 focus:outline-none;
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
    overflow: hidden;
}
.lb-podium-card:hover {
    @apply bg-muted/30;
}
.lb-podium-card--champ {
    @apply border-[#D97757]/20;
}
.lb-podium-card--you {
    @apply ring-1 ring-[#D97757]/30;
}
@media (min-width: 640px) {
    .lb-podium-card--champ {
        padding-top: 2.25rem;
        padding-bottom: 2.25rem;
        min-height: 370px;
    }
    .lb-podium-card:not(.lb-podium-card--champ) {
        min-height: 290px;
    }
}
@media (max-width: 639px) {
    .lb-podium-card {
        padding: 1rem;
    }
    .lb-podium-card--champ {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
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
    @apply border-[#D97757]/20 bg-[#D97757]/[0.04];
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
.lb-blurred-text::selection {
    background: transparent;
}
.lb-blurred-text::-moz-selection {
    background: transparent;
}

/* ── Mobile-specific fixes ── */
@media (max-width: 639px) {
    .lb-podium {
        gap: 0.625rem;
    }

    .lb-rank-row {
        padding: 0.875rem;
    }

    /* Compact the action buttons row on narrow screens */
    .lb-season-select,
    .lb-blur-toggle,
    .lb-season-pill {
        min-height: 38px;
        font-size: 0.75rem;
        padding: 0.375rem 0.625rem;
    }
}
</style>
