<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

const layer = ref<HTMLElement | null>(null);
const itemRefs = ref<HTMLElement[]>([]);

const prisms = [
    { kind: 'cube', size: 70, top: '42%', left: '4%', depth: -140 },
    { kind: 'diamond', size: 56, top: '55%', right: '6%', depth: -100 },
    { kind: 'cube', size: 44, top: '70%', left: '3%', depth: -160 },
    { kind: 'diamond', size: 60, top: '78%', right: '8%', depth: -110 },
    { kind: 'cube', size: 38, top: '88%', left: '48%', depth: -80 },
] as const;

const setItemRef = (el: unknown, i: number) => {
    if (el) itemRefs.value[i] = el as HTMLElement;
};

let ctx: gsap.Context | null = null;

onMounted(() => {
    if (props.prefersReducedMotion) return;

    ctx = gsap.context(() => {
        itemRefs.value.forEach((el, i) => {
            if (!el) return;
            const dir = i % 2 === 0 ? 1 : -1;
            const p = prisms[i];

            // Seed initial 3D state via GSAP so nothing else fights the transform
            gsap.set(el, { z: p.depth, rotationX: 20 * dir, rotationY: 30 * -dir });

            // Continuous tumble
            gsap.to(el, {
                rotationX: `+=${360 * dir}`,
                rotationY: `+=${360 * -dir}`,
                rotationZ: `+=${180 * dir}`,
                duration: 8 + i * 1.2,
                ease: 'none',
                repeat: -1,
            });

            // Gentle drift
            gsap.to(el, {
                y: dir * 18,
                x: dir * -10,
                duration: 3.6 + i * 0.4,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
            });

            // Scroll parallax
            gsap.to(el, {
                yPercent: dir * -30,
                ease: 'none',
                scrollTrigger: {
                    trigger: document.body,
                    start: 'top top',
                    end: 'bottom bottom',
                    scrub: 1.2,
                },
            });
        });
    }, layer);
});

onBeforeUnmount(() => {
    ctx?.revert();
});
</script>

<template>
    <div
        ref="layer"
        class="fp-layer pointer-events-none fixed inset-0 overflow-hidden"
        style="z-index: 0;"
        aria-hidden="true"
    >
        <div
            v-for="(p, i) in prisms"
            :key="i"
            :ref="(el) => setItemRef(el, i)"
            class="fp-item"
            :style="{
                top: p.top,
                left: (p as any).left,
                right: (p as any).right,
                width: `${p.size}px`,
                height: `${p.size}px`,
            }"
        >
            <!-- Cube -->
            <div v-if="p.kind === 'cube'" class="fp-cube">
                <span class="fp-face" :style="`transform: translateZ(${p.size / 2}px)`"></span>
                <span class="fp-face" :style="`transform: rotateY(180deg) translateZ(${p.size / 2}px)`"></span>
                <span class="fp-face" :style="`transform: rotateY(90deg) translateZ(${p.size / 2}px)`"></span>
                <span class="fp-face" :style="`transform: rotateY(-90deg) translateZ(${p.size / 2}px)`"></span>
                <span class="fp-face" :style="`transform: rotateX(90deg) translateZ(${p.size / 2}px)`"></span>
                <span class="fp-face" :style="`transform: rotateX(-90deg) translateZ(${p.size / 2}px)`"></span>
            </div>

            <!-- Diamond / Octahedron (approximated with two rotated squares) -->
            <div v-else class="fp-diamond">
                <span class="fp-face fp-face-diamond" :style="`transform: rotateX(55deg) rotateZ(45deg) translateZ(${p.size / 3}px)`"></span>
                <span class="fp-face fp-face-diamond" :style="`transform: rotateX(-55deg) rotateZ(45deg) translateZ(${p.size / 3}px)`"></span>
                <span class="fp-face fp-face-diamond" :style="`transform: rotateY(55deg) rotateZ(45deg) translateZ(${p.size / 3}px)`"></span>
                <span class="fp-face fp-face-diamond" :style="`transform: rotateY(-55deg) rotateZ(45deg) translateZ(${p.size / 3}px)`"></span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fp-layer {
    perspective: 900px;
    perspective-origin: 50% 50%;
    /* Fade out over the hero region so the headline stays dominant */
    -webkit-mask-image: linear-gradient(
        to bottom,
        transparent 0%,
        transparent 35%,
        rgba(0, 0, 0, 0.6) 55%,
        rgba(0, 0, 0, 1) 70%
    );
    mask-image: linear-gradient(
        to bottom,
        transparent 0%,
        transparent 35%,
        rgba(0, 0, 0, 0.6) 55%,
        rgba(0, 0, 0, 1) 70%
    );
}
.fp-item {
    position: absolute;
    transform-style: preserve-3d;
    will-change: transform;
    opacity: 0.32;
}
.fp-cube,
.fp-diamond {
    position: relative;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
}
.fp-face {
    position: absolute;
    inset: 0;
    border: 1px solid color-mix(in srgb, var(--color-primary) 40%, transparent);
    background: linear-gradient(
        145deg,
        color-mix(in srgb, var(--color-primary) 10%, transparent),
        color-mix(in srgb, var(--color-primary) 2%, transparent)
    );
    box-shadow: 0 0 18px color-mix(in srgb, var(--color-primary) 10%, transparent) inset;
}
.fp-face-diamond {
    border-color: color-mix(in srgb, var(--color-primary) 55%, transparent);
    background: linear-gradient(
        145deg,
        color-mix(in srgb, var(--color-primary) 18%, transparent),
        color-mix(in srgb, var(--color-primary) 4%, transparent)
    );
}

@media (prefers-reduced-motion: reduce) {
    .fp-item { display: none; }
}
</style>
