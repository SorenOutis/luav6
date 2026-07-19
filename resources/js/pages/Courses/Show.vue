<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import {
    BookOpen,
    BookMarked,
    CheckCircle2,
    Circle,
    ChevronRight,
    ChevronLeft,
    PlayCircle,
    BarChart3,
    Trophy,
    ListChecks,
    Sparkles,
    GraduationCap,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

interface Lesson {
    id: number;
    title: string;
    sortOrder: number;
    completed: boolean;
    quizScore: number | null;
    hasQuiz: boolean;
}

interface Module {
    id: number;
    title: string;
    description: string | null;
    sortOrder: number;
    lessons: Lesson[];
}

interface CourseData {
    id: number;
    name: string;
    description: string | null;
    cover_photo: string | null;
    totalLessons: number;
    completedLessons: number;
    progress: number;
}

const props = defineProps<{
    course: CourseData;
    modules: Module[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Courses', href: '/courses' },
    { title: props.course.name, href: `/courses/${props.course.id}` },
];

// ─── Horizontal Scroll Refs ───
const scrollContainer = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);
const activeModuleIndex = ref(0);

const scrollModules = (direction: 'left' | 'right') => {
    const el = scrollContainer.value;
    if (!el) return;
    const scrollAmt = el.clientWidth * 0.7;
    el.scrollBy({
        left: direction === 'left' ? -scrollAmt : scrollAmt,
        behavior: 'smooth',
    });
};

const updateScrollState = () => {
    const el = scrollContainer.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 10;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 10;

    // Determine which module is most visible
    const cards = el.querySelectorAll('[data-module-index]');
    if (!cards.length) return;
    const containerRect = el.getBoundingClientRect();
    const containerCenterX = containerRect.left + containerRect.width / 2;
    let bestIdx = 0;
    let bestVisible = 0;
    cards.forEach((card, i) => {
        const rect = card.getBoundingClientRect();
        const cardCenter = rect.left + rect.width / 2;
        const dist = Math.abs(cardCenter - containerCenterX);
        const visibility = 1 - dist / containerRect.width;
        if (visibility > bestVisible) {
            bestVisible = visibility;
            bestIdx = i;
        }
    });
    activeModuleIndex.value = bestIdx;
};

onMounted(() => {
    const el = scrollContainer.value;
    if (el) {
        el.addEventListener('scroll', updateScrollState);
        updateScrollState();
    }

    // Auto-scroll to first incomplete module
    const firstIncompleteIdx = props.modules.findIndex(mod =>
        mod.lessons.some(l => !l.completed)
    );
    if (firstIncompleteIdx > 0 && el) {
        const target = el.children[firstIncompleteIdx] as HTMLElement;
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }, 300);
        }
    }

    // Fire confetti celebration if course is completed
    if (props.course.progress >= 100) {
        setTimeout(() => {
            if (document.querySelector('.course-header')) {
                burstConfetti();
                setTimeout(burstConfetti, 500);
            }
        }, 600);
    }
});

onUnmounted(() => {
    const el = scrollContainer.value;
    if (el) {
        el.removeEventListener('scroll', updateScrollState);
    }
});

const firstIncompleteLesson = computed(() => {
    for (const mod of props.modules) {
        for (const lesson of mod.lessons) {
            if (!lesson.completed) return lesson;
        }
    }
    return null;
});

// ─── Module Progress ───
const moduleProgress = computed(() => {
    return props.modules.map(mod => {
        const total = mod.lessons.length;
        const done = mod.lessons.filter(l => l.completed).length;
        return {
            id: mod.id,
            total,
            done,
            pct: total > 0 ? Math.round((done / total) * 100) : 0,
        };
    });
});

const mp = (modId: number) => moduleProgress.value.find(m => m.id === modId)!;

