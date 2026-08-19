<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Check,
    ChevronRight,
    FileText,
    Gift,
    GraduationCap,
    Settings2,
    Sparkles,
    Star,
    TrendingUp,
    Trophy,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';

interface UserStats {
    level: number;
    currentXP: number;
    maxXPForLevel: number;
}

interface BreakdownEntry {
    label: string;
    amount: number;
    count: number;
}

interface XpHistoryEntry {
    id: number;
    reason: string;
    description: string | null;
    amount: number;
    createdAt: string;
    isClaim: boolean;
}

interface ClaimInfo {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    baseXp?: number;
    nextClaimAt?: string | null;
    lastClaimedAt?: string | null;
}

interface Props {
    userStats: UserStats;
    breakdown?: BreakdownEntry[];
    xpHistory?: XpHistoryEntry[];
    claimXp?: ClaimInfo;
    bonusXp?: ClaimInfo;
}

const props = withDefaults(defineProps<Props>(), {
    breakdown: () => [],
    xpHistory: () => [],
    claimXp: undefined,
    bonusXp: undefined,
});

const showBreakdown = ref(false);
const activeTab = ref<'history' | 'summary'>('history');

const animLevel = useNumberAnimation(() => props.userStats.level);
const animXP = useNumberAnimation(() => props.userStats.currentXP);

const xpPercent = computed(() => {
    if (!props.userStats.maxXPForLevel) return 0;
    const percent =
        (props.userStats.currentXP / props.userStats.maxXPForLevel) * 100;
    return Math.min(100, Math.max(0, percent));
});

const xpToNext = computed(() =>
    Math.max(0, props.userStats.maxXPForLevel - props.userStats.currentXP),
);

const openBreakdown = () => {
    // Default to the freshest view each time the modal opens.
    activeTab.value = 'history';
    showBreakdown.value = true;
};

// ── Daily-claim status ("have I claimed today's XP?") ───────────────────────
interface ClaimStatus {
    state: 'available' | 'claimed' | 'never';
    amount: number;
    whenLabel?: string;
}

const claimStatus = computed<ClaimStatus | null>(() => {
    const c = props.claimXp;
    if (!c) return null;
    // Feature turned off in Platform Settings — no claim status at all.
    if (c.enabled === false) return null;
    if (c.canClaim) {
        if (!c.lastClaimedAt) {
            return { state: 'never', amount: c.amount };
        }
        return { state: 'available', amount: c.amount };
    }
    return {
        state: 'claimed',
        amount: c.amount,
        whenLabel: c.lastClaimedAt ? formatWhen(c.lastClaimedAt) : undefined,
    };
});

// ── Bonus-claim status ──────────────────────────────────────────────────────
const bonusStatus = computed<ClaimStatus | null>(() => {
    const b = props.bonusXp;
    if (!b) return null;
    if (b.enabled === false) return null;
    if (b.canClaim) {
        if (!b.lastClaimedAt) {
            return { state: 'never', amount: b.amount };
        }
        return { state: 'available', amount: b.amount };
    }
    return {
        state: 'claimed',
        amount: b.amount,
        whenLabel: b.lastClaimedAt ? formatWhen(b.lastClaimedAt) : undefined,
    };
});

// Local state for bonus claim interaction inside the modal
const bonusClaimState = ref<'idle' | 'claiming' | 'claimed'>('idle');
const bonusClaimedAmount = ref(0);
const bonusError = ref(false);
let bonusErrorTimer: ReturnType<typeof setTimeout> | null = null;

// Sync local state with prop
const syncBonusState = () => {
    const b = props.bonusXp;
    if (!b || b.enabled === false) {
        bonusClaimState.value = 'idle';
        return;
    }
    // If we just claimed locally, keep 'claimed' until props refresh
    if (bonusClaimState.value === 'claimed') return;
    bonusClaimState.value = b.canClaim ? 'idle' : 'claimed';
};

