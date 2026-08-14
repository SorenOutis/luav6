<script setup lang="ts">
import { Head, usePage, usePoll, router } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ChevronDown, ChevronUp, Trophy } from 'lucide-vue-next';
import {
    onMounted,
    onBeforeUnmount,
    reactive,
    ref,
    computed,
    watch,
} from 'vue';

const dashboardContainer = ref<HTMLElement | null>(null);
const { isMobile, isDesktop, prefersReducedMotion, isLowEndDevice } =
    useMobile();

import DailyRewardCard from '@/components/dashboard/DailyRewardCard.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import DashboardSkeleton from '@/components/dashboard/DashboardSkeleton.vue';
import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';
import SeasonProgressBand from '@/components/dashboard/SeasonProgressBand.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';
import TodayStrip from '@/components/dashboard/TodayStrip.vue';
import type { NextUpItem } from '@/components/dashboard/TodayStrip.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import SectionSelectionModal from '@/components/SectionSelectionModal.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';
import { useLoader } from '@/composables/useLoader';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPageMountedBefore } from '@/lib/page-mount-state';
import { logout } from '@/routes';
import { index as assignmentsIndex } from '@/routes/assignments';
import { show as examsShow } from '@/routes/exams';

import type { BreadcrumbItem } from '@/types';

const { isVisible: isLoaderVisible } = useLoader();

const breadcrumbs: BreadcrumbItem[] = [];

const isRefreshing = ref(false);

const POLL_PROPS = [
    'userStats',
    'notifications',
    'loginDates',
    'announcements',
    'assignments',
    'upcomingExams',
    'sectionLeaderboards',
    'activeSeason',
    // Keep the daily-claim status + XP history fresh so the level card's
    // "claimed today?" banner and history reflect a claim immediately.
    'claimXp',
    'xpHistory',
];
// Dashboard data is intentionally refreshed less often than interaction-heavy
// pages. This avoids repeatedly rebuilding the leaderboard and sidebar on
// lower-end devices while keeping progress reasonably current.
const POLL_INTERVAL_MS = 30000;

const { stop: stopPoll, start: startPoll } = usePoll(
    POLL_INTERVAL_MS,
    {
        only: POLL_PROPS,
        onStart: () => {
            isRefreshing.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
        },
    },
    { autoStart: false },
);

const isPollingActive = ref(false);
const resumePolling = () => {
    if (isPollingActive.value) return;
    startPoll();
    isPollingActive.value = true;
};
const pausePolling = () => {
    if (!isPollingActive.value) return;
    stopPoll();
    isPollingActive.value = false;
};

