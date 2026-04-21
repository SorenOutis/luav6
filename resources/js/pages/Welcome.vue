<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { BookOpen, ArrowRight, LayoutDashboard, Command, Zap, Award, Target, Sun, Moon, Cpu, Activity, Timer, CheckCircle2, XCircle, ChevronDown, ClipboardCheck, FileText, Trophy, Sparkles, Play, Terminal } from 'lucide-vue-next';

gsap.registerPlugin(ScrollTrigger);
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useNumberAnimation } from '@/composables/useNumberAnimation';
import { dashboard, login, register } from '@/routes';

interface ActiveSeason {
    name: string;
    startDate: string | null;
    endDate: string | null;
    showCountdown: boolean;
}

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        totalUsers?: number;
        totalExams?: number;
        totalAssignments?: number;
        totalSubmissions?: number;
        activeSeason?: ActiveSeason | null;
    }>(),
    {
        canRegister: true,
        totalUsers: 0,
        totalExams: 0,
        totalAssignments: 0,
        totalSubmissions: 0,
        activeSeason: null,
    },
);

const featureCards = ref<HTMLElement[]>([]);
const structuralLines = ref<HTMLElement[]>([]);
const mainContainer = ref<HTMLElement | null>(null);
const backgroundGrid = ref<HTMLElement | null>(null);
const mouseGlow = ref<HTMLElement | null>(null);
const techCarousel = ref<HTMLElement | null>(null);
const ambientOrbs = ref<HTMLElement[]>([]);
const bootOverlay = ref<HTMLElement | null>(null);
const bootText = ref<HTMLElement[]>([]);

// ─── Enhancement 1: Neural Particle Network ───────────────────────────────
const particleCanvas = ref<HTMLCanvasElement | null>(null);
let particleAnimFrame: number | null = null;

interface Particle {
    x: number; y: number;
    vx: number; vy: number;
    radius: number;
    opacity: number;
    mouseInfluence: number;
}

