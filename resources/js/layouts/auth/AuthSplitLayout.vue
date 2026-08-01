<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import gsap from 'gsap';
import { Command, ArrowUpRight } from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import { home } from '@/routes';

// Layout doesn't need title/description props anymore since pages provide them
const page = usePage();

interface SchoolBranding {
    name?: string;
    tagline?: string;
    logoUrl?: string | null;
    accentColor?: string;
}

const schoolBranding = computed(
    () => (page.props.schoolBranding || {}) as SchoolBranding,
);
const brandName = computed(() => schoolBranding.value.name || 'LSI Engine');
const brandTagline = computed(
    () => schoolBranding.value.tagline || 'Learning Systems Intelligence',
);
const brandLogoUrl = computed(() => schoolBranding.value.logoUrl || null);
const brandAccentColor = computed(
    () => schoolBranding.value.accentColor || '#f59e0b',
);
const heroTitleLines = computed(() => {
    const words = brandTagline.value.trim().split(/\s+/).filter(Boolean);

    if (words.length >= 3) {
        return [words[0], words.slice(1, -1).join(' '), words.at(-1) || ''];
    }

    return ['Learning', 'Systems', 'Intelligence'];
});

// Refs
const leftPanel = ref<HTMLElement | null>(null);
const formPanel = ref<HTMLElement | null>(null);
const orbRefs = ref<HTMLElement[]>([]);
const gridOverlay = ref<HTMLElement | null>(null);

