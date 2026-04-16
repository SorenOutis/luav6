<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { onMounted, ref, computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import gsap from 'gsap';
import { BookOpen, Video, ArrowRight, Github, LayoutDashboard, Command, Zap, Award, Target, Sun, Moon, Cpu, Activity, Zap as ZapIcon, BrainCircuit } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        totalUsers?: number;
    }>(),
    {
        canRegister: true,
        totalUsers: 0,
    },
);

const heroTitle = ref<HTMLElement | null>(null);
const heroSub = ref<HTMLElement | null>(null);
const heroCta = ref<HTMLElement | null>(null);
const featureCards = ref<HTMLElement[]>([]);
const structuralLines = ref<HTMLElement[]>([]);
const mainContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const mouseGlow = ref<HTMLElement | null>(null);
const techCarousel = ref<HTMLElement | null>(null);

// Appearance Management
const { appearance, toggleTheme } = useAppearance();

// Animated Metrics
const animUsers = useNumberAnimation(() => props.totalUsers || 0, 2, 'expo.out');
const animUptime = useNumberAnimation(() => 99, 1.5, 'power2.out');
const animLatency = useNumberAnimation(() => 5, 2.5, 'elastic.out(1, 0.3)');
const animSync = useNumberAnimation(() => 1240, 3, 'power4.out');

// Interaction Logic
const handleMagnetic = (e: MouseEvent) => {
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
    if (!mouseGlow.value || !backgroundGrid.value) return;

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

const handleFeatureMouseMove = (e: MouseEvent, index: number) => {
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
let typingTimeout: any = null;

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
    // Start typing after initial load
    setTimeout(type, 2500);

    const tl = gsap.timeline({ defaults: { ease: 'expo.out', duration: 1.4 } });


    // 1. Initial State
    gsap.set('.reveal-content', { y: '100%', opacity: 0 });
    gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
    gsap.set('.footer-reveal', { opacity: 0, y: 30 });

    // 2. Animate Structural Lines
    tl.to(structuralLines.value, {
        scaleX: 1,
        scaleY: 1,
        stagger: 0.1,
        duration: 1.2,
        ease: 'power4.inOut'
    })
    // 3. Reveal Nav
    .from('.nav-item', {
        y: -20,
        opacity: 0,
        stagger: 0.05,
        duration: 0.8
    }, "-=0.8")
    // 4. Reveal Hero with Clip-Path/Overflow
    .to('.hero-reveal .reveal-content', {
        y: '0%',
        opacity: 1,
        stagger: 0.2,
        duration: 1.5
    }, "-=1")
    // 5. Build Feature Grid with advanced stagger
    .from(featureCards.value, {
        y: 60,
        opacity: 0,
        stagger: {
            each: 0.1,
            from: "center"
        },
        duration: 1.2,
        ease: 'power4.out',
        clearProps: "all"
    }, "-=1.2")
    // 6. Reveal New Sections
    .from('.reveal-section', {
        y: 40,
        opacity: 0,
        stagger: 0.2,
        duration: 1,
        ease: 'power3.out'
    }, "-=0.8")
    // 7. Reveal Footer
    .to('.footer-reveal', {
        opacity: 1,
        y: 0,
        stagger: 0.1,
        duration: 1
    }, "-=0.8");

    // Tech Carousel Logic
    if (techCarousel.value) {
        const carousel = techCarousel.value;
        
        gsap.to(carousel, {
            xPercent: -50,
            duration: 20,
            ease: "none",
            repeat: -1
        });
    }
});

const coreFeatures = [
    {
        title: 'Assessment Engine',
        description: 'Industrial-grade testing infrastructure with real-time analytics and automated evaluation modules.',
        icon: Target,
        code: 'MOD_EXM_01'
    },
    {
        title: 'Skill Acquisition',
        description: 'Structured assignment workflows designed to track progressive growth and mastery across seasons.',
        icon: Zap,
        code: 'MOD_ASN_02'
    },
    {
        title: 'Operational Elite',
        description: 'High-fidelity leaderboard system driven by XP, streaks, and competitive intelligence metrics.',
        icon: Award,
        code: 'MOD_LDR_03'
    }
];

