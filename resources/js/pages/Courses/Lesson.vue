<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import {
    CheckCircle2,
    ChevronRight,
    ChevronLeft,
    CheckCheck,
    AlertCircle,
    RefreshCw,
    Sparkles,
    ArrowLeft,
    Clock,
    Trophy,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface QuizResult {
    passed: boolean;
    score: number;
    passScore: number;
    totalQuestions: number;
    correctAnswers: number;
}

interface QuizQuestion {
    question: string;
    options: Array<{ text: string; is_correct: boolean }>;
}

interface QuizData {
    id: number;
    questions: QuizQuestion[];
    passScore: number;
    allowedAttempts: number;
}

interface UserProgress {
    completed: boolean;
    quizScore: number | null;
    attempts: number;
    quizAnswers: Array<{
        questionIndex: number;
        selectedOptionIndex: number;
    }> | null;
    completedAt: string | null;
}

interface LessonData {
    id: number;
    title: string;
    content: string | null;
    videoUrl: string | null;
    mediaAttachments: any | null;
}

interface CourseInfo {
    id: number;
    name: string;
}

interface ModuleLesson {
    id: number;
    title: string;
    sortOrder: number;
    completed: boolean;
    quizScore: number | null;
    hasQuiz: boolean;
}

interface ModuleData {
    id: number;
    title: string;
    description: string | null;
    sortOrder: number;
    lessons: ModuleLesson[];
}

const props = defineProps<{
    course: CourseInfo;
    lesson: LessonData;
    quiz: QuizData | null;
    userProgress: UserProgress;
    quizResult: QuizResult | null;
    prevLessonId: number | null;
    nextLessonId: number | null;
    modules: ModuleData[];
    courseProgress: {
        totalLessons: number;
        completedLessons: number;
        progress: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Courses', href: '/courses' },
    { title: props.course.name, href: `/courses/${props.course.id}` },
    {
        title: props.lesson.title,
        href: `/courses/${props.course.id}/lessons/${props.lesson.id}`,
    },
];

// ─── Scroll Progress ───
const scrollContainer = ref<HTMLElement | null>(null);
const scrollProgress = ref(0);

const handleScroll = () => {
    const el = scrollContainer.value;
    if (!el) return;
    const scrollTop = el.scrollTop;
    const scrollHeight = el.scrollHeight - el.clientHeight;
    scrollProgress.value =
        scrollHeight > 0 ? Math.min((scrollTop / scrollHeight) * 100, 100) : 0;
};

// ─── Reading Time ───
const readingTime = computed(() => {
    if (!props.lesson.content) return '1 min';
    const words = props.lesson.content
        .replace(/<[^>]*>/g, '')
        .split(/\s+/)
        .filter(Boolean).length;
    const mins = Math.max(1, Math.ceil(words / 200));
    return `${mins} min read`;
});

// ─── Quiz state ───
const selectedAnswers = ref<Record<number, number>>({});
const isSubmitting = ref(false);
const showResult = ref(!!props.quizResult);

// ─── Module Strip State ───
const lessonNumber = computed(() => {
    let count = 0;
    for (const mod of props.modules) {
        for (const l of mod.lessons) {
            count++;
            if (l.id === props.lesson.id) return count;
        }
    }
    return 0;
});

const totalLessonsInCourse = computed(() =>
    props.modules.reduce((acc, m) => acc + m.lessons.length, 0),
);

// Build cumulative lesson number offset per module for the module strip
const moduleLessonOffsets = computed(() => {
    let offset = 0;
    return props.modules.map((mod) => {
        const start = offset + 1;
        offset += mod.lessons.length;
        return { start, end: offset };
    });
});

// Initialize selected answers from previous attempt
if (props.userProgress.quizAnswers && !props.userProgress.completed) {
    for (const ans of props.userProgress.quizAnswers) {
        selectedAnswers.value[ans.questionIndex] = ans.selectedOptionIndex;
    }
}

const canAttemptQuiz = computed(() => {
    if (props.userProgress.completed) return false;
    if (!props.quiz) return false;
    if (props.quiz.allowedAttempts === 0) return true;
    return props.userProgress.attempts < props.quiz.allowedAttempts;
});

const allQuestionsAnswered = computed(() => {
    if (!props.quiz) return false;
    return props.quiz.questions.every(
        (_, i) => selectedAnswers.value[i] !== undefined,
    );
});

const answeredCount = computed(() => {
    if (!props.quiz) return 0;
    return Object.keys(selectedAnswers.value).length;
});

const totalQuestions = computed(() => props.quiz?.questions.length ?? 0);

const submitQuiz = () => {
    if (!allQuestionsAnswered.value || isSubmitting.value) return;

    isSubmitting.value = true;

    const answers = Object.entries(selectedAnswers.value).map(
        ([questionIndex, selectedOptionIndex]) => ({
            questionIndex: parseInt(questionIndex),
            selectedOptionIndex,
        }),
    );

    router.post(
        `/courses/${props.course.id}/lessons/${props.lesson.id}/quiz`,
        { answers },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isSubmitting.value = false;
                showResult.value = true;
            },
        },
    );
};

