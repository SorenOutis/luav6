<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bell,
    BookOpenCheck,
    CalendarClock,
    ChevronDown,
    RefreshCw,
    Sparkles,
    Trophy,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';
import type { NextUpItem } from '@/components/dashboard/TodayStrip.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';

interface Announcement {
    id: number;
    title: string;
    description: string;
    link?: string;
    sectionName?: string | null;
    createdAt?: string | null;
}

interface UserStats {
    totalXP: number;
    level: number;
    currentXP: number;
    maxXPForLevel: number;
    rank: string;
    rankNumber: number;
    totalPlayers: number;
    achievements: number;
    points: number;
    streak: number;
    longestStreak: number;
    joinedAt: string;
}

interface Season {
    id: number;
    name: string;
    startDate?: string | null;
    endDate?: string | null;
}

interface LeaderboardUser {
    id: number;
    name: string;
    avatar?: string;
    xp: number;
    level: number;
    xpProgress: number;
    streak: number;
    joinedAt: string;
    weeklyXp: number;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser: boolean;
}

interface LeaderboardData {
    sectionId: number;
    sectionName: string;
    workspaceId?: number | null;
    workspaceName?: string | null;
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface ClaimXp {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    baseXp?: number;
    nextClaimAt: string | null;
    lastClaimedAt?: string | null;
    showPrompt?: boolean;
}

const props = withDefaults(
    defineProps<{
        userName: string;
        userAvatar?: string;
        profileHref?: string;
        userStats: UserStats;
        announcements: Announcement[];
        timeBasedGreeting: string;
        greetingTheme: string;
        statusColor: string;
        smarterStatus: string;
        isRefreshing: boolean;
        dueTodayCount: number;
        overdueCount: number;
        upcoming24hCount: number;
        nextItem: NextUpItem | null;
        claimXp: ClaimXp;
        bonusXp?: ClaimXp;
        statsBreakdown?: {
            xp: { label: string; amount: number; count: number }[];
            points: { label: string; amount: number; count: number }[];
        };
        xpHistory?: {
            id: number;
            reason: string;
            description: string | null;
            amount: number;
            createdAt: string;
            isClaim: boolean;
        }[];
        loginDates?: string[];
        sectionLeaderboards: LeaderboardData[];
        activeSeason?: Season | null;
        availableSeasons?: Season[];
        primaryLeaderboard?: {
            sectionName: string;
            totalPlayers: number;
            userRank: number;
        } | null;
        leaderboardExpanded: boolean;
    }>(),
    {
        userAvatar: undefined,
        profileHref: undefined,
        bonusXp: undefined,
        statsBreakdown: undefined,
        xpHistory: undefined,
        loginDates: () => [],
        activeSeason: null,
        availableSeasons: () => [],
        primaryLeaderboard: null,
    },
);

const emit = defineEmits<{
    closeAnnouncement: [id: number];
    refresh: [];
    openSectionModal: [];
    claimed: [];
    toggleLeaderboard: [];
}>();

const firstAnnouncement = computed(() => props.announcements[0] ?? null);
const xpProgress = computed(() => {
    if (props.userStats.maxXPForLevel <= 0) return 0;

    return Math.min(
        100,
        Math.round(
            (props.userStats.currentXP / props.userStats.maxXPForLevel) * 100,
        ),
    );
});

const formatNextDue = (item: NextUpItem) => {
    const date = new Date(item.dueAt);
    if (Number.isNaN(date.getTime())) return 'Upcoming';

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

const itemTypeLabel = (item: NextUpItem) =>
    item.kind === 'exam' ? 'Next exam' : 'Next assignment';

const seasonProgress = computed(() => {
    const start = props.activeSeason?.startDate
        ? new Date(props.activeSeason.startDate).getTime()
        : Number.NaN;
    const end = props.activeSeason?.endDate
        ? new Date(props.activeSeason.endDate).getTime()
        : Number.NaN;

    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
        return null;
    }

    return Math.min(
        100,
        Math.max(0, Math.round(((Date.now() - start) / (end - start)) * 100)),
    );
});

const seasonDaysLeft = computed(() => {
    const end = props.activeSeason?.endDate
        ? new Date(props.activeSeason.endDate).getTime()
        : Number.NaN;

    if (!Number.isFinite(end)) return null;

    return Math.max(0, Math.ceil((end - Date.now()) / 86_400_000));
});

const seasonDateLabel = computed(() => {
    const start = props.activeSeason?.startDate
        ? new Date(props.activeSeason.startDate)
        : null;
    const end = props.activeSeason?.endDate
        ? new Date(props.activeSeason.endDate)
        : null;

    if (
        !start ||
        !end ||
        Number.isNaN(start.getTime()) ||
        Number.isNaN(end.getTime())
    ) {
        return '';
    }

    const format = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    });

    return `${format.format(start)} – ${format.format(end)}`;
});
</script>

