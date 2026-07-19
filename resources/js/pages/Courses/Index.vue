<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    BookOpen,
    BookMarked,
    ChevronRight,
    BarChart3,
    Zap,
    ArrowUpDown,
    Search,
    X,
    Filter,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import { useMobile } from '@/composables/useMobile';
import PageSkeleton from '@/components/PageSkeleton.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

interface Course {
    id: number;
    name: string;
    description: string | null;
    cover_photo: string | null;
    totalLessons: number;
    completedLessons: number;
    progress: number;
    xpEarned: number;
    modulesCount: number;
}

const props = defineProps<{
    courses: Course[];
}>();

const { isVisible: isLoaderVisible } = useLoader();
const { isMobile: isMobileDevice } = useMobile();
const isBooted = ref(false);

if (!isLoaderVisible.value) {
    isBooted.value = true;
}

watch(isLoaderVisible, (visible) => {
    if (!visible) {
        isBooted.value = true;
    }
}, { immediate: true });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Courses', href: '/courses' },
];

// ─── Search & Filter State ───
const searchQuery = ref('');
const sortBy = ref<'progress' | 'name' | 'lessons'>('progress');
const filterStatus = ref<'all' | 'in-progress' | 'completed'>('all');

const filteredCourses = computed(() => {
    let list = [...props.courses];

    // Search filter
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => c.name.toLowerCase().includes(q));
    }

    // Status filter
    if (filterStatus.value === 'in-progress') {
        list = list.filter(c => c.progress > 0 && c.progress < 100);
    } else if (filterStatus.value === 'completed') {
        list = list.filter(c => c.progress >= 100);
    }

    // Sort
    if (sortBy.value === 'progress') {
        list.sort((a, b) => b.progress - a.progress);
    } else if (sortBy.value === 'name') {
        list.sort((a, b) => a.name.localeCompare(b.name));
    } else if (sortBy.value === 'lessons') {
        list.sort((a, b) => b.completedLessons - a.completedLessons);
    }

    return list;
});

const stats = computed(() => [
    {
        label: 'Enrolled Courses',
        value: props.courses.length,
        icon: BookMarked,
        color: 'primary' as const,
        sub: `${props.courses.filter(c => c.progress >= 100).length} completed`,
    },
    {
        label: 'Total Lessons',
        value: props.courses.reduce((acc, c) => acc + c.totalLessons, 0),
        icon: BarChart3,
        color: 'primary' as const,
        sub: `${props.courses.reduce((acc, c) => acc + c.completedLessons, 0)} done`,
    },
    {
        label: 'In Progress',
        value: props.courses.filter(c => c.progress > 0 && c.progress < 100).length,
        icon: BookOpen,
        color: 'blue' as const,
        sub: 'actively learning',
    },
    {
        label: 'XP Earned',
        value: props.courses.reduce((acc, c) => acc + c.xpEarned, 0),
        icon: Zap,
        color: 'amber' as const,
        sub: 'total experience',
    },
]);
</script>

