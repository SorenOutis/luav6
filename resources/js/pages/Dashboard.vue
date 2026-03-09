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

import { usePage } from '@inertiajs/vue3';

const page = usePage();
const userName = computed(() => page.props.auth.user?.name || 'User');

const props = defineProps<{
    userStats: {
        totalXP: number;
        level: number;
        currentXP: number;
        maxXPForLevel: number;
        rank: string;
        achievements: number;
        points: number;
    }
}>();

const userStats = computed(() => props.userStats);
const progressPercentage = computed(() => (userStats.value.currentXP / userStats.value.maxXPForLevel) * 100);
const totalXPProgress = computed(() => Math.min(100, (userStats.value.totalXP / 500000) * 100));

const announcements = ref([
    { id: 1, title: 'Season 6: The Great Ascent', description: 'New milestone rewards have been unlocked. Reach Level 15 to claim your exclusive emblem.' }
]);

const courses = ref([
    { id: 1, name: 'Full-Stack Development', progress: 65, completedLessons: 13, totalLessons: 20, xpEarned: 1200, nextDeadline: 'Tomorrow' },
    { id: 2, name: 'UI/UX Design Principles', progress: 30, completedLessons: 6, totalLessons: 20, xpEarned: 600, nextDeadline: 'Friday' }
]);

const assignments = ref([
    { id: 1, title: 'Database Migration Project', description: 'Complete the schema design and migration scripts.', dueDate: 'Mar 12', isOverdue: false, submitted: false, status: 'Pending', grade: null },
    { id: 2, title: 'Frontend UI Polish', description: 'Apply GSAP animations to the main dashboard sections.', dueDate: 'Mar 10', isOverdue: true, submitted: false, status: 'Pending', grade: null }
]);

const leaderboardUsers = ref([
    { id: 1, name: 'Alex Rivera', xp: 25000, trend: 'up' as const },
    { id: 2, name: 'Sarah Chen', xp: 22000, trend: 'up' as const },
    { id: 3, name: 'Jordan Hayes', xp: 21500, trend: 'down' as const },
    { id: 4, name: 'User', xp: 12540, trend: 'stable' as const, isCurrentUser: true },
    { id: 5, name: 'Michael Scott', xp: 11000, trend: 'up' as const },
    { id: 6, name: 'Pam Beesly', xp: 9500, trend: 'stable' as const },
    { id: 7, name: 'Dwight Schrute', xp: 9200, trend: 'up' as const },
    { id: 8, name: 'Jim Halpert', xp: 8800, trend: 'down' as const },
    { id: 9, name: 'Kelly Kapoor', xp: 8500, trend: 'up' as const },
    { id: 10, name: 'Stanley Hudson', xp: 7400, trend: 'stable' as const }
]);

const streak = ref({
    currentStreak: 5,
    longestStreak: 12,
    loginDates: ['2026-03-05', '2026-03-06', '2026-03-07', '2026-03-08', '2026-03-09']
});

const timeBasedGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
});

onMounted(() => {
    if (dashboardContainer.value) {
        // Premium Staggered Entrance Timeline
        const tl = gsap.timeline({
            defaults: { ease: "power4.out", duration: 1.2 }
        });

        tl.fromTo('.animate-section', 
            { 
                opacity: 0, 
                y: 40,
                scale: 0.98,
                rotationX: -10,
                visibility: 'visible'
            },
            { 
                opacity: 1, 
                y: 0, 
                scale: 1,
                rotationX: 0,
                stagger: 0.2,
                duration: 1.2,
                ease: "power4.out",
                clearProps: "all"
            },
            "+=0.1"
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
                ease: "sine.inOut"
            });
        });
    }
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
                class="animate-section"
                :user-name="userName"
                :user-stats="userStats"
                :announcements="announcements"
                :total-x-p-progress="totalXPProgress"
                :time-based-greeting="timeBasedGreeting"
                @close-announcement="announcements = []"
            />

            <!-- Header Section with User Stats -->
            <DashboardStats 
                class="animate-section stagger-2"
                :user-stats="userStats"
                :streak="streak"
                :progress-percentage="progressPercentage"
            />

            <!-- Improved Leaderboard -->
            <div class="animate-section stagger-3">
                <ImprovedLeaderboard :users="leaderboardUsers" />
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-8 lg:grid-cols-3 animate-section stagger-4">
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
