<script setup lang="ts">
import { Head, usePage, usePoll, router } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import {
    onMounted,
    onBeforeUnmount,
    reactive,
    ref,
    computed,
    watch,
} from 'vue';

import CommandBar from '@/components/dashboard/CommandBar.vue';
import DashboardSkeleton from '@/components/dashboard/DashboardSkeleton.vue';
import MobileDashboard from '@/components/dashboard/MobileDashboard.vue';
import ProgressCard from '@/components/dashboard/ProgressCard.vue';
import TodayPanel from '@/components/dashboard/TodayPanel.vue';
import type { TodayTask } from '@/components/dashboard/TodayPanel.vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import SectionSelectionModal from '@/components/SectionSelectionModal.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';
import { useDashboardLayoutBreakpoint } from '@/composables/useBreakpoint';
import { useLoader } from '@/composables/useLoader';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import { getTourStatus } from '@/lib/onboarding';
import type { TourStep } from '@/lib/onboarding';
import { hasPageMountedBefore } from '@/lib/page-mount-state';
import { logout } from '@/routes';

import type { BreadcrumbItem } from '@/types';

const dashboardContainer = ref<HTMLElement | null>(null);
const { prefersReducedMotion } = useMobile();
// Drives which composition below mounts (see the MobileDashboard / desktop
// composition blocks). Mirrors the exact CSS rules the old `hidden md:block`
// / `md:hidden` toggle relied on — Tailwind's `md:` breakpoint (768px) plus
// the `html.touch-mobile` override in app.blade.php that keeps touch
// devices under 1024px on the mobile layout — so converting to a real v-if
// doesn't shift the switch point for any device.
const { isMdUp } = useDashboardLayoutBreakpoint();

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
    'bonusXp',
    'xpHistory',
    'statsBreakdown',
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
const userAvatar = computed(() => page.props.auth.user?.avatar || undefined);
const userProfileHref = computed(() => {
    const publicId = page.props.auth.user?.public_id;

    return publicId ? `/u/${publicId}` : undefined;
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

// A friendlier, context-aware greeting that keeps the dashboard from feeling
// canned. It varies by time of day, day of the week, and the user's current
// momentum (streak / overdue / due-today). The pick is seeded by the calendar
// day so it stays stable all day but feels fresh each morning — no generic
// "Good morning" every single visit.
const GREETING_POOLS = {
    night: [
        'Late night grind',
        'Burning the midnight oil',
        'Night owl mode',
        'Quiet hours',
        'After dark',
    ],
    early: [
        'Early bird',
        'Rise and shine',
        'Up and at them',
        'Morning light',
        'Daybreak hustle',
    ],
    morning: [
        'Good morning',
        'Hello! What’s cooking',
        'Morning superstar',
        'Rise and grind',
        'Fresh XP today',
        'Top of the morning',
        'Ready to shine',
    ],
    afternoon: [
        'Good afternoon',
        'Hello! What’s cooking',
        'Midday momentum',
        'Afternoon grind',
        'Afternoon energy',
        'Crushing it',
    ],
    evening: [
        'Good evening',
        'Hello! What’s cooking',
        'Evening check-in',
        'Finishing strong',
        'Home stretch',
        'Wind-down wisdom',
    ],
    streak: [
        'On fire',
        'Unstoppable',
        'Streak mode',
        'On a roll',
        'No breaks, no brakes',
    ],
    champion: [
        'Legend in the making',
        'Elite status',
        'Straight dominating',
        'Top-tier form',
        'Unmatched energy',
    ],
    overdue: [
        'Let’s catch up',
        'Back in the saddle',
        'Squashing it',
        'We got this',
        'No sweat',
        'Let’s power through',
    ],
    dueToday: [
        'Let’s make it count',
        'Game time',
        'Let’s get after it',
        'Tackling the day',
        'Showtime',
    ],
};

const daySeedFor = (poolLength: number) =>
    Math.floor(Date.now() / 86_400_000) % poolLength;

const personalizedGreeting = computed(() => {
    const hour = new Date().getHours();
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;
    const dueToday = todaySummary.value.dueTodayCount;

    const pick = (pool: string[]): string =>
        pool[daySeedFor(pool.length)] ?? pool[0];

    // Context takes priority over the clock: overdue > streak > due-today.
    if (overdue > 0) return pick(GREETING_POOLS.overdue);
    if (streak >= 7) return pick(GREETING_POOLS.champion);
    if (streak >= 3) return pick(GREETING_POOLS.streak);
    if (dueToday > 0) return pick(GREETING_POOLS.dueToday);

    if (hour >= 0 && hour < 4) return pick(GREETING_POOLS.night);
    if (hour >= 4 && hour < 7) return pick(GREETING_POOLS.early);
    if (hour >= 7 && hour < 12) return pick(GREETING_POOLS.morning);
    if (hour >= 12 && hour < 17) return pick(GREETING_POOLS.afternoon);
    if (hour >= 17 && hour < 21) return pick(GREETING_POOLS.evening);
    return pick(GREETING_POOLS.night);
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

// Concise, context-aware subtext for the hero (the "smart" part of the greeting)
const smarterStatus = computed(() => {
    const xpRemaining =
        props.userStats.maxXPForLevel - props.userStats.currentXP;
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;
    const dueToday = todaySummary.value.dueTodayCount;

    if (overdue > 0)
        return `You have ${overdue} task${overdue === 1 ? '' : 's'} to catch up on.`;
    if (xpRemaining < 200)
        return `Almost there — ${xpRemaining} XP to Level ${props.userStats.level + 1}.`;
    if (streak >= 3) return `${streak}-day streak — keep it going!`;
    if (dueToday > 0)
        return `${dueToday} item${dueToday === 1 ? '' : 's'} due today.`;

    return `All caught up. Nice work!`;
});

const isBooted = ref(false);

// ─── Onboarding tour ─────────────────────────────────────────────────────────
// Completion is per user *and* per device (localStorage), so a login from a
// new device replays the walkthrough while skipped/finished devices stay
// quiet. The daily-XP claim prompt is held back until the tour resolves so
// the two overlays never stack.
const dashboardTourPending = ref(false);
const isTourActive = ref(false);

const FOX_WELCOME_STORAGE_PREFIX = 'fox-welcome:v1';
const showFoxWelcomeModal = ref(false);
let foxWelcomeTimer: number | null = null;

const foxWelcomeStorageKey = (): string =>
    `${FOX_WELCOME_STORAGE_PREFIX}:${page.props.auth.user?.public_id ?? 'user'}`;

const hasSeenFoxWelcome = (): boolean => {
    if (typeof window === 'undefined') return false;

    try {
        return window.localStorage.getItem(foxWelcomeStorageKey()) === 'seen';
    } catch {
        return false;
    }
};

const markFoxWelcomeSeen = (): void => {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(foxWelcomeStorageKey(), 'seen');
    } catch {
        // Ignore storage failures; the modal remains dismissible.
    }
};

const dashboardTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Welcome to your dashboard',
        body: 'This is your home base — XP, streaks, deadlines and your class leaderboard all live here. Here’s a quick tour (you can skip anytime).',
    },
    {
        id: 'hero',
        target: 'dashboard-hero',
        title: 'Your daily snapshot',
        body: 'A personalized greeting with your rank, level and announcements from your teachers. It refreshes automatically.',
    },
    {
        id: 'today',
        target: 'dashboard-today',
        title: 'Today at a glance',
        body: 'What’s due today, overdue, or coming up in the next 24 hours — plus the single most urgent item so you always know what to do next.',
    },
    {
        id: 'daily-reward',
        target: 'dashboard-daily-reward',
        title: 'Claim your daily XP',
        body: 'Come back every day to claim free XP. Longer login streaks earn bigger bonuses.',
    },
    {
        id: 'level',
        target: 'dashboard-progress',
        title: 'Level & XP history',
        body: 'Switch between XP, streak and season in one card. Open your full XP history here — every exam, assignment and daily claim that earned you XP.',
    },
    {
        id: 'streak',
        target: 'dashboard-progress',
        title: 'Your streak',
        body: 'The Streak tab shows your login streak. Open it to see your streak calendar and your all-time best.',
    },
    {
        id: 'season',
        target: 'dashboard-progress',
        title: 'Season progress',
        body: 'Seasons group your class activities. The Season tab shows how many days remain before the season wraps up.',
    },
    {
        id: 'leaderboard',
        target: 'dashboard-leaderboard',
        title: 'Class leaderboard',
        body: 'See where you rank in your section. Earn XP from activities and daily claims to climb the board.',
    },
    {
        id: 'activity',
        target: 'dashboard-activity',
        title: 'Activity heatmap',
        body: 'Your last four weeks at a glance — the greener, the more consistent you’ve been. That’s the tour, have fun!',
    },
];

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
    /** Present for the super admin's platform-wide view: which workspace the section belongs to. */
    workspaceId?: number | null;
    workspaceName?: string | null;
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
    starts_at_iso?: string | null;
    ends_at_iso?: string | null;
    is_open_now?: boolean;
    is_upcoming?: boolean;
    has_ended?: boolean;
    duration_minutes: number;
    status: string;
    parts_count: number;
    submitted_parts: number;
    is_completed: boolean;
}

