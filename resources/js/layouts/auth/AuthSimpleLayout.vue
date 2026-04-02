<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { onMounted, ref } from 'vue';
import gsap from 'gsap';
import { Command, Terminal } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();

const structuralLines = ref<HTMLElement[]>([]);
const terminalRef = ref<HTMLElement | null>(null);

onMounted(() => {
    const tl = gsap.timeline({ defaults: { ease: 'expo.out', duration: 1.2 } });

    // 1. Initial State
    gsap.set('.auth-reveal', { y: 20, opacity: 0 });
    gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });

    // 2. Animate Structural Lines & Terminal Construction
    tl.to(structuralLines.value, {
        scaleX: 1,
        scaleY: 1,
        stagger: 0.1,
        duration: 1,
        ease: 'power4.inOut'
    })
    .from(terminalRef.value, {
        scale: 0.98,
        opacity: 0,
        duration: 0.8
    }, "-=0.5")
    .to('.auth-reveal', {
        y: 0,
        opacity: 1,
        stagger: 0.1,
        duration: 1
    }, "-=0.4");
});
</script>

<template>
    <div class="relative min-h-svh flex flex-col items-center justify-center bg-background font-sans text-foreground selection:bg-primary/20 overflow-hidden">
        
        <!-- Structural Grid Background -->
        <div class="fixed inset-0 z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06]">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>

        <!-- Vertical & Horizontal Structural Lines -->
        <div class="fixed left-1/2 -translate-x-1/2 top-0 bottom-0 w-px bg-border/10 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed left-0 right-0 top-1/2 -translate-y-1/2 h-px bg-border/10 z-0 origin-left" ref="structuralLines"></div>

        <!-- Corner Accents for Central Box -->
        <div class="absolute inset-0 z-0 pointer-events-none flex items-center justify-center p-6 md:p-10">
            <div class="relative w-full max-w-md h-[500px] border border-border/5">
                <div class="absolute -top-px -left-px w-8 h-8 border-t-2 border-l-2 border-primary/40"></div>
                <div class="absolute -top-px -right-px w-8 h-8 border-t-2 border-r-2 border-primary/40"></div>
                <div class="absolute -bottom-px -left-px w-8 h-8 border-b-2 border-l-2 border-primary/40"></div>
                <div class="absolute -bottom-px -right-px w-8 h-8 border-b-2 border-r-2 border-primary/40"></div>
            </div>
        </div>

        <main class="relative z-10 w-full max-w-sm px-6 py-10" ref="terminalRef">
            <div class="flex flex-col gap-10">
                <!-- Branding Header -->
                <div class="flex flex-col items-center gap-6">
                    <Link :href="home()" class="group flex flex-col items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-none bg-primary text-primary-foreground transition-all duration-700 group-hover:rotate-[90deg] shadow-2xl">
                            <Command class="h-6 w-6" />
                        </div>
                        <div class="flex flex-col items-center leading-none mt-2">
                             <span class="text-[10px] font-black tracking-[0.5em] uppercase text-foreground/40">System Auth Node</span>
                             <span class="text-[9px] font-bold text-primary uppercase mt-1 tracking-widest leading-none">AUTH_NODE_064</span>
                        </div>
                    </Link>

                    <div class="space-y-2 text-center auth-reveal">
                        <h1 class="text-2xl font-black uppercase tracking-tight text-foreground">{{ title }}</h1>
                        <p class="text-center text-xs font-medium uppercase tracking-[0.15em] text-muted-foreground opacity-60">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="auth-reveal">
                    <div class="relative bg-background/50 border border-border/10 p-1 backdrop-blur-xl shadow-2xl">
                         <div class="border border-border/5 p-8 md:p-10">
                             <slot />
                         </div>
                    </div>
                </div>

                <!-- Technical Footer Decoration -->
                <div class="flex items-center justify-center gap-4 opacity-20 auth-reveal">
                    <div class="h-px w-12 bg-border"></div>
                    <Terminal class="w-4 h-4" />
                    <div class="h-px w-12 bg-border"></div>
                </div>
            </div>
        </main>

        <!-- Environment Status -->
        <div class="fixed bottom-8 left-8 hidden lg:flex items-center gap-3 opacity-30">
            <div class="w-1 h-1 rounded-full bg-primary animate-pulse"></div>
            <span class="text-[8px] font-black uppercase tracking-[0.4em]">Encrypted Session // AES-256</span>
        </div>
    </div>
</template>

<style scoped>
/* Ensure sharp edges match the monolithic theme */
:deep(input), :deep(button) {
    border-radius: 0 !important;
}

/* Structural Line Transitions */
.fixed {
    transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1);
}
</style>