// ─── Confetti ───
const burstConfetti = () => {
    const colors = [
        'var(--color-primary)',
        '#22c55e',
        '#f59e0b',
        '#a78bfa',
        '#f472b6',
        '#60a5fa',
        '#34d399',
        '#fb923c',
    ];

    const header = document.querySelector('.course-header');
    if (!header) return;
    const rect = header.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height;

    for (let i = 0; i < 60; i++) {
        const el = document.createElement('div');
        const size = 6 + Math.random() * 8;
        const color = colors[Math.floor(Math.random() * colors.length)];
        const isCircle = Math.random() > 0.5;

        el.style.cssText = `
            position: fixed;
            z-index: 9999;
            pointer-events: none;
            width: ${isCircle ? size : size * 0.6}px;
            height: ${size}px;
            background: ${color};
            border-radius: ${isCircle ? '50%' : '2px'};
            left: ${centerX}px;
            top: ${centerY}px;
            opacity: 1;
        `;
        document.body.appendChild(el);

        const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.2;
        const velocity = 400 + Math.random() * 600;
        const vx = Math.cos(angle) * velocity;
        const vy = Math.sin(angle) * velocity;
        const rotation = Math.random() * 720 - 360;
        const gravity = 800;
        const duration = 1.5 + Math.random() * 1;

        gsap.to(el, {
            x: vx * 0.5,
            y: vy * 0.5 + 0.5 * gravity * 0.25,
            rotation,
            opacity: 0,
            scale: 0.3,
            duration,
            ease: 'power2.out',
            onComplete: () => el.remove(),
        });
    }
};


</script>

