<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
];

const dashboardContainer = ref<HTMLElement | null>(null);

import { usePage, Link } from '@inertiajs/vue3';
import { BookOpen, Clock } from 'lucide-vue-next';
import { index as examsIndex, show as examsShow } from '@/routes/exams';

const page = usePage();
const userName = computed(() => page.props.auth.user?.name || 'User');

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

interface LeaderboardUser {
    id: number;
    name: string;
    xp: number;
    completionRate: number;
    streak: number;
    joinedAt: string;
    weeklyXp: number;
    trend: 'up' | 'down' | 'stable';
    isCurrentUser: boolean;
}

interface Season {
    id: number;
    name: string;
}

interface Announcement {
    id: number;
    title: string;
    description: string;
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
        joinedAt: string;
    };
    announcements: Announcement[];
    courses: Course[];
    assignments: Assignment[];
    upcomingExams: Exam[];
    leaderboardUsers: LeaderboardUser[];
    activeSeason: Season | null;
}>();

const userStats = computed(() => props.userStats);
const progressPercentage = computed(() => (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100);
const totalXPProgress = computed(() => Math.min(100, (userStats.value.totalXP / 500000) * 100));

const announcements = computed(() => props.announcements);
const courses = computed(() => props.courses);
const assignments = computed(() => props.assignments);
const upcomingExams = computed(() => props.upcomingExams);
const leaderboardUsers = computed(() => props.leaderboardUsers);

const streak = computed(() => ({
    currentStreak: props.userStats.streak || 0,
    longestStreak: 12, // Still mock for now, can be clarified
    loginDates: ['2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09']
}));

const timeBasedGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

onMounted(() => {
    if (!dashboardContainer.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power4.out', duration: 1.1 }
    });

    gsap.set('.dashboard-hero', { opacity: 0, y: 40, scale: 0.98 });
    gsap.set('.dashboard-stats', { opacity: 0, y: 30, scale: 0.97 });
    gsap.set('.dashboard-leaderboard', { opacity: 0, y: 30, scale: 0.97 });
    gsap.set('.dashboard-main-grid', {
        opacity: 0,
        y: 40,
        scale: 0.97,
        rotationX: -8,
        transformOrigin: 'center top'
    });

    tl.to('.dashboard-hero', { opacity: 1, y: 0, scale: 1 });

    tl.to(
        '.dashboard-stats',
        { opacity: 1, y: 0, scale: 1, clearProps: 'transform,opacity' },
        '-=0.6'
    );

    tl.to(
        '.dashboard-leaderboard',
        { opacity: 1, y: 0, scale: 1, clearProps: 'transform,opacity' },
        '-=0.4'
    );

    tl.to(
        '.dashboard-main-grid',
        {
            opacity: 1,
            y: 0,
            scale: 1,
            rotationX: 0,
            clearProps: 'transform,opacity'
        },
        '-=0.3'
    );

    // Background orb animation refinement
    const orbs = dashboardContainer.value.querySelectorAll('.orb');
    orbs.forEach((orb, i) => {
        gsap.to(orb, {
            x: `random(-60, 60)`,
            y: `random(-60, 60)`,
            duration: 15 + i * 5,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });
    });
});

const handleQuickAction = (action: string) => {
    console.log('Quick action:', action);
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div ref="dashboardContainer" class="flex h-full flex-1 flex-col gap-8 p-4 md:p-10 relative overflow-hidden bg-background perspective-[1000px]">
            <!-- Glassy background decorative orbs -->
            <div class="orb absolute -top-48 -right-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
            <div class="orb absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

            <!-- Hero Banner Section -->
            <DashboardHero 
                class="animate-section dashboard-hero"
                :user-name="userName"
                :user-stats="userStats"
                :announcements="announcements"
                :total-x-p-progress="totalXPProgress"
                :time-based-greeting="timeBasedGreeting"
                @close-announcement="announcements = []"
            />

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
                    :users="leaderboardUsers" 
                    :user-rank="userStats.rankNumber"
                    :total-players="userStats.totalPlayers"
                    :active-season-name="activeSeason?.name"
                />
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-8 lg:grid-cols-3 animate-section stagger-4 dashboard-main-grid">
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
                <DashboardSidebar 
                    :unread-notification-count="3"
                    :weekly-x-p="userStats.currentXP" 
                    :weekly-goal="1000"
                    :upcoming-exams="upcomingExams"
                    @quick-action="handleQuickAction"
                />
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
