<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Target,
    Compass,
    Sparkles,
    ShieldCheck,
    Layers,
    Mail,
    Github,
    Twitter,
    Linkedin,
    ArrowUp,
    GraduationCap,
    Users,
    UserCog,
    CheckCircle2,
    XCircle,
    RotateCcw,
    ArrowRight,
} from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import SeoHead from '@/components/Seo/SeoHead.vue';
import WelcomeFooter from '@/components/welcome/WelcomeFooter.vue';
import WelcomeHeader from '@/components/welcome/WelcomeHeader.vue';
import { syncLenisWithGsap } from '@/composables/useLenis';
gsap.registerPlugin(ScrollTrigger);

defineProps<{
    canRegister: boolean;
    totalUsers?: number;
    totalExams?: number;
    totalSubmissions?: number;
}>();

const root = ref<HTMLElement | null>(null);
let ctx: gsap.Context | null = null;
let lenisCleanup: (() => void) | null = null;

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
        body: 'Clear progress signals and a Learning Map make achievement feel inevitable — without ever being patronizing.',
    },
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
        body: 'Author exams in minutes, push assignments to whole sections, and watch real-time mastery build through the dashboard.',
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
        body: 'A clear learning map and steady progress turn each lesson into a checkpoint on a clear journey — instead of a wall of grades.',
        bullets: [
            'Personal progress tracking',
            'A personal learning map',
            'Achievement milestones',
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

const seoJsonLd = computed(() => [
    {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'LSI Learning Engine',
        alternateName: 'Learning Systems Intelligence',
        description:
            'A school-ready learning platform for exams, assignments, grades, and AI feedback.',
        url:
            typeof window !== 'undefined' ? window.location.origin : undefined,
    },
    {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqs.map((f) => ({
            '@type': 'Question',
            name: f.q,
            acceptedAnswer: { '@type': 'Answer', text: f.a },
        })),
    },
]);

onMounted(() => {
    lenisCleanup = syncLenisWithGsap(ScrollTrigger);

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

        // ─── About credit fade in ───
        const creditEl = root.value?.querySelector('.about-credit');
        if (creditEl) {
            gsap.from(creditEl, {
                y: 20,
                opacity: 0,
                filter: 'blur(4px)',
                duration: 0.8,
                ease: 'expo.out',
                scrollTrigger: {
                    trigger: creditEl,
                    start: 'top 90%',
                    toggleActions: 'play none none none',
                },
            });
        }

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
    lenisCleanup?.();
    lenisCleanup = null;
});

const scrollTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });
</script>

