<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Sparkles, ArrowRight } from 'lucide-vue-next';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    isCoarsePointer: boolean;
    prefersReducedMotion: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
}>();

const coreFeatures = [
    {
        title: 'Assessment Engine',
        description: 'Smart testing infrastructure with real-time analytics and AI-powered evaluation modules.',
        code: 'MOD_EXM_01',
        details: 'Take timed exams with multiple question types: multiple choice, true/false, identification, and AI-graded essays. Get instant feedback and track your performance across seasons.',
        stats: [{ label: 'Question Types', value: '4' }, { label: 'AI Grading', value: 'Active' }, { label: 'Auto-Score', value: 'Real-time' }]
    },
    {
        title: 'Skill Acquisition',
        description: 'Structured assignment workflows designed to track progressive learning and mastery across subjects.',
        code: 'MOD_ASN_02',
        details: 'Submit assignments with file uploads, track deadlines, and receive grades from your instructors. Stay on top of your academic goals with progress tracking.',
        stats: [{ label: 'File Upload', value: 'Secure' }, { label: 'Deadline Alerts', value: 'Live' }, { label: 'Grade Tracking', value: 'Instant' }]
    },
    {
        title: 'Gamified Learning',
        description: 'Engaging leaderboard system driven by XP, daily streaks, and competitive academic performance.',
        code: 'MOD_LDR_03',
        details: 'Compete with peers on the section-based leaderboard. Earn XP from exams, assignments, and daily streaks. Rise through the ranks and dominate your section.',
        stats: [{ label: 'XP System', value: 'Active' }, { label: 'Streak Bonus', value: 'Daily' }, { label: 'Sections', value: 'Multi' }]
    }
];

const expandedFeature = ref<number | null>(null);
const toggleFeature = (index: number) => {
    expandedFeature.value = expandedFeature.value === index ? null : index;
};

const featureBars = computed(() => {
    return coreFeatures.map((_, fIdx) => {
        const count = 24;
        return Array.from({ length: count }, (_, i) => ({
            id: i,
            height: 30 + ((Math.sin(fIdx * 1.5 + i * 0.8) + 1) / 2) * 50,
            delay: i * 0.15,
            duration: 4.5 + ((Math.cos(fIdx * 2.2 + i * 0.4) + 1) / 2) * 3.5,
            hasBit: ((Math.sin(fIdx * 3.1 + i * 1.7) + 1) / 2) > 0.65,
            bitDelay: i * 0.25
        }));
    });
});

const handleFeatureMouseMove = (e: MouseEvent) => {
    if (props.isCoarsePointer || props.prefersReducedMotion) return;

    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const xPercent = (x / rect.width - 0.5) * 2;
    const yPercent = (y / rect.height - 0.5) * 2;

    gsap.to(card, {
        rotateY: xPercent * 5,
        rotateX: -yPercent * 5,
        transformPerspective: 1000,
        duration: 0.4,
        ease: 'power2.out'
    });

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

onMounted(() => {
    nextTick(() => {
        // Entrance animation for the cards
        gsap.utils.toArray('.feature-card').forEach((card: any, i: number) => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 95%',
                },
                y: 60,
                opacity: 0,
                duration: 1.2,
                delay: i * 0.1,
                ease: 'power4.out',
                clearProps: 'all'
            });
        });

        // Continuous bar wave animation
        gsap.utils.toArray('.fragment-bar').forEach((bar: any, i: number) => {
            gsap.fromTo(bar, {
                scaleY: 0.7,
                opacity: 0.4,
                transformOrigin: 'bottom'
            }, {
                scaleY: 1.1,
                opacity: 1,
                duration: 1.5 + Math.random() * 1.5,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
                delay: (i % 24) * 0.08
            });
        });

        // Continuous bit flicker animation
        gsap.utils.toArray('.fragment-bit').forEach((bit: any, i: number) => {
            gsap.fromTo(bit, {
                opacity: 0.1
            }, {
                opacity: 0.9,
                duration: 0.8 + Math.random() * 1.2,
                repeat: -1,
                yoyo: true,
                ease: 'power1.inOut',
                delay: (i % 12) * 0.15
            });
        });

        ScrollTrigger.refresh();
    });
});
</script>

