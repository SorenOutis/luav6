<script setup lang="ts">
import {
    Check,
    ChevronRight,
    FileText,
    Gift,
    GraduationCap,
    Settings2,
    Sparkles,
    TrendingUp,
    Trophy,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { SpotlightCard } from '@/components/ui/spotlight-card';
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
    canClaim: boolean;
    amount: number;
    nextClaimAt?: string | null;
    lastClaimedAt?: string | null;
}

interface Props {
    userStats: UserStats;
    breakdown?: BreakdownEntry[];
    xpHistory?: XpHistoryEntry[];
    claimXp?: ClaimInfo;
}

const props = withDefaults(defineProps<Props>(), {
    breakdown: () => [],
    xpHistory: () => [],
    claimXp: undefined,
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

// ── Reason → icon / label / tone mapping ────────────────────────────────────
type Tone = 'amber' | 'sky' | 'emerald' | 'violet' | 'zinc' | 'primary';

interface ReasonMeta {
    icon: typeof Zap;
    label: string;
    tone: Tone;
}

const reasonMeta = (reason: string): ReasonMeta => {
    const r = (reason || '').toLowerCase();
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
    amber: 'bg-amber-500/15 text-amber-500 dark:text-amber-400',
    sky: 'bg-sky-500/15 text-sky-500 dark:text-sky-400',
    emerald: 'bg-emerald-500/15 text-emerald-500 dark:text-emerald-400',
    violet: 'bg-violet-500/15 text-violet-500 dark:text-violet-400',
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
    <SpotlightCard
        customSize
        glowColor="purple"
        className="surface-card premium-hover group relative w-full min-w-0 cursor-pointer p-5 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none sm:p-6"
        :tabindex="0"
        :role="'button'"
        :aria-label="'Open your XP history'"
        @click="openBreakdown"
        @keydown.enter.prevent="openBreakdown"
        @keydown.space.prevent="openBreakdown"
    >
        <div
            class="relative z-10 flex h-full w-full flex-col justify-between gap-5"
        >
            <!-- Header: Level -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110"
                    >
                        <TrendingUp class="h-5 w-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-black tracking-[0.2em] text-muted-foreground/70 uppercase"
                        >
                            Level
                        </p>
                        <div class="flex items-baseline gap-1.5">
                            <h3
                                class="premium-gradient-text text-3xl leading-none font-black tracking-tighter tabular-nums sm:text-4xl"
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
                    class="relative h-3 w-full overflow-hidden rounded-full border border-white/10 bg-muted/40 shadow-inner sm:h-3.5 dark:bg-black/30"
                >
                    <div
                        class="relative h-full rounded-full bg-gradient-to-r from-primary via-primary/90 to-primary/70 shadow-lg shadow-primary/40 transition-[width] duration-1000 ease-out"
                        :style="{ width: `${xpPercent}%` }"
                    />
                </div>
                <div
                    class="flex items-baseline justify-between gap-2 text-[11px] font-bold tabular-nums"
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
                    <span class="font-black text-foreground"
                        >{{ xpToNext.toLocaleString() }} XP</span
                    >
                    to Level {{ userStats.level + 1 }}
                </p>
                <span
                    class="flex items-center gap-1 text-[10px] font-black tracking-widest text-primary uppercase"
                >
                    <Sparkles class="h-3 w-3" />
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
                <!-- Daily-claim status: "have I claimed today?" -->
                <div
                    v-if="claimStatus"
                    class="flex items-center gap-3 rounded-xl border p-3"
                    :class="
                        claimStatus.state === 'claimed'
                            ? 'border-emerald-500/30 bg-emerald-500/10'
                            : 'border-amber-500/30 bg-amber-500/10'
                    "
                    role="status"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            claimStatus.state === 'claimed'
                                ? 'bg-emerald-500/15 text-emerald-500 dark:text-emerald-400'
                                : 'bg-amber-500/15 text-amber-500 dark:text-amber-400'
                        "
                    >
                        <Check
                            v-if="claimStatus.state === 'claimed'"
                            class="h-5 w-5"
                        />
                        <Gift v-else class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm font-bold tracking-tight text-foreground"
                        >
                            <template v-if="claimStatus.state === 'claimed'">
                                Today's daily XP claimed
                            </template>
                            <template v-else-if="claimStatus.state === 'never'">
                                Your first daily XP is ready
                            </template>
                            <template v-else>
                                Daily XP ready to claim
                            </template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="claimStatus.state === 'claimed'">
                                +{{ claimStatus.amount }} XP
                                <span v-if="claimStatus.whenLabel"
                                    >· {{ claimStatus.whenLabel }}</span
                                >
                            </template>
                            <template v-else>
                                +{{ claimStatus.amount }} XP available — claim
                                it from the daily reward card.
                            </template>
                        </p>
                    </div>
                    <span
                        class="shrink-0 text-sm font-black tabular-nums"
                        :class="
                            claimStatus.state === 'claimed'
                                ? 'text-emerald-500 dark:text-emerald-400'
                                : 'text-amber-500 dark:text-amber-400'
                        "
                    >
                        +{{ claimStatus.amount }}
                    </span>
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
                    class="max-h-[50vh] space-y-2 overflow-y-auto pr-1"
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
                                    class="rounded-full border border-amber-400/30 bg-amber-400/10 px-1.5 py-0.5 text-[9px] font-black tracking-wide text-amber-500 uppercase dark:text-amber-400"
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
                            class="shrink-0 text-sm font-black tabular-nums"
                            :class="
                                entry.amount >= 0
                                    ? 'text-emerald-500 dark:text-emerald-400'
                                    : 'text-rose-500 dark:text-rose-400'
                            "
                        >
                            {{ signedAmount(entry.amount) }}
                        </span>
                    </div>
                </div>

                <!-- Summary tab: aggregated by category -->
                <div v-else class="space-y-2">
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
                        <span class="font-black text-purple-400 tabular-nums"
                            >+{{ entry.amount.toLocaleString() }} XP</span
                        >
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing activity from the active season.
                </p>
            </div>
        </ResponsiveModal>
    </SpotlightCard>
</template>
