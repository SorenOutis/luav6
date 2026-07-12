<script setup lang="ts">
import { Head, usePage, usePoll, router } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { Calendar, ChevronDown, ChevronUp, Trophy } from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, reactive, ref, computed, watch } from 'vue';

gsap.registerPlugin(ScrollTrigger);

const mouseGlow = ref<HTMLElement | null>(null);
const dashboardContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const prefersReducedMotion = ref(false);
const isMobile = ref(false);
const isTouchDevice = ref(false);

const syncInteractionModes = () => {
    prefersReducedMotion.value = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    isMobile.value = window.innerWidth < 768;
    isTouchDevice.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
};

const handleGlobalMouseMove = (e: MouseEvent) => {
    if (
        !mouseGlow.value ||
        prefersReducedMotion.value ||
        isMobile.value ||
        isTouchDevice.value
    )
        return;

    const { clientX, clientY } = e;

    gsap.to(mouseGlow.value, {
        x: clientX,
        y: clientY,
        duration: 1.2,
        ease: 'power3.out',
    });
};

import CourseAssignmentList from '@/components/dashboard/CourseAssignmentList.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import DashboardSidebar from '@/components/dashboard/DashboardSidebar.vue';
import DashboardSkeleton from '@/components/dashboard/DashboardSkeleton.vue';
import DashboardStats from '@/components/dashboard/DashboardStats.vue';
import type { NextUpItem } from '@/components/dashboard/NextUpCard.vue';
import SeasonProgressBand from '@/components/dashboard/SeasonProgressBand.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import SectionSelectionModal from '@/components/SectionSelectionModal.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import AppLayout from '@/layouts/AppLayout.vue';
import { logout } from '@/routes';
import { index as assignmentsIndex } from '@/routes/assignments';
import { show as examsShow } from '@/routes/exams';
import { edit as profileEdit } from '@/routes/profile';

const { isVisible: isLoaderVisible } = useLoader();

const breadcrumbs = [];

const lastSyncTime = ref(new Date());
const isRefreshing = ref(false);

const POLL_PROPS = [
    'userStats',
    'userBadges',
    'notifications',
    'loginDates',
    'announcements',
    'courses',
    'assignments',
    'upcomingExams',
    'sectionLeaderboards',
    'activeSeason',
];
const POLL_INTERVAL_MS = 15000;

const { stop: stopPoll, start: startPoll } = usePoll(
    POLL_INTERVAL_MS,
    {
        only: POLL_PROPS,
        onStart: () => {
            isRefreshing.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
            lastSyncTime.value = new Date();
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
            lastSyncTime.value = new Date();
        },
    });
};

const page = usePage();
const userName = computed(() => page.props.auth.user?.name || 'User');
const userAvatar = computed(() => page.props.auth.user?.avatar);
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

    if (overdue > 0)
        return 'from-rose-500 to-red-600 dark:from-rose-400 dark:to-red-500';
    if (streak >= 7)
        return 'from-amber-400 to-orange-500 dark:from-amber-300 dark:to-orange-400';
    if (streak > 0)
        return 'from-emerald-400 to-teal-500 dark:from-emerald-300 dark:to-teal-400';

    if (hour >= 0 && hour < 4)
        return 'from-indigo-400 to-purple-600 dark:from-indigo-300 dark:to-purple-500'; // Late night
    if (hour >= 4 && hour < 7)
        return 'from-sky-400 to-blue-500 dark:from-sky-300 dark:to-blue-400'; // Early bird
    if (hour >= 7 && hour < 12)
        return 'from-amber-300 to-yellow-500 dark:from-amber-200 dark:to-yellow-400'; // Morning
    if (hour >= 12 && hour < 17)
        return 'from-orange-400 to-rose-500 dark:from-orange-300 dark:to-rose-400'; // Afternoon
    if (hour >= 17 && hour < 21)
        return 'from-violet-500 to-fuchsia-600 dark:from-violet-400 dark:to-fuchsia-500'; // Evening
    return 'from-blue-400 to-indigo-600 dark:from-blue-300 dark:to-indigo-500'; // Winding down
});

