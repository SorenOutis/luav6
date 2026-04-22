<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const stage = ref<HTMLElement | null>(null);
const world = ref<HTMLElement | null>(null);
const cube = ref<HTMLElement | null>(null);
const core = ref<HTMLElement | null>(null);
const ringA = ref<HTMLElement | null>(null);
const ringB = ref<HTMLElement | null>(null);
const ringC = ref<HTMLElement | null>(null);
const section = ref<HTMLElement | null>(null);

const faces = [
    { label: 'NEURAL', code: '0xN01', transform: 'translateZ(110px)' },
    { label: 'VECTOR', code: '0xV02', transform: 'rotateY(180deg) translateZ(110px)' },
    { label: 'QUERY', code: '0xQ03', transform: 'rotateY(90deg) translateZ(110px)' },
    { label: 'SYNC', code: '0xS04', transform: 'rotateY(-90deg) translateZ(110px)' },
    { label: 'TRAIN', code: '0xT05', transform: 'rotateX(90deg) translateZ(110px)' },
    { label: 'LEARN', code: '0xL06', transform: 'rotateX(-90deg) translateZ(110px)' },
];

const orbitNodes = (count: number) =>
    Array.from({ length: count }, (_, i) => ({
        id: i,
        angle: (360 / count) * i,
    }));

const nodesA = orbitNodes(6);
const nodesB = orbitNodes(8);
const nodesC = orbitNodes(5);

let ctx: gsap.Context | null = null;
let rafId = 0;
let mouseTilt = { x: 0, y: 0, targetX: 0, targetY: 0 };

const handleMouseMove = (e: MouseEvent) => {
    if (!stage.value) return;
    const rect = stage.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width - 0.5;
    const y = (e.clientY - rect.top) / rect.height - 0.5;
    mouseTilt.targetX = y * -18;
    mouseTilt.targetY = x * 24;
};

const handleMouseLeave = () => {
    mouseTilt.targetX = 0;
    mouseTilt.targetY = 0;
};

const tiltLoop = () => {
    mouseTilt.x += (mouseTilt.targetX - mouseTilt.x) * 0.08;
    mouseTilt.y += (mouseTilt.targetY - mouseTilt.y) * 0.08;
    if (world.value) {
        world.value.style.setProperty('--tilt-x', `${mouseTilt.x}deg`);
        world.value.style.setProperty('--tilt-y', `${mouseTilt.y}deg`);
    }
    rafId = requestAnimationFrame(tiltLoop);
};

onMounted(() => {
    ctx = gsap.context(() => {
        // Seed orbit ring tilts via GSAP (otherwise CSS transform conflicts with GSAP writes)
        gsap.set(ringA.value, { rotationX: 72 });
        gsap.set(ringB.value, { rotationX: 56, rotationY: 30 });
        gsap.set(ringC.value, { rotationX: 40, rotationY: -40 });

        gsap.to(cube.value, {
            rotationX: '+=360',
            rotationY: '+=360',
            duration: 14,
            ease: 'none',
            repeat: -1,
        });

        gsap.to(core.value, {
            rotationX: '-=360',
            rotationY: '+=360',
            rotationZ: '+=360',
            duration: 8,
            ease: 'none',
            repeat: -1,
        });

        gsap.to(ringA.value, { rotationZ: '+=360', duration: 10, ease: 'none', repeat: -1 });
        gsap.to(ringB.value, { rotationZ: '-=360', duration: 14, ease: 'none', repeat: -1 });
        gsap.to(ringC.value, { rotationZ: '+=360', duration: 18, ease: 'none', repeat: -1 });

        gsap.to('.dc-float', {
            y: (i: number) => (i % 2 ? 14 : -14),
            duration: 4,
            ease: 'sine.inOut',
            yoyo: true,
            repeat: -1,
            stagger: 0.25,
        });

        gsap.fromTo(
            '.dc-reveal',
            { y: 60, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 1.1,
                ease: 'expo.out',
                stagger: 0.08,
                scrollTrigger: {
                    trigger: section.value,
                    start: 'top 80%',
                },
            },
        );

        gsap.fromTo(
            stage.value,
            { scale: 0.75, opacity: 0 },
            {
                scale: 1,
                opacity: 1,
                duration: 1.6,
                ease: 'expo.out',
                scrollTrigger: {
                    trigger: section.value,
                    start: 'top 75%',
                },
            },
        );

        // Scroll parallax applied on the outer stage (not on `world` to avoid transform conflicts with tilt)
        gsap.to(stage.value, {
            y: -30,
            ease: 'none',
            scrollTrigger: {
                trigger: section.value,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 1,
            },
        });
    }, section);

    rafId = requestAnimationFrame(tiltLoop);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(rafId);
    ctx?.revert();
});
</script>

