<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { BookOpen, Video, ArrowRight, Github, LayoutDashboard, Command, Zap, Award, Target, Sun, Moon, Cpu, Activity, Zap as ZapIcon, BrainCircuit, Timer, CheckCircle2, XCircle, ChevronDown, ClipboardCheck, FileText, Trophy, Sparkles, Play } from 'lucide-vue-next';

gsap.registerPlugin(ScrollTrigger);
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { dashboard, login, register } from '@/routes';

interface ActiveSeason {
    name: string;
    startDate: string | null;
    endDate: string | null;
    showCountdown: boolean;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        totalUsers?: number;
        totalExams?: number;
        totalAssignments?: number;
        totalSubmissions?: number;
        activeSeason?: ActiveSeason | null;
    }>(),
    {
        canRegister: true,
        totalUsers: 0,
        totalExams: 0,
        totalAssignments: 0,
        totalSubmissions: 0,
        activeSeason: null,
    },
);

const featureCards = ref<HTMLElement[]>([]);
const structuralLines = ref<HTMLElement[]>([]);
const mainContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const mouseGlow = ref<HTMLElement | null>(null);
const techCarousel = ref<HTMLElement | null>(null);
const ambientOrbs = ref<HTMLElement[]>([]);
const bootOverlay = ref<HTMLElement | null>(null);
const bootText = ref<HTMLElement[]>([]);

// Determine boot message based on session state
const isLoggingOut = ref(false);

const bootMessage = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'SESSION TERMINATED';
    }
    return 'LSI ENGINE';
});

const bootSubtext = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'Safely Terminating Active Node Sessions...';
    }
    return 'Booting System Components...';
});

let typingTimeout: ReturnType<typeof setTimeout> | null = null;
let removeMediaListeners = () => {};
let gsapCtx: gsap.Context | null = null;
const isCoarsePointer = ref(false);
const prefersReducedMotion = ref(false);

// Appearance Management
const { appearance, toggleTheme } = useAppearance();

// Animated Metrics (Feature 3 & 9 — Real Data)
const animUsers = useNumberAnimation(() => props.totalUsers || 0, 2, 'expo.out');
const animExams = useNumberAnimation(() => props.totalExams || 0, 1.8, 'power2.out');
const animAssignments = useNumberAnimation(() => props.totalAssignments || 0, 2.2, 'expo.out');
const animSubmissions = useNumberAnimation(() => props.totalSubmissions || 0, 2.5, 'power4.out');

// Feature 8 — Season Countdown Timer
const countdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
const countdownActive = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const updateCountdown = () => {
    if (!props.activeSeason?.endDate || !props.activeSeason?.showCountdown) {
        countdownActive.value = false;
        return;
    }
    const end = new Date(props.activeSeason.endDate).getTime();
    const now = Date.now();
    const diff = end - now;
    if (diff <= 0) {
        countdownActive.value = false;
        countdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
        if (countdownInterval) clearInterval(countdownInterval);
        return;
    }
    countdownActive.value = true;
    countdown.value = {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((diff / (1000 * 60)) % 60),
        seconds: Math.floor((diff / 1000) % 60),
    };
};

// Feature 6 — Interactive Demo Quiz
interface DemoQuestion {
    id: number;
    text: string;
    type: 'multiple_choice' | 'true_false';
    options: string[];
    correctIndex: number;
    explanation: string;
}

const demoQuestions: DemoQuestion[] = [
    {
        id: 1,
        text: 'What does HTML stand for?',
        type: 'multiple_choice',
        options: ['Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language'],
        correctIndex: 0,
        explanation: 'HTML stands for Hyper Text Markup Language — the standard language for creating web pages.'
    },
    {
        id: 2,
        text: 'JavaScript is a compiled programming language.',
        type: 'true_false',
        options: ['True', 'False'],
        correctIndex: 1,
        explanation: 'JavaScript is an interpreted (or JIT compiled) scripting language, not a traditionally compiled language.'
    },
    {
        id: 3,
        text: 'Which CSS property controls the font size?',
        type: 'multiple_choice',
        options: ['text-size', 'font-style', 'font-size', 'text-style'],
        correctIndex: 2,
        explanation: 'The font-size property is used to specify the size of the font in CSS.'
    },
];

const currentDemoQuestion = ref(0);
const selectedDemoAnswer = ref<number | null>(null);
const demoAnswered = ref(false);
const demoScore = ref(0);
const demoCompleted = ref(false);

const selectDemoAnswer = (index: number) => {
    if (demoAnswered.value) return;
    selectedDemoAnswer.value = index;
    demoAnswered.value = true;
    if (index === demoQuestions[currentDemoQuestion.value].correctIndex) {
        demoScore.value++;
    }
};

const nextDemoQuestion = () => {
    if (currentDemoQuestion.value < demoQuestions.length - 1) {
        currentDemoQuestion.value++;
        selectedDemoAnswer.value = null;
        demoAnswered.value = false;
    } else {
        demoCompleted.value = true;
    }
};

const resetDemoQuiz = () => {
    currentDemoQuestion.value = 0;
    selectedDemoAnswer.value = null;
    demoAnswered.value = false;
    demoScore.value = 0;
    demoCompleted.value = false;
};

// Feature 10 — Expandable Feature Cards
const expandedFeature = ref<number | null>(null);
const toggleFeature = (index: number) => {
    expandedFeature.value = expandedFeature.value === index ? null : index;
};

const syncInteractionModes = () => {
    isCoarsePointer.value = window.matchMedia('(pointer: coarse)').matches;
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

// Interaction Logic
const handleMagnetic = (e: MouseEvent) => {
    if (isCoarsePointer.value || prefersReducedMotion.value) return;

    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    
    gsap.to(btn, {
        x: x * 0.4,
        y: y * 0.4,
        duration: 0.3,
        ease: 'power2.out'
    });
};

const resetMagnetic = (e: MouseEvent) => {
    const btn = e.currentTarget as HTMLElement;
    gsap.to(btn, {
        x: 0,
        y: 0,
        duration: 0.5,
        ease: 'elastic.out(1, 0.3)'
    });
};

const handleGlobalMouseMove = (e: MouseEvent) => {
    if (!mouseGlow.value || !backgroundGrid.value || isCoarsePointer.value || prefersReducedMotion.value) return;

    const { clientX, clientY } = e;
    const xPercent = clientX / window.innerWidth;
    const yPercent = clientY / window.innerHeight;

    // Background Glow
    gsap.to(mouseGlow.value, {
        x: clientX,
        y: clientY,
        duration: 1.2,
        ease: 'power3.out'
    });

    // Grid Parallax
    gsap.to(backgroundGrid.value, {
        x: (xPercent - 0.5) * 40,
        y: (yPercent - 0.5) * 40,
        duration: 1.5,
        ease: 'power2.out'
    });
};

const handleFeatureMouseMove = (e: MouseEvent) => {
    if (isCoarsePointer.value || prefersReducedMotion.value) return;

    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const xPercent = (x / rect.width - 0.5) * 2;
    const yPercent = (y / rect.height - 0.5) * 2;

    // 3D Tilt
    gsap.to(card, {
        rotateY: xPercent * 5,
        rotateX: -yPercent * 5,
        transformPerspective: 1000,
        duration: 0.4,
        ease: 'power2.out'
    });

    // Local Glow
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const resetFeatureMouse = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        rotateY: 0,
        rotateX: 0,
        duration: 0.8,
        ease: 'power4.out'
    });
};