const statusColor = computed(() => {
    const overdue = todaySummary.value.overdueCount;
    const streak = props.userStats.streak;

    if (overdue > 0) return 'bg-rose-500 shadow-[0_0_6px_rgba(244,63,94,0.4)]';
    if (streak >= 7)
        return 'bg-amber-500 shadow-[0_0_6px_rgba(245,158,11,0.4)]';
    if (streak > 0)
        return 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]';

    return 'bg-blue-500 shadow-[0_0_6px_rgba(59,130,246,0.4)]';
});

const ambientColor = computed(() => {
    const hour = new Date().getHours();
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;

    if (overdue > 0) return 'bg-rose-500/10 dark:bg-rose-500/20';
    if (streak >= 7) return 'bg-amber-500/10 dark:bg-amber-500/20';
    if (streak > 0) return 'bg-emerald-500/10 dark:bg-emerald-500/20';

    if (hour >= 0 && hour < 4) return 'bg-purple-500/10 dark:bg-purple-500/20';
    if (hour >= 4 && hour < 7) return 'bg-sky-500/10 dark:bg-sky-500/20';
    if (hour >= 7 && hour < 12) return 'bg-amber-400/10 dark:bg-amber-400/20';
    if (hour >= 12 && hour < 17)
        return 'bg-orange-500/10 dark:bg-orange-500/20';
    if (hour >= 17 && hour < 21)
        return 'bg-fuchsia-500/10 dark:bg-fuchsia-500/20';
    return 'bg-indigo-500/10 dark:bg-indigo-500/20';
});

const accentBadge = computed(() => {
    const hour = new Date().getHours();
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;

    if (overdue > 0)
        return {
            text: 'Requires Attention',
            theme: 'bg-rose-500/10 text-rose-500 border-rose-500/20',
        };
    if (streak >= 7)
        return {
            text: 'Legendary Streak',
            theme: 'bg-amber-500/10 text-amber-500 border-amber-500/20',
        };
    if (streak > 0)
        return {
            text: 'Active Streak',
            theme: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
        };

    if (hour >= 0 && hour < 4)
        return {
            text: 'Late Night Session',
            theme: 'bg-purple-500/10 text-purple-500 border-purple-500/20',
        };
    if (hour >= 4 && hour < 7)
        return {
            text: 'Early Bird',
            theme: 'bg-sky-500/10 text-sky-500 border-sky-500/20',
        };
    if (hour >= 7 && hour < 12)
        return {
            text: 'Morning Session',
            theme: 'bg-amber-400/10 text-amber-500 border-amber-400/20',
        };
    if (hour >= 12 && hour < 17)
        return {
            text: 'Afternoon Focus',
            theme: 'bg-orange-500/10 text-orange-500 border-orange-500/20',
        };
    if (hour >= 17 && hour < 21)
        return {
            text: 'Evening Grind',
            theme: 'bg-fuchsia-500/10 text-fuchsia-500 border-fuchsia-500/20',
        };
    return {
        text: 'Winding Down',
        theme: 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20',
    };
});

// Smarter status subtext for the hero
const smarterStatus = computed(() => {
    const xpRemaining =
        props.userStats.maxXPForLevel - props.userStats.currentXP;
    const streak = props.userStats.streak;
    const overdue = todaySummary.value.overdueCount;
    const dueToday = todaySummary.value.dueTodayCount;

    if (overdue > 0)
        return `You have ${overdue} ${overdue === 1 ? 'task' : 'tasks'} requiring <span class="text-rose-500 dark:text-rose-400 font-semibold drop-shadow-sm">immediate attention</span>.`;
    if (xpRemaining < 200)
        return `Only <span class="text-emerald-500 dark:text-emerald-400 font-semibold drop-shadow-sm">${xpRemaining} XP</span> until you reach Level ${props.userStats.level + 1}!`;
    if (streak >= 3)
        return `You've maintained a <span class="text-amber-500 dark:text-amber-400 font-semibold drop-shadow-sm">${streak}-day streak</span>. Keep the momentum!`;
    if (dueToday > 0)
        return `You have ${dueToday} ${dueToday === 1 ? 'item' : 'items'} on your <span class="text-blue-500 dark:text-blue-400 font-semibold drop-shadow-sm">schedule for today</span>.`;

    return `Your learning engine is performing at <span class="text-primary font-semibold drop-shadow-sm">peak capacity</span>.`;
});

