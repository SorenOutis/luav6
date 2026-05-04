<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Command, ArrowUpRight } from 'lucide-vue-next';
import { home } from '@/routes';
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import gsap from 'gsap';

// Layout doesn't need title/description props anymore since pages provide them


// Refs
const leftPanel = ref<HTMLElement | null>(null);
const formPanel = ref<HTMLElement | null>(null);
const brandBlock = ref<HTMLElement | null>(null);
const orbRefs = ref<HTMLElement[]>([]);
const gridOverlay = ref<HTMLElement | null>(null);

// Rotating taglines
const taglines = [
    'Clear Feedback.',
    'Smarter Assessment.',
    'Learning Insight.',
    'Visible Progress.',
    'Guided Growth.',
];
const currentTagline = ref(0);
let taglineInterval: ReturnType<typeof setInterval> | null = null;

// Ambient orb configurations
const orbs = [
    { size: 'w-72 h-72', position: '-left-20 -top-20', color: 'bg-indigo-500/[0.07] dark:bg-indigo-400/[0.12]', delay: 0 },
    { size: 'w-96 h-96', position: '-right-32 top-1/4', color: 'bg-violet-500/[0.05] dark:bg-violet-400/[0.08]', delay: 0.3 },
    { size: 'w-64 h-64', position: 'left-1/4 -bottom-16', color: 'bg-cyan-500/[0.06] dark:bg-cyan-400/[0.1]', delay: 0.6 },
    { size: 'w-48 h-48', position: 'right-1/3 top-12', color: 'bg-fuchsia-500/[0.04] dark:bg-fuchsia-400/[0.07]', delay: 0.9 },
];

// Current time display
const currentTime = ref('');
let clockInterval: ReturnType<typeof setInterval> | null = null;

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
};

let gsapCtx: gsap.Context | null = null;

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    // Tagline rotation
    taglineInterval = setInterval(() => {
        currentTagline.value = (currentTagline.value + 1) % taglines.length;
    }, 3000);

    gsapCtx = gsap.context(() => {
        const tl = gsap.timeline({ defaults: { ease: 'expo.out', duration: 1.2 } });

        // Initial states
        gsap.set('.auth-cinematic-reveal', { y: 24, opacity: 0 });
        gsap.set('.form-reveal', { x: 30, opacity: 0 });
        gsap.set(orbRefs.value, { scale: 0, opacity: 0 });

        // 1. Left panel gradient reveal
        if (leftPanel.value) {
            tl.from(leftPanel.value, {
                opacity: 0,
                duration: 0.8,
                ease: 'power2.out',
            });
        }

        // 2. Grid overlay fades in
        if (gridOverlay.value) {
            tl.from(gridOverlay.value, {
                opacity: 0,
                duration: 1,
            }, '-=0.5');
        }

        // 3. Orbs float into position
        tl.to(orbRefs.value, {
            scale: 1,
            opacity: 1,
            stagger: 0.15,
            duration: 1.4,
            ease: 'back.out(1.2)',
        }, '-=0.8');

        // 4. Brand block reveals
        tl.to('.auth-cinematic-reveal', {
            y: 0,
            opacity: 1,
            stagger: 0.08,
            duration: 1,
        }, '-=1');

        // 5. Right panel form reveals
        tl.to('.form-reveal', {
            x: 0,
            opacity: 1,
            stagger: 0.1,
            duration: 1,
            ease: 'power3.out',
        }, '-=0.7');

        // 6. Continuous orb floating
        orbRefs.value.forEach((orb, i) => {
            gsap.to(orb, {
                y: i % 2 === 0 ? 20 : -20,
                x: i % 2 === 0 ? -10 : 15,
                duration: 5 + i * 0.8,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                delay: i * 0.4,
            });
        });
    });
});

onBeforeUnmount(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (taglineInterval) clearInterval(taglineInterval);
    if (gsapCtx) {
        gsapCtx.revert();
        gsapCtx = null;
    }
});
</script>

