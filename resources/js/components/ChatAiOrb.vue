<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        pulsing?: boolean;
    }>(),
    {
        size: 'md',
        pulsing: false,
    },
);
</script>

<template>
    <div
        class="ai-orb-container relative flex items-center justify-center select-none"
        :class="{
            'h-20 w-20 sm:h-24 sm:w-24': size === 'md',
            'h-16 w-16 sm:h-20 sm:w-20': size === 'sm',
            'h-28 w-28 sm:h-32 sm:w-32': size === 'lg',
        }"
        aria-hidden="true"
    >
        <!-- Layer 1: Ambient Outer Aurora / Glow -->
        <div
            class="ai-orb-glow pointer-events-none absolute inset-0 -m-6 rounded-full blur-2xl transition-all duration-700 sm:-m-8"
        />

        <!-- Layer 2: Orbiting Energy Halo Rings -->
        <div
            class="ai-orb-ring-1 pointer-events-none absolute inset-[-6px] rounded-full border border-primary/25 sm:inset-[-8px]"
        />
        <div
            class="ai-orb-ring-2 pointer-events-none absolute inset-[-14px] rounded-full border border-dashed border-primary/20 sm:inset-[-18px]"
        />

        <!-- Satellite Micro-Sparkle -->
        <div class="ai-satellite pointer-events-none absolute">
            <span
                class="block h-1.5 w-1.5 rounded-full bg-cyan-400 shadow-[0_0_8px_#22d3ee]"
            />
        </div>

        <!-- Layer 3: Glass Core Sphere -->
        <div
            class="ai-orb-core relative flex h-full w-full items-center justify-center overflow-hidden rounded-3xl border border-white/20 shadow-2xl transition-transform duration-300 hover:scale-105 active:scale-95"
        >
            <!-- Shifting Gradient Mesh Surface -->
            <div class="ai-orb-surface absolute inset-0" />

            <!-- Glass Specular Highlight (Rim reflection) -->
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-white/30"
            />

            <!-- Dynamic Inner Core Glow -->
            <div
                class="ai-orb-inner-pulse pointer-events-none absolute inset-3 rounded-2xl blur-md"
            />

            <!-- Center Icon: AI Neural Sparkle -->
            <div
                class="ai-orb-icon relative z-10 flex items-center justify-center text-white drop-shadow-[0_2px_10px_rgba(0,0,0,0.5)]"
            >
                <Sparkles
                    :class="{
                        'h-6 w-6 sm:h-8 sm:w-8': size === 'md',
                        'h-5 w-5 sm:h-6 sm:w-6': size === 'sm',
                        'h-9 w-9 sm:h-11 sm:w-11': size === 'lg',
                    }"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Ambient Outer Glow ── */
.ai-orb-glow {
    background: radial-gradient(
        circle,
        color-mix(in srgb, var(--color-primary, #6366f1) 45%, #a855f7 35%) 0%,
        color-mix(in srgb, var(--color-primary, #6366f1) 25%, #06b6d4 25%) 40%,
        transparent 70%
    );
    animation: orb-glow-pulse 4s ease-in-out infinite alternate;
}

@keyframes orb-glow-pulse {
    0% {
        opacity: 0.55;
        transform: scale(0.92);
    }
    50% {
        opacity: 0.85;
        transform: scale(1.06);
    }
    100% {
        opacity: 0.6;
        transform: scale(0.96);
    }
}

/* ── Orbiting Rings ── */
.ai-orb-ring-1 {
    animation: ring-rotate-cw 18s linear infinite;
}

.ai-orb-ring-2 {
    animation: ring-rotate-ccw 26s linear infinite;
}

@keyframes ring-rotate-cw {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@keyframes ring-rotate-ccw {
    from {
        transform: rotate(360deg);
    }
    to {
        transform: rotate(0deg);
    }
}

/* ── Orbiting Satellite Dot ── */
.ai-satellite {
    inset: -14px;
    animation: ring-rotate-cw 8s linear infinite;
}

/* ── Core Sphere Surface & Colors ── */
.ai-orb-core {
    background: linear-gradient(
        135deg,
        rgba(15, 23, 42, 0.95) 0%,
        rgba(30, 27, 75, 0.9) 50%,
        rgba(15, 23, 42, 0.95) 100%
    );
    box-shadow:
        0 10px 30px -5px rgba(99, 102, 241, 0.3),
        inset 0 1px 1px 0 rgba(255, 255, 255, 0.35),
        inset 0 -4px 12px 0 rgba(0, 0, 0, 0.6);
    animation: orb-float 5s ease-in-out infinite;
}

.ai-orb-surface {
    background:
        radial-gradient(
            circle at 25% 25%,
            rgba(255, 255, 255, 0.25),
            transparent 50%
        ),
        conic-gradient(
            from 180deg at 50% 50%,
            #4f46e5 0deg,
            #7c3aed 72deg,
            #06b6d4 144deg,
            #3b82f6 216deg,
            #a855f7 288deg,
            #4f46e5 360deg
        );
    mix-blend-mode: overlay;
    opacity: 0.85;
    animation: surface-spin 20s linear infinite;
}

@keyframes surface-spin {
    from {
        transform: rotate(0deg) scale(1.2);
    }
    to {
        transform: rotate(360deg) scale(1.2);
    }
}

/* ── Inner Core Pulse ── */
.ai-orb-inner-pulse {
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.8) 0%,
        rgba(168, 85, 247, 0.6) 40%,
        rgba(99, 102, 241, 0.3) 80%,
        transparent 100%
    );
    animation: inner-pulse 3s ease-in-out infinite alternate;
}

@keyframes inner-pulse {
    0% {
        transform: scale(0.75);
        opacity: 0.5;
    }
    100% {
        transform: scale(1.1);
        opacity: 0.95;
    }
}

/* ── Levitation Float ── */
@keyframes orb-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

/* ── Center Icon Shimmer ── */
.ai-orb-icon {
    animation: icon-float 5s ease-in-out infinite;
}

@keyframes icon-float {
    0%,
    100% {
        transform: scale(1) rotate(0deg);
        filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.6));
    }
    50% {
        transform: scale(1.08) rotate(3deg);
        filter: drop-shadow(0 0 12px rgba(168, 85, 247, 0.9));
    }
}

/* ── Reduced Motion ── */
@media (prefers-reduced-motion: reduce) {
    .ai-orb-glow,
    .ai-orb-ring-1,
    .ai-orb-ring-2,
    .ai-satellite,
    .ai-orb-core,
    .ai-orb-surface,
    .ai-orb-inner-pulse,
    .ai-orb-icon {
        animation: none !important;
        transform: none !important;
    }
    .ai-orb-glow {
        opacity: 0.6;
    }
}
</style>