// Typing Animation Logic
const words = ['Peak Performance.', 'Operational Elite.', 'Architectural Might.', 'System Synergy.', 'High Precision.'];
const currentWordIndex = ref(0);
const currentCharIndex = ref(words[0].length);
const isTyping = ref(false);
const typedText = ref(words[0]);

const type = () => {
    const currentWord = words[currentWordIndex.value];
    
    if (isTyping.value) {
        typedText.value = currentWord.substring(0, currentCharIndex.value + 1);
        currentCharIndex.value++;
        
        if (currentCharIndex.value === currentWord.length) {
            isTyping.value = false;
            typingTimeout = setTimeout(type, 2000); // Wait at end
            return;
        }
    } else {
        typedText.value = currentWord.substring(0, currentCharIndex.value - 1);
        currentCharIndex.value--;
        
        if (currentCharIndex.value === 0) {
            isTyping.value = true;
            currentWordIndex.value = (currentWordIndex.value + 1) % words.length;
            typingTimeout = setTimeout(type, 500); // Wait at start
            return;
        }
    }
    
    const delay = isTyping.value ? 100 : 50;
    typingTimeout = setTimeout(type, delay);
};

onMounted(() => {
    // Check logout state before starting
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        isLoggingOut.value = true;
    }

    syncInteractionModes();

    const coarsePointerQuery = window.matchMedia('(pointer: coarse)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    const onMediaChange = () => syncInteractionModes();
    coarsePointerQuery.addEventListener('change', onMediaChange);
    reducedMotionQuery.addEventListener('change', onMediaChange);
    removeMediaListeners = () => {
        coarsePointerQuery.removeEventListener('change', onMediaChange);
        reducedMotionQuery.removeEventListener('change', onMediaChange);
    };

    // Start typing after initial load
    typingTimeout = setTimeout(type, 2500);

    gsapCtx = gsap.context(() => {
        const motionFactor = prefersReducedMotion.value ? 0.55 : 1;
        const tl = gsap.timeline({
            paused: true,
            defaults: { ease: 'expo.out', duration: 1.4 * motionFactor }
        });

        // 0. Pre-Boot State
        gsap.set(bootOverlay.value, { autoAlpha: 1 });
        gsap.set('.reveal-content', { y: '100%', opacity: 0 });
        gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
        gsap.set('.footer-reveal > *', { opacity: 0, y: 30 });
        gsap.set('.reveal-section.grid-cols-2', { opacity: 0, y: 50 });
        gsap.set(featureCards.value, { opacity: 0, y: 60 });
        gsap.set('.reveal-section.mt-24 .flex.items-center', { opacity: 0, x: -30 });
        gsap.set('.signal-fill', { scaleX: 0, transformOrigin: 'left center' });
        gsap.set('.pulse-panel', { autoAlpha: 0, y: 16, clipPath: 'inset(0 0 100% 0)' });
        gsap.set('.pulse-row', { autoAlpha: 0, y: 16 });
        gsap.set('.quick-link-row', { autoAlpha: 0, y: 16, x: 18 });
        gsap.set('.online-pill', { autoAlpha: 0, y: 16, scale: 0.86 });

        // 1. Boot Sequence (The Entrance)
        tl.to(bootText.value, {
            opacity: 1,
            y: 0,
            stagger: 0.04,
            duration: 0.8 * motionFactor,
            ease: 'back.out(2)'
        })
        .to('.boot-progress', {
            scaleX: 1,
            duration: 0.4 * motionFactor,
            ease: 'power4.inOut'
        }, '-=0.4')
        .to(bootOverlay.value, {
            clipPath: 'inset(0 0 100% 0)',
            duration: 1.4 * motionFactor,
            ease: 'power4.inOut',
            onComplete: () => {
                if (bootOverlay.value) bootOverlay.value.style.display = 'none';
                if (isLoggingOut.value) {
                    sessionStorage.removeItem('logged_out');
                    isLoggingOut.value = false;
                }
            }
        }, '+=0.2')

        // 2. Animate Structural Lines
        .to(structuralLines.value, {
            scaleX: 1,
            scaleY: 1,
            stagger: 0.1,
            duration: 1.2,
            ease: 'power4.inOut'
        }, '-=0.8')
        // 3. Reveal Nav
        .from('.nav-item', {
            y: -20,
            opacity: 0,
            stagger: 0.05,
            duration: 0.4 * motionFactor
        }, '-=0.8')
        // 4. Reveal Hero with Clip-Path/Overflow
        .to('.hero-reveal .reveal-content', {
            y: '0%',
            opacity: 1,
            stagger: 0.2,
            duration: 1.5 * motionFactor
        }, '-=1.2')
        // 5. Explicit entrance choreography for pulse + links (These are usually in view immediately)
        .to('.pulse-panel', {
            clipPath: 'inset(0 0 0% 0)',
            y: 0,
            autoAlpha: 1,
            duration: 1.1 * motionFactor,
            ease: 'power3.out'
        }, '-=1.3')
        .to('.online-pill', {
            scale: 1,
            autoAlpha: 1,
            y: 0,
            duration: 0.7 * motionFactor,
            ease: 'back.out(1.8)'
        }, '-=1.1')
        .to('.pulse-row', {
            y: 0,
            autoAlpha: 1,
            stagger: 0.12,
            duration: 0.8 * motionFactor,
            ease: 'power3.out'
        }, '-=1')
        .to('.quick-link-row', {
            x: 0,
            y: 0,
            autoAlpha: 1,
            stagger: 0.1,
            duration: 0.65 * motionFactor,
            ease: 'power2.out'
        }, '-=0.9')
        // 9. Signal bar entrance
        .to('.signal-fill', {
            scaleX: 1,
            stagger: 0.14,
            duration: 0.9 * motionFactor,
            ease: 'power2.out'
        }, '-=0.7');

        // --- NEW: Scroll-Triggered Animations ---
        
        // Metrics Ticker
        gsap.to('.reveal-section.grid-cols-2', {
            scrollTrigger: {
                trigger: '.reveal-section.grid-cols-2',
                start: 'top 85%',
            },
            y: 0,
            opacity: 1,
            duration: 1.2,
            ease: 'power3.out'
        });

        // Features Grid
        featureCards.value.forEach((card, i) => {
            gsap.to(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 90%',
                },
                y: 0,
                opacity: 1,
                duration: 1,
                delay: i * 0.1,
                ease: 'power4.out'
            });
        });

        // Tech Stack Header
        gsap.to('.reveal-section.mt-24 .flex.items-center', {
            scrollTrigger: {
                trigger: '.reveal-section.mt-24',
                start: 'top 85%',
            },
            x: 0,
            opacity: 1,
            duration: 1,
            ease: 'power2.out'
        });

        // Footer Reveal
        gsap.to('.footer-reveal > *', {
            scrollTrigger: {
                trigger: 'footer',
                start: 'top 90%',
            },
            y: 0,
            opacity: 1,
            stagger: 0.1,
            duration: 1,
            ease: 'power3.out'
        });

        gsap.to('.signal-fill', {
            opacity: 0.7,
            duration: 1.8,
            yoyo: true,
            repeat: -1,
            stagger: 0.2,
            ease: 'sine.inOut'
        });

        if (ambientOrbs.value.length > 0) {
            gsap.to(ambientOrbs.value, {
                y: (index) => (index % 2 ? 18 : -18),
                x: (index) => (index % 2 ? -8 : 12),
                duration: 6,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                stagger: 0.5
            });
        }

        // Tech Carousel Logic
        if (techCarousel.value) {
            gsap.to(techCarousel.value, {
                xPercent: -50,
                duration: isCoarsePointer.value ? 30 : 20,
                ease: 'none',
                repeat: -1
            });
        }

        requestAnimationFrame(() => tl.play(0));
    }, mainContainer);

    // Feature 8 — Start countdown timer
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
});

