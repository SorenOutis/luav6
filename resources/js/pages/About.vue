<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Command,
    ArrowLeft,
    Target,
    Compass,
    Sparkles,
    ShieldCheck,
    Users,
    Layers,
    BookOpenCheck,
    Mail,
    Github,
    Twitter,
    Linkedin,
    ArrowUp,
    GraduationCap,
    Briefcase,
    UserCog,
    CheckCircle2,
    XCircle,
    RotateCcw,
} from 'lucide-vue-next';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon } from 'lucide-vue-next';

gsap.registerPlugin(ScrollTrigger);

defineProps<{
    totalUsers?: number;
    totalExams?: number;
    totalSubmissions?: number;
}>();

const { appearance, toggleTheme } = useAppearance();

const root = ref<HTMLElement | null>(null);
let ctx: gsap.Context | null = null;

const principles = [
    {
        icon: Target,
        title: 'Clarity over cleverness',
        body: 'Interfaces that disappear so the learning shows through. No noise, no spectacle for its own sake.',
    },
    {
        icon: ShieldCheck,
        title: 'Trust by design',
        body: 'Privacy, integrity, and accessibility are not afterthoughts — they shape every decision we make.',
    },
    {
        icon: Layers,
        title: 'Modular at every layer',
        body: 'From auth to assessment, components compose. Institutions deploy what they need, scale when they want.',
    },
    {
        icon: Sparkles,
        title: 'Joyful by default',
        body: 'Streaks, badges, and a Learning Map make progress feel inevitable — without ever being patronizing.',
    },
];

const milestones = [
    {
        year: '2023',
        title: 'First seed',
        body: 'A single classroom prototype tests adaptive assessment for high-school students.',
    },
    {
        year: '2024',
        title: 'Open beta',
        body: 'Pilot deployments across three institutions; the engine handles thousands of submissions weekly.',
    },
    {
        year: '2025',
        title: 'LSI v5',
        body: 'Live Pulse, Architecture Stack, and the Learning Map ship — turning grades into journeys.',
    },
    {
        year: '2026',
        title: 'LSI v6',
        body: 'Full revamp: real-time metrics, AI-assisted authoring, and a unified design system.',
    },
];

const team = [
    { name: 'Koamishin Studio', role: 'Engineering & Design', tag: 'Core' },
    { name: 'Faculty Council', role: 'Academic Direction', tag: 'Advisors' },
    { name: 'Pilot Cohort', role: 'Real-classroom Feedback', tag: 'Partners' },
    { name: 'Open Contributors', role: 'Community Modules', tag: 'Network' },
];

const faqs = [
    {
        q: 'What does LSI stand for?',
        a: 'Learning Systems Infrastructure — the underlying engine powering everything you see in this product.',
    },
    {
        q: 'Is LSI free for educators?',
        a: 'Pilot programs are free for verified institutions during the v6 cycle. Get in touch via the contact link below.',
    },
    {
        q: 'Do students need any special device or software?',
        a: "No. LSI runs in any modern browser on phones, tablets, or laptops — no installs, no plug-ins. A reliable internet connection is all that's required.",
    },
    {
        q: 'How is student data protected?',
        a: 'All submissions are encrypted at rest and in transit. We do not sell data, and institutions retain full ownership of their content.',
    },
];

