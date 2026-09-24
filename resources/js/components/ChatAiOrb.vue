<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        size: 'md',
    },
);
</script>

<template>
    <div
        class="relative flex items-center justify-center select-none"
        :class="{
            'h-24 w-24 sm:h-28 sm:w-28': size === 'md',
            'h-20 w-20': size === 'sm',
            'h-32 w-32': size === 'lg',
        }"
        role="img"
        aria-label="Echo AI Presence"
    >
        <!-- Concentric ambient harmonic ripples (expanding outward) -->
        <span class="harmonic-ripple ripple-1" aria-hidden="true" />
        <span class="harmonic-ripple ripple-2" aria-hidden="true" />
        <span class="harmonic-ripple ripple-3" aria-hidden="true" />

        <!-- Soft diffuse background bloom -->
        <div
            class="ambient-bloom pointer-events-none absolute inset-0 -m-6 rounded-full blur-2xl transition-all duration-700 sm:-m-10"
            aria-hidden="true"
        />

        <!-- Glass Orb Core -->
        <div
            class="orb-sphere group relative flex h-14 w-14 items-center justify-center rounded-full border border-white/20 shadow-2xl transition-transform duration-300 hover:scale-105 active:scale-95 sm:h-16 sm:w-16"
        >
            <!-- Fluid specular gradient surface -->
            <div class="orb-aurora-mesh absolute inset-0 rounded-full" />

            <!-- Edge glass specular highlight -->
            <div
                class="pointer-events-none absolute inset-0 rounded-full bg-gradient-to-tr from-transparent via-white/5 to-white/30"
            />

            <!-- Subtle inner breathing glow -->
            <div
                class="orb-inner-glow pointer-events-none absolute inset-1.5 rounded-full blur-sm"
            />

            <!-- Center icon -->
            <div
                class="relative z-10 text-white drop-shadow-[0_2px_8px_rgba(0,0,0,0.4)]"
            >
                <Sparkles
                    class="h-6 w-6 transition-transform duration-500 group-hover:scale-110 sm:h-7 sm:w-7"
                    stroke-width="1.75"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Harmonic Ripple Waves (Concentric Sound/Thought Frequency) ── */
.harmonic-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    border-radius: 9999px;
    border: 1px solid
        color-mix(in srgb, var(--color-primary, #6366f1) 35%, transparent);
    transform: translate(-50%, -50%);
    pointer-events: none;
    animation: ripple-pulse 4.5s cubic-bezier(0.25, 1, 0.5, 1) infinite;
}

.ripple-1 {
    width: 80px;
    height: 80px;
    animation-delay: 0s;
}

.ripple-2 {
    width: 80px;
    height: 80px;
    animation-delay: 1.5s;
}

.ripple-3 {
    width: 80px;
    height: 80px;
    animation-delay: 3s;
}

@keyframes ripple-pulse {
    0% {
        width: 60px;
        height: 60px;
        opacity: 0.7;
        transform: translate(-50%, -50%) scale(0.9);
    }
    100% {
        width: 170px;
        height: 170px;
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.05);
    }
}

/* ── Ambient Bloom (Diffuse color wash) ── */
.ambient-bloom {
    background: radial-gradient(
        circle,
        color-mix(in srgb, var(--color-primary, #6366f1) 40%, transparent) 0%,
        color-mix(in srgb, #818cf8 25%, transparent) 40%,
        transparent 70%
    );
    animation: bloom-breathe 4s ease-in-out infinite alternate;
}

@keyframes bloom-breathe {
    0% {
        opacity: 0.45;
        transform: scale(0.95);
    }
    100% {
        opacity: 0.75;
        transform: scale(1.08);
    }
}

/* ── Glass Orb Core (The Living Sphere) ── */
.orb-sphere {
    background: radial-gradient(
        circle at 35% 30%,
        rgba(30, 27, 75, 0.95) 0%,
        rgba(15, 23, 42, 0.98) 70%
    );
    box-shadow:
        0 12px 32px -4px
            color-mix(in srgb, var(--color-primary, #6366f1) 35%, transparent),
        inset 0 1px 2px rgba(255, 255, 255, 0.4),
        inset 0 -2px 6px rgba(0, 0, 0, 0.8);
    animation: sphere-levitate 4s ease-in-out infinite alternate;
}

@keyframes sphere-levitate {
    0% {
        transform: translateY(0);
    }
    100% {
        transform: translateY(-4px);
    }
}

/* ── Aurora Mesh Surface ── */
.orb-aurora-mesh {
    background: conic-gradient(
        from 0deg at 50% 50%,
        #6366f1 0deg,
        #8b5cf6 90deg,
        #06b6d4 180deg,
        #3b82f6 270deg,
        #6366f1 360deg
    );
    mix-blend-mode: overlay;
    opacity: 0.65;
    animation: aurora-spin 12s linear infinite;
}

@keyframes aurora-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* ── Inner Glow ── */
.orb-inner-glow {
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.5) 0%,
        color-mix(in srgb, var(--color-primary, #6366f1) 60%, transparent) 50%,
        transparent 80%
    );
    animation: inner-breathe 3s ease-in-out infinite alternate;
}

@keyframes inner-breathe {
    0% {
        opacity: 0.4;
        transform: scale(0.85);
    }
    100% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

/* ── Reduced Motion ── */
@media (prefers-reduced-motion: reduce) {
    .harmonic-ripple {
        animation: none !important;
        opacity: 0.2;
        width: 110px;
        height: 110px;
    }
    .ripple-2,
    .ripple-3 {
        display: none;
    }
    .ambient-bloom,
    .orb-sphere,
    .orb-aurora-mesh,
    .orb-inner-glow {
        animation: none !important;
        transform: none !important;
    }
}
</style>