const initParticleNetwork = () => {
    const canvas = particleCanvas.value;
    if (!canvas || prefersReducedMotion.value) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const COUNT = isCoarsePointer.value ? 22 : 40;
    const CONNECTION_DIST = 140;
    const MOUSE_REPEL_DIST = 90;
    let mouse = { x: -9999, y: -9999 };
    let particles: Particle[] = [];

    const resize = () => {
        const parent = canvas.parentElement;
        if (!parent) return;
        canvas.width = parent.offsetWidth;
        canvas.height = parent.offsetHeight;
    };

    const spawnParticles = () => {
        particles = Array.from({ length: COUNT }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - 0.5) * 0.35,
            vy: (Math.random() - 0.5) * 0.35,
            radius: Math.random() * 1.5 + 0.8,
            opacity: Math.random() * 0.5 + 0.2,
            mouseInfluence: Math.random() * 0.4 + 0.6,
        }));
    };

    const getColor = () => {
        const isDark = document.documentElement.classList.contains('dark');
        return isDark ? '250,250,250' : '9,9,11';
    };

    const draw = () => {
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
                p.vx += (dx / dist) * force * 0.08 * p.mouseInfluence;
                p.vy += (dy / dist) * force * 0.08 * p.mouseInfluence;
            }

            // Dampen & move
            p.vx *= 0.96;
            p.vy *= 0.96;
            p.x += p.vx;
            p.y += p.vy;

            // Wrap edges
            if (p.x < -10) p.x = canvas.width + 10;
            if (p.x > canvas.width + 10) p.x = -10;
            if (p.y < -10) p.y = canvas.height + 10;
            if (p.y > canvas.height + 10) p.y = -10;

            // Draw dot
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${color},${p.opacity})`;
            ctx.fill();

            // Draw connections
            for (let j = i + 1; j < particles.length; j++) {
                const q = particles[j];
                const cx = p.x - q.x;
                const cy = p.y - q.y;
                const cd = Math.sqrt(cx * cx + cy * cy);
                if (cd < CONNECTION_DIST) {
                    const alpha = (1 - cd / CONNECTION_DIST) * 0.18;
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(q.x, q.y);
                    ctx.strokeStyle = `rgba(${color},${alpha})`;
                    ctx.lineWidth = 0.8;
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
};

const destroyParticleNetwork = () => {
    if (particleAnimFrame !== null) {
        cancelAnimationFrame(particleAnimFrame);
        particleAnimFrame = null;
    }
};

// ─── Enhancement 2: Live System Terminal ──────────────────────────────────
const terminalLines = ref<{ id: number; time: string; module: string; message: string; displayText: string; isTyping: boolean; type: 'info' | 'success' | 'warn' }[]>([]);
const terminalPaused = ref(false);
const terminalEl = ref<HTMLElement | null>(null);
let terminalInterval: ReturnType<typeof setInterval> | null = null;
let terminalLineId = 0;
let isProcessingQueue = false;
const terminalQueue: any[] = [];

const terminalPool = [
    { module: 'EVAL_ENGINE', message: 'Assessment batch processed — 12 submissions graded', type: 'success' as const },
    { module: 'SYNC', message: 'Leaderboard XP recalculated for Section A', type: 'info' as const },
    { module: 'AI_GRADE', message: 'Essay scoring model loaded — avg confidence 94.2%', type: 'success' as const },
    { module: 'AUTH', message: 'Session token refreshed for 3 active nodes', type: 'info' as const },
    { module: 'EXAM_SVC', message: 'Timed exam #2847 finalized — results dispatched', type: 'success' as const },
    { module: 'STREAK_SVC', message: 'Daily streak bonus applied to 18 learners', type: 'success' as const },
    { module: 'WARN', message: 'Idle session detected — initiating heartbeat probe', type: 'warn' as const },
    { module: 'DB_POOL', message: 'Connection pool rebalanced — latency nominal', type: 'info' as const },
    { module: 'ASSIGN_SVC', message: 'Deadline alert dispatched — 6 pending submissions', type: 'warn' as const },
    { module: 'CACHE', message: 'Leaderboard snapshot cached — TTL 60s', type: 'info' as const },
    { module: 'QUEUE', message: 'Job #9912 completed — 0 failures in batch', type: 'success' as const },
    { module: 'SCORING', message: 'Rubric v3.1 applied to Section B exam submissions', type: 'success' as const },
    { module: 'MONITOR', message: 'System integrity check passed — all services healthy', type: 'success' as const },
    { module: 'AI_GRADE', message: 'Short-answer NLP model inference — 7ms avg latency', type: 'info' as const },
    { module: 'USER_SVC', message: 'New learner node registered — profile initialized', type: 'success' as const },
];

const getTerminalTime = () => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
};

const processTerminalQueue = async () => {
    if (isProcessingQueue || terminalQueue.length === 0) return;
    
    // Respect pause state
    if (terminalPaused.value) {
        setTimeout(processTerminalQueue, 500);
        return;
    }
    
    isProcessingQueue = true;
    const entry = terminalQueue.shift();
    
    const newLine = { 
        id: terminalLineId++, 
        time: getTerminalTime(), 
        ...entry, 
        displayText: '', 
        isTyping: true 
    };
    
    terminalLines.value.push(newLine);
    
    if (terminalLines.value.length > 7) {
        terminalLines.value.shift();
    }

    // Human-like typing rhythm
    const message = entry.message;
    for (let i = 0; i < message.length; i++) {
        // Stop if component unmounted or extreme case
        if (terminalLineId === 0) break; 

        // Update the reactive proxy instead of the raw object
        const activeProxy = terminalLines.value.find(l => l.id === newLine.id);
        if (activeProxy) {
            activeProxy.displayText += message[i];
        }
        
        // Human-like variable delay
        let delay = 20 + Math.random() * 50; // Base speed 20-70ms
        if ([' ', ',', '.', '—'].includes(message[i])) delay += 60 + Math.random() * 40; // Punctuation pause
        
        await new Promise(resolve => setTimeout(resolve, delay));
        
        // Auto-scroll as text grows
        nextTick(() => {
            if (terminalEl.value) {
                terminalEl.value.scrollTop = terminalEl.value.scrollHeight;
            }
        });
    }

    const finalProxy = terminalLines.value.find(l => l.id === newLine.id);
    if (finalProxy) {
        finalProxy.isTyping = false;
    }
    isProcessingQueue = false;
    
    // Pause between lines
    const nextLineDelay = 1000 + Math.random() * 1500;
    setTimeout(processTerminalQueue, nextLineDelay);
};

const pushTerminalLine = () => {
    const entry = terminalPool[Math.floor(Math.random() * terminalPool.length)];
    terminalQueue.push(entry);
    if (!isProcessingQueue) processTerminalQueue();
};

const startTerminal = () => {
    // Queue up initial "boot" logs
    terminalQueue.push(terminalPool[0]);
    terminalQueue.push(terminalPool[1]);
    terminalQueue.push(terminalPool[2]);
    
    processTerminalQueue();
    
    // Continuously add new logs to the queue
    terminalInterval = setInterval(() => {
        if (terminalQueue.length < 5) { // Don't overfill the queue
            pushTerminalLine();
        }
    }, 6000);
};

// ─── Enhancement 7: 3D Architecture Stack ─────────────────────────────────
const archContainer = ref<HTMLElement | null>(null);
const archLayers = ref<HTMLElement[]>([]);

const archStack = [
    { title: 'Intelligence Layer', desc: 'Neural processing & AI evaluation modules.', color: 'primary' },
    { title: 'Application Logic', desc: 'Secure exam orchestration & routing.', color: 'muted' },
    { title: 'Data Persistence', desc: 'High-fidelity academic records & analytics.', color: 'muted' },
    { title: 'Core Infrastructure', desc: 'Scalable cloud-native node environment.', color: 'primary' },
];

const initArchAnimation = () => {
    if (!archContainer.value || prefersReducedMotion.value) return;

    const tl = gsap.timeline({
        scrollTrigger: {
            trigger: archContainer.value,
            start: 'top 60%',
            end: 'bottom 20%',
            scrub: 1,
        }
    });

    // Animate the whole stack to tilt and spread
    tl.to('.arch-stack-wrapper', {
        rotationX: 55,
        rotationZ: -35,
        y: -50,
        duration: 1,
    })
    .to(archLayers.value, {
        z: (i) => i * 80,
        opacity: (i) => 0.4 + (i * 0.15),
        stagger: 0,
        duration: 1,
    }, 0);

    // Float floating nodes
    gsap.to('.arch-node', {
        y: 'random(-20, 20)',
        x: 'random(-20, 20)',
        rotation: 360,
        duration: 'random(3, 5)',
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
        stagger: {
            each: 0.5,
            from: 'random'
        }
    });
};

// ─── Enhancement 5: Text Scramble ─────────────────────────────────────────
const SCRAMBLE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*_-+';

const scrambleText = (el: HTMLElement) => {
    const original = el.dataset.scramble || el.textContent || '';
    const totalDuration = 600;
    const chars = original.split('');
    const resolved = new Array(chars.length).fill(false);
    const start = performance.now();

    const tick = (now: number) => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / totalDuration, 1);
        // Resolve characters progressively left-to-right
        const resolveCount = Math.floor(progress * chars.length);
        for (let i = 0; i < resolveCount; i++) resolved[i] = true;

        el.textContent = chars.map((c, i) => {
            if (c === ' ') return ' ';
            if (resolved[i]) return c;
            return SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
        }).join('');

        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = original;
    };
    requestAnimationFrame(tick);
};

const initScrambleElements = () => {
    const els = document.querySelectorAll<HTMLElement>('[data-scramble]');
    els.forEach(el => {
        el.dataset.scramble = el.textContent?.trim() || '';
        ScrollTrigger.create({
            trigger: el,
            start: 'top 88%',
            once: true,
            onEnter: () => scrambleText(el),
        });
    });
};

// Determine boot message based on session state
const isLoggingOut = ref(false);

const bootMessage = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'SESSION TERMINATED';
    }
    return 'LSI ENGINE';
});

const bootSubtext = computed(() => {
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        return 'Safely Terminating Active Node Sessions...';
    }
    return 'Booting System Components...';
});

let typingTimeout: ReturnType<typeof setTimeout> | null = null;
let removeMediaListeners = () => {};
let gsapCtx: gsap.Context | null = null;
const isCoarsePointer = ref(false);
const prefersReducedMotion = ref(false);

// Appearance Management
const { appearance, toggleTheme } = useAppearance();

// Animated Metrics (Feature 3 & 9 — Real Data)
const animUsers = useNumberAnimation(() => props.totalUsers || 0, 2, 'expo.out');
const animExams = useNumberAnimation(() => props.totalExams || 0, 1.8, 'power2.out');
const animAssignments = useNumberAnimation(() => props.totalAssignments || 0, 2.2, 'expo.out');
const animSubmissions = useNumberAnimation(() => props.totalSubmissions || 0, 2.5, 'power4.out');

// Feature 8 — Season Countdown Timer
const countdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
const countdownActive = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const updateCountdown = () => {
    if (!props.activeSeason?.endDate || !props.activeSeason?.showCountdown) {
        countdownActive.value = false;
        return;
    }
    const end = new Date(props.activeSeason.endDate).getTime();
    const now = Date.now();
    const diff = end - now;
    if (diff <= 0) {
        countdownActive.value = false;
        countdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
        if (countdownInterval) clearInterval(countdownInterval);
        return;
    }
    countdownActive.value = true;
    countdown.value = {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((diff / (1000 * 60)) % 60),
        seconds: Math.floor((diff / 1000) % 60),
    };
};

// Feature 6 — Interactive Demo Quiz
interface DemoQuestion {
    id: number;
    text: string;
    type: 'multiple_choice' | 'true_false';
    options: string[];
    correctIndex: number;
    explanation: string;
}

const demoQuestions: DemoQuestion[] = [
    {
        id: 1,
        text: 'What does HTML stand for?',
        type: 'multiple_choice',
        options: ['Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language'],
        correctIndex: 0,
        explanation: 'HTML stands for Hyper Text Markup Language — the standard language for creating web pages.'
    },
    {
        id: 2,
        text: 'JavaScript is a compiled programming language.',
        type: 'true_false',
        options: ['True', 'False'],
        correctIndex: 1,
        explanation: 'JavaScript is an interpreted (or JIT compiled) scripting language, not a traditionally compiled language.'
    },
    {
        id: 3,
        text: 'Which CSS property controls the font size?',
        type: 'multiple_choice',
        options: ['text-size', 'font-style', 'font-size', 'text-style'],
        correctIndex: 2,
        explanation: 'The font-size property is used to specify the size of the font in CSS.'
    },
];

const currentDemoQuestion = ref(0);
const selectedDemoAnswer = ref<number | null>(null);
const demoAnswered = ref(false);
const demoScore = ref(0);
const demoCompleted = ref(false);

const selectDemoAnswer = (index: number) => {
    if (demoAnswered.value) return;
    selectedDemoAnswer.value = index;
    demoAnswered.value = true;
    if (index === demoQuestions[currentDemoQuestion.value].correctIndex) {
        demoScore.value++;
    }
};

const nextDemoQuestion = () => {
    if (currentDemoQuestion.value < demoQuestions.length - 1) {
        currentDemoQuestion.value++;
        selectedDemoAnswer.value = null;
        demoAnswered.value = false;
    } else {
        demoCompleted.value = true;
    }
};

const resetDemoQuiz = () => {
    currentDemoQuestion.value = 0;
    selectedDemoAnswer.value = null;
    demoAnswered.value = false;
    demoScore.value = 0;
    demoCompleted.value = false;
};

// Feature 10 — Expandable Feature Cards
const expandedFeature = ref<number | null>(null);
const toggleFeature = (index: number) => {
    expandedFeature.value = expandedFeature.value === index ? null : index;
};

const syncInteractionModes = () => {
    isCoarsePointer.value = window.matchMedia('(pointer: coarse)').matches;
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
};

// Interaction Logic
const handleMagnetic = (e: MouseEvent) => {
    if (isCoarsePointer.value || prefersReducedMotion.value) return;

    const btn = e.currentTarget as HTMLElement;
    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    
    gsap.to(btn, {
        x: x * 0.4,
        y: y * 0.4,
        duration: 0.3,
        ease: 'power2.out'
    });
};

const resetMagnetic = (e: MouseEvent) => {
    const btn = e.currentTarget as HTMLElement;
    gsap.to(btn, {
        x: 0,
        y: 0,
        duration: 0.5,
        ease: 'elastic.out(1, 0.3)'
    });
};

const handleGlobalMouseMove = (e: MouseEvent) => {
    if (!mouseGlow.value || !backgroundGrid.value || isCoarsePointer.value || prefersReducedMotion.value) return;

    const { clientX, clientY } = e;
    const xPercent = clientX / window.innerWidth;
    const yPercent = clientY / window.innerHeight;

    // Background Glow
    gsap.to(mouseGlow.value, {
        x: clientX,
        y: clientY,
        duration: 1.2,
        ease: 'power3.out'
    });

    // Grid Parallax
    gsap.to(backgroundGrid.value, {
        x: (xPercent - 0.5) * 40,
        y: (yPercent - 0.5) * 40,
        duration: 1.5,
        ease: 'power2.out'
    });
};

const handleFeatureMouseMove = (e: MouseEvent) => {
    if (isCoarsePointer.value || prefersReducedMotion.value) return;

    const card = e.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const xPercent = (x / rect.width - 0.5) * 2;
    const yPercent = (y / rect.height - 0.5) * 2;

    // 3D Tilt
    gsap.to(card, {
        rotateY: xPercent * 5,
        rotateX: -yPercent * 5,
        transformPerspective: 1000,
        duration: 0.4,
        ease: 'power2.out'
    });

    // Local Glow
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
};

const resetFeatureMouse = (e: MouseEvent) => {
    const card = e.currentTarget as HTMLElement;
    gsap.to(card, {
        rotateY: 0,
        rotateX: 0,
        duration: 0.8,
        ease: 'power4.out'
    });
};

// Typing Animation Logic
const words = ['Peak Performance.', 'Operational Elite.', 'Architectural Might.', 'System Synergy.', 'High Precision.'];
const currentWordIndex = ref(0);
const currentCharIndex = ref(words[0].length);
const isTyping = ref(false);
const typedText = ref(words[0]);

const type = () => {
    const currentWord = words[currentWordIndex.value];
    
    if (isTyping.value) {
        typedText.value = currentWord.substring(0, currentCharIndex.value + 1);
        currentCharIndex.value++;
        
        if (currentCharIndex.value === currentWord.length) {
            isTyping.value = false;
            typingTimeout = setTimeout(type, 2500); // Wait at end
            return;
        }
    } else {
        typedText.value = currentWord.substring(0, currentCharIndex.value - 1);
        currentCharIndex.value--;
        
        if (currentCharIndex.value === 0) {
            isTyping.value = true;
            currentWordIndex.value = (currentWordIndex.value + 1) % words.length;
            typingTimeout = setTimeout(type, 800); // Wait at start
            return;
        }
    }
    
    // Human-like typing rhythm for the hero section
    let delay = isTyping.value ? 40 + Math.random() * 60 : 30; // Randomize typing delay, fast delete
    if (isTyping.value && typedText.value.endsWith(' ')) delay += 60 + Math.random() * 40; // Pause slightly on spaces
    
    typingTimeout = setTimeout(type, delay);
};

onMounted(() => {
    // Check logout state before starting
    if (typeof window !== 'undefined' && sessionStorage.getItem('logged_out') === 'true') {
        isLoggingOut.value = true;
    }

    syncInteractionModes();

    const coarsePointerQuery = window.matchMedia('(pointer: coarse)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    const onMediaChange = () => syncInteractionModes();
    coarsePointerQuery.addEventListener('change', onMediaChange);
    reducedMotionQuery.addEventListener('change', onMediaChange);
    removeMediaListeners = () => {
        coarsePointerQuery.removeEventListener('change', onMediaChange);
        reducedMotionQuery.removeEventListener('change', onMediaChange);
    };

    // Start typing after initial load
    typingTimeout = setTimeout(type, 2500);

    gsapCtx = gsap.context(() => {
        const motionFactor = prefersReducedMotion.value ? 0.55 : 1;
        const tl = gsap.timeline({
            paused: true,
            defaults: { ease: 'expo.out', duration: 1.4 * motionFactor }
        });

        // 0. Pre-Boot State
        gsap.set(bootOverlay.value, { autoAlpha: 1 });
        gsap.set('.reveal-content', { y: '100%', opacity: 0 });
        gsap.set(structuralLines.value, { scaleX: 0, scaleY: 0 });
        gsap.set('.footer-reveal > *', { opacity: 0, y: 30 });
        gsap.set('.reveal-section.grid-cols-2', { opacity: 0, y: 50 });
        gsap.set(featureCards.value, { opacity: 0, y: 60 });
        gsap.set('.reveal-section.mt-24 .flex.items-center', { opacity: 0, x: -30 });
        gsap.set('.signal-fill', { scaleX: 0, transformOrigin: 'left center' });
        gsap.set('.pulse-panel', { autoAlpha: 0, y: 16, clipPath: 'inset(0 0 100% 0)' });
        gsap.set('.pulse-row', { autoAlpha: 0, y: 16 });
        gsap.set('.quick-link-row', { autoAlpha: 0, y: 16, x: 18 });
        gsap.set('.online-pill', { autoAlpha: 0, y: 16, scale: 0.86 });

        // 1. Boot Sequence (The Entrance)
        tl.to(bootText.value, {
            opacity: 1,
            y: 0,
            stagger: 0.04,
            duration: 0.8 * motionFactor,
            ease: 'back.out(2)'
        })
        .to('.boot-progress', {
            scaleX: 1,
            duration: 0.4 * motionFactor,
            ease: 'power4.inOut'
        }, '-=0.4')
        .to(bootOverlay.value, {
            clipPath: 'inset(0 0 100% 0)',
            duration: 1.4 * motionFactor,
            ease: 'power4.inOut',
            onComplete: () => {
                if (bootOverlay.value) bootOverlay.value.style.display = 'none';
                if (isLoggingOut.value) {
                    sessionStorage.removeItem('logged_out');
                    isLoggingOut.value = false;
                }
            }
        }, '+=0.2')

        // 2. Animate Structural Lines
        .to(structuralLines.value, {
            scaleX: 1,
            scaleY: 1,
            stagger: 0.1,
            duration: 1.2,
            ease: 'power4.inOut'
        }, '-=0.8')
        // 3. Reveal Nav
        .from('.nav-item', {
            y: -20,
            opacity: 0,
            stagger: 0.05,
            duration: 0.4 * motionFactor
        }, '-=0.8')
        // 4. Reveal Hero with Clip-Path/Overflow
        .to('.hero-reveal .reveal-content', {
            y: '0%',
            opacity: 1,
            stagger: 0.2,
            duration: 1.5 * motionFactor
        }, '-=1.2')
        // 5. Explicit entrance choreography for pulse + links (These are usually in view immediately)
        .to('.pulse-panel', {
            clipPath: 'inset(0 0 0% 0)',
            y: 0,
            autoAlpha: 1,
            duration: 1.1 * motionFactor,
            ease: 'power3.out'
        }, '-=1.3')
        .to('.online-pill', {
            scale: 1,
            autoAlpha: 1,
            y: 0,
            duration: 0.7 * motionFactor,
            ease: 'back.out(1.8)'
        }, '-=1.1')
        .to('.pulse-row', {
            y: 0,
            autoAlpha: 1,
            stagger: 0.12,
            duration: 0.8 * motionFactor,
            ease: 'power3.out'
        }, '-=1')
        .to('.quick-link-row', {
            x: 0,
            y: 0,
            autoAlpha: 1,
            stagger: 0.1,
            duration: 0.65 * motionFactor,
            ease: 'power2.out'
        }, '-=0.9')
        // 9. Signal bar entrance
        .to('.signal-fill', {
            scaleX: 1,
            stagger: 0.14,
            duration: 0.9 * motionFactor,
            ease: 'power2.out'
        }, '-=0.7');

        // --- NEW: Scroll-Triggered Animations ---
        
        // Metrics Ticker
        gsap.to('.reveal-section.grid-cols-2', {
            scrollTrigger: {
                trigger: '.reveal-section.grid-cols-2',
                start: 'top 85%',
            },
            y: 0,
            opacity: 1,
            duration: 1.2,
            ease: 'power3.out'
        });

        // Features Grid
        featureCards.value.forEach((card, i) => {
            gsap.to(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 90%',
                },
                y: 0,
                opacity: 1,
                duration: 1,
                delay: i * 0.1,
                ease: 'power4.out'
            });
        });

        // Tech Stack Header
        gsap.to('.reveal-section.mt-24 .flex.items-center', {
            scrollTrigger: {
                trigger: '.reveal-section.mt-24',
                start: 'top 85%',
            },
            x: 0,
            opacity: 1,
            duration: 1,
            ease: 'power2.out'
        });

        // Footer Reveal
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

        gsap.to('.signal-fill', {
            opacity: 0.7,
            duration: 1.8,
            yoyo: true,
            repeat: -1,
            stagger: 0.2,
            ease: 'sine.inOut'
        });

        if (ambientOrbs.value.length > 0) {
            gsap.to(ambientOrbs.value, {
                y: (index) => (index % 2 ? 18 : -18),
                x: (index) => (index % 2 ? -8 : 12),
                duration: 6,
                ease: 'sine.inOut',
                yoyo: true,
                repeat: -1,
                stagger: 0.5
            });
        }

        // Tech Carousel Logic
        if (techCarousel.value) {
            gsap.to(techCarousel.value, {
                xPercent: -50,
                duration: isCoarsePointer.value ? 30 : 20,
                ease: 'none',
                repeat: -1
            });
        }

        requestAnimationFrame(() => tl.play(0));
    }, mainContainer);

    // Feature 8 — Start countdown timer
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);

    // Enhancement 1 — Neural particle network
    nextTick(() => initParticleNetwork());

    // Enhancement 2 — Live terminal
    startTerminal();

    // Enhancement 5 — Text scramble on scroll
    nextTick(() => initScrambleElements());

    // Enhancement 7 — 3D Architecture Stack
    nextTick(() => initArchAnimation());
});

onBeforeUnmount(() => {
    if (typingTimeout) {
        clearTimeout(typingTimeout);
        typingTimeout = null;
    }

    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    if (terminalInterval) {
        clearInterval(terminalInterval);
        terminalInterval = null;
    }

    // Reset IDs and state for cleanup
    terminalLineId = 0;
    isProcessingQueue = false;
    terminalQueue.length = 0;

    destroyParticleNetwork();
    removeMediaListeners();

    if (gsapCtx) {
        gsapCtx.revert();
        gsapCtx = null;
    }
});

const liveSignals = [
    { label: 'AI Evaluation Speed', value: 92, valueLabel: 'Optimal' },
    { label: 'System Integrity', value: 98, valueLabel: '98%' },
    { label: 'Active Assessments', value: 100, valueLabel: 'Live' },
];

const quickLinks = [
    { label: 'Exam Directory', href: '#', icon: BookOpen },
    { label: 'Assessment Analytics', href: '#', icon: Activity },
    { label: 'Evaluation Logs', href: '#', icon: ClipboardCheck },
];

const orbLayers = [
    { style: 'width: 11rem; height: 11rem; left: -4.5rem; top: 5rem;' },
    { style: 'width: 14rem; height: 14rem; right: -5.5rem; top: 28%;' },
    { style: 'width: 9rem; height: 9rem; right: 18%; bottom: -4.5rem;' },
];

const coreFeatures = [
    {
        title: 'Assessment Engine',
        description: 'Smart testing infrastructure with real-time analytics and AI-powered evaluation modules.',
        icon: Target,
        code: 'MOD_EXM_01',
        details: 'Take timed exams with multiple question types: multiple choice, true/false, identification, and AI-graded essays. Get instant feedback and track your performance across seasons.',
        stats: [{ label: 'Question Types', value: '4' }, { label: 'AI Grading', value: 'Active' }, { label: 'Auto-Score', value: 'Real-time' }]
    },
    {
        title: 'Skill Acquisition',
        description: 'Structured assignment workflows designed to track progressive learning and mastery across subjects.',
        icon: Zap,
        code: 'MOD_ASN_02',
        details: 'Submit assignments with file uploads, track deadlines, and receive grades from your instructors. Stay on top of your academic goals with progress tracking.',
        stats: [{ label: 'File Upload', value: 'Secure' }, { label: 'Deadline Alerts', value: 'Live' }, { label: 'Grade Tracking', value: 'Instant' }]
    },
    {
        title: 'Gamified Learning',
        description: 'Engaging leaderboard system driven by XP, daily streaks, and competitive academic performance.',
        icon: Award,
        code: 'MOD_LDR_03',
        details: 'Compete with peers on the section-based leaderboard. Earn XP from exams, assignments, and daily streaks. Rise through the ranks and dominate your section.',
        stats: [{ label: 'XP System', value: 'Active' }, { label: 'Streak Bonus', value: 'Daily' }, { label: 'Sections', value: 'Multi' }]
    }
];

const systemStats = computed(() => [
    { label: 'Active Users', value: animUsers.value, unit: 'LEARNERS', icon: Cpu },
    { label: 'Assessments', value: animExams.value, unit: 'READY', icon: ClipboardCheck },
    { label: 'Assignments', value: animAssignments.value, unit: 'ACTIVE', icon: FileText },
    { label: 'Submissions', value: animSubmissions.value, unit: 'TOTAL', icon: Trophy },
]);

const techStack = [
    { name: 'Laravel 11', description: 'Robust backend architecture for scale.', icon: Command },
    { name: 'Vue 3', description: 'High-performance reactive UI system.', icon: Zap },
    { name: 'Inertia.js', description: 'The modern monolith connection layer.', icon: Target },
    { name: 'GSAP', description: 'Ultra-smooth professional animations.', icon: Award },
    { name: 'Tailwind CSS', description: 'Utility-first design framework.', icon: LayoutDashboard },
    { name: 'TypeScript', description: 'Type-safe scalable development.', icon: Command },
];
</script>

<template>
    <Head title="Welcome | LUAV Learning Engine" />
    
    <div 
        ref="mainContainer"
        @mousemove="handleGlobalMouseMove"
        class="relative min-h-screen w-full overflow-hidden bg-background font-sans text-foreground selection:bg-primary/20 transition-colors duration-500"
    >
        <!-- Global Mouse Glow -->
        <div 
            ref="mouseGlow"
            class="pointer-events-none fixed -left-[200px] -top-[200px] z-0 hidden h-[400px] w-[400px] rounded-full bg-primary/[0.06] blur-[150px] will-change-transform dark:bg-primary/[0.12] md:block"
        ></div>

        <!-- Monolithic Grid Overlay -->
        <div ref="backgroundGrid" class="fixed inset-[-100px] z-0 pointer-events-none opacity-[0.025] dark:opacity-[0.05] will-change-transform">
            <div class="absolute inset-0" style="background-image: linear-gradient(var(--color-border) 1px, transparent 1px), linear-gradient(90deg, var(--color-border) 1px, transparent 1px); background-size: 60px 60px;"></div>
        </div>
        <div
            v-for="(orb, index) in orbLayers"
            :key="index"
            ref="ambientOrbs"
            class="ambient-orb pointer-events-none absolute z-[1] rounded-full"
            :style="orb.style"
        ></div>

        <!-- System Structural Constraints (Lines) -->
        <div class="fixed inset-y-0 left-4 lg:left-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-top" ref="structuralLines"></div>
        <div class="fixed inset-y-0 right-4 lg:right-24 w-px bg-border/10 lg:bg-border/20 z-0 origin-bottom" ref="structuralLines"></div>
        <div class="fixed inset-x-0 top-1/4 h-px bg-border/20 z-0 hidden lg:block origin-left" ref="structuralLines"></div>
        <div class="fixed inset-x-0 bottom-1/4 h-px bg-border/20 z-0 hidden lg:block origin-right" ref="structuralLines"></div>

        <!-- Enhancement 4: Noise / Grain Overlay -->
        <div class="noise-grain pointer-events-none fixed inset-0 z-[1] opacity-[0.015] dark:opacity-[0.03]"
             aria-hidden="true">
            <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
                <filter id="grain-filter">
                    <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/>
                    <feColorMatrix type="saturate" values="0"/>
                </filter>
                <rect width="100%" height="100%" filter="url(#grain-filter)"/>
            </svg>
        </div>

        <!-- Global Header -->
        <header class="relative z-20 flex w-full items-center justify-between px-6 py-5 lg:px-16 lg:py-6 border-b border-border/10 dark:border-border/5 backdrop-blur-2xl bg-background/60 dark:bg-background/30 transition-colors duration-500">
            <!-- Header glow line -->
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
            <div class="nav-item flex items-center gap-3 lg:gap-4 group cursor-pointer">
                <div class="relative flex h-10 w-10 items-center justify-center text-foreground transition-all duration-700 group-hover:rotate-[180deg]">
                    <div class="absolute inset-0 rounded-xl bg-primary/5 dark:bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <Command class="h-6 w-6 lg:h-7 lg:w-7 relative z-10" />
                </div>
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] lg:text-xs font-black tracking-[0.4em] uppercase">LSI Engine</span>
                    <span class="text-[7px] lg:text-[8px] font-bold text-primary/60 uppercase mt-1 tracking-widest">v6.4.0</span>
                </div>
            </div>

            <nav class="flex items-center gap-4 lg:gap-8">
                <!-- Theme Toggle Button - always visible -->
                <button 
                    @click="toggleTheme" 
                    class="relative p-2.5 text-muted-foreground hover:text-foreground transition-all active:scale-90 rounded-xl hover:bg-muted/40"
                    aria-label="Toggle Theme"
                >
                    <Sun v-if="appearance === 'dark'" class="h-4 w-4 lg:h-5 lg:w-5" />
                    <Moon v-else class="h-4 w-4 lg:h-5 lg:w-5" />
                </button>

                <template v-if="$page.props.auth.user">
                    <Link :href="dashboard()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] lg:tracking-[0.3em] text-muted-foreground hover:text-primary transition-all flex items-center gap-2"
                    >
                        <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.6)]"></div>
                        <span class="hidden sm:inline">Access Engine</span>
                        <span class="sm:hidden">Engine</span>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="login()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Login
                    </Link>
                    <Link v-if="canRegister" :href="register()" 
                        @mousemove="handleMagnetic" 
                        @mouseleave="resetMagnetic"
                        class="nav-item relative bg-foreground text-background px-5 lg:px-8 py-2.5 lg:py-3 text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] hover:bg-primary transition-all shadow-2xl overflow-hidden group"
                    >
                        <span class="relative z-10">Init</span>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary to-primary/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Main Operational Interface -->
        <main class="relative z-10 mx-auto flex max-w-[1500px] flex-col px-6 pt-12 pb-32 lg:px-16 lg:pt-28">
            
            <!-- Hero Monolith -->
            <div class="max-w-6xl relative">
                <!-- Enhancement 1: Neural Particle Network Canvas -->
                <canvas
                    ref="particleCanvas"
                    class="particle-canvas pointer-events-none absolute inset-0 w-full h-full z-0 hidden md:block"
                    aria-hidden="true"
                />

                <div class="hero-reveal overflow-hidden mb-2 lg:mb-4 relative z-10">
                    <h1 class="reveal-content text-5xl sm:text-7xl lg:text-[8rem] font-black tracking-[-0.04em] leading-[0.9] sm:leading-[0.8] uppercase flex flex-col">
                        <span>Learning Systems</span>
                        <span class="bg-gradient-to-r from-muted-foreground/30 via-muted-foreground/15 to-muted-foreground/5 bg-clip-text text-transparent italic">Intelligence</span>
                    </h1>
                </div>
                
                <div class="hero-reveal mb-10 lg:mb-16 lg:pl-2 relative">
                    <!-- Invisible Shadow Element: Reserves the maximum possible space to prevent layout shifts -->
                    <p class="max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium leading-relaxed tracking-tight opacity-0 pointer-events-none select-none invisible whitespace-pre-wrap">
                        Experience the smart assessment engine engineered for high-fidelity learning and academic growth in 
                        <span class="font-black uppercase tracking-widest inline-flex items-center">
                            Architectural Might.<span class="ml-1 w-1 h-[0.8em] bg-primary"></span>
                        </span> 
                    </p>
                    
                    <!-- Visible Animated Element: Positioned absolutely within the reserved space -->
                    <p class="reveal-content absolute inset-0 max-w-3xl text-sm sm:text-xl lg:text-2xl font-medium text-muted-foreground leading-relaxed tracking-tight">
                        Experience the smart assessment engine engineered for high-fidelity learning and academic growth in 
                        <span class="text-foreground font-black uppercase tracking-widest inline-flex items-center">
                            {{ typedText }}<span class="ml-1 w-1 h-[0.8em] bg-primary animate-[pulse_1s_infinite] shadow-[0_0_8px_var(--color-primary)]"></span>
                        </span> 
                    </p>
                </div>




                <div class="hero-reveal overflow-hidden p-2 -m-2">
                    <div class="reveal-content flex flex-col sm:flex-row gap-4 sm:gap-6 lg:gap-8">
                        <Link v-if="$page.props.auth.user" :href="dashboard()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group relative flex items-center justify-center bg-primary px-12 py-5 lg:py-6 text-primary-foreground transition-all active:scale-[0.98] shadow-[0_8px_40px_-12px] shadow-primary/30 -skew-x-[12deg] hover:bg-primary/90"
                        >
                            <div class="skew-x-[12deg] flex items-center gap-4">
                                <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em] relative z-10">Access Dashboard</span>
                                <ArrowRight class="h-4 w-4 lg:h-5 lg:w-5 relative z-10 group-hover:translate-x-1 transition-transform" />
                            </div>
                        </Link>
                        <Link v-else :href="login()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group relative flex items-center justify-center bg-foreground text-background px-12 py-5 lg:py-6 transition-all active:scale-[0.98] shadow-2xl -skew-x-[12deg] hover:bg-primary hover:text-primary-foreground"
                        >
                            <div class="skew-x-[12deg] flex items-center gap-4">
                                <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em] relative z-10">Login to Hub</span>
                                <ArrowRight class="h-4 w-4 lg:h-5 lg:w-5 relative z-10 group-hover:translate-x-1 transition-transform" />
                            </div>
                        </Link>
                        
                        <Link v-if="!$page.props.auth.user && canRegister" :href="register()" 
                            @mousemove="handleMagnetic" 
                            @mouseleave="resetMagnetic"
                            class="group relative flex items-center justify-center border-2 border-border/40 dark:border-border/20 px-12 py-5 lg:py-6 transition-all hover:bg-muted/30 hover:border-primary/30 active:scale-[0.98] text-muted-foreground -skew-x-[12deg]"
                        >
                            <div class="skew-x-[12deg] flex items-center gap-4">
                                <span class="text-[10px] lg:text-[11px] font-black uppercase tracking-[0.4em]">Register Account</span>
                                <LayoutDashboard class="h-4 w-4 lg:h-5 lg:w-5 opacity-40 group-hover:opacity-100 group-hover:text-primary transition-all" />
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Enhancement 2: Live System Terminal -->
            <div class="reveal-section mt-8 lg:mt-12 relative">
                <div
                    class="terminal-panel relative overflow-hidden rounded-xl border border-border/30 dark:border-border/20 bg-[#0a0a0c] dark:bg-[#050507] backdrop-blur-xl"
                    @mouseenter="terminalPaused = true"
                    @mouseleave="terminalPaused = false"
                >
                    <!-- Scanline overlay inside terminal -->
                    <div class="scanline absolute inset-0 pointer-events-none opacity-[0.04] z-10"></div>

                    <!-- Terminal Header Bar -->
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-white/5">
                        <div class="flex items-center gap-2">
                            <div class="flex gap-1.5">
                                <div class="h-2.5 w-2.5 rounded-full bg-red-500/70"></div>
                                <div class="h-2.5 w-2.5 rounded-full bg-yellow-500/70"></div>
                                <div class="h-2.5 w-2.5 rounded-full bg-emerald-500/70"></div>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                <Terminal class="h-3 w-3 text-emerald-400/60" />
                                <span class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30">LSI_SYSLOG — LIVE STREAM</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Transition
                                enter-active-class="transition-opacity duration-300"
                                enter-from-class="opacity-0"
                                enter-to-class="opacity-100"
                                leave-active-class="transition-opacity duration-200"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0"
                            >
                                <span v-if="terminalPaused" class="text-[8px] font-black uppercase tracking-widest text-yellow-400/70 mr-1">STREAM PAUSED</span>
                            </Transition>
                            <div class="h-1.5 w-1.5 rounded-full animate-pulse" :class="terminalPaused ? 'bg-yellow-400/70' : 'bg-emerald-400'"></div>
                        </div>
                    </div>

                    <!-- Terminal Log Body -->
                    <div ref="terminalEl" class="terminal-body overflow-hidden px-4 py-3 space-y-1.5 max-h-[160px] lg:max-h-[140px]">
                        <TransitionGroup
                            enter-active-class="transition-[opacity,transform] duration-500 ease-out"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-[opacity,transform] duration-300 ease-in"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                            tag="div"
                            class="space-y-1.5"
                        >
                            <div
                                v-for="(line, idx) in terminalLines"
                                :key="line.id"
                                class="flex items-start gap-2 sm:gap-3 font-mono text-[10px] sm:text-xs leading-relaxed"
                                :class="{ 'opacity-40': idx < terminalLines.length - 5 }"
                            >
                                <span class="text-white/25 shrink-0 tabular-nums">{{ line.time }}</span>
                                <span class="shrink-0 px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest"
                                    :class="{
                                        'bg-emerald-500/15 text-emerald-400': line.type === 'success',
                                        'bg-blue-500/15 text-blue-400': line.type === 'info',
                                        'bg-yellow-500/15 text-yellow-400': line.type === 'warn',
                                    }">{{ line.module }}</span>
                                <span class="text-white/50 leading-relaxed break-all sm:break-normal">
                                    {{ line.displayText }}
                                    <span v-if="line.isTyping" class="inline-block w-1 h-3 bg-emerald-400/70 ml-0.5 animate-pulse"></span>
                                </span>
                            </div>
                        </TransitionGroup>
                        <!-- Blinking cursor at end -->
                        <div class="flex items-center gap-2 font-mono text-xs">
                            <span class="text-emerald-400/60">$</span>
                            <span class="h-3.5 w-1.5 bg-emerald-400/70 animate-[pulse_1s_ease-in-out_infinite]"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal-section mt-10 lg:mt-16 grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                <section class="pulse-panel gradient-border relative overflow-hidden rounded-2xl border border-border/30 dark:border-border/15 bg-card/60 dark:bg-background/50 p-5 sm:p-6 lg:p-8 shadow-[0_20px_80px_-30px_rgba(0,0,0,0.15)] dark:shadow-[0_20px_80px_-45px_rgba(0,0,0,0.45)] backdrop-blur-2xl">
                    <div class="scan-line pointer-events-none absolute inset-x-0 top-0 h-px"></div>
                    <!-- Inner glow accent -->
                    <div class="absolute -top-20 -right-20 h-40 w-40 rounded-full bg-primary/5 dark:bg-primary/10 blur-3xl pointer-events-none"></div>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary/80" data-scramble>Live Platform Pulse</p>
                            <h2 class="mt-2 text-xl sm:text-2xl font-black tracking-tight">Learning metrics in real time</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Enhancement 6: Animated SVG Waveform -->
                            <div class="hidden sm:flex items-end gap-[3px] h-5" aria-hidden="true">
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0s; --bar-min:30%; --bar-max:90%"></span>
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0.15s; --bar-min:50%; --bar-max:100%"></span>
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0.05s; --bar-min:20%; --bar-max:70%"></span>
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0.25s; --bar-min:60%; --bar-max:100%"></span>
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0.1s; --bar-min:35%; --bar-max:85%"></span>
                                <span class="waveform-bar w-[3px] rounded-full bg-emerald-500/70" style="--bar-delay:0.3s; --bar-min:15%; --bar-max:60%"></span>
                            </div>
                            <div class="online-pill hidden sm:flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_6px_rgba(16,185,129,0.6)]"></span>
                                Online
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div v-for="signal in liveSignals" :key="signal.label" class="pulse-row rounded-xl border border-border/20 dark:border-border/10 bg-muted/20 dark:bg-foreground/[0.03] p-3 sm:p-4 hover:bg-muted/30 dark:hover:bg-foreground/[0.05] transition-colors">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-[10px] sm:text-xs font-black uppercase tracking-[0.15em] text-muted-foreground">{{ signal.label }}</span>
                                <span class="text-[10px] sm:text-xs font-black tracking-wider text-foreground">{{ signal.valueLabel }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted/50 dark:bg-foreground/10">
                                <div class="signal-fill h-full rounded-full bg-gradient-to-r from-primary/60 to-primary" :style="{ width: `${signal.value}%` }"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="pulse-panel relative overflow-hidden rounded-2xl border border-border/30 dark:border-border/15 bg-card/60 dark:bg-background/50 p-5 sm:p-6 lg:p-8 backdrop-blur-2xl">
                    <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-primary/5 dark:bg-primary/10 blur-3xl"></div>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary/80">Learning Environment</p>
                    <h3 class="mt-2 text-xl font-black tracking-tight">Centralized Assessment Platform.</h3>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground">AI-powered evaluation, secure exam handling, and live performance tracking integrated seamlessly into your classroom.</p>

                    <div class="mt-5 space-y-2">
                        <a
                            v-for="quickLink in quickLinks"
                            :key="quickLink.label"
                            :href="quickLink.href"
                            class="quick-link-row group flex items-center justify-between rounded-xl border border-border/20 dark:border-border/10 px-3 sm:px-4 py-3 transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.05] hover:border-primary/20"
                        >
                            <span class="flex items-center gap-3">
                                <component :is="quickLink.icon" class="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                <span class="text-xs font-black uppercase tracking-[0.18em] text-foreground/80">{{ quickLink.label }}</span>
                            </span>
                            <ArrowRight class="h-4 w-4 text-muted-foreground/40 group-hover:text-primary transition-all group-hover:translate-x-1" />
                        </a>
                    </div>
                </section>
            </div>

            <!-- System Metrics Ticker -->
            <div class="reveal-section mt-24 lg:mt-40 grid grid-cols-2 md:grid-cols-4 gap-8 lg:gap-20 border-y border-border/20 dark:border-border/10 py-8 lg:py-14">
                <div v-for="stat in systemStats" :key="stat.label" class="flex flex-col gap-3 group hover:-translate-y-1 transition-transform duration-500">
                    <div class="flex items-center gap-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/5 dark:bg-primary/10 group-hover:bg-primary/10 dark:group-hover:bg-primary/20 transition-colors">
                            <component :is="stat.icon" class="h-3.5 w-3.5 text-primary opacity-60 group-hover:opacity-100 transition-opacity" />
                        </div>
                        <span class="text-[9px] lg:text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">{{ stat.label }}</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl lg:text-4xl font-black tracking-tighter tabular-nums">{{ stat.value }}</span>
                        <span class="text-[10px] lg:text-xs font-bold text-primary tracking-widest">{{ stat.unit }}</span>
                    </div>
                    <div class="h-px w-full bg-gradient-to-r from-primary/30 to-transparent scale-x-0 group-hover:scale-x-100 origin-left transition-transform duration-700"></div>
                </div>
            </div>

            <!-- Enhancement 7: 3D Architecture Stack -->
            <section ref="archContainer" class="reveal-section mt-32 lg:mt-56 py-20 relative overflow-hidden">
                <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
                    <!-- 3D Visual Column -->
                    <div class="relative w-full lg:w-1/2 flex justify-center perspective-[2000px]">
                        <div class="arch-stack-wrapper relative w-64 h-64 sm:w-80 sm:h-80 preserve-3d transition-transform duration-700">
                            <!-- Floating Data Nodes around the stack -->
                            <div v-for="n in 6" :key="'node-'+n" 
                                 class="arch-node absolute w-3 h-3 bg-primary/40 rounded-full blur-[1px] z-10"
                                 :style="{ 
                                     left: Math.random() * 100 + '%', 
                                     top: Math.random() * 100 + '%',
                                     transform: `translateZ(${Math.random() * 200 - 100}px)` 
                                 }">
                            </div>

                            <!-- Layered Stack -->
                            <div v-for="(layer, i) in archStack" :key="'layer-'+i"
                                 ref="archLayers"
                                 class="absolute inset-0 border border-primary/20 bg-card/40 backdrop-blur-xl rounded-2xl flex flex-col items-center justify-center p-6 text-center shadow-[0_20px_50px_rgba(0,0,0,0.1)] dark:shadow-none"
                                 :style="{ transform: `translateZ(${i * 20}px)` }">
                                <div class="absolute top-4 left-4 text-[8px] font-black tracking-widest text-primary/40">0{{ archStack.length - i }}</div>
                                <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest mb-2">{{ layer.title }}</h4>
                                <div class="h-px w-8 bg-primary/20 mb-2"></div>
                                <p class="text-[10px] text-muted-foreground leading-tight px-4 opacity-60">{{ layer.desc }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Text Content Column -->
                    <div class="w-full lg:w-1/2 space-y-8 lg:space-y-12">
                        <div class="space-y-4">
                            <div class="flex items-center gap-4">
                                <div class="h-px w-12 bg-primary"></div>
                                <span class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em] text-primary" data-scramble>System Schematic</span>
                            </div>
                            <h2 class="text-3xl sm:text-5xl font-black uppercase tracking-tight leading-none">Multidimensional <br /> Architecture</h2>
                        </div>
                        
                        <div class="grid gap-6">
                            <div v-for="(layer, i) in archStack" :key="'text-'+i" class="flex gap-6 group">
                                <span class="text-xl font-black text-primary/20 group-hover:text-primary/60 transition-colors tabular-nums">0{{ archStack.length - i }}</span>
                                <div>
                                    <h5 class="text-sm font-black uppercase tracking-widest mb-1">{{ layer.title }}</h5>
                                    <p class="text-xs text-muted-foreground leading-relaxed">{{ layer.desc }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-8">
                            <Link :href="register()" class="group inline-flex items-center gap-6 text-[10px] font-black uppercase tracking-[0.4em] border border-border px-8 py-5 hover:bg-foreground hover:text-background transition-all">
                                Inspect Ecosystem
                                <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Array (Feature 10 — Expandable) -->
            <div class="mt-12 lg:mt-24 grid w-full lg:grid-cols-3 gap-0 border-b border-border/20 dark:border-border/10">
                <div 
                    v-for="(feature, index) in coreFeatures" 
                    :key="index"
                    ref="featureCards"
                    @mousemove="handleFeatureMouseMove($event)"
                    @mouseleave="resetFeatureMouse"
                    class="group relative flex flex-col p-8 sm:p-12 lg:p-16 border-border/20 dark:border-border/10 transition-all hover:bg-muted/30 dark:hover:bg-foreground/[0.02] overflow-hidden cursor-pointer"
                    :class="[
                        { 'border-b lg:border-b-0 lg:border-r': index !== coreFeatures.length - 1 },
                        expandedFeature === index ? 'bg-muted/20 dark:bg-foreground/[0.03]' : ''
                    ]"
                    @click="toggleFeature(index)"
                >
                    <!-- Local Card Glow -->
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"
                        :style="{ background: `radial-gradient(500px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), var(--glow-color), transparent 40%)` }">
                    </div>

                    <!-- Module Indicator -->
                    <div class="absolute top-8 left-8 lg:left-12 flex items-center gap-3">
                         <span class="text-[8px] font-black tracking-widest text-primary/70 leading-none group-hover:text-primary transition-colors">{{ feature.code }}</span>
                         <div class="h-px w-8 lg:w-12 bg-primary/20 group-hover:w-16 lg:group-hover:w-32 group-hover:bg-gradient-to-r group-hover:from-primary group-hover:to-transparent transition-all duration-700"></div>
                    </div>

                    <div class="mt-8 lg:mt-12 mb-8 lg:mb-12 flex h-14 w-14 lg:h-20 lg:w-20 relative border border-border/30 dark:border-border/10 bg-muted/20 dark:bg-foreground/[0.02] transition-all duration-700 group-hover:rotate-[15deg] group-hover:scale-110 group-hover:border-primary/40 group-hover:bg-primary/5 rounded-2xl items-center justify-center overflow-hidden">
                        <!-- Icon subtle glow -->
                        <div class="absolute inset-0 bg-primary opacity-0 group-hover:opacity-[0.15] blur-xl transition-opacity duration-700"></div>
                        <component :is="feature.icon" class="h-6 w-6 lg:h-8 lg:w-8 text-muted-foreground opacity-40 group-hover:opacity-100 transition-all group-hover:text-primary duration-500 relative z-10" />
                    </div>
                    
                    <div class="space-y-4 lg:space-y-6 relative z-10">
                        <h3 class="text-xl lg:text-3xl font-black uppercase tracking-tight">{{ feature.title }}</h3>
                        <p class="text-sm lg:text-base leading-relaxed text-muted-foreground group-hover:text-foreground/90 transition-colors duration-500 max-w-sm">
                            {{ feature.description }}
                        </p>
                    </div>

                    <div class="mt-10 lg:mt-16 relative z-10">
                        <button class="text-[10px] font-black uppercase tracking-[0.3em] lg:tracking-[0.4em] text-muted-foreground hover:text-primary transition-all flex items-center gap-4 group/btn">
                            {{ expandedFeature === index ? 'Close Specs' : 'View Specs' }}
                            <ChevronDown class="h-4 w-4 transition-transform duration-500 group-hover/btn:translate-y-0.5" :class="{ 'rotate-180 group-hover/btn:-translate-y-0.5': expandedFeature === index }" />
                        </button>
                    </div>

                    <!-- Expandable Detail Panel -->
                    <Transition
                        enter-active-class="transition-all duration-700 ease-[0.2,0.8,0.2,1]"
                        enter-from-class="max-h-0 opacity-0 translate-y-4"
                        enter-to-class="max-h-[500px] opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-300 ease-in"
                        leave-from-class="max-h-[500px] opacity-100 translate-y-0"
                        leave-to-class="max-h-0 opacity-0 -translate-y-4"
                    >
                        <div v-if="expandedFeature === index" class="relative overflow-hidden mt-8 lg:mt-12 pt-8 z-10">
                            <!-- Gradient Top Border -->
                            <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>
                            
                            <p class="text-sm leading-relaxed text-muted-foreground mb-8 max-w-md bg-muted/30 dark:bg-foreground/[0.03] p-5 rounded-lg border border-border/20 dark:border-border/10">
                                <Sparkles class="w-4 h-4 text-primary mb-3 inline-block" />
                                <br />
                                {{ feature.details }}
                            </p>
                            
                            <!-- Feature Mini Stats -->
                            <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-8">
                                <div v-for="stat in feature.stats" :key="stat.label" class="p-3 sm:p-4 border border-border/40 dark:border-border/20 bg-card dark:bg-background/50 backdrop-blur-sm rounded-lg shadow-sm">
                                    <p class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.15em] sm:tracking-[0.2em] text-muted-foreground mb-1.5">{{ stat.label }}</p>
                                    <p class="text-[11px] sm:text-xs font-black text-primary tracking-widest">{{ stat.value }}</p>
                                </div>
                            </div>

                            <Link v-if="$page.props.auth.user" :href="dashboard()" class="inline-flex items-center gap-4 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] bg-primary text-primary-foreground px-6 py-4 hover:bg-primary/90 transition-all rounded-lg shadow-lg hover:shadow-primary/20 hover:gap-6 group/link">
                                Access Module
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                            <Link v-else :href="login()" class="inline-flex items-center gap-4 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.3em] bg-foreground text-background px-6 py-4 hover:bg-primary hover:text-primary-foreground transition-all rounded-lg shadow-lg hover:shadow-primary/20 hover:gap-6 group/link">
                                Login to Access
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Feature 8 — Season Countdown Timer -->
            <div v-if="countdownActive && activeSeason" class="reveal-section mt-24 lg:mt-40 relative">
                <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/80 dark:bg-background/70 p-6 sm:p-10 lg:p-14 backdrop-blur-xl shadow-lg dark:shadow-none">
                    <!-- Scan line effect -->
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"></div>
                    
                    <!-- Corner brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 border-t-2 border-l-2 border-primary/40 pointer-events-none"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 border-b-2 border-r-2 border-primary/40 pointer-events-none"></div>
                    
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 lg:gap-8">
                        <div class="space-y-2 sm:space-y-3">
                            <div class="flex items-center gap-3">
                                <Timer class="h-4 w-4 text-primary animate-pulse" />
                                <span class="text-[9px] font-black uppercase tracking-[0.3em] text-primary">Season Timer</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black uppercase tracking-tight">{{ activeSeason.name }}</h2>
                            <p class="text-[10px] sm:text-xs text-muted-foreground uppercase tracking-widest">Time Remaining Until Season End</p>
                        </div>
                        
                        <div class="grid grid-cols-4 gap-2 sm:gap-4 lg:gap-5">
                            <div v-for="(unit, key) in { DAYS: countdown.days, HRS: countdown.hours, MIN: countdown.minutes, SEC: countdown.seconds }" :key="key" class="flex flex-col items-center p-3 sm:p-5 lg:p-6 border border-border/40 dark:border-border/15 bg-muted/40 dark:bg-foreground/[0.04] rounded-lg min-w-0">
                                <span class="text-xl sm:text-3xl lg:text-5xl font-black tracking-tighter tabular-nums font-mono leading-none">
                                    {{ String(unit).padStart(2, '0') }}
                                </span>
                                <span class="text-[6px] sm:text-[8px] font-black uppercase tracking-[0.2em] sm:tracking-[0.3em] text-muted-foreground mt-1.5 sm:mt-2">{{ key }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress bar showing season elapsed -->
                    <div v-if="activeSeason.startDate" class="mt-6 sm:mt-8 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground">Season Progress</span>
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-primary">
                                {{ Math.round((Date.now() - new Date(activeSeason.startDate).getTime()) / (new Date(activeSeason.endDate!).getTime() - new Date(activeSeason.startDate).getTime()) * 100) }}%
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted/60 dark:bg-foreground/10">
                            <div class="h-full rounded-full bg-primary/70 transition-all duration-1000" :style="{ width: Math.min(100, Math.round((Date.now() - new Date(activeSeason.startDate).getTime()) / (new Date(activeSeason.endDate!).getTime() - new Date(activeSeason.startDate).getTime()) * 100)) + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature 6 — Interactive Demo Quiz -->
            <div class="reveal-section mt-24 lg:mt-40 relative">
                <div class="flex items-center gap-4 mb-8 sm:mb-10">
                    <div class="h-px w-12 bg-primary"></div>
                    <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]" data-scramble>Try a Sample Assessment</h2>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-border/40 dark:border-border/20 bg-card/80 dark:bg-background/70 backdrop-blur-xl shadow-lg dark:shadow-none gradient-border">
                    <!-- Corner brackets -->
                    <div class="absolute top-0 left-0 w-6 h-6 sm:w-8 sm:h-8 border-t-2 border-l-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>
                    <div class="absolute bottom-0 right-0 w-6 h-6 sm:w-8 sm:h-8 border-b-2 border-r-2 border-foreground/20 dark:border-foreground/10 pointer-events-none z-10"></div>

                    <!-- Quiz Header -->
                    <div class="border-b border-border/30 dark:border-border/15 p-4 sm:p-6 lg:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center border border-primary/30 bg-primary/10 rounded-lg shrink-0">
                                <Play class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-[0.3em] text-primary">Demo Protocol</p>
                                <h3 class="text-base sm:text-lg font-black uppercase tracking-tight">Quick Knowledge Check</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Progress</span>
                                <span class="text-xs sm:text-sm font-black font-mono">{{ demoCompleted ? demoQuestions.length : currentDemoQuestion + 1 }}/{{ demoQuestions.length }}</span>
                            </div>
                            <div class="px-3 sm:px-4 py-2 border border-border/40 dark:border-border/20 bg-muted/30 dark:bg-foreground/[0.04] rounded-lg">
                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-[0.2em] text-muted-foreground block">Score</span>
                                <span class="text-xs sm:text-sm font-black font-mono text-primary">{{ demoScore }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Body -->
                    <div class="p-4 sm:p-6 lg:p-12">
                        <!-- Active Question -->
                        <div v-if="!demoCompleted">
                            <div class="flex items-center gap-3 mb-4 sm:mb-6">
                                <span class="text-[9px] font-black tracking-[0.3em] text-primary font-mono">Q_{{ String(currentDemoQuestion + 1).padStart(2, '0') }}</span>
                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground px-2 py-0.5 border border-border/30 dark:border-border/20 rounded">
                                    {{ demoQuestions[currentDemoQuestion].type.replace('_', ' ') }}
                                </span>
                            </div>
                            
                            <p class="text-base sm:text-lg lg:text-xl font-black tracking-tight mb-6 sm:mb-8 max-w-2xl">
                                {{ demoQuestions[currentDemoQuestion].text }}
                            </p>

                            <!-- Options -->
                            <div class="grid gap-2.5 sm:gap-3 grid-cols-1" :class="demoQuestions[currentDemoQuestion].type === 'true_false' ? 'sm:grid-cols-2' : 'sm:grid-cols-2'">
                                <button
                                    v-for="(option, oIndex) in demoQuestions[currentDemoQuestion].options"
                                    :key="oIndex"
                                    @click="selectDemoAnswer(oIndex)"
                                    class="relative p-4 sm:p-5 border rounded-lg text-left transition-all duration-300 group/opt overflow-hidden"
                                    :class="[
                                        !demoAnswered ? 'border-border/40 dark:border-border/20 hover:border-primary/50 hover:bg-primary/5 dark:hover:bg-primary/10 cursor-pointer active:scale-[0.98] bg-muted/20 dark:bg-foreground/[0.03]' : '',
                                        demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex ? 'border-emerald-500/60 bg-emerald-500/10 dark:bg-emerald-500/15' : '',
                                        demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex ? 'border-red-500/60 bg-red-500/10 dark:bg-red-500/15' : '',
                                        demoAnswered && oIndex !== demoQuestions[currentDemoQuestion].correctIndex && oIndex !== selectedDemoAnswer ? 'opacity-40' : '',
                                    ]"
                                    :disabled="demoAnswered"
                                >
                                    <div class="flex items-center justify-between gap-3 sm:gap-4">
                                        <div class="flex items-center gap-3 sm:gap-4">
                                            <span class="text-[10px] font-black font-mono text-muted-foreground w-5 shrink-0">{{ String.fromCharCode(65 + oIndex) }}.</span>
                                            <span class="text-xs sm:text-sm font-bold">{{ option }}</span>
                                        </div>
                                        <CheckCircle2 v-if="demoAnswered && oIndex === demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-emerald-500 shrink-0" />
                                        <XCircle v-if="demoAnswered && oIndex === selectedDemoAnswer && oIndex !== demoQuestions[currentDemoQuestion].correctIndex" class="h-4 w-4 sm:h-5 sm:w-5 text-red-500 shrink-0" />
                                    </div>
                                </button>
                            </div>

                            <!-- Feedback + Next -->
                            <Transition
                                enter-active-class="transition-all duration-500 ease-out"
                                enter-from-class="opacity-0 translate-y-4"
                                enter-to-class="opacity-100 translate-y-0"
                            >
                                <div v-if="demoAnswered" class="mt-6 sm:mt-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4 sm:gap-6">
                                    <div class="p-4 border border-border/30 dark:border-border/15 bg-muted/30 dark:bg-foreground/[0.04] max-w-lg rounded-lg">
                                        <div class="flex items-center gap-2 mb-2">
                                            <Sparkles class="h-3 w-3 text-primary" />
                                            <span class="text-[8px] font-black uppercase tracking-[0.3em] text-primary">Explanation</span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                            {{ demoQuestions[currentDemoQuestion].explanation }}
                                        </p>
                                    </div>
                                    <button
                                        @click="nextDemoQuestion"
                                        class="flex items-center justify-center sm:justify-start gap-4 bg-foreground text-background px-6 py-3.5 sm:py-4 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-primary hover:text-primary-foreground transition-all active:scale-95 shrink-0 rounded-lg"
                                    >
                                        {{ currentDemoQuestion < demoQuestions.length - 1 ? 'Next Question' : 'View Results' }}
                                        <ArrowRight class="h-4 w-4" />
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <!-- Quiz Complete -->
                        <div v-else class="flex flex-col items-center justify-center py-8 lg:py-16 text-center">
                            <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center border-2 border-primary/30 bg-primary/10 mb-6 rotate-45 rounded-xl">
                                <Trophy class="h-6 w-6 sm:h-8 sm:w-8 text-primary -rotate-45" />
                            </div>
                            <h3 class="text-2xl sm:text-3xl lg:text-4xl font-black uppercase tracking-tight mb-2">Assessment Complete</h3>
                            <p class="text-muted-foreground text-xs sm:text-sm mb-2">Demo Protocol Finalized</p>
                            
                            <div class="flex items-baseline gap-2 my-4 sm:my-6">
                                <span class="text-4xl sm:text-5xl lg:text-6xl font-black text-primary font-mono">{{ demoScore }}</span>
                                <span class="text-lg sm:text-xl font-black text-muted-foreground/40">/{{ demoQuestions.length }}</span>
                            </div>
                            
                            <p class="text-xs sm:text-sm text-muted-foreground mb-6 sm:mb-8 max-w-md px-4">
                                {{ demoScore === demoQuestions.length ? 'Perfect score! You\'re ready to dominate.' : demoScore >= 2 ? 'Solid performance. The real assessments await.' : 'Room for growth. Sign up and sharpen your skills.' }}
                            </p>

                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto px-4 sm:px-0">
                                <button @click="resetDemoQuiz" class="flex items-center justify-center gap-3 border border-border/40 dark:border-border/20 px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:bg-muted/30 transition-all active:scale-95 rounded-lg">
                                    Retry Assessment
                                </button>
                                <Link v-if="!$page.props.auth.user" :href="register()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                                    Create Account
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                                <Link v-else :href="dashboard()" class="flex items-center justify-center gap-3 bg-primary text-primary-foreground px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.3em] hover:gap-5 transition-all active:scale-95 rounded-lg shadow-sm">
                                    Go to Dashboard
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Progress dots -->
                    <div class="border-t border-border/30 dark:border-border/15 p-4 flex items-center justify-center gap-2">
                        <div v-for="(q, qi) in demoQuestions" :key="qi" class="h-1.5 rounded-full transition-all duration-300"
                            :class="[
                                qi === currentDemoQuestion && !demoCompleted ? 'w-8 bg-primary' : 'w-1.5',
                                qi < currentDemoQuestion || demoCompleted ? 'bg-primary/50' : 'bg-muted dark:bg-foreground/15',
                            ]"
                        ></div>
                    </div>
                </div>
            </div>


            <!-- Tech Stack Carousel -->
            <div class="reveal-section mt-24 lg:mt-48 overflow-hidden relative py-12 border-y border-border/5 -mx-6 sm:mx-0">
                <div class="flex items-center gap-4 mb-12 px-6 sm:px-0">
                    <div class="h-px w-12 bg-primary"></div>
                    <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.4em]" data-scramble>Core Technology Stack</h2>
                </div>
                
                <div class="flex whitespace-nowrap" ref="techCarousel">
                    <!-- Duplicate items for seamless loop -->
                    <div v-for="n in 2" :key="n" class="flex gap-12 lg:gap-24 pr-12 lg:pr-24 pl-6 sm:pl-0">
                        <div v-for="tech in techStack" :key="tech.name" class="flex items-center gap-6 group">
                            <div class="flex h-12 w-12 lg:h-16 lg:w-16 items-center justify-center border border-border/10 bg-muted/5 group-hover:border-primary/30 transition-colors">
                                <component :is="tech.icon" class="h-6 w-6 lg:h-8 lg:w-8 text-muted-foreground group-hover:text-primary transition-colors" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-base lg:text-xl font-black uppercase tracking-tight">{{ tech.name }}</span>
                                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest opacity-60">{{ tech.description }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- System Registry (Footer) -->
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

        <!-- Initial Boot Interface (Entrance) -->
        <div 
            ref="bootOverlay"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-background p-6"
        >
            <div class="scanline absolute inset-0 pointer-events-none opacity-[0.05]"></div>
            <div class="relative flex flex-col items-center gap-4">
                <!-- Boot Sequence Text -->
                <div class="flex flex-col items-center gap-2">
                    <div class="flex items-center gap-2 overflow-hidden">
                        <span v-for="(letter, i) in bootMessage.split('')" :key="i" ref="bootText" class="text-xs font-black tracking-[0.6em] uppercase opacity-0 translate-y-4">
                            {{ letter === ' ' ? '\u00A0' : letter }}
                        </span>
                    </div>
                    <div class="h-px w-32 bg-primary/20 overflow-hidden">
                        <div class="boot-progress h-full w-full bg-primary origin-left scale-x-0"></div>
                    </div>
                    <span class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest opacity-60 mt-1">{{ bootSubtext }}</span>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.hero-reveal {
    display: block;
}

.ambient-orb {
    background: radial-gradient(circle at 30% 30%, color-mix(in srgb, var(--color-foreground) 28%, transparent), transparent 70%);
    opacity: 0.35;
    filter: blur(6px);
}

.scan-line {
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--color-foreground) 35%, transparent), transparent);
}

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

.signal-fill {
    box-shadow: 0 0 20px color-mix(in srgb, currentColor 45%, transparent);
}

/* Custom easing override for ultra-premium feel */
.fixed {
    transition: transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* ─────────────────────────────────────────────────────────────────────────
   Enhancement 1: Particle Canvas
──────────────────────────────────────────────────────────────────────────*/
.particle-canvas {
    /* Fills the hero wrapper exactly; pointer-events none so it never blocks clicks */
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
}

/* ─────────────────────────────────────────────────────────────────────────
   Enhancement 3: Animated Gradient Border
   Uses @property so the rotating angle is CSS-animatable.
──────────────────────────────────────────────────────────────────────────*/
@property --gb-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

@keyframes rotate-gb {
    to { --gb-angle: 360deg; }
}

.gradient-border {
    position: relative;
    isolation: isolate;
}

.gradient-border::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    padding: 1px;
    z-index: -1;
    pointer-events: none;
    background: conic-gradient(
        from var(--gb-angle),
        transparent 0%,
        color-mix(in srgb, var(--color-primary) 35%, transparent) 20%,
        transparent 40%,
        transparent 60%,
        color-mix(in srgb, var(--color-primary) 20%, transparent) 80%,
        transparent 100%
    );
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: rotate-gb 6s linear infinite;
    opacity: 0.6;
}

.gradient-border:hover::before {
    opacity: 1;
    animation-duration: 3s;
}

/* Dark mode: slightly brighter border */
.dark .gradient-border::before {
    opacity: 0.8;
}

.dark .gradient-border:hover::before {
    opacity: 1;
}

/* ─────────────────────────────────────────────────────────────────────────
   Enhancement 6: Animated Waveform Bars
──────────────────────────────────────────────────────────────────────────*/
.waveform-bar {
    display: inline-block;
    height: 100%;
    animation: waveform-pulse 1.2s ease-in-out infinite alternate;
    animation-delay: var(--bar-delay, 0s);
    transform-origin: bottom;
    transform: scaleY(var(--bar-min, 30%));
}

@keyframes waveform-pulse {
    from { transform: scaleY(var(--bar-min, 30%)); }
    to   { transform: scaleY(var(--bar-max, 90%)); }
}

/* ─────────────────────────────────────────────────────────────────────────
   Noise Grain: visible in both themes, slightly stronger in dark
──────────────────────────────────────────────────────────────────────────*/
.noise-grain {
    mix-blend-mode: overlay;
}

/* Terminal panel: always dark bg, never inherits theme background */
.terminal-panel {
    font-variant-numeric: tabular-nums;
}

@media (max-width: 1024px) {
    .ambient-orb {
        opacity: 0.22;
    }
}

@media (max-width: 768px) {
    .ambient-orb {
        display: none;
    }

    .fixed {
        transition-duration: 0.35s;
    }

    /* On mobile, reduce gradient border intensity */
    .gradient-border::before {
        opacity: 0.3;
    }

    /* Disable particle canvas on mobile (hidden via tailwind hidden md:block already) */
    .particle-canvas {
        display: none;
    }
}

/* ─────────────────────────────────────────────────────────────────────────
   Enhancement 7: 3D Utilities
──────────────────────────────────────────────────────────────────────────*/
.perspective-\[2000px\] {
    perspective: 2000px;
}

.preserve-3d {
    transform-style: preserve-3d;
}

.arch-stack-wrapper {
    transform-style: preserve-3d;
    will-change: transform;
}

.arch-node {
    pointer-events: none;
    will-change: transform;
}

/* System Variable Integration */
@layer base {
    :root {
        --color-background: #ffffff;
        --color-foreground: #09090b;
        --glow-color: rgba(0, 0, 0, 0.05);
    }
    .dark {
        --color-background: #09090b;
        --color-foreground: #fafafa;
        --glow-color: rgba(255, 255, 255, 0.08);
    }
}
</style>