// Interactive: role picker
const activeRole = ref<'educator' | 'student' | 'admin'>('educator');
const roles = [
    {
        key: 'educator' as const,
        icon: GraduationCap,
        label: 'Educator',
        headline: 'Spend less time grading, more time teaching.',
        body: 'Author exams in minutes, push assignments to whole sections, and watch real-time mastery build through the Live Pulse dashboard.',
        bullets: [
            'Section-aware leaderboards',
            'AI-assisted feedback drafts',
            'One-click reusable exam parts',
        ],
    },
    {
        key: 'student' as const,
        icon: Users,
        label: 'Student',
        headline: 'Learn with momentum, not anxiety.',
        body: 'Streaks, badges, and the Learning Map turn each lesson into a checkpoint on a clear journey — instead of a wall of grades.',
        bullets: [
            'Daily streaks & XP',
            'Personal learning map',
            'Anonymous classroom messaging',
        ],
    },
    {
        key: 'admin' as const,
        icon: UserCog,
        label: 'Administrator',
        headline: 'See your whole campus at a glance.',
        body: 'Filament-powered admin tools give you full visibility into seasons, sections, submissions, and engagement across the institution.',
        bullets: [
            'Per-section analytics',
            'Season & cohort management',
            'Granular permissions',
        ],
    },
];
const currentRole = computed(
    () => roles.find((r) => r.key === activeRole.value)!,
);

// Interactive: mini quiz demo
const demoQuestion = {
    prompt: 'Which of the following best describes formative assessment?',
    options: [
        { id: 'a', text: 'A high-stakes ranking exam at the end of a course.' },
        {
            id: 'b',
            text: 'Ongoing checks for understanding that guide further learning.',
            correct: true,
        },
        { id: 'c', text: 'A standardized test administered nationally.' },
    ],
};
const selectedAnswer = ref<string | null>(null);
const showFeedback = computed(() => selectedAnswer.value !== null);
const isCorrect = computed(() => {
    const opt = demoQuestion.options.find((o) => o.id === selectedAnswer.value);
    return !!opt?.correct;
});
const selectAnswer = (id: string) => {
    if (selectedAnswer.value !== null) return;
    selectedAnswer.value = id;
};
const resetDemo = () => {
    selectedAnswer.value = null;
};

onMounted(() => {
    ctx = gsap.context(() => {
        gsap.from('.about-hero > *', {
            y: 40,
            opacity: 0,
            filter: 'blur(12px)',
            duration: 1.1,
            ease: 'expo.out',
            stagger: 0.08,
        });

        gsap.utils.toArray<HTMLElement>('.reveal-block').forEach((el) => {
            gsap.from(el, {
                y: 40,
                opacity: 0,
                filter: 'blur(10px)',
                duration: 0.9,
                ease: 'expo.out',
                scrollTrigger: { trigger: el, start: 'top 85%' },
            });
        });

        gsap.utils.toArray<HTMLElement>('.timeline-row').forEach((el, i) => {
            gsap.from(el, {
                x: i % 2 === 0 ? -40 : 40,
                opacity: 0,
                filter: 'blur(8px)',
                duration: 0.9,
                ease: 'expo.out',
                scrollTrigger: { trigger: el, start: 'top 85%' },
            });
        });

        // Directional reveal: each section is visible only while it overlaps the viewport.
        nextTick(() => {
            const sections = Array.from(
                root.value?.querySelectorAll<HTMLElement>('main > section') ??
                    [],
            );
            const viewportH = window.innerHeight;
            sections.forEach((section) => {
                if (section.offsetHeight < 40) return;
                gsap.set(section, { willChange: 'transform, opacity, filter' });
                const hideUp = () =>
                    gsap.to(section, {
                        opacity: 0,
                        y: -40,
                        filter: 'blur(10px)',
                        duration: 0.55,
                        ease: 'power2.in',
                        overwrite: 'auto',
                    });
                const hideDown = () =>
                    gsap.to(section, {
                        opacity: 0,
                        y: 40,
                        filter: 'blur(10px)',
                        duration: 0.55,
                        ease: 'power2.in',
                        overwrite: 'auto',
                    });
                const show = () =>
                    gsap.to(section, {
                        opacity: 1,
                        y: 0,
                        filter: 'blur(0px)',
                        duration: 0.7,
                        ease: 'expo.out',
                        overwrite: 'auto',
                    });

                const rect = section.getBoundingClientRect();
                if (rect.top > viewportH * 0.95) {
                    gsap.set(section, {
                        opacity: 0,
                        y: 40,
                        filter: 'blur(10px)',
                    });
                }
                ScrollTrigger.create({
                    trigger: section,
                    start: 'bottom top+=40',
                    end: 'bottom top',
                    onLeave: hideUp,
                    onEnterBack: show,
                });
                ScrollTrigger.create({
                    trigger: section,
                    start: 'top bottom-=40',
                    end: 'top bottom',
                    onEnter: show,
                    onLeaveBack: hideDown,
                });
            });
        });
    }, root);
});

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});

const scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });
</script>

<template>
    <Head title="About | LSI Learning Engine" />

    <div
        ref="root"
        class="theme-about relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <!-- Background grid -->
        <div
            class="pointer-events-none fixed inset-0 z-0 opacity-[0.05]"
            style="
                background-image:
                    linear-gradient(var(--color-border) 1px, transparent 1px),
                    linear-gradient(
                        90deg,
                        var(--color-border) 1px,
                        transparent 1px
                    );
                background-size: 60px 60px;
            "
        ></div>

        <!-- Header -->
        <header
            class="sticky top-0 z-50 flex w-full items-center justify-between bg-transparent px-6 py-5 lg:px-16 lg:py-8"
        >
            <Link href="/" class="group flex items-center gap-3">
                <div
                    class="relative flex h-10 w-10 items-center justify-center transition-transform duration-700 group-hover:rotate-180"
                >
                    <Command
                        class="relative z-10 h-6 w-6 text-foreground lg:h-7 lg:w-7"
                    />
                </div>
                <div class="flex flex-col leading-none">
                    <span
                        class="text-[10px] font-black tracking-[0.4em] text-foreground uppercase lg:text-xs"
                        >LSI Engine</span
                    >
                    <span
                        class="mt-1 text-[7px] font-bold tracking-widest text-primary uppercase lg:text-[8px]"
                        >/ about</span
                    >
                </div>
            </Link>

            <div class="flex items-center gap-4 lg:gap-8">
                <button
                    @click="toggleTheme"
                    class="p-2 text-muted-foreground transition-colors hover:text-foreground"
                    aria-label="Toggle theme"
                >
                    <Sun
                        v-if="appearance === 'dark'"
                        class="h-4 w-4 lg:h-5 lg:w-5"
                    />
                    <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
                </button>
                <Link
                    href="/"
                    class="group inline-flex items-center gap-2 text-[10px] font-black tracking-[0.25em] text-muted-foreground uppercase transition-colors hover:text-foreground"
                >
                    <ArrowLeft
                        class="h-3 w-3 transition-transform group-hover:-translate-x-1"
                    />
                    Back to home
                </Link>
            </div>
        </header>

        <main
            class="relative z-10 mx-auto max-w-[1500px] px-6 pt-12 pb-24 lg:px-16 lg:pt-20"
        >
            <!-- Hero -->
            <section
                class="about-hero grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-16"
            >
                <div class="flex flex-col gap-8 lg:col-span-8">
                    <div class="flex items-center gap-3">
                        <span class="h-px w-10 bg-primary"></span>
                        <span
                            class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                            >/ about_lsi</span
                        >
                    </div>
                    <h1
                        class="text-5xl leading-[0.9] font-black tracking-tighter uppercase sm:text-6xl lg:text-8xl"
                    >
                        Learning,<br />
                        <span class="text-primary">re-engineered</span><br />
                        from first principles.
                    </h1>
                    <p
                        class="max-w-xl text-sm leading-relaxed font-medium text-muted-foreground/80 lg:text-base"
                    >
                        LSI is an academic operating system built for
                        institutions that take learning seriously. We exist to
                        remove friction between teachers, students, and the
                        moments that actually matter — the ones where
                        understanding clicks into place.
                    </p>
                </div>
                <div class="flex justify-end lg:col-span-4">
                    <div class="grid w-full grid-cols-3 gap-6 lg:max-w-md">
                        <div
                            v-for="(stat, i) in [
                                { label: 'Learners', value: totalUsers ?? 144 },
                                { label: 'Exams', value: totalExams ?? 7 },
                                {
                                    label: 'Submissions',
                                    value: totalSubmissions ?? 719,
                                },
                            ]"
                            :key="i"
                            class="flex flex-col gap-2 border border-border/40 bg-card/50 p-4 shadow-sm lg:p-6"
                        >
                            <span
                                class="text-[8px] font-black tracking-[0.2em] text-muted-foreground/60 uppercase"
                                >{{ stat.label }}</span
                            >
                            <span
                                class="text-3xl font-black tracking-tight tabular-nums lg:text-4xl"
                                >{{ stat.value.toLocaleString() }}</span
                            >
                        </div>
                    </div>
                </div>
            </section>

            <!-- Mission / Vision -->
            <section
                class="reveal-block mt-32 grid grid-cols-1 gap-16 lg:mt-48 lg:grid-cols-2 lg:gap-24"
            >
                <article
                    class="flex flex-col gap-6 border-l-2 border-primary pl-8 lg:pl-12"
                >
                    <div class="flex items-center gap-3">
                        <Compass class="h-4 w-4 text-primary" />
                        <span
                            class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                            >Mission</span
                        >
                    </div>
                    <div class="space-y-6">
                        <h2
                            class="text-3xl leading-[1.1] font-black tracking-tighter uppercase lg:text-5xl"
                        >
                            Make assessment a tool for
                            <span class="text-primary">growth</span>, not
                            surveillance.
                        </h2>
                        <p
                            class="max-w-lg text-sm leading-relaxed text-muted-foreground/70 lg:text-base"
                        >
                            We believe great assessment is generous: it teaches
                            as it measures. Our engine turns every exam,
                            assignment, and quiz into a feedback loop students
                            actually want to engage with.
                        </p>
                    </div>
                </article>

                <article
                    class="flex flex-col gap-6 border-l-2 border-border/20 pl-8 lg:pl-12"
                >
                    <div class="flex items-center gap-3">
                        <Layers class="h-4 w-4 text-muted-foreground/40" />
                        <span
                            class="text-[10px] font-black tracking-[0.4em] text-muted-foreground/40 uppercase"
                            >Vision</span
                        >
                    </div>
                    <div class="space-y-6">
                        <h2
                            class="text-3xl leading-[1.1] font-black tracking-tighter uppercase lg:text-5xl"
                        >
                            A learning platform that feels less like software
                            and more like a
                            <span class="relative inline-block">
                                PLACE.
                                <span
                                    class="absolute -bottom-2 left-0 h-1 w-full bg-primary/30"
                                ></span>
                            </span>
                        </h2>
                        <p
                            class="max-w-lg text-sm leading-relaxed text-muted-foreground/70 lg:text-base"
                        >
                            Classrooms, dashboards, and learning maps that fade
                            into the background — leaving the human
                            relationships at the center of every cohort.
                        </p>
                    </div>
                </article>
            </section>

            <!-- Principles -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ principles</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            What we believe
                        </h2>
                    </div>
                </div>
                <div
                    class="grid grid-cols-1 gap-px bg-border/15 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="(p, i) in principles"
                        :key="p.title"
                        class="reveal-block flex flex-col gap-4 bg-background p-6 transition-colors hover:bg-muted/[0.03] lg:p-8"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-10 w-10 items-center justify-center border border-primary/30 bg-primary/5"
                            >
                                <component
                                    :is="p.icon"
                                    class="h-4 w-4 text-primary"
                                />
                            </div>
                            <span
                                class="text-[9px] font-black tracking-[0.3em] text-muted-foreground/40 uppercase"
                                >0{{ i + 1 }}</span
                            >
                        </div>
                        <h3
                            class="text-base leading-tight font-black tracking-tight uppercase lg:text-lg"
                        >
                            {{ p.title }}
                        </h3>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground lg:text-sm"
                        >
                            {{ p.body }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Timeline -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ timeline</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            How we got here
                        </h2>
                    </div>
                </div>
                <div class="relative">
                    <!-- Spine: mobile at left edge, desktop centered -->
                    <div
                        class="absolute top-0 bottom-0 left-3 w-px -translate-x-1/2 bg-border/30 lg:left-1/2"
                    ></div>
                    <div class="flex flex-col gap-12 lg:gap-20">
                        <div
                            v-for="(m, i) in milestones"
                            :key="m.year"
                            class="timeline-row relative grid grid-cols-[24px_1fr] items-start gap-x-6 lg:grid-cols-[1fr_24px_1fr] lg:gap-x-10"
                        >
                            <!-- Mobile: stacked year + title + body (full width on mobile, hidden on lg) -->
                            <div
                                class="col-start-2 flex flex-col gap-2 lg:hidden"
                            >
                                <span
                                    class="text-5xl leading-none font-black text-primary/90 tabular-nums"
                                    >{{ m.year }}</span
                                >
                                <h3
                                    class="mt-2 text-xl font-black tracking-tight uppercase"
                                >
                                    {{ m.title }}
                                </h3>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ m.body }}
                                </p>
                            </div>

                            <!-- Desktop: YEAR (placed left on even rows, right on odd rows) -->
                            <div
                                class="hidden items-start lg:flex"
                                :class="
                                    i % 2 === 0
                                        ? 'lg:col-start-1 lg:justify-end lg:pr-2'
                                        : 'lg:col-start-3 lg:justify-start lg:pl-2'
                                "
                            >
                                <span
                                    class="text-5xl leading-none font-black text-primary/90 tabular-nums lg:text-7xl"
                                    >{{ m.year }}</span
                                >
                            </div>

                            <!-- Spine dot (mobile col 1, desktop col 2) -->
                            <div
                                class="relative col-start-1 row-start-1 flex justify-center lg:col-start-2"
                            >
                                <span
                                    class="mt-2 inline-block h-3 w-3 rounded-full bg-primary ring-4 ring-background"
                                ></span>
                            </div>

                            <!-- Desktop: DETAILS (placed right on even rows, left on odd rows) -->
                            <div
                                class="hidden lg:block"
                                :class="
                                    i % 2 === 0
                                        ? 'lg:col-start-3 lg:pl-2 lg:text-left'
                                        : 'lg:col-start-1 lg:pr-2 lg:text-right'
                                "
                            >
                                <h3
                                    class="mb-2 text-xl font-black tracking-tight uppercase lg:text-2xl"
                                >
                                    {{ m.title }}
                                </h3>
                                <p
                                    class="max-w-md text-xs leading-relaxed text-muted-foreground lg:text-sm"
                                    :class="i % 2 === 0 ? '' : 'lg:ml-auto'"
                                >
                                    {{ m.body }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Interactive: Role Picker -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Briefcase class="h-4 w-4 text-primary" />
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ explore</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            What LSI does for
                            <span class="text-primary">you</span>
                        </h2>
                        <p class="max-w-xl text-sm text-muted-foreground">
                            Pick the role that fits — see what changes for you
                            on day one.
                        </p>
                    </div>
                </div>
                <div
                    class="reveal-block border border-border/20 bg-gradient-to-br from-muted/[0.03] to-transparent"
                >
                    <!-- Tabs -->
                    <div
                        role="tablist"
                        class="grid grid-cols-3 border-b border-border/15"
                    >
                        <button
                            v-for="r in roles"
                            :key="r.key"
                            role="tab"
                            :aria-selected="activeRole === r.key"
                            @click="activeRole = r.key"
                            class="group relative flex flex-col items-center justify-center gap-2 px-3 py-5 text-[10px] font-black tracking-[0.25em] uppercase transition-colors sm:flex-row sm:gap-3 lg:py-6 lg:text-xs"
                            :class="
                                activeRole === r.key
                                    ? 'bg-background text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <component
                                :is="r.icon"
                                class="h-4 w-4"
                                :class="
                                    activeRole === r.key ? 'text-primary' : ''
                                "
                            />
                            <span>{{ r.label }}</span>
                            <span
                                class="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-primary transition-transform duration-500"
                                :class="
                                    activeRole === r.key
                                        ? 'scale-x-100'
                                        : 'scale-x-0'
                                "
                            ></span>
                        </button>
                    </div>
                    <!-- Panel -->
                    <div
                        :key="activeRole"
                        class="role-panel grid grid-cols-1 gap-8 p-6 lg:grid-cols-[1.4fr_1fr] lg:gap-12 lg:p-12"
                    >
                        <div class="flex flex-col gap-4">
                            <span
                                class="text-[10px] font-black tracking-[0.3em] text-primary uppercase"
                                >{{ currentRole.label }}</span
                            >
                            <h3
                                class="text-2xl leading-tight font-black tracking-tight uppercase lg:text-4xl"
                            >
                                {{ currentRole.headline }}
                            </h3>
                            <p
                                class="max-w-xl text-sm leading-relaxed text-muted-foreground lg:text-base"
                            >
                                {{ currentRole.body }}
                            </p>
                        </div>
                        <ul
                            class="flex flex-col gap-3 lg:border-l lg:border-border/20 lg:pl-8"
                        >
                            <li
                                v-for="(b, i) in currentRole.bullets"
                                :key="b"
                                class="flex items-start gap-3 text-xs lg:text-sm"
                                :style="{ animationDelay: `${i * 80}ms` }"
                            >
                                <span
                                    class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"
                                ></span>
                                <span class="text-foreground/90">{{ b }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Interactive: Try a question -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Sparkles class="h-4 w-4 text-primary" />
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ try_it</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            A taste of the engine
                        </h2>
                        <p class="max-w-xl text-sm text-muted-foreground">
                            A 10-second demo of what an LSI question feels like
                            — instant feedback, no judgment, no scoreboard.
                        </p>
                    </div>
                </div>
                <div
                    class="reveal-block grid grid-cols-1 gap-px border border-border/15 bg-border/15 lg:grid-cols-[1fr_320px]"
                >
                    <div class="flex flex-col gap-6 bg-background p-6 lg:p-10">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                >Question · 01</span
                            >
                            <span
                                class="text-[9px] font-black tracking-[0.3em] text-primary uppercase"
                                >Pedagogy 101</span
                            >
                        </div>
                        <h3
                            class="text-lg leading-snug font-black tracking-tight lg:text-2xl"
                        >
                            {{ demoQuestion.prompt }}
                        </h3>
                        <div class="flex flex-col gap-3">
                            <button
                                v-for="opt in demoQuestion.options"
                                :key="opt.id"
                                type="button"
                                @click="selectAnswer(opt.id)"
                                :disabled="selectedAnswer !== null"
                                class="group relative flex items-start gap-4 border p-4 text-left transition-all"
                                :class="[
                                    selectedAnswer === null &&
                                        'cursor-pointer border-border/30 hover:border-primary/60 hover:bg-primary/5',
                                    selectedAnswer !== null &&
                                        opt.correct &&
                                        'border-emerald-500/60 bg-emerald-500/5',
                                    selectedAnswer === opt.id &&
                                        !opt.correct &&
                                        'border-rose-500/60 bg-rose-500/5',
                                    selectedAnswer !== null &&
                                        selectedAnswer !== opt.id &&
                                        !opt.correct &&
                                        'border-border/20 opacity-50',
                                ]"
                            >
                                <span
                                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center border border-border/40 text-[10px] font-black tracking-widest uppercase"
                                    >{{ opt.id }}</span
                                >
                                <span
                                    class="flex-1 text-sm leading-snug font-medium lg:text-[15px]"
                                    >{{ opt.text }}</span
                                >
                                <CheckCircle2
                                    v-if="
                                        selectedAnswer !== null && opt.correct
                                    "
                                    class="h-5 w-5 shrink-0 text-emerald-500"
                                />
                                <XCircle
                                    v-else-if="
                                        selectedAnswer === opt.id &&
                                        !opt.correct
                                    "
                                    class="h-5 w-5 shrink-0 text-rose-500"
                                />
                            </button>
                        </div>
                    </div>
                    <aside
                        class="flex flex-col justify-between gap-4 bg-background p-6 lg:p-8"
                    >
                        <div class="flex flex-col gap-3">
                            <span
                                class="text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                                >Live feedback</span
                            >
                            <div
                                v-if="!showFeedback"
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                Pick an answer to see how LSI replies — students
                                get the same kind of instant, supportive
                                feedback after every prompt.
                            </div>
                            <div
                                v-else-if="isCorrect"
                                class="flex flex-col gap-2"
                            >
                                <div
                                    class="flex items-center gap-2 text-emerald-500"
                                >
                                    <CheckCircle2 class="h-5 w-5" />
                                    <span
                                        class="text-xs font-black tracking-[0.25em] uppercase"
                                        >Correct</span
                                    >
                                </div>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    Exactly. Formative assessment is about
                                    <span class="font-bold text-foreground"
                                        >guiding</span
                                    >
                                    learning while it's still happening — not
                                    ranking it after.
                                </p>
                            </div>
                            <div v-else class="flex flex-col gap-2">
                                <div
                                    class="flex items-center gap-2 text-rose-500"
                                >
                                    <XCircle class="h-5 w-5" />
                                    <span
                                        class="text-xs font-black tracking-[0.25em] uppercase"
                                        >Not quite</span
                                    >
                                </div>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    That describes
                                    <span class="font-bold text-foreground"
                                        >summative</span
                                    >
                                    assessment. Formative happens
                                    <em>during</em> learning to inform what
                                    comes next.
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="showFeedback"
                            @click="resetDemo"
                            class="inline-flex items-center gap-2 self-start border border-border/30 px-3 py-2 text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-colors hover:border-primary/60 hover:text-foreground"
                        >
                            <RotateCcw class="h-3 w-3" />
                            Try again
                        </button>
                    </aside>
                </div>
            </section>

            <!-- Team -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Users class="h-4 w-4 text-primary" />
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ the_humans</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            Built by a small, opinionated team
                        </h2>
                    </div>
                </div>
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6"
                >
                    <div
                        v-for="t in team"
                        :key="t.name"
                        class="reveal-block group flex min-h-[180px] flex-col justify-between gap-6 border border-border/20 bg-gradient-to-br from-muted/[0.02] to-transparent p-6 transition-colors hover:border-primary/40"
                    >
                        <span
                            class="self-start border border-primary/30 px-2 py-1 text-[8px] font-black tracking-[0.3em] text-primary uppercase"
                            >{{ t.tag }}</span
                        >
                        <div class="flex flex-col gap-1">
                            <h3
                                class="text-base leading-tight font-black tracking-tight uppercase"
                            >
                                {{ t.name }}
                            </h3>
                            <p
                                class="text-[11px] font-bold tracking-[0.18em] text-muted-foreground/70 uppercase"
                            >
                                {{ t.role }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section class="mt-24 lg:mt-40">
                <div
                    class="reveal-block mb-10 flex items-end justify-between gap-4 lg:mb-14"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span
                                class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                                >/ questions</span
                            >
                        </div>
                        <h2
                            class="text-3xl font-black tracking-tight uppercase lg:text-5xl"
                        >
                            FAQ
                        </h2>
                    </div>
                </div>
                <div
                    class="grid grid-cols-1 gap-px border border-border/15 bg-border/15 lg:grid-cols-2"
                >
                    <details
                        v-for="f in faqs"
                        :key="f.q"
                        class="reveal-block group cursor-pointer bg-background p-6 lg:p-8"
                    >
                        <summary
                            class="flex list-none items-center justify-between gap-4"
                        >
                            <h3
                                class="text-sm font-black tracking-tight uppercase lg:text-base"
                            >
                                {{ f.q }}
                            </h3>
                            <span
                                class="text-xl font-black text-primary transition-transform group-open:rotate-45"
                                >+</span
                            >
                        </summary>
                        <p
                            class="mt-4 text-xs leading-relaxed text-muted-foreground lg:text-sm"
                        >
                            {{ f.a }}
                        </p>
                    </details>
                </div>
            </section>

            <!-- CTA -->
            <section
                class="reveal-block relative mt-24 overflow-hidden border border-primary/30 bg-gradient-to-br from-primary/[0.06] via-transparent to-transparent p-8 lg:mt-40 lg:p-16"
            >
                <div
                    class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl"
                ></div>
                <div
                    class="relative grid grid-cols-1 items-center gap-8 lg:grid-cols-[1.4fr_1fr]"
                >
                    <div class="flex flex-col gap-4">
                        <span
                            class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                            >/ get_in_touch</span
                        >
                        <h2
                            class="text-3xl leading-tight font-black tracking-tight uppercase lg:text-5xl"
                        >
                            Bring LSI to your<br />institution.
                        </h2>
                        <p
                            class="max-w-xl text-sm leading-relaxed text-muted-foreground"
                        >
                            We pilot with a small number of institutions every
                            cycle. If your school cares about meaningful
                            assessment, we'd love to talk.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a
                            href="mailto:hello@koamishin.dev"
                            class="group inline-flex items-center justify-between gap-3 bg-foreground px-5 py-4 text-background transition-colors hover:bg-primary"
                        >
                            <span class="flex items-center gap-3">
                                <Mail class="h-4 w-4" />
                                <span
                                    class="text-[10px] font-black tracking-[0.3em] uppercase"
                                    >hello@koamishin.dev</span
                                >
                            </span>
                            <span
                                class="text-lg font-black transition-transform group-hover:translate-x-1"
                                >→</span
                            >
                        </a>
                        <div class="flex items-center gap-2">
                            <a
                                v-for="s in [
                                    { icon: Github, label: 'GitHub' },
                                    { icon: Twitter, label: 'Twitter' },
                                    { icon: Linkedin, label: 'LinkedIn' },
                                ]"
                                :key="s.label"
                                href="#"
                                :aria-label="s.label"
                                class="flex h-10 w-10 items-center justify-center border border-border/30 transition-colors hover:border-primary/60 hover:bg-primary/5"
                            >
                                <component
                                    :is="s.icon"
                                    class="h-3.5 w-3.5 text-muted-foreground hover:text-primary"
                                />
                            </a>
                            <button
                                @click="scrollTop"
                                class="ml-auto inline-flex items-center gap-2 border border-border/30 px-3 py-2 text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-colors hover:border-primary/60 hover:text-foreground"
                            >
                                <span>Top</span>
                                <ArrowUp class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>

<style scoped>
details > summary::-webkit-details-marker {
    display: none;
}

.role-panel {
    animation: rolePanelIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.role-panel li {
    animation: roleBulletIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes rolePanelIn {
    from {
        opacity: 0;
        transform: translateY(12px);
        filter: blur(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
        filter: blur(0);
    }
}
@keyframes roleBulletIn {
    from {
        opacity: 0;
        transform: translateX(-8px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .role-panel,
    .role-panel li {
        animation: none;
    }
}
</style>