<template>
    <Head :title="course.name" />

    <AppLayout :breadcrumbs="breadcrumbs">                        <div class="relative flex h-full flex-1 flex-col overflow-hidden bg-background">
            <!-- Course Header -->
            <div class="course-header relative overflow-hidden border-b border-border/10">
                <!-- Background -->
                <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-primary/5 to-background"></div>
                <div v-if="course.cover_photo" class="absolute inset-0">
                    <img :src="course.cover_photo" :alt="course.name" class="h-full w-full object-cover opacity-20" />
                </div>
                <div class="absolute inset-0 bg-gradient-to-r from-background/80 via-background/60 to-background/40"></div>

                <div class="relative z-10 p-6 md:p-10">
                    <Motion
                        :initial="{ opacity: 0, y: 20 }"
                        :animate="{ opacity: 1, y: 0 }"
                        :transition="{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }"
                    >
                        <div class="flex items-start justify-between gap-6">
                            <div class="flex-1">
                                <div class="mb-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <BookOpen class="h-4 w-4" />
                                    <span>Course</span>
                                    <ChevronRight class="h-3 w-3" />
                                    <span class="text-foreground/60">{{ course.name }}</span>
                                </div>
                                <h1 class="text-3xl font-black tracking-tight md:text-4xl">{{ course.name }}</h1>
                                <p v-if="course.description" class="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground/70">
                                    {{ course.description }}
                                </p>

                                <!-- Stats -->
                                <div class="mt-6 flex flex-wrap items-center gap-6">
                                    <div class="flex items-center gap-2">
                                        <BarChart3 class="h-4 w-4 text-primary" />
                                        <span class="text-sm font-medium">{{ course.completedLessons }} / {{ course.totalLessons }} lessons</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <BookMarked class="h-4 w-4 text-primary" />
                                        <span class="text-sm font-medium">{{ modules.length }} modules</span>
                                    </div>
                                </div>

                                <!-- Course Progress Bar -->
                                <div class="mt-4 max-w-md space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-medium text-foreground/60">Course Progress</span>
                                        <span class="font-black tabular-nums" :class="course.progress >= 100 ? 'text-emerald-500' : 'text-primary'">
                                            {{ course.progress >= 100 ? '🎉 100%' : course.progress + '%' }}
                                        </span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full transition-all duration-1000"
                                            :class="course.progress >= 100 ? 'bg-emerald-500' : 'bg-primary'"
                                            :style="{ width: `${course.progress}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Completion Celebration -->
                                <Motion
                                    v-if="course.progress >= 100"
                                    :initial="{ opacity: 0, y: 10, scale: 0.95 }"
                                    :animate="{ opacity: 1, y: 0, scale: 1 }"
                                    :transition="{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }"
                                    class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3"
                                >
                                    <Trophy class="h-5 w-5 text-emerald-500" />
                                    <span class="text-sm font-bold text-emerald-600">
                                        You completed this course! 🎉
                                    </span>
                                </Motion>
                            </div>

                            <!-- Continue / Review Button -->
                            <Link
                                v-if="firstIncompleteLesson"
                                :href="`/courses/${course.id}/lessons/${firstIncompleteLesson.id}`"
                                class="group shrink-0 rounded-xl bg-primary px-6 py-3 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:bg-primary/90 hover:shadow-xl hover:shadow-primary/30 active:scale-[0.97]"
                            >
                                <span class="flex items-center gap-2">
                                    <PlayCircle class="h-5 w-5" />
                                    Continue
                                    <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </Link>
                            <Link
                                v-else-if="props.modules[0]?.lessons[0]?.id"
                                :href="`/courses/${course.id}/lessons/${props.modules[0].lessons[0].id}`"
                                class="group shrink-0 rounded-xl border border-border/40 px-6 py-3 text-sm font-bold text-muted-foreground transition-all hover:border-primary/30 hover:text-primary active:scale-[0.97]"
                            >
                                <span class="flex items-center gap-2">
                                    <ListChecks class="h-5 w-5" />
                                    Review
                                    <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </Link>
                        </div>
                    </Motion>
                </div>
            </div>

            <!-- Modules & Lessons — Horizontal Layout -->
            <div class="flex-1 overflow-hidden p-4 md:p-6">
                <div class="relative">
                    <!-- Scroll Arrows -->
                    <button
                        v-if="canScrollLeft"
                        @click="scrollModules('left')"
                        class="absolute left-0 top-1/2 z-20 hidden -translate-y-1/2 rounded-full border border-border/40 bg-background/80 p-2 shadow-lg backdrop-blur-sm transition-all hover:bg-background hover:shadow-xl md:flex"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>
                    <button
                        v-if="canScrollRight"
                        @click="scrollModules('right')"
                        class="absolute right-0 top-1/2 z-20 hidden -translate-y-1/2 rounded-full border border-border/40 bg-background/80 p-2 shadow-lg backdrop-blur-sm transition-all hover:bg-background hover:shadow-xl md:flex"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </button>

                    <!-- Horizontal Module Cards -->
                    <div
                        ref="scrollContainer"
                        class="scrollbar-none flex gap-4 overflow-x-auto pb-4 md:gap-6"
                        style="overscroll-behavior-x: contain; scroll-snap-type: x proximity;"
                    >
                        <Motion
                            v-for="(mod, mIdx) in modules"
                            :key="mod.id"
                            :initial="{ opacity: 0, x: 40 }"
                            :in-view="{ opacity: 1, x: 0 }"
                            :in-view-options="{ once: true, margin: '-50px' }"
                            :transition="{ duration: 0.7, ease: [0.16, 1, 0.3, 1], delay: mIdx * 0.08 }"
                            :data-module-index="mIdx"
                            class="shrink-0"
                            style="scroll-snap-align: start; width: min(85vw, 420px);"
                        >
                            <div
                                class="surface-card flex h-full flex-col overflow-hidden rounded-2xl border border-border/40 transition-all duration-500 hover:border-border/60 hover:shadow-lg"
                                :class="mp(mod.id).done === mp(mod.id).total && mp(mod.id).total > 0
                                    ? 'border-emerald-500/20'
                                    : ''"
                            >
                                <!-- Module Header -->
                                <div class="border-b border-border/10 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-bold text-muted-foreground/40">MODULE {{ mIdx + 1 }}</span>
                                                <span
                                                    v-if="mp(mod.id).done === mp(mod.id).total && mp(mod.id).total > 0"
                                                    class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-500"
                                                >
                                                    ✓ Complete
                                                </span>
                                            </div>
                                            <h3 class="mt-1 text-base font-bold truncate">{{ mod.title }}</h3>
                                            <p v-if="mod.description" class="mt-1 text-[11px] leading-relaxed text-muted-foreground/60 line-clamp-2">
                                                {{ mod.description }}
                                            </p>
                                        </div>
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                            :class="mp(mod.id).done === mp(mod.id).total && mp(mod.id).total > 0
                                                ? 'bg-emerald-500/10 text-emerald-500'
                                                : 'bg-primary/10 text-primary'"
                                        >
                                            <GraduationCap class="h-5 w-5" />
                                        </div>
                                    </div>

                                    <!-- Module progress bar -->
                                    <div class="mt-4 space-y-1.5">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-medium text-muted-foreground/50">{{ mp(mod.id).done }}/{{ mp(mod.id).total }} lessons</span>
                                            <span class="font-bold" :class="mp(mod.id).done === mp(mod.id).total ? 'text-emerald-500' : 'text-primary'">
                                                {{ mp(mod.id).pct }}%
                                            </span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full transition-all duration-700"
                                                :class="mp(mod.id).done === mp(mod.id).total ? 'bg-emerald-500' : 'bg-primary/60'"
                                                :style="{ width: `${mp(mod.id).pct}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lessons List -->
                                <div class="flex-1 overflow-y-auto p-3">
                                    <div class="space-y-1.5">
                                        <Link
                                            v-for="(lesson, lIdx) in mod.lessons"
                                            :key="lesson.id"
                                            :href="`/courses/${course.id}/lessons/${lesson.id}`"
                                            class="group flex items-center gap-3 rounded-xl px-3 py-3 transition-all hover:bg-muted/20 focus:bg-muted/30 focus:outline-none"
                                            :class="lesson.completed
                                                ? 'bg-emerald-500/[0.03]'
                                                : lIdx === 0 && mIdx === 0
                                                    ? 'bg-primary/[0.03]'
                                                    : ''"
                                        >
                                            <!-- Status -->
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all"
                                                :class="lesson.completed
                                                    ? 'bg-emerald-500/10'
                                                    : 'border-2 border-muted-foreground/20 bg-muted/20 group-hover:border-primary/30'"
                                            >
                                                <CheckCircle2
                                                    v-if="lesson.completed"
                                                    class="h-4.5 w-4.5 text-emerald-500"
                                                />
                                                <span v-else class="text-[10px] font-bold text-muted-foreground/40">{{ lIdx + 1 }}</span>
                                            </div>

                                            <!-- Info -->
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-[10px] font-medium text-muted-foreground/40">L{{ lIdx + 1 }}</span>
                                                    <span v-if="lesson.hasQuiz" class="rounded bg-primary/10 px-1.5 py-0.5 text-[8px] font-bold text-primary leading-none">Q</span>
                                                </div>
                                                <p class="mt-0.5 text-xs font-semibold leading-snug transition-colors group-hover:text-primary line-clamp-2">
                                                    {{ lesson.title }}
                                                </p>
                                            </div>

                                            <!-- Score -->
                                            <div class="flex shrink-0 items-center gap-1">
                                                <span
                                                    v-if="lesson.quizScore !== null"
                                                    class="rounded-md px-1.5 py-0.5 text-[9px] font-bold"
                                                    :class="lesson.quizScore >= 75 ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'"
                                                >
                                                    {{ lesson.quizScore }}%
                                                </span>
                                                <ChevronRight class="h-3.5 w-3.5 text-muted-foreground/20 transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
                                            </div>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Module footer -->
                                <div class="border-t border-border/10 p-3">
                                    <Link
                                        v-if="mp(mod.id).done < mp(mod.id).total && mod.lessons.length > 0"
                                        :href="`/courses/${course.id}/lessons/${mod.lessons.find(l => !l.completed)?.id ?? mod.lessons[0].id}`"
                                        class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary/10 py-2.5 text-[11px] font-bold text-primary transition-all hover:bg-primary/20 active:scale-[0.98]"
                                    >
                                        <PlayCircle class="h-4 w-4" />
                                        {{ mp(mod.id).done > 0 ? 'Continue' : 'Start' }} Module
                                    </Link>
                                    <div
                                        v-else
                                        class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-500/10 py-2.5 text-[11px] font-bold text-emerald-500"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                        All done
                                    </div>
                                </div>
                            </div>
                        </Motion>
                    </div>

                    <!-- Scroll hint fade edges -->
                    <div
                        v-if="canScrollLeft"
                        class="pointer-events-none absolute left-0 top-0 bottom-4 w-12 bg-gradient-to-r from-background to-transparent md:hidden"
                    ></div>
                    <div
                        v-if="canScrollRight"
                        class="pointer-events-none absolute right-0 top-0 bottom-4 w-12 bg-gradient-to-l from-background to-transparent md:hidden"
                    ></div>
                </div>

                <!-- Dots indicator -->
                <div class="mt-2 flex items-center justify-center gap-1.5 md:hidden">
                    <div
                        v-for="(mod, mIdx) in modules"
                        :key="mod.id"
                        class="rounded-full transition-all duration-300"
                        :class="mIdx === activeModuleIndex
                            ? 'w-4 bg-primary h-1.5'
                            : 'w-1.5 h-1.5 bg-muted-foreground/20'"
                    ></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.slide-enter-active {
    transition: all 0.3s ease-out;
}
.slide-leave-active {
    transition: all 0.2s ease-in;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
}
</style>
