<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Command, Zap, Target, Award, LayoutDashboard } from 'lucide-vue-next';
import gsap from 'gsap';

const props = defineProps<{
    isCoarsePointer: boolean;
}>();

const techCarousel = ref<HTMLElement | null>(null);

const techStack = [
    { name: 'Laravel 11', description: 'Robust backend architecture for scale.', icon: Command },
    { name: 'Vue 3', description: 'High-performance reactive UI system.', icon: Zap },
    { name: 'Inertia.js', description: 'The modern monolith connection layer.', icon: Target },
    { name: 'GSAP', description: 'Ultra-smooth professional animations.', icon: Award },
    { name: 'Tailwind CSS', description: 'Utility-first design framework.', icon: LayoutDashboard },
    { name: 'TypeScript', description: 'Type-safe scalable development.', icon: Command },
];

onMounted(() => {
    if (techCarousel.value) {
        const anim = gsap.to(techCarousel.value, {
            xPercent: -50,
            duration: props.isCoarsePointer ? 30 : 20,
            ease: 'none',
            repeat: -1
        });

        // Optimization: Pause animation when not in view
        gsap.to(techCarousel.value, {
            scrollTrigger: {
                trigger: techCarousel.value,
                start: 'top bottom',
                end: 'bottom top',
                onToggle: self => {
                    if (self.isActive) anim.play();
                    else anim.pause();
                }
            }
        });
    }
});
</script>

<template>
    <div class="reveal-section mt-24 lg:mt-48 overflow-hidden relative py-12 border-y border-border/5 -mx-6 sm:mx-0">
        <div class="flex items-center gap-4 mb-12 px-6 sm:px-0">
            <div class="h-px w-12 bg-primary"></div>
            <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]" data-scramble>Core Technology Stack</h2>
        </div>
        
        <div class="flex flex-nowrap" ref="techCarousel">
            <div v-for="n in 2" :key="n" class="flex flex-nowrap">
                <div v-for="tech in techStack" :key="tech.name + n" class="flex items-center gap-6 group pr-12 lg:pr-24">
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
</template>