onBeforeUnmount(() => {
    if (typingTimeout) {
        clearTimeout(typingTimeout);
        typingTimeout = null;
    }

    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    removeMediaListeners();

    if (gsapCtx) {
        gsapCtx.revert();
        gsapCtx = null;
    }
});

const liveSignals = [
    { label: 'Session Throughput', value: 92, valueLabel: '92%' },
    { label: 'Adaptive Routing', value: 86, valueLabel: '86%' },
    { label: 'Knowledge Sync', value: 97, valueLabel: '97%' },
];

const quickLinks = [
    { label: 'Documentation', href: '#', icon: BookOpen },
    { label: 'Live Sessions', href: '#', icon: Video },
    { label: 'Source Cluster', href: '#', icon: Github },
];

const orbLayers = [
    { style: 'width: 11rem; height: 11rem; left: -4.5rem; top: 5rem;' },
    { style: 'width: 14rem; height: 14rem; right: -5.5rem; top: 28%;' },
    { style: 'width: 9rem; height: 9rem; right: 18%; bottom: -4.5rem;' },
];

const coreFeatures = [
    {
        title: 'Assessment Engine',
        description: 'Industrial-grade testing infrastructure with real-time analytics and automated evaluation modules.',
        icon: Target,
        code: 'MOD_EXM_01',
        details: 'Take timed exams with multiple question types: multiple choice, true/false, identification, and AI-graded essays. Get instant feedback and track your performance across seasons.',
        stats: [{ label: 'Question Types', value: '4' }, { label: 'AI Grading', value: 'Active' }, { label: 'Auto-Score', value: 'Real-time' }]
    },
    {
        title: 'Skill Acquisition',
        description: 'Structured assignment workflows designed to track progressive growth and mastery across seasons.',
        icon: Zap,
        code: 'MOD_ASN_02',
        details: 'Submit assignments with file uploads, track deadlines, and receive grades from your instructors. Stay on top of every mission objective with status tracking.',
        stats: [{ label: 'File Upload', value: 'Secure' }, { label: 'Deadline Alerts', value: 'Live' }, { label: 'Grade Tracking', value: 'Instant' }]
    },
    {
        title: 'Operational Elite',
        description: 'High-fidelity leaderboard system driven by XP, streaks, and competitive intelligence metrics.',
        icon: Award,
        code: 'MOD_LDR_03',
        details: 'Compete with peers on the section-based leaderboard. Earn XP from exams, assignments, and daily streaks. Rise through the ranks and dominate your section.',
        stats: [{ label: 'XP System', value: 'Active' }, { label: 'Streak Bonus', value: 'Daily' }, { label: 'Sections', value: 'Multi' }]
    }
];

const systemStats = computed(() => [
    { label: 'Active Users', value: animUsers.value, unit: 'NODES', icon: Cpu },
    { label: 'Assessments', value: animExams.value, unit: 'LIVE', icon: ClipboardCheck },
    { label: 'Assignments', value: animAssignments.value, unit: 'ACTIVE', icon: FileText },
    { label: 'Submissions', value: animSubmissions.value, unit: 'TOTAL', icon: Trophy },
]);

const techStack = [
    { name: 'Laravel 11', description: 'Robust backend architecture for scale.', icon: Command },
    { name: 'Vue 3', description: 'High-performance reactive UI system.', icon: Zap },
    { name: 'Inertia.js', description: 'The modern monolith connection layer.', icon: Target },
    { name: 'GSAP', description: 'Ultra-smooth professional animations.', icon: Award },
    { name: 'Tailwind CSS', description: 'Utility-first design framework.', icon: LayoutDashboard },
    { name: 'TypeScript', description: 'Type-safe scalable development.', icon: Command },
];
</script>