<template>
    <div class="mt-12 lg:mt-24 grid w-full lg:grid-cols-3 gap-0 border-b border-border/20 dark:border-border/10">
        <div 
            v-for="(feature, index) in coreFeatures" 
            :key="index"
            @mousemove="handleFeatureMouseMove"
            @mouseleave="resetFeatureMouse"
            class="feature-card group relative flex flex-col p-8 sm:p-12 lg:p-16 border-border/20 dark:border-border/10 transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.02] overflow-hidden cursor-pointer"
            :class="[
                { 'border-b lg:border-b-0 lg:border-r': index !== coreFeatures.length - 1 },
                expandedFeature === index ? 'bg-muted/20 dark:bg-foreground/[0.03]' : ''
            ]"
            @click="toggleFeature(index)"
        >
            <!-- Local Card Glow -->
            <div class="absolute inset-0 opacity-0 group-hover:opacity-[0.07] dark:group-hover:opacity-[0.12] transition-opacity duration-700 pointer-events-none"
                :style="{ background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--color-primary), transparent 70%)` }">
            </div>

            <div class="absolute top-8 left-8 lg:left-12 flex items-center gap-3">
                 <span class="text-[8px] font-black tracking-widest text-primary/70 leading-none group-hover:text-primary transition-colors">{{ feature.code }}</span>
                 <div class="h-px w-8 lg:w-12 bg-primary/20 group-hover:w-16 lg:group-hover:w-32 group-hover:bg-gradient-to-r group-hover:from-primary group-hover:to-transparent transition-all duration-700"></div>
            </div>

            <div class="mt-12 lg:mt-16 mb-10 lg:mb-14 relative h-16 lg:h-20 w-full flex items-end gap-1.5 overflow-hidden group/matrix">
                <div v-for="bar in featureBars[index].slice(0, isCoarsePointer ? 12 : 24)" :key="bar.id" 
                     class="fragment-bar flex-1 bg-muted-foreground/10 dark:bg-foreground/5 rounded-t-sm origin-bottom group-hover:bg-primary/20 will-change-transform"
                     :style="{ 
                         height: bar.height + '%',
                     }">
                     <div v-if="bar.hasBit" 
                          class="fragment-bit w-full h-1.5 bg-primary/30 dark:bg-primary/50"
                          :style="{ opacity: 0.5 }"></div>
                </div>

                <div class="absolute top-0 right-0 flex items-center gap-2 px-2 py-1 border border-border/20 bg-background/50 backdrop-blur-sm rounded group-hover:border-primary/30 transition-colors">
                    <div class="h-1 w-1 rounded-full bg-primary animate-ping"></div>
                    <span class="text-[7px] font-black uppercase tracking-widest text-muted-foreground group-hover:text-primary transition-colors">Fragment_Active</span>
                </div>
            </div>
            
            <div class="space-y-4 lg:space-y-6 relative z-10">
                <h3 class="text-xl lg:text-3xl font-black uppercase tracking-tight group-hover:translate-x-1 transition-transform duration-500">{{ feature.title }}</h3>
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

            <Transition
                enter-active-class="transition-all duration-700 ease-[0.2,0.8,0.2,1]"
                enter-from-class="max-h-0 opacity-0 translate-y-4"
                enter-to-class="max-h-[500px] opacity-100 translate-y-0"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="max-h-[500px] opacity-100 translate-y-0"
                leave-to-class="max-h-0 opacity-0 -translate-y-4"
            >
                <div v-if="expandedFeature === index" class="relative overflow-hidden mt-8 lg:mt-12 pt-8 z-10">
                    <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
                    
                    <p class="text-sm leading-relaxed text-muted-foreground mb-8 max-w-md bg-muted/30 dark:bg-foreground/[0.03] p-5 rounded-lg border border-border/20 dark:border-border/10">
                        <Sparkles class="w-4 h-4 text-primary mb-3 inline-block" />
                        <br />
                        {{ feature.details }}
                    </p>
                    
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-8">
                        <div v-for="stat in feature.stats" :key="stat.label" class="p-3 sm:p-4 border border-border/40 dark:border-border/20 bg-card dark:bg-background/50 backdrop-blur-sm rounded-lg shadow-sm">
                            <p class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-muted-foreground mb-1.5">{{ stat.label }}</p>
                            <p class="text-[11px] sm:text-xs font-black text-primary tracking-widest">{{ stat.value }}</p>
                        </div>
                    </div>

                    <Link v-if="auth.user" :href="dashboard()" class="inline-flex items-center gap-4 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] bg-primary text-primary-foreground px-6 py-4 hover:bg-primary/90 transition-all rounded-lg shadow-lg hover:shadow-primary/20 hover:gap-6 group/link">
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
</template>
