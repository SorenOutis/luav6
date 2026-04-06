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
    <div class="relative min-h-svh flex flex-col items-center justify-center bg-background font-sans text-foreground selection:bg-primary/20 overflow-hidden perspective-[1000px]">
        
        <!-- Structural Grid Background -->
        <div class="fixed inset-0 z-0 pointer-events-none opacity-[0.03] dark:opacity-[0.06] bg-[radial-gradient(circle_at_50%_50%,rgba(var(--primary),0.05),transparent_70%)]">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>

        <!-- Animated CRT Scanline Effect -->
        <div class="scanline fixed inset-0 z-10 pointer-events-none opacity-[0.03] dark:opacity-[0.07]"></div>

        <!-- Vertical & Horizontal Structural Lines -->
        <div class="fixed left-1/2 -translate-x-1/2 top-0 bottom-0 w-px bg-primary/10 z-0 origin-top shadow-[0_0_15px_rgba(var(--primary),0.1)]" ref="structuralLines"></div>
        <div class="fixed left-0 right-0 top-1/2 -translate-y-1/2 h-px bg-primary/10 z-0 origin-left shadow-[0_0_15px_rgba(var(--primary),0.1)]" ref="structuralLines"></div>

        <main class="relative z-20 w-full max-w-md px-10 py-12" ref="terminalRef">

            <div class="flex flex-col gap-12">

                <!-- Branding Header -->
                <div class="flex flex-col items-center gap-8 auth-reveal">
                    <Link :href="home()" class="group flex flex-col items-center gap-4">
                        <div class="relative">
                            <div class="absolute inset-0 bg-primary/20 blur-xl rounded-full scale-0 group-hover:scale-150 transition-transform duration-700"></div>
                            <div class="relative flex h-14 w-14 items-center justify-center text-foreground transition-all duration-700 group-hover:rotate-[90deg]">
                                <Command class="h-10 w-10" />
                            </div>
                        </div>


                        <div class="flex flex-col items-center leading-none mt-2">
                             <span class="text-[10px] font-black uppercase tracking-[0.6em] text-foreground/50">Security Protocol</span>
                             <span class="text-[11px] font-black text-primary uppercase mt-1 tracking-widest leading-none">NODE_ACCESS_064</span>
                        </div>
                    </Link>

                    <div class="space-y-3 text-center">
                        <h1 class="text-3xl font-black uppercase tracking-tighter text-foreground leading-tight">{{ title }}</h1>
                        <p class="text-center text-[10px] font-bold uppercase tracking-[0.3em] text-muted-foreground/40 max-w-[240px] mx-auto">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="auth-reveal">
                    <div class="relative bg-background/40 border border-border/20 backdrop-blur-3xl shadow-[0_30px_60px_-12px_rgba(0,0,0,0.5)]">
                         <!-- Inner technical border -->
                         <div class="absolute inset-px border border-border/5 pointer-events-none"></div>
                         <div class="p-8 md:p-12">
                             <slot />
                         </div>
                    </div>
                </div>

                <!-- Technical Footer Decoration -->
                <div class="flex items-center justify-center gap-6 opacity-30 auth-reveal">
                    <div class="h-px w-16 bg-gradient-to-r from-transparent to-border"></div>
                    <Terminal class="w-4 h-4 text-primary" />
                    <div class="h-px w-16 bg-gradient-to-l from-transparent to-border"></div>
                </div>
            </div>
        </main>

        <!-- Environment Status -->
        <div class="fixed bottom-10 left-10 hidden lg:flex items-center gap-4 opacity-40 auth-reveal">
            <div class="relative">
                <div class="absolute inset-0 bg-emerald-500/30 blur-md rounded-full animate-ping"></div>
                <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-lg"></div>
            </div>
            <div class="flex flex-col gap-1">
                <span class="text-[8px] font-black uppercase tracking-[0.5em] text-foreground">Secure Session ACTIVE</span>
                <span class="text-[7px] font-bold uppercase text-muted-foreground/50 tracking-widest">AES-256-GCM / Cluster_Primary</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Structural Enhancements */
:deep(input), :deep(button) {
    border-radius: 0 !important;
}

:deep(input) {
    background: rgba(var(--background), 0.05);
    border-color: rgba(var(--border), 0.1);
    font-weight: 600;
}

/* CRT Scanline Animation */
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

@keyframes pulse-glow {
    0%, 100% { opacity: 0.6; filter: blur(0px); }
    50% { opacity: 1; filter: blur(1px); }
}

.animate-pulse-glow {
    animation: pulse-glow 3s infinite ease-in-out;
}

/* Structural Line Transitions */
.fixed {
    transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
}
</style>