const props = defineProps<{
    claimXp: {
        enabled?: boolean;
        canClaim: boolean;
        amount: number;
        baseXp?: number;
        nextClaimAt: string | null;
        lastClaimedAt?: string | null;
        showPrompt?: boolean;
    };
    bonusXp?: {
        enabled?: boolean;
        canClaim: boolean;
        amount: number;
        nextClaimAt: string | null;
        lastClaimedAt?: string | null;
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
    streakRestore?: {
        enabled: boolean;
        limit: number;
        used: number;
        remaining: number;
        costs: number[];
        nextCost: number;
        restoredDates: string[];
        resetsAt: string | null;
    };
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

// Whether the onboarding walkthrough still needs to run. Resolved from the
// account record first (shared as `onboarding.tours`) and localStorage second,
// so the claim prompt isn't blocked forever for users who already finished or
// skipped the tour — on this device or any other.
dashboardTourPending.value =
    typeof window !== 'undefined' &&
    getTourStatus(
        'dashboard',
        page.props.auth.user?.public_id ?? '',
        page.props.onboarding,
    ) === null;

// The tour waits for boot + the section flow, and never runs for banned users.
const tourCanStart = computed(
    () => isBooted.value && !isBanned.value && claimPromptReady.value,
);

// ─── Claim → Echo sequencing ───────────────────────────────────────────────
// The daily-claim auto-prompt must run first (when present), then the Echo
// welcome. Without this, the claim modal opens instantly on login while the
// Echo modal fires 500ms later — stacking both overlays at once. The claim
// button reports its modal visibility via prompt-open/prompt-close so Echo
// waits until the claim flow is fully resolved (dismissed or claimed).
const isClaimModalOpen = ref(false);
const claimPromptResolved = ref(false);

const handleClaimPromptOpen = (): void => {
    isClaimModalOpen.value = true;
};

const handleClaimPromptClose = (): void => {
    isClaimModalOpen.value = false;
    claimPromptResolved.value = true;
};

// True while a claim prompt still needs to run first in this session. The
// server prop stays true after a "Later" dismiss (it only flips on reload),
// so the local `claimPromptResolved` flag is what unblocks Echo afterwards.
// Mobile has no auto-prompt modal (MobileDashboard only shows a static
// reward tile), so Echo must not wait there — otherwise it would be blocked
// forever with nothing to resolve it.
const needsClaimFirst = computed(
    () =>
        isMdUp.value &&
        claimPromptReady.value &&
        !dashboardTourPending.value &&
        !isTourActive.value &&
        Boolean(props.claimXp.showPrompt && props.claimXp.canClaim) &&
        !claimPromptResolved.value,
);

const foxWelcomeCanOpen = computed(
    () =>
        isBooted.value &&
        !isBanned.value &&
        !dashboardTourPending.value &&
        !isTourActive.value &&
        claimPromptReady.value &&
        !showSectionModal.value &&
        !isClaimModalOpen.value &&
        !needsClaimFirst.value &&
        !hasSeenFoxWelcome(),
);

const scheduleFoxWelcome = (): void => {
    if (!foxWelcomeCanOpen.value || foxWelcomeTimer !== null) return;

    foxWelcomeTimer = window.setTimeout(() => {
        foxWelcomeTimer = null;

        if (foxWelcomeCanOpen.value) {
            showFoxWelcomeModal.value = true;
        }
    }, 500);
};

const dismissFoxWelcome = (): void => {
    if (foxWelcomeTimer !== null) {
        window.clearTimeout(foxWelcomeTimer);
        foxWelcomeTimer = null;
    }

    showFoxWelcomeModal.value = false;
    markFoxWelcomeSeen();
};

const onTourResolved = () => {
    dashboardTourPending.value = false;
    isTourActive.value = false;
};

// Gate the auto-prompt behind the section flow (see claimPromptReady above)
// and the onboarding tour, so overlays never stack on top of each other.
const claimXpForPrompt = computed(() => ({
    ...props.claimXp,
    showPrompt:
        claimPromptReady.value &&
        !dashboardTourPending.value &&
        !isTourActive.value &&
        !showFoxWelcomeModal.value &&
        Boolean(props.claimXp.showPrompt),
}));

// Dismissals persist per user across sessions (localStorage), seeded into a
// reactive Set so both compositions stay in sync.
const dismissedAnnouncementIds = reactive(new Set<number>());
const DISMISSED_ANNOUNCEMENTS_KEY = (): string =>
    `dashboard:dismissed-announcements:${
        page.props.auth.user?.public_id ?? 'user'
    }`;

const loadDismissedAnnouncements = (): void => {
    if (typeof window === 'undefined') return;
    try {
        const raw = window.localStorage.getItem(DISMISSED_ANNOUNCEMENTS_KEY());
        if (!raw) return;
        for (const id of JSON.parse(raw) as number[])
            dismissedAnnouncementIds.add(id);
    } catch {
        // Corrupt or unavailable storage — in-memory dismissal still works.
    }
};
loadDismissedAnnouncements();

const dismissAnnouncement = (id: number): void => {
    dismissedAnnouncementIds.add(id);
    try {
        window.localStorage.setItem(
            DISMISSED_ANNOUNCEMENTS_KEY(),
            JSON.stringify([...dismissedAnnouncementIds]),
        );
    } catch {
        // Ignore storage failures; dismissal remains session-only.
    }
};

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
            href: '/activities',
            meta: a.description,
            isCompleted: a.submitted,
            isOverdue: a.isOverdue,
        });
    }

    for (const e of props.upcomingExams ?? []) {
        // Closed / draft exams are not actionable — they must not count as
        // today, overdue, next-24h, or "next exam".
        if (e.status !== 'published') continue;
        if (!e.exam_date_iso && !e.starts_at_iso) continue;
        const dueAt = new Date(e.starts_at_iso || e.exam_date_iso || '');
        if (Number.isNaN(dueAt.getTime())) continue;
        items.push({
            kind: 'exam',
            title: e.title,
            dueAt,
            href: '/activities',
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

const primaryLeaderboard = computed(() => sectionLeaderboards.value[0] ?? null);

// Serializable task list for the TodayPanel (both compositions).
const todayTasks = computed<TodayTask[]>(() =>
    dueItems.value.map((item) => ({
        kind: item.kind,
        title: item.title,
        dueAtIso: item.dueAt.toISOString(),
        href: item.href,
        meta: item.meta,
        isCompleted: item.isCompleted,
        isOverdue: item.isOverdue,
    })),
);

// "Full rankings" link on the podium band scrolls to the full card.
const scrollToLeaderboard = (): void => {
    const el = document.getElementById('dashboard-leaderboard-card');
    el?.scrollIntoView({
        behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
        block: 'start',
    });
};

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

watch(
    [
        isBooted,
        dashboardTourPending,
        isTourActive,
        claimPromptReady,
        showSectionModal,
        isClaimModalOpen,
        claimPromptResolved,
        needsClaimFirst,
    ],
    scheduleFoxWelcome,
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
});

// Pause/resume polling in response to ban modal
watch(showBanModal, (open) => {
    if (open) {
        pausePolling();
    } else if (!document.hidden) {
        resumePolling();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    pausePolling();
    if (foxWelcomeTimer !== null) {
        window.clearTimeout(foxWelcomeTimer);
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
            class="dashboard-ui mobile-ui-page relative flex h-full w-full max-w-full min-w-0 flex-1 flex-col gap-4 overflow-hidden bg-background p-3 sm:gap-5 sm:p-6 md:gap-7 md:p-8"
            :class="{
                'pointer-events-none blur-sm select-none': showBanModal,
            }"
        >
            <!-- Skeleton loader (shown while booting) -->
            <DashboardSkeleton v-if="!isBooted" />

            <!-- Dedicated mobile composition. Gated on isMdUp (not just
                 CSS) so the desktop tree below never mounts on phones —
                 previously both compositions were always mounted and only
                 hidden with `hidden md:block` / `md:hidden`, which meant
                 phones paid full render/reactivity/GSAP cost for a second,
                 invisible desktop dashboard on every poll tick. isMdUp is
                 seeded synchronously (before mount), so this doesn't
                 introduce a first-paint flash. -->
            <MobileDashboard
                v-if="isBooted && !isMdUp"
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
                :today-tasks="todayTasks"
                :claim-xp="claimXpForPrompt"
                :bonus-xp="props.bonusXp"
                :stats-breakdown="props.statsBreakdown"
                :xp-history="props.xpHistory"
                :login-dates="streak.loginDates"
                :streak-restore="props.streakRestore ?? null"
                :section-leaderboards="sectionLeaderboards"
                :active-season="activeSeason"
                :available-seasons="props.availableSeasons ?? []"
                :primary-leaderboard="primaryLeaderboard"
                :leaderboard-expanded="isLeaderboardExpanded"
                @close-announcement="dismissAnnouncement"
                @refresh="manualRefresh"
                @open-section-modal="showSectionModal = true"
                @claimed="manualRefresh"
                @toggle-leaderboard="
                    isLeaderboardExpanded = !isLeaderboardExpanded
                "
            />

            <!-- Desktop composition. Real v-if="isMdUp" (see the
                 MobileDashboard block above) instead of `hidden md:block` —
                 isMdUp/!isMdUp are exact complements, so exactly one of the
                 two compositions is ever mounted, with no dual-mount or gap
                 window at any width. Content renders statically — no entrance
                 animations; the boot skeleton covers the loading state. -->
            <div
                v-if="isMdUp && isBooted"
                class="dashboard-desktop-composition flex w-full min-w-0 flex-col gap-3 sm:gap-4"
            >
                <!-- Command bar: greeting, level chip, announcements, claim, refresh -->
                <CommandBar
                    data-tour="dashboard-hero"
                    class="dashboard-hero"
                    :user-name="userName"
                    :user-avatar="userAvatar"
                    :profile-href="userProfileHref"
                    :level="userStats.level"
                    :current-xp="userStats.currentXP"
                    :max-xp-for-level="userStats.maxXPForLevel"
                    :greeting="personalizedGreeting"
                    :status-line="smarterStatus"
                    :announcements="announcements"
                    :is-refreshing="isRefreshing"
                    :claim-xp="claimXpForPrompt"
                    :streak="userStats.streak"
                    @close-announcement="dismissAnnouncement"
                    @refresh="manualRefresh"
                    @open-section-modal="showSectionModal = true"
                    @claimed="manualRefresh"
                    @prompt-open="handleClaimPromptOpen"
                    @prompt-close="handleClaimPromptClose"
                />

                <!-- Podium band: the top 3 of the active section, above the fold -->
                <div
                    class="surface-card w-full min-w-0 p-3 sm:p-4"
                    data-tour="dashboard-leaderboard-podium"
                >
                    <ImprovedLeaderboard
                        podium-only
                        :section-leaderboards="sectionLeaderboards"
                        :active-season-name="activeSeason?.name"
                        :available-seasons="props.availableSeasons ?? []"
                    >
                        <template #band-action>
                            <button
                                type="button"
                                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-full px-2.5 py-1 text-[13px] font-semibold text-[#D97757] transition-colors hover:bg-[#D97757]/10"
                                @click="scrollToLeaderboard"
                            >
                                Full rankings
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                        </template>
                    </ImprovedLeaderboard>
                </div>

                <!-- Today (interactive, tabbed) + consolidated Progress card -->
                <div
                    class="grid min-w-0 grid-cols-1 items-start gap-3 sm:gap-4 lg:grid-cols-3"
                >
                    <div
                        class="min-w-0 lg:col-span-2"
                        data-tour="dashboard-today"
                    >
                        <TodayPanel :tasks="todayTasks" />
                    </div>

                    <ProgressCard
                        class="dashboard-progress"
                        data-tour="dashboard-progress"
                        :user-stats="userStats"
                        :breakdown="props.statsBreakdown?.xp ?? []"
                        :xp-history="props.xpHistory ?? []"
                        :claim-xp="claimXpForPrompt"
                        :bonus-xp="props.bonusXp"
                        :login-dates="streak.loginDates"
                        :streak-restore="props.streakRestore ?? null"
                        :season-name="activeSeason?.name ?? null"
                        :season-start-date="activeSeason?.startDate ?? null"
                        :season-end-date="activeSeason?.endDate ?? null"
                    />
                </div>

                <!-- Full rankings (list starts at rank 1; podium lives in the band) -->
                <div
                    class="grid min-w-0 grid-cols-1 items-start gap-3 sm:gap-4 lg:grid-cols-3"
                >
                    <div
                        id="dashboard-leaderboard-card"
                        class="surface-card min-w-0 p-3 sm:p-4 lg:col-span-2"
                        data-tour="dashboard-leaderboard"
                    >
                        <ImprovedLeaderboard
                            hide-podium
                            :section-leaderboards="sectionLeaderboards"
                            :active-season-name="activeSeason?.name"
                            :available-seasons="props.availableSeasons ?? []"
                            show-view-button
                            show-join-button
                            @open-section-modal="showSectionModal = true"
                        />
                    </div>

                    <section
                        class="surface-card w-full min-w-0 p-4 sm:p-5"
                        aria-label="Activity"
                        data-tour="dashboard-activity"
                    >
                        <div class="mb-4 min-w-0 sm:mb-5">
                            <h3
                                class="dash-title text-[17px] text-foreground sm:text-lg"
                            >
                                Activity
                            </h3>
                            <p class="mt-0.5 text-[13px] text-muted-foreground">
                                Your last 4 weeks at a glance.
                            </p>
                        </div>
                        <StreakHeatmap :login-dates="streak.loginDates" />
                    </section>
                </div>
            </div>
        </div>

        <SectionSelectionModal
            :show="showSectionModal"
            @close="
                showSectionModal = false;
                claimPromptReady = true;
            "
        />

        <ResponsiveModal
            :open="showFoxWelcomeModal"
            title="Introducing Echo"
            description="Your learning companion is here."
            content-class="max-w-md overflow-hidden"
            @close="dismissFoxWelcome"
        >
            <div
                data-testid="fox-welcome-modal"
                class="flex flex-col items-center gap-5 px-2 pb-2 text-center"
            >
                <FoxCompanion
                    mascot="welcome"
                    :size="150"
                    :show-message="false"
                    label="Echo, your learning companion"
                />
                <p class="max-w-sm text-sm leading-6 text-muted-foreground">
                    Meet Echo, your learning companion. Echo will celebrate your
                    progress, point out what to do next, and help keep your
                    learning streak moving.
                </p>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="dismissFoxWelcome"
                    >
                        Maybe later
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                        @click="dismissFoxWelcome"
                    >
                        Let’s go
                    </button>
                </div>
            </template>
        </ResponsiveModal>

        <!-- First-visit walkthrough (per user, per device) -->
        <OnboardingTour
            tour-id="dashboard"
            :steps="dashboardTourSteps"
            :can-start="tourCanStart"
            :start-delay="900"
            @start="isTourActive = true"
            @finish="onTourResolved"
            @skip="onTourResolved"
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
