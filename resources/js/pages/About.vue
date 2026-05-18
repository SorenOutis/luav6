<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Command, ArrowLeft, Target, Compass, Sparkles, ShieldCheck,
    Users, Layers, BookOpenCheck, Mail, Github, Twitter, Linkedin, ArrowUp,
    GraduationCap, Briefcase, UserCog, CheckCircle2, XCircle, RotateCcw,
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
    { year: '2023', title: 'First seed',  body: 'A single classroom prototype tests adaptive assessment for high-school students.' },
    { year: '2024', title: 'Open beta',   body: 'Pilot deployments across three institutions; the engine handles thousands of submissions weekly.' },
    { year: '2025', title: 'LSI v5',      body: 'Live Pulse, Architecture Stack, and the Learning Map ship — turning grades into journeys.' },
    { year: '2026', title: 'LSI v6',      body: 'Full revamp: real-time metrics, AI-assisted authoring, and a unified design system.' },
];

const team = [
    { name: 'Koamishin Studio', role: 'Engineering & Design',   tag: 'Core' },
    { name: 'Faculty Council',   role: 'Academic Direction',     tag: 'Advisors' },
    { name: 'Pilot Cohort',      role: 'Real-classroom Feedback',tag: 'Partners' },
    { name: 'Open Contributors', role: 'Community Modules',      tag: 'Network' },
];

const faqs = [
    { q: 'What does LSI stand for?', a: 'Learning Systems Infrastructure — the underlying engine powering everything you see in this product.' },
    { q: 'Is LSI free for educators?', a: 'Pilot programs are free for verified institutions during the v6 cycle. Get in touch via the contact link below.' },
    { q: 'Do students need any special device or software?', a: 'No. LSI runs in any modern browser on phones, tablets, or laptops — no installs, no plug-ins. A reliable internet connection is all that\'s required.' },
    { q: 'How is student data protected?', a: 'All submissions are encrypted at rest and in transit. We do not sell data, and institutions retain full ownership of their content.' },
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
        bullets: ['Section-aware leaderboards', 'AI-assisted feedback drafts', 'One-click reusable exam parts'],
    },
    {
        key: 'student' as const,
        icon: Users,
        label: 'Student',
        headline: 'Learn with momentum, not anxiety.',
        body: 'Streaks, badges, and the Learning Map turn each lesson into a checkpoint on a clear journey — instead of a wall of grades.',
        bullets: ['Daily streaks & XP', 'Personal learning map', 'Anonymous classroom messaging'],
    },
    {
        key: 'admin' as const,
        icon: UserCog,
        label: 'Administrator',
        headline: 'See your whole campus at a glance.',
        body: 'Filament-powered admin tools give you full visibility into seasons, sections, submissions, and engagement across the institution.',
        bullets: ['Per-section analytics', 'Season & cohort management', 'Granular permissions'],
    },
];
const currentRole = computed(() => roles.find(r => r.key === activeRole.value)!);

// Interactive: mini quiz demo
const demoQuestion = {
    prompt: 'Which of the following best describes formative assessment?',
    options: [
        { id: 'a', text: 'A high-stakes ranking exam at the end of a course.' },
        { id: 'b', text: 'Ongoing checks for understanding that guide further learning.', correct: true },
        { id: 'c', text: 'A standardized test administered nationally.' },
    ],
};
const selectedAnswer = ref<string | null>(null);
const showFeedback = computed(() => selectedAnswer.value !== null);
const isCorrect = computed(() => {
    const opt = demoQuestion.options.find(o => o.id === selectedAnswer.value);
    return !!opt?.correct;
});
const selectAnswer = (id: string) => {
    if (selectedAnswer.value !== null) return;
    selectedAnswer.value = id;
};
const resetDemo = () => { selectedAnswer.value = null; };

