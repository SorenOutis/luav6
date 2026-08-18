<script setup lang="ts">
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    ArrowUp,
    Github,
    Twitter,
    Linkedin,
    Mail,
    Activity,
    Globe,
    Cpu,
} from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, onUnmounted, ref, computed } from 'vue';
import { isLowEndDeviceSignal } from '@/lib/device';

gsap.registerPlugin(ScrollTrigger);

const footerRef = ref<HTMLElement | null>(null);
const time = ref('--:--:--');
let timer: number | null = null;
let gsapCtx: gsap.Context | null = null;

const year = new Date().getFullYear();

const sections = [
    {
        title: 'Platform',
        links: [
            { label: 'Dashboard', href: '#engine' },
            { label: 'Architecture', href: '#architecture' },
            { label: 'Metrics', href: '#metrics' },
            { label: 'Features', href: '#features' },
        ],
    },
    {
        title: 'Resources',
        links: [
            { label: 'Documentation', href: '#' },
            { label: 'Changelog', href: '#' },
            { label: 'Roadmap', href: '#' },
            { label: 'API Reference', href: '#' },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'About Koamishin', href: '#' },
            { label: 'Press Kit', href: '#' },
            { label: 'Careers', href: '#' },
            { label: 'Contact', href: '#' },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacy', href: '#' },
            { label: 'Terms of Service', href: '#' },
            { label: 'Data Policy', href: '#' },
            { label: 'License', href: '#' },
        ],
    },
];

const socials = [
    { label: 'GitHub', icon: Github, href: '#' },
    { label: 'Twitter', icon: Twitter, href: '#' },
    { label: 'LinkedIn', icon: Linkedin, href: '#' },
    { label: 'Email', icon: Mail, href: 'mailto:hello@koamishin.dev' },
];

const stats = computed(() => [
    { icon: Activity, label: 'Uptime', value: '99.98%' },
    { icon: Globe, label: 'Region', value: 'GLOBAL · CAMPUS 01' },
    { icon: Cpu, label: 'Engine', value: 'LUAV v6.4 — STABLE' },
]);

const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const updateTime = () => {
    const d = new Date();
    const pad = (n: number) => n.toString().padStart(2, '0');
    time.value = `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())} UTC`;
};

onMounted(() => {
    updateTime();

    // Phones / low-end: one static timestamp, no 1 Hz re-render, no GSAP
    // blur-filter reveals (those force a paint of the whole footer).
    if (isLowEndDeviceSignal()) {
        return;
    }

    timer = window.setInterval(updateTime, 1000);

    const reducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    if (!reducedMotion && footerRef.value) {
        gsapCtx = gsap.context(() => {
            // ─── Dramatic stagger reveal for all footer sections ───
            const staggerEls =
                footerRef.value?.querySelectorAll('.footer-stagger');
            if (staggerEls?.length) {
                gsap.fromTo(
                    staggerEls,
                    { y: 40, opacity: 0, scale: 0.95, filter: 'blur(6px)' },
                    {
                        y: 0,
                        opacity: 1,
                        scale: 1,
                        filter: 'blur(0px)',
                        duration: 1,
                        stagger: 0.1,
                        ease: 'expo.out',
                        scrollTrigger: {
                            trigger: footerRef.value,
                            start: 'top 85%',
                            toggleActions: 'play none none none',
                        },
                    },
                );
            }

            // ─── Wordmark gradient sweep + parallax ───
            const wordmark = footerRef.value?.querySelector('.footer-wordmark');
            if (wordmark) {
                gsap.fromTo(
                    wordmark,
                    {
                        backgroundPositionX: '100%',
                        opacity: 0.6,
                    },
                    {
                        backgroundPositionX: '0%',
                        opacity: 1,
                        duration: 1.4,
                        ease: 'expo.out',
                        scrollTrigger: {
                            trigger: footerRef.value,
                            start: 'top 80%',
                            toggleActions: 'play none none none',
                        },
                    },
                );

                // ─── Wordmark slow parallax on scroll ───
                gsap.to(wordmark, {
                    y: -40,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: footerRef.value,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: 1.5,
                    },
                });
            }

            // ─── Status bar staggered reveal ───
            const statusItems =
                footerRef.value?.querySelectorAll('.status-item');
            if (statusItems?.length) {
                gsap.fromTo(
                    statusItems,
                    { y: 20, opacity: 0 },
                    {
                        y: 0,
                        opacity: 1,
                        duration: 0.7,
                        stagger: 0.08,
                        ease: 'power3.out',
                        scrollTrigger: {
                            trigger: footerRef.value,
                            start: 'top 90%',
                            toggleActions: 'play none none none',
                        },
                    },
                );
            }
        }, footerRef.value);
    }
});