<template>
    <section
        ref="section"
        class="dimensional-core relative mt-20 lg:mt-28 overflow-hidden rounded-3xl border border-border/40 bg-gradient-to-br from-background via-background to-primary/[0.04] px-4 py-14 sm:px-10 sm:py-20 lg:px-16 lg:py-24"
    >
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]" aria-hidden="true">
            <div
                class="absolute inset-0"
                style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 48px 48px;"
            ></div>
        </div>

        <div class="relative z-10 grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:items-center">
            <div class="space-y-5">
                <div class="dc-reveal flex items-center gap-2">
                    <span class="h-px w-10 bg-primary/60"></span>
                    <span class="text-[10px] font-mono font-bold tracking-[0.35em] text-primary/80 uppercase">
                        &gt; DIMENSION.ENGINE
                    </span>
                </div>
                <h2
                    class="dc-reveal text-3xl sm:text-4xl lg:text-5xl font-black uppercase tracking-tight leading-[1.05]"
                >
                    Multidimensional
                    <br />
                    <span class="bg-gradient-to-br from-primary via-primary/80 to-foreground bg-clip-text text-transparent">
                        Intelligence Core
                    </span>
                </h2>
                <p class="dc-reveal text-sm sm:text-base text-muted-foreground/80 leading-relaxed max-w-md">
                    Six reasoning surfaces orbiting a quantum lattice — every query is projected through
                    <span class="text-foreground font-semibold">neural</span>,
                    <span class="text-foreground font-semibold">vector</span>, and
                    <span class="text-foreground font-semibold">temporal</span> dimensions in parallel.
                </p>

                <div class="dc-reveal grid grid-cols-3 gap-2 pt-3 max-w-sm">
                    <div class="rounded-lg border border-border/40 bg-muted/30 p-3 text-left">
                        <p class="text-[9px] font-mono uppercase tracking-widest text-muted-foreground/60">Axes</p>
                        <p class="text-xl font-black text-foreground">6</p>
                    </div>
                    <div class="rounded-lg border border-border/40 bg-muted/30 p-3 text-left">
                        <p class="text-[9px] font-mono uppercase tracking-widest text-muted-foreground/60">Orbits</p>
                        <p class="text-xl font-black text-foreground">3</p>
                    </div>
                    <div class="rounded-lg border border-border/40 bg-muted/30 p-3 text-left">
                        <p class="text-[9px] font-mono uppercase tracking-widest text-muted-foreground/60">Nodes</p>
                        <p class="text-xl font-black text-primary">19</p>
                    </div>
                </div>

                <div class="dc-reveal flex items-center gap-2 pt-2">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary/60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                    </span>
                    <span class="text-[10px] font-mono font-bold tracking-[0.3em] text-muted-foreground/70 uppercase">
                        Runtime stable · phase locked
                    </span>
                </div>
            </div>

            <div
                ref="stage"
                class="relative mx-auto h-[360px] w-full max-w-[460px] sm:h-[460px] sm:max-w-[560px] select-none"
                @mousemove="handleMouseMove"
                @mouseleave="handleMouseLeave"
            >
                <!-- halos -->
                <div class="dc-halo dc-float absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[260px] w-[260px] rounded-full bg-primary/20 blur-3xl"></div>
                <div class="dc-halo dc-float absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[340px] w-[340px] rounded-full border border-primary/10"></div>
                <div class="dc-halo dc-float absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[420px] w-[420px] rounded-full border border-dashed border-primary/10"></div>

                <div ref="world" class="dc-world absolute inset-0">
                    <!-- Orbit ring A -->
                    <div ref="ringA" class="dc-ring dc-ring-a">
                        <div
                            v-for="node in nodesA"
                            :key="`a-${node.id}`"
                            class="dc-node"
                            :style="{ transform: `rotateZ(${node.angle}deg) translateX(170px)` }"
                        >
                            <span class="dc-node-dot"></span>
                        </div>
                    </div>

                    <!-- Orbit ring B -->
                    <div ref="ringB" class="dc-ring dc-ring-b">
                        <div
                            v-for="node in nodesB"
                            :key="`b-${node.id}`"
                            class="dc-node"
                            :style="{ transform: `rotateZ(${node.angle}deg) translateX(200px)` }"
                        >
                            <span class="dc-node-dot dc-node-dot-sm"></span>
                        </div>
                    </div>

                    <!-- Orbit ring C -->
                    <div ref="ringC" class="dc-ring dc-ring-c">
                        <div
                            v-for="node in nodesC"
                            :key="`c-${node.id}`"
                            class="dc-node"
                            :style="{ transform: `rotateZ(${node.angle}deg) translateX(230px)` }"
                        >
                            <span class="dc-node-dot dc-node-accent"></span>
                        </div>
                    </div>

                    <!-- Core: inner tetrahedron-like diamond -->
                    <div ref="core" class="dc-core">
                        <span class="dc-core-face" style="transform: rotateY(0deg) translateZ(45px)"></span>
                        <span class="dc-core-face" style="transform: rotateY(90deg) translateZ(45px)"></span>
                        <span class="dc-core-face" style="transform: rotateY(180deg) translateZ(45px)"></span>
                        <span class="dc-core-face" style="transform: rotateY(270deg) translateZ(45px)"></span>
                        <span class="dc-core-face dc-core-cap" style="transform: rotateX(90deg) translateZ(45px)"></span>
                        <span class="dc-core-face dc-core-cap" style="transform: rotateX(-90deg) translateZ(45px)"></span>
                    </div>

                    <!-- Outer cube -->
                    <div ref="cube" class="dc-cube">
                        <div
                            v-for="(face, i) in faces"
                            :key="i"
                            class="dc-face"
                            :style="{ transform: face.transform }"
                        >
                            <div class="dc-face-inner">
                                <div class="dc-face-corner dc-face-corner-tl"></div>
                                <div class="dc-face-corner dc-face-corner-tr"></div>
                                <div class="dc-face-corner dc-face-corner-bl"></div>
                                <div class="dc-face-corner dc-face-corner-br"></div>
                                <span class="dc-face-code">{{ face.code }}</span>
                                <span class="dc-face-label">{{ face.label }}</span>
                                <span class="dc-face-scan"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scanner crosshair -->
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-full w-px bg-gradient-to-b from-transparent via-primary/20 to-transparent"></div>
                </div>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="h-px w-full bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
                </div>

                <!-- Corner labels -->
                <span class="dc-reveal absolute left-3 top-3 text-[9px] font-mono tracking-[0.25em] text-muted-foreground/60 uppercase">
                    &gt; engine.online
                </span>
                <span class="dc-reveal absolute right-3 top-3 text-[9px] font-mono tracking-[0.25em] text-primary/70 uppercase">
                    v6.∞
                </span>
                <span class="dc-reveal absolute left-3 bottom-3 text-[9px] font-mono tracking-[0.25em] text-muted-foreground/60 uppercase">
                    axis: x · y · z
                </span>
                <span class="dc-reveal absolute right-3 bottom-3 text-[9px] font-mono tracking-[0.25em] text-muted-foreground/60 uppercase">
                    ▲ drag · hover
                </span>
            </div>
        </div>
    </section>