// Video embed URL parsing
const embedUrl = computed(() => {
    if (!props.lesson.videoUrl) return null;
    const url = props.lesson.videoUrl;

    // YouTube
    const ytMatch = url.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/,
    );
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;

    // Vimeo
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;

    return url;
});

// Computed property for lesson content with fallback
const sanitizedContent = computed(() => {
    return (
        props.lesson.content ||
        '<p class="text-muted-foreground">No content available for this lesson yet.</p>'
    );
});

// ─── Page Transition Animation ───
const TRANSITION_DURATION = 280; // ms
const isExiting = ref(false);
const exitDir = ref<'left' | 'right' | null>(null);
const entranceAnimClass = ref('');

// True during any page transition (exit or entrance) so we can adjust parent overflow
const isAnimating = computed(
    () => isExiting.value || !!entranceAnimClass.value,
);

const navigateToLesson = (lessonId: number, direction?: 'left' | 'right') => {
    if (direction) {
        if (isExiting.value) return; // prevent double-navigation
        isExiting.value = true;
        exitDir.value = direction;
        // Store direction so the next page knows which way to slide in
        sessionStorage.setItem('swipe_entrance_dir', direction);

        setTimeout(() => {
            router.get(`/courses/${props.course.id}/lessons/${lessonId}`);
        }, TRANSITION_DURATION);
    } else {
        router.get(`/courses/${props.course.id}/lessons/${lessonId}`);
    }
};

// ─── Keyboard Shortcuts ───
const handleKeydown = (e: KeyboardEvent) => {
    // Don't trigger if user is typing in an input
    if (
        (e.target as HTMLElement)?.tagName === 'INPUT' ||
        (e.target as HTMLElement)?.tagName === 'TEXTAREA'
    )
        return;

    if (e.key === 'ArrowLeft' && props.prevLessonId) {
        e.preventDefault();
        navigateToLesson(props.prevLessonId, 'right'); // exit right, enter from left
    } else if (e.key === 'ArrowRight' && props.nextLessonId) {
        e.preventDefault();
        navigateToLesson(props.nextLessonId, 'left'); // exit left, enter from right
    }
};

// ─── Swipe Gesture (Mobile) ───
const SWIPE_THRESHOLD = 50; // minimum px to trigger swipe
let touchStartX = 0;
let touchStartY = 0;
let touchStartTime = 0;

const handleTouchStart = (e: TouchEvent) => {
    // Ignore swipes starting on interactive elements (quiz buttons, inputs, etc.)
    const target = e.target as HTMLElement;
    if (target.closest('button, input, textarea, label, select, a')) return;

    const touch = e.touches[0];
    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
    touchStartTime = Date.now();
};

const handleTouchEnd = (e: TouchEvent) => {
    const touch = e.changedTouches[0];
    const dx = touch.clientX - touchStartX;
    const dy = touch.clientY - touchStartY;
    const elapsed = Date.now() - touchStartTime;

    // Must be a quick gesture (under 400ms) and more horizontal than vertical
    if (elapsed > 400) return;
    if (Math.abs(dy) > Math.abs(dx) * 1.5) return; // vertical scroll detected
    if (Math.abs(dx) < SWIPE_THRESHOLD) return;

    // Prevent default to avoid pull-to-refresh etc
    e.preventDefault();

    if (dx > 0 && props.prevLessonId) {
        // Swipe right → previous lesson
        navigateToLesson(props.prevLessonId, 'right');
    } else if (dx < 0 && props.nextLessonId) {
        // Swipe left → next lesson
        navigateToLesson(props.nextLessonId, 'left');
    }
};

