<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { ArrowRight, LayoutDashboard, Play } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';

gsap.registerPlugin(ScrollTrigger, SplitText);

const props = defineProps<{
    canRegister: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
    isBooted?: boolean;
    isCoarsePointer?: boolean;
    prefersReducedMotion?: boolean;
    branding?: {
        name?: string;
        tagline?: string;
        logoUrl?: string | null;
        accentColor?: string;
    };
}>();

const emit = defineEmits(['magnetic', 'resetMagnetic', 'watchDemo']);

const heroSubtitle =
    'A school-ready learning platform for exams, assignments, grades, and AI feedback — with a clear path for every learner.';
let gsapCtx: gsap.Context | null = null;

const heroRef = ref<HTMLElement | null>(null);

const handleMagnetic = (e: MouseEvent) => emit('magnetic', e);
const resetMagnetic = (e: MouseEvent) => emit('resetMagnetic', e);
const watchDemo = () => emit('watchDemo');

const initAnimations = () => {
    if (!props.isBooted || !heroRef.value) return;

    gsapCtx = gsap.context(() => {
        if (props.prefersReducedMotion) {
            // On low-end, just set final visuals — no animation
            const headingLines = heroRef.value?.querySelectorAll(
                '.hero-heading-line-last .hero-char',
            );
            if (headingLines?.length) {
                gsap.set(headingLines, {
                    backgroundImage:
                        'linear-gradient(to right, var(--color-foreground), color-mix(in srgb, var(--color-foreground) 30%, transparent), color-mix(in srgb, var(--color-foreground) 10%, transparent))',
                    backgroundClip: 'text',
                    WebkitBackgroundClip: 'text',
                    color: 'transparent',
                    WebkitTextFillColor: 'transparent',
                });
            }

            const allChars = heroRef.value?.querySelectorAll('.hero-char');
            if (allChars?.length) {
                gsap.set(allChars, { y: 0, opacity: 1, rotateX: 0 });
            }

            const ctaBtns = heroRef.value?.querySelectorAll('.hero-cta');
            if (ctaBtns?.length) {
                gsap.set(ctaBtns, { y: 0, opacity: 1 });
            }

            const creditEl = heroRef.value?.querySelector('.hero-credit');
            if (creditEl) {
                gsap.set(creditEl, { y: 0, opacity: 1 });
            }

            // Low-end / reduced-motion: skip scrubbed parallax entirely.
            // Scrub re-renders transforms on every scroll frame, which is a
            // primary source of frame drops on coarse-pointer / low-memory
            // hardware. Static positioning keeps the page smooth.
            return;
        }

        // ─── SplitText: Hero Heading ───
        const headingLines =
            heroRef.value?.querySelectorAll('.hero-heading-line');
        let allChars: Element[] = [];
        const splitInstances: SplitText[] = [];

        if (headingLines?.length) {
            headingLines.forEach((line) => {
                const split = SplitText.create(line as HTMLElement, {
                    type: 'chars',
                    charsClass: 'hero-char',
                });
                splitInstances.push(split);
                if (split.chars) {
                    allChars = allChars.concat(Array.from(split.chars));
                }
            });

            // Apply gradient styling to INTELLIGENCE chars BEFORE animation so they look correct while animating in
            const lastLineChars = heroRef.value?.querySelectorAll(
                '.hero-heading-line-last .hero-char',
            );
            if (lastLineChars?.length) {
                gsap.set(lastLineChars, {
                    backgroundImage:
                        'linear-gradient(to right, var(--color-foreground), color-mix(in srgb, var(--color-foreground) 30%, transparent), color-mix(in srgb, var(--color-foreground) 10%, transparent))',
                    backgroundClip: 'text',
                    WebkitBackgroundClip: 'text',
                    color: 'transparent',
                    WebkitTextFillColor: 'transparent',
                });
            }

            // Animate all chars with a continuous stagger
            if (allChars.length) {
                gsap.fromTo(
                    allChars,
                    { y: 120, opacity: 0, rotateX: -90 },
                    {
                        y: 0,
                        opacity: 1,
                        rotateX: 0,
                        duration: 1.2,
                        stagger: { each: 0.04, from: 'start' },
                        ease: 'expo.out',
                        delay: 0.15,
                    },
                );
            }
        }

        // ─── Subtitle blur reveal (handled by Motion component in template) ───

        // ─── CTA Buttons ───
        const ctaBtns = heroRef.value?.querySelectorAll('.hero-cta');
        if (ctaBtns?.length) {
            gsap.fromTo(
                ctaBtns,
                { y: 40, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 1,
                    stagger: 0.12,
                    ease: 'expo.out',
                    delay: 1.0,
                },
            );
        }

        // ─── Developed By credit ───
        const creditEl = heroRef.value?.querySelector('.hero-credit');
        if (creditEl) {
            gsap.fromTo(
                creditEl,
                { y: 15, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.8,
                    ease: 'power2.out',
                    delay: 1.6,
                },
            );
        }

        // ─── Scroll Parallax ───
        gsap.to('.hero-parallax', {
            y: (_, target) => {
                const speed = parseFloat(
                    (target as HTMLElement).dataset.speed || '0.2',
                );
                return -window.innerHeight * speed;
            },
            ease: 'none',
            scrollTrigger: {
                trigger: heroRef.value,
                start: 'top top',
                end: 'bottom top',
                scrub: true,
            },
        });

        // ─── Hero Scroll Fade ───
        gsap.to(heroRef.value, {
            opacity: 0,
            y: -80,
            ease: 'none',
            scrollTrigger: {
                trigger: heroRef.value,
                start: 'top 15%',
                end: 'bottom top',
                scrub: 1.2,
            },
        });
    }, heroRef.value);
};

