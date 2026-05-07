<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import gsap from 'gsap';
import { ArrowUp, Github, Twitter, Linkedin, Mail, Activity, Globe, Cpu } from 'lucide-vue-next';

const footerRef = ref<HTMLElement | null>(null);
const time = ref('--:--:--');
let timer: number | null = null;

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
    { icon: Globe,    label: 'Region', value: 'GLOBAL · CAMPUS 01' },
    { icon: Cpu,      label: 'Engine', value: 'LUAV v6.4 — STABLE' },
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
    timer = window.setInterval(updateTime, 1000);

    if (footerRef.value) {
        gsap.from(footerRef.value.querySelectorAll('.footer-stagger'), {
            scrollTrigger: { trigger: footerRef.value, start: 'top 85%' },
            y: 30,
            opacity: 0,
            filter: 'blur(8px)',
            stagger: 0.08,
            duration: 0.9,
            ease: 'expo.out',
        });

        gsap.fromTo(footerRef.value.querySelector('.footer-wordmark'),
            { backgroundPositionX: '100%' },
            {
                backgroundPositionX: '0%',
                duration: 1.4,
                ease: 'expo.out',
                scrollTrigger: { trigger: footerRef.value, start: 'top 80%' },
            }
        );
    }
});

onBeforeUnmount(() => {
    if (timer) window.clearInterval(timer);
});
</script>

<template>
    <footer
        ref="footerRef"
        class="relative z-10 mt-24 border-t border-border/10 bg-gradient-to-b from-background via-background to-muted/[0.04] backdrop-blur-sm overflow-hidden"
    >
        <!-- Decorative grid backdrop -->
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.03] dark:opacity-[0.06]"
            style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 56px 56px;"
        ></div>
        <!-- Top primary stripe -->
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/60 to-transparent"></div>

        <!-- Status bar -->
        <div class="relative border-b border-border/10">
            <div class="mx-auto max-w-[1500px] flex flex-col md:flex-row items-stretch divide-y md:divide-y-0 md:divide-x divide-border/10">
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="footer-stagger flex-1 flex items-center gap-3 px-6 lg:px-10 py-5"
                >
                    <component :is="stat.icon" class="h-4 w-4 text-primary" />
                    <div class="flex flex-col leading-tight">
                        <span class="text-[8px] font-black uppercase tracking-[0.4em] text-muted-foreground/60">{{ stat.label }}</span>
                        <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.18em] text-foreground/90">{{ stat.value }}</span>
                    </div>
                </div>
                <div class="footer-stagger flex items-center gap-3 px-6 lg:px-10 py-5 md:border-l border-border/10">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-[0.3em] text-foreground/80 tabular-nums">{{ time }}</span>
                </div>
            </div>
        </div>

        <!-- Main grid -->
        <div class="relative mx-auto max-w-[1500px] px-6 lg:px-16 py-16 lg:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
                <!-- Brand block -->
                <div class="lg:col-span-4 flex flex-col gap-8 footer-stagger">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <div class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></div>
                            <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.5em] text-foreground">LSI Academic Engine</span>
                        </div>
                        <h3 class="text-3xl lg:text-5xl font-black uppercase leading-[0.95] tracking-tight">
                            Built for<br/>
                            <span class="text-primary">assessment-driven</span><br/>
                            learning.
                        </h3>
                        <p class="max-w-sm text-xs lg:text-sm text-muted-foreground leading-relaxed">
                            A modern operating system for institutions. Compose exams, track mastery, and orchestrate learning journeys at any scale.
                        </p>
                    </div>

                    <!-- Newsletter -->
                    <form @submit.prevent class="flex flex-col gap-2">
                        <label for="footer-newsletter" class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground">/ stay_synced</label>
                        <div class="flex items-stretch border border-border/30 focus-within:border-primary/60 transition-colors bg-background/40">
                            <input
                                id="footer-newsletter"
                                type="email"
                                placeholder="you@institution.edu"
                                class="flex-1 bg-transparent px-3 py-2.5 text-xs text-foreground placeholder:text-muted-foreground/50 focus:outline-none"
                            />
                            <button type="submit" class="px-4 bg-primary text-primary-foreground text-[10px] font-black uppercase tracking-[0.25em] hover:bg-primary/90 transition-colors">
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
                            class="group flex h-9 w-9 items-center justify-center border border-border/20 hover:border-primary/60 hover:bg-primary/5 transition-colors"
                        >
                            <component :is="s.icon" class="h-3.5 w-3.5 text-muted-foreground group-hover:text-primary transition-colors" />
                        </a>
                    </div>
                </div>

                <!-- Sitemap -->
                <div class="lg:col-span-7 lg:col-start-6 grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-12">
                    <div
                        v-for="section in sections"
                        :key="section.title"
                        class="flex flex-col gap-5 footer-stagger"
                    >
                        <h4 class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.35em] text-foreground/90 flex items-center gap-2">
                            <span class="h-px w-4 bg-primary/60"></span>
                            {{ section.title }}
                        </h4>
                        <ul class="flex flex-col gap-3">
                            <li v-for="link in section.links" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="group inline-flex items-center gap-2 text-[10px] lg:text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground/70 hover:text-foreground transition-colors"
                                >
                                    <span class="h-px w-0 group-hover:w-3 bg-primary transition-all duration-300"></span>
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Wordmark -->
            <div class="mt-16 lg:mt-24 footer-stagger overflow-hidden">
                <div
                    class="footer-wordmark text-[18vw] sm:text-[14vw] lg:text-[11rem] font-black uppercase leading-none tracking-tighter select-none whitespace-nowrap"
                    style="background-image: linear-gradient(90deg, var(--color-foreground) 0%, color-mix(in oklab, var(--color-foreground) 5%, transparent) 100%); background-size: 200% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;"
                >
                    LSI · v6
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="mt-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 pt-8 border-t border-border/10 footer-stagger">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground/60">
                        © {{ year }} Koamishin · All rights reserved
                    </p>
                    <span class="text-[9px] font-bold uppercase tracking-[0.25em] text-muted-foreground/30">Build · Stable · {{ year }}.{{ String(new Date().getMonth() + 1).padStart(2, '0') }}</span>
                </div>
                <button
                    type="button"
                    @click="scrollTop"
                    class="group inline-flex items-center gap-2 border border-border/30 hover:border-primary/60 px-3 py-2 text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground hover:text-foreground transition-colors"
                >
                    <span>Return to top</span>
                    <ArrowUp class="h-3 w-3 group-hover:-translate-y-0.5 transition-transform" />
                </button>
            </div>
        </div>
    </footer>
</template>
