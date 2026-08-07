<script setup lang="ts">
import { ChevronRight, Sparkles, TrendingUp } from 'lucide-vue-next';
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

interface Props {
    userStats: UserStats;
    breakdown?: BreakdownEntry[];
}

const props = withDefaults(defineProps<Props>(), {
    breakdown: () => [],
});

const showBreakdown = ref(false);

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
    showBreakdown.value = true;
};
</script>

<template>
    <SpotlightCard
        customSize
        glowColor="purple"
        className="surface-card premium-hover group relative w-full min-w-0 cursor-pointer p-5 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none sm:p-6"
        :tabindex="0"
        :role="'button'"
        :aria-label="'Open your XP breakdown'"
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
                    Breakdown
                </span>
            </div>
        </div>

        <!-- XP Breakdown Modal -->
        <ResponsiveModal
            v-model="showBreakdown"
            title="How you accumulated XP"
            description="Activity from the active season"
            content-class="sm:max-w-lg"
            @close="showBreakdown = false"
        >
            <div class="space-y-3 py-2">
                <div
                    v-if="breakdown.length === 0"
                    class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                >
                    No activity has contributed to your XP total yet. Complete
                    lessons, assignments, and exams to start earning.
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
                <p class="pt-1 text-xs text-muted-foreground">
                    Showing activity from the active season.
                </p>
            </div>
        </ResponsiveModal>
    </SpotlightCard>
</template>
