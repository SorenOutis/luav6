<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    BookOpen,
    BookMarked,
    ChevronRight,
    BarChart3,
    ArrowUpDown,
    Search,
    X,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import PageSkeleton from '@/components/PageSkeleton.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
import { useLoader } from '@/composables/useLoader';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

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

// ─── Daily Tips ───
interface ContextAwareTip {
    condition: (courses: Course[]) => boolean;
    generate: (courses: Course[]) => string;
}

const generalTips: string[] = [
    'Consistency beats intensity — study a little every day rather than cramming.',
    'Teaching someone else is one of the best ways to solidify what you\'ve learned.',
    'Take a 5-minute break every 25 minutes — your brain needs time to consolidate.',
    'Active recall (testing yourself) is far more effective than re-reading notes.',
    'Sleep is when your brain processes new information — don\'t skip it.',
    'Explaining a concept in simple terms reveals whether you truly understand it.',
    'Small daily progress adds up to extraordinary results over time.',
    'Take handwritten notes — the physical act of writing improves retention.',
    'Mix different subjects in a study session to build stronger mental connections.',
    'Review material within 24 hours of learning it to move it into long-term memory.',
    'Set specific goals for each study session — vague intentions lead to vague results.',
    'Your brain remembers what it thinks about — stay curious and ask questions.',
    'Errors are not failures — they\'re data. Adjust and move forward.',
    'Reading aloud engages more senses and improves recall.',
    'The best time to start was yesterday. The next best time is now.',
    // New general tips
    'Spaced repetition is the most efficient way to move knowledge into long-term memory — review at increasing intervals.',
    'The Pomodoro Technique: 25 minutes of focused work, 5 minutes break. Repeat for laser focus.',
    'Don\'t just highlight text — rephrase key ideas in your own words to truly understand them.',
    'Studying in a quiet, distraction-free environment boosts retention by up to 40%.',
    'Interleaving — mixing different topics — helps your brain build stronger pattern recognition.',
    'Use the Feynman Technique: explain a concept like you\'re teaching it to a child.',
    'A tidy study space leads to a tidy mind — organise your environment before you begin.',
    'Practice testing is one of the highest-impact study strategies known to learning science.',
    'Your brain is a muscle — the more you challenge it, the stronger it becomes.',
    'Perfectionism is the enemy of progress. Focus on completing, not perfecting.',
    'Memory palaces (method of loci) help you recall complex sequences using spatial memory.',
    'Study right before sleep — your brain consolidates memories and learnings during rest.',
    'A 20-minute walk can reset your focus and spark creative breakthroughs.',
    'The first 5 minutes of any study session are the hardest — just start and momentum will carry you.',
    'Curiosity is a superpower — follow your questions down rabbit holes to deepen understanding.',
    'Dual coding: pair words with diagrams or visuals for double the memory pathways.',
    'Put your phone on airplane mode — a distraction-free 30 minutes beats two hours of fractured attention.',
    'Write down intrusive thoughts as they come, then deal with them after your session ends.',
    'Mnemonics turn boring facts into memorable stories your brain can\'t forget.',
    'Every expert was once a beginner who refused to give up — keep going.',
];

const contextTips: ContextAwareTip[] = [
    {
        condition: (courses) => courses.some((c) => c.progress >= 75 && c.progress < 100),
        generate: (courses) => {
            const near = courses.filter((c) => c.progress >= 75 && c.progress < 100);
            if (near.length === 1) {
                return `You're ${near[0].progress}% through "${near[0].name}" — that last stretch is where champions are made. Keep pushing!`;
            }
            return `You have ${near.length} courses over 75% complete — the finish line is in sight for all of them!`;
        },
    },
    {
        condition: (courses) => {
            const active = courses.filter((c) => c.progress > 0 && c.progress < 100);
            return active.length >= 2;
        },
        generate: (courses) => {
            const active = courses.filter((c) => c.progress > 0 && c.progress < 100);
            return `You're juggling ${active.length} courses at once. Try the Pomodoro Technique — 25 minutes per course in rotation keeps things fresh.`;
        },
    },
    {
        condition: (courses) => courses.length > 0 && courses.every((c) => c.progress >= 100),
        generate: (courses) => {
            return `You've completed all ${courses.length} courses! 🎉 That's an achievement — take a moment to celebrate before your next enrollment.`;
        },
    },
    {
        condition: (courses) => courses.length === 0,
        generate: () => {
            return 'You\'re not enrolled in any courses yet — your next learning adventure is just one enrollment away.';
        },
    },
    {
        condition: (courses) => {
            const count = courses.filter((c) => c.progress > 0 && c.progress < 25).length;
            return count === courses.length && courses.length > 0;
        },
        generate: () => {
            return 'Every course starts slow. The secret? Consistent 10-minute daily sessions build unstoppable momentum over time.';
        },
    },
    {
        condition: (courses) => {
            const totalDone = courses.reduce((sum, c) => sum + c.completedLessons, 0);
            return totalDone > 0 && totalDone < 5;
        },
        generate: () => {
            return 'You\'ve started making progress! The first few lessons are always the hardest — keep showing up and the habit will carry you.';
        },
    },
    {
        condition: (courses) => courses.some((c) => c.completedLessons > 0 && c.progress < 100),
        generate: (courses) => {
            const best = courses.reduce((a, b) => (a.completedLessons > b.completedLessons ? a : b));
            return `You've done ${best.completedLessons} of ${best.totalLessons} lessons in "${best.name}" — consistency is building momentum!`;
        },
    },
    {
        condition: (courses) => courses.length > 0,
        generate: (courses) => {
            const totalLessons = courses.reduce((sum, c) => sum + c.totalLessons, 0);
            const totalDone = courses.reduce((sum, c) => sum + c.completedLessons, 0);
            const totalXp = courses.reduce((sum, c) => sum + c.xpEarned, 0);
            return `Across ${courses.length} course${courses.length > 1 ? 's' : ''}, you've completed ${totalDone} of ${totalLessons} lessons and earned ${totalXp} XP. Every bit counts!`;
        },
    },
    {
        condition: (courses) => {
            const near = courses.filter((c) => c.progress >= 75 && c.progress < 100);
            const others = courses.filter((c) => c.progress < 75 && c.progress > 0);
            return near.length > 0 && others.length > 0;
        },
        generate: (courses) => {
            const near = courses.filter((c) => c.progress >= 75 && c.progress < 100);
            const name = near.length === 1 ? `"${near[0].name}"` : `${near.length} courses`;
            return `You're close to finishing ${name}. A final push now saves you from starting over later!`;
        },
    },
];

