<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import gsap from 'gsap';

const archContainer = ref<HTMLElement | null>(null);

const archStack = [
    { title: 'Intelligence Layer', desc: 'Neural processing & AI evaluation modules.', color: 'primary' },
    { title: 'Application Logic', desc: 'Secure exam orchestration & routing.', color: 'muted' },
    { title: 'Data Persistence', desc: 'High-fidelity academic records & analytics.', color: 'muted' },
    { title: 'Core Infrastructure', desc: 'Scalable cloud-native node environment.', color: 'primary' },
];

onMounted(() => {
    if (!archContainer.value) return;

    gsap.set('.arch-stack-wrapper', {
        rotationX: 55,
        rotationZ: -35,
        y: 10
    });
    
    gsap.set('.arch-layer-card', {
        z: (i) => i * 30,
        opacity: 0.7
    });

    const tl = gsap.timeline({
        scrollTrigger: {
            trigger: archContainer.value,
            start: 'top 80%',
            end: 'bottom 20%',
            scrub: 1,
        }
    });

    tl.to('.arch-stack-wrapper', {
        rotationZ: -45,
        y: -40,
        duration: 1,
    })
    .to('.arch-layer-card', {
        z: (i) => i * 80,
        opacity: (i) => 0.5 + (i * 0.15),
        stagger: 0,
        duration: 1,
    }, 0);

    gsap.to('.arch-stack-idle', {
        y: '+=25',
        x: '+=12',
        rotationZ: '+=3',
        duration: 4.5,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

    gsap.to('.arch-card-content', {
        y: () => (Math.random() - 0.5) * 20,
        x: () => (Math.random() - 0.5) * 10,
        rotationZ: () => (Math.random() - 0.5) * 2,
        duration: () => 3 + Math.random() * 2,
        repeat: -1,
        repeatRefresh: true,
        yoyo: true,
        ease: 'sine.inOut',
        stagger: {
            each: 0.4,
            from: 'random'
        }
    });
});
</script>

<template>
    <section ref="archContainer" class="reveal-section mt-32 lg:mt-56 py-20 relative overflow-hidden">
        <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
            <div class="relative w-full lg:w-1/2 flex justify-center perspective-[2000px]">
                <div class="arch-stack-wrapper relative w-64 h-64 sm:w-80 sm:h-80 preserve-3d">
                    <div class="arch-stack-idle relative w-full h-full preserve-3d">
                        <div v-for="(layer, i) in [...archStack].reverse()" :key="'layer-'+i"
                             class="arch-layer-card absolute inset-0 border border-primary/30 bg-background/90 dark:bg-[#050507]/90 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.2)] dark:shadow-[0_0_30px_rgba(0,0,0,0.5)] transition-colors overflow-hidden">
                            <div class="arch-card-content w-full h-full flex flex-col items-center justify-center p-6 text-center">
                                <div class="absolute top-4 left-4 text-[10px] font-black tracking-widest text-primary/60">0{{ i + 1 }}</div>
                                <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest mb-2 text-foreground">{{ layer.title }}</h4>
                                <div class="h-px w-8 bg-primary/40 mb-2"></div>
                                <p class="text-[10px] text-muted-foreground leading-tight px-4 opacity-80">{{ layer.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-1/2 space-y-8 lg:space-y-12">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="h-px w-12 bg-primary"></div>
                        <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em] text-primary" data-scramble>System Schematic</span>
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-black uppercase tracking-tight leading-none">Multidimensional <br /> Architecture</h2>
                </div>
                
                <div class="grid gap-6">
                    <div v-for="(layer, i) in archStack" :key="'text-'+i" class="flex gap-6 group">
                        <span class="text-xl font-black text-primary/20 group-hover:text-primary/60 transition-colors tabular-nums">0{{ archStack.length - i }}</span>
                        <div>
                            <h5 class="text-sm font-black uppercase tracking-widest mb-1">{{ layer.title }}</h5>
                            <p class="text-xs text-muted-foreground leading-relaxed">{{ layer.desc }}</p>
                        </div>
                    </div>
                </div>

                <div class="pt-8">
                    <Link href="/register" class="group inline-flex items-center gap-6 text-[10px] font-black uppercase tracking-[0.4em] border border-border px-8 py-5 hover:bg-foreground hover:text-background transition-all">
                        Inspect Ecosystem
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
