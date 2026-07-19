<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ChevronDown, Sparkles, ArrowRight } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    isCoarsePointer: boolean;
    prefersReducedMotion: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
}>();

const featureCardsRef = ref<HTMLElement | null>(null);
let gsapCtx: gsap.Context | null = null;

const coreFeatures = [
    {
        title: 'Assessment Intelligence',
        description:
            'Structured exams and classroom checks powered by AI-assisted scoring and clear performance analysis.',
        code: 'MOD_EXM_01',
        details:
            'Run timed assessments with multiple question types, instant checking, and rubric-aware review flows that help teachers and learners understand every result.',
        stats: [
            { label: 'Question Types', value: 'Multi' },
            { label: 'AI Support', value: 'Active' },
            { label: 'Score Flow', value: 'Real-time' },
        ],
    },
    {
        title: 'Feedback Intelligence',
        description:
            'Turn submissions into explainable feedback so learners know what was strong, weak, and next to improve.',
        code: 'MOD_ASN_02',
        details:
            'Support assignments, rubric checks, and review traces with feedback that is easier to inspect, discuss, and use for classroom improvement.',
        stats: [
            { label: 'Rubric View', value: 'Visible' },
            { label: 'Feedback', value: 'Traceable' },
            { label: 'Review Flow', value: 'Guided' },
        ],
    },
    {
        title: 'Progress Intelligence',
        description:
            'Track learner growth over time with mastery signals, activity history, and performance trends.',
        code: 'MOD_LDR_03',
        details:
            'Monitor how students improve across assessments, identify weak areas earlier, and surface the next actions that support stronger learning outcomes.',
        stats: [
            { label: 'Mastery', value: 'Tracked' },
            { label: 'Insights', value: 'Live' },
            { label: 'History', value: 'Continuous' },
        ],
    },
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
            hasBit: (Math.sin(fIdx * 3.1 + i * 1.7) + 1) / 2 > 0.65,
            bitDelay: i * 0.25,
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
        ease: 'power2.out',
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
        ease: 'power4.out',
    });
};

onMounted(() => {
    nextTick(() => {
        // ─── Scroll-triggered stagger entrance for feature cards ───
        gsapCtx = gsap.context(() => {
            const cards = featureCardsRef.value?.querySelectorAll('.feature-card');
            if (cards?.length) {
                gsap.fromTo(cards,
                    { y: 80, opacity: 0, scale: 0.95 },
                    {
                        y: 0,
                        opacity: 1,
                        scale: 1,
                        duration: 1,
                        stagger: 0.2,
                        ease: 'expo.out',
                        scrollTrigger: {
                            trigger: featureCardsRef.value,
                            start: 'top 80%',
                            toggleActions: 'play none none none',
                        },
                    },
                );
            }

            // Continuous bar wave animation
            gsap.utils.toArray('.fragment-bar').forEach((bar: any, i: number) => {
                gsap.fromTo(
                    bar,
                    {
                        scaleY: 0.7,
                        opacity: 0.4,
                        transformOrigin: 'bottom',
                    },
                    {
                        scaleY: 1.1,
                        opacity: 1,
                        duration: 1.5 + Math.random() * 1.5,
                        repeat: -1,
                        yoyo: true,
                        ease: 'sine.inOut',
                        delay: (i % 24) * 0.08,
                    },
                );
            });

            // Continuous bit flicker animation
            gsap.utils.toArray('.fragment-bit').forEach((bit: any, i: number) => {
                gsap.fromTo(
                    bit,
                    {
                        opacity: 0.1,
                    },
                    {
                        opacity: 0.9,
                        duration: 0.8 + Math.random() * 1.2,
                        repeat: -1,
                        yoyo: true,
                        ease: 'power1.inOut',
                        delay: (i % 12) * 0.15,
                    },
                );
            });
        }, featureCardsRef.value);

        ScrollTrigger.refresh();
    });
});

onUnmounted(() => {
    gsapCtx?.revert();
});
</script>

