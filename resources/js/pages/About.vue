<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Command, ArrowLeft, Target, Compass, Sparkles, ShieldCheck,
    Users, Layers, BookOpenCheck, Mail, Github, Twitter, Linkedin, ArrowUp,
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
    { q: 'Can I integrate with my existing LMS?', a: 'Yes. LSI exposes a clean API and supports standard SSO. Custom integrations are possible for enterprise deployments.' },
    { q: 'How is student data protected?', a: 'All submissions are encrypted at rest and in transit. We do not sell data, and institutions retain full ownership of their content.' },
];

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

    <div ref="root" class="relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20">
        <!-- Background grid -->
        <div class="pointer-events-none fixed inset-0 z-0 opacity-[0.03] dark:opacity-[0.06]"
             style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>

        <!-- Top primary stripe -->
        <div class="fixed inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/40 to-transparent z-40"></div>

        <!-- Header -->
        <header class="sticky top-0 z-50 flex w-full items-center justify-between px-6 py-5 lg:px-16 lg:py-6 border-b border-border/10 backdrop-blur-2xl bg-background/60">
            <Link href="/" class="flex items-center gap-3 group">
                <div class="relative flex h-10 w-10 items-center justify-center transition-transform duration-700 group-hover:rotate-180">
                    <div class="absolute inset-0 rounded-xl bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <Command class="h-6 w-6 lg:h-7 lg:w-7 relative z-10" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] lg:text-xs font-black tracking-[0.4em] uppercase">LSI Engine</span>
                    <span class="text-[7px] lg:text-[8px] font-bold text-primary/60 uppercase mt-1 tracking-widest">/ about</span>
                </div>
            </Link>

            <div class="flex items-center gap-4 lg:gap-6">
                <button @click="toggleTheme" class="p-2.5 text-muted-foreground hover:text-foreground transition-colors rounded-xl hover:bg-muted/40" aria-label="Toggle theme">
                    <Sun v-if="appearance === 'dark'" class="h-4 w-4 lg:h-5 lg:w-5" />
                    <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
                </button>
                <Link href="/" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.25em] text-muted-foreground hover:text-foreground transition-colors">
                    <ArrowLeft class="h-3 w-3" />
                    Back to home
                </Link>
            </div>
        </header>

        <main class="relative z-10 mx-auto max-w-[1500px] px-6 lg:px-16 pt-16 lg:pt-28 pb-24">
            <!-- Hero -->
            <section class="about-hero grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16 items-end">
                <div class="lg:col-span-8 flex flex-col gap-6">
                    <div class="flex items-center gap-3">
                        <span class="h-px w-10 bg-primary"></span>
                        <span class="text-[10px] font-black uppercase tracking-[0.4em] text-primary">/ about_lsi</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black uppercase leading-[0.95] tracking-tight">
                        Learning,<br/>
                        <span class="text-primary">re-engineered</span><br/>
                        from first principles.
                    </h1>
                    <p class="max-w-2xl text-sm lg:text-base text-muted-foreground leading-relaxed">
                        LSI is an academic operating system built for institutions that take learning seriously. We exist to remove friction between teachers, students, and the moments that actually matter — the ones where understanding clicks into place.
                    </p>
                </div>
                <div class="lg:col-span-4 grid grid-cols-3 gap-4">
                    <div v-for="(stat, i) in [
                        { label: 'Learners',    value: totalUsers ?? 0 },
                        { label: 'Exams',       value: totalExams ?? 0 },
                        { label: 'Submissions', value: totalSubmissions ?? 0 },
                    ]" :key="i" class="border border-border/20 bg-muted/[0.03] p-4 lg:p-5 flex flex-col gap-1">
                        <span class="text-[8px] font-black uppercase tracking-[0.3em] text-muted-foreground">{{ stat.label }}</span>
                        <span class="text-2xl lg:text-3xl font-black tabular-nums">{{ stat.value.toLocaleString() }}</span>
                    </div>
                </div>
            </section>

            <!-- Mission / Vision -->
            <section class="reveal-block mt-24 lg:mt-40 grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
                <article class="border-l-2 border-primary pl-6 lg:pl-10 flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <Compass class="h-4 w-4 text-primary" />
                        <span class="text-[10px] font-black uppercase tracking-[0.3em] text-primary">Mission</span>
                    </div>
                    <h2 class="text-2xl lg:text-4xl font-black uppercase tracking-tight leading-tight">
                        Make assessment a tool for <span class="text-primary">growth</span>, not surveillance.
                    </h2>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        We believe great assessment is generous: it teaches as it measures. Our engine turns every exam, assignment, and quiz into a feedback loop students actually want to engage with.
                    </p>
                </article>
                <article class="border-l-2 border-border/30 pl-6 lg:pl-10 flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <BookOpenCheck class="h-4 w-4 text-foreground" />
                        <span class="text-[10px] font-black uppercase tracking-[0.3em] text-foreground/80">Vision</span>
                    </div>
                    <h2 class="text-2xl lg:text-4xl font-black uppercase tracking-tight leading-tight">
                        A learning platform that feels less like software and more like a <span class="underline decoration-primary decoration-2 underline-offset-4">place</span>.
                    </h2>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Classrooms, dashboards, and learning maps that fade into the background — leaving the human relationships at the center of every cohort.
                    </p>
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
                    <div class="absolute left-3 lg:left-1/2 top-0 bottom-0 w-px bg-border/30 -translate-x-1/2"></div>
                    <div class="flex flex-col gap-10 lg:gap-16">
                        <div
                            v-for="(m, i) in milestones"
                            :key="m.year"
                            class="timeline-row relative grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-16"
                            :class="i % 2 === 0 ? '' : 'lg:[&>*:first-child]:order-2'"
                        >
                            <div class="flex flex-col gap-2 pl-10 lg:pl-0" :class="i % 2 === 0 ? 'lg:text-right lg:pr-10' : 'lg:pl-10'">
                                <span class="text-5xl lg:text-7xl font-black tabular-nums leading-none text-primary/90">{{ m.year }}</span>
                            </div>
                            <div class="relative pl-10 lg:pl-0" :class="i % 2 === 0 ? 'lg:pl-10' : 'lg:text-right lg:pr-10'">
                                <span class="absolute left-3 lg:left-auto top-2 -translate-x-1/2 lg:translate-x-0 lg:-left-2 h-3 w-3 rounded-full bg-primary ring-4 ring-background"
                                      :class="i % 2 === 0 ? 'lg:-left-2' : 'lg:-right-2 lg:left-auto'"></span>
                                <h3 class="text-xl lg:text-2xl font-black uppercase tracking-tight mb-2">{{ m.title }}</h3>
                                <p class="text-xs lg:text-sm text-muted-foreground leading-relaxed max-w-md" :class="i % 2 !== 0 ? 'lg:ml-auto' : ''">{{ m.body }}</p>
                            </div>
                        </div>
                    </div>
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
</style>
