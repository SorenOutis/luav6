<script setup lang="ts">
import {
    ArrowRight,
    ChevronDown,
    Plus,
    RefreshCw,
    Sparkles,
    Trophy,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';

import ClaimXpButton from '@/components/dashboard/ClaimXpButton.vue';
import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';
import TodayPanel from '@/components/dashboard/TodayPanel.vue';
import type { TodayTask } from '@/components/dashboard/TodayPanel.vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

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
        isRefreshing: boolean;
        todayTasks: TodayTask[];
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
        streakRestore?: {
            enabled: boolean;
            limit: number;
            used: number;
            remaining: number;
            costs: number[];
            nextCost: number;
            restoredDates: string[];
            resetsAt: string | null;
        } | null;
        sectionLeaderboards: LeaderboardData[];
        activeSeason?: Season | null;
        availableSeasons?: Season[];
        primaryLeaderboard?: {
            sectionName: string;
            totalPlayers: number;
            userRank: number;
        } | null;
        leaderboardExpanded: boolean;
        /** Shared section selection (owned by Dashboard) for the Top-3 band + rankings. */
        leaderboardSectionId?: number | null;
    }>(),
    {
        userAvatar: undefined,
        profileHref: undefined,
        bonusXp: undefined,
        statsBreakdown: undefined,
        xpHistory: undefined,
        loginDates: () => [],
        streakRestore: null,
        activeSeason: null,
        availableSeasons: () => [],
        primaryLeaderboard: null,
        todayTasks: () => [],
        leaderboardSectionId: null,
    },
);

const emit = defineEmits<{
    closeAnnouncement: [id: number];
    refresh: [];
    openSectionModal: [];
    claimed: [];
    toggleLeaderboard: [];
    'update:leaderboardSectionId': [id: number];
}>();