const systemStats = computed(() => [
    { label: 'Total Users', value: animUsers.value, unit: 'NODES', icon: Cpu },
    { label: 'Uptime', value: animUptime.value + '.99', unit: '%', icon: Activity },
    { label: 'Latency', value: animLatency.value, unit: 'MS', icon: ZapIcon },
    { label: 'Intelligence', value: (animSync.value / 100).toFixed(1) + 'k', unit: 'SYNC', icon: BrainCircuit },
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
            class="pointer-events-none fixed -left-[150px] -top-[150px] z-0 h-[300px] w-[300px] rounded-full bg-primary/5 blur-[120px] will-change-transform dark:bg-primary/10"
        ></div>

        <!-- Monolithic Grid Overlay -->
        <div ref="backgroundGrid" class="fixed inset-[-100px] z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06] will-change-transform">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>

        <!-- System Structural Constraints (Lines) -->
        <div class="fixed inset-y-0 left-6 lg:left-24 w-px bg-border/20 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed inset-y-0 right-6 lg:right-24 w-px bg-border/20 z-0 origin-bottom" ref="structuralLines"></div>
        <div class="fixed inset-x-0 top-1/4 h-px bg-border/20 z-0 hidden lg:block origin-left" ref="structuralLines"></div>
        <div class="fixed inset-x-0 bottom-1/4 h-px bg-border/20 z-0 hidden lg:block origin-right" ref="structuralLines"></div>

        <!-- Global Header -->
        <header class="relative z-20 flex w-full items-center justify-between px-6 py-6 lg:px-16 border-b border-border/5 backdrop-blur-xl transition-colors duration-500">
            <div class="nav-item flex items-center gap-3 lg:gap-4 group cursor-pointer">
                <div class="flex h-10 w-10 items-center justify-center text-foreground transition-all duration-700 group-hover:rotate-[180deg]">
                    <Command class="h-6 w-6 lg:h-7 lg:w-7" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] lg:text-xs font-black tracking-[0.4em] uppercase">LUAV Engine</span>
                    <span class="text-[7px] lg:text-[8px] font-bold text-muted-foreground uppercase mt-1 tracking-widest opacity-60">v6.4.0</span>
                </div>
            </div>

            <nav class="flex items-center gap-4 lg:gap-8">
                <!-- Theme Toggle Button - always visible -->
                <button 
                    @click="toggleTheme" 
                    class="p-2 text-muted-foreground hover:text-foreground transition-all active:scale-90"
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
                        <div class="h-1 w-1 rounded-full bg-emerald-500 animate-pulse"></div>
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
                        class="nav-item bg-foreground text-background px-4 lg:px-8 py-2 lg:py-3 text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] hover:bg-primary transition-all shadow-2xl"
                    >
                        Init
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Main Operational Interface -->
        <main class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28">
            
            <!-- Hero Monolith -->
            <div class="max-w-6xl">
                <div class="hero-reveal overflow-hidden mb-2 lg:mb-4">
                    <h1 class="reveal-content text-[12vw] sm:text-7xl lg:text-[8rem] font-black tracking-[-0.04em] leading-[0.8] uppercase flex flex-col">
                        <span>Learning Systems</span>
                        <span class="text-muted-foreground/20 italic">Intelligence</span>
                    </h1>
                </div>
                
                <div class="hero-reveal mb-10 lg:mb-16 lg:pl-2 relative">
                    <!-- Invisible Shadow Element: Reserves the maximum possible space to prevent layout shifts -->
                    <p class="max-w-3xl text-base font-medium sm:text-xl lg:text-2xl leading-relaxed tracking-tight opacity-0 pointer-events-none select-none invisible whitespace-pre-wrap">
                        Access the industrial-grade assessment engine engineered for high-fidelity performance and architectural growth in 
                        <span class="font-black uppercase tracking-widest inline-flex items-center">
                            Architectural Might.<span class="ml-1 w-1 h-[0.8em] bg-primary"></span>
                        </span> 
                    </p>
                    
                    <!-- Visible Animated Element: Positioned absolutely within the reserved space -->
                    <p class="reveal-content absolute inset-0 max-w-3xl text-base font-medium text-muted-foreground sm:text-xl lg:text-2xl leading-relaxed tracking-tight">
                        Access the industrial-grade assessment engine engineered for high-fidelity performance and architectural growth in 
                        <span class="text-foreground font-black uppercase tracking-widest inline-flex items-center">
                            {{ typedText }}<span class="ml-1 w-1 h-[0.8em] bg-primary animate-[pulse_1s_infinite]"></span>
                        </span> 
                    </p>
                </div>




                <div class="hero-reveal overflow-hidden">
                    <div class="reveal-content flex flex-col sm:flex-row gap-3 lg:gap-4">
                        <Link v-if="$page.props.auth.user" :href="dashboard()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group flex items-center justify-between gap-8 lg:gap-12 bg-primary px-8 lg:px-10 py-5 lg:py-6 text-primary-foreground transition-all hover:gap-12 lg:hover:gap-16 active:scale-95 shadow-2xl"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] lg:tracking-[0.4em]">Initialize Dashboard</span>
                            <ArrowRight class="h-5 w-5 lg:h-6 lg:w-6" />
                        </Link>
                        <Link v-else :href="login()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group flex items-center justify-between gap-8 lg:gap-12 bg-foreground px-8 lg:px-10 py-5 lg:py-6 text-background transition-all hover:gap-12 lg:hover:gap-16 active:scale-95 shadow-2xl"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] lg:tracking-[0.4em]">Authenticate System</span>
                            <ArrowRight class="h-5 w-5 lg:h-6 lg:w-6" />
                        </Link>
                        
                        <Link v-if="!$page.props.auth.user && canRegister" :href="register()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group flex items-center justify-between gap-8 lg:gap-10 border border-border px-8 lg:px-10 py-5 lg:py-6 transition-all hover:bg-muted/30 active:scale-95 text-muted-foreground"
                        >
                            <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em]">Register Account</span>
                            <LayoutDashboard class="h-4 w-4 lg:h-5 lg:w-5 opacity-40 group-hover:opacity-100 transition-opacity" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- System Metrics Ticker -->
            <div class="reveal-section mt-24 lg:mt-40 grid grid-cols-2 md:grid-cols-4 gap-8 lg:gap-20 border-y border-border/10 py-8 lg:py-12">
                <div v-for="stat in systemStats" :key="stat.label" class="flex flex-col gap-3 group">
                    <div class="flex items-center gap-3">
                        <component :is="stat.icon" class="h-4 w-4 text-primary opacity-50 group-hover:opacity-100 transition-opacity" />
                        <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">{{ stat.label }}</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl lg:text-4xl font-black tracking-tighter">{{ stat.value }}</span>
                        <span class="text-[10px] lg:text-xs font-bold text-primary tracking-widest">{{ stat.unit }}</span>
                    </div>
                </div>
            </div>

            <!-- Features Array -->
            <div class="mt-12 lg:mt-24 grid w-full lg:grid-cols-3 gap-0 border-b border-border/10">
                <div 
                    v-for="(feature, index) in coreFeatures" 
                    :key="index"
                    ref="featureCards"
                    @mousemove="handleFeatureMouseMove($event, index)"
                    @mouseleave="resetFeatureMouse"
                    class="group relative flex flex-col p-8 lg:p-20 border-border/10 transition-all hover:bg-muted/20 overflow-hidden"
                    :class="{ 'border-b lg:border-b-0 lg:border-r': index !== coreFeatures.length - 1 }"
                >
                    <!-- Local Card Glow -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(400px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--glow-color), transparent 40%)` }">
                    </div>

                    <!-- Module Indicator -->
                    <div class="absolute top-8 left-8 lg:left-20 flex items-center gap-3">
                         <span class="text-[9px] font-black tracking-widest text-primary leading-none">{{ feature.code }}</span>
                         <div class="h-px w-8 lg:w-12 bg-primary/20 group-hover:w-12 lg:group-hover:w-20 group-hover:bg-primary transition-all"></div>
                    </div>

                    <div class="mt-6 lg:mt-12 mb-8 lg:mb-14 flex h-12 w-12 lg:h-16 lg:w-16 items-center justify-center border border-foreground/5 bg-foreground/[0.02] transition-all group-hover:rotate-[15deg] group-hover:border-primary/20">
                        <component :is="feature.icon" class="h-5 w-5 lg:h-7 lg:w-7 opacity-20 group-hover:opacity-100 transition-all group-hover:text-primary duration-500" />
                    </div>
                    
                    <div class="space-y-4 lg:space-y-6">
                        <h3 class="text-xl lg:text-2xl font-black uppercase tracking-tight">{{ feature.title }}</h3>
                        <p class="text-sm lg:text-base leading-relaxed text-muted-foreground group-hover:text-foreground transition-colors duration-500 max-w-sm">
                            {{ feature.description }}
                        </p>
                    </div>

                    <div class="mt-10 lg:mt-20">
                        <a href="#" class="text-[9px] font-black uppercase tracking-[0.4em] text-muted-foreground hover:text-primary transition-all flex items-center gap-5">
                            View Specs
                            <ArrowRight class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tech Stack Carousel -->
            <div class="reveal-section mt-24 lg:mt-48 overflow-hidden relative py-12 border-y border-border/5">
                <div class="flex items-center gap-4 mb-12 px-6 lg:px-0">
                    <div class="h-px w-12 bg-primary"></div>
                    <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]">Core Technology Stack</h2>
                </div>
                
                <div class="flex whitespace-nowrap" ref="techCarousel">
                    <!-- Duplicate items for seamless loop -->
                    <div v-for="n in 2" :key="n" class="flex gap-12 lg:gap-24 pr-12 lg:pr-24">
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

    </div>
</template>

<style scoped>
.hero-reveal {
    display: block;
}

/* Custom easing override for ultra-premium feel */
.fixed {
    transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
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
