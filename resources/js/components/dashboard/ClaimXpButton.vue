<script setup lang="ts">
import axios from 'axios';
import gsap from 'gsap';
import { Gift, Sparkles, Check, Clock } from 'lucide-vue-next';
import {
    ref,
    computed,
    watch,
    nextTick,
    onMounted,
    onBeforeUnmount,
} from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { useMobile } from '@/composables/useMobile';

const props = defineProps<{
    canClaim: boolean;
    amount: number;
    nextClaimAt: string | null;
    streak: number;
    showPrompt?: boolean;
}>();

const emit = defineEmits<{
    claimed: [amount: number, totalXp: number];
}>();

const { prefersReducedMotion } = useMobile();

const claimState = ref<'idle' | 'claiming' | 'claimed'>(
    props.canClaim ? 'idle' : 'claimed',
);
const claimedAmount = ref(0);
const showParticles = ref(false);
const showFloatingXp = ref(false);
const justClaimed = ref(false);
const buttonRef = ref<HTMLElement | null>(null);
const particlesRef = ref<HTMLElement | null>(null);
const floatXpRef = ref<HTMLElement | null>(null);
const successRef = ref<HTMLElement | null>(null);
const checkCircleRef = ref<SVGCircleElement | null>(null);
const checkPathRef = ref<SVGPathElement | null>(null);
const successTitleRef = ref<HTMLElement | null>(null);
const countdownText = ref('');
const showClaimModal = ref(false);
let glowAnim: gsap.core.Tween | null = null;
let closeTimer: ReturnType<typeof setTimeout> | null = null;
const activeTweens = new Set<gsap.core.Tween>();

const confettiColors = [
    'bg-amber-400',
    'bg-yellow-300',
    'bg-orange-400',
    'bg-rose-400',
    'bg-emerald-400',
    'bg-sky-400',
];

// Open the prompt modal when the parent signals it's ready — immediately for
// users who already have a section, or after the section-selection flow for
// new users (the prop flips to true once the section modal is dealt with).
watch(
    () => props.showPrompt && props.canClaim,
    (shouldShow) => {
        if (shouldShow && claimState.value === 'idle' && !showClaimModal.value) {
            showClaimModal.value = true;

            // Mark the prompt as delivered so it doesn't re-open on later
            // dashboard visits in this session.
            axios.post('/api/claim-xp/prompt-shown').catch(() => {});
        }
    },
    { immediate: true },
);

// Countdown timer for next claim
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const nextClaimDate = computed(() => {
    if (!props.nextClaimAt) return null;
    const d = new Date(props.nextClaimAt);
    return Number.isNaN(d.getTime()) ? null : d;
});

function updateCountdown() {
    if (!nextClaimDate.value) {
        countdownText.value = '';
        return;
    }
    const now = Date.now();
    const diff = nextClaimDate.value.getTime() - now;
    if (diff <= 0) {
        countdownText.value = 'Available now!';
        claimState.value = 'idle';
        return;
    }
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    countdownText.value = `Next claim in ${hours}h ${minutes}m`;
}

// Track tweens so they can be killed on unmount. The set is tiny (a handful
// of tweens per claim) and cleared wholesale in onBeforeUnmount.
function track(tween: gsap.core.Tween) {
    activeTweens.add(tween);
    return tween;
}

// The particle burst animation (inline button)
function triggerParticleBurst() {
    if (!particlesRef.value) return;
    showParticles.value = true;

    const particles = particlesRef.value.querySelectorAll('.xp-particle');
    track(
        gsap.fromTo(
            particles,
            {
                y: 0,
                x: 0,
                opacity: 1,
                scale: 1,
            },
            {
                y: 'random(-120, -200)',
                x: 'random(-60, 60)',
                opacity: 0,
                scale: 0.2,
                duration: 0.8 + Math.random() * 0.4,
                ease: 'power2.out',
                stagger: 0.04,
                onComplete: () => {
                    showParticles.value = false;
                },
            },
        ),
    );
}

