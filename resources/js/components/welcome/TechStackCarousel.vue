<script setup lang="ts">
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import {
    Command,
    Zap,
    Target,
    Award,
    LayoutDashboard,
    Database,
    Code2,
    Sparkles,
    Server,
    GitBranch,
    Boxes,
    Cpu,
} from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount, onUnmounted } from 'vue';

gsap.registerPlugin(ScrollTrigger);

const rowTop = ref<HTMLElement | null>(null);
const rowBottom = ref<HTMLElement | null>(null);
const wrapper = ref<HTMLElement | null>(null);
const techStackRef = ref<HTMLElement | null>(null);
let gsapCtx: gsap.Context | null = null;

const techStackTop = [
    {
        name: 'Laravel 12',
        description: 'Robust backend architecture',
        icon: Command,
        accent: '#FF2D20',
    },
    {
        name: 'Vue 3',
        description: 'Reactive UI system',
        icon: Zap,
        accent: '#42B883',
    },
    {
        name: 'Inertia.js',
        description: 'Monolith connection layer',
        icon: Target,
        accent: '#9553E9',
    },
    {
        name: 'TypeScript',
        description: 'Type-safe development',
        icon: Code2,
        accent: '#3178C6',
    },
    {
        name: 'GSAP',
        description: 'Pro-grade animation engine',
        icon: Award,
        accent: '#88CE02',
    },
    {
        name: 'Tailwind',
        description: 'Utility-first design system',
        icon: LayoutDashboard,
        accent: '#38BDF8',
    },
];

const techStackBottom = [
    {
        name: 'PostgreSQL',
        description: 'Battle-tested data layer',
        icon: Database,
        accent: '#336791',
    },
    {
        name: 'Filament',
        description: 'Admin command center',
        icon: Sparkles,
        accent: '#F59E0B',
    },
    {
        name: 'Vite',
        description: 'Lightning build pipeline',
        icon: Boxes,
        accent: '#646CFF',
    },
    {
        name: 'RoadRunner',
        description: 'High-performance app server',
        icon: Server,
        accent: '#EF4444',
    },
    {
        name: 'Pest',
        description: 'Elegant testing framework',
        icon: Cpu,
        accent: '#A855F7',
    },
    {
        name: 'Git',
        description: 'Distributed version control',
        icon: GitBranch,
        accent: '#F05033',
    },
];

let tweens: gsap.core.Tween[] = [];

const props = defineProps<{
    isCoarsePointer: boolean;
    prefersReducedMotion?: boolean;
}>();

onMounted(() => {
    // On low-end, skip all GSAP — no entrance, no marquee, no scroll-triggered FX
    if (props.prefersReducedMotion) return;

    gsapCtx = gsap.context(() => {
        // ─── Scroll-triggered entrance: fade + scale in before marquee starts ───
        gsap.fromTo(techStackRef.value,
            { opacity: 0, y: 40, scale: 0.97 },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                duration: 1.2,
                ease: 'expo.out',
                scrollTrigger: {
                    trigger: techStackRef.value,
                    start: 'top 85%',
                    toggleActions: 'play none none none',
                },
                onComplete: () => {
                    // ─── Start marquee after entrance animation ───
                    const buildMarquee = (
                        el: HTMLElement | null,
                        direction: 1 | -1,
                        duration: number,
                    ) => {
                        if (!el) return null;
                        const start = direction === -1 ? 0 : -50;
                        const end = direction === -1 ? -50 : 0;
                        gsap.set(el, { xPercent: start });
                        return gsap.to(el, {
                            xPercent: end,
                            duration,
                            ease: 'none',
                            repeat: -1,
                        });
                    };

                    const t1 = buildMarquee(rowTop.value, -1, 38);
                    const t2 = buildMarquee(rowBottom.value, 1, 46);
                    tweens = [t1, t2].filter(Boolean) as gsap.core.Tween[];

                    if (wrapper.value) {
                        const onEnter = () => tweens.forEach((t) => t.timeScale(0.25));
                        const onLeave = () => tweens.forEach((t) => t.timeScale(1));
                        wrapper.value.addEventListener('mouseenter', onEnter);
                        wrapper.value.addEventListener('mouseleave', onLeave);
                    }
                },
            },
        );
    }, techStackRef.value);
});

