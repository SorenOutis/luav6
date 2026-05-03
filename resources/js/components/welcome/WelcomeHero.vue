<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, LayoutDashboard } from 'lucide-vue-next';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    canRegister: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
    isBooted?: boolean;
}>();

const emit = defineEmits(['magnetic', 'resetMagnetic']);

const words = ['Clear Feedback.', 'Smarter Assessment.', 'Learning Insight.', 'Visible Progress.', 'Guided Growth.'];
const currentWordIndex = ref(0);
const currentCharIndex = ref(words[0].length);
const isTyping = ref(false);
const typedText = ref(words[0]);
let typingTimeout: ReturnType<typeof setTimeout> | null = null;

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
            currentWordIndex.value = (currentWordIndex.value + 1) % words.length;
            typingTimeout = setTimeout(type, 800);
            return;
        }
    }
    
    let delay = isTyping.value ? 40 + Math.random() * 60 : 30;
    if (isTyping.value && typedText.value.endsWith(' ')) delay += 60 + Math.random() * 40;
    
    typingTimeout = setTimeout(type, delay);
};

const handleMagnetic = (e: MouseEvent) => emit('magnetic', e);
const resetMagnetic = (e: MouseEvent) => emit('resetMagnetic', e);

// Scroll Parallax and Premium Reveals
const titleLetters = "LEARNING".split("");
const systemsLetters = "SYSTEMS".split("");
const subtitleLetters = "INTELLIGENCE".split("");
const heroRef = ref<HTMLElement | null>(null);

import { watch } from 'vue';

const initAnimations = () => {
    if (!props.isBooted || !heroRef.value) return;

    // Premium Scroll Parallax for Hero
    gsap.to(".hero-parallax", {
        y: (i, target) => {
            const speed = target.dataset.speed || 0.2;
            return -window.innerHeight * speed;
        },
        ease: "none",
        scrollTrigger: {
            trigger: heroRef.value,
            start: "top top",
            end: "bottom top",
            scrub: true
        }
    });
};

onMounted(() => {
    typingTimeout = setTimeout(type, 2500);
    if (props.isBooted) {
        initAnimations();
    }
});

watch(() => props.isBooted, (newVal) => {
    if (newVal) {
        initAnimations();
    }
});

onBeforeUnmount(() => {
    if (typingTimeout) {
        clearTimeout(typingTimeout);
    }
});
</script>

