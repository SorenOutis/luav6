<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const props = defineProps<{
    isCoarsePointer: boolean;
    prefersReducedMotion: boolean;
    paused?: boolean;
}>();

const particleCanvas = ref<HTMLCanvasElement | null>(null);
let particleAnimFrame: number | null = null;
let scrollTriggerInstance: ScrollTrigger | null = null;

interface Particle {
    x: number; y: number;
    vx: number; vy: number;
    radius: number;
    opacity: number;
    mouseInfluence: number;
}

const initParticleNetwork = () => {
    const canvas = particleCanvas.value;
    if (!canvas || props.prefersReducedMotion) return;

    const ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return;

    const COUNT = props.isCoarsePointer ? 20 : 40;
    const CONNECTION_DIST = 140;
    const MOUSE_REPEL_DIST = 90;
    let mouse = { x: -9999, y: -9999 };
    let particles: Particle[] = [];
    let isActive = true;

    const resize = () => {
        const parent = canvas.parentElement;
        if (!parent) return;
        canvas.width = parent.offsetWidth;
        canvas.height = parent.offsetHeight;
    };

    const spawnParticles = () => {
        const isDark = document.documentElement.classList.contains('dark');
        particles = Array.from({ length: COUNT }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - 0.5) * 1.2,
            vy: (Math.random() - 0.5) * 1.2,
            radius: Math.random() * 1.5 + (isDark ? 1.2 : 0.8),
            opacity: Math.random() * (isDark ? 0.6 : 0.5) + (isDark ? 0.4 : 0.2),
            mouseInfluence: Math.random() * 0.4 + 0.6,
        }));
    };

    const getColor = () => {
        const isDark = document.documentElement.classList.contains('dark');
        return isDark ? '250,250,250' : '9,9,11';
    };

    const draw = () => {
        if (!isActive || props.paused) {
            particleAnimFrame = null;
            return;
        }
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const color = getColor();

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            // Mouse repel
            const dx = p.x - mouse.x;
            const dy = p.y - mouse.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < MOUSE_REPEL_DIST) {
                const force = (MOUSE_REPEL_DIST - dist) / MOUSE_REPEL_DIST;
                p.vx += (dx / dist) * force * 0.18 * p.mouseInfluence;
                p.vy += (dy / dist) * force * 0.18 * p.mouseInfluence;
            }

            p.vx += (Math.random() - 0.5) * 0.04;
            p.vy += (Math.random() - 0.5) * 0.04;
            p.vx *= 0.985;
            p.vy *= 0.985;
            
            const speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
            const maxSpeed = 2.2;
            if (speed > maxSpeed) {
                p.vx = (p.vx / speed) * maxSpeed;
                p.vy = (p.vy / speed) * maxSpeed;
            }

            p.x += p.vx;
            p.y += p.vy;

            if (p.x < -10) p.x = canvas.width + 10;
            if (p.x > canvas.width + 10) p.x = -10;
            if (p.y < -10) p.y = canvas.height + 10;
            if (p.y > canvas.height + 10) p.y = -10;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${color},${p.opacity})`;
            ctx.fill();

            for (let j = i + 1; j < particles.length; j++) {
                const q = particles[j];
                const cx = p.x - q.x;
                const cy = p.y - q.y;
                const cd = Math.sqrt(cx * cx + cy * cy);
                if (cd < CONNECTION_DIST) {
                    const isDark = document.documentElement.classList.contains('dark');
                    const alpha = (1 - cd / CONNECTION_DIST) * (isDark ? 0.35 : 0.18);
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(q.x, q.y);
                    ctx.strokeStyle = `rgba(${color},${alpha})`;
                    ctx.lineWidth = isDark ? 1.2 : 0.8;
                    ctx.stroke();
                }
            }
        }

        particleAnimFrame = requestAnimationFrame(draw);
    };

    const onMouseMove = (e: MouseEvent) => {
        const rect = canvas.getBoundingClientRect();
        mouse = { x: e.clientX - rect.left, y: e.clientY - rect.top };
    };
    const onMouseLeave = () => { mouse = { x: -9999, y: -9999 }; };

    resize();
    spawnParticles();
    draw();

    canvas.addEventListener('mousemove', onMouseMove);
    canvas.addEventListener('mouseleave', onMouseLeave);
    window.addEventListener('resize', resize);

    watch(() => props.paused, (isPaused) => {
        if (!isPaused && isActive && !particleAnimFrame) {
            draw();
        }
    });

    // Use ScrollTrigger to pause animation when not in view
    scrollTriggerInstance = ScrollTrigger.create({
        trigger: canvas,
        start: 'top bottom',
        end: 'bottom top',
        onToggle: self => {
            isActive = self.isActive;
            if (isActive && !particleAnimFrame) {
                draw();
            } else if (!isActive && particleAnimFrame) {
                cancelAnimationFrame(particleAnimFrame);
                particleAnimFrame = null;
            }
        }
    });
};

const destroyParticleNetwork = () => {
    if (particleAnimFrame !== null) {
        cancelAnimationFrame(particleAnimFrame);
        particleAnimFrame = null;
    }
    if (scrollTriggerInstance) {
        scrollTriggerInstance.kill();
    }
};

onMounted(() => {
    nextTick(() => initParticleNetwork());
});

onBeforeUnmount(() => {
    destroyParticleNetwork();
});
</script>

<template>
    <canvas
        ref="particleCanvas"
        class="particle-canvas pointer-events-none absolute inset-0 w-full h-full z-0 hidden md:block"
        aria-hidden="true"
    />
</template>
