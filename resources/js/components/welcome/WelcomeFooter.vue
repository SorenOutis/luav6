<script setup lang="ts">
import { onMounted } from 'vue';
import gsap from 'gsap';

onMounted(() => {
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
});
</script>

<template>
    <footer class="relative z-10 border-t border-border/5 bg-background/50 py-16 lg:py-24 px-6 lg:px-16 backdrop-blur-sm">
        <div class="footer-reveal mx-auto flex max-w-[1500px] flex-col lg:flex-row items-start justify-between gap-12 lg:gap-20">
            <div class="flex flex-col gap-6 lg:gap-8">
                <div class="flex items-center gap-4 lg:gap-5">
                    <div class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></div>
                    <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.5em] text-foreground">Luav Academic Engine</span>
                </div>
                <div class="flex flex-col gap-2">
                     <p class="text-[9px] lg:text-[10px] font-bold text-muted-foreground tracking-widest uppercase">Deploy: Global // Campus 01</p>
                     <p class="text-[9px] lg:text-[10px] font-medium text-muted-foreground/30 leading-snug">Designed for the next generation of assessment-driven <br class="hidden sm:block"/>learning ecosystems.</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-12 lg:gap-x-20 gap-y-10 lg:gap-y-12">
                <div v-for="category in ['Platform', 'Support', 'Legal']" :key="category" class="flex flex-col gap-4 lg:gap-6">
                     <h4 class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-foreground/80">{{ category }}</h4>
                     <div class="flex flex-col gap-2 lg:gap-3">
                         <a v-for="link in ['Directory', 'Policies', 'Privacy']" :key="link" href="#" class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground/40 hover:text-primary transition-colors">
                             {{ link }}
                         </a>
                     </div>
                </div>
            </div>

            <div class="flex flex-col items-start lg:items-end gap-2 text-left lg:text-right w-full lg:w-auto">
                <p class="text-[9px] lg:text-[10px] font-black text-foreground/20 uppercase tracking-[0.3em]">
                    ALL RIGHTS RESERVED KOAMISHIN 2026
                </p>
                <div class="h-px w-24 bg-border/20 hidden lg:block"></div>
            </div>
        </div>
    </footer>
</template>