onMounted(async () => {
    // ─── Entrance Animation ───
    const dir = sessionStorage.getItem('swipe_entrance_dir');
    if (dir === 'left') {
        // Came from right — exit was left, so this page slides in from right
        entranceAnimClass.value = 'animate-slide-in-right';
    } else if (dir === 'right') {
        // Came from left — exit was right, so this page slides in from left
        entranceAnimClass.value = 'animate-slide-in-left';
    }
    sessionStorage.removeItem('swipe_entrance_dir');

    if (entranceAnimClass.value) {
        await nextTick();
        // Remove animation class after it completes to reset transform
        setTimeout(() => {
            entranceAnimClass.value = '';
        }, TRANSITION_DURATION + 40);
    }

    window.addEventListener('keydown', handleKeydown);

    // Attach scroll listener to the content container
    const el = scrollContainer.value;
    if (el) {
        el.addEventListener('scroll', handleScroll);
        el.addEventListener('touchstart', handleTouchStart, { passive: true });
        el.addEventListener('touchend', handleTouchEnd, { passive: false });
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);

    // Clean up scroll listener and swipe listeners
    const el = scrollContainer.value;
    if (el) {
        el.removeEventListener('scroll', handleScroll);
        el.removeEventListener('touchstart', handleTouchStart);
        el.removeEventListener('touchend', handleTouchEnd);
    }
});

// Watch for quiz result flash notification
watch(showResult, (val) => {
    if (val) {
        setTimeout(() => {
            showResult.value = false;
        }, 8000);
    }
});
</script>

