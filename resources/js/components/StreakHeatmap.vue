<script setup lang="ts">
import { Flame } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    loginDates: string[];
}

const props = withDefaults(defineProps<Props>(), { loginDates: () => [] });

/**
 * Normalize a date string to a YYYY-MM-DD key without timezone shifting.
 * Server loginDates are plain `DATE(created_at)` strings (YYYY-MM-DD); parsing
 * those via `new Date()` would interpret them as UTC midnight and shift the
 * day in non-UTC locales. Parse the date prefix directly instead.
 */
const toDayKey = (value: string): string | null => {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value.trim());
    if (match) return `${match[1]}-${match[2]}-${match[3]}`;
    // Fallback for full ISO timestamps (still read in local time).
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const activeDays = computed(() => {
    const set = new Set<string>();
    for (const raw of props.loginDates) {
        const key = toDayKey(raw);
        if (key) set.add(key);
    }
    return set;
});

// Last 28 days, oldest → newest, ending today. Each cell maps to one real day.
const cells = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Array.from({ length: 28 }, (_, i) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (27 - i));
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        const key = `${y}-${m}-${d}`;
        return {
            date,
            key,
            active: activeDays.value.has(key),
            isToday: i === 27,
        };
    });
});

const activeCount = computed(() => cells.value.filter((c) => c.active).length);
const last7ActiveCount = computed(
    () => cells.value.slice(-7).filter((c) => c.active).length,
);
const activeToday = computed(() => cells.value[27]?.active ?? false);

type Tone = 'amber' | 'emerald' | 'primary' | 'muted';

interface Encouragement {
    emoji: string;
    title: string;
    sub: string;
    tone: Tone;
}

// A warm, student-friendly pep talk that reacts to recent activity. Keeps the
// gamified, encouraging tone the rest of the dashboard already uses.
const encouragement = computed<Encouragement>(() => {
    const total = activeCount.value;
    const week = last7ActiveCount.value;
    const today = activeToday.value;

    if (total === 0) {
        return {
            emoji: '🌱',
            title: 'Your journey starts today',
            sub: 'Log in and finish a task to light up your first square.',
            tone: 'muted',
        };
    }
    if (today && week >= 5) {
        return {
            emoji: '🔥',
            title: "You're on fire!",
            sub: `${week} of the last 7 days — incredible consistency.`,
            tone: 'amber',
        };
    }
    if (today && week >= 3) {
        return {
            emoji: '✨',
            title: 'Great momentum!',
            sub: `${week} active days this week. Keep it up!`,
            tone: 'emerald',
        };
    }
    if (today) {
        return {
            emoji: '👏',
            title: 'Nice work today!',
            sub: "You showed up — that's how streaks begin.",
            tone: 'emerald',
        };
    }
    if (week >= 4) {
        return {
            emoji: '💪',
            title: 'Strong week!',
            sub: 'Pop in today to keep your momentum going.',
            tone: 'amber',
        };
    }
    return {
        emoji: '🎯',
        title: 'Ready when you are',
        sub: 'A quick visit today keeps your streak alive.',
        tone: 'primary',
    };
});

const toneClasses: Record<Tone, string> = {
    amber: 'bg-[#FF9F0A]/10',
    emerald: 'bg-[#34C759]/10',
    primary: 'bg-[#007AFF]/10',
    muted: 'bg-muted/40',
};

const dayLabel = (date: Date) =>
    date.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
</script>