// Rotating taglines
const taglines = [
    brandTagline.value,
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
    {
        size: 'w-72 h-72',
        position: '-left-20 -top-20',
        color: 'bg-zinc-500/[0.05] dark:bg-zinc-400/[0.08]',
        delay: 0,
    },
    {
        size: 'w-96 h-96',
        position: '-right-32 top-1/4',
        color: 'bg-zinc-600/[0.03] dark:bg-zinc-500/[0.05]',
        delay: 0.3,
    },
    {
        size: 'w-64 h-64',
        position: 'left-1/4 -bottom-16',
        color: 'bg-zinc-500/[0.04] dark:bg-zinc-400/[0.06]',
        delay: 0.6,
    },
    {
        size: 'w-48 h-48',
        position: 'right-1/3 top-12',
        color: 'bg-zinc-600/[0.02] dark:bg-zinc-500/[0.04]',
        delay: 0.9,
    },
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
        const tl = gsap.timeline({
            defaults: { ease: 'expo.out', duration: 1.2 },
        });

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
            tl.from(
                gridOverlay.value,
                {
                    opacity: 0,
                    duration: 1,
                },
                '-=0.5',
            );
        }

        // 3. Orbs float into position
        tl.to(
            orbRefs.value,
            {
                scale: 1,
                opacity: 1,
                stagger: 0.15,
                duration: 1.4,
                ease: 'back.out(1.2)',
            },
            '-=0.8',
        );

        // 4. Brand block reveals
        tl.to(
            '.auth-cinematic-reveal',
            {
                y: 0,
                opacity: 1,
                stagger: 0.08,
                duration: 1,
            },
            '-=1',
        );

        // 5. Right panel form reveals
        tl.to(
            '.form-reveal',
            {
                x: 0,
                opacity: 1,
                stagger: 0.1,
                duration: 1,
                ease: 'power3.out',
            },
            '-=0.7',
        );

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
    <div
        class="theme-neutral-page relative flex min-h-svh overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20"
        :style="{ '--school-accent': brandAccentColor }"
    >
        <!-- ═══════════════════════════════════════════ -->
        <!-- LEFT PANEL — Cinematic Visual Canvas       -->
        <!-- ═══════════════════════════════════════════ -->
        <div
            ref="leftPanel"
            class="relative hidden flex-col justify-between overflow-hidden bg-zinc-950 lg:flex lg:w-[55%] xl:w-[50%] dark:bg-zinc-950"
        >
            <!-- Ambient Gradient Base -->
            <div
                class="absolute inset-0 bg-gradient-to-br from-zinc-950 via-zinc-900 to-zinc-950"
            ></div>

            <!-- Secondary subtle dark gradient layer (replaces blueish tints) -->
            <div
                class="absolute inset-0 bg-gradient-to-tl from-zinc-900/40 via-transparent to-zinc-800/20"
            ></div>

            <!-- Animated Grid Overlay -->
            <div
                ref="gridOverlay"
                class="pointer-events-none absolute inset-0 opacity-[0.04]"
                style="
                    background-image:
                        linear-gradient(
                            rgba(255, 255, 255, 0.08) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            90deg,
                            rgba(255, 255, 255, 0.08) 1px,
                            transparent 1px
                        );
                    background-size: 60px 60px;
                "
            ></div>

            <!-- Floating Ambient Orbs -->
            <div
                v-for="(orb, index) in orbs"
                :key="index"
                ref="orbRefs"
                class="pointer-events-none absolute rounded-full blur-3xl will-change-transform"
                :class="[orb.size, orb.position, orb.color]"
            ></div>

            <!-- Subtle noise texture -->
            <div
                class="absolute inset-0 opacity-[0.015]"
                style="
                    background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIj48ZmlsdGVyIGlkPSJhIiB4PSIwIiB5PSIwIj48ZmVUdXJidWxlbmNlIGJhc2VGcmVxdWVuY3k9Ii43NSIgc3RpdGNoVGlsZXM9InN0aXRjaCIgdHlwZT0iZnJhY3RhbE5vaXNlIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ic2F0dXJhdGUiIHZhbHVlcz0iMCIvPjwvZmlsdGVyPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbHRlcj0idXJsKCNhKSIgb3BhY2l0eT0iMSIvPjwvc3ZnPg==');
                "
            ></div>

            <!-- Top: Brand Block -->
            <div class="relative z-10 p-10 xl:p-14">
                <Link
                    :href="home()"
                    class="group auth-cinematic-reveal inline-flex items-center gap-4"
                >
                    <div
                        class="relative flex h-12 w-12 items-center justify-center overflow-hidden text-white/90 transition-all duration-700 group-hover:rotate-[180deg]"
                    >
                        <div
                            class="absolute inset-0 rounded-2xl border border-white/[0.08] bg-white/[0.06] backdrop-blur-sm transition-all duration-500 group-hover:bg-white/[0.1]"
                        ></div>
                        <img
                            v-if="brandLogoUrl"
                            :src="brandLogoUrl"
                            :alt="`${brandName} logo`"
                            class="relative z-10 h-full w-full rounded-2xl object-cover"
                        />
                        <Command v-else class="relative z-10 h-6 w-6" />
                    </div>
                    <div class="flex flex-col leading-none">
                        <span
                            class="max-w-[16rem] truncate text-[11px] font-black tracking-[0.42em] text-white/80 uppercase"
                            >{{ brandName }}</span
                        >
                        <span
                            class="mt-1.5 max-w-[16rem] truncate text-[9px] font-bold tracking-widest text-white/30 uppercase"
                            >{{ brandTagline }}</span
                        >
                    </div>
                </Link>
            </div>

            <!-- Center: Hero Tagline Area -->
            <div
                class="relative z-10 flex flex-1 flex-col justify-center px-10 xl:px-14"
            >
                <div class="max-w-lg">
                    <div class="auth-cinematic-reveal">
                        <h2
                            class="text-5xl leading-[0.9] font-black tracking-[-0.04em] text-white/90 uppercase xl:text-6xl"
                        >
                            {{ heroTitleLines[0] }}<br />
                            <span class="text-white/40">{{
                                heroTitleLines[1]
                            }}</span
                            ><br />
                            {{ heroTitleLines[2] }}
                        </h2>
                    </div>

                    <!-- Rotating tagline -->
                    <div class="auth-cinematic-reveal mt-8">
                        <div class="mb-4 flex items-center gap-3">
                            <div
                                class="h-px w-12 bg-gradient-to-r from-white/30 to-transparent"
                            ></div>
                            <span
                                class="text-[9px] font-black tracking-[0.4em] text-white/30 uppercase"
                                >System Promise</span
                            >
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
                                    class="text-lg font-bold tracking-wide text-white/60 xl:text-xl"
                                >
                                    {{ taglines[currentTagline] }}
                                </p>
                            </TransitionGroup>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom: Status + Time -->
            <div
                class="relative z-10 flex items-end justify-between p-10 xl:p-14"
            >
                <div class="auth-cinematic-reveal flex items-center gap-3">
                    <div class="relative">
                        <div
                            class="absolute inset-0 animate-ping rounded-full bg-emerald-500/40 blur-md"
                        ></div>
                        <div
                            class="relative h-2 w-2 rounded-full bg-emerald-500 shadow-lg"
                        ></div>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span
                            class="text-[8px] font-black tracking-[0.5em] text-white/50 uppercase"
                            >Secure Connection</span
                        >
                        <span
                            class="text-[7px] font-bold tracking-widest text-white/20 uppercase"
                            >TLS 1.3 / Encrypted</span
                        >
                    </div>
                </div>

                <div class="auth-cinematic-reveal text-right">
                    <span
                        class="text-2xl font-black tracking-tight text-white/15 tabular-nums"
                        >{{ currentTime }}</span
                    >
                </div>
            </div>

            <!-- Edge glow line -->
            <div
                class="absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-white/[0.06] to-transparent"
            ></div>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- RIGHT PANEL — Form Area                    -->
        <!-- ═══════════════════════════════════════════ -->
        <div
            ref="formPanel"
            class="relative flex flex-1 flex-col items-center justify-center px-6 py-12 sm:px-10 lg:px-16"
        >
            <!-- Mobile-only subtle gradient (replaces left panel on small screens) -->
            <div class="absolute inset-0 lg:hidden">
                <div
                    class="absolute inset-0 bg-gradient-to-b from-background via-background to-background"
                ></div>
                <div
                    class="absolute top-0 right-0 left-0 h-64 bg-gradient-to-b from-primary/[0.02] to-transparent"
                ></div>
            </div>

            <!-- Subtle grid on right panel -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.015] dark:opacity-[0.03]"
                style="
                    background-image:
                        linear-gradient(
                            var(--color-border) 1px,
                            transparent 1px
                        ),
                        linear-gradient(
                            90deg,
                            var(--color-border) 1px,
                            transparent 1px
                        );
                    background-size: 60px 60px;
                "
            ></div>

            <div class="relative z-10 flex w-full max-w-md flex-col gap-10">
                <!-- Mobile brand mark (hidden on desktop since left panel shows it) -->
                <div class="form-reveal flex flex-col items-center lg:hidden">
                    <Link
                        :href="home()"
                        class="group flex flex-col items-center gap-3"
                    >
                        <div
                            class="relative flex h-12 w-12 items-center justify-center overflow-hidden text-foreground transition-all duration-700 group-hover:rotate-[180deg]"
                        >
                            <div
                                class="absolute inset-0 rounded-2xl bg-primary/[0.04] transition-all duration-500 group-hover:bg-primary/[0.08] dark:bg-primary/[0.08]"
                            ></div>
                            <img
                                v-if="brandLogoUrl"
                                :src="brandLogoUrl"
                                :alt="`${brandName} logo`"
                                class="relative z-10 h-full w-full rounded-2xl object-cover"
                            />
                            <Command v-else class="relative z-10 h-6 w-6" />
                        </div>
                        <div class="flex flex-col items-center leading-none">
                            <span
                                class="max-w-[18rem] truncate text-[10px] font-black tracking-[0.42em] text-foreground/60 uppercase"
                                >{{ brandName }}</span
                            >
                            <span
                                class="mt-1 max-w-[18rem] truncate text-[8px] font-bold tracking-widest text-primary/40 uppercase"
                                >{{ brandTagline }}</span
                            >
                        </div>
                    </Link>
                </div>

                <!-- Form Slot -->
                <div class="form-reveal relative flex w-full flex-col gap-6">
                    <Transition name="form-swap" mode="out-in">
                        <div :key="page.component" class="w-full">
                            <slot />
                        </div>
                    </Transition>
                </div>

                <!-- Decorative bottom element -->
                <div
                    class="form-reveal flex items-center justify-center gap-4 opacity-20"
                >
                    <div
                        class="h-px flex-1 bg-gradient-to-r from-transparent to-border"
                    ></div>
                    <div class="h-1 w-1 rounded-full bg-border"></div>
                    <div
                        class="h-px flex-1 bg-gradient-to-l from-transparent to-border"
                    ></div>
                </div>

                <!-- Developed by credit -->
                <div class="form-reveal flex justify-center">
                    <span
                        class="inline-flex items-center gap-2 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground/60 uppercase"
                    >
                        <span class="h-px w-6 bg-border/40"></span>
                        Developed by
                        <span
                            class="font-black tracking-[0.3em] text-muted-foreground/80"
                            >KOAMISHIN</span
                        >
                    </span>
                </div>
            </div>

            <!-- Back to home link -->
            <div class="form-reveal absolute right-8 bottom-8 hidden lg:block">
                <Link
                    :href="home()"
                    class="group flex items-center gap-2 text-[9px] font-black tracking-[0.3em] text-muted-foreground/40 uppercase transition-colors hover:text-muted-foreground"
                >
                    <span>Home</span>
                    <ArrowUpRight
                        class="h-3 w-3 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                    />
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Keep sharp, structural inputs — no rounded corners */
:deep(input),
:deep(button) {
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
