<script setup lang="ts">
import axios from 'axios';
import gsap from 'gsap';
import { Gift, Sparkles, Check, Clock, Zap } from 'lucide-vue-next';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';

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

const claimState = ref<'idle' | 'claiming' | 'claimed' | 'waiting'>(
    props.canClaim ? 'idle' : 'claimed',
);
const claimedAmount = ref(0);
const showParticles = ref(false);
const buttonRef = ref<HTMLElement | null>(null);
const particlesRef = ref<HTMLElement | null>(null);
const countdownText = ref('');
const showClaimModal = ref(Boolean(props.showPrompt && props.canClaim));
let glowAnim: gsap.core.Tween | null = null;

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

// The particle burst animation
function triggerParticleBurst() {
    if (!particlesRef.value) return;
    showParticles.value = true;

    const particles = particlesRef.value.querySelectorAll('.xp-particle');
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
    );
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

            // Trigger the particle burst
            triggerParticleBurst();

            // Brief delay for the visual impact
            await new Promise((r) => setTimeout(r, 150));

            claimState.value = 'claimed';
            showClaimModal.value = false;

            // Notify parent to animate XP counter
            emit('claimed', data.amount, data.total_xp);
        } else {
            claimState.value = 'claimed'; // Already claimed
        }
    } catch {
        claimState.value = 'idle';
    }
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
});
</script>

<template>
    <div class="relative">
        <ResponsiveModal
            v-model="showClaimModal"
            title="Your daily XP reward is ready"
            description="Keep your streak going with today’s login reward."
            content-class="sm:max-w-md"
        >
            <div class="space-y-5 py-2">
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
                        class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-muted-foreground transition hover:bg-muted"
                        @click="showClaimModal = false"
                    >
                        Later
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-black transition hover:bg-amber-400 disabled:opacity-60"
                        :disabled="claimState === 'claiming'"
                        @click="handleClaim"
                    >
                        Claim {{ amount }} XP
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
                        Claim
                        <span class="tabular-nums">{{ amount }}</span>
                        XP
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