watch(
    () => props.bonusXp,
    () => syncBonusState(),
    { immediate: true, deep: true },
);

// Reset when modal opens (in case new day)
watch(showBreakdown, (open) => {
    if (open) syncBonusState();
});

const bonusDisplayStatus = computed<ClaimStatus | null>(() => {
    // If we locally marked as claimed, show claimed even though props may still say available until reload
    if (bonusClaimState.value === 'claimed') {
        const raw = bonusStatus.value;
        return {
            state: 'claimed' as const,
            amount:
                bonusClaimedAmount.value ||
                raw?.amount ||
                props.bonusXp?.amount ||
                0,
            whenLabel: 'Just now',
        };
    }
    return bonusStatus.value;
});

const isBonusAvailable = computed(() => {
    if (!bonusStatus.value) return false;
    if (bonusClaimState.value === 'claimed') return false;
    return bonusStatus.value.state !== 'claimed';
});

// ── Daily claim interaction inside the same modal ───────────────────────
// The dashboard also shows the daily claim as a standalone card, but the
// XP history modal deserves the same action so students don't have to close
// the modal, scroll, and hunt for the card. Local state mirrors the bonus
// flow so the UI flips to "claimed" instantly and stays there until the
// next Inertia reload brings fresh props.
const dailyClaimState = ref<'idle' | 'claiming' | 'claimed'>('idle');
const dailyClaimedAmount = ref(0);
const dailyError = ref(false);
let dailyErrorTimer: ReturnType<typeof setTimeout> | null = null;

const syncDailyState = () => {
    const c = props.claimXp;
    if (!c || c.enabled === false) {
        dailyClaimState.value = 'idle';
        return;
    }
    if (dailyClaimState.value === 'claimed') return;
    dailyClaimState.value = c.canClaim ? 'idle' : 'claimed';
};

watch(
    () => props.claimXp,
    () => syncDailyState(),
    { immediate: true, deep: true },
);

watch(showBreakdown, (open) => {
    if (open) syncDailyState();
});

const dailyDisplayStatus = computed<ClaimStatus | null>(() => {
    if (dailyClaimState.value === 'claimed') {
        const raw = claimStatus.value;
        return {
            state: 'claimed' as const,
            amount:
                dailyClaimedAmount.value ||
                raw?.amount ||
                props.claimXp?.amount ||
                0,
            whenLabel: 'Just now',
        };
    }
    return claimStatus.value;
});

const isDailyAvailable = computed(() => {
    if (!dailyDisplayStatus.value) return false;
    if (dailyClaimState.value === 'claimed') return false;
    return dailyDisplayStatus.value.state !== 'claimed';
});

async function handleDailyClaim() {
    if (dailyClaimState.value === 'claiming') return;
    if (!isDailyAvailable.value) return;
    dailyClaimState.value = 'claiming';
    dailyError.value = false;
    if (dailyErrorTimer) clearTimeout(dailyErrorTimer);

    try {
        const { data } = await axios.post<{
            claimed: boolean;
            amount: number;
            total_xp: number;
            streak: number;
        }>('/api/claim-xp', undefined, { timeout: 15000 });

        if (data.claimed) {
            dailyClaimedAmount.value = data.amount;
            dailyClaimState.value = 'claimed';
            router.reload({
                only: [
                    'claimXp',
                    'xpHistory',
                    'userStats',
                    'statsBreakdown',
                    'notifications',
                ] as any,
            });
        } else {
            dailyClaimState.value = 'claimed';
            dailyClaimedAmount.value =
                data.amount || props.claimXp?.amount || 0;
        }
    } catch {
        dailyClaimState.value = 'idle';
        dailyError.value = true;
        dailyErrorTimer = setTimeout(() => {
            dailyError.value = false;
        }, 4000);
    }
}