<template>
    <Head title="Welcome | LUAV Learning Engine" />
    
    <div 
        ref="mainContainer"
        @mousemove="handleGlobalMouseMove"
        class="relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20 transition-colors duration-500"
    >
        <!-- Global Mouse Glow -->
        <div 
            ref="mouseGlow"
            class="pointer-events-none fixed -left-[200px] -top-[200px] z-0 hidden h-[400px] w-[400px] rounded-full bg-primary/[0.06] blur-[150px] will-change-transform dark:bg-primary/[0.12] md:block"
        ></div>

        <!-- Monolithic Grid Overlay -->
        <div ref="backgroundGrid" class="fixed inset-[-100px] z-0 pointer-events-none opacity-[0.025] dark:opacity-[0.05] will-change-transform">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>
        <div
            v-for="(orb, index) in orbLayers"
            :key="index"
            ref="ambientOrbs"
            class="ambient-orb pointer-events-none absolute z-[1] rounded-full"
            :style="orb.style"
        ></div>

        <!-- System Structural Constraints (Lines) -->
        <div class="fixed inset-y-0 left-4 lg:left-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed inset-y-0 right-4 lg:right-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-bottom" ref="structuralLines"></div>
        <div class="fixed inset-x-0 top-1/4 h-px bg-border/20 z-0 hidden lg:block origin-left" ref="structuralLines"></div>
        <div class="fixed inset-x-0 bottom-1/4 h-px bg-border/20 z-0 hidden lg:block origin-right" ref="structuralLines"></div>

        <!-- Global Header -->
        <header class="relative z-20 flex w-full items-center justify-between px-6 py-5 lg:px-16 lg:py-6 border-b border-border/10 dark:border-border/5 backdrop-blur-2xl bg-background/60 dark:bg-background/30 transition-colors duration-500">
            <!-- Header glow line -->
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
            <div class="nav-item flex items-center gap-3 lg:gap-4 group cursor-pointer">
                <div class="relative flex h-10 w-10 items-center justify-center text-foreground transition-all duration-700 group-hover:rotate-[180deg]">
                    <div class="absolute inset-0 rounded-xl bg-primary/5 dark:bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <Command class="h-6 w-6 lg:h-7 lg:w-7 relative z-10" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] lg:text-xs font-black tracking-[0.4em] uppercase">LUAV Engine</span>
                    <span class="text-[7px] lg:text-[8px] font-bold text-primary/60 uppercase mt-1 tracking-widest">v6.4.0</span>
                </div>
            </div>

            <nav class="flex items-center gap-4 lg:gap-8">
                <!-- Theme Toggle Button - always visible -->
                <button 
                    @click="toggleTheme" 
                    class="relative p-2.5 text-muted-foreground hover:text-foreground transition-all active:scale-90 rounded-xl hover:bg-muted/40"
                    aria-label="Toggle Theme"
                >
                    <Sun v-if="appearance === 'dark'" class="h-4 w-4 lg:h-5 lg:w-5" />
                    <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
                </button>

                <template v-if="$page.props.auth.user">
                    <Link :href="dashboard()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-muted-foreground hover:text-primary transition-all flex items-center gap-2"
                    >
                        <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.6)]"></div>
                        <span class="hidden sm:inline">Access Engine</span>
                        <span class="sm:hidden">Engine</span>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="login()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Login
                    </Link>
                    <Link v-if="canRegister" :href="register()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item relative bg-foreground text-background px-5 lg:px-8 py-2.5 lg:py-3 text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] hover:bg-primary transition-all shadow-2xl overflow-hidden group"
                    >
                        <span class="relative z-10">Init</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary to-primary/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Main Operational Interface -->
        <main class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28">
            
            <!-- Hero Monolith -->
            <div class="max-w-6xl">
                <div class="hero-reveal overflow-hidden mb-2 lg:mb-4">
                    <h1 class="reveal-content text-5xl sm:text-7xl lg:text-[8rem] font-black tracking-[-0.04em] leading-[0.9] sm:leading-[0.8] uppercase flex flex-col">
                        <span>Learning Systems</span>
                        <span class="bg-gradient-to-r from-muted-foreground/30 via-muted-foreground/15 to-muted-foreground/5 bg-clip-text text-transparent italic">Intelligence</span>
                    </h1>
                </div>
                
                <div class="hero-reveal mb-10 lg:mb-16 lg:pl-2 relative">
                    <!-- Invisible Shadow Element: Reserves the maximum possible space to prevent layout shifts -->
                    <p class="max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium leading-relaxed tracking-tight opacity-0 pointer-events-none select-none invisible whitespace-pre-wrap">
                        Access the industrial-grade assessment engine engineered for high-fidelity performance and architectural growth in 
                        <span class="font-black uppercase tracking-widest inline-flex items-center">
                            Architectural Might.<span class="ml-1 w-1 h-[0.8em] bg-primary"></span>
                        </span> 
                    </p>
                    
                    <!-- Visible Animated Element: Positioned absolutely within the reserved space -->
                    <p class="reveal-content absolute inset-0 max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium text-muted-foreground leading-relaxed tracking-tight">
                        Access the industrial-grade assessment engine engineered for high-fidelity performance and architectural growth in 
                        <span class="text-foreground font-black uppercase tracking-widest inline-flex items-center">
                            {{ typedText }}<span class="ml-1 w-1 h-[0.8em] bg-primary animate-[pulse_1s_infinite] shadow-[0_0_8px_var(--color-primary)]"></span>
                        </span> 
                    </p>
                </div>




                <div class="hero-reveal overflow-hidden">
                    <div class="reveal-content flex flex-col sm:flex-row gap-3 lg:gap-4">
                        <Link v-if="$page.props.auth.user" :href="dashboard()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group relative flex items-center justify-between gap-8 lg:gap-12 bg-primary px-8 lg:px-10 py-5 lg:py-6 text-primary-foreground transition-all hover:gap-12 lg:hover:gap-16 active:scale-[0.98] shadow-[0_8px_40px_-12px] shadow-primary/30 overflow-hidden"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] relative z-10">Initialize Dashboard</span>
                            <ArrowRight class="h-5 w-5 lg:h-6 lg:w-6 relative z-10 group-hover:translate-x-1 transition-transform" />
                            <div class="absolute inset-0 bg-gradient-to-r from-primary via-primary to-primary/80 opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                        </Link>
                        <Link v-else :href="login()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group relative flex items-center justify-between gap-8 lg:gap-12 bg-foreground px-8 lg:px-10 py-5 lg:py-6 text-background transition-all hover:gap-12 lg:hover:gap-16 active:scale-[0.98] shadow-2xl overflow-hidden"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] relative z-10">Authenticate System</span>
                            <ArrowRight class="h-5 w-5 lg:h-6 lg:w-6 relative z-10 group-hover:translate-x-1 transition-transform" />
                            <div class="absolute inset-0 bg-primary opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        </Link>
                        
                        <Link v-if="!$page.props.auth.user && canRegister" :href="register()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group flex items-center justify-between gap-8 lg:gap-10 border border-border/40 dark:border-border/20 px-8 lg:px-10 py-5 lg:py-6 transition-all hover:bg-muted/30 hover:border-primary/30 active:scale-[0.98] text-muted-foreground"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em]">Register Account</span>
                            <LayoutDashboard class="h-4 w-4 lg:h-5 lg:w-5 opacity-40 group-hover:opacity-100 group-hover:text-primary transition-all" />
                        </Link>
                    </div>
                </div>
            </div>

            <div class="reveal-section mt-10 lg:mt-16 grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                <section class="pulse-panel relative overflow-hidden rounded-2xl border border-border/30 dark:border-border/15 bg-card/60 dark:bg-background/50 p-5 sm:p-6 lg:p-8 shadow-[0_20px_80px_-30px_rgba(0,0,0,0.15)] dark:shadow-[0_20px_80px_-45px_rgba(0,0,0,0.45)] backdrop-blur-2xl">
                    <div class="scan-line pointer-events-none absolute inset-x-0 top-0 h-px"></div>
                    <!-- Inner glow accent -->
                    <div class="absolute -top-20 -right-20 h-40 w-40 rounded-full bg-primary/5 dark:bg-primary/10 blur-3xl pointer-events-none"></div>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary/80">Live System Pulse</p>
                            <h2 class="mt-2 text-xl sm:text-2xl font-black tracking-tight">Operational signals in real time</h2>
                        </div>
                        <div class="online-pill hidden sm:flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.6)]"></span>
                            Online
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div v-for="signal in liveSignals" :key="signal.label" class="pulse-row rounded-xl border border-border/20 dark:border-border/10 bg-muted/20 dark:bg-foreground/[0.03] p-3 sm:p-4 hover:bg-muted/30 dark:hover:bg-foreground/[0.05] transition-colors">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-[10px] sm:text-xs font-black uppercase tracking-[0.15em] text-muted-foreground">{{ signal.label }}</span>
                                <span class="text-[10px] sm:text-xs font-black tracking-wider text-foreground">{{ signal.valueLabel }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted/50 dark:bg-foreground/10">
                                <div class="signal-fill h-full rounded-full bg-gradient-to-r from-primary/60 to-primary" :style="{ width: `${signal.value}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="pulse-panel relative overflow-hidden rounded-2xl border border-border/30 dark:border-border/15 bg-card/60 dark:bg-background/50 p-5 sm:p-6 lg:p-8 backdrop-blur-2xl">
                    <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-primary/5 dark:bg-primary/10 blur-3xl"></div>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary/80">Control Links</p>
                    <h3 class="mt-2 text-xl font-black tracking-tight">Stay inside one command surface.</h3>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">Jump directly into docs, session feed, and source channels without leaving the launch page.</p>

                    <div class="mt-5 space-y-2">
                        <a
                            v-for="quickLink in quickLinks"
                            :key="quickLink.label"
                            :href="quickLink.href"
                            class="quick-link-row group flex items-center justify-between rounded-xl border border-border/20 dark:border-border/10 px-3 sm:px-4 py-3 transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.05] hover:border-primary/20"
                        >
                            <span class="flex items-center gap-3">
                                <component :is="quickLink.icon" class="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                <span class="text-xs font-black uppercase tracking-[0.18em] text-foreground/80">{{ quickLink.label }}</span>
                            </span>
                            <ArrowRight class="h-4 w-4 text-muted-foreground/40 group-hover:text-primary transition-all group-hover:translate-x-1" />
                        </a>
                    </div>
                </section>
            </div>

            <!-- System Metrics Ticker -->
            <div class="reveal-section mt-24 lg:mt-40 grid grid-cols-2 md:grid-cols-4 gap-8 lg:gap-20 border-y border-border/20 dark:border-border/10 py-8 lg:py-14">
                <div v-for="stat in systemStats" :key="stat.label" class="flex flex-col gap-3 group hover:-translate-y-1 transition-transform duration-500">
                    <div class="flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/5 dark:bg-primary/10 group-hover:bg-primary/10 dark:group-hover:bg-primary/20 transition-colors">
                            <component :is="stat.icon" class="h-3.5 w-3.5 text-primary opacity-60 group-hover:opacity-100 transition-opacity" />
                        </div>
                        <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">{{ stat.label }}</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl lg:text-4xl font-black tracking-tighter tabular-nums">{{ stat.value }}</span>
                        <span class="text-[10px] lg:text-xs font-bold text-primary tracking-widest">{{ stat.unit }}</span>
                    </div>
                    <div class="h-px w-full bg-gradient-to-r from-primary/30 to-transparent scale-x-0 group-hover:scale-x-100 origin-left transition-transform duration-700"></div>
                </div>
            </div>

            <!-- Features Array (Feature 10 — Expandable) -->
            <div class="mt-12 lg:mt-24 grid w-full lg:grid-cols-3 gap-0 border-b border-border/20 dark:border-border/10">
                <div 
                    v-for="(feature, index) in coreFeatures" 
                    :key="index"
                    ref="featureCards"
                    @mousemove="handleFeatureMouseMove($event)"
                    @mouseleave="resetFeatureMouse"
                    class="group relative flex flex-col p-8 sm:p-12 lg:p-16 border-border/20 dark:border-border/10 transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.02] overflow-hidden cursor-pointer"
                    :class="[
                        { 'border-b lg:border-b-0 lg:border-r': index !== coreFeatures.length - 1 },
                        expandedFeature === index ? 'bg-muted/20 dark:bg-foreground/[0.03]' : ''
                    ]"
                    @click="toggleFeature(index)"
                >
                    <!-- Local Card Glow -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(500px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--glow-color), transparent 40%)` }">
                    </div>

                    <!-- Module Indicator -->
                    <div class="absolute top-8 left-8 lg:left-12 flex items-center gap-3">
                         <span class="text-[8px] font-black tracking-widest text-primary/70 leading-none group-hover:text-primary transition-colors">{{ feature.code }}</span>
                         <div class="h-px w-8 lg:w-12 bg-primary/20 group-hover:w-16 lg:group-hover:w-32 group-hover:bg-gradient-to-r group-hover:from-primary group-hover:to-transparent transition-all duration-700"></div>
                    </div>

                    <div class="mt-8 lg:mt-12 mb-8 lg:mb-12 flex h-14 w-14 lg:h-20 lg:w-20 relative border border-border/30 dark:border-border/10 bg-muted/20 dark:bg-foreground/[0.02] transition-all duration-700 group-hover:rotate-[15deg] group-hover:scale-110 group-hover:border-primary/40 group-hover:bg-primary/5 rounded-2xl items-center justify-center overflow-hidden">
                        <!-- Icon subtle glow -->
                        <div class="absolute inset-0 bg-primary opacity-0 group-hover:opacity-[0.15] blur-xl transition-opacity duration-700"></div>
                        <component :is="feature.icon" class="h-6 w-6 lg:h-8 lg:w-8 text-muted-foreground opacity-40 group-hover:opacity-100 transition-all group-hover:text-primary duration-500 relative z-10" />
                    </div>
                    
                    <div class="space-y-4 lg:space-y-6 relative z-10">
                        <h3 class="text-xl lg:text-3xl font-black uppercase tracking-tight">{{ feature.title }}</h3>
                        <p class="text-sm lg:text-base leading-relaxed text-muted-foreground group-hover:text-foreground/90 transition-colors duration-500 max-w-sm">
                            {{ feature.description }}
                        </p>
                    </div>

                    <div class="mt-10 lg:mt-16 relative z-10">
                        <button class="text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] text-muted-foreground hover:text-primary transition-all flex items-center gap-4 group/btn">
                            {{ expandedFeature === index ? 'Close Specs' : 'View Specs' }}
                            <ChevronDown class="h-4 w-4 transition-transform duration-500 group-hover/btn:translate-y-0.5" :class="{ 'rotate-180 group-hover/btn:-translate-y-0.5': expandedFeature === index }" />
                        </button>
                    </div>

                    <!-- Expandable Detail Panel -->
                    <Transition
                        enter-active-class="transition-all duration-700 ease-[0.2,0.8,0.2,1]"
                        enter-from-class="max-h-0 opacity-0 translate-y-4"
                        enter-to-class="max-h-[500px] opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-300 ease-in"
                        leave-from-class="max-h-[500px] opacity-100 translate-y-0"
                        leave-to-class="max-h-0 opacity-0 -translate-y-4"
                    >
                        <div v-if="expandedFeature === index" class="relative overflow-hidden mt-8 lg:mt-12 pt-8 z-10">
                            <!-- Gradient Top Border -->
                            <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
                            
                            <p class="text-sm leading-relaxed text-muted-foreground mb-8 max-w-md bg-muted/30 dark:bg-foreground/[0.03] p-5 rounded-lg border border-border/20 dark:border-border/10">
                                <Sparkles class="w-4 h-4 text-primary mb-3 inline-block" />
                                <br />
                                {{ feature.details }}
                            </p>
                            
                            <!-- Feature Mini Stats -->
                            <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-8">
                                <div v-for="stat in feature.stats" :key="stat.label" class="p-3 sm:p-4 border border-border/40 dark:border-border/20 bg-card dark:bg-background/50 backdrop-blur-sm rounded-lg shadow-sm">
                                    <p class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-muted-foreground mb-1.5">{{ stat.label }}</p>
                                    <p class="text-[11px] sm:text-xs font-black text-primary tracking-widest">{{ stat.value }}</p>
                                </div>
                            </div>

                            <Link v-if="$page.props.auth.user" :href="dashboard()" class="inline-flex items-center gap-4 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] bg-primary text-primary-foreground px-6 py-4 hover:bg-primary/90 transition-all rounded-lg shadow-lg hover:shadow-primary/20 hover:gap-6 group/link">
                                Access Module
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                            <Link v-else :href="login()" class="inline-flex items-center gap-4 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] bg-foreground text-background px-6 py-4 hover:bg-primary hover:text-primary-foreground transition-all rounded-lg shadow-lg hover:shadow-primary/20 hover:gap-6 group/link">
                                Login to Access
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Feature 8 — Season Countdown Timer -->
            <div v-if="countdownActive && activeSeason" class="reveal-section mt-24 lg:mt-40 relative">
                <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/80 dark:bg-background/70 p-6 sm:p-10 lg:p-14 backdrop-blur-xl shadow-lg dark:shadow-none">
                    <!-- Scan line effect -->
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                    
                    <!-- Corner brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary/40 pointer-events-none"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary/40 pointer-events-none"></div>
                    
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 lg:gap-8">
                        <div class="space-y-2 sm:space-y-3">
                            <div class="flex items-center gap-3">
                                <Timer class="h-4 w-4 text-primary animate-pulse" />
                                <span class="text-[9px] font-black uppercase tracking-[0.3em] text-primary">Season Timer</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black uppercase tracking-tight">{{ activeSeason.name }}</h2>
                            <p class="text-[10px] sm:text-xs text-muted-foreground uppercase tracking-widest">Time Remaining Until Season End</p>
                        </div>
                        
                        <div class="grid grid-cols-4 gap-2 sm:gap-4 lg:gap-5">
                            <div v-for="(unit, key) in { DAYS: countdown.days, HRS: countdown.hours, MIN: countdown.minutes, SEC: countdown.seconds }" :key="key" class="flex flex-col items-center p-3 sm:p-5 lg:p-6 border border-border/40 dark:border-border/15 bg-muted/40 dark:bg-foreground/[0.04] rounded-lg min-w-0">
                                <span class="text-xl sm:text-3xl lg:text-5xl font-black tracking-tighter tabular-nums font-mono leading-none">
                                    {{ String(unit).padStart(2, '0') }}
                                </span>
                                <span class="text-[6px] sm:text-[8px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] text-muted-foreground mt-1.5 sm:mt-2">{{ key }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress bar showing season elapsed -->
                    <div v-if="activeSeason.startDate" class="mt-6 sm:mt-8 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground">Season Progress</span>
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-primary">
                                {{ Math.round((Date.now() - new Date(activeSeason.startDate).getTime()) / (new Date(activeSeason.endDate!).getTime() - new Date(activeSeason.startDate).getTime()) * 100) }}%
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted/60 dark:bg-foreground/10">
                            <div class="h-full rounded-full bg-primary/70 transition-all duration-1000" :style="{ width: Math.min(100, Math.round((Date.now() - new Date(activeSeason.startDate).getTime()) / (new Date(activeSeason.endDate!).getTime() - new Date(activeSeason.startDate).getTime()) * 100)) + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature 6 — Interactive Demo Quiz -->
            <div class="reveal-section mt-24 lg:mt-40 relative">
                <div class="flex items-center gap-4 mb-8 sm:mb-10">
                    <div class="h-px w-12 bg-primary"></div>
                    <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]">Try a Sample Assessment</h2>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/80 dark:bg-background/70 backdrop-blur-xl shadow-lg dark:shadow-none">
                    <!-- Corner brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-2 border-l-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-2 border-r-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>

                    <!-- Quiz Header -->
                    <div class="border-b border-border/30 dark:border-border/15 p-4 sm:p-6 lg:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center border border-primary/30 bg-primary/10 rounded-lg shrink-0">
                                <Play class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-[0.3em] text-primary">Demo Protocol</p>
                                <h3 class="text-base sm:text-lg font-black uppercase tracking-tight">Quick Knowledge Check</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Progress</span>
                                <span class="text-xs sm:text-sm font-black font-mono">{{ demoCompleted ? demoQuestions.length : currentDemoQuestion + 1 }}/{{ demoQuestions.length }}</span>
                            </div>
                            <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Score</span>
                                <span class="text-xs sm:text-sm font-black font-mono text-primary">{{ demoScore }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Body -->
                    <div class="p-4 sm:p-6 lg:p-12">
                        <!-- Active Question -->
                        <div v-if="!demoCompleted">
                            <div class="flex items-center gap-3 mb-4 sm:mb-6">
                                <span class="text-[9px] font-black tracking-[0.3em] text-primary font-mono">Q_{{ String(currentDemoQuestion + 1).padStart(2, '0') }}</span>
                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground px-2 py-0.5 border border-border/30 dark:border-border/20 rounded">
                                    {{ demoQuestions[currentDemoQuestion].type.replace('_', ' ') }}
                                </span>
                            </div>
                            
                            <p class="text-base sm:text-lg lg:text-xl font-black tracking-tight mb-6 sm:mb-8 max-w-2xl">
                                {{ demoQuestions[currentDemoQuestion].text }}
                            </p>

                            <!-- Options -->
                            <div class="grid gap-2.5 sm:gap-3 grid-cols-1" :class="demoQuestions[currentDemoQuestion].type === 'true_false' ? 'sm:grid-cols-2' : 'sm:grid-cols-2'">
                                <button
                                    v-for="(option, oIndex) in demoQuestions[currentDemoQuestion].options"
                                    :key="oIndex"
                                    @click="selectDemoAnswer(oIndex)"
                                    class="relative p-4 sm:p-5 border rounded-lg text-left transition-all duration-300 group/opt overflow-hidden"
                                    :class="[
                                        !demoAnswered ? 'border-border/40 dark:border-border/20 hover:border-primary/50 hover:bg-primary/5 dark:hover:bg-primary/10 cursor-pointer active:scale-[0.98] bg-muted/20 dark:bg-foreground/[0.03]' : '',
                                        demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex ? 'border-emerald-500/60 bg-emerald-500/10 dark:bg-emerald-500/15' : '',
                                        demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex ? 'border-red-500/60 bg-red-500/10 dark:bg-red-500/15' : '',
                                        demoAnswered && oIndex !== demoQuestions[currentDemoQuestion].correctIndex && oIndex !== selectedDemoAnswer ? 'opacity-40' : '',
                                    ]"
                                    :disabled="demoAnswered"
                                >
                                    <div class="flex items-center justify-between gap-3 sm:gap-4">
                                        <div class="flex items-center gap-3 sm:gap-4">
                                            <span class="text-[10px] font-black font-mono text-muted-foreground w-5 shrink-0">{{ String.fromCharCode(65 + oIndex) }}.</span>
                                            <span class="text-xs sm:text-sm font-bold">{{ option }}</span>
                                        </div>
                                        <CheckCircle2 v-if="demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-emerald-500 shrink-0" />
                                        <XCircle v-if="demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-red-500 shrink-0" />
                                    </div>
                                </button>
                            </div>

                            <!-- Feedback + Next -->
                            <Transition
                                enter-active-class="transition-all duration-500 ease-out"
                                enter-from-class="opacity-0 translate-y-4"
                                enter-to-class="opacity-100 translate-y-0"
                            >
                                <div v-if="demoAnswered" class="mt-6 sm:mt-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4 sm:gap-6">
                                    <div class="p-4 border border-border/30 dark:border-border/15 bg-muted/30 dark:bg-foreground/[0.04] max-w-lg rounded-lg">
                                        <div class="flex items-center gap-2 mb-2">
                                            <Sparkles class="h-3 w-3 text-primary" />
                                            <span class="text-[8px] font-black uppercase tracking-[0.3em] text-primary">Explanation</span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                            {{ demoQuestions[currentDemoQuestion].explanation }}
                                        </p>
                                    </div>
                                    <button
                                        @click="nextDemoQuestion"
                                        class="flex items-center justify-center sm:justify-start gap-4 bg-foreground text-background px-6 py-3.5 sm:py-4 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-primary hover:text-primary-foreground transition-all active:scale-95 shrink-0 rounded-lg"
                                    >
                                        {{ currentDemoQuestion < demoQuestions.length - 1 ? 'Next Question' : 'View Results' }}
                                        <ArrowRight class="h-4 w-4" />
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <!-- Quiz Complete -->
                        <div v-else class="flex flex-col items-center justify-center py-8 lg:py-16 text-center">
                            <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center border-2 border-primary/30 bg-primary/10 mb-6 rotate-45 rounded-xl">
                                <Trophy class="h-6 w-6 sm:h-8 sm:w-8 text-primary -rotate-45" />
                            </div>
                            <h3 class="text-2xl sm:text-3xl lg:text-4xl font-black uppercase tracking-tight mb-2">Assessment Complete</h3>
                            <p class="text-muted-foreground text-xs sm:text-sm mb-2">Demo Protocol Finalized</p>
                            
                            <div class="flex items-baseline gap-2 my-4 sm:my-6">
                                <span class="text-4xl sm:text-5xl lg:text-6xl font-black text-primary font-mono">{{ demoScore }}</span>
                                <span class="text-lg sm:text-xl font-black text-muted-foreground/40">/{{ demoQuestions.length }}</span>
                            </div>
                            
                            <p class="text-xs sm:text-sm text-muted-foreground mb-6 sm:mb-8 max-w-md px-4">
                                {{ demoScore === demoQuestions.length ? 'Perfect score! You\'re ready to dominate.' : demoScore >= 2 ? 'Solid performance. The real assessments await.' : 'Room for growth. Sign up and sharpen your skills.' }}
                            </p>

                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto px-4 sm:px-0">
                                <button @click="resetDemoQuiz" class="flex items-center justify-center gap-3 border border-border/40 dark:border-border/20 px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-muted/30 transition-all active:scale-95 rounded-lg">
                                    Retry Assessment
                                </button>
                                <Link v-if="!$page.props.auth.user" :href="register()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                                    Create Account
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                                <Link v-else :href="dashboard()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                                    Go to Dashboard
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Progress dots -->
                    <div class="border-t border-border/30 dark:border-border/15 p-4 flex items-center justify-center gap-2">
                        <div v-for="(q, qi) in demoQuestions" :key="qi" class="h-1.5 rounded-full transition-all duration-300"
                            :class="[
                                qi === currentDemoQuestion && !demoCompleted ? 'w-8 bg-primary' : 'w-1.5',
                                qi < currentDemoQuestion || demoCompleted ? 'bg-primary/50' : 'bg-muted dark:bg-foreground/15',
                            ]"
                        ></div>
                    </div>
                </div>
            </div>


            <!-- Tech Stack Carousel -->
            <div class="reveal-section mt-24 lg:mt-48 overflow-hidden relative py-12 border-y border-border/5 -mx-6 sm:mx-0">
                <div class="flex items-center gap-4 mb-12 px-6 sm:px-0">
                    <div class="h-px w-12 bg-primary"></div>
                    <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]">Core Technology Stack</h2>
                </div>
                
                <div class="flex whitespace-nowrap" ref="techCarousel">
                    <!-- Duplicate items for seamless loop -->
                    <div v-for="n in 2" :key="n" class="flex gap-12 lg:gap-24 pr-12 lg:pr-24 pl-6 sm:pl-0">
                        <div v-for="tech in techStack" :key="tech.name" class="flex items-center gap-6 group">
                            <div class="flex h-12 w-12 lg:h-16 lg:w-16 items-center justify-center border border-border/10 bg-muted/5 group-hover:border-primary/30 transition-colors">
                                <component :is="tech.icon" class="h-6 w-6 lg:h-8 lg:w-8 text-muted-foreground group-hover:text-primary transition-colors" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-base lg:text-xl font-black uppercase tracking-tight">{{ tech.name }}</span>
                                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest opacity-60">{{ tech.description }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- System Registry (Footer) -->
        <footer class="relative z-10 border-t border-border/5 bg-background/50 py-16 lg:py-24 px-6 lg:px-16 backdrop-blur-sm">
            <div class="footer-reveal mx-auto flex max-w-[1500px] flex-col lg:flex-row items-start justify-between gap-12 lg:gap-20">
                <div class="flex flex-col gap-6 lg:gap-8">
                    <div class="flex items-center gap-4 lg:gap-5">
                        <div class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></div>
                        <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.5em] text-foreground">Luav Operational Intelligence</span>
                    </div>
                    <div class="flex flex-col gap-2">
                         <p class="text-[9px] lg:text-[10px] font-bold text-muted-foreground tracking-widest uppercase">Region: Global // Cluster 01</p>
                         <p class="text-[9px] lg:text-[10px] font-medium text-muted-foreground/30 leading-snug">Designed for the next generation of assessment-driven <br class="hidden sm:block"/>learning ecosystems.</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-12 lg:gap-x-20 gap-y-10 lg:gap-y-12">
                    <div v-for="category in ['Core', 'External', 'Legal']" :key="category" class="flex flex-col gap-4 lg:gap-6">
                         <h4 class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-foreground/80">{{ category }}</h4>
                         <div class="flex flex-col gap-2 lg:gap-3">
                             <a v-for="link in ['Registry', 'Auth', 'Nodes']" :key="link" href="#" class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground/40 hover:text-primary transition-colors">
                                 {{ link }}
                             </a>
                         </div>
                    </div>
                </div>

                <div class="flex flex-col items-start lg:items-end gap-2 text-left lg:text-right w-full lg:w-auto">
                    <p class="text-[9px] lg:text-[10px] font-black text-foreground/20 uppercase tracking-[0.3em]">
                        © 2026 LUAV STRUCTURAL SYSTEMS
                    </p>
                    <div class="h-px w-24 bg-border/20 hidden lg:block"></div>
                </div>
            </div>
        </footer>

        <!-- Initial Boot Interface (Entrance) -->
        <div 
            ref="bootOverlay"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-background p-6"
        >
            <div class="scanline absolute inset-0 pointer-events-none opacity-[0.05]"></div>
            <div class="relative flex flex-col items-center gap-4">
                <!-- Boot Sequence Text -->
                <div class="flex flex-col items-center gap-2">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span v-for="(letter, i) in bootMessage.split('')" :key="i" ref="bootText" class="text-xs font-black tracking-[0.6em] uppercase opacity-0 translate-y-4">
                            {{ letter === ' ' ? '\u00A0' : letter }}
                        </span>
                    </div>
                    <div class="h-px w-32 bg-primary/20 overflow-hidden">
                        <div class="boot-progress h-full w-full bg-primary origin-left scale-x-0"></div>
                    </div>
                    <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest opacity-60 mt-1">{{ bootSubtext }}</span>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.hero-reveal {
    display: block;
}

.ambient-orb {
    background: radial-gradient(circle at 30% 30%, color-mix(in srgb, var(--color-foreground) 28%, transparent), transparent 70%);
    opacity: 0.35;
    filter: blur(6px);
}

.scan-line {
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--color-foreground) 35%, transparent), transparent);
}

.scanline {
    background: linear-gradient(
        to bottom,
        transparent 50%,
        rgba(0, 0, 0, 0.1) 51%,
        transparent 52%
    );
    background-size: 100% 4px;
    animation: scan 10s linear infinite;
}

@keyframes scan {
    from { background-position: 0 0; }
    to { background-position: 0 100%; }
}

.signal-fill {
    box-shadow: 0 0 20px color-mix(in srgb, currentColor 45%, transparent);
}

/* Custom easing override for ultra-premium feel */
.fixed {
    transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@media (max-width: 1024px) {
    .ambient-orb {
        opacity: 0.22;
    }
}

@media (max-width: 768px) {
    .ambient-orb {
        display: none;
    }

    .fixed {
        transition-duration: 0.35s;
    }
}

/* System Variable Integration */
@layer base {
    :root {
        --color-background: #ffffff;
        --color-foreground: #09090b;
        --glow-color: rgba(0, 0, 0, 0.05);
    }
    .dark {
        --color-background: #09090b;
        --color-foreground: #fafafa;
        --glow-color: rgba(255, 255, 255, 0.08);
    }
}
</style>
