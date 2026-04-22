<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard, logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import DashboardStats from '@/components/dashboard/DashboardStats.vue';
import CourseAssignmentList from '@/components/dashboard/CourseAssignmentList.vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';
import DashboardSidebar from '@/components/dashboard/DashboardSidebar.vue';
import StreakHeatmap from '@/components/StreakHeatmap.vue';
import { Calendar } from 'lucide-vue-next';
import SectionSelectionModal from '@/components/SectionSelectionModal.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const dashboardContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const mouseGlow = ref<HTMLElement | null>(null);

let gsapCtx: gsap.Context | null = null;
const isCoarsePointer = ref(false);

const syncInteractionModes = () => {
    isCoarsePointer.value = window.matchMedia('(pointer: coarse)').matches;
};

import { usePage, Link, usePoll, router } from '@inertiajs/vue3';
import { BookOpen, Clock, RefreshCw } from 'lucide-vue-next';
import { index as examsIndex, show as examsShow } from '@/routes/exams';
import { edit as profileEdit } from '@/routes/profile';
import { index as assignmentsIndex } from '@/routes/assignments';

const lastSyncTime = ref(new Date());
const isRefreshing = ref(false);

const POLL_PROPS = ['userStats', 'loginDates', 'announcements', 'courses', 'assignments', 'upcomingExams', 'sectionLeaderboards', 'activeSeason'];
const POLL_INTERVAL_MS = 15000;

const { stop: stopPoll, start: startPoll } = usePoll(POLL_INTERVAL_MS, {
    only: POLL_PROPS,
    onStart: () => { isRefreshing.value = true; },
    onFinish: () => {
        isRefreshing.value = false;
        lastSyncTime.value = new Date();
    },
}, { autoStart: false });

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

// Personalized greeting based on stats
const personalizedGreeting = computed(() => {
    const hour = new Date().getHours();
    let base = 'Good Evening';
    if (hour < 12) base = 'Good Morning';
    else if (hour < 18) base = 'Good Afternoon';

    const streak = props.userStats.streak;
    if (streak > 5) return `${base}, Champ!`;
    if (streak > 0) return `${base}, keep it up!`;
    return base;
});

// Interactive Background Logic
const handleGlobalMouseMove = (e: MouseEvent) => {
    if (!mouseGlow.value || !backgroundGrid.value || isCoarsePointer.value) return;

    const { clientX, clientY } = e;
    const xPercent = clientX / window.innerWidth;
    const yPercent = clientY / window.innerHeight;

    // Background Glow
    gsap.to(mouseGlow.value, {
        x: clientX,
        y: clientY,
        duration: 1.2,
        ease: 'power3.out'
    });

    // Grid Parallax
    gsap.to(backgroundGrid.value, {
        x: (xPercent - 0.5) * 30,
        y: (yPercent - 0.5) * 30,
        duration: 1.5,
        ease: 'power2.out'
    });
};

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
    isOverdue: boolean;
    submitted: boolean;
    status: string;
    grade: string | null;
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
    duration_minutes: number;
    status: string;
    parts_count: number;
    submitted_parts: number;
    is_completed: boolean;
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
    courses: Course[];
    assignments: Assignment[];
    upcomingExams: Exam[];
    sectionLeaderboards: LeaderboardData[];
    activeSeason: Season | null;
    sectionName?: string | null;
    allSections: Array<{ id: number, name: string }>;
}>();

