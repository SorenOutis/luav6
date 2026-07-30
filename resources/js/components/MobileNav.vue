<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import {
    LayoutGrid,
    BookOpen,
    ClipboardList,
    GraduationCap,
    Gamepad2,
    MoreHorizontal,
    Award,
    Settings,
    LogOut,
    Zap,
    Flame,
    ChevronRight,
} from 'lucide-vue-next';
import { useTimeoutFn } from '@vueuse/core';
import { computed, ref, onMounted, watch, nextTick } from 'vue';
import MobileBottomSheet from '@/components/MobileBottomSheet.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import { edit } from '@/routes/profile';
import { index as gamesIndex } from '@/routes/games';
import { index as examsIndex } from '@/routes/exams';
import { index as assignmentsIndex } from '@/routes/assignments';
import { index as coursesIndex } from '@/routes/courses';
import { grades, logout } from '@/routes';

const page = usePage();
const { getInitials } = useInitials();

const user = computed(() => page.props.auth?.user);
const userStats = computed(
    () =>
        (page.props as any).userStats as
            | {
                  totalXP?: number;
                  level?: number;
                  streak?: number;
                  currentXP?: number;
                  maxXPForLevel?: number;
              }
            | undefined,
);

const level = computed(() => userStats.value?.level ?? 0);
const streak = computed(() => userStats.value?.streak ?? 0);
const hasGamificationData = computed(
    () =>
        (userStats.value?.totalXP ?? 0) > 0 ||
        (userStats.value?.level ?? 0) > 0,
);

const isMoreSheetOpen = ref(false);

const navItems = computed(() =>
    [
        {
            label: 'Home',
            href: dashboard.url(),
            icon: LayoutGrid,
            studentPageKey: 'dashboard',
        },
        {
            label: 'Courses',
            href: coursesIndex().url,
            icon: BookOpen,
            studentPageKey: 'courses',
        },
        {
            label: 'Exams',
            href: examsIndex().url,
            icon: GraduationCap,
            studentPageKey: 'exams',
        },
        {
            label: 'Assignments',
            href: assignmentsIndex().url,
            icon: ClipboardList,
            studentPageKey: 'assignments',
        },
        {
            label: 'Games',
            href: gamesIndex().url,
            icon: Gamepad2,
            studentPageKey: 'games',
        },
        {
            label: 'More',
            href: '#more',
            icon: MoreHorizontal,
            studentPageKey: null, // always visible
        },
    ].filter(
        (item) =>
            item.studentPageKey === null ||
            page.props.studentPageControls?.pages?.[item.studentPageKey]
                ?.mode !== 'disabled',
    ),
);

// More menu items (shown in the bottom sheet)
const moreMenuItems = computed(() =>
    [
        {
            label: 'Grades',
            href: grades().url,
            icon: Award,
            description: 'View your grades and scores',
            studentPageKey: 'grades',
        },
        {
            label: 'Profile',
            href: edit.url(),
            icon: Settings,
            description: 'Account settings & preferences',
            studentPageKey: 'profile',
        },
    ].filter(
        (item) =>
            item.studentPageKey === null ||
            page.props.studentPageControls?.pages?.[item.studentPageKey]
                ?.mode !== 'disabled',
    ),
);

// --- Active Route Detection ---
const isActive = (href: string) => {
    if (href === '#more') return false; // More is handled by sheet

    const currentPath = page.url.split('?')[0];

    if (href === '/' || href === dashboard.url()) {
        return (
            currentPath === href ||
            currentPath === '/dashboard' ||
            currentPath === '/'
        );
    }
    return currentPath.startsWith(href);
};

const activeIndex = computed(() => {
    return navItems.value.findIndex((item) => isActive(item.href));
});

// --- GSAP Animated Indicator ---
const indicatorRef = ref<HTMLElement | null>(null);
const itemRefs = ref<(HTMLElement | null)[]>([]);

