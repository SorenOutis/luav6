<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import gsap from 'gsap';
import { Terminal, Command, Cpu } from 'lucide-vue-next';
import { useLoader } from '@/composables/useLoader';

const props = defineProps<{
    show: boolean;
}>();

const { pendingHide, hide } = useLoader();

const loaderContainer = ref<HTMLElement | null>(null);
const mainText = ref<HTMLElement | null>(null);
const structuralLines = ref<HTMLElement[]>([]);
const progress = ref(0);
const isMounted = ref(false);

// Two gates that must BOTH be true before exit plays
let progressDone = false;
let serverDone = false;

const tryExit = () => {
    if (progressDone && serverDone) {
        console.log('[GlobalLoader] Both gates open — starting exit');
        startExit();
    }
};

onMounted(() => {
    isMounted.value = true;
    gsap.set(loaderContainer.value, { y: '-100%', display: 'none' });

    if (props.show) {
        startEntrance();
    }
});

watch(() => props.show, (newVal) => {
    console.log('[GlobalLoader] Prop "show" changed to:', newVal);
    if (newVal) {
        startEntrance();
    }
    // Exit is now only triggered via the dual-gate (pendingHide + progress)
});

const startEntrance = () => {
    console.log('[GlobalLoader] Starting Entrance Animation');

    // Reset gates
    progressDone = false;
    serverDone = false;

    const tl = gsap.timeline({ defaults: { ease: 'expo.out', duration: 1.2 } });

    gsap.set(loaderContainer.value, { display: 'flex', y: '0%', opacity: 1 });
    gsap.set('.loader-reveal', { y: 20, opacity: 0 });
    gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
    gsap.set(mainText.value, { clipPath: 'inset(100% 0 0 0)' });
    progress.value = 0;

    tl.to(structuralLines.value, {
        scaleX: 1,
        scaleY: 1,
        stagger: 0.1,
        duration: 0.8,
        ease: 'power4.inOut'
    })
    .to(mainText.value, {
        clipPath: 'inset(0% 0 0 0)',
        duration: 1.5,
        ease: 'expo.inOut'
    }, '-=0.4')
    .to('.loader-reveal', {
        y: 0,
        opacity: 1,
        stagger: 0.1,
        duration: 1
    }, '-=1');

    // Progress always runs the full journey: 0 → 100% over ~2.8s
    // Uses a slight ease so it feels organic (fast start, slows near end)
    gsap.to(progress, {
        value: 100,
        duration: 2.8,
        ease: 'power1.inOut',
        onUpdate: () => {
            progress.value = Math.floor(progress.value);
        },
        onComplete: () => {
            console.log('[GlobalLoader] Progress reached 100%');
            progressDone = true;
            tryExit();
        }
    });
};

const startExit = () => {
    console.log('[GlobalLoader] Starting Exit Animation');
    gsap.to(loaderContainer.value, {
        y: '-100%',
        duration: 1.2,
        ease: 'expo.inOut',
        delay: 0.4, // Let user see 100% for a brief moment
        onComplete: () => {
            console.log('[GlobalLoader] Exit Animation Complete');
            gsap.set(loaderContainer.value, { display: 'none' });
            hide();
        }
    });
};

// Gate 2: server signals it's done
watch(pendingHide, (isPending) => {
    if (!isPending) return;
    console.log('[GlobalLoader] Server done — gate 2 open');
    serverDone = true;
    tryExit();
});
</script>

<template>
    <div 
        ref="loaderContainer" 
        class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-background font-sans text-foreground selection:bg-primary/20 overflow-hidden"
        style="display: none;"
    >
        <!-- Structural Grid Background -->
        <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06]">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 80px 80px;"></div>
        </div>

        <!-- Structural Frame Lines -->
        <div class="fixed left-1/2 -translate-x-1/2 top-0 bottom-0 w-px bg-border/20 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed left-0 right-0 top-1/2 -translate-y-1/2 h-px bg-border/20 z-0 origin-left" ref="structuralLines"></div>

        <main class="relative z-10 flex flex-col items-center gap-12 px-6">
            <!-- Monolithic Branding -->
            <div class="flex flex-col items-center gap-4">
                <div class="h-14 w-14 flex items-center justify-center bg-primary text-primary-foreground loader-reveal shadow-2xl">
                    <Cpu class="h-8 w-8 animate-pulse" />
                </div>
                
                <div class="overflow-hidden flex flex-col items-center">
                    <h1 
                        ref="mainText"
                        class="text-[12vw] md:text-8xl lg:text-[10rem] font-black tracking-[-0.04em] leading-none uppercase text-center"
                    >
                        KOAMISHIN.ORG
                    </h1>
                </div>
            </div>

            <!-- Initialization Status -->
            <div class="flex flex-col items-center gap-6 w-full max-w-xs">
                <div class="flex items-center justify-between w-full text-[9px] font-black tracking-[0.4em] uppercase text-muted-foreground/60 loader-reveal">
                    <span>Initializing Engine</span>
                    <span>{{ progress }}%</span>
                </div>
                
                <div class="h-px w-full bg-border/20 overflow-hidden loader-reveal">
                    <div 
                        class="h-full bg-primary transition-all duration-300"
                        :style="{ width: `${progress}%` }"
                    ></div>
                </div>

                <div class="flex items-center gap-3 opacity-30 loader-reveal">
                    <div class="h-1 w-1 rounded-full bg-primary animate-pulse"></div>
                    <span class="text-[8px] font-black uppercase tracking-[0.5em] animate-pulse">Establishing Node Connectivity...</span>
                </div>
            </div>
        </main>

        <!-- Environment Metadata Footer -->
        <div class="fixed bottom-12 left-1/2 -translate-x-1/2 flex items-center gap-8 opacity-20 text-[9px] font-bold tracking-[0.3em] uppercase loader-reveal">
            <span class="flex items-center gap-2"><Terminal class="w-3 h-3" /> System_Root</span>
            <span class="flex items-center gap-2"><Command class="w-3 h-3" /> Core_v6.4</span>
        </div>
    </div>
</template>

<style scoped>
.fixed {
    will-change: transform, clip-path, opacity;
}
</style>