</template>

<style scoped>
.dimensional-core {
    --tilt-x: 0deg;
    --tilt-y: 0deg;
}

.dc-world {
    transform-style: preserve-3d;
    perspective: 1200px;
    transform: rotateX(var(--tilt-x)) rotateY(var(--tilt-y));
    will-change: transform;
}

/* Cube */
.dc-cube {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 220px;
    height: 220px;
    margin-left: -110px;
    margin-top: -110px;
    transform-style: preserve-3d;
    will-change: transform;
}
.dc-face {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
}
.dc-face-inner {
    position: relative;
    width: 100%;
    height: 100%;
    border: 1px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
    background: linear-gradient(145deg, color-mix(in srgb, var(--color-primary) 8%, transparent), rgba(0, 0, 0, 0.25));
    backdrop-filter: blur(2px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    overflow: hidden;
    box-shadow: 0 0 40px color-mix(in srgb, var(--color-primary) 6%, transparent) inset;
}
.dc-face-code {
    font-family: ui-monospace, monospace;
    font-size: 9px;
    letter-spacing: 0.25em;
    color: color-mix(in srgb, var(--color-primary) 75%, transparent);
    font-weight: 700;
}
.dc-face-label {
    font-size: 17px;
    font-weight: 900;
    letter-spacing: 0.12em;
    color: var(--color-foreground);
}
.dc-face-corner {
    position: absolute;
    width: 12px;
    height: 12px;
    border-color: color-mix(in srgb, var(--color-primary) 80%, transparent);
    border-style: solid;
    border-width: 0;
}
.dc-face-corner-tl { top: 4px; left: 4px; border-top-width: 1px; border-left-width: 1px; }
.dc-face-corner-tr { top: 4px; right: 4px; border-top-width: 1px; border-right-width: 1px; }
.dc-face-corner-bl { bottom: 4px; left: 4px; border-bottom-width: 1px; border-left-width: 1px; }
.dc-face-corner-br { bottom: 4px; right: 4px; border-bottom-width: 1px; border-right-width: 1px; }
.dc-face-scan {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        transparent 0%,
        color-mix(in srgb, var(--color-primary) 15%, transparent) 50%,
        transparent 100%
    );
    transform: translateY(-100%);
    animation: dc-scan 3.2s ease-in-out infinite;
}
@keyframes dc-scan {
    0%   { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}

/* Core diamond */
.dc-core {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 90px;
    height: 90px;
    margin-left: -45px;
    margin-top: -45px;
    transform-style: preserve-3d;
    will-change: transform;
}
.dc-core-face {
    position: absolute;
    inset: 0;
    border: 1px solid color-mix(in srgb, var(--color-primary) 70%, transparent);
    background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 20%, transparent), color-mix(in srgb, var(--color-primary) 2%, transparent));
    box-shadow: 0 0 18px color-mix(in srgb, var(--color-primary) 35%, transparent) inset;
}
.dc-core-cap {
    background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 32%, transparent), color-mix(in srgb, var(--color-primary) 6%, transparent));
}

