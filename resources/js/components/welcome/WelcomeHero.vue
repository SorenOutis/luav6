<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { ArrowRight, CalendarCheck, LayoutDashboard } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount, onUnmounted, watch } from 'vue';

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

const words = [
    'School-Ready Assessment.',
    'Teacher Clarity.',
    'Student Momentum.',
    'Actionable Reports.',
    'Guided Growth.',
];
const currentWordIndex = ref(0);
const currentCharIndex = ref(words[0].length);
const isTyping = ref(false);
const typedText = ref(words[0]);
let typingTimeout: ReturnType<typeof setTimeout> | null = null;
let gsapCtx: gsap.Context | null = null;

const heroRef = ref<HTMLElement | null>(null);

const type = () => {
    const currentWord = words[currentWordIndex.value];

    if (isTyping.value) {
        typedText.value = currentWord.substring(0, currentCharIndex.value + 1);
        currentCharIndex.value++;

        if (currentCharIndex.value === currentWord.length) {
            isTyping.value = false;
            typingTimeout = setTimeout(type, 2500);
            return;
        }
    } else {
        typedText.value = currentWord.substring(0, currentCharIndex.value - 1);
        currentCharIndex.value--;

        if (currentCharIndex.value === 0) {
            isTyping.value = true;
            currentWordIndex.value =
                (currentWordIndex.value + 1) % words.length;
            typingTimeout = setTimeout(type, 800);
            return;
        }
    }

    let delay = isTyping.value ? 40 + Math.random() * 60 : 30;
    if (isTyping.value && typedText.value.endsWith(' '))
        delay += 60 + Math.random() * 40;

    typingTimeout = setTimeout(type, delay);
};

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

            // Still attach scroll effects (parallax is passive scroll-driven)
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
            return;
        }

        // ─── SplitText: Hero Heading ───
        const headingLines =
            heroRef.value?.querySelectorAll('.hero-heading-line');
        let allChars: Element[] = [];
        const splitInstances: SplitText[] = [];

        if (headingLines?.length) {
            headingLines.forEach((line, idx) => {
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
    if (!props.prefersReducedMotion) {
        typingTimeout = setTimeout(type, 2500);
    } else {
        // On low-end, show the final typed text immediately
        typedText.value = words[0];
    }
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

onBeforeUnmount(() => {
    if (typingTimeout) {
        clearTimeout(typingTimeout);
    }
});

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
                A school-ready learning platform for exams, assignments, grades,
                AI feedback, and student engagement through
                <span
                    class="inline-flex items-center font-black tracking-widest uppercase"
                >
                    Guided Growth.<span
                        class="ml-1 h-[0.8em] w-1 bg-primary"
                    ></span>
                </span>
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
                        : { duration: 1.5, ease: 'ease-out', delay: 0.2 }
                "
                class="absolute inset-0 max-w-3xl text-sm leading-relaxed font-medium tracking-tight text-muted-foreground sm:text-xl lg:text-2xl"
            >
                A school-ready learning platform for exams, assignments, grades,
                AI feedback, and student engagement through
                <span
                    class="inline-flex items-center font-black tracking-widest text-foreground uppercase"
                >
                    {{ typedText
                    }}<span
                        class="ml-1 h-[0.8em] w-1 animate-[pulse_1s_infinite] bg-primary shadow-[0_0_8px_var(--color-primary)]"
                    ></span>
                </span>
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
                    ease: [0.16, 1, 0.3, 1],
                    delay: 0.4,
                }"
                class="hero-cta flex flex-col gap-3 sm:flex-row sm:gap-4 lg:gap-5"
            >
                <Link
                    v-if="auth.user"
                    :href="dashboard()"
                    @mousemove="handleMagnetic"
                    @mouseleave="resetMagnetic"
                    class="group relative flex -skew-x-[12deg] items-center justify-center bg-primary px-8 py-4 text-primary-foreground shadow-[0_8px_40px_-12px] shadow-primary/30 transition-all hover:bg-primary/90 active:scale-[0.98] sm:px-10"
                >
                    <span
                        class="relative z-10 flex skew-x-[12deg] items-center gap-2.5 text-sm font-bold tracking-[0.22em] uppercase sm:text-base"
                    >
                        System Dashboard
                        <LayoutDashboard
                            class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1 sm:h-5 sm:w-5"
                        />
                    </span>
                    <div
                        class="absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"
                    ></div>
                </Link>

                <template v-else>
                    <button
                        type="button"
                        @click="watchDemo"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative flex -skew-x-[12deg] items-center justify-center bg-foreground px-8 py-4 text-background transition-all hover:bg-primary hover:text-primary-foreground active:scale-[0.98] sm:px-10"
                    >
                        <span
                            class="relative z-10 flex skew-x-[12deg] items-center gap-2.5 text-sm font-bold tracking-[0.22em] uppercase sm:text-base"
                        >
                            Watch Demo
                            <CalendarCheck
                                class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1 sm:h-5 sm:w-5"
                            />
                        </span>
                    </button>

                    <Link
                        :href="login()"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative flex -skew-x-[12deg] items-center justify-center border border-border bg-background/50 px-8 py-4 text-foreground backdrop-blur-sm transition-all hover:bg-muted/50 active:scale-[0.98] sm:px-10"
                    >
                        <span
                            class="relative z-10 flex skew-x-[12deg] items-center gap-2.5 text-sm font-bold tracking-[0.22em] uppercase sm:text-base"
                        >
                            Login
                            <ArrowRight
                                class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1 sm:h-5 sm:w-5"
                            />
                        </span>
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="register()"
                        @mousemove="handleMagnetic"
                        @mouseleave="resetMagnetic"
                        class="group relative flex -skew-x-[12deg] items-center justify-center border border-primary/30 bg-primary/5 px-8 py-4 text-foreground backdrop-blur-sm transition-all hover:bg-primary/10 active:scale-[0.98] sm:hidden"
                    >
                        <span
                            class="relative z-10 flex skew-x-[12deg] items-center gap-2.5 text-sm font-bold tracking-[0.22em] uppercase"
                        >
                            Join
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