<template>
    <div class="relative min-h-svh flex bg-background font-sans text-foreground selection:bg-primary/20 overflow-hidden">

        <!-- ═══════════════════════════════════════════ -->
        <!-- LEFT PANEL — Cinematic Visual Canvas       -->
        <!-- ═══════════════════════════════════════════ -->
        <div
            ref="leftPanel"
            class="relative hidden lg:flex lg:w-[55%] xl:w-[50%] flex-col justify-between overflow-hidden bg-zinc-950 dark:bg-zinc-950"
        >
            <!-- Ambient Gradient Base -->
            <div class="absolute inset-0 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950"></div>

            <!-- Secondary warm gradient layer -->
            <div class="absolute inset-0 bg-gradient-to-tl from-indigo-950/30 via-transparent to-violet-950/20"></div>

            <!-- Animated Grid Overlay -->
            <div
                ref="gridOverlay"
                class="absolute inset-0 pointer-events-none opacity-[0.04]"
                style="background-image: linear-gradient(rgba(255,255,255,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.08) 1px, transparent 1px); background-size: 60px 60px;"
            ></div>

            <!-- Floating Ambient Orbs -->
            <div
                v-for="(orb, index) in orbs"
                :key="index"
                ref="orbRefs"
                class="absolute rounded-full blur-3xl pointer-events-none will-change-transform"
                :class="[orb.size, orb.position, orb.color]"
            ></div>

            <!-- Subtle noise texture -->
            <div class="absolute inset-0 opacity-[0.015]" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIiB4PSIwIiB5PSIwIj48ZmVUdXJidWxlbmNlIGJhc2VGcmVxdWVuY3k9Ii43NSIgc3RpdGNoVGlsZXM9InN0aXRjaCIgdHlwZT0iZnJhY3RhbE5vaXNlIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ic2F0dXJhdGUiIHZhbHVlcz0iMCIvPjwvZmlsdGVyPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbHRlcj0idXJsKCNhKSIgb3BhY2l0eT0iMSIvPjwvc3ZnPg==');"></div>

            <!-- Top: Brand Block -->
            <div class="relative z-10 p-10 xl:p-14">
                <Link :href="home()" class="group inline-flex items-center gap-4 auth-cinematic-reveal">
                    <div class="relative flex h-12 w-12 items-center justify-center text-white/90 transition-all duration-700 group-hover:rotate-[180deg]">
                        <div class="absolute inset-0 rounded-2xl bg-white/[0.06] backdrop-blur-sm border border-white/[0.08] group-hover:bg-white/[0.1] transition-all duration-500"></div>
                        <Command class="h-6 w-6 relative z-10" />
                    </div>
                    <div class="flex flex-col leading-none">
                        <span class="text-[11px] font-black tracking-[0.5em] uppercase text-white/80">LSI Engine</span>
                        <span class="text-[9px] font-bold text-white/30 uppercase mt-1.5 tracking-widest">v6.4.0</span>
                    </div>
                </Link>
            </div>

            <!-- Center: Hero Tagline Area -->
            <div class="relative z-10 flex-1 flex flex-col justify-center px-10 xl:px-14">
                <div class="max-w-lg">
                    <div class="auth-cinematic-reveal">
                        <h2 class="text-5xl xl:text-6xl font-black uppercase tracking-[-0.04em] leading-[0.9] text-white/90">
                            Learning<br />
                            <span class="text-white/40">Systems</span><br />
                            Intelligence
                        </h2>
                    </div>

                    <!-- Rotating tagline -->
                    <div class="mt-8 auth-cinematic-reveal">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-px w-12 bg-gradient-to-r from-white/30 to-transparent"></div>
                            <span class="text-[9px] font-black uppercase tracking-[0.4em] text-white/30">System Promise</span>
                        </div>
                        <div class="relative h-8 overflow-hidden">
                            <TransitionGroup
                                enter-active-class="transition-all duration-700 ease-out"
                                enter-from-class="translate-y-full opacity-0"
                                enter-to-class="translate-y-0 opacity-100"
                                leave-active-class="transition-all duration-500 ease-in absolute"
                                leave-from-class="translate-y-0 opacity-100"
                                leave-to-class="-translate-y-full opacity-0"
                            >
                                <p
                                    :key="currentTagline"
                                    class="text-lg xl:text-xl font-bold text-white/60 tracking-wide"
                                >
                                    {{ taglines[currentTagline] }}
                                </p>
                            </TransitionGroup>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom: Status + Time -->
            <div class="relative z-10 p-10 xl:p-14 flex items-end justify-between">
                <div class="flex items-center gap-3 auth-cinematic-reveal">
                    <div class="relative">
                        <div class="absolute inset-0 bg-emerald-500/40 blur-md rounded-full animate-ping"></div>
                        <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-lg relative"></div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[8px] font-black uppercase tracking-[0.5em] text-white/50">Secure Connection</span>
                        <span class="text-[7px] font-bold uppercase text-white/20 tracking-widest">TLS 1.3 / Encrypted</span>
                    </div>
                </div>

                <div class="auth-cinematic-reveal text-right">
                    <span class="text-2xl font-black text-white/15 tracking-tight tabular-nums">{{ currentTime }}</span>
                </div>
            </div>

            <!-- Edge glow line -->
            <div class="absolute right-0 inset-y-0 w-px bg-gradient-to-b from-transparent via-white/[0.06] to-transparent"></div>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- RIGHT PANEL — Form Area                    -->
        <!-- ═══════════════════════════════════════════ -->
        <div
            ref="formPanel"
            class="relative flex-1 flex flex-col items-center justify-center px-6 sm:px-10 lg:px-16 py-12"
        >
            <!-- Mobile-only subtle gradient (replaces left panel on small screens) -->
            <div class="absolute inset-0 lg:hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-background via-background to-background"></div>
                <div class="absolute top-0 left-0 right-0 h-64 bg-gradient-to-b from-primary/[0.02] to-transparent"></div>
            </div>

            <!-- Subtle grid on right panel -->
            <div class="absolute inset-0 pointer-events-none opacity-[0.015] dark:opacity-[0.03]" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>

            <div class="relative z-10 w-full max-w-md flex flex-col gap-10">

                <!-- Mobile brand mark (hidden on desktop since left panel shows it) -->
                <div class="flex flex-col items-center lg:hidden form-reveal">
                    <Link :href="home()" class="group flex flex-col items-center gap-3">
                        <div class="relative flex h-12 w-12 items-center justify-center text-foreground transition-all duration-700 group-hover:rotate-[180deg]">
                            <div class="absolute inset-0 rounded-2xl bg-primary/[0.04] dark:bg-primary/[0.08] group-hover:bg-primary/[0.08] transition-all duration-500"></div>
                            <Command class="h-6 w-6 relative z-10" />
                        </div>
                        <div class="flex flex-col items-center leading-none">
                            <span class="text-[10px] font-black tracking-[0.5em] uppercase text-foreground/60">LSI Engine</span>
                            <span class="text-[8px] font-bold text-primary/40 uppercase mt-1 tracking-widest">v6.4.0</span>
                        </div>
                    </Link>
                </div>

                <!-- Form Slot -->
                <div class="form-reveal w-full flex flex-col gap-6 relative">
                    <Transition name="form-swap" mode="out-in">
                        <div :key="$page.component" class="w-full">
                            <slot />
                        </div>
                    </Transition>
                </div>

                <!-- Decorative bottom element -->
                <div class="flex items-center justify-center gap-4 opacity-20 form-reveal">
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent to-border"></div>
                    <div class="w-1 h-1 rounded-full bg-border"></div>
                    <div class="h-px flex-1 bg-gradient-to-l from-transparent to-border"></div>
                </div>
            </div>

            <!-- Back to home link -->
            <div class="absolute bottom-8 right-8 hidden lg:block form-reveal">
                <Link
                    :href="home()"
                    class="group flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground/40 hover:text-muted-foreground transition-colors"
                >
                    <span>Home</span>
                    <ArrowUpRight class="w-3 h-3 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Keep sharp, structural inputs — no rounded corners */
:deep(input), :deep(button) {
    border-radius: 0 !important;
}

:deep(input) {
    font-weight: 600;
}

/* Tagline transition group needs position context */
.relative > :deep(.absolute) {
    width: 100%;
}

/* Form Swap Transition */
.form-swap-enter-active,
.form-swap-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.form-swap-enter-from {
    opacity: 0;
    transform: translateX(15px);
    filter: blur(4px);
}
.form-swap-leave-to {
    opacity: 0;
    transform: translateX(-15px);
    filter: blur(4px);
}
</style>