async function handleBonusClaim() {
    if (bonusClaimState.value === 'claiming') return;
    if (!isBonusAvailable.value) return;
    bonusClaimState.value = 'claiming';
    bonusError.value = false;
    if (bonusErrorTimer) clearTimeout(bonusErrorTimer);

    try {
        const { data } = await axios.post<{
            claimed: boolean;
            amount: number;
            total_xp: number;
            streak: number;
        }>('/api/claim-bonus-xp', undefined, { timeout: 15000 });

        if (data.claimed) {
            bonusClaimedAmount.value = data.amount;
            bonusClaimState.value = 'claimed';
            // Refresh surrounding stats without waiting for poll
            router.reload({
                only: [
                    'bonusXp',
                    'xpHistory',
                    'userStats',
                    'statsBreakdown',
                    'notifications',
                ] as any,
            });
        } else {
            // Already claimed (race)
            bonusClaimState.value = 'claimed';
            bonusClaimedAmount.value =
                data.amount || props.bonusXp?.amount || 0;
        }
    } catch {
        bonusClaimState.value = 'idle';
        bonusError.value = true;
        bonusErrorTimer = setTimeout(() => {
            bonusError.value = false;
        }, 4000);
    }
}

// ── Reason → icon / label / tone mapping ────────────────────────────────────
type Tone = 'amber' | 'sky' | 'emerald' | 'violet' | 'zinc' | 'primary';

interface ReasonMeta {
    icon: typeof Zap;
    label: string;
    tone: Tone;
}

const reasonMeta = (reason: string): ReasonMeta => {
    const r = (reason || '').toLowerCase();
    if (r === 'bonus claim')
        return { icon: Star, label: 'Bonus Claim', tone: 'violet' };
    if (r === 'daily claim')
        return { icon: Gift, label: 'Daily Claim', tone: 'amber' };
    if (r.includes('exam'))
        return { icon: FileText, label: 'Exam', tone: 'sky' };
    if (r.includes('enroll') || r.includes('section'))
        return { icon: GraduationCap, label: 'Enrollment', tone: 'emerald' };
    if (r.includes('season reward'))
        return { icon: Trophy, label: 'Season Reward', tone: 'amber' };
    if (r.includes('season'))
        return { icon: Sparkles, label: 'Season', tone: 'violet' };
    if (r.includes('admin') || r.includes('manual') || r.includes('adjust'))
        return { icon: Settings2, label: 'Adjustment', tone: 'zinc' };
    return { icon: Zap, label: reason || 'Activity', tone: 'primary' };
};

const toneChip: Record<Tone, string> = {
    amber: 'bg-[#E0AF68]/15 text-[#E0AF68] dark:text-[#E0AF68]',
    sky: 'bg-[#D97757]/15 text-[#D97757] dark:text-[#D97757]',
    emerald: 'bg-[#4D9375]/15 text-[#4D9375] dark:text-[#4D9375]',
    violet: 'bg-[#9D7CD8]/15 text-[#9D7CD8] dark:text-[#9D7CD8]',
    zinc: 'bg-zinc-500/15 text-zinc-500 dark:text-zinc-400',
    primary: 'bg-primary/15 text-primary',
};

const signedAmount = (n: number) =>
    n > 0
        ? `+${Math.round(n).toLocaleString()}`
        : `${Math.round(n).toLocaleString()}`;