const userStats = computed(() => props.userStats);
const progressPercentage = computed(() => (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100);
const totalXPProgress = computed(() => {
    if (!userStats.value.maxXPForLevel) return 0;
    const percent = (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100;
    return Math.min(100, Math.max(0, percent));
});

const announcements = computed(() => props.announcements);
const courses = computed(() => props.courses);
const assignments = computed(() => props.assignments);
const upcomingExams = computed(() => props.upcomingExams);
const sectionLeaderboards = computed(() => props.sectionLeaderboards);

const streak = computed(() => ({
    currentStreak: props.userStats.streak || 0,
    longestStreak: props.userStats.longestStreak || 0,
    loginDates: props.loginDates ?? [],
}));

const showSectionModal = ref(false);

watch(() => props.sectionName, (newSection) => {
    if (newSection) {
        showSectionModal.value = false;
    }
}, { immediate: true });

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
        const tl = gsap.timeline({
            defaults: { ease: 'expo.out', duration: 1.2 }
        });

        // Reset initial states
        gsap.set('.dashboard-hero', { opacity: 0, y: 30, scale: 0.99 });
        gsap.set('.dashboard-stats', { opacity: 0, y: 20, scale: 0.99 });
        gsap.set('.dashboard-leaderboard', { opacity: 0, y: 20, scale: 0.99 });
        gsap.set('.dashboard-main-grid', {
            opacity: 0,
            y: 40,
            scale: 0.99,
        });

        // Hero and Stats Entrance (Immediate)
        tl.to('.dashboard-hero', { 
            opacity: 1, 
            y: 0, 
            scale: 1,
            duration: 1
        })
        .to('.dashboard-stats', { 
            opacity: 1, 
            y: 0, 
            scale: 1,
            duration: 0.8
        }, '-=0.7');

        // Scroll-Triggered Sections
        gsap.to('.dashboard-leaderboard', {
            scrollTrigger: {
                trigger: '.dashboard-leaderboard',
                start: 'top 75%',
            },
            opacity: 1,
            y: 0,
            scale: 1,
            duration: 1,
            ease: 'expo.out'
        });

        gsap.to('.dashboard-main-grid', {
            scrollTrigger: {
                trigger: '.dashboard-main-grid',
                start: 'top 75%',
            },
            opacity: 1,
            y: 0,
            scale: 1,
            duration: 1.2,
            ease: 'expo.out'
        });

        // Background orb animation refinement — re-randomize each cycle
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
            document.querySelector('.dashboard-leaderboard')?.scrollIntoView({ behavior: 'smooth' });
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
            class="flex h-full flex-1 flex-col gap-6 md:gap-8 p-4 md:p-10 relative overflow-hidden bg-background transition-all duration-300"
            :class="{
                'pointer-events-none blur-sm select-none': showBanModal,
            }"
        >
            <!-- Global Mouse Glow -->
            <div 
                ref="mouseGlow"
                class="pointer-events-none fixed -left-[200px] -top-[200px] z-0 h-[400px] w-[400px] rounded-full bg-primary/5 blur-[120px] will-change-transform dark:bg-primary/10"
            ></div>

            <!-- Monolithic Grid Overlay -->
            <div ref="backgroundGrid" class="fixed inset-[-100px] z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06] will-change-transform">
                <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
            </div>

            <!-- Glassy background decorative orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Hero Banner Section -->
            <div class="relative space-y-6">
                <DashboardHero 
                    class="animate-section dashboard-hero"
                    :user-name="userName"
                    :user-avatar="userAvatar"
                    :user-stats="userStats"
                    :announcements="announcements"
                    :total-x-p-progress="totalXPProgress"
                    :time-based-greeting="personalizedGreeting"
                    :is-refreshing="isRefreshing"
                    :last-sync-time="lastSyncTime"
                    @close-announcement="announcements = []"
                    @refresh="manualRefresh"
                />
            </div>

            <!-- Header Section with User Stats -->
            <DashboardStats 
                class="animate-section stagger-2 dashboard-stats"
                :user-stats="userStats"
                :streak="streak"
                :progress-percentage="progressPercentage"
            />

            <!-- Improved Leaderboard -->
            <div class="animate-section stagger-3 dashboard-leaderboard">
                <ImprovedLeaderboard 
                    :section-leaderboards="sectionLeaderboards" 
                    :active-season-name="activeSeason?.name"
                />
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-8 lg:grid-cols-3 items-start animate-section stagger-4 dashboard-main-grid">
                <!-- Courses Progress - Main Section -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Streak Heatmap Card -->
                    <div class="surface-card p-6 sm:p-8">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-xl font-bold flex items-center gap-2">
                                    <Calendar class="w-5 h-5 text-primary" />
                                    Activity Pulse
                                </h3>
                                <p class="text-xs text-muted-foreground mt-1">Consistency is the bridge between goals and accomplishment.</p>
                            </div>
                        </div>
                        <StreakHeatmap :login-dates="streak.loginDates" />
                    </div>

                    <CourseAssignmentList 
                        :courses="courses"
                        :assignments="assignments"
                        @course-click="(c) => console.log('Course:', c)"
                        @assignment-click="(a) => console.log('Assignment:', a)"
                    />
                </div>

                <!-- Sidebar - Notifications & Achievements -->
                <div class="lg:sticky lg:top-24 lg:self-start">
                    <DashboardSidebar
                        :unread-notification-count="3"
                        :weekly-x-p="userStats.currentXP"
                        :weekly-goal="1000"
                        :upcoming-exams="upcomingExams"
                        @quick-action="handleQuickAction"
                    />
                </div>
            </div>
        </div>

        <SectionSelectionModal 
            :show="showSectionModal" 
            :sections="allSections" 
        />

        <div
            v-if="showBanModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-md"
        >
            <div class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-destructive/30 bg-background/95 shadow-2xl">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-destructive/10 via-destructive to-destructive/10" />
                <div class="p-6 sm:p-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-destructive/85">Access Restricted</p>
                        <h2 class="mt-1 text-3xl font-semibold tracking-tight text-foreground">Account Suspended</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base">
                            Your account is currently banned from using this system. Please contact your administrator to request a review.
                        </p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div
                            v-if="banReason"
                            class="rounded-xl border border-border/80 bg-gradient-to-br from-muted/60 to-muted/30 p-4"
                        >
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Ban reason</p>
                            <p class="mt-1 text-sm text-foreground">{{ banReason }}</p>
                        </div>
                        <div
                            v-if="bannedAt"
                            class="inline-flex items-center rounded-full border border-border/80 bg-muted/40 px-3 py-1 text-xs text-muted-foreground"
                        >
                            Banned on: {{ bannedAt }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end border-t border-border/70 bg-muted/20 p-4 sm:p-5">
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