onBeforeUnmount(() => {
    tweens.forEach((t) => t.kill());
    tweens = [];
});

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <div
        ref="techStackRef"
        class="relative -mx-6 mt-24 overflow-hidden border-y border-border/10 bg-gradient-to-b from-background via-muted/[0.02] to-background py-14 sm:mx-0 lg:mt-48 lg:py-20"
    >
        <!-- Subtle grid backdrop -->
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.05] dark:opacity-[0.08]"
            style="
                background-image:
                    linear-gradient(var(--color-border) 1px, transparent 1px),
                    linear-gradient(
                        90deg,
                        var(--color-border) 1px,
                        transparent 1px
                    );
                background-size: 48px 48px;
            "
        ></div>

        <!-- Section header -->
        <div
            class="relative mb-10 flex items-end justify-between gap-4 px-6 sm:px-10 lg:mb-14 lg:px-16"
        >
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-px w-10 bg-primary"></div>
                    <span
                        class="text-[9px] font-black tracking-[0.4em] text-primary uppercase lg:text-[10px]"
                        >/ stack_matrix</span
                    >
                </div>
                <h2
                    class="text-2xl font-black tracking-tight uppercase lg:text-4xl"
                    data-scramble
                >
                    Engineered with Precision
                </h2>
                <p
                    class="max-w-md text-[11px] tracking-wide text-muted-foreground lg:text-xs"
                >
                    A composition of resilient frameworks and lightning tooling
                    — purpose-built for assessment-driven learning at scale.
                </p>
            </div>
            <div class="hidden flex-col items-end gap-2 md:flex">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-primary"
                        ></span>
                    </span>
                    <span
                        class="text-[9px] font-black tracking-[0.3em] text-muted-foreground uppercase"
                        >live · synced</span
                    >
                </div>
                <span
                    class="text-[9px] font-bold tracking-[0.25em] text-muted-foreground/50 uppercase"
                    >{{ techStackTop.length + techStackBottom.length }} modules
                    · v6</span
                >
            </div>
        </div>

        <!-- Marquee viewport with edge fades -->
        <div class="carousel-mask relative space-y-6">
            <!-- Row 1 -->
            <div class="flex flex-nowrap" ref="rowTop">
                <div
                    v-for="n in 2"
                    :key="`top-${n}`"
                    class="flex shrink-0 flex-nowrap"
                >
                    <div
                        v-for="tech in techStackTop"
                        :key="tech.name + n"
                        class="tech-chip group"
                        :style="{ '--accent': tech.accent }"
                    >
                        <div class="tech-chip__icon">
                            <component
                                :is="tech.icon"
                                class="h-5 w-5 lg:h-6 lg:w-6"
                            />
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span
                                class="text-sm font-black tracking-tight uppercase lg:text-base"
                                >{{ tech.name }}</span
                            >
                            <span
                                class="text-[9px] font-bold tracking-[0.18em] text-muted-foreground/70 uppercase"
                                >{{ tech.description }}</span
                            >
                        </div>
                        <div class="tech-chip__dot"></div>
                    </div>
                </div>
            </div>

            <!-- Row 2 (opposing direction) -->
            <div class="flex flex-nowrap" ref="rowBottom">
                <div
                    v-for="n in 2"
                    :key="`bot-${n}`"
                    class="flex shrink-0 flex-nowrap"
                >
                    <div
                        v-for="tech in techStackBottom"
                        :key="tech.name + n"
                        class="tech-chip group tech-chip--ghost"
                        :style="{ '--accent': tech.accent }"
                    >
                        <div class="tech-chip__icon">
                            <component
                                :is="tech.icon"
                                class="h-5 w-5 lg:h-6 lg:w-6"
                            />
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span
                                class="text-sm font-black tracking-tight uppercase lg:text-base"
                                >{{ tech.name }}</span
                            >
                            <span
                                class="text-[9px] font-bold tracking-[0.18em] text-muted-foreground/70 uppercase"
                                >{{ tech.description }}</span
                            >
                        </div>
                        <div class="tech-chip__dot"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.carousel-mask {
    -webkit-mask-image: linear-gradient(
        90deg,
        transparent 0,
        #000 8%,
        #000 92%,
        transparent 100%
    );
    mask-image: linear-gradient(
        90deg,
        transparent 0,
        #000 8%,
        #000 92%,
        transparent 100%
    );
}

.tech-chip {
    --accent: var(--color-primary);
    display: inline-flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 1.25rem;
    margin-right: 1rem;
    border: 1px solid color-mix(in oklab, var(--color-border) 60%, transparent);
    background: color-mix(in oklab, var(--color-muted) 6%, transparent);
    backdrop-filter: blur(6px);
    border-radius: 999px;
    position: relative;
    transition:
        border-color 0.35s ease,
        transform 0.35s ease,
        background 0.35s ease,
        box-shadow 0.35s ease;
    flex-shrink: 0;
}

.tech-chip:hover {
    border-color: color-mix(in oklab, var(--accent) 55%, transparent);
    background: color-mix(in oklab, var(--accent) 8%, transparent);
    transform: translateY(-2px);
    box-shadow: 0 8px 30px -12px
        color-mix(in oklab, var(--accent) 50%, transparent);
}

.tech-chip--ghost {
    border-style: dashed;
    background: transparent;
}

.tech-chip__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 999px;
    color: color-mix(in oklab, var(--accent) 90%, var(--color-foreground));
    background: color-mix(in oklab, var(--accent) 14%, transparent);
    border: 1px solid color-mix(in oklab, var(--accent) 30%, transparent);
    transition:
        transform 0.4s ease,
        background 0.4s ease;
}

.tech-chip:hover .tech-chip__icon {
    transform: rotate(-6deg) scale(1.05);
    background: color-mix(in oklab, var(--accent) 22%, transparent);
}

.tech-chip__dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: color-mix(in oklab, var(--accent) 75%, transparent);
    box-shadow: 0 0 12px color-mix(in oklab, var(--accent) 60%, transparent);
    margin-left: 0.25rem;
}
</style>