<template>
    <Head :title="`${lesson.title} — ${course.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative flex h-full flex-1 flex-col bg-background"
            :class="{ 'overflow-hidden': !isAnimating }"
        >
            <!-- Scroll Progress Bar (fixed top) -->
            <div
                class="pointer-events-none fixed top-0 right-0 left-0 z-50 h-1"
            >
                <div
                    class="h-full bg-primary transition-all duration-150 ease-out"
                    :style="{ width: `${scrollProgress}%` }"
                ></div>
            </div>

            <!-- ─── Animated Page Content ─── -->
            <div
                class="flex flex-1 flex-col"
                :class="[
                    entranceAnimClass,
                    isExiting
                        ? exitDir === 'left'
                            ? 'animate-slide-out-left'
                            : 'animate-slide-out-right'
                        : '',
                ]"
            >
                <!-- ─── Horizontal Module Strip ─── -->
                <div
                    class="flex shrink-0 scrollbar-none gap-0.5 overflow-x-auto border-b border-border/10 bg-muted/10 px-2 py-1.5 md:gap-1 md:px-6 md:py-2"
                >
                    <template v-for="(mod, mIdx) in modules" :key="mod.id">
                        <!-- Module divider (not first) -->
                        <div
                            v-if="mIdx > 0"
                            class="mx-0.5 mt-3 h-6 w-px shrink-0 bg-border/30 md:mx-1 md:mt-2.5 md:h-5"
                        ></div>

                        <!-- Module Group -->
                        <div class="shrink-0">
                            <!-- Module name label: hidden on mobile, visible on md+ -->
                            <div class="hidden items-center gap-1 px-1 md:flex">
                                <span
                                    class="text-[9px] font-bold tracking-wider text-muted-foreground/40 uppercase"
                                >
                                    {{ mod.title }}
                                </span>
                                <span
                                    v-if="
                                        mod.lessons.every((l) => l.completed) &&
                                        mod.lessons.length > 0
                                    "
                                    class="text-[9px] text-emerald-500"
                                    >✓</span
                                >
                            </div>
                            <!-- Lesson buttons: bigger touch targets on mobile -->
                            <div class="flex gap-1 md:mt-1">
                                <Link
                                    v-for="l in mod.lessons"
                                    :key="l.id"
                                    :href="`/courses/${course.id}/lessons/${l.id}`"
                                    :aria-label="`Lesson ${moduleLessonOffsets[mIdx].start + mod.lessons.indexOf(l)}: ${l.title}`"
                                    class="group relative flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold transition-all active:scale-95 md:h-8 md:w-8 md:text-[10px] md:active:scale-100"
                                    :class="
                                        l.id === lesson.id
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : l.completed
                                              ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20'
                                              : 'bg-muted text-muted-foreground/50 hover:bg-muted/80 hover:text-foreground/70'
                                    "
                                    :title="l.title"
                                >
                                    <span class="md:inline">{{
                                        moduleLessonOffsets[mIdx].start +
                                        mod.lessons.indexOf(l)
                                    }}</span>
                                    <span
                                        v-if="l.hasQuiz && l.id !== lesson.id"
                                        class="absolute -top-0.5 -right-0.5 h-1.5 w-1.5 rounded-full bg-primary/40"
                                    ></span>
                                </Link>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ─── Top Bar ─── -->
                <div
                    class="flex items-center justify-between border-b border-border/10 px-4 py-2.5 md:px-6"
                >
                    <div class="flex items-center gap-3">
                        <Link
                            :href="`/courses/${course.id}`"
                            class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            <span class="hidden sm:inline">Back to course</span>
                        </Link>
                        <span class="text-muted-foreground/20">|</span>
                        <span
                            class="text-[11px] font-medium text-muted-foreground/60"
                        >
                            Lesson {{ lessonNumber }} of
                            {{ totalLessonsInCourse }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="flex items-center gap-1.5 text-[11px] text-muted-foreground/50"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">{{
                                readingTime
                            }}</span>
                        </span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                            :class="
                                userProgress.completed
                                    ? 'bg-emerald-500/10 text-emerald-500'
                                    : 'bg-muted text-muted-foreground/60'
                            "
                        >
                            {{ userProgress.completed ? '✓ Done' : 'Pending' }}
                        </span>
                    </div>
                </div>

                <!-- ─── Main Content + Floating Navigation ─── -->
                <div class="relative flex flex-1 overflow-hidden">
                    <!-- Floating Prev Button -->
                    <button
                        v-if="prevLessonId"
                        @click="navigateToLesson(prevLessonId, 'right')"
                        class="group absolute top-1/2 left-2 z-20 -translate-y-1/2 rounded-full border border-border/30 bg-background/80 p-2.5 shadow-lg backdrop-blur-sm transition-all hover:border-primary/30 hover:bg-background hover:shadow-xl active:scale-95 md:left-4"
                        title="Previous lesson"
                        type="button"
                    >
                        <ChevronLeft
                            class="h-5 w-5 text-muted-foreground transition-colors group-hover:text-primary"
                        />
                    </button>

                    <!-- Floating Next Button -->
                    <button
                        v-if="nextLessonId"
                        @click="navigateToLesson(nextLessonId, 'left')"
                        class="group absolute top-1/2 right-2 z-20 -translate-y-1/2 rounded-full border border-border/30 bg-background/80 p-2.5 shadow-lg backdrop-blur-sm transition-all hover:border-primary/30 hover:bg-background hover:shadow-xl active:scale-95 md:right-4"
                        title="Next lesson"
                        type="button"
                    >
                        <ChevronRight
                            class="h-5 w-5 text-muted-foreground transition-colors group-hover:text-primary"
                        />
                    </button>

                    <!-- Scrollable Lesson Content -->
                    <div
                        ref="scrollContainer"
                        class="flex-1 overflow-y-auto"
                        style="touch-action: pan-y"
                    >
                        <div
                            class="mx-auto max-w-4xl px-5 py-8 md:px-10 md:py-12"
                        >
                            <!-- Video Embed -->
                            <Motion
                                v-if="embedUrl"
                                :initial="{ opacity: 0, y: 20 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :transition="{ duration: 0.6 }"
                                class="mb-8 overflow-hidden rounded-2xl border border-border/10 shadow-lg"
                            >
                                <div class="aspect-video">
                                    <iframe
                                        :src="embedUrl"
                                        class="h-full w-full"
                                        allowfullscreen
                                        allow="
                                            accelerometer;
                                            autoplay;
                                            clipboard-write;
                                            encrypted-media;
                                            gyroscope;
                                            picture-in-picture;
                                        "
                                    ></iframe>
                                </div>
                            </Motion>

                            <!-- Lesson Header -->
                            <Motion
                                :initial="{ opacity: 0, y: 20 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :transition="{ duration: 0.6, delay: 0.1 }"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-4"
                                >
                                    <div class="flex-1">
                                        <h1
                                            class="text-3xl font-black tracking-tight"
                                        >
                                            {{ lesson.title }}
                                        </h1>
                                        <p
                                            class="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground/50"
                                        >
                                            <Clock class="h-3.5 w-3.5" />
                                            {{ readingTime }}
                                            <span
                                                class="mx-1.5 text-muted-foreground/20"
                                                >·</span
                                            >
                                            <span>{{
                                                userProgress.completed
                                                    ? 'Completed'
                                                    : 'Not yet completed'
                                            }}</span>
                                        </p>
                                    </div>
                                    <span
                                        class="flex items-center gap-1 rounded-full border border-border/30 bg-muted/30 px-3 py-1 text-[10px] font-medium text-muted-foreground md:hidden"
                                    >
                                        <Clock class="h-3 w-3" />
                                        {{ readingTime }}
                                    </span>
                                </div>
                            </Motion>

                            <!-- Lesson Content -->
                            <Motion
                                :initial="{ opacity: 0, y: 20 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :transition="{ duration: 0.6, delay: 0.2 }"
                                class="lesson-content mt-8 max-w-none"
                                v-html="sanitizedContent"
                            ></Motion>

                            <!-- Completion Quiz Section -->
                            <Motion
                                v-if="quiz"
                                :initial="{ opacity: 0, y: 30 }"
                                :in-view="{ opacity: 1, y: 0 }"
                                :in-view-options="{
                                    once: true,
                                    margin: '-50px',
                                }"
                                :transition="{
                                    duration: 0.8,
                                    ease: [0.16, 1, 0.3, 1],
                                }"
                                class="mt-16"
                            >
                                <div
                                    class="rounded-2xl border-2 p-8"
                                    :class="
                                        userProgress.completed
                                            ? 'border-emerald-500/20 bg-emerald-500/[0.02]'
                                            : quizResult
                                              ? quizResult.passed
                                                  ? 'border-emerald-500/20 bg-emerald-500/[0.02]'
                                                  : 'border-red-500/20 bg-red-500/[0.02]'
                                              : 'border-primary/10 bg-primary/[0.02]'
                                    "
                                >
                                    <!-- Quiz Header -->
                                    <div
                                        class="mb-6 flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-12 w-12 items-center justify-center rounded-xl"
                                                :class="
                                                    userProgress.completed
                                                        ? 'bg-emerald-500/10 text-emerald-500'
                                                        : 'bg-primary/10 text-primary'
                                                "
                                            >
                                                <Sparkles class="h-6 w-6" />
                                            </div>
                                            <div>
                                                <h2 class="text-lg font-bold">
                                                    {{
                                                        userProgress.completed
                                                            ? 'Lesson Complete!'
                                                            : 'Check Your Understanding'
                                                    }}
                                                </h2>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        userProgress.completed
                                                            ? `You scored ${userProgress.quizScore}% — Great job!`
                                                            : `Pass the quiz (${quiz.passScore}%+) to complete this lesson`
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                v-if="userProgress.completed"
                                                class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-500"
                                            >
                                                <CheckCheck
                                                    class="mr-1 inline h-3.5 w-3.5"
                                                />
                                                Completed
                                            </span>
                                            <span
                                                v-else-if="
                                                    userProgress.attempts > 0
                                                "
                                                class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                                            >
                                                Attempt
                                                {{ userProgress.attempts
                                                }}{{
                                                    quiz.allowedAttempts > 0
                                                        ? `/${quiz.allowedAttempts}`
                                                        : ''
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Question Progress Indicator (when quiz is active) -->
                                    <div
                                        v-if="
                                            canAttemptQuiz && totalQuestions > 0
                                        "
                                        class="mb-6"
                                    >
                                        <div
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span
                                                class="font-medium text-muted-foreground/60"
                                            >
                                                {{ answeredCount }} of
                                                {{ totalQuestions }} answered
                                            </span>
                                            <span
                                                class="font-bold"
                                                :class="
                                                    allQuestionsAnswered
                                                        ? 'text-emerald-500'
                                                        : 'text-muted-foreground/50'
                                                "
                                            >
                                                {{
                                                    allQuestionsAnswered
                                                        ? '✓ Ready'
                                                        : `${totalQuestions - answeredCount} remaining`
                                                }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex gap-1.5">
                                            <div
                                                v-for="qIdx in totalQuestions"
                                                :key="qIdx"
                                                class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                                :class="
                                                    selectedAnswers[
                                                        qIdx - 1
                                                    ] !== undefined
                                                        ? 'bg-primary'
                                                        : 'bg-muted'
                                                "
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Quiz Result Banner -->
                                    <Motion
                                        v-if="quizResult && showResult"
                                        :initial="{
                                            opacity: 0,
                                            scale: 0.95,
                                            y: -10,
                                        }"
                                        :animate="{
                                            opacity: 1,
                                            scale: 1,
                                            y: 0,
                                        }"
                                        :transition="{ duration: 0.4 }"
                                        class="mb-6 rounded-xl p-4"
                                        :class="
                                            quizResult.passed
                                                ? 'border border-emerald-500/30 bg-emerald-500/10'
                                                : 'border border-red-500/30 bg-red-500/10'
                                        "
                                    >
                                        <div class="flex items-start gap-3">
                                            <CheckCircle2
                                                v-if="quizResult.passed"
                                                class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500"
                                            />
                                            <AlertCircle
                                                v-else
                                                class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                                            />
                                            <div>
                                                <p
                                                    class="text-sm font-bold"
                                                    :class="
                                                        quizResult.passed
                                                            ? 'text-emerald-600'
                                                            : 'text-red-600'
                                                    "
                                                >
                                                    {{
                                                        quizResult.passed
                                                            ? 'Congratulations! You passed!'
                                                            : 'Not quite — keep studying and try again.'
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    Score:
                                                    {{
                                                        quizResult.correctAnswers
                                                    }}/{{
                                                        quizResult.totalQuestions
                                                    }}
                                                    ({{ quizResult.score }}%)
                                                    &mdash; Required:
                                                    {{ quizResult.passScore }}%
                                                </p>
                                            </div>
                                        </div>
                                    </Motion>

                                    <!-- Quiz Questions -->
                                    <div
                                        v-if="canAttemptQuiz"
                                        class="space-y-8"
                                    >
                                        <div
                                            v-for="(
                                                question, qIdx
                                            ) in quiz.questions"
                                            :key="qIdx"
                                            class="space-y-3"
                                        >
                                            <p class="text-sm font-semibold">
                                                <span
                                                    class="text-muted-foreground/50"
                                                    >{{ qIdx + 1 }}.</span
                                                >
                                                {{ question.question }}
                                            </p>
                                            <div
                                                class="grid grid-cols-1 gap-2 md:grid-cols-2"
                                            >
                                                <button
                                                    v-for="(
                                                        option, oIdx
                                                    ) in question.options"
                                                    :key="oIdx"
                                                    @click="
                                                        selectedAnswers[qIdx] =
                                                            oIdx
                                                    "
                                                    class="group flex items-center gap-3 rounded-xl border p-3.5 text-left text-sm transition-all"
                                                    :class="
                                                        selectedAnswers[
                                                            qIdx
                                                        ] === oIdx
                                                            ? 'border-primary/40 bg-primary/5 ring-1 ring-primary/20'
                                                            : 'border-border/40 hover:border-primary/20 hover:bg-muted/20'
                                                    "
                                                >
                                                    <div
                                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-all"
                                                        :class="
                                                            selectedAnswers[
                                                                qIdx
                                                            ] === oIdx
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-muted-foreground/30'
                                                        "
                                                    >
                                                        <div
                                                            v-if="
                                                                selectedAnswers[
                                                                    qIdx
                                                                ] === oIdx
                                                            "
                                                            class="h-2 w-2 rounded-full bg-current"
                                                        ></div>
                                                    </div>
                                                    <span>{{
                                                        option.text
                                                    }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <div
                                            class="flex items-center justify-between pt-4"
                                        >
                                            <p
                                                v-if="!allQuestionsAnswered"
                                                class="text-xs text-muted-foreground/60"
                                            >
                                                Answer all questions to submit
                                            </p>
                                            <button
                                                @click="submitQuiz"
                                                :disabled="
                                                    !allQuestionsAnswered ||
                                                    isSubmitting
                                                "
                                                class="rounded-xl px-8 py-3 text-sm font-bold transition-all"
                                                :class="
                                                    allQuestionsAnswered &&
                                                    !isSubmitting
                                                        ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20 hover:bg-primary/90 active:scale-[0.98]'
                                                        : 'cursor-not-allowed bg-muted text-muted-foreground/40'
                                                "
                                            >
                                                <span
                                                    class="flex items-center gap-2"
                                                >
                                                    <RefreshCw
                                                        v-if="isSubmitting"
                                                        class="h-4 w-4 animate-spin"
                                                    />
                                                    <CheckCheck
                                                        v-else
                                                        class="h-4 w-4"
                                                    />
                                                    {{
                                                        isSubmitting
                                                            ? 'Submitting...'
                                                            : 'Submit & Complete'
                                                    }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Already Completed -->
                                    <div
                                        v-if="userProgress.completed"
                                        class="flex flex-col items-center justify-center py-8"
                                    >
                                        <div
                                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10"
                                        >
                                            <CheckCircle2
                                                class="h-8 w-8 text-emerald-500"
                                            />
                                        </div>
                                        <p class="text-lg font-bold">
                                            You've completed this lesson!
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Score: {{ userProgress.quizScore }}%
                                            <span
                                                v-if="userProgress.completedAt"
                                                >·
                                                {{
                                                    new Date(
                                                        userProgress.completedAt,
                                                    ).toLocaleDateString()
                                                }}</span
                                            >
                                        </p>
                                    </div>
                                </div>
                            </Motion>

                            <!-- Bottom Navigation (for keyboard / mobile) -->
                            <div
                                class="mt-12 flex items-center justify-between border-t border-border/10 pt-6 md:hidden"
                            >
                                <button
                                    v-if="prevLessonId"
                                    @click="
                                        navigateToLesson(prevLessonId, 'right')
                                    "
                                    class="flex items-center gap-2 rounded-xl border border-border/40 px-5 py-3 text-sm font-medium transition-colors hover:border-primary/30 hover:text-primary"
                                    type="button"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                    Previous
                                </button>
                                <div v-else></div>

                                <button
                                    v-if="nextLessonId"
                                    @click="
                                        navigateToLesson(nextLessonId, 'left')
                                    "
                                    class="flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:bg-primary/90 active:scale-[0.98]"
                                    type="button"
                                >
                                    Next
                                    <ChevronRight class="h-4 w-4" />
                                </button>
                                <Link
                                    v-else
                                    :href="`/courses/${course.id}`"
                                    class="flex items-center gap-2 rounded-xl bg-primary/10 px-5 py-3 text-sm font-bold text-primary transition-all hover:bg-primary/20"
                                >
                                    Finish Course
                                    <CheckCircle2 class="h-4 w-4" />
                                </Link>
                            </div>

                            <!-- Desktop: subtle finish button at bottom -->
                            <div
                                v-if="!nextLessonId"
                                class="mt-8 flex justify-center"
                            >
                                <Link
                                    :href="`/courses/${course.id}`"
                                    class="group flex items-center gap-2 rounded-2xl border-2 border-emerald-500/20 bg-emerald-500/[0.03] px-8 py-4 text-sm font-bold text-emerald-600 transition-all hover:border-emerald-500/40 hover:bg-emerald-500/[0.06] hover:shadow-lg active:scale-[0.98]"
                                >
                                    <Trophy class="h-5 w-5" />
                                    Course Complete — Back to Overview
                                    <ChevronRight
                                        class="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                                    />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../css/app.css";

/* ─── Page Transition Animations ─── */
/* Exit: slide out — shadow grows on the trailing/leading edge for depth */
@keyframes slide-out-left {
    to {
        transform: translateX(-100%);
        opacity: 0;
        box-shadow: 8px 0 40px -8px rgba(0, 0, 0, 0.25);
    }
}
@keyframes slide-out-right {
    to {
        transform: translateX(100%);
        opacity: 0;
        box-shadow: -8px 0 40px -8px rgba(0, 0, 0, 0.25);
    }
}
/* Entrance: slide in — starts elevated, settles flat */
@keyframes slide-in-left {
    from {
        transform: translateX(-100%);
        opacity: 0;
        box-shadow: 8px 0 40px -8px rgba(0, 0, 0, 0.25);
    }
    40% {
        box-shadow: 4px 0 24px -6px rgba(0, 0, 0, 0.18);
    }
    to {
        transform: translateX(0);
        opacity: 1;
        box-shadow: 0 0 0 rgba(0, 0, 0, 0);
    }
}
@keyframes slide-in-right {
    from {
        transform: translateX(100%);
        opacity: 0;
        box-shadow: -8px 0 40px -8px rgba(0, 0, 0, 0.25);
    }
    40% {
        box-shadow: -4px 0 24px -6px rgba(0, 0, 0, 0.18);
    }
    to {
        transform: translateX(0);
        opacity: 1;
        box-shadow: 0 0 0 rgba(0, 0, 0, 0);
    }
}

.animate-slide-out-left {
    animation: slide-out-left var(--slide-duration, 280ms)
        cubic-bezier(0.4, 0, 0.2, 1) forwards;
    will-change: transform, opacity, box-shadow;
}
.animate-slide-out-right {
    animation: slide-out-right var(--slide-duration, 280ms)
        cubic-bezier(0.4, 0, 0.2, 1) forwards;
    will-change: transform, opacity, box-shadow;
}
.animate-slide-in-left {
    animation: slide-in-left var(--slide-duration, 280ms)
        cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity, box-shadow;
}
.animate-slide-in-right {
    animation: slide-in-right var(--slide-duration, 280ms)
        cubic-bezier(0.16, 1, 0.3, 1) forwards;
    will-change: transform, opacity, box-shadow;
}

/* Subtle z-layer behind the animated wrapper so shadow is visible */
.animate-slide-out-left,
.animate-slide-out-right,
.animate-slide-in-left,
.animate-slide-in-right {
    position: relative;
    z-index: 10;
}

.slide-enter-active {
    transition: all 0.3s ease-out;
}
.slide-leave-active {
    transition: all 0.2s ease-in;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
}
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.3s ease;
}
.slide-left-enter-from {
    transform: translateX(-100%);
    opacity: 0;
}
.slide-left-leave-to {
    transform: translateX(-100%);
    opacity: 0;
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Prose styling for lesson content */
:deep(.prose h2) {
    @apply mt-8 mb-4 text-2xl font-bold;
}
:deep(.prose h3) {
    @apply mt-6 mb-3 text-xl font-bold;
}
:deep(.prose p) {
    @apply mb-4 leading-relaxed text-foreground/80;
}
:deep(.prose ul) {
    @apply mb-4 list-disc space-y-1 pl-6;
}
:deep(.prose ol) {
    @apply mb-4 list-decimal space-y-1 pl-6;
}
:deep(.prose li) {
    @apply text-foreground/80;
}
:deep(.prose blockquote) {
    @apply border-l-4 border-primary/30 pl-4 text-muted-foreground italic;
}
:deep(.prose img) {
    @apply my-6 max-w-full rounded-xl;
}
:deep(.prose code) {
    @apply rounded bg-muted px-1.5 py-0.5 font-mono text-sm;
}
:deep(.prose pre) {
    @apply my-4 overflow-x-auto rounded-xl bg-muted p-4;
}
:deep(.prose a) {
    @apply text-primary underline decoration-primary/30 hover:decoration-primary;
}
</style>
