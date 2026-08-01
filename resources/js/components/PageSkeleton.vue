<script setup lang="ts">
import { computed } from 'vue';

interface SkeletonCard {
    /** Number of skeleton lines inside each card */
    lines?: number;
    /** Whether to show an avatar circle */
    avatar?: boolean;
    /** Whether to show a badge/pill */
    badge?: boolean;
    /** Additional classes for this card */
    cardClass?: string;
}

const widths = [
    '60%',
    '75%',
    '50%',
    '85%',
    '65%',
    '55%',
    '80%',
    '70%',
] as const;
const cardsWidths = ['72%', '85%', '65%', '78%', '92%', '60%'] as const;

const props = withDefaults(
    defineProps<{
        /** Layout variant */
        variant?: 'default' | 'minimal' | 'cards' | 'list' | 'hero-list';
        /** Number of skeleton blocks/pages */
        count?: number;
        /** Show hero/header section */
        hero?: boolean;
        /** Hero subtitle line */
        subtitle?: boolean;
        /** Hero action buttons */
        actions?: number;
        /** Stats cards row */
        stats?: number;
        /** Custom card configuration */
        cards?: SkeletonCard;
        /** Additional classes for the wrapper */
        wrapperClass?: string;
    }>(),
    {
        variant: 'default',
        count: 3,
        hero: false,
        subtitle: false,
        actions: 0,
        stats: 0,
        cards: () => ({ lines: 3, avatar: false, badge: false }),
        wrapperClass: '',
    },
);

const statsGridClass = computed(() => {
    const map: Record<number, string> = {
        1: 'md:grid-cols-1',
        2: 'md:grid-cols-2',
        3: 'md:grid-cols-3',
        4: 'md:grid-cols-4',
    };
    return map[props.stats] || 'md:grid-cols-3';
});
</script>