<template>
    <div ref="heroRef" class="max-w-6xl relative">
        <div class="hero-parallax absolute inset-0 -z-10" data-speed="0.1">
            <slot name="background"></slot>
        </div>

        <div class="mb-2 lg:mb-4 relative z-10 preserve-3d">
            <h1 class="text-5xl sm:text-7xl lg:text-[8rem] font-black tracking-[-0.04em] leading-[0.9] sm:leading-[0.8] uppercase flex flex-col">
                <span class="flex overflow-hidden">
                    <Motion 
                        v-for="(letter, i) in titleLetters" 
                        :key="i"
                        :initial="{ y: 100, opacity: 0, rotateX: -90 }"
                        :animate="isBooted ? { y: 0, opacity: 1, rotateX: 0 } : {}"
                        :transition="{ duration: 1.2, delay: i * 0.04, ease: [0.16, 1, 0.3, 1] }"
                        class="title-letter inline-block transform-gpu"
                    >
                        {{ letter }}
                    </Motion>
                </span>
                <span class="flex overflow-hidden">
                    <Motion 
                        v-for="(letter, i) in systemsLetters" 
                        :key="i"
                        :initial="{ y: 100, opacity: 0, rotateX: -90 }"
                        :animate="isBooted ? { y: 0, opacity: 1, rotateX: 0 } : {}"
                        :transition="{ duration: 1.2, delay: 0.2 + i * 0.04, ease: [0.16, 1, 0.3, 1] }"
                        class="systems-letter inline-block transform-gpu"
                    >
                        {{ letter }}
                    </Motion>
                </span>
                <span class="flex overflow-hidden">
                    <Motion 
                        v-for="(letter, i) in subtitleLetters" 
                        :key="i"
                        :initial="{ x: -20, opacity: 0, filter: 'blur(10px)' }"
                        :animate="isBooted ? { x: 0, opacity: 1, filter: 'blur(0px)' } : {}"
                        :transition="{ duration: 1, delay: 0.4 + i * 0.03, ease: 'ease-out' }"
                        class="subtitle-letter inline-block transform-gpu bg-gradient-to-r from-foreground/50 via-foreground/30 to-foreground/10 bg-clip-text text-transparent italic"
                    >
                        {{ letter === ' ' ? '\u00A0' : letter }}
                    </Motion>
                </span>
            </h1>
        </div>
        
        <div class="mb-10 lg:mb-16 lg:pl-2 relative hero-parallax" data-speed="0.05">
            <p class="max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium leading-relaxed tracking-tight opacity-0 pointer-events-none select-none invisible whitespace-pre-wrap">
                An AI-powered learning system that evaluates work, explains results, tracks mastery, and guides improvement through 
                <span class="font-black uppercase tracking-widest inline-flex items-center">
                    Guided Growth.<span class="ml-1 w-1 h-[0.8em] bg-primary"></span>
                </span> 
            </p>
            
            <Motion 
                :initial="{ opacity: 0, y: 20 }"
                :animate="isBooted ? { opacity: 1, y: 0 } : {}"
                :transition="{ duration: 1.5, ease: 'ease-out', delay: 0.2 }"
                class="absolute inset-0 max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium text-muted-foreground leading-relaxed tracking-tight"
            >
                An AI-powered learning system that evaluates work, explains results, tracks mastery, and guides improvement through 
                <span class="text-foreground font-black uppercase tracking-widest inline-flex items-center">
                    {{ typedText }}<span class="ml-1 w-1 h-[0.8em] bg-primary animate-[pulse_1s_infinite] shadow-[0_0_8px_var(--color-primary)]"></span>
                </span> 
            </Motion>
        </div>

        <div class="overflow-hidden p-2 -m-2 hero-parallax" data-speed="0.02">
            <Motion 
                :initial="{ y: 40, opacity: 0 }"
                :animate="isBooted ? { y: 0, opacity: 1 } : {}"
                :transition="{ duration: 1, ease: [0.16, 1, 0.3, 1], delay: 0.4 }"
                class="flex flex-col sm:flex-row gap-4 sm:gap-6 lg:gap-8"
            >
                <Link v-if="auth.user" :href="dashboard()" 
                    @mousemove="handleMagnetic" 
                    @mouseleave="resetMagnetic"
                    class="group relative flex items-center justify-center bg-primary px-12 py-5 lg:py-6 text-primary-foreground transition-all active:scale-[0.98] shadow-[0_8px_40px_-12px] shadow-primary/30 -skew-x-[12deg] hover:bg-primary/90"
                >
                    <span class="relative z-10 flex items-center gap-3 text-lg font-bold tracking-widest uppercase skew-x-[12deg]">
                        System Dashboard
                        <LayoutDashboard class="w-5 h-5 transition-transform duration-500 group-hover:translate-x-1" />
                    </span>
                    <div class="absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
                </Link>

                <template v-else>
                    <Link :href="login()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="group relative flex items-center justify-center bg-foreground px-12 py-5 lg:py-6 text-background transition-all active:scale-[0.98] -skew-x-[12deg] hover:bg-foreground/90"
                    >
                        <span class="relative z-10 flex items-center gap-3 text-lg font-bold tracking-widest uppercase skew-x-[12deg]">
                            Login
                            <ArrowRight class="w-5 h-5 transition-transform duration-500 group-hover:translate-x-1" />
                        </span>
                    </Link>

                    <Link v-if="canRegister" :href="register()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="group relative flex items-center justify-center border border-border bg-background/50 backdrop-blur-sm px-12 py-5 lg:py-6 text-foreground transition-all active:scale-[0.98] -skew-x-[12deg] hover:bg-muted/50"
                    >
                        <span class="relative z-10 flex items-center gap-3 text-lg font-bold tracking-widest uppercase skew-x-[12deg]">
                            Register
                        </span>
                    </Link>
                </template>
            </Motion>
        </div>
    </div>
</template>