const STORAGE_KEY_TIP = 'luav6-course-tip';
const STORAGE_KEY_DISMISS = 'luav6-course-tip-dismissed';

const currentTipText = ref('');
const tipType = ref<'general' | 'context'>('general');
const tipKey = ref(0);

function isDismissedToday(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY_DISMISS) === getTodayDate();
    } catch {
        return false;
    }
}

function getTodayDate(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const showTip = ref(!isDismissedToday());

function dismissTip() {
    showTip.value = false;
    try {
        localStorage.setItem(STORAGE_KEY_DISMISS, getTodayDate());
    } catch {
        // localStorage unavailable
    }
}

function getSavedTip(): string | null {
    try {
        return localStorage.getItem(STORAGE_KEY_TIP);
    } catch {
        return null;
    }
}

function saveTip(tip: string) {
    try {
        localStorage.setItem(STORAGE_KEY_TIP, tip);
    } catch {
        // localStorage unavailable (private browsing, etc.)
    }
}

onMounted(() => {
    pickRandomTip();
});

const pickRandomTip = () => {
    const lastTip = getSavedTip();
    const contextEligible = contextTips.filter((t) => t.condition(props.courses));
    const tryContext = Math.random() < 0.3 && contextEligible.length > 0;

    let picked = '';
    let attempts = 0;
    const maxAttempts = 20;

    do {
        if (tryContext) {
            const pickedContext = contextEligible[Math.floor(Math.random() * contextEligible.length)];
            picked = pickedContext.generate(props.courses);
            tipType.value = 'context';
        } else {
            picked = generalTips[Math.floor(Math.random() * generalTips.length)];
            tipType.value = 'general';
        }
        attempts++;
    } while (picked === lastTip && attempts < maxAttempts);

    currentTipText.value = picked;
    saveTip(picked);
    tipKey.value++;
};

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


                <!-- Daily Tip -->
                <Motion
                    v-if="showTip"
                    :key="'tip-' + tipKey"
                    :initial="{ opacity: 0, y: 30 }"
                    :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                    :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.2 }"
                    class="relative z-10"
                >
                    <div class="relative overflow-hidden rounded-2xl border border-border/20 bg-gradient-to-br from-primary/[0.04] via-muted/[0.02] to-transparent p-6 md:p-8">
                        <!-- Decorative glow -->
                        <div class="pointer-events-none absolute -right-20 -top-20 h-40 w-40 rounded-full bg-primary/5 blur-[60px]"></div>
                        <div class="relative flex flex-col gap-4">
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-sm transition-colors duration-500"
                                    :class="tipType === 'context'
                                        ? 'bg-amber-500/15 text-amber-500/80'
                                        : 'bg-primary/10 text-primary/70'"
                                >
                                    {{ tipType === 'context' ? '🎯' : '✨' }}
                                </span>
                                <span
                                    class="text-[10px] font-black tracking-[0.3em] uppercase transition-colors duration-500"
                                    :class="tipType === 'context'
                                        ? 'text-amber-500/70'
                                        : 'text-primary/60'"
                                >
                                    {{ tipType === 'context' ? 'Your Progress' : 'Daily Insight' }}
                                </span>
                            </div>
                            <p class="text-lg leading-relaxed font-medium tracking-tight text-foreground/90 md:text-2xl md:leading-snug">
                                &ldquo;{{ currentTipText }}&rdquo;
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="dismissTip"
                                    class="shrink-0 rounded-lg border border-border/30 px-3 py-2 text-[10px] font-black tracking-[0.2em] text-muted-foreground/40 uppercase transition-all hover:border-foreground/20 hover:text-foreground/60"
                                    title="Dismiss"
                                >
                                    Got it
                                </button>
                                <div class="h-px flex-1 bg-gradient-to-r from-primary/20 via-border/20 to-transparent"></div>
                                <button
                                    @click="pickRandomTip"
                                    class="shrink-0 rounded-lg border border-border/30 px-4 py-2 text-[10px] font-black tracking-[0.2em] text-muted-foreground/50 uppercase transition-all hover:border-primary/40 hover:text-primary/70"
                                    title="New tip"
                                >
                                    Shuffle
                                </button>
                            </div>
                        </div>
                    </div>
                </Motion>

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
                            <div class="scrollbar-none flex overflow-x-auto rounded-xl border border-border/40 bg-background/60 p-0.5">
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