function animateIndicator() {
    if (
        activeIndex.value !== -1 &&
        activeIndex.value < navItems.value.length &&
        itemRefs.value[activeIndex.value] &&
        indicatorRef.value &&
        typeof (itemRefs.value[activeIndex.value] as any).getBoundingClientRect === 'function'
    ) {
        const target = itemRefs.value[activeIndex.value]!;
        const targetRect = target.getBoundingClientRect();
        const parentRect =
            target.parentElement?.getBoundingClientRect() ?? { left: 0 };

        gsap.to(indicatorRef.value, {
            x: targetRect.left - parentRect.left + targetRect.width / 2 - 20,
            opacity: 1,
            width: 40,
            duration: 0.6,
            ease: 'power4.out',
        });
    } else if (indicatorRef.value) {
        gsap.to(indicatorRef.value, {
            opacity: 0,
            duration: 0.3,
        });
    }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function setItemRef(el: any, index: number) {
    itemRefs.value[index] = el as HTMLElement | null;
}

onMounted(() => {
    // Small delay to let DOM paint. useTimeoutFn cancels on unmount — a bare
    // setTimeout would fire into a destroyed component after navigation.
    useTimeoutFn(animateIndicator, 150);
});

watch(activeIndex, () => {
    nextTick(animateIndicator);
});

// --- Navigation click ---
const handleNavClick = (index: number) => {
    const item = navItems.value[index];
    if (item.label === 'More') {
        isMoreSheetOpen.value = true;
    }
};

// --- Logout ---
const handleLogout = () => {
    sessionStorage.setItem('logged_out', 'true');
    router.post(logout());
};

</script>

<template>
    <!-- Bottom Navigation Bar -->
    <nav
        aria-label="Mobile navigation"
        class="fixed right-0 bottom-0 left-0 z-50 mx-auto w-full md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)"
    >
        <div
            class="relative mx-2 mb-2 flex h-16 items-center justify-around overflow-hidden rounded-2xl border border-border/60 bg-background/85 px-4 shadow-2xl shadow-black/10 backdrop-blur-3xl dark:bg-zinc-950/90 dark:shadow-black/30"
        >
            <!-- Sliding Active Indicator -->
            <div
                ref="indicatorRef"
                class="pointer-events-none absolute top-1 z-0 h-1 w-10 rounded-full bg-primary opacity-0"
            />

            <template v-for="(item, index) in navItems" :key="item.label">
                <!-- More button (opens sheet) -->
                <button
                    v-if="item.label === 'More'"
                    :ref="(el) => setItemRef(el, index)"
                    aria-haspopup="dialog"
                    :aria-expanded="isMoreSheetOpen"
                    class="relative z-10 flex flex-1 flex-col items-center justify-center gap-0.5 py-2 transition-all outline-none active:scale-90"
                    @click="handleNavClick(index)"
                >
                    <div
                        class="flex items-center justify-center transition-all duration-500"
                    >
                        <component
                            :is="item.icon"
                            class="size-5.5 text-muted-foreground stroke-[1.5px] transition-all duration-500"
                        />
                    </div>
                    <span
                        class="text-[7px] font-black tracking-[0.12em] uppercase text-muted-foreground opacity-60 transition-all duration-500"
                    >
                        {{ item.label }}
                    </span>
                </button>

                <!-- Regular Link -->
                <Link
                    v-else
                    :href="item.href"
                    :ref="(el) => setItemRef(el, index)"
                    class="relative z-10 flex flex-1 flex-col items-center justify-center gap-0.5 py-2 transition-all outline-none active:scale-90"
                    @click="handleNavClick(index)"
                >
                    <div
                        class="flex items-center justify-center transition-all duration-500"
                        :class="[
                            activeIndex === index ? 'scale-110' : '',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="transition-all duration-500"
                            :class="
                                activeIndex === index
                                    ? 'size-6 text-primary stroke-[2.5px]'
                                    : 'size-5.5 text-muted-foreground stroke-[1.5px]'
                            "
                        />
                    </div>

                    <span
                        class="text-[7px] font-black tracking-[0.12em] uppercase transition-all duration-500"
                        :class="[
                            activeIndex === index
                                ? 'text-primary opacity-100'
                                : 'text-muted-foreground opacity-60',
                        ]"
                    >
                        {{ item.label }}
                    </span>
                </Link>
            </template>
        </div>
    </nav>

    <!-- ─── MORE BOTTOM SHEET ─────────────────────────────────────── -->
    <MobileBottomSheet
        :open="isMoreSheetOpen"
        title="Menu"
        @close="isMoreSheetOpen = false"
    >
        <!-- User Profile Preview -->
        <div
            v-if="user"
            class="sheet-item mx-2 mb-4 mt-2 flex items-center gap-3 rounded-2xl border border-border/50 bg-muted/30 p-3"
        >
            <Avatar class="h-10 w-10 overflow-hidden rounded-xl border border-border/50">
                <AvatarImage
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                />
                <AvatarFallback class="rounded-xl bg-primary/10 text-sm font-bold text-primary">
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-foreground">
                    {{ user.name }}
                </p>
                <p
                    v-if="user.email"
                    class="truncate text-[10px] font-medium text-muted-foreground"
                >
                    {{ user.email }}
                </p>
            </div>

            <!-- Gamification Peek -->
            <div
                v-if="hasGamificationData"
                class="flex shrink-0 items-center gap-2"
            >
                <div
                    v-if="level > 0"
                    class="flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-[10px] font-black text-primary"
                >
                    <Zap class="h-3 w-3" />
                    {{ level }}
                </div>
                <div
                    v-if="streak > 0"
                    class="flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-[10px] font-black text-amber-500"
                >
                    <Flame class="h-3 w-3" />
                    {{ streak }}
                </div>
            </div>
        </div>

        <!-- Navigation Items -->
        <div class="sheet-section space-y-0.5 px-1">
            <Link
                v-for="item in moreMenuItems"
                :key="item.label"
                :href="item.href"
                class="sheet-item flex items-center gap-3 rounded-xl px-3 py-3.5 transition-all hover:bg-muted/50 active:scale-[0.98]"
                @click="isMoreSheetOpen = false"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted/60 text-muted-foreground"
                >
                    <component :is="item.icon" class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">
                        {{ item.label }}
                    </p>
                    <p
                        v-if="item.description"
                        class="text-[10px] font-medium text-muted-foreground"
                    >
                        {{ item.description }}
                    </p>
                </div>
                <ChevronRight
                    class="h-4 w-4 shrink-0 text-muted-foreground/40"
                />
            </Link>
        </div>

        <!-- Divider -->
        <div class="sheet-section mx-3 my-3 h-px bg-border/60" />

        <!-- Logout -->
        <div class="sheet-section px-1 pb-2">
            <button
                @click="handleLogout"
                class="sheet-item flex w-full items-center gap-3 rounded-xl px-3 py-3.5 text-left transition-all hover:bg-rose-500/10 active:scale-[0.98]"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500"
                >
                    <LogOut class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-rose-500">Log Out</p>
                    <p class="text-[10px] font-medium text-rose-400/60">
                        Sign out of your account
                    </p>
                </div>
                <ChevronRight class="h-4 w-4 shrink-0 text-rose-300/40" />
            </button>
        </div>
    </MobileBottomSheet>
</template>

<style scoped>
/* Ensure the actual padding from env() takes effect */
nav {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