<template>
    <div
        ref="featureCardsRef"
        class="mt-12 grid w-full gap-0 border-b border-border/20 lg:mt-24 lg:grid-cols-3 dark:border-border/10"
    >
        <Motion
            v-for="(feature, index) in coreFeatures"
            :key="index"
            :initial="{ opacity: 0, y: 50 }"
            :in-view="{ opacity: 1, y: 0 }"
            :in-view-options="{ once: true, margin: '-100px' }"
            :transition="{
                duration: 0.8,
                delay: index * 0.15,
                ease: [0.16, 1, 0.3, 1],
            }"
            @mousemove="handleFeatureMouseMove"
            @mouseleave="resetFeatureMouse"
            class="feature-card group relative flex cursor-pointer flex-col overflow-hidden border-border/20 p-8 transition-all hover:bg-muted/30 sm:p-12 lg:p-16 dark:border-border/10 dark:hover:bg-foreground/[0.02]"
            :class="[
                {
                    'border-b lg:border-r lg:border-b-0':
                        index !== coreFeatures.length - 1,
                },
                expandedFeature === index
                    ? 'bg-muted/20 dark:bg-foreground/[0.03]'
                    : '',
            ]"
            @click="toggleFeature(index)"
        >
            <!-- Local Card Glow -->
            <div
                class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-700 group-hover:opacity-[0.07] dark:group-hover:opacity-[0.12]"
                :style="{
                    background: `radial-gradient(600px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--color-primary), transparent 70%)`,
                }"
            ></div>

            <div
                class="absolute top-8 left-8 flex items-center gap-3 lg:left-12"
            >
                <span
                    class="text-[8px] leading-none font-black tracking-widest text-primary/70 transition-colors group-hover:text-primary"
                    >{{ feature.code }}</span
                >
                <div
                    class="h-px w-8 bg-primary/20 transition-all duration-700 group-hover:w-16 group-hover:bg-gradient-to-r group-hover:from-primary group-hover:to-transparent lg:w-12 lg:group-hover:w-32"
                ></div>
            </div>

            <div
                class="group/matrix relative mt-12 mb-10 flex h-16 w-full items-end gap-1.5 overflow-hidden lg:mt-16 lg:mb-14 lg:h-20"
            >
                <div
                    v-for="bar in featureBars[index].slice(
                        0,
                        isCoarsePointer ? 12 : 24,
                    )"
                    :key="bar.id"
                    class="fragment-bar flex-1 origin-bottom rounded-t-sm bg-muted-foreground/10 will-change-transform group-hover:bg-primary/20 dark:bg-foreground/5"
                    :style="{
                        height: bar.height + '%',
                    }"
                >
                    <div
                        v-if="bar.hasBit"
                        class="fragment-bit h-1.5 w-full bg-primary/30 dark:bg-primary/50"
                        :style="{ opacity: 0.5 }"
                    ></div>
                </div>

                <div
                    class="absolute top-0 right-0 flex items-center gap-2 rounded border border-border/20 bg-background/50 px-2 py-1 backdrop-blur-sm transition-colors group-hover:border-primary/30"
                >
                    <div
                        class="h-1 w-1 animate-ping rounded-full bg-primary"
                    ></div>
                    <span
                        class="text-[7px] font-black tracking-widest text-muted-foreground uppercase transition-colors group-hover:text-primary"
                        >Fragment_Active</span
                    >
                </div>
            </div>

            <div class="relative z-10 space-y-4 lg:space-y-6">
                <h3
                    class="text-xl font-black tracking-tight uppercase transition-transform duration-500 group-hover:translate-x-1 lg:text-3xl"
                >
                    {{ feature.title }}
                </h3>
                <p
                    class="max-w-sm text-sm leading-relaxed text-muted-foreground transition-colors duration-500 group-hover:text-foreground/90 lg:text-base"
                >
                    {{ feature.description }}
                </p>
            </div>

            <div class="relative z-10 mt-10 lg:mt-16">
                <button
                    class="group/btn flex items-center gap-4 text-[10px] font-black tracking-[0.3em] text-muted-foreground uppercase transition-all hover:text-primary lg:tracking-[0.4em]"
                >
                    {{
                        expandedFeature === index
                            ? 'Close Details'
                            : 'View Details'
                    }}
                    <ChevronDown
                        class="h-4 w-4 transition-transform duration-500 group-hover/btn:translate-y-0.5"
                        :class="{
                            'rotate-180 group-hover/btn:-translate-y-0.5':
                                expandedFeature === index,
                        }"
                    />
                </button>
            </div>

            <Motion
                :animate="{
                    height: expandedFeature === index ? 'auto' : 0,
                    opacity: expandedFeature === index ? 1 : 0,
                }"
                :initial="{ height: 0, opacity: 0 }"
                :transition="{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }"
                class="overflow-hidden"
            >
                <div class="relative z-10 mt-8 overflow-hidden pt-8 lg:mt-12">
                    <div
                        class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"
                    ></div>

                    <p
                        class="mb-8 max-w-md rounded-lg border border-border/20 bg-muted/30 p-5 text-sm leading-relaxed text-muted-foreground dark:border-border/10 dark:bg-foreground/[0.03]"
                    >
                        <Sparkles
                            class="mb-3 inline-block h-4 w-4 text-primary"
                        />
                        <br />
                        {{ feature.details }}
                    </p>

                    <div class="mb-8 grid grid-cols-3 gap-2 sm:gap-3">
                        <div
                            v-for="stat in feature.stats"
                            :key="stat.label"
                            class="rounded-lg border border-border/40 bg-card p-3 shadow-sm backdrop-blur-sm sm:p-4 dark:border-border/20 dark:bg-background/50"
                        >
                            <p
                                class="mb-1.5 text-[7px] font-black tracking-[0.15em] text-muted-foreground uppercase sm:text-[8px] sm:tracking-[0.2em]"
                            >
                                {{ stat.label }}
                            </p>
                            <p
                                class="text-[11px] font-black tracking-widest text-primary sm:text-xs"
                            >
                                {{ stat.value }}
                            </p>
                        </div>
                    </div>

                    <Link
                        v-if="auth.user"
                        :href="dashboard()"
                        class="group/link inline-flex items-center gap-4 rounded-lg bg-primary px-6 py-4 text-[9px] font-black tracking-[0.3em] text-primary-foreground uppercase shadow-lg transition-all hover:gap-6 hover:bg-primary/90 hover:shadow-primary/20 sm:text-[10px]"
                    >
                        Open Module
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="group/link inline-flex items-center gap-4 rounded-lg bg-foreground px-6 py-4 text-[9px] font-black tracking-[0.3em] text-background uppercase shadow-lg transition-all hover:gap-6 hover:bg-primary hover:text-primary-foreground hover:shadow-primary/20 sm:text-[10px]"
                    >
                        Login to Continue
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </Motion>
        </Motion>
    </div>
</template>