const isBooted = ref(false);

interface Course {
    id: number;
    name: string;
    progress: number;
    completedLessons: number;
    totalLessons: number;
    xpEarned: number;
    nextDeadline: string;
}

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

interface UserBadge {
    id: number;
    name: string;
    description?: string | null;
    requiredLevel?: number | null;
    image?: string | null;
    iconUrl?: string | null;
    earnedSeason?: string | null;
    earnedAt?: string | null;
}

const props = defineProps<{
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
    loginDates?: string[];
    announcements: Announcement[];
    userBadges: UserBadge[];
    courses: Course[];
    assignments: Assignment[];
    upcomingExams: Exam[];
    sectionLeaderboards: LeaderboardData[];
    activeSeason: Season | null;
    sectionName?: string | null;
    availableSeasons?: Season[];
}>();

const userStats = computed(() => props.userStats);
const progressPercentage = computed(
    () => (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100,
);
const totalXPProgress = computed(() => {
    if (!userStats.value.maxXPForLevel) return 0;
    const percent =
        (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100;
    return Math.min(100, Math.max(0, percent));
});

const dismissedAnnouncementIds = reactive(new Set<number>());
const announcements = computed(() =>
    props.announcements.filter((a) => !dismissedAnnouncementIds.has(a.id)),
);
const userBadges = computed(() => props.userBadges);
const courses = computed(() => props.courses);
const assignments = computed(() => props.assignments);
const upcomingExams = computed(() => props.upcomingExams);
const sectionLeaderboards = computed(() => props.sectionLeaderboards);

const streak = computed(() => ({
    currentStreak: props.userStats.streak || 0,
    longestStreak: props.userStats.longestStreak || 0,
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

const nextUpItem = computed<NextUpItem | null>(() => {
    const now = Date.now();
    const candidates = dueItems.value
        .filter((i) => !i.isCompleted)
        .sort(
            (a, b) =>
                Math.abs(a.dueAt.getTime() - now) -
                Math.abs(b.dueAt.getTime() - now),
        );
    const pick = candidates[0];
    if (!pick) return null;
    return {
        kind: pick.kind,
        title: pick.title,
        dueAt: pick.dueAt.toISOString(),
        href: pick.href,
        meta: pick.meta,
    };
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

const seasonalXpTarget = computed(() => {
    // Rough target: fill the currently reached level's XP band; can be tuned later
    return props.userStats?.maxXPForLevel ?? 100;
});

let gsapCtx: gsap.Context | null = null;

const showSectionModal = ref(false);
const isLeaderboardExpanded = ref(false);
const isSidebarExpanded = ref(false);

watch(
    () => props.sectionName,
    (newSection) => {
        if (newSection) {
            showSectionModal.value = false;
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
    syncInteractionModes();
    window.addEventListener('resize', syncInteractionModes);
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
        if (prefersReducedMotion.value || isMobile.value) {
            gsap.set(
                [
                    '.dashboard-hero',
                    '.dashboard-stats',
                    '.dashboard-leaderboard',
                    '.dashboard-main-grid',
                ],
                { opacity: 1, y: 0, scale: 1, clearProps: 'transform' },
            );
            return;
        }

        gsap.to(backgroundGrid.value, {
            y: -100,
            ease: 'none',
            scrollTrigger: {
                trigger: dashboardContainer.value,
                start: 'top top',
                end: 'bottom bottom',
                scrub: true,
            },
        });

        const orbs = dashboardContainer.value?.querySelectorAll('.orb');
        orbs?.forEach((orb, i) => {
            gsap.to(orb, {
                x: 'random(-100, 100)',
                y: 'random(-100, 100)',
                duration: 12 + i * 4,
                repeat: -1,
                repeatRefresh: true,
                yoyo: true,
                ease: 'sine.inOut',
            });
        });
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
    window.removeEventListener('resize', syncInteractionModes);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    pausePolling();
    if (gsapCtx) {
        gsapCtx.revert();
    }
});

const handleQuickAction = (action: string) => {
    switch (action) {
        case 'resume':
            if (props.courses.length > 0) {
                // Navigate to the first course or resume last
                console.log('Resuming course...');
            }
            break;
        case 'assignments':
            router.get(assignmentsIndex().url);
            break;
        case 'leaderboard':
            // If there's a specific leaderboard route, navigate there
            // Otherwise maybe just scroll to leaderboard
            document
                .querySelector('.dashboard-leaderboard')
                ?.scrollIntoView({ behavior: 'smooth' });
            break;
        case 'settings':
            router.get(profileEdit().url);
            break;
    }
};

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
            @mousemove="handleGlobalMouseMove"
            class="relative flex h-full w-full max-w-full min-w-0 flex-1 flex-col gap-4 overflow-hidden bg-background p-3 sm:p-5 md:gap-6 md:p-8"
            :class="{
                'pointer-events-none blur-sm select-none': showBanModal,
            }"
        >
            <!-- Global Mouse Glow -->
            <div
                ref="mouseGlow"
                class="pointer-events-none fixed -top-[200px] -left-[200px] z-0 h-[400px] w-[400px] rounded-full blur-[120px] transition-colors duration-1000 will-change-transform"
                :class="ambientColor"
                aria-hidden="true"
            ></div>

            <!-- Monolithic Grid Overlay -->
            <div
                ref="backgroundGrid"
                class="pointer-events-none fixed inset-[-100px] z-0 opacity-[0.03] will-change-transform dark:opacity-[0.06]"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-0"
                    style="
                        background-image:
                            linear-gradient(
                                var(--color-border) 1px,
                                transparent 1px
                            ),
                            linear-gradient(
                                90deg,
                                var(--color-border) 1px,
                                transparent 1px
                            );
                        background-size: 60px 60px;
                    "
                ></div>
            </div>

            <!-- Ambient orbs -->
            <div
                class="orb pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full blur-[120px] transition-colors duration-1000"
                :class="ambientColor"
                aria-hidden="true"
            ></div>
            <div
                class="orb pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full blur-[120px] transition-colors duration-1000"
                :class="ambientColor"
                aria-hidden="true"
            ></div>

            <!-- Skeleton loader (shown while booting) -->
            <DashboardSkeleton v-if="!isBooted" />

            <!-- Real content (shown after booted) -->
            <template v-if="isBooted">
                <!-- Hero Banner Section -->
                <Motion
                    :initial="{ opacity: 0, y: 30 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{
                        duration: 1,
                        ease: [0.16, 1, 0.3, 1],
                        delay: 0.1,
                    }"
                    class="relative space-y-6"
                >
                    <DashboardHero
                        class="dashboard-hero"
                        :user-name="userName"
                        :user-avatar="userAvatar"
                        :user-stats="userStats"
                        :announcements="announcements"
                        :total-x-p-progress="totalXPProgress"
                        :time-based-greeting="personalizedGreeting"
                        :greeting-theme="greetingTheme"
                        :status-color="statusColor"
                        :accent-badge="accentBadge"
                        :smarter-status="smarterStatus"
                        :is-refreshing="isRefreshing"
                        :last-sync-time="lastSyncTime"
                        @close-announcement="(id: number) => dismissedAnnouncementIds.add(id)"
                        @refresh="manualRefresh"
                        @open-section-modal="showSectionModal = true"
                    />
                </Motion>

                <!-- Header Section with User Stats -->
                <Motion
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{
                        duration: 1,
                        ease: [0.16, 1, 0.3, 1],
                        delay: 0.2,
                    }"
                >
                    <DashboardStats
                        class="dashboard-stats"
                        :user-stats="userStats"
                        :streak="streak"
                        :progress-percentage="progressPercentage"
                    />
                </Motion>

                <!-- Main Content Grid -->
                <Motion
                    :initial="{ opacity: 0, y: 40 }"
                    :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                    :in-view-options="{ once: true, margin: '-50px' }"
                    :transition="{ duration: 1.2, ease: [0.16, 1, 0.3, 1] }"
                    class="dashboard-main-grid grid min-w-0 grid-cols-1 items-start gap-8 lg:grid-cols-3"
                >
                    <!-- Main Section: Leaderboard + Mission Control -->
                    <div class="min-w-0 space-y-8 lg:col-span-2">
                        <!-- Mobile: Collapsible Leaderboard -->
                        <div class="lg:hidden">
                            <button
                                @click="isLeaderboardExpanded = !isLeaderboardExpanded"
                                class="flex w-full items-center justify-between rounded-xl border border-border/30 bg-card/40 px-4 py-3 text-left transition-all duration-300 hover:border-amber-400/30"
                            >
                                <div class="flex items-center gap-3">
                                    <Trophy class="h-4 w-4 text-amber-400" />
                                    <div>
                                        <span class="text-xs font-bold text-foreground">Leaderboard</span>
                                        <p v-if="sectionLeaderboards.length > 0" class="text-[9px] text-muted-foreground">
                                            {{ sectionLeaderboards[0]?.sectionName }} · {{ sectionLeaderboards[0]?.totalPlayers }} players
                                        </p>
                                    </div>
                                </div>
                                <component :is="isLeaderboardExpanded ? ChevronUp : ChevronDown" class="h-4 w-4 text-muted-foreground transition-transform duration-300" />
                            </button>
                            <div
                                v-show="isLeaderboardExpanded"
                                class="mt-3"
                            >
                                <ImprovedLeaderboard
                                    class="dashboard-leaderboard"
                                    :section-leaderboards="sectionLeaderboards"
                                    :active-season-name="activeSeason?.name"
                                    :available-seasons="props.availableSeasons ?? []"
                                />
                            </div>
                        </div>

                        <!-- Desktop: Full Leaderboard -->
                        <div class="hidden lg:block">
                            <ImprovedLeaderboard
                                class="dashboard-leaderboard"
                                :section-leaderboards="sectionLeaderboards"
                                :active-season-name="activeSeason?.name"
                                :available-seasons="props.availableSeasons ?? []"
                            />
                        </div>

                        <CourseAssignmentList
                            :courses="courses"
                            :assignments="assignments"
                            @course-click="(c) => console.log('Course:', c)"
                            @assignment-click="(a) => console.log('Assignment:', a)"
                        />
                    </div>

                    <!-- Sidebar - Season / Activity Pulse / Notifications & Achievements -->
                    <div
                        class="min-w-0 space-y-6 lg:sticky lg:top-24 lg:self-start"
                    >
                        <!-- Mobile: Collapsible Sidebar -->
                        <div class="lg:hidden">
                            <button
                                @click="isSidebarExpanded = !isSidebarExpanded"
                                class="flex w-full items-center justify-between rounded-xl border border-border/30 bg-card/40 px-4 py-3 text-left transition-all duration-300 hover:border-primary/30"
                            >
                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-primary" />
                                    <div>
                                        <span class="text-xs font-bold text-foreground">Insights &amp; Progress</span>
                                        <p class="text-[9px] text-muted-foreground">Season, activity, and quick actions</p>
                                    </div>
                                </div>
                                <component :is="isSidebarExpanded ? ChevronUp : ChevronDown" class="h-4 w-4 text-muted-foreground transition-transform duration-300" />
                            </button>
                            <div
                                v-show="isSidebarExpanded"
                                class="mt-3 space-y-6"
                            >
                                <SeasonProgressBand
                                    :name="activeSeason?.name ?? null"
                                    :start-date="activeSeason?.startDate ?? null"
                                    :end-date="activeSeason?.endDate ?? null"
                                    :xp-earned="userStats.currentXP"
                                    :xp-target="seasonalXpTarget"
                                />

                                <SpotlightCard
                                    customSize
                                    glowColor="blue"
                                    className="surface-card p-0 w-full min-w-0"
                                >
                                    <div
                                        class="relative flex h-full w-full flex-col p-4 sm:p-5"
                                    >
                                        <div
                                            class="relative z-10 mb-4 flex items-center justify-between"
                                        >
                                            <div>
                                                <h3
                                                    class="flex items-center gap-2 text-sm font-bold"
                                                >
                                                    <Calendar
                                                        class="h-4 w-4 text-primary"
                                                    />
                                                    Activity Pulse
                                                </h3>
                                                <p
                                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                                >
                                                    Consistency builds momentum.
                                                </p>
                                            </div>
                                        </div>
                                        <StreakHeatmap :login-dates="streak.loginDates" />
                                    </div>
                                </SpotlightCard>

                                <DashboardSidebar
                                    :unread-notification-count="3"
                                    :badges="userBadges"
                                    :weekly-x-p="userStats.currentXP"
                                    :weekly-goal="1000"
                                    :upcoming-exams="props.upcomingExams"
                                    :exam-seasons="props.availableSeasons ?? []"
                                    :next-up-item="nextUpItem"
                                    :profile-url="`/u/${page.props.auth.user?.id}`"
                                    @quick-action="handleQuickAction"
                                />
                            </div>
                        </div>

                        <!-- Desktop: Full Sidebar -->
                        <div class="hidden lg:block space-y-6">
                            <SeasonProgressBand
                                :name="activeSeason?.name ?? null"
                                :start-date="activeSeason?.startDate ?? null"
                                :end-date="activeSeason?.endDate ?? null"
                                :xp-earned="userStats.currentXP"
                                :xp-target="seasonalXpTarget"
                            />

                            <!-- Streak Heatmap Card (compact) -->
                            <SpotlightCard
                                customSize
                                glowColor="blue"
                                className="surface-card p-0 w-full min-w-0"
                            >
                                <div
                                    class="relative flex h-full w-full flex-col p-4 sm:p-5"
                                >
                                    <div
                                        class="relative z-10 mb-4 flex items-center justify-between"
                                    >
                                        <div>
                                            <h3
                                                class="flex items-center gap-2 text-sm font-bold"
                                            >
                                                <Calendar
                                                    class="h-4 w-4 text-primary"
                                                />
                                                Activity Pulse
                                            </h3>
                                            <p
                                                class="mt-0.5 text-[10px] text-muted-foreground"
                                            >
                                                Consistency builds momentum.
                                            </p>
                                        </div>
                                    </div>
                                    <StreakHeatmap :login-dates="streak.loginDates" />
                                </div>
                            </SpotlightCard>

                            <DashboardSidebar
                                :unread-notification-count="3"
                                :badges="userBadges"
                                :weekly-x-p="userStats.currentXP"
                                :weekly-goal="1000"
                                :upcoming-exams="props.upcomingExams"
                                :exam-seasons="props.availableSeasons ?? []"
                                :next-up-item="nextUpItem"
                                :profile-url="`/u/${page.props.auth.user?.id}`"
                                @quick-action="handleQuickAction"
                            />
                        </div>
                    </div>
                </Motion>
            </template>
        </div>

        <SectionSelectionModal
            :show="showSectionModal"
            @close="showSectionModal = false"
        />

        <div
            v-if="showBanModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-md"
        >
            <div
                class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-destructive/30 bg-background/95 shadow-2xl"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-destructive/10 via-destructive to-destructive/10"
                />
                <div class="p-6 sm:p-8">
                    <div>
                        <p
                            class="text-xs font-medium text-destructive/80"
                        >
                            Access Restricted
                        </p>
                        <h2
                            class="mt-1 text-3xl font-semibold tracking-tight text-foreground"
                        >
                            Account Suspended
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
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
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
                        class="inline-flex items-center justify-center rounded-xl bg-destructive px-5 py-2.5 text-sm font-semibold text-destructive-foreground transition-colors hover:bg-destructive/90"
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