// Floating "+X XP" badge rising above the inline button
function triggerFloatingXp() {
    if (!floatXpRef.value) return;
    showFloatingXp.value = true;

    track(
        gsap.fromTo(
            floatXpRef.value,
            { y: 0, opacity: 0, scale: 0.6 },
            {
                y: -46,
                opacity: 1,
                scale: 1,
                duration: 0.9,
                ease: 'power2.out',
                onComplete: () => {
                    track(
                        gsap.to(floatXpRef.value, {
                            y: -70,
                            opacity: 0,
                            scale: 1.05,
                            duration: 0.35,
                            ease: 'power2.in',
                            onComplete: () => {
                                showFloatingXp.value = false;
                            },
                        }),
                    );
                },
            },
        ),
    );
}

// Success celebration inside the modal: checkmark draw + confetti + title pop
async function playClaimSuccess() {
    if (!successRef.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power3.out' },
        paused: prefersReducedMotion.value,
    });

    // Draw the circle
    if (checkCircleRef.value) {
        const circle = checkCircleRef.value;
        const len = circle.getTotalLength();
        gsap.set(circle, { strokeDasharray: len, strokeDashoffset: len });
        tl.to(circle, {
            strokeDashoffset: 0,
            duration: 0.5,
            ease: 'power2.inOut',
        });
    }

    // Draw the checkmark
    if (checkPathRef.value) {
        const path = checkPathRef.value;
        const len = path.getTotalLength();
        gsap.set(path, { strokeDasharray: len, strokeDashoffset: len });
        tl.to(
            path,
            {
                strokeDashoffset: 0,
                duration: 0.35,
                ease: 'power2.out',
            },
            '-=0.15',
        );
    }

    // Title pop
    if (successTitleRef.value) {
        tl.fromTo(
            successTitleRef.value,
            { scale: 0.6, opacity: 0 },
            {
                scale: 1,
                opacity: 1,
                duration: 0.4,
                ease: 'back.out(2)',
            },
            '-=0.1',
        );
    }

    // Confetti burst from the checkmark
    const confetti = successRef.value.querySelectorAll('.claim-confetti');
    if (confetti.length) {
        tl.fromTo(
            confetti,
            { x: 0, y: 0, opacity: 1, scale: 0.4, rotation: 0 },
            {
                x: () => gsap.utils.random(-110, 110),
                y: () => gsap.utils.random(-160, 30),
                rotation: () => gsap.utils.random(-180, 180),
                opacity: 0,
                scale: 1,
                duration: 0.9,
                stagger: 0.02,
                ease: 'power2.out',
            },
            '-=0.2',
        );
    }

    if (prefersReducedMotion.value) {
        tl.progress(1);
    } else {
        tl.play();
    }
}

async function handleClaim() {
    if (claimState.value !== 'idle') return;

    claimState.value = 'claiming';

    // Button press animation
    if (buttonRef.value) {
        gsap.to(buttonRef.value, {
            scale: 0.92,
            duration: 0.1,
            ease: 'power2.in',
            yoyo: true,
            repeat: 1,
        });
    }

    try {
        const { data } = await axios.post<{
            claimed: boolean;
            amount: number;
            total_xp: number;
            streak: number;
        }>('/api/claim-xp');

        if (data.claimed) {
            claimedAmount.value = data.amount;
            justClaimed.value = true;

            // Flip the inline button to its claimed state so it never shows a
            // stuck spinner once the modal closes.
            claimState.value = 'claimed';

            if (showClaimModal.value) {
                // Celebration inside the modal, then close it and notify parent
                await nextTick();
                playClaimSuccess();

                closeTimer = setTimeout(() => {
                    showClaimModal.value = false;
                    justClaimed.value = false;
                    emit('claimed', data.amount, data.total_xp);
                }, prefersReducedMotion.value ? 300 : 1500);
            } else {
                // Inline claim: burst + floating badge, then notify parent.
                // Wait for the v-if elements to render before reading the refs.
                await nextTick();
                triggerParticleBurst();
                triggerFloatingXp();

                closeTimer = setTimeout(() => {
                    emit('claimed', data.amount, data.total_xp);
                }, 1000);
            }
        } else {
            claimState.value = 'claimed'; // Already claimed
        }
    } catch {
        claimState.value = 'idle';
    }
}