onMounted(() => {
    ctx = gsap.context(() => {
        gsap.from('.about-hero > *', {
            y: 40, opacity: 0, filter: 'blur(12px)',
            duration: 1.1, ease: 'expo.out', stagger: 0.08,
        });

        gsap.utils.toArray<HTMLElement>('.reveal-block').forEach((el) => {
            gsap.from(el, {
                y: 40, opacity: 0, filter: 'blur(10px)',
                duration: 0.9, ease: 'expo.out',
                scrollTrigger: { trigger: el, start: 'top 85%' },
            });
        });

        gsap.utils.toArray<HTMLElement>('.timeline-row').forEach((el, i) => {
            gsap.from(el, {
                x: i % 2 === 0 ? -40 : 40,
                opacity: 0, filter: 'blur(8px)',
                duration: 0.9, ease: 'expo.out',
                scrollTrigger: { trigger: el, start: 'top 85%' },
            });
        });

        // Directional reveal: each section is visible only while it overlaps the viewport.
        nextTick(() => {
            const sections = Array.from(root.value?.querySelectorAll<HTMLElement>('main > section') ?? []);
            const viewportH = window.innerHeight;
            sections.forEach((section) => {
                if (section.offsetHeight < 40) return;
                gsap.set(section, { willChange: 'transform, opacity, filter' });
                const hideUp = () => gsap.to(section, { opacity: 0, y: -40, filter: 'blur(10px)', duration: 0.55, ease: 'power2.in', overwrite: 'auto' });
                const hideDown = () => gsap.to(section, { opacity: 0, y: 40, filter: 'blur(10px)', duration: 0.55, ease: 'power2.in', overwrite: 'auto' });
                const show = () => gsap.to(section, { opacity: 1, y: 0, filter: 'blur(0px)', duration: 0.7, ease: 'expo.out', overwrite: 'auto' });

                const rect = section.getBoundingClientRect();
                if (rect.top > viewportH * 0.95) {
                    gsap.set(section, { opacity: 0, y: 40, filter: 'blur(10px)' });
                }
                ScrollTrigger.create({ trigger: section, start: 'bottom top+=40', end: 'bottom top', onLeave: hideUp, onEnterBack: show });
                ScrollTrigger.create({ trigger: section, start: 'top bottom-=40', end: 'top bottom', onEnter: show, onLeaveBack: hideDown });
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

    <div ref="root" class="theme-about relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20">
        <!-- Background grid -->
        <div class="pointer-events-none fixed inset-0 z-0 opacity-[0.05]"
             style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>

        <!-- Header -->
        <header class="sticky top-0 z-50 flex w-full items-center justify-between px-6 py-5 lg:px-16 lg:py-8 bg-transparent">
            <Link href="/" class="flex items-center gap-3 group">
                <div class="relative flex h-10 w-10 items-center justify-center transition-transform duration-700 group-hover:rotate-180">
                    <Command class="h-6 w-6 lg:h-7 lg:w-7 relative z-10 text-foreground" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] lg:text-xs font-black tracking-[0.4em] uppercase text-foreground">LSI Engine</span>
                    <span class="text-[7px] lg:text-[8px] font-bold text-primary uppercase mt-1 tracking-widest">/ about</span>
                </div>
            </Link>

            <div class="flex items-center gap-4 lg:gap-8">
                <button @click="toggleTheme" class="p-2 text-muted-foreground hover:text-foreground transition-colors" aria-label="Toggle theme">
                    <Sun v-if="appearance === 'dark'" class="h-4 w-4 lg:h-5 lg:w-5" />
                    <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
                </button>
                <Link href="/" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.25em] text-muted-foreground hover:text-foreground transition-colors group">
                    <ArrowLeft class="h-3 w-3 transition-transform group-hover:-translate-x-1" />
                    Back to home
                </Link>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-[1500px] px-6 lg:px-16 pt-12 lg:pt-20 pb-24">
            <!-- Hero -->
            <section class="about-hero grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16 items-start">
                <div class="lg:col-span-8 flex flex-col gap-8">
                    <div class="flex items-center gap-3">
                        <span class="h-px w-10 bg-primary"></span>
                        <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ about_lsi</span>
                    </div>
                    <h1 class="text-5xl sm:text-6xl lg:text-8xl font-black uppercase leading-[0.9] tracking-tighter">
                        Learning,<br/>
                        <span class="text-primary">re-engineered</span><br/>
                        from first principles.
                    </h1>
                    <p class="max-w-xl text-sm lg:text-base text-muted-foreground/80 leading-relaxed font-medium">
                        LSI is an academic operating system built for institutions that take learning seriously. We exist to remove friction between teachers, students, and the moments that actually matter — the ones where understanding clicks into place.
                    </p>
                </div>
                <div class="lg:col-span-4 flex justify-end">
                    <div class="grid grid-cols-3 gap-6 w-full lg:max-w-md">
                        <div v-for="(stat, i) in [
                            { label: 'Learners',    value: totalUsers ?? 144 },
                            { label: 'Exams',       value: totalExams ?? 7 },
                            { label: 'Submissions', value: totalSubmissions ?? 719 },
                        ]" :key="i" class="border border-border/40 bg-card/50 p-4 lg:p-6 flex flex-col gap-2 shadow-sm">
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ stat.label }}</span>
                            <span class="text-3xl lg:text-4xl font-black tabular-nums tracking-tight">{{ stat.value.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Mission / Vision -->
            <section class="reveal-block mt-32 lg:mt-48 grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-24">
                <article class="border-l-2 border-primary pl-8 lg:pl-12 flex flex-col gap-6">
                    <div class="flex items-center gap-3">
                        <Compass class="h-4 w-4 text-primary" />
                        <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">Mission</span>
                    </div>
                    <div class="space-y-6">
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tighter leading-[1.1]">
                            Make assessment a tool for <span class="text-primary">growth</span>, not surveillance.
                        </h2>
                        <p class="text-sm lg:text-base text-muted-foreground/70 leading-relaxed max-w-lg">
                            We believe great assessment is generous: it teaches as it measures. Our engine turns every exam, assignment, and quiz into a feedback loop students actually want to engage with.
                        </p>
                    </div>
                </article>

                <article class="flex flex-col gap-6 border-l-2 border-border/20 pl-8 lg:pl-12">
                    <div class="flex items-center gap-3">
                        <Layers class="h-4 w-4 text-muted-foreground/40" />
                        <span class="text-[10px] font-black uppercase tracking-[0.4em] text-muted-foreground/40">Vision</span>
                    </div>
                    <div class="space-y-6">
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tighter leading-[1.1]">
                            A learning platform that feels less like software and more like a <span class="relative inline-block">
                                PLACE.
                                <span class="absolute -bottom-2 left-0 w-full h-1 bg-primary/30"></span>
                            </span>
                        </h2>
                        <p class="text-sm lg:text-base text-muted-foreground/70 leading-relaxed max-w-lg">
                            Classrooms, dashboards, and learning maps that fade into the background — leaving the human relationships at the center of every cohort.
                        </p>
                    </div>
                </article>
            </section>

            <!-- Principles -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ principles</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">What we believe</h2>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-border/15">
                    <div
                        v-for="(p, i) in principles"
                        :key="p.title"
                        class="reveal-block flex flex-col gap-4 p-6 lg:p-8 bg-background hover:bg-muted/[0.03] transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-10 w-10 items-center justify-center border border-primary/30 bg-primary/5">
                                <component :is="p.icon" class="h-4 w-4 text-primary" />
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground/40">0{{ i + 1 }}</span>
                        </div>
                        <h3 class="text-base lg:text-lg font-black uppercase tracking-tight leading-tight">{{ p.title }}</h3>
                        <p class="text-xs lg:text-sm text-muted-foreground leading-relaxed">{{ p.body }}</p>
                    </div>
                </div>
            </section>

            <!-- Timeline -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ timeline</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">How we got here</h2>
                    </div>
                </div>
                <div class="relative">
                    <!-- Spine: mobile at left edge, desktop centered -->
                    <div class="absolute left-3 lg:left-1/2 top-0 bottom-0 w-px bg-border/30 -translate-x-1/2"></div>
                    <div class="flex flex-col gap-12 lg:gap-20">
                        <div
                            v-for="(m, i) in milestones"
                            :key="m.year"
                            class="timeline-row relative grid grid-cols-[24px_1fr] lg:grid-cols-[1fr_24px_1fr] gap-x-6 lg:gap-x-10 items-start"
                        >
                            <!-- Mobile: stacked year + title + body (full width on mobile, hidden on lg) -->
                            <div class="lg:hidden col-start-2 flex flex-col gap-2">
                                <span class="text-5xl font-black tabular-nums leading-none text-primary/90">{{ m.year }}</span>
                                <h3 class="text-xl font-black uppercase tracking-tight mt-2">{{ m.title }}</h3>
                                <p class="text-xs text-muted-foreground leading-relaxed">{{ m.body }}</p>
                            </div>

                            <!-- Desktop: YEAR (placed left on even rows, right on odd rows) -->
                            <div
                                class="hidden lg:flex items-start"
                                :class="i % 2 === 0 ? 'lg:col-start-1 lg:justify-end lg:pr-2' : 'lg:col-start-3 lg:justify-start lg:pl-2'"
                            >
                                <span class="text-5xl lg:text-7xl font-black tabular-nums leading-none text-primary/90">{{ m.year }}</span>
                            </div>

                            <!-- Spine dot (mobile col 1, desktop col 2) -->
                            <div class="col-start-1 lg:col-start-2 row-start-1 relative flex justify-center">
                                <span class="mt-2 inline-block h-3 w-3 rounded-full bg-primary ring-4 ring-background"></span>
                            </div>

                            <!-- Desktop: DETAILS (placed right on even rows, left on odd rows) -->
                            <div
                                class="hidden lg:block"
                                :class="i % 2 === 0 ? 'lg:col-start-3 lg:pl-2 lg:text-left' : 'lg:col-start-1 lg:pr-2 lg:text-right'"
                            >
                                <h3 class="text-xl lg:text-2xl font-black uppercase tracking-tight mb-2">{{ m.title }}</h3>
                                <p
                                    class="text-xs lg:text-sm text-muted-foreground leading-relaxed max-w-md"
                                    :class="i % 2 === 0 ? '' : 'lg:ml-auto'"
                                >{{ m.body }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Interactive: Role Picker -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Briefcase class="h-4 w-4 text-primary" />
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ explore</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">What LSI does for <span class="text-primary">you</span></h2>
                        <p class="text-sm text-muted-foreground max-w-xl">Pick the role that fits — see what changes for you on day one.</p>
                    </div>
                </div>
                <div class="reveal-block border border-border/20 bg-gradient-to-br from-muted/[0.03] to-transparent">
                    <!-- Tabs -->
                    <div role="tablist" class="grid grid-cols-3 border-b border-border/15">
                        <button
                            v-for="r in roles"
                            :key="r.key"
                            role="tab"
                            :aria-selected="activeRole === r.key"
                            @click="activeRole = r.key"
                            class="group relative flex flex-col sm:flex-row items-center justify-center gap-2 sm:gap-3 px-3 py-5 lg:py-6 text-[10px] lg:text-xs font-black uppercase tracking-[0.25em] transition-colors"
                            :class="activeRole === r.key ? 'text-foreground bg-background' : 'text-muted-foreground hover:text-foreground'"
                        >
                            <component :is="r.icon" class="h-4 w-4" :class="activeRole === r.key ? 'text-primary' : ''" />
                            <span>{{ r.label }}</span>
                            <span
                                class="absolute inset-x-0 bottom-0 h-0.5 bg-primary origin-left transition-transform duration-500"
                                :class="activeRole === r.key ? 'scale-x-100' : 'scale-x-0'"
                            ></span>
                        </button>
                    </div>
                    <!-- Panel -->
                    <div :key="activeRole" class="role-panel grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-8 lg:gap-12 p-6 lg:p-12">
                        <div class="flex flex-col gap-4">
                            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-primary">{{ currentRole.label }}</span>
                            <h3 class="text-2xl lg:text-4xl font-black uppercase tracking-tight leading-tight">{{ currentRole.headline }}</h3>
                            <p class="text-sm lg:text-base text-muted-foreground leading-relaxed max-w-xl">{{ currentRole.body }}</p>
                        </div>
                        <ul class="flex flex-col gap-3 lg:border-l lg:border-border/20 lg:pl-8">
                            <li
                                v-for="(b, i) in currentRole.bullets"
                                :key="b"
                                class="flex items-start gap-3 text-xs lg:text-sm"
                                :style="{ animationDelay: `${i * 80}ms` }"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                                <span class="text-foreground/90">{{ b }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Interactive: Try a question -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Sparkles class="h-4 w-4 text-primary" />
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ try_it</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">A taste of the engine</h2>
                        <p class="text-sm text-muted-foreground max-w-xl">A 10-second demo of what an LSI question feels like — instant feedback, no judgment, no scoreboard.</p>
                    </div>
                </div>
                <div class="reveal-block grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-px bg-border/15 border border-border/15">
                    <div class="bg-background p-6 lg:p-10 flex flex-col gap-6">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground">Question · 01</span>
                            <span class="text-[9px] font-black uppercase tracking-[0.3em] text-primary">Pedagogy 101</span>
                        </div>
                        <h3 class="text-lg lg:text-2xl font-black tracking-tight leading-snug">{{ demoQuestion.prompt }}</h3>
                        <div class="flex flex-col gap-3">
                            <button
                                v-for="opt in demoQuestion.options"
                                :key="opt.id"
                                type="button"
                                @click="selectAnswer(opt.id)"
                                :disabled="selectedAnswer !== null"
                                class="group relative flex items-start gap-4 border p-4 text-left transition-all"
                                :class="[
                                    selectedAnswer === null && 'border-border/30 hover:border-primary/60 hover:bg-primary/5 cursor-pointer',
                                    selectedAnswer !== null && opt.correct && 'border-emerald-500/60 bg-emerald-500/5',
                                    selectedAnswer === opt.id && !opt.correct && 'border-rose-500/60 bg-rose-500/5',
                                    selectedAnswer !== null && selectedAnswer !== opt.id && !opt.correct && 'border-border/20 opacity-50',
                                ]"
                            >
                                <span class="mt-0.5 flex h-6 w-6 items-center justify-center border border-border/40 text-[10px] font-black uppercase tracking-widest shrink-0">{{ opt.id }}</span>
                                <span class="text-sm lg:text-[15px] font-medium leading-snug flex-1">{{ opt.text }}</span>
                                <CheckCircle2 v-if="selectedAnswer !== null && opt.correct" class="h-5 w-5 text-emerald-500 shrink-0" />
                                <XCircle v-else-if="selectedAnswer === opt.id && !opt.correct" class="h-5 w-5 text-rose-500 shrink-0" />
                            </button>
                        </div>
                    </div>
                    <aside class="bg-background p-6 lg:p-8 flex flex-col gap-4 justify-between">
                        <div class="flex flex-col gap-3">
                            <span class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground">Live feedback</span>
                            <div v-if="!showFeedback" class="text-xs text-muted-foreground leading-relaxed">
                                Pick an answer to see how LSI replies — students get the same kind of instant, supportive feedback after every prompt.
                            </div>
                            <div v-else-if="isCorrect" class="flex flex-col gap-2">
                                <div class="flex items-center gap-2 text-emerald-500">
                                    <CheckCircle2 class="h-5 w-5" />
                                    <span class="text-xs font-black uppercase tracking-[0.25em]">Correct</span>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">Exactly. Formative assessment is about <span class="text-foreground font-bold">guiding</span> learning while it's still happening — not ranking it after.</p>
                            </div>
                            <div v-else class="flex flex-col gap-2">
                                <div class="flex items-center gap-2 text-rose-500">
                                    <XCircle class="h-5 w-5" />
                                    <span class="text-xs font-black uppercase tracking-[0.25em]">Not quite</span>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">That describes <span class="text-foreground font-bold">summative</span> assessment. Formative happens <em>during</em> learning to inform what comes next.</p>
                            </div>
                        </div>
                        <button
                            v-if="showFeedback"
                            @click="resetDemo"
                            class="self-start inline-flex items-center gap-2 border border-border/30 hover:border-primary/60 px-3 py-2 text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <RotateCcw class="h-3 w-3" />
                            Try again
                        </button>
                    </aside>
                </div>
            </section>

            <!-- Team -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <Users class="h-4 w-4 text-primary" />
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ the_humans</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">Built by a small, opinionated team</h2>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                    <div
                        v-for="t in team"
                        :key="t.name"
                        class="reveal-block group flex flex-col justify-between gap-6 border border-border/20 hover:border-primary/40 transition-colors p-6 min-h-[180px] bg-gradient-to-br from-muted/[0.02] to-transparent"
                    >
                        <span class="self-start text-[8px] font-black uppercase tracking-[0.3em] text-primary border border-primary/30 px-2 py-1">{{ t.tag }}</span>
                        <div class="flex flex-col gap-1">
                            <h3 class="text-base font-black uppercase tracking-tight leading-tight">{{ t.name }}</h3>
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground/70">{{ t.role }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block flex items-end justify-between gap-4 mb-10 lg:mb-14">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-10 bg-primary"></span>
                            <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ questions</span>
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight">FAQ</h2>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-px bg-border/15 border border-border/15">
                    <details
                        v-for="f in faqs"
                        :key="f.q"
                        class="reveal-block group bg-background p-6 lg:p-8 cursor-pointer"
                    >
                        <summary class="flex items-center justify-between gap-4 list-none">
                            <h3 class="text-sm lg:text-base font-black uppercase tracking-tight">{{ f.q }}</h3>
                            <span class="text-primary text-xl font-black transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-4 text-xs lg:text-sm text-muted-foreground leading-relaxed">{{ f.a }}</p>
                    </details>
                </div>
            </section>

            <!-- CTA -->
            <section class="reveal-block mt-24 lg:mt-40 relative border border-primary/30 bg-gradient-to-br from-primary/[0.06] via-transparent to-transparent p-8 lg:p-16 overflow-hidden">
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl"></div>
                <div class="relative grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-8 items-center">
                    <div class="flex flex-col gap-4">
                        <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ get_in_touch</span>
                        <h2 class="text-3xl lg:text-5xl font-black uppercase tracking-tight leading-tight">
                            Bring LSI to your<br/>institution.
                        </h2>
                        <p class="max-w-xl text-sm text-muted-foreground leading-relaxed">
                            We pilot with a small number of institutions every cycle. If your school cares about meaningful assessment, we'd love to talk.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="mailto:hello@koamishin.dev" class="group inline-flex items-center justify-between gap-3 bg-foreground text-background px-5 py-4 hover:bg-primary transition-colors">
                            <span class="flex items-center gap-3">
                                <Mail class="h-4 w-4" />
                                <span class="text-[10px] font-black uppercase tracking-[0.3em]">hello@koamishin.dev</span>
                            </span>
                            <span class="text-lg font-black group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                        <div class="flex items-center gap-2">
                            <a v-for="s in [{ icon: Github, label: 'GitHub' }, { icon: Twitter, label: 'Twitter' }, { icon: Linkedin, label: 'LinkedIn' }]"
                               :key="s.label" href="#" :aria-label="s.label"
                               class="flex h-10 w-10 items-center justify-center border border-border/30 hover:border-primary/60 hover:bg-primary/5 transition-colors">
                                <component :is="s.icon" class="h-3.5 w-3.5 text-muted-foreground hover:text-primary" />
                            </a>
                            <button @click="scrollTop" class="ml-auto inline-flex items-center gap-2 border border-border/30 hover:border-primary/60 px-3 py-2 text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground transition-colors">
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
details > summary::-webkit-details-marker { display: none; }

.role-panel {
    animation: rolePanelIn .55s cubic-bezier(.16,1,.3,1) both;
}
.role-panel li {
    animation: roleBulletIn .5s cubic-bezier(.16,1,.3,1) both;
}
@keyframes rolePanelIn {
    from { opacity: 0; transform: translateY(12px); filter: blur(6px); }
    to   { opacity: 1; transform: translateY(0);    filter: blur(0); }
}
@keyframes roleBulletIn {
    from { opacity: 0; transform: translateX(-8px); }
    to   { opacity: 1; transform: translateX(0); }
}
@media (prefers-reduced-motion: reduce) {
    .role-panel, .role-panel li { animation: none; }
}
</style>