const manualRefresh = () => {
    isRefreshing.value = true;
    router.reload({
        only: POLL_PROPS,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const page = usePage();
const userName = computed(() => page.props.auth.user?.name || 'User');
const userAvatar = computed(() => page.props.auth.user?.avatar);
const userProfileHref = computed(() => {
    const id = page.props.auth.user?.id;

    return id ? `/u/${id}` : undefined;
});
const isBanned = computed(() => Boolean(page.props.auth.user?.is_banned));
const banReason = computed(() => page.props.auth.user?.ban_reason || '');
const bannedAt = computed(() => {
    const value = page.props.auth.user?.banned_at;
    if (!value) return '';

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
});
const showBanModal = ref(false);

// Smarter, context-aware greeting
const personalizedGreeting = computed(() => {
    const hour = new Date().getHours();
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;

    // Time-based base greeting
    let greeting = 'Good Evening';
    if (hour >= 0 && hour < 4) greeting = 'Late night session';
    else if (hour >= 4 && hour < 7) greeting = 'Early bird vibes';
    else if (hour >= 7 && hour < 12) greeting = 'Good Morning';
    else if (hour >= 12 && hour < 17) greeting = 'Good Afternoon';
    else if (hour >= 17 && hour < 21) greeting = 'Good Evening';
    else greeting = 'Winding down';

    // Add flair based on state
    if (overdue > 0) return `${greeting}, let's catch up`;
    if (streak >= 7) return `${greeting}, Legend`;
    if (streak > 0) return `${greeting}, keep it up`;

    return greeting;
});

const greetingTheme = computed(() => {
    const hour = new Date().getHours();
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;

    if (overdue > 0) return 'bg-[#CB7676]';
    if (streak >= 7) return 'bg-[#D97757]';
    if (streak > 0) return 'bg-[#4D9375]';

    if (hour >= 0 && hour < 4) return 'bg-[#D97757]';
    if (hour >= 4 && hour < 7) return 'bg-[#D97757]';
    if (hour >= 7 && hour < 12) return 'bg-[#D97757]';
    if (hour >= 12 && hour < 17) return 'bg-[#D97757]';
    if (hour >= 17 && hour < 21) return 'bg-[#D97757]';
    return 'bg-[#D97757]';
});

const statusColor = computed(() => {
    const overdue = todaySummary.value.overdueCount;
    const streak = props.userStats.streak;

    if (overdue > 0) return 'bg-[#CB7676]';
    if (streak >= 7) return 'bg-[#D97757]';
    if (streak > 0) return 'bg-[#4D9375]';

    return 'bg-[#4D9375]';
});

// Smarter status subtext for the hero
const smarterStatus = computed(() => {
    const xpRemaining =
        props.userStats.maxXPForLevel - props.userStats.currentXP;
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;
    const dueToday = todaySummary.value.dueTodayCount;

    if (overdue > 0)
        return `You have ${overdue} ${overdue === 1 ? 'task' : 'tasks'} that need attention.`;
    if (xpRemaining < 200)
        return `Only ${xpRemaining} XP until Level ${props.userStats.level + 1}.`;
    if (streak >= 3) return `A ${streak}-day streak. Keep the momentum going.`;
    if (dueToday > 0)
        return `${dueToday} ${dueToday === 1 ? 'item' : 'items'} on your schedule today.`;

    return `You're all caught up for now.`;
});

const isBooted = ref(false);

interface Assignment {
    id: number;
    title: string;
    description: string;
    dueDate: string;
    dueAtIso?: string | null;
    isOverdue: boolean;
    submitted: boolean;
    status: string;
    grade: string | null;
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
    users: LeaderboardUser[];
    userRank: number;
    totalPlayers: number;
}

interface Season {
    id: number;
    name: string;
    startDate?: string | null;
    endDate?: string | null;
}

interface Announcement {
    id: number;
    title: string;
    description: string;
    link?: string;
    sectionName?: string | null;
    createdAt?: string | null;
}

interface Exam {
    id: number;
    title: string;
    description: string;
    exam_date: string;
    exam_date_iso?: string | null;
    duration_minutes: number;
    status: string;
    parts_count: number;
    submitted_parts: number;
    is_completed: boolean;
}

const props = defineProps<{
    claimXp: {
        canClaim: boolean;
        amount: number;
        nextClaimAt: string | null;
        lastClaimedAt?: string | null;
        showPrompt?: boolean;
    };
    userStats: {
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
    };
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
    announcements: Announcement[];
    assignments: Assignment[];
    upcomingExams: Exam[];
    sectionLeaderboards: LeaderboardData[];
    activeSeason: Season | null;
    sectionName?: string | null;
    availableSeasons?: Season[];
}>();

const userStats = computed(() => props.userStats);

// The daily XP prompt must wait until the section-selection flow is done:
// new users (no section) see the section modal first, then the claim prompt.
// Declared above claimXpForPrompt so the computed can reference it safely.
const claimPromptReady = ref(Boolean(props.sectionName));

// Gate the auto-prompt behind the section flow (see claimPromptReady above).
const claimXpForPrompt = computed(() => ({
    ...props.claimXp,
    showPrompt: claimPromptReady.value && Boolean(props.claimXp.showPrompt),
}));

const dismissedAnnouncementIds = reactive(new Set<number>());
const announcements = computed(() =>
    props.announcements.filter((a) => !dismissedAnnouncementIds.has(a.id)),
);
const sectionLeaderboards = computed(() => props.sectionLeaderboards);

const streak = computed(() => ({
    loginDates: props.loginDates ?? [],
}));

// Unified list of items with due-dates for "Today" + "Next Up"
interface DueItem {
    kind: 'exam' | 'assignment';
    title: string;
    dueAt: Date;
    href: string;
    meta?: string;
    isCompleted: boolean;
    isOverdue: boolean;
}

const dueItems = computed<DueItem[]>(() => {
    const items: DueItem[] = [];

    for (const a of props.assignments ?? []) {
        if (!a.dueAtIso) continue;
        const dueAt = new Date(a.dueAtIso);
        if (Number.isNaN(dueAt.getTime())) continue;
        items.push({
            kind: 'assignment',
            title: a.title,
            dueAt,
            href: assignmentsIndex().url,
            meta: a.description,
            isCompleted: a.submitted,
            isOverdue: a.isOverdue,
        });
    }

    for (const e of props.upcomingExams ?? []) {
        if (!e.exam_date_iso) continue;
        const dueAt = new Date(e.exam_date_iso);
        if (Number.isNaN(dueAt.getTime())) continue;
        items.push({
            kind: 'exam',
            title: e.title,
            dueAt,
            href: examsShow(e.id).url,
            meta: `${e.submitted_parts}/${e.parts_count} parts · ${e.duration_minutes}m`,
            isCompleted: e.is_completed,
            isOverdue: dueAt.getTime() < Date.now() && !e.is_completed,
        });
    }

    return items;
});

const todaySummary = computed(() => {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const endOfDay = start.getTime() + 86_400_000;
    const in24h = Date.now() + 86_400_000;

    let dueTodayCount = 0;
    let overdueCount = 0;
    let upcoming24hCount = 0;

    for (const item of dueItems.value) {
        if (item.isCompleted) continue;
        const t = item.dueAt.getTime();
        if (t < Date.now()) {
            overdueCount += 1;
            continue;
        }
        if (t >= start.getTime() && t < endOfDay) dueTodayCount += 1;
        if (t < in24h) upcoming24hCount += 1;
    }

    return { dueTodayCount, overdueCount, upcoming24hCount };
});

// The single most urgent item: overdue first, then soonest due date.
const nextItem = computed<NextUpItem | null>(() => {
    const urgent = dueItems.value
        .filter((i) => !i.isCompleted)
        .sort((a, b) => {
            if (a.isOverdue !== b.isOverdue) return a.isOverdue ? -1 : 1;
            return a.dueAt.getTime() - b.dueAt.getTime();
        })[0];

    if (!urgent) return null;

    return {
        kind: urgent.kind,
        title: urgent.title,
        dueAt: urgent.dueAt.toISOString(),
        href: urgent.href,
        meta: urgent.meta,
    };
});

const primaryLeaderboard = computed(() => sectionLeaderboards.value[0] ?? null);

const seasonalXpTarget = computed(() => {
    // Rough target: fill the currently reached level's XP band; can be tuned later
    return props.userStats?.maxXPForLevel ?? 100;
});

let gsapCtx: gsap.Context | null = null;

const showSectionModal = ref(false);
const isLeaderboardExpanded = ref(false);

watch(
    () => props.sectionName,
    (newSection) => {
        if (newSection) {
            showSectionModal.value = false;
            claimPromptReady.value = true;
        }
    },
    { immediate: true },
);

const handleVisibilityChange = () => {
    if (document.hidden) {
        pausePolling();
    } else if (!showBanModal.value) {
        resumePolling();
        // Fire an immediate sync so stale data updates right away
        manualRefresh();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);

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

    // Kick off polling (respect current tab visibility)
    if (!document.hidden) {
        resumePolling();
    }

    // Skip the refresh on the session's very first mount: the server just
    // rendered fresh props, so a reload would be a wasted request. Every
    // later remount may be a stale restore (sidebar prefetch cache / history
    // state / back-nav) — e.g. after submitting an exam the upcoming-exam
    // cards would keep showing the pre-submission state — so sync immediately
    // instead of waiting for the next poll tick. Skipped while the tab is
    // hidden — the visibility handler refreshes as soon as it becomes visible.
    if (hasPageMountedBefore('dashboard') && !document.hidden) {
        manualRefresh();
    }

    // If user has no sections, show the selection modal immediately but after initial dashboard animations start
    if (!props.sectionName) {
        setTimeout(() => {
            showSectionModal.value = true;
        }, 800);
    }

    if (isBanned.value) {
        pausePolling();
        setTimeout(() => {
            showBanModal.value = true;
        }, 450);
    }

    if (!dashboardContainer.value) return;

    gsapCtx = gsap.context(() => {
        if (
            prefersReducedMotion.value ||
            isMobile.value ||
            isLowEndDevice.value
        ) {
            gsap.set(
                [
                    '.dashboard-hero',
                    '.dashboard-focus',
                    '.dashboard-reward',
                    '.dashboard-progress',
                    '.dashboard-leaderboard',
                    '.dashboard-main-grid',
                ],
                { opacity: 1, y: 0, scale: 1, clearProps: 'transform' },
            );
            return;
        }
    }, dashboardContainer.value);
});

// Pause/resume polling + animations in response to ban modal
watch(showBanModal, (open) => {
    if (open) {
        pausePolling();
        gsap.globalTimeline.pause();
    } else {
        gsap.globalTimeline.resume();
        if (!document.hidden) resumePolling();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    pausePolling();
    if (gsapCtx) {
        gsapCtx.revert();
    }
});

const handleLogout = () => {
    sessionStorage.setItem('logged_out', 'true');
    router.post(logout());
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="dashboardContainer"
            class="dashboard-ui relative flex h-full w-full max-w-full min-w-0 flex-1 flex-col gap-3 overflow-hidden bg-background p-3 sm:gap-5 sm:p-6 md:gap-7 md:p-8"
            :class="{
                'pointer-events-none blur-sm select-none': showBanModal,
            }"
        >
            <!-- Skeleton loader (shown while booting) -->
            <DashboardSkeleton v-if="!isBooted" />

            <!-- Real content (shown after booted) -->
            <template v-if="isBooted">
                <!-- Hero Banner Section -->
                <Motion
                    :initial="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? false
                            : { opacity: 0, y: 30 }
                    "
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? { duration: 0 }
                            : {
                                  duration: 0.7,
                                  easing: [0.16, 1, 0.3, 1],
                                  delay: 0.05,
                              }
                    "
                    class="relative space-y-3 sm:space-y-6"
                >
                    <DashboardHero
                        class="dashboard-hero"
                        :user-name="userName"
                        :user-avatar="userAvatar"
                        :profile-href="userProfileHref"
                        :user-stats="userStats"
                        :announcements="announcements"
                        :time-based-greeting="personalizedGreeting"
                        :greeting-theme="greetingTheme"
                        :status-color="statusColor"
                        :smarter-status="smarterStatus"
                        :is-refreshing="isRefreshing"
                        @close-announcement="
                            (id: number) => dismissedAnnouncementIds.add(id)
                        "
                        @refresh="manualRefresh"
                        @open-section-modal="showSectionModal = true"
                    />
                </Motion>

                <!-- Focus Strip: What's due / next up -->
                <Motion
                    :initial="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? false
                            : { opacity: 0, y: 20 }
                    "
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? { duration: 0 }
                            : {
                                  duration: 0.7,
                                  easing: [0.16, 1, 0.3, 1],
                                  delay: 0.08,
                              }
                    "
                >
                    <TodayStrip
                        class="dashboard-focus"
                        :due-today-count="todaySummary.dueTodayCount"
                        :overdue-count="todaySummary.overdueCount"
                        :upcoming-24h-count="todaySummary.upcoming24hCount"
                        :next-item="nextItem"
                    />
                </Motion>

                <!-- Daily Reward (Claim XP) -->
                <Motion
                    :initial="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? false
                            : { opacity: 0, y: 20 }
                    "
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? { duration: 0 }
                            : {
                                  duration: 0.7,
                                  easing: [0.16, 1, 0.3, 1],
                                  delay: 0.1,
                              }
                    "
                >
                    <DailyRewardCard
                        class="dashboard-reward"
                        :claim-xp="claimXpForPrompt"
                        :streak="userStats.streak"
                        @claimed="manualRefresh"
                    />
                </Motion>

                <!-- Progress Row: Level / Streak / Season -->
                <Motion
                    :initial="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? false
                            : { opacity: 0, y: 20 }
                    "
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? { duration: 0 }
                            : {
                                  duration: 0.7,
                                  easing: [0.16, 1, 0.3, 1],
                                  delay: 0.15,
                              }
                    "
                    class="dashboard-progress grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-2 lg:grid-cols-4"
                >
                    <LevelProgressCard
                        class="col-span-2"
                        :user-stats="userStats"
                        :breakdown="props.statsBreakdown?.xp ?? []"
                        :xp-history="props.xpHistory ?? []"
                        :claim-xp="claimXpForPrompt"
                    />
                    <StreakCard
                        :current-streak="userStats.streak"
                        :longest-streak="userStats.longestStreak"
                        :login-dates="streak.loginDates"
                    />
                    <SeasonProgressBand
                        :name="activeSeason?.name ?? null"
                        :start-date="activeSeason?.startDate ?? null"
                        :end-date="activeSeason?.endDate ?? null"
                        :xp-earned="userStats.currentXP"
                        :xp-target="seasonalXpTarget"
                    />
                </Motion>

                <!-- Main Content Grid -->
                <Motion
                    :initial="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? false
                            : { opacity: 0, y: 40 }
                    "
                    :in-view="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? undefined
                            : isBooted
                              ? { opacity: 1, y: 0 }
                              : {}
                    "
                    :in-view-options="{ once: true, margin: '-50px' }"
                    :transition="
                        isMobile || prefersReducedMotion || isLowEndDevice
                            ? { duration: 0 }
                            : { duration: 0.8, easing: [0.16, 1, 0.3, 1] }
                    "
                    class="dashboard-main-grid grid min-w-0 grid-cols-1 items-start gap-3 sm:gap-8 lg:grid-cols-3"
                >
                    <!-- Main Section: Leaderboard -->
                    <div class="min-w-0 space-y-4 sm:space-y-8 lg:col-span-2">
                        <!-- Mobile: Collapsible Leaderboard -->
                        <div v-if="!isDesktop" class="lg:hidden">
                            <button
                                @click="
                                    isLeaderboardExpanded =
                                        !isLeaderboardExpanded
                                "
                                :aria-expanded="isLeaderboardExpanded"
                                :aria-controls="'mobile-leaderboard-panel'"
                                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-border/50 bg-card px-3 py-2.5 text-left transition-colors active:bg-muted/50 sm:rounded-[1.25rem] sm:px-4 sm:py-3.5"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Trophy class="h-4 w-4 text-[#D97757]" />
                                    <div class="min-w-0">
                                        <span
                                            class="text-[15px] font-semibold tracking-tight text-foreground"
                                            >Leaderboard</span
                                        >
                                        <p
                                            v-if="primaryLeaderboard"
                                            class="truncate text-[13px] text-muted-foreground"
                                        >
                                            {{ primaryLeaderboard.sectionName }}
                                            ·
                                            {{
                                                primaryLeaderboard.totalPlayers
                                            }}
                                            players
                                        </p>
                                    </div>
                                </div>
                                <span
                                    v-if="primaryLeaderboard"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-[#D97757]/10 px-2.5 py-1 text-[13px] font-semibold text-[#D97757] tabular-nums"
                                >
                                    #{{ primaryLeaderboard.userRank }}
                                </span>
                                <component
                                    :is="
                                        isLeaderboardExpanded
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-300"
                                />
                            </button>
                            <div
                                v-show="isLeaderboardExpanded"
                                id="mobile-leaderboard-panel"
                                class="mt-3"
                            >
                                <ImprovedLeaderboard
                                    class="dashboard-leaderboard"
                                    :section-leaderboards="sectionLeaderboards"
                                    :active-season-name="activeSeason?.name"
                                    :available-seasons="
                                        props.availableSeasons ?? []
                                    "
                                    show-view-button
                                />
                            </div>
                        </div>

                        <!-- Desktop: Full Leaderboard -->
                        <div v-else class="hidden lg:block">
                            <ImprovedLeaderboard
                                class="dashboard-leaderboard"
                                :section-leaderboards="sectionLeaderboards"
                                :active-season-name="activeSeason?.name"
                                :available-seasons="
                                    props.availableSeasons ?? []
                                "
                                show-view-button
                            />
                        </div>
                    </div>

                    <!-- Sidebar - Activity Pulse -->
                    <div
                        class="min-w-0 space-y-6 lg:sticky lg:top-24 lg:self-start"
                    >
                        <!-- Streak Heatmap Card (compact) -->
                        <section
                            class="surface-card w-full min-w-0 p-4 sm:p-5"
                            aria-label="Activity"
                        >
                            <div class="mb-4 min-w-0 sm:mb-5">
                                <h3
                                    class="dash-title text-[17px] text-foreground sm:text-lg"
                                >
                                    Activity
                                </h3>
                                <p
                                    class="mt-0.5 text-[13px] text-muted-foreground"
                                >
                                    Your last 4 weeks at a glance.
                                </p>
                            </div>
                            <StreakHeatmap :login-dates="streak.loginDates" />
                        </section>
                    </div>
                </Motion>
            </template>
        </div>

        <SectionSelectionModal
            :show="showSectionModal"
            @close="
                showSectionModal = false;
                claimPromptReady = true;
            "
        />

        <div
            v-if="showBanModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-md"
        >
            <div
                class="relative w-full max-w-lg overflow-hidden rounded-[1.75rem] border border-border/60 bg-card shadow-2xl"
            >
                <div class="p-6 sm:p-8">
                    <div>
                        <p class="text-[13px] font-medium text-[#CB7676]">
                            Access restricted
                        </p>
                        <h2
                            class="mt-1 text-[28px] font-semibold tracking-tight text-foreground"
                        >
                            Account suspended
                        </h2>
                        <p
                            class="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            Your account is currently banned from using this
                            system. Please contact your administrator to request
                            a review.
                        </p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div
                            v-if="banReason"
                            class="rounded-xl border border-border/80 bg-gradient-to-br from-muted/60 to-muted/30 p-4"
                        >
                            <p
                                class="text-[13px] font-medium text-muted-foreground"
                            >
                                Ban reason
                            </p>
                            <p class="mt-1 text-sm text-foreground">
                                {{ banReason }}
                            </p>
                        </div>
                        <div
                            v-if="bannedAt"
                            class="inline-flex items-center rounded-full border border-border/80 bg-muted/40 px-3 py-1 text-xs text-muted-foreground"
                        >
                            Banned on: {{ bannedAt }}
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-end border-t border-border/70 bg-muted/20 p-4 sm:p-5"
                >
                    <button
                        type="button"
                        class="dash-btn inline-flex items-center justify-center bg-destructive px-5 text-[15px] text-destructive-foreground transition-colors hover:bg-destructive/90"
                        @click="handleLogout"
                    >
                        Log out
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Global Customizations for this Page */
.animate-section {
    /* Handled by GSAP onMounted */
    will-change: transform, opacity;
}
</style>