function handleModalClose() {
    if (claimState.value === 'claiming') return;
    showClaimModal.value = false;
}

onMounted(() => {
    // Start idle pulse animation
    if (buttonRef.value && claimState.value === 'idle') {
        glowAnim = gsap.to(buttonRef.value, {
            boxShadow:
                '0 0 20px rgba(250,204,21,0.3), 0 0 40px rgba(250,204,21,0.1)',
            duration: 1.5,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
    }

    // Start countdown if claimed
    if (claimState.value === 'claimed' && nextClaimDate.value) {
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 10000);
    }
});

onBeforeUnmount(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    if (glowAnim) {
        glowAnim.kill();
    }
    if (closeTimer) {
        clearTimeout(closeTimer);
    }
    activeTweens.forEach((tween) => tween.kill());
    activeTweens.clear();
});
</script>

<template>
    <div class="relative">
        <ResponsiveModal
            v-model="showClaimModal"
            title="Your daily XP reward is ready"
            description="Keep your streak going with today’s login reward."
            content-class="sm:max-w-md"
            @close="handleModalClose"
        >
            <!-- Success celebration -->
            <div
                v-if="justClaimed"
                class="relative space-y-5 overflow-hidden py-2 text-center"
                ref="successRef"
            >
                <!-- Confetti -->
                <div
                    v-for="i in 16"
                    :key="i"
                    class="claim-confetti absolute top-16 left-1/2 h-2.5 w-2.5 rounded-sm"
                    :class="confettiColors[i % confettiColors.length]"
                    :style="{ marginLeft: '-5px' }"
                ></div>

                <div class="mx-auto flex h-28 w-28 items-center justify-center">
                    <svg
                        viewBox="0 0 64 64"
                        class="h-28 w-28"
                        fill="none"
                        stroke="currentColor"
                    >
                        <circle
                            ref="checkCircleRef"
                            cx="32"
                            cy="32"
                            r="28"
                            class="text-emerald-400"
                            stroke-width="5"
                            stroke-linecap="round"
                        />
                        <path
                            ref="checkPathRef"
                            d="M21 33.5 29.5 42 44 25"
                            class="text-emerald-300"
                            stroke-width="6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>

                <div ref="successTitleRef" class="space-y-1">
                    <p class="text-3xl font-black text-emerald-400">
                        +{{ claimedAmount || amount }} XP
                    </p>
                    <p class="text-sm font-semibold text-foreground">
                        Claimed! Keep your streak going 🔥
                    </p>
                    <p
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Streak {{ streak }}
                        <span v-if="amount > 1">
                            · {{ amount - 1 }} streak bonus
                        </span>
                    </p>
                </div>
            </div>

            <!-- Claim prompt -->
            <div v-else class="space-y-5 py-2">
                <div
                    class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-5 text-center"
                >
                    <p
                        class="text-xs font-black tracking-[0.18em] text-amber-400/70 uppercase"
                    >
                        You can claim
                    </p>
                    <p class="mt-2 text-4xl font-black text-amber-300">
                        +{{ amount }} XP
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        1 base XP
                        <span v-if="amount > 1">
                            + {{ amount - 1 }} streak bonus</span
                        >
                        <span class="text-amber-400">
                            · Streak {{ streak }}</span
                        >
                    </p>
                </div>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Claim now to add this reward to your XP total, or choose
                    Later and come back anytime today.
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-muted-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="claimState === 'claiming'"
                        @click="showClaimModal = false"
                    >
                        Later
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-w-[9.5rem] items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-black transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="claimState === 'claiming'"
                        @click="handleClaim"
                    >
                        <span
                            v-if="claimState === 'claiming'"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-black/30 border-t-black"
                        ></span>
                        <span>{{
                            claimState === 'claiming'
                                ? 'Claiming…'
                                : `Claim ${amount} XP`
                        }}</span>
                    </button>
                </div>
            </div>
        </ResponsiveModal>

        <!-- Particle burst container -->
        <div
            v-if="showParticles"
            ref="particlesRef"
            class="pointer-events-none absolute inset-0 z-50 flex items-center justify-center overflow-visible"
        >
            <div
                v-for="i in 12"
                :key="i"
                class="xp-particle absolute h-2.5 w-2.5 rounded-full"
                :class="[
                    i % 3 === 0
                        ? 'bg-amber-400'
                        : i % 3 === 1
                          ? 'bg-yellow-300'
                          : 'bg-orange-400',
                ]"
                :style="{
                    boxShadow:
                        '0 0 6px rgba(250,204,21,0.6), 0 0 12px rgba(250,204,21,0.3)',
                }"
            ></div>
        </div>

        <!-- Floating +XP badge (inline claims) -->
        <div
            v-if="showFloatingXp"
            ref="floatXpRef"
            class="pointer-events-none absolute inset-x-0 -top-3 z-50 flex justify-center"
        >
            <span
                class="rounded-full bg-gradient-to-r from-amber-500 to-yellow-400 px-3 py-1 text-sm font-black text-black shadow-lg shadow-amber-500/40"
            >
                +{{ claimedAmount || amount }} XP
            </span>
        </div>

        <!-- Claim button -->
        <button
            v-if="claimState === 'idle' || claimState === 'claiming'"
            ref="buttonRef"
            @click="handleClaim"
            :disabled="claimState === 'claiming'"
            class="group relative flex w-full cursor-pointer items-center gap-2 overflow-hidden rounded-xl border border-amber-400/30 bg-gradient-to-br from-amber-500/20 via-amber-400/10 to-yellow-500/20 px-3 py-2.5 text-left transition-all duration-300 hover:border-amber-400/60 hover:from-amber-500/30 hover:via-amber-400/20 hover:to-yellow-500/30 disabled:cursor-not-allowed disabled:opacity-70"
        >
            <!-- Background shimmer -->
            <div
                class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-amber-400/10 to-transparent transition-transform duration-1000 group-hover:translate-x-full"
            ></div>

            <div class="relative z-10 flex items-center gap-2.5">
                <!-- Icon -->
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/20 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6"
                >
                    <Gift
                        v-if="claimState === 'idle'"
                        class="h-4 w-4 text-amber-400"
                    />
                    <div
                        v-else
                        class="h-4 w-4 animate-spin rounded-full border-2 border-amber-400 border-t-transparent"
                    ></div>
                </div>

                <div>
                    <p
                        class="text-[10px] font-black tracking-[0.1em] text-amber-400/70 uppercase"
                    >
                        Daily Reward
                    </p>
                    <p class="text-sm font-bold text-amber-300">
                        <span v-if="claimState === 'claiming'">
                            Claiming…
                        </span>
                        <template v-else>
                            Claim
                            <span class="tabular-nums">{{ amount }}</span>
                            XP
                        </template>
                        <span
                            v-if="streak > 0"
                            class="text-[10px] font-medium text-amber-400/60"
                        >
                            · Streak {{ streak }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Arrow indicator -->
            <div
                class="relative z-10 ml-auto flex h-6 w-6 items-center justify-center rounded-full bg-amber-500/20 transition-all duration-300 group-hover:scale-110 group-hover:bg-amber-500/30"
            >
                <Sparkles class="h-3 w-3 text-amber-400" />
            </div>
        </button>

        <!-- Claimed state -->
        <div
            v-else-if="claimState === 'claimed'"
            class="flex w-full items-center gap-2.5 rounded-xl border border-emerald-400/20 bg-gradient-to-br from-emerald-500/10 via-emerald-400/5 to-green-500/10 px-3 py-2.5"
        >
            <div
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/20"
            >
                <Check class="h-4 w-4 text-emerald-400" />
            </div>
            <div class="flex-1">
                <p
                    class="text-[10px] font-black tracking-[0.1em] text-emerald-400/70 uppercase"
                >
                    Claimed
                </p>
                <p class="text-sm font-bold text-emerald-300">
                    +{{ claimedAmount || amount }} XP
                    <span class="text-[10px] font-medium text-emerald-400/60">
                        · {{ countdownText || 'Come back tomorrow!' }}
                    </span>
                </p>
            </div>
            <div
                class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20"
            >
                <Clock class="h-3 w-3 text-emerald-400" />
            </div>
        </div>
    </div>
</template>