onMounted(() => {
    if (props.isBooted) {
        initAnimations();
    }
});

watch(
    () => props.isBooted,
    (newVal) => {
        if (newVal) {
            initAnimations();
        }
    },
);

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <div ref="heroRef" class="relative max-w-6xl">
        <div class="hero-parallax absolute inset-0 -z-10" data-speed="0.1">
            <slot name="background"></slot>
        </div>

        <div class="preserve-3d relative z-10 mb-2 lg:mb-4">
            <h1
                class="flex flex-col text-5xl leading-[0.9] font-black tracking-[-0.04em] uppercase sm:text-7xl sm:leading-[0.8] lg:text-[8rem]"
            >
                <span class="hero-heading-line flex overflow-hidden"
                    >LEARNING</span
                >
                <span class="hero-heading-line flex overflow-hidden"
                    >SYSTEMS</span
                >
                <span
                    class="hero-heading-line hero-heading-line-last flex overflow-hidden italic"
                >
                    INTELLIGENCE
                </span>
            </h1>
        </div>

        <div
            class="hero-parallax relative mb-10 lg:mb-16 lg:pl-2"
            data-speed="0.05"
        >
            <p
                class="pointer-events-none invisible max-w-3xl text-sm leading-relaxed font-medium tracking-tight whitespace-pre-wrap opacity-0 select-none sm:text-xl lg:text-2xl"
            >
                {{ heroSubtitle }}
            </p>

            <Motion
                :initial="
                    prefersReducedMotion
                        ? { opacity: 1, y: 0 }
                        : { opacity: 0, y: 20 }
                "
                :animate="{ opacity: 1, y: 0 }"
                :transition="
                    prefersReducedMotion
                        ? { duration: 0 }
                        : { duration: 1.5, easing: 'ease-out', delay: 0.2 }
                "
                class="absolute inset-0 max-w-3xl text-sm leading-relaxed font-medium tracking-tight text-muted-foreground sm:text-xl lg:text-2xl"
            >
                {{ heroSubtitle }}
            </Motion>
        </div>

        <div class="hero-parallax -m-2 overflow-hidden p-2" data-speed="0.02">
            <Motion
                :initial="{ y: 40, opacity: 0 }"
                :animate="
                    isBooted ? { y: 0, opacity: 1 } : { y: 40, opacity: 0 }
                "
                :transition="{
                    duration: 1,
                    easing: [0.16, 1, 0.3, 1],
                    delay: 0.4,
                }"
                class="hero-cta flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4 lg:gap-5"
            >
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    @mousemove="handleMagnetic"
                    @mouseleave="resetMagnetic"
                    class="group relative inline-flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-full bg-primary px-8 py-4 text-sm font-bold tracking-wide text-primary-foreground shadow-[0_10px_40px_-12px] shadow-primary/40 transition-all duration-300 hover:shadow-[0_16px_50px_-12px] hover:shadow-primary/50 active:scale-[0.98] sm:w-auto sm:px-10 sm:text-base"
                >
                    <span class="relative z-10 flex items-center gap-2.5">
                        System Dashboard
                        <LayoutDashboard
                            class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 sm:h-5 sm:w-5"
                        />
                    </span>
                    <span
                        class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"
                    ></span>
                </Link>

                <template v-else>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative inline-flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-full bg-primary px-8 py-4 text-sm font-bold tracking-wide text-primary-foreground shadow-[0_10px_40px_-12px] shadow-primary/40 transition-all duration-300 hover:shadow-[0_16px_50px_-12px] hover:shadow-primary/50 hover:brightness-105 active:scale-[0.98] sm:w-auto sm:px-10 sm:text-base"
                    >
                        <span class="relative z-10 flex items-center gap-2.5">
                            Start for free
                            <ArrowRight
                                class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 sm:h-5 sm:w-5"
                            />
                        </span>
                        <span
                            class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"
                        ></span>
                    </Link>

                    <button
                        type="button"
                        @click="watchDemo"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative inline-flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-full bg-foreground px-8 py-4 text-sm font-bold tracking-wide text-background transition-all duration-300 hover:bg-primary hover:text-primary-foreground active:scale-[0.98] sm:w-auto sm:px-10 sm:text-base"
                    >
                        <span class="relative z-10 flex items-center gap-2.5">
                            <Play
                                class="h-4 w-4 fill-current transition-transform duration-300 group-hover:scale-110 sm:h-5 sm:w-5"
                            />
                            Watch Demo
                        </span>
                    </button>

                    <Link
                        :href="login()"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative inline-flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-full border border-border bg-background/50 px-8 py-4 text-sm font-semibold tracking-wide text-foreground backdrop-blur-sm transition-all duration-300 hover:border-primary/40 hover:bg-primary/5 active:scale-[0.98] sm:w-auto sm:px-10 sm:text-base"
                    >
                        <span class="relative z-10 flex items-center gap-2.5">
                            Login
                            <ArrowRight
                                class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1 sm:h-5 sm:w-5"
                            />
                        </span>
                    </Link>
                </template>
            </Motion>
        </div>

        <!-- Developed by credit -->
        <div class="hero-credit mt-10 flex justify-end lg:mt-14">
            <span
                class="inline-flex items-center gap-2 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground/60 uppercase lg:text-[10px]"
            >
                <span class="h-px w-6 bg-border/40"></span>
                Developed by
                <span
                    class="font-black tracking-[0.3em] text-muted-foreground/80"
                    >KOAMISHIN</span
                >
            </span>
        </div>
    </div>
</template>
