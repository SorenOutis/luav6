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

const dayLabel = (date: Date) =>
    date.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
</script>

<template>
    <div class="relative z-10 flex flex-col gap-2 sm:gap-3">
        <template v-if="activeCount > 0">
            <!-- Grid -->
            <div class="grid grid-cols-7 gap-1 sm:gap-2">
                <div
                    v-for="cell in cells"
                    :key="cell.key"
                    class="group/cell relative aspect-square cursor-pointer rounded-sm border transition-all duration-300 hover:scale-110 sm:rounded-md"
                    :class="
                        cell.active
                            ? cell.isToday
                                ? 'border-primary bg-primary shadow-[0_0_14px_rgba(var(--primary-rgb),0.45)]'
                                : 'border-primary/40 bg-primary/70 shadow-[0_0_10px_rgba(var(--primary-rgb),0.2)]'
                            : 'border-border/10 bg-muted/10'
                    "
                >
                    <!-- Tooltip -->
                    <div
                        class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded bg-foreground px-2 py-1 text-[8px] font-black tracking-widest whitespace-nowrap text-background uppercase opacity-0 shadow-2xl transition-opacity group-hover/cell:opacity-100 sm:text-[9px]"
                    >
                        {{ dayLabel(cell.date) }} ·
                        {{ cell.active ? 'Active' : 'Standby' }}
                    </div>
                </div>
            </div>
        </template>

        <!-- First-run / quiet state: honest, not a fake busy grid -->
        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/20 bg-muted/10 py-6 text-center"
            role="status"
        >
            <Flame class="h-5 w-5 text-muted-foreground/40" />
            <p class="text-[10px] font-black tracking-[0.2em] uppercase">
                No activity yet
            </p>
            <p
                class="max-w-[26ch] text-[10px] leading-snug text-muted-foreground/70"
            >
                Complete exams, assignments, and daily claims to light up your
                heatmap.
            </p>
        </div>

        <!-- Legend -->
        <div
            class="mt-3 flex items-center justify-between border-t border-border/10 pt-3 text-[8px] font-black tracking-widest text-muted-foreground/40 uppercase sm:mt-4 sm:pt-4 sm:text-[9px]"
        >
            <span>{{
                activeCount > 0 ? `${activeCount} active days` : 'Standby'
            }}</span>
            <div class="flex items-center gap-1 sm:gap-2">
                <div
                    class="h-2 w-2 rounded-[2px] border border-border/10 bg-muted/10 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
                <div
                    class="h-2 w-2 rounded-[2px] border border-primary/40 bg-primary/70 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
            </div>
            <span>Active</span>
        </div>
    </div>
</template>