<template>
    <div :class="['flex flex-col gap-6', wrapperClass]">
        <!-- Hero Section -->
        <div v-if="hero" class="flex flex-col gap-4">
            <div class="flex items-start justify-between">
                <div class="flex flex-col gap-3">
                    <div
                        class="h-6 w-56 animate-pulse rounded-md bg-primary/10"
                    ></div>
                    <div
                        v-if="subtitle"
                        class="h-4 w-80 animate-pulse rounded-md bg-primary/10"
                    ></div>
                </div>
                <!-- Action buttons in hero -->
                <div v-if="actions > 0" class="flex gap-2">
                    <div
                        v-for="a in actions"
                        :key="a"
                        class="h-9 w-24 animate-pulse rounded-lg bg-primary/10"
                    ></div>
                </div>
            </div>
            <!-- Hero stat pills -->
            <div class="flex flex-wrap gap-3">
                <div
                    v-for="p in 3"
                    :key="p"
                    class="h-8 w-20 animate-pulse rounded-lg bg-primary/10"
                ></div>
            </div>
        </div>

        <!-- Stats Row -->
        <div
            v-if="stats > 0"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            :class="statsGridClass"
        >
            <div
                v-for="s in stats"
                :key="s"
                class="flex flex-col gap-2 rounded-xl border border-border/10 bg-card/30 p-4"
            >
                <div class="h-3 w-16 animate-pulse rounded bg-primary/10"></div>
                <div class="h-7 w-20 animate-pulse rounded bg-primary/10"></div>
                <div
                    class="h-2 w-full animate-pulse rounded bg-primary/10"
                ></div>
            </div>
        </div>

        <!-- ===== Variants ===== -->

        <!-- Default: simple list of rows -->
        <div v-if="variant === 'default'" class="flex flex-col gap-3">
            <div
                v-for="i in count"
                :key="i"
                class="flex items-center gap-4 rounded-xl border border-border/10 bg-card/30 p-4"
            >
                <div
                    v-if="cards.avatar"
                    class="h-10 w-10 animate-pulse rounded-full bg-primary/10"
                ></div>
                <div class="flex flex-1 flex-col gap-2">
                    <div
                        class="h-4 animate-pulse rounded bg-primary/10"
                        :style="{ width: widths[i % widths.length] }"
                    ></div>
                    <div
                        v-for="l in Math.max(1, cards.lines - 1)"
                        :key="l"
                        class="h-3 animate-pulse rounded bg-primary/10"
                        :style="{ width: widths[(i + l + 1) % widths.length] }"
                    ></div>
                </div>
                <div
                    v-if="cards.badge"
                    class="h-6 w-16 animate-pulse rounded-md bg-primary/10"
                ></div>
            </div>
        </div>

        <!-- Minimal: just simple pulsing lines -->
        <div v-if="variant === 'minimal'" class="flex flex-col gap-4 px-1">
            <div
                v-for="i in count"
                :key="i"
                class="h-4 animate-pulse rounded bg-primary/10"
                :style="{ width: widths[i % widths.length] }"
            ></div>
        </div>

        <!-- Cards: grid of cards -->
        <div
            v-if="variant === 'cards'"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="i in count"
                :key="i"
                class="flex flex-col gap-3 rounded-xl border border-border/10 bg-card/30 p-5"
                :class="cards.cardClass"
            >
                <div
                    v-if="cards.badge"
                    class="h-5 w-16 animate-pulse rounded bg-primary/10"
                ></div>
                <div
                    class="h-5 animate-pulse rounded bg-primary/10"
                    :style="{ width: cardsWidths[i % cardsWidths.length] }"
                ></div>
                <div
                    v-for="l in cards.lines"
                    :key="l"
                    class="h-3 animate-pulse rounded bg-primary/10"
                    :style="{
                        width: cardsWidths[(i + l + 1) % cardsWidths.length],
                    }"
                ></div>
            </div>
        </div>

        <!-- List: like default but with left number/icon indicator -->
        <div v-if="variant === 'list'" class="flex flex-col gap-1">
            <div
                v-for="i in count"
                :key="i"
                class="flex items-center gap-4 rounded-xl border border-border/10 bg-card/30 px-4 py-3.5"
            >
                <div class="flex h-8 w-8 items-center justify-center">
                    <div
                        class="h-5 w-5 animate-pulse rounded bg-primary/10"
                    ></div>
                </div>
                <div class="flex flex-1 flex-col gap-1.5">
                    <div
                        class="h-4 animate-pulse rounded bg-primary/10"
                        :style="{ width: widths[i % widths.length] }"
                    ></div>
                    <div
                        class="h-3 animate-pulse rounded bg-primary/10"
                        :style="{ width: widths[(i + 2) % widths.length] }"
                    ></div>
                </div>
                <div
                    class="h-6 w-14 animate-pulse rounded-md bg-primary/10"
                ></div>
            </div>
        </div>

        <!-- Hero-List: hero section + list -->
        <div v-if="variant === 'hero-list'" class="flex flex-col gap-6">
            <!-- Sub-header with tabs-like skeleton -->
            <div class="flex gap-6 border-b border-border/10 pb-3">
                <div
                    v-for="t in 3"
                    :key="t"
                    class="h-5 w-20 animate-pulse rounded bg-primary/10"
                ></div>
            </div>
            <div class="flex flex-col gap-3">
                <div
                    v-for="i in count"
                    :key="i"
                    class="flex items-center gap-4 rounded-xl border border-border/10 bg-card/30 p-4"
                >
                    <div class="flex flex-1 flex-col gap-2">
                        <div
                            class="h-4 animate-pulse rounded bg-primary/10"
                            :style="{ width: widths[i % widths.length] }"
                        ></div>
                        <div
                            class="h-3 animate-pulse rounded bg-primary/10"
                            :style="{ width: widths[(i + 3) % widths.length] }"
                        ></div>
                    </div>
                    <div
                        class="h-8 w-20 animate-pulse rounded-lg bg-primary/10"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