<template>
    <div class="mobile-dashboard-composition md:hidden">
        <section class="mobile-dashboard-greeting" data-tour="dashboard-hero">
            <div class="mobile-dashboard-greeting__topline">
                <span class="mobile-dashboard-kicker">Your learning space</span>
                <button
                    type="button"
                    class="mobile-dashboard-icon-button"
                    aria-label="Refresh dashboard"
                    :disabled="isRefreshing"
                    @click="emit('refresh')"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': isRefreshing }"
                    />
                </button>
            </div>
            <div class="mobile-dashboard-greeting__body">
                <Link
                    v-if="profileHref"
                    :href="profileHref"
                    class="mobile-dashboard-avatar"
                    aria-label="Open your profile"
                >
                    <img
                        v-if="userAvatar"
                        :src="userAvatar"
                        :alt="userName"
                        class="h-full w-full object-cover"
                    />
                    <span v-else>{{ userName.slice(0, 1).toUpperCase() }}</span>
                </Link>
                <div v-else class="mobile-dashboard-avatar">
                    <img
                        v-if="userAvatar"
                        :src="userAvatar"
                        :alt="userName"
                        class="h-full w-full object-cover"
                    />
                    <span v-else>{{ userName.slice(0, 1).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="mobile-dashboard-eyebrow">
                        {{ timeBasedGreeting }}
                    </p>
                    <h1 class="mobile-dashboard-title truncate">
                        {{ userName }}
                    </h1>
                    <p class="mobile-dashboard-status">
                        <span
                            class="mobile-dashboard-status-dot"
                            :class="statusColor"
                        />
                        {{ smarterStatus }}
                    </p>
                </div>
                <button
                    type="button"
                    class="mobile-dashboard-bell"
                    aria-label="Open notifications"
                    @click="emit('refresh')"
                >
                    <Bell class="h-5 w-5" />
                </button>
            </div>
        </section>

        <section
            v-if="firstAnnouncement"
            class="mobile-dashboard-announcement"
            aria-label="Announcement"
        >
            <div class="mobile-dashboard-announcement__icon">
                <Sparkles class="h-4 w-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="mobile-dashboard-card-kicker">
                    {{ firstAnnouncement.sectionName || 'Announcement' }}
                </p>
                <h2 class="mobile-dashboard-card-title truncate">
                    {{ firstAnnouncement.title }}
                </h2>
                <p class="mobile-dashboard-card-copy line-clamp-2">
                    {{ firstAnnouncement.description }}
                </p>
                <a
                    v-if="firstAnnouncement.link"
                    :href="firstAnnouncement.link"
                    class="mobile-dashboard-inline-link"
                >
                    Read announcement <ArrowRight class="h-3.5 w-3.5" />
                </a>
            </div>
            <button
                type="button"
                class="mobile-dashboard-dismiss"
                aria-label="Dismiss announcement"
                @click="emit('closeAnnouncement', firstAnnouncement.id)"
            >
                <X class="h-4 w-4" />
            </button>
        </section>

        <section class="mobile-dashboard-today" data-tour="dashboard-today">
            <div class="mobile-dashboard-section-heading">
                <div>
                    <span class="mobile-dashboard-kicker">Your snapshot</span>
                    <h2 class="mobile-dashboard-section-title">
                        Today at a glance
                    </h2>
                </div>
                <CalendarClock class="h-5 w-5 text-muted-foreground" />
            </div>
            <div class="mobile-dashboard-metric-grid">
                <div class="mobile-dashboard-metric">
                    <span class="mobile-dashboard-metric__value">{{
                        dueTodayCount
                    }}</span>
                    <span class="mobile-dashboard-metric__label"
                        >Due today</span
                    >
                </div>
                <div
                    class="mobile-dashboard-metric"
                    :class="{ 'is-alert': overdueCount > 0 }"
                >
                    <span class="mobile-dashboard-metric__value">{{
                        overdueCount
                    }}</span>
                    <span class="mobile-dashboard-metric__label">Overdue</span>
                </div>
                <div class="mobile-dashboard-metric">
                    <span class="mobile-dashboard-metric__value">{{
                        upcoming24hCount
                    }}</span>
                    <span class="mobile-dashboard-metric__label">Next 24h</span>
                </div>
            </div>
            <Link
                v-if="nextItem"
                :href="nextItem.href"
                class="mobile-dashboard-next-item"
            >
                <span class="mobile-dashboard-next-item__icon">
                    <BookOpenCheck class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="mobile-dashboard-card-kicker">{{
                        itemTypeLabel(nextItem)
                    }}</span>
                    <strong
                        class="mobile-dashboard-next-item__title truncate"
                        >{{ nextItem.title }}</strong
                    >
                    <span class="mobile-dashboard-next-item__meta">{{
                        formatNextDue(nextItem)
                    }}</span>
                </span>
                <ArrowRight class="h-4 w-4 shrink-0 text-muted-foreground" />
            </Link>
            <div v-else class="mobile-dashboard-empty-next">
                <Sparkles class="h-4 w-4" />
                <span>You are all caught up for now.</span>
            </div>
        </section>

        <section
            class="mobile-dashboard-reward-grid"
            aria-label="Daily reward and current streak"
        >
            <div
                class="mobile-dashboard-reward"
                data-tour="dashboard-daily-reward"
            >
                <div class="mobile-dashboard-reward__icon">
                    <Zap class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <span class="mobile-dashboard-card-kicker"
                        >Daily reward</span
                    >
                    <strong class="mobile-dashboard-reward__value"
                        >+{{ claimXp.amount }} XP</strong
                    >
                    <span class="mobile-dashboard-reward__copy">
                        {{
                            claimXp.canClaim
                                ? 'Ready to claim'
                                : 'Come back tomorrow'
                        }}
                    </span>
                </div>
            </div>
            <StreakCard
                class="mobile-dashboard-streak-summary"
                data-tour="dashboard-streak-card"
                :current-streak="userStats.streak"
                :longest-streak="userStats.longestStreak"
                :login-dates="loginDates ?? []"
            />
        </section>

        <section
            class="mobile-dashboard-progress-band"
            aria-label="Level and season progress"
        >
            <div class="mobile-dashboard-progress-band__level">
                <span class="mobile-dashboard-card-kicker">Level progress</span>
                <strong>Level {{ userStats.level }}</strong>
                <span
                    >{{ userStats.currentXP.toLocaleString() }} /
                    {{ userStats.maxXPForLevel.toLocaleString() }} XP</span
                >
                <div class="mobile-dashboard-progress-track" aria-hidden="true">
                    <span :style="{ width: `${xpProgress}%` }" />
                </div>
            </div>
            <span class="mobile-dashboard-progress-band__divider" />
            <div class="mobile-dashboard-progress-band__season">
                <span class="mobile-dashboard-card-kicker"
                    >Season progress</span
                >
                <strong>{{
                    seasonProgress === null ? '—' : `${seasonProgress}%`
                }}</strong>
                <span v-if="activeSeason">{{ seasonDaysLeft }} days left</span>
                <span v-else>No active season</span>
                <small v-if="seasonDateLabel">{{ seasonDateLabel }}</small>
            </div>
        </section>

        <details class="mobile-dashboard-progress-details" open>
            <summary>
                <span>XP history, claims, and details</span>
                <ChevronDown class="h-4 w-4" />
            </summary>
            <div class="mobile-dashboard-progress-card">
                <LevelProgressCard
                    data-tour="dashboard-level-card"
                    :user-stats="userStats"
                    :breakdown="statsBreakdown?.xp ?? []"
                    :xp-history="xpHistory ?? []"
                    :claim-xp="claimXp"
                    :bonus-xp="bonusXp"
                />
            </div>
        </details>

        <section
            class="mobile-dashboard-leaderboard"
            data-tour="dashboard-leaderboard"
        >
            <button
                type="button"
                class="mobile-dashboard-section-trigger"
                :aria-expanded="leaderboardExpanded"
                aria-controls="mobile-dashboard-leaderboard-panel"
                @click="emit('toggleLeaderboard')"
            >
                <span class="mobile-dashboard-section-trigger__icon"
                    ><Trophy class="h-4 w-4"
                /></span>
                <span class="min-w-0 flex-1 text-left">
                    <strong>Class leaderboard</strong>
                    <small v-if="primaryLeaderboard">
                        {{ primaryLeaderboard.sectionName }} ·
                        {{ primaryLeaderboard.totalPlayers }} students
                    </small>
                </span>
                <span
                    v-if="primaryLeaderboard"
                    class="mobile-dashboard-section-trigger__rank"
                >
                    #{{ primaryLeaderboard.userRank }}
                </span>
                <ChevronDown
                    class="h-4 w-4 shrink-0 transition-transform"
                    :class="{ 'rotate-180': leaderboardExpanded }"
                />
            </button>
            <div
                v-show="leaderboardExpanded"
                id="mobile-dashboard-leaderboard-panel"
                class="mobile-dashboard-leaderboard__panel"
            >
                <ImprovedLeaderboard
                    class="dashboard-leaderboard"
                    :section-leaderboards="sectionLeaderboards"
                    :active-season-name="activeSeason?.name"
                    :available-seasons="availableSeasons ?? []"
                    show-view-button
                />
            </div>
        </section>

        <section
            class="mobile-dashboard-activity"
            aria-label="Activity"
            data-tour="dashboard-activity"
        >
            <div class="mobile-dashboard-section-heading">
                <div>
                    <span class="mobile-dashboard-kicker"
                        >Your consistency</span
                    >
                    <h2 class="mobile-dashboard-section-title">Activity</h2>
                </div>
                <span class="mobile-dashboard-activity-badge"
                    >Last 4 weeks</span
                >
            </div>
            <StreakHeatmap :login-dates="loginDates ?? []" />
        </section>
    </div>
</template>