onBeforeUnmount(() => {
    if (timer) window.clearInterval(timer);
});

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <footer
        ref="footerRef"
        class="relative z-10 mt-24 overflow-hidden border-t border-border/10 bg-gradient-to-b from-background via-background to-muted/[0.04] backdrop-blur-sm"
    >
        <!-- Decorative grid backdrop -->
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.03] dark:opacity-[0.06]"
            style="
                background-image:
                    linear-gradient(var(--color-border) 1px, transparent 1px),
                    linear-gradient(
                        90deg,
                        var(--color-border) 1px,
                        transparent 1px
                    );
                background-size: 56px 56px;
            "
        ></div>
        <!-- Top primary stripe -->
        <div
            class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/60 to-transparent"
        ></div>

        <!-- Status bar -->
        <div class="relative border-b border-border/10">
            <div
                class="mx-auto flex max-w-[1500px] flex-col items-stretch divide-y divide-border/10 md:flex-row md:divide-x md:divide-y-0"
            >
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="footer-stagger status-item flex flex-1 items-center gap-3 px-6 py-5 lg:px-10"
                >
                    <component :is="stat.icon" class="h-4 w-4 text-primary" />
                    <div class="flex flex-col leading-tight">
                        <span
                            class="text-[8px] font-black tracking-[0.4em] text-muted-foreground/60 uppercase"
                            >{{ stat.label }}</span
                        >
                        <span
                            class="text-[10px] font-black tracking-[0.18em] text-foreground/90 uppercase lg:text-xs"
                            >{{ stat.value }}</span
                        >
                    </div>
                </div>
                <div
                    class="footer-stagger status-item flex items-center gap-3 border-border/10 px-6 py-5 md:border-l lg:px-10"
                >
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                        ></span>
                    </span>
                    <span
                        class="text-[10px] font-black tracking-[0.3em] text-foreground/80 uppercase tabular-nums"
                        >{{ time }}</span
                    >
                </div>
            </div>
        </div>

        <!-- Main grid -->
        <div
            class="relative mx-auto max-w-[1500px] px-6 py-16 lg:px-16 lg:py-24"
        >
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-16">
                <!-- Brand block -->
                <div class="footer-stagger flex flex-col gap-8 lg:col-span-4">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary"
                            ></div>
                            <span
                                class="text-[9px] font-black tracking-[0.5em] text-foreground uppercase lg:text-[10px]"
                                >LSI Academic Engine</span
                            >
                        </div>
                        <h3
                            class="text-3xl leading-[0.95] font-black tracking-tight uppercase lg:text-5xl"
                        >
                            Built for<br />
                            <span class="text-primary">assessment-driven</span
                            ><br />
                            learning.
                        </h3>
                        <p
                            class="max-w-sm text-xs leading-relaxed text-muted-foreground lg:text-sm"
                        >
                            A modern operating system for institutions. Compose
                            exams, track mastery, and orchestrate learning
                            journeys at any scale.
                        </p>
                    </div>

                    <!-- Newsletter -->
                    <form @submit.prevent class="flex flex-col gap-2">
                        <label
                            for="footer-newsletter"
                            class="text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                            >/ stay_synced</label
                        >
                        <div
                            class="flex items-stretch border border-border/30 bg-background/40 transition-colors focus-within:border-primary/60"
                        >
                            <input
                                id="footer-newsletter"
                                type="email"
                                placeholder="you@institution.edu"
                                class="flex-1 bg-transparent px-3 py-2.5 text-xs text-foreground placeholder:text-muted-foreground/50 focus:outline-none"
                            />
                            <button
                                type="submit"
                                class="bg-primary px-4 text-[10px] font-black tracking-[0.25em] text-primary-foreground uppercase transition-colors hover:bg-primary/90"
                            >
                                Subscribe
                            </button>
                        </div>
                    </form>

                    <!-- Socials -->
                    <div class="flex items-center gap-2">
                        <a
                            v-for="s in socials"
                            :key="s.label"
                            :href="s.href"
                            :aria-label="s.label"
                            class="group flex h-9 w-9 items-center justify-center border border-border/20 transition-colors hover:border-primary/60 hover:bg-primary/5"
                        >
                            <component
                                :is="s.icon"
                                class="h-3.5 w-3.5 text-muted-foreground transition-colors group-hover:text-primary"
                            />
                        </a>
                    </div>
                </div>

                <!-- Sitemap -->
                <div
                    class="grid grid-cols-2 gap-x-8 gap-y-12 sm:grid-cols-4 lg:col-span-7 lg:col-start-6"
                >
                    <div
                        v-for="section in sections"
                        :key="section.title"
                        class="footer-stagger flex flex-col gap-5"
                    >
                        <h4
                            class="flex items-center gap-2 text-[9px] font-black tracking-[0.35em] text-foreground/90 uppercase lg:text-[10px]"
                        >
                            <span class="h-px w-4 bg-primary/60"></span>
                            {{ section.title }}
                        </h4>
                        <ul class="flex flex-col gap-3">
                            <li v-for="link in section.links" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="group inline-flex items-center gap-2 text-[10px] font-bold tracking-[0.18em] text-muted-foreground/70 uppercase transition-colors hover:text-foreground lg:text-[11px]"
                                >
                                    <span
                                        class="h-px w-0 bg-primary transition-all duration-300 group-hover:w-3"
                                    ></span>
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Wordmark -->
            <div class="footer-stagger mt-16 overflow-hidden lg:mt-24">
                <div
                    class="footer-wordmark text-[18vw] leading-none font-black tracking-tighter whitespace-nowrap uppercase select-none sm:text-[14vw] lg:text-[11rem]"
                    style="
                        background-image: linear-gradient(
                            90deg,
                            var(--color-foreground) 0%,
                            color-mix(
                                    in oklab,
                                    var(--color-foreground) 5%,
                                    transparent
                                )
                                100%
                        );
                        background-size: 200% 100%;
                        -webkit-background-clip: text;
                        background-clip: text;
                        color: transparent;
                    "
                >
                    LSI · v6
                </div>
            </div>

            <!-- Bottom bar -->
            <div
                class="footer-stagger mt-12 flex flex-col items-start justify-between gap-6 border-t border-border/10 pt-8 md:flex-row md:items-center"
            >
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <p
                        class="text-[9px] font-black tracking-[0.3em] text-muted-foreground/60 uppercase"
                    >
                        © {{ year }} Koamishin · All rights reserved
                    </p>
                    <span
                        class="text-[9px] font-bold tracking-[0.25em] text-muted-foreground/30 uppercase"
                        >Build · Stable · {{ year }}.{{
                            String(new Date().getMonth() + 1).padStart(2, '0')
                        }}</span
                    >
                </div>
                <button
                    type="button"
                    @click="scrollTop"
                    class="group inline-flex items-center gap-2 border border-border/30 px-3 py-2 text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-colors hover:border-primary/60 hover:text-foreground"
                >
                    <span>Return to top</span>
                    <ArrowUp
                        class="h-3 w-3 transition-transform group-hover:-translate-y-0.5"
                    />
                </button>
            </div>
        </div>
    </footer>
</template>