<template>
    <Head title="About" />
    <SeoHead
        :description="'LSI is a school-ready assessment and learning platform. We turn every exam, assignment, and quiz into a feedback loop students actually want to engage with.'"
        type="article"
        :jsonld="seoJsonLd"
    />

    <div
        ref="root"
        class="about-root relative min-h-screen w-full bg-background font-sans text-foreground selection:bg-primary/20"
    >
        <WelcomeHeader
            :can-register="canRegister"
            :auth="$page.props.auth"
            :dashboard="() => '/dashboard'"
            :login="() => '/login'"
            :register="() => '/register'"
            :is-booted="true"
            hide-scroll-nav
        />

        <main
            class="relative z-10 mx-auto max-w-[1500px] px-6 pt-12 pb-24 lg:px-16 lg:pt-20"
        >
            <!-- Hero -->
            <section
                class="about-hero grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-16"
            >
                <div class="flex flex-col gap-6 lg:col-span-8">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >About LSI</span
                        >
                    </div>
                    <h1
                        class="text-4xl leading-[0.95] font-bold tracking-tighter sm:text-5xl lg:text-7xl"
                    >
                        Learning,<br />
                        <span class="text-primary">re-imagined</span><br />
                        from the ground up.
                    </h1>
                    <p
                        class="max-w-xl text-base leading-relaxed text-muted-foreground lg:text-lg"
                    >
                        LSI is an academic platform built for institutions that
                        take learning seriously. We remove friction between
                        teachers, students, and the moments that actually
                        matter.
                    </p>
                </div>
                <div class="flex justify-end lg:col-span-4">
                    <div class="grid w-full grid-cols-3 gap-4 lg:max-w-md">
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
                            class="flex flex-col gap-2 rounded-lg border border-border/20 bg-card/30 p-4 lg:p-5"
                        >
                            <span
                                class="text-xs font-medium text-muted-foreground/70"
                                >{{ stat.label }}</span
                            >
                            <span
                                class="text-2xl font-bold tracking-tight tabular-nums lg:text-3xl"
                            >
                                {{ stat.value.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Mission / Vision -->
            <section
                class="reveal-block mt-24 grid grid-cols-1 gap-12 lg:mt-40 lg:grid-cols-2 lg:gap-20"
            >
                <article
                    class="flex flex-col gap-4 border-l-2 border-primary pl-6 lg:pl-8"
                >
                    <div class="flex items-center gap-3">
                        <Compass class="h-4 w-4 text-primary" />
                        <span class="text-sm font-semibold text-primary"
                            >Mission</span
                        >
                    </div>
                    <h2
                        class="text-2xl leading-tight font-bold tracking-tight lg:text-4xl"
                    >
                        Make assessment a tool for<br />
                        <span class="text-primary">growth</span>, not
                        surveillance.
                    </h2>
                    <p
                        class="max-w-lg text-sm leading-relaxed text-muted-foreground/80 lg:text-base"
                    >
                        We believe great assessment is generous: it teaches as
                        it measures. Our platform turns every exam, assignment,
                        and quiz into a feedback loop students actually want to
                        engage with.
                    </p>
                </article>

                <article
                    class="flex flex-col gap-4 border-l-2 border-border/20 pl-6 lg:pl-8"
                >
                    <div class="flex items-center gap-3">
                        <Layers class="h-4 w-4 text-muted-foreground/40" />
                        <span
                            class="text-sm font-semibold text-muted-foreground/50"
                            >Vision</span
                        >
                    </div>
                    <h2
                        class="text-2xl leading-tight font-bold tracking-tight lg:text-4xl"
                    >
                        A learning platform that feels less like software and
                        more like a <span class="text-primary">place</span>.
                    </h2>
                    <p
                        class="max-w-lg text-sm leading-relaxed text-muted-foreground/80 lg:text-base"
                    >
                        Classrooms, dashboards, and learning maps that fade into
                        the background — leaving the human relationships at the
                        center of every cohort.
                    </p>
                </article>
            </section>

            <!-- Principles -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block mb-8 flex flex-col gap-2 lg:mb-10">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >Principles</span
                        >
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        What we believe
                    </h2>
                </div>
                <div
                    class="grid grid-cols-1 gap-px bg-border/10 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="p in principles"
                        :key="p.title"
                        class="reveal-block flex flex-col gap-4 bg-background p-6 transition-colors hover:bg-muted/[0.03] lg:p-8"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg border border-primary/20 bg-primary/5"
                        >
                            <component
                                :is="p.icon"
                                class="h-5 w-5 text-primary"
                            />
                        </div>
                        <h3
                            class="text-base font-bold tracking-tight lg:text-lg"
                        >
                            {{ p.title }}
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ p.body }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Interactive: Role Picker -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block mb-8 flex flex-col gap-2 lg:mb-10">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >For Everyone</span
                        >
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        What LSI does for <span class="text-primary">you</span>
                    </h2>
                    <p class="max-w-xl text-muted-foreground">
                        Pick the role that fits — see what changes on day one.
                    </p>
                </div>
                <div
                    class="reveal-block overflow-hidden rounded-xl border border-border/20 bg-gradient-to-br from-muted/[0.03] to-transparent"
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
                            class="group relative flex items-center justify-center gap-2 px-3 py-4 text-sm font-semibold transition-colors"
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
                                class="absolute inset-x-0 bottom-0 h-0.5 bg-primary transition-transform duration-300"
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
                        class="role-panel grid grid-cols-1 gap-8 p-6 lg:grid-cols-[1.4fr_1fr] lg:gap-12 lg:p-10"
                    >
                        <div class="flex flex-col gap-4">
                            <span class="text-xs font-semibold text-primary">{{
                                currentRole.label
                            }}</span>
                            <h3
                                class="text-2xl leading-tight font-bold tracking-tight lg:text-3xl"
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
                                class="flex items-start gap-3 text-sm"
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
                <div class="reveal-block mb-8 flex flex-col gap-2 lg:mb-10">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >Try It</span
                        >
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        A taste of the platform
                    </h2>
                    <p class="max-w-xl text-muted-foreground">
                        A 10-second demo of what an LSI question feels like —
                        instant feedback, no scoreboard.
                    </p>
                </div>
                <div
                    class="reveal-block grid grid-cols-1 overflow-hidden rounded-xl border border-border/15 bg-border/15 lg:grid-cols-[1fr_320px]"
                >
                    <div class="flex flex-col gap-6 bg-background p-6 lg:p-10">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Question · 01</span
                            >
                            <span class="text-xs font-semibold text-primary"
                                >Pedagogy 101</span
                            >
                        </div>
                        <h3
                            class="text-lg leading-snug font-bold tracking-tight lg:text-xl"
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
                                class="group relative flex items-start gap-4 rounded-lg border p-4 text-left transition-all"
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
                                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded border border-border/40 text-xs font-medium"
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
                                class="text-xs font-semibold text-muted-foreground"
                                >Live feedback</span
                            >
                            <div
                                v-if="!showFeedback"
                                class="text-sm leading-relaxed text-muted-foreground"
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
                                    <span class="text-sm font-semibold"
                                        >Correct</span
                                    >
                                </div>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    Exactly. Formative assessment is about
                                    <span class="font-semibold text-foreground"
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
                                    <span class="text-sm font-semibold"
                                        >Not quite</span
                                    >
                                </div>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    That describes
                                    <span class="font-semibold text-foreground"
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
                            class="inline-flex items-center gap-2 self-start rounded-lg border border-border/30 px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/60 hover:text-foreground"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                            Try again
                        </button>
                    </aside>
                </div>
            </section>

            <!-- FAQ -->
            <section class="mt-24 lg:mt-40">
                <div class="reveal-block mb-8 flex flex-col gap-2 lg:mb-10">
                    <div
                        class="inline-flex items-center gap-2 self-start rounded-full bg-primary/10 px-4 py-1.5"
                    >
                        <span class="text-sm font-medium text-primary"
                            >FAQ</span
                        >
                    </div>
                    <h2 class="text-3xl font-bold tracking-tight lg:text-5xl">
                        Common questions
                    </h2>
                </div>
                <div
                    class="grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-border/15 bg-border/15 lg:grid-cols-2"
                >
                    <details
                        v-for="f in faqs"
                        :key="f.q"
                        class="group cursor-pointer bg-background p-6 lg:p-8"
                    >
                        <summary
                            class="flex list-none items-center justify-between gap-4"
                        >
                            <h3 class="text-sm font-semibold lg:text-base">
                                {{ f.q }}
                            </h3>
                            <span
                                class="text-xl font-semibold text-primary transition-transform group-open:rotate-45"
                                >+</span
                            >
                        </summary>
                        <p
                            class="mt-3 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ f.a }}
                        </p>
                    </details>
                </div>
            </section>

            <!-- CTA -->
            <section
                class="reveal-block relative mt-24 overflow-hidden rounded-2xl border border-primary/30 bg-gradient-to-br from-primary/[0.06] via-transparent to-transparent p-8 lg:mt-40 lg:p-16"
            >
                <div
                    class="relative grid grid-cols-1 items-center gap-8 lg:grid-cols-[1.4fr_1fr]"
                >
                    <div class="flex flex-col gap-4">
                        <span class="text-xs font-semibold text-primary"
                            >Get in touch</span
                        >
                        <h2
                            class="text-3xl leading-tight font-bold tracking-tight lg:text-5xl"
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
                    <div class="flex flex-col gap-4">
                        <a
                            href="mailto:hello@koamishin.dev"
                            class="group inline-flex items-center justify-between gap-3 rounded-lg bg-foreground px-5 py-4 text-sm font-semibold text-background transition-colors hover:bg-primary"
                        >
                            <span class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                hello@koamishin.dev
                            </span>
                            <ArrowRight
                                class="h-4 w-4 transition-transform group-hover:translate-x-1"
                            />
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
                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-border/30 transition-colors hover:border-primary/60 hover:bg-primary/5"
                            >
                                <component
                                    :is="s.icon"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </a>
                            <button
                                @click="scrollTop"
                                class="ml-auto inline-flex items-center gap-2 rounded-lg border border-border/30 px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:border-primary/60 hover:text-foreground"
                            >
                                <span>Top</span>
                                <ArrowUp class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Developed by credit -->
        <div class="about-credit flex justify-center pb-8">
            <span
                class="inline-flex items-center gap-2 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground/60 uppercase"
            >
                <span class="h-px w-6 bg-border/40"></span>
                Developed by
                <span
                    class="font-black tracking-[0.3em] text-muted-foreground/80"
                    >KOAMISHIN</span
                >
            </span>
        </div>

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

<style>
/* Force Inter on the about page regardless of dashboard font presets.
   Uses higher specificity than :root[data-font-preset] .font-sans (0-3-1 vs 0-3-0).
   The * selector ensures child elements with font-sans are also overridden. */
html[data-font-preset] .about-root.font-sans,
html[data-font-preset] .about-root.font-sans * {
    font-family: Inter, ui-sans-serif, system-ui, sans-serif !important;
}
</style>