<template>
    <div class="relative z-10 flex flex-col gap-3 sm:gap-4">
        <!-- Friendly, reactive encouragement banner -->
        <div
            class="flex items-center gap-3 rounded-[1.1rem] p-2.5 sm:p-3"
            :class="toneClasses[encouragement.tone]"
            role="status"
        >
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-background/60 text-lg leading-none shadow-inner sm:h-10 sm:w-10 sm:text-xl"
                aria-hidden="true"
            >
                {{ encouragement.emoji }}
            </span>
            <div class="min-w-0">
                <p
                    class="truncate text-[15px] font-semibold tracking-tight text-foreground"
                >
                    {{ encouragement.title }}
                </p>
                <p
                    class="mt-0.5 text-xs leading-snug text-muted-foreground sm:text-[13px]"
                >
                    {{ encouragement.sub }}
                </p>
            </div>
        </div>

        <template v-if="activeCount > 0">
            <!-- Grid -->
            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                <div
                    v-for="cell in cells"
                    :key="cell.key"
                    class="group/cell relative aspect-square rounded-md border transition-colors sm:rounded-lg"
                    :class="
                        cell.active
                            ? cell.isToday
                                ? 'border-[#007AFF] bg-[#007AFF] ring-2 ring-[#007AFF]/25 ring-offset-1 ring-offset-background'
                                : 'border-[#007AFF]/30 bg-[#007AFF]/70'
                            : 'border-border/15 bg-muted/20'
                    "
                >
                    <!-- Today marker dot -->
                    <span
                        v-if="cell.isToday && cell.active"
                        class="absolute top-1/2 left-1/2 h-1 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-background/80"
                        aria-hidden="true"
                    ></span>

                    <!-- Tooltip -->
                    <div
                        class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded-md bg-foreground px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-background opacity-0 shadow-xl transition-opacity group-hover/cell:opacity-100 sm:text-xs"
                        role="tooltip"
                    >
                        {{ dayLabel(cell.date) }} ·
                        {{ cell.active ? 'Active' : 'Rest day' }}
                    </div>
                </div>
            </div>
        </template>

        <!-- First-run / quiet state: honest, not a fake busy grid -->
        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-[1.1rem] border border-dashed border-border/40 bg-muted/20 px-4 py-7 text-center"
            role="status"
        >
            <Flame class="h-6 w-6 text-muted-foreground/50" />
            <p class="text-[15px] font-semibold tracking-tight text-foreground">
                No activity yet
            </p>
            <p
                class="max-w-[30ch] text-xs leading-snug text-muted-foreground sm:text-[13px]"
            >
                Exams, assignments, and daily claims will light up your grid —
                one square at a time.
            </p>
        </div>

        <!-- Legend -->
        <div
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-border/15 pt-3 text-xs text-muted-foreground sm:pt-4"
        >
            <span class="font-medium">
                <span class="font-bold text-foreground">{{ activeCount }}</span>
                of 28 days active
            </span>
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-1.5">
                    <span
                        class="h-3 w-3 rounded-sm border border-border/15 bg-muted/20"
                    ></span>
                    Rest
                </span>
                <span class="flex items-center gap-1.5">
                    <span
                        class="h-3 w-3 rounded-sm border border-primary/40 bg-gradient-to-br from-primary/80 to-primary/50"
                    ></span>
                    Active
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * Glows use color-mix against --color-primary so the "lit up" pulse actually
 * renders AND adapts to every theme (light, dark, and alternate presets).
 * The old rgba(var(--primary-rgb), …) form relied on a variable that was
 * never defined anywhere, so the glow silently never showed.
 */
.pulse-cell {
    transition:
        box-shadow 0.3s ease,
        transform 0.3s ease,
        background-color 0.3s ease,
        border-color 0.3s ease;
}

.pulse-cell--today {
    box-shadow: 0 0 14px
        color-mix(in srgb, var(--color-primary) 45%, transparent);
}

.pulse-cell--active {
    box-shadow: 0 0 10px
        color-mix(in srgb, var(--color-primary) 20%, transparent);
}

.pulse-cell--today:hover,
.pulse-cell--active:hover {
    box-shadow: 0 0 18px
        color-mix(in srgb, var(--color-primary) 55%, transparent);
}

/* Respect users who prefer less motion / lower-end devices */
@media (prefers-reduced-motion: reduce) {
    .pulse-cell {
        transition: none;
    }
}
</style>