/* Orbit rings */
.dc-ring {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 0;
    height: 0;
    transform-style: preserve-3d;
    will-change: transform;
}
.dc-ring-a { transform: rotateX(72deg); }
.dc-ring-b { transform: rotateX(56deg) rotateY(30deg); }
.dc-ring-c { transform: rotateX(40deg) rotateY(-40deg); }

.dc-node {
    position: absolute;
    left: 0;
    top: 0;
}
.dc-node-dot {
    display: block;
    width: 8px;
    height: 8px;
    margin-left: -4px;
    margin-top: -4px;
    border-radius: 9999px;
    background: color-mix(in srgb, var(--color-primary) 90%, transparent);
    box-shadow: 0 0 10px color-mix(in srgb, var(--color-primary) 80%, transparent), 0 0 24px color-mix(in srgb, var(--color-primary) 40%, transparent);
}
.dc-node-dot-sm {
    width: 5px;
    height: 5px;
    margin-left: -2.5px;
    margin-top: -2.5px;
    background: color-mix(in srgb, var(--color-primary) 70%, transparent);
}
.dc-node-accent {
    width: 6px;
    height: 6px;
    margin-left: -3px;
    margin-top: -3px;
    background: var(--color-foreground);
    box-shadow: 0 0 10px color-mix(in srgb, var(--color-foreground) 70%, transparent);
}

/* Halos */
.dc-halo { will-change: transform; }

@media (max-width: 640px) {
    .dc-world { transform: rotateX(var(--tilt-x)) rotateY(var(--tilt-y)) scale(0.78); }
}
</style>