<template>
    <Head title="My Courses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Skeleton Loading -->
        <template v-if="!isBooted">
            <div class="relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 md:p-10">
                <PageSkeleton :hero="true" :stats="4" variant="minimal" wrapperClass="z-10 mb-4" />
                <div class="z-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="i in 3" :key="i" class="h-72 animate-pulse rounded-xl border border-border/10 bg-card/30"></div>
                </div>
            </div>
        </template>

        <!-- Real Content -->
        <template v-if="isBooted">
            <div class="relative flex h-full flex-1 flex-col gap-8 overflow-hidden bg-background p-4 md:p-10">
                <!-- Decorative Orbs -->
                <div v-if="!isMobileDevice" class="pointer-events-none absolute -top-48 -right-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"></div>
                <div v-if="!isMobileDevice" class="pointer-events-none absolute -bottom-48 -left-48 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[120px]"></div>

                <!-- Header -->
                <Motion
                    :initial="{ opacity: 0, y: 30 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.1 }"
                    class="relative z-10"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 shadow-lg shadow-primary/5">
                            <BookOpen class="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-black tracking-tight">My Courses</h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Continue your learning journey
                            </p>
                        </div>
                    </div>
                </Motion>

                <!-- Stats -->
                <div class="relative z-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Motion
                        v-for="(stat, idx) in stats"
                        :key="stat.label"
                        :initial="{ opacity: 0, y: 20 }"
                        :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                        :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.2 + idx * 0.1 }"
                    >
                        <div class="surface-card flex items-center gap-3 rounded-xl border border-border/40 p-4">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl"
                                :class="{
                                    'bg-primary/10 text-primary': stat.color === 'primary',
                                    'bg-emerald-500/10 text-emerald-500': stat.color === 'green',
                                    'bg-amber-500/10 text-amber-500': stat.color === 'amber',
                                    'bg-blue-500/10 text-blue-500': stat.color === 'blue',
                                }"
                            >
                                <component :is="stat.icon" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-2xl font-black tabular-nums">{{ stat.value }}</p>
                                <p class="text-[10px] font-medium text-muted-foreground/60">{{ stat.label }}</p>
                                <p v-if="stat.sub" class="text-[9px] text-muted-foreground/40">{{ stat.sub }}</p>
                            </div>
                        </div>
                    </Motion>
                </div>

                <!-- Search, Filter & Sort Bar -->
                <Motion
                    :initial="{ opacity: 0, y: 20 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{ duration: 0.6, ease: [0.16, 1, 0.3, 1], delay: 0.4 }"
                    class="relative z-10"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Search Input -->
                        <div class="relative flex-1 max-w-md">
                            <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/40" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search courses..."
                                class="w-full rounded-xl border border-border/40 bg-background/60 py-2.5 pl-10 pr-9 text-sm outline-none transition-all placeholder:text-muted-foreground/40 focus:border-primary/30 focus:ring-2 focus:ring-primary/10"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg p-0.5 text-muted-foreground/40 transition-colors hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Status Filter -->
                            <div class="flex overflow-hidden rounded-xl border border-border/40 bg-background/60 p-0.5">
                                <button
                                    v-for="tab in [{ key: 'all', label: 'All' }, { key: 'in-progress', label: 'Active' }, { key: 'completed', label: 'Done' }]"
                                    :key="tab.key"
                                    @click="filterStatus = tab.key as typeof filterStatus"
                                    class="rounded-lg px-3 py-1.5 text-[11px] font-bold transition-all"
                                    :class="filterStatus === tab.key
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'text-muted-foreground/60 hover:text-foreground'"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>

                            <!-- Sort -->
                            <div class="relative">
                                <select
                                    v-model="sortBy"
                                    class="appearance-none rounded-xl border border-border/40 bg-background/60 py-2.5 pl-3 pr-8 text-[11px] font-bold text-muted-foreground outline-none transition-all focus:border-primary/30 focus:ring-2 focus:ring-primary/10"
                                >
                                    <option value="progress">By Progress</option>
                                    <option value="name">A–Z</option>
                                    <option value="lessons">Most Done</option>
                                </select>
                                <ArrowUpDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground/40" />
                            </div>
                        </div>
                    </div>
                </Motion>

                <!-- Course Grid -->
                <div v-if="filteredCourses.length > 0" class="relative z-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Motion
                        v-for="(course, idx) in filteredCourses"
                        :key="course.id"
                        :initial="{ opacity: 0, y: 40 }"
                        :in-view="isBooted ? { opacity: 1, y: 0 } : {}"
                        :in-view-options="{ once: true, margin: '-50px' }"
                        :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: idx * 0.1 }"
                    >
                        <Link
                            :href="`/courses/${course.id}`"
                            class="group/card surface-card premium-hover relative flex flex-col overflow-hidden rounded-2xl border border-border/40 transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:shadow-primary/10"
                        >
                            <!-- Cover Image -->
                            <div class="relative h-44 overflow-hidden bg-gradient-to-br from-primary/20 via-primary/10 to-muted">
                                <img
                                    v-if="course.cover_photo"
                                    :src="course.cover_photo"
                                    :alt="course.name"
                                    class="h-full w-full object-cover transition-all duration-700 group-hover/card:scale-105"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <BookOpen class="h-16 w-16 text-primary/20" />
                                </div>
                                <!-- Overlay gradient -->
                                <div class="absolute inset-0 bg-gradient-to-t from-background/80 via-background/20 to-transparent"></div>

                                <!-- Status badges -->
                                <div class="absolute right-3 top-3 flex gap-2">
                                    <div
                                        v-if="course.progress >= 100"
                                        class="rounded-full border border-emerald-500/20 bg-emerald-500/80 px-3 py-1 text-[10px] font-black tracking-wider text-white shadow-lg backdrop-blur-sm"
                                    >
                                        ✓ Done
                                    </div>
                                    <div
                                        v-else
                                        class="rounded-full border border-white/20 bg-background/60 px-3 py-1 text-[10px] font-black tracking-wider text-white shadow-lg backdrop-blur-sm"
                                    >
                                        {{ course.progress }}%
                                    </div>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="text-lg font-bold tracking-tight transition-colors duration-500 group-hover/card:text-primary">
                                    {{ course.name }}
                                </h3>
                                <p v-if="course.description" class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground/60">
                                    {{ course.description }}
                                </p>

                                <!-- Meta -->
                                <div class="mt-auto flex items-center justify-between pt-4">
                                    <div class="flex items-center gap-3 text-[11px] text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <BookMarked class="h-3.5 w-3.5" />
                                            {{ course.modulesCount }} modules
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <BarChart3 class="h-3.5 w-3.5" />
                                            {{ course.completedLessons }}/{{ course.totalLessons }}
                                        </span>
                                    </div>
                                    <ChevronRight class="h-4 w-4 text-muted-foreground/40 transition-all duration-500 group-hover/card:translate-x-1 group-hover/card:text-primary" />
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-1000"
                                        :class="course.progress >= 100 ? 'bg-emerald-500' : 'bg-primary'"
                                        :style="{ width: `${course.progress}%` }"
                                    ></div>
                                </div>
                            </div>
                        </Link>
                    </Motion>
                </div>

                <!-- Empty State (no results after filter) -->
                <Motion
                    v-else-if="props.courses.length > 0"
                    :initial="{ opacity: 0, scale: 0.95 }"
                    :animate="{ opacity: 1, scale: 1 }"
                    :transition="{ duration: 0.4 }"
                    class="relative z-10 flex flex-col items-center justify-center py-24"
                >
                    <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-3xl border border-dashed border-border/40 bg-muted/10">
                        <Search class="h-8 w-8 text-muted-foreground/30" />
                    </div>
                    <h3 class="text-lg font-bold text-muted-foreground/60">No matches found</h3>
                    <p class="mt-2 text-sm text-muted-foreground/40">
                        Try a different search term or filter.
                    </p>
                    <button
                        @click="searchQuery = ''; filterStatus = 'all'"
                        class="mt-4 rounded-xl border border-border/40 px-4 py-2 text-xs font-bold text-muted-foreground/60 transition-colors hover:border-primary/30 hover:text-primary"
                    >
                        Clear filters
                    </button>
                </Motion>

                <!-- Empty State (no courses at all) -->
                <Motion
                    v-else
                    :initial="{ opacity: 0, scale: 0.95 }"
                    :animate="{ opacity: 1, scale: 1 }"
                    :transition="{ duration: 0.6 }"
                    class="relative z-10 flex flex-col items-center justify-center py-24"
                >
                    <div class="mb-6 flex h-24 w-24 items-center justify-center rounded-3xl border border-dashed border-border/40 bg-muted/10">
                        <BookOpen class="h-10 w-10 text-muted-foreground/30" />
                    </div>
                    <h3 class="text-xl font-bold text-muted-foreground/60">No courses yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground/40">
                        Your enrolled courses will appear here once your instructor assigns them.
                    </p>
                </Motion>
            </div>
        </template>
    </AppLayout>
</template>