function formatWhen(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return `${d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} · ${d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
}
</script>

<template>
    <div
        class="surface-card group relative w-full min-w-0 cursor-pointer p-3.5 transition-colors focus-visible:ring-2 focus-visible:ring-[#D97757]/40 focus-visible:outline-none active:bg-muted/30 sm:p-6"
        tabindex="0"
        role="button"
        aria-label="Open your XP history"
        @click="openBreakdown"
        @keydown.enter.prevent="openBreakdown"
        @keydown.space.prevent="openBreakdown"
    >
        <div
            class="relative z-10 flex h-full w-full flex-col justify-between gap-3 sm:gap-5"
        >
            <!-- Header: Level -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="dash-icon-well bg-[#D97757]/10 text-[#D97757]">
                        <TrendingUp class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="dash-label">Level</p>
                        <div class="flex items-baseline gap-1.5">
                            <h3
                                class="dash-metric text-[28px] leading-none text-foreground sm:text-4xl"
                            >
                                {{ animLevel }}
                            </h3>
                        </div>
                    </div>
                </div>
                <ChevronRight
                    class="h-5 w-5 shrink-0 text-muted-foreground/30 transition-all duration-300 group-hover:translate-x-1 group-hover:text-primary"
                />
            </div>

            <!-- XP Bar -->
            <div class="space-y-2">
                <div
                    class="relative h-2 w-full overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="relative h-full rounded-full bg-[#D97757] transition-[width] duration-700 ease-out"
                        :style="{ width: `${xpPercent}%` }"
                    />
                </div>
                <div
                    class="flex items-baseline justify-between gap-2 text-[13px] font-medium tabular-nums"
                >
                    <span class="text-muted-foreground"
                        >{{ Math.round(animXP).toLocaleString() }} /
                        {{ props.userStats.maxXPForLevel.toLocaleString() }}
                        XP</span
                    >
                    <span class="text-primary"
                        >{{ Math.round(xpPercent) }}%</span
                    >
                </div>
            </div>

            <!-- Footer: next-level nudge -->
            <div
                class="flex items-center justify-between border-t border-border/10 pt-3"
            >
                <p class="text-xs text-muted-foreground">
                    <span class="font-semibold text-foreground"
                        >{{ xpToNext.toLocaleString() }} XP</span
                    >
                    to Level {{ userStats.level + 1 }}
                </p>
                <span
                    class="flex items-center gap-1 text-[13px] font-medium text-[#D97757]"
                >
                    History
                </span>
            </div>
        </div>

        <!-- XP History Modal -->
        <ResponsiveModal
            v-model="showBreakdown"
            title="Your XP history"
            description="How you earned your XP this season"
            content-class="sm:max-w-lg"
            @close="showBreakdown = false"
        >
            <div class="space-y-4 py-2">
                <!-- Daily-claim status: "have I claimed today?" — now with an inline Claim button so students don't need to hunt the dashboard card -->
                <div
                    v-if="dailyDisplayStatus"
                    class="flex items-center gap-3 rounded-xl border p-3"
                    :class="
                        dailyDisplayStatus.state === 'claimed'
                            ? 'border-[#4D9375]/30 bg-[#4D9375]/10'
                            : 'border-[#E0AF68]/30 bg-[#E0AF68]/10'
                    "
                    role="status"
                    aria-label="Daily XP"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            dailyDisplayStatus.state === 'claimed'
                                ? 'bg-[#4D9375]/15 text-[#4D9375] dark:text-[#4D9375]'
                                : 'bg-[#E0AF68]/15 text-[#E0AF68] dark:text-[#E0AF68]'
                        "
                    >
                        <Check
                            v-if="dailyDisplayStatus.state === 'claimed'"
                            class="h-5 w-5"
                        />
                        <Gift v-else class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm font-bold tracking-tight text-foreground"
                        >
                            <template
                                v-if="dailyDisplayStatus.state === 'claimed'"
                            >
                                Today's daily XP claimed
                            </template>
                            <template
                                v-else-if="dailyDisplayStatus.state === 'never'"
                            >
                                Your first daily XP is ready
                            </template>
                            <template v-else>
                                Daily XP ready to claim
                            </template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template
                                v-if="dailyDisplayStatus.state === 'claimed'"
                            >
                                +{{ dailyDisplayStatus.amount }} XP
                                <span v-if="dailyDisplayStatus.whenLabel"
                                    >· {{ dailyDisplayStatus.whenLabel }}</span
                                >
                            </template>
                            <template v-else>
                                +{{ dailyDisplayStatus.amount }} XP available —
                                claim it here.
                            </template>
                        </p>
                        <p
                            v-if="dailyError"
                            class="mt-1 text-[11px] font-semibold text-[#CB7676]"
                        >
                            Couldn’t claim — check connection and try again.
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="dailyDisplayStatus.state === 'claimed'"
                            class="text-sm font-semibold text-[#4D9375] tabular-nums dark:text-[#4D9375]"
                        >
                            +{{ dailyDisplayStatus.amount }}
                        </span>
                        <template v-else>
                            <span
                                class="hidden text-sm font-semibold text-[#E0AF68] tabular-nums sm:inline dark:text-[#E0AF68]"
                            >
                                +{{ dailyDisplayStatus.amount }}
                            </span>
                            <button
                                type="button"
                                :disabled="dailyClaimState === 'claiming'"
                                class="inline-flex min-w-[7.5rem] items-center justify-center gap-1.5 rounded-lg bg-[#D97757] px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#D97757]/90 disabled:cursor-not-allowed disabled:opacity-60"
                                @click.stop="handleDailyClaim"
                            >
                                <span
                                    v-if="dailyClaimState === 'claiming'"
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white"
                                ></span>
                                <Gift v-else class="h-3.5 w-3.5" />
                                {{
                                    dailyClaimState === 'claiming'
                                        ? 'Claiming…'
                                        : `Claim ${dailyDisplayStatus.amount} XP`
                                }}
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Bonus XP claim: second daily reward inside the same modal -->
                <div
                    v-if="bonusDisplayStatus"
                    class="flex items-center gap-3 rounded-xl border p-3"
                    :class="
                        bonusDisplayStatus.state === 'claimed'
                            ? 'border-[#4D9375]/30 bg-[#4D9375]/10'
                            : 'border-[#9D7CD8]/30 bg-[#9D7CD8]/10'
                    "
                    role="status"
                    aria-label="Bonus XP"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            bonusDisplayStatus.state === 'claimed'
                                ? 'bg-[#4D9375]/15 text-[#4D9375] dark:text-[#4D9375]'
                                : 'bg-[#9D7CD8]/15 text-[#9D7CD8] dark:text-[#9D7CD8]'
                        "
                    >
                        <Check
                            v-if="bonusDisplayStatus.state === 'claimed'"
                            class="h-5 w-5"
                        />
                        <Star v-else class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm font-bold tracking-tight text-foreground"
                        >
                            <template
                                v-if="bonusDisplayStatus.state === 'claimed'"
                            >
                                Bonus XP claimed
                            </template>
                            <template
                                v-else-if="bonusDisplayStatus.state === 'never'"
                            >
                                Your bonus XP is ready
                            </template>
                            <template v-else>
                                Bonus XP ready to claim
                            </template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template
                                v-if="bonusDisplayStatus.state === 'claimed'"
                            >
                                +{{ bonusDisplayStatus.amount }} XP
                                <span v-if="bonusDisplayStatus.whenLabel"
                                    >· {{ bonusDisplayStatus.whenLabel }}</span
                                >
                            </template>
                            <template v-else>
                                +{{ bonusDisplayStatus.amount }} XP available —
                                claim it below.
                            </template>
                        </p>
                        <p
                            v-if="bonusError"
                            class="mt-1 text-[11px] font-semibold text-[#CB7676]"
                        >
                            Couldn’t claim — check connection and try again.
                        </p>
                    </div>
                    <!-- Right side: amount + claim button or claimed badge -->
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="bonusDisplayStatus.state === 'claimed'"
                            class="text-sm font-semibold text-[#4D9375] tabular-nums dark:text-[#4D9375]"
                        >
                            +{{ bonusDisplayStatus.amount }}
                        </span>
                        <template v-else>
                            <span
                                class="hidden text-sm font-semibold text-[#9D7CD8] tabular-nums sm:inline dark:text-[#9D7CD8]"
                            >
                                +{{ bonusDisplayStatus.amount }}
                            </span>
                            <button
                                type="button"
                                :disabled="bonusClaimState === 'claiming'"
                                class="inline-flex min-w-[7.5rem] items-center justify-center gap-1.5 rounded-lg bg-[#9D7CD8] px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#9D7CD8]/90 disabled:cursor-not-allowed disabled:opacity-60"
                                @click.stop="handleBonusClaim"
                            >
                                <span
                                    v-if="bonusClaimState === 'claiming'"
                                    class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white"
                                ></span>
                                <Sparkles v-else class="h-3.5 w-3.5" />
                                {{
                                    bonusClaimState === 'claiming'
                                        ? 'Claiming…'
                                        : `Claim ${bonusDisplayStatus.amount} XP`
                                }}
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Tabs -->
                <div
                    class="flex gap-1 rounded-xl border border-border/40 bg-muted/30 p-1"
                >
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="
                            activeTab === 'history'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="activeTab === 'history'"
                        @click="activeTab = 'history'"
                    >
                        History
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="
                            activeTab === 'summary'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="activeTab === 'summary'"
                        @click="activeTab = 'summary'"
                    >
                        Summary
                    </button>
                </div>

                <!-- History tab: per-entry ledger -->
                <div
                    v-if="activeTab === 'history'"
                    data-lenis-prevent
                    class="max-h-[50vh] space-y-2 overflow-y-auto overscroll-contain pr-1"
                >
                    <div
                        v-if="xpHistory.length === 0"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                    >
                        No XP earned yet this season. Complete exams,
                        assignments, and daily claims to fill up your history.
                    </div>
                    <div
                        v-for="entry in xpHistory"
                        :key="entry.id"
                        class="flex items-center gap-3 rounded-xl border border-border/60 bg-muted/20 px-3 py-2.5"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            :class="toneChip[reasonMeta(entry.reason).tone]"
                        >
                            <component
                                :is="reasonMeta(entry.reason).icon"
                                class="h-4 w-4"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ reasonMeta(entry.reason).label }}
                                </p>
                                <span
                                    v-if="entry.isClaim"
                                    class="rounded-full border border-[#E0AF68]/30 bg-[#E0AF68]/10 px-1.5 py-0.5 text-[12px] font-medium text-[#E0AF68] dark:text-[#E0AF68]"
                                >
                                    Claimed
                                </span>
                            </div>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    entry.description ||
                                    formatWhen(entry.createdAt)
                                }}
                            </p>
                            <p
                                v-if="entry.description"
                                class="text-[11px] text-muted-foreground/70"
                            >
                                {{ formatWhen(entry.createdAt) }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-semibold tabular-nums"
                            :class="
                                entry.amount >= 0
                                    ? 'text-[#4D9375] dark:text-[#4D9375]'
                                    : 'text-[#CB7676] dark:text-[#CB7676]'
                            "
                        >
                            {{ signedAmount(entry.amount) }}
                        </span>
                    </div>
                </div>

                <!-- Summary tab: aggregated by category -->
                <div
                    v-else
                    data-lenis-prevent
                    class="max-h-[50vh] space-y-2 overflow-y-auto overscroll-contain pr-1"
                >
                    <div
                        v-if="breakdown.length === 0"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                    >
                        No activity has contributed to your XP total yet.
                        Complete lessons, assignments, and exams to start
                        earning.
                    </div>
                    <div
                        v-for="entry in breakdown"
                        :key="entry.label"
                        class="flex items-center justify-between rounded-xl border border-border/60 bg-muted/20 px-4 py-3"
                    >
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                {{ entry.label }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.count }}
                                {{ entry.count === 1 ? 'entry' : 'entries' }}
                            </p>
                        </div>
                        <span class="font-semibold text-[#D97757] tabular-nums"
                            >+{{ entry.amount.toLocaleString() }} XP</span
                        >
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing activity from the active season.
                </p>
            </div>
        </ResponsiveModal>
    </div>
</template>