const firstAnnouncement = computed(() => props.announcements[0] ?? null);
const initials = computed(() => getInitials(props.userName));
const xpProgress = computed(() => {
    if (props.userStats.maxXPForLevel <= 0) return 0;

    return Math.min(
        100,
        Math.round(
            (props.userStats.currentXP / props.userStats.maxXPForLevel) * 100,
        ),
    );
});

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
    <div class="mobile-dashboard-composition">
        <section class="mobile-dashboard-greeting" data-tour="dashboard-hero">
            <div class="mobile-dashboard-greeting__topline">
                <span class="mobile-dashboard-kicker">Your learning space</span>
                <div class="mobile-dashboard-greeting__actions">
                    <button
                        type="button"
                        class="mobile-dashboard-join"
                        aria-label="Join section"
                        title="Join section"
                        @click="emit('openSectionModal')"
                    >
                        <Plus class="h-5 w-5" />
                    </button>
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
            </div>
            <div class="mobile-dashboard-greeting__body">
                <div class="mobile-dashboard-greeting__identity">
                    <Avatar class="h-11 w-11 shrink-0 sm:h-12 sm:w-12">
                        <AvatarImage
                            v-if="userAvatar"
                            :src="userAvatar"
                            :alt="`${userName} avatar`"
                        />
                        <AvatarFallback
                            class="bg-[#D97757]/15 text-sm font-semibold text-[#D97757]"
                        >
                            {{ initials }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="mobile-dashboard-eyebrow">
                            {{ timeBasedGreeting }}
                        </p>
                        <h1 class="mobile-dashboard-title truncate">
                            {{ userName }}
                        </h1>
                        <span
                            class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[12px] font-semibold text-[#D97757]"
                            :title="`Level ${userStats.level} · ${userStats.currentXP.toLocaleString()} / ${userStats.maxXPForLevel.toLocaleString()} XP`"
                        >
                            Lv {{ userStats.level }}
                            <span
                                class="h-1 w-16 overflow-hidden rounded-full bg-[#D97757]/20"
                            >
                                <span
                                    class="block h-full rounded-full bg-[#D97757]"
                                    :style="{ width: `${xpProgress}%` }"
                                ></span>
                            </span>
                        </span>
                    </div>
                </div>
                <FoxCompanion
                    class="mobile-dashboard-greeting__fox"
                    mascot="welcome"
                    :size="96"
                    :show-message="false"
                    label="Dashboard fox"
                />
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

        <!-- Podium band: top 3 of the active section -->
        <section
            class="surface-card w-full min-w-0 p-3"
            aria-label="Top 3 leaderboard"
            data-tour="dashboard-leaderboard-podium"
        >
            <ImprovedLeaderboard
                podium-only
                :section-leaderboards="sectionLeaderboards"
                :active-season-name="activeSeason?.name"
                :available-seasons="availableSeasons ?? []"
                :active-section-id="leaderboardSectionId"
                @update:active-section-id="
                    emit('update:leaderboardSectionId', $event)
                "
            >
                <template #band-action>
                    <button
                        type="button"
                        class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-full px-2 py-1 text-[13px] font-semibold text-[#D97757] transition-colors hover:bg-[#D97757]/10"
                        @click="emit('toggleLeaderboard')"
                    >
                        Full rankings
                    </button>
                </template>
            </ImprovedLeaderboard>
        </section>

        <section class="mobile-dashboard-today" data-tour="dashboard-today">
            <TodayPanel :tasks="todayTasks" compact />
        </section>

        <section
            class="mobile-dashboard-reward-grid"
            aria-label="Daily reward and current streak"
        >
            <div
                class="mobile-dashboard-reward"
                data-tour="dashboard-daily-reward"
            >
                <ClaimXpButton
                    :can-claim="claimXp.canClaim"
                    :amount="claimXp.amount"
                    :base-xp="claimXp.baseXp"
                    :next-claim-at="claimXp.nextClaimAt"
                    :streak="userStats.streak"
                    :show-prompt="false"
                    @claimed="emit('claimed')"
                />
            </div>
            <StreakCard
                class="mobile-dashboard-streak-summary"
                data-tour="dashboard-streak-card"
                :current-streak="userStats.streak"
                :longest-streak="userStats.longestStreak"
                :login-dates="loginDates ?? []"
                :user-xp="userStats.totalXP"
                :restore="streakRestore ?? null"
                compact
            />
        </section>

        <section
            class="mobile-dashboard-progress-band"
            aria-label="Level and season progress"
        >
            <div class="mobile-dashboard-progress-band__level">
                <div class="mobile-dashboard-progress-band__icon">
                    <TrendingUp class="h-4 w-4" />
                </div>
                <div class="mobile-dashboard-progress-band__content">
                    <span class="mobile-dashboard-card-kicker"
                        >Level progress</span
                    >
                    <strong>Level {{ userStats.level }}</strong>
                    <span
                        >{{ userStats.currentXP.toLocaleString() }} /
                        {{ userStats.maxXPForLevel.toLocaleString() }} XP</span
                    >
                    <div
                        class="mobile-dashboard-progress-track"
                        aria-hidden="true"
                    >
                        <span :style="{ width: `${xpProgress}%` }" />
                    </div>
                </div>
            </div>
            <span class="mobile-dashboard-progress-band__divider" />
            <div class="mobile-dashboard-progress-band__season">
                <div class="mobile-dashboard-progress-band__icon">
                    <Trophy class="h-4 w-4" />
                </div>
                <div class="mobile-dashboard-progress-band__content">
                    <span class="mobile-dashboard-card-kicker"
                        >Season progress</span
                    >
                    <strong>{{
                        seasonProgress === null ? '—' : `${seasonProgress}%`
                    }}</strong>
                    <span v-if="activeSeason"
                        >{{ seasonDaysLeft }} days left</span
                    >
                    <span v-else>No active season</span>
                    <small v-if="seasonDateLabel">{{ seasonDateLabel }}</small>
                </div>
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
                    hide-podium
                    :section-leaderboards="sectionLeaderboards"
                    :active-season-name="activeSeason?.name"
                    :available-seasons="availableSeasons ?? []"
                    :active-section-id="leaderboardSectionId"
                    @update:active-section-id="
                        emit('update:leaderboardSectionId', $event)
                    "
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
