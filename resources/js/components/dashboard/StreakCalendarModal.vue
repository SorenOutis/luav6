<script setup lang="ts">
import { ref, computed } from 'vue';
import { Flame, ChevronLeft, ChevronRight, Check } from 'lucide-vue-next';
import ResponsiveModal from '@/components/ResponsiveModal.vue';

const props = defineProps<{
    open: boolean;
    loginDates: string[];
    currentStreak: number;
    longestStreak: number;
}>();

const emit = defineEmits<{
    close: [];
}>();

// ── Date helpers ──────────────────────────────────────────────
const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function localDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

// ── Calendar state ────────────────────────────────────────────
const now = new Date();
const viewMonth = ref(now.getMonth());
const viewYear = ref(now.getFullYear());

const loginDateSet = computed(() => new Set(props.loginDates));

// Calendar grid: 6 rows x 7 columns
const calendarCells = computed(() => {
    const firstDay = new Date(viewYear.value, viewMonth.value, 1);
    const startDow = firstDay.getDay(); // 0=Sun
    const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate();

    const cells: { dateStr: string; dayNum: number; visible: boolean; isActive: boolean; isToday: boolean }[] = [];

    // Leading blanks
    for (let i = 0; i < startDow; i++) {
        cells.push({ dateStr: '', dayNum: 0, visible: false, isActive: false, isToday: false });
    }

    const today = localDateStr(new Date());

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${viewYear.value}-${String(viewMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({
            dateStr,
            dayNum: d,
            visible: true,
            isActive: loginDateSet.value.has(dateStr),
            isToday: dateStr === today,
        });
    }

    // Trailing blanks to fill 42 cells (6 rows)
    while (cells.length < 42) {
        cells.push({ dateStr: '', dayNum: 0, visible: false, isActive: false, isToday: false });
    }

    return cells;
});

const viewTitle = computed(() => `${monthNames[viewMonth.value]} ${viewYear.value}`);

// Active days this month
const activeDaysThisMonth = computed(() => {
    return calendarCells.value.filter(c => c.visible && c.isActive).length;
});

// Navigation
const canGoForward = computed(() => {
    const today = new Date();
    return !(viewMonth.value === today.getMonth() && viewYear.value === today.getFullYear());
});

function prevMonth() {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value--;
    } else {
        viewMonth.value--;
    }
}

function nextMonth() {
    if (!canGoForward.value) return;
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value++;
    } else {
        viewMonth.value++;
    }
}

function handleClose() {
    emit('close');
}

// Motivational message
const motivationalMessage = computed(() => {
    const s = props.currentStreak;
    if (s === 0) return { text: "Let's start a new streak today!", emoji: '🌱' };
    if (s <= 2) return { text: 'Great start! Keep it going!', emoji: '✨' };
    if (s <= 5) return { text: "You're building momentum!", emoji: '🔥' };
    if (s <= 10) return { text: "You're on fire!", emoji: '🔥🔥' };
    if (s <= 20) return { text: 'Unstoppable streak!', emoji: '💥' };
    return { text: 'Legendary! Absolute champion!', emoji: '🏆' };
});
</script>

<template>
    <ResponsiveModal
        :open="open"
        title="Your Streak"
        description="Daily check-in activity"
        content-class="sm:max-w-md"
        @close="handleClose"
    >
        <!-- ═══ Hero: Streak Counter ═══ -->
        <div class="mb-4 text-center">
            <div class="mb-2 flex items-center justify-center">
                <div
                    class="relative flex h-14 w-14 items-center justify-center rounded-2xl"
                    :class="currentStreak > 0
                        ? 'bg-orange-500/15'
                        : 'bg-muted/30'"
                >
                    <Flame
                        class="h-7 w-7"
                        :class="currentStreak > 0 ? 'text-orange-400' : 'text-muted-foreground/40'"
                    />
                    <div
                        v-if="currentStreak > 0"
                        class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-orange-500 text-[9px] font-black text-white"
                    >
                        {{ currentStreak }}
                    </div>
                </div>
            </div>
            <p class="text-xl font-black text-foreground tabular-nums">
                {{ currentStreak }} <span class="text-sm font-semibold text-muted-foreground/60">day streak</span>
            </p>
            <p class="mt-1 text-xs text-muted-foreground/50">
                {{ motivationalMessage.emoji }} {{ motivationalMessage.text }}
            </p>
        </div>

        <!-- ═══ Quick Stats Row ═══ -->
        <div class="mb-4 flex items-center justify-center gap-5 text-center">
            <div>
                <p class="text-[9px] font-black tracking-[0.1em] text-muted-foreground/40 uppercase">Best</p>
                <p class="text-base font-black text-foreground tabular-nums">{{ longestStreak }} <span class="text-[10px] font-medium text-muted-foreground/40">days</span></p>
            </div>
            <div class="h-6 w-px bg-border/15"></div>
            <div>
                <p class="text-[9px] font-black tracking-[0.1em] text-muted-foreground/40 uppercase">This Month</p>
                <p class="text-base font-black text-foreground tabular-nums">{{ activeDaysThisMonth }} <span class="text-[10px] font-medium text-muted-foreground/40">days</span></p>
            </div>
            <div class="h-6 w-px bg-border/15"></div>
            <div>
                <p class="text-[9px] font-black tracking-[0.1em] text-muted-foreground/40 uppercase">Total</p>
                <p class="text-base font-black text-foreground tabular-nums">{{ loginDateSet.size }} <span class="text-[10px] font-medium text-muted-foreground/40">days</span></p>
            </div>
        </div>

        <!-- ═══ Month Navigation ═══ -->
        <div class="mb-2 flex items-center justify-between">
            <button
                @click="prevMonth"
                class="flex h-7 w-7 items-center justify-center rounded-lg cursor-pointer transition-all duration-200 hover:bg-muted/30"
            >
                <ChevronLeft class="h-4 w-4 text-foreground/60" />
            </button>
            <p class="text-sm font-bold text-foreground">{{ viewTitle }}</p>
            <button
                @click="nextMonth"
                :disabled="!canGoForward"
                class="flex h-7 w-7 items-center justify-center rounded-lg cursor-pointer transition-all duration-200 hover:bg-muted/30 disabled:cursor-not-allowed disabled:opacity-20"
            >
                <ChevronRight class="h-4 w-4 text-foreground/60" />
            </button>
        </div>

        <!-- ═══ Calendar Grid ═══ -->
        <div class="rounded-xl border border-border/10 bg-card/30 p-3">
            <!-- Day-of-week header -->
            <div class="mb-1.5 grid grid-cols-7 gap-1">
                <div
                    v-for="day in dayNames"
                    :key="day"
                    class="py-1 text-center text-[8px] font-black tracking-wider text-muted-foreground/35 uppercase"
                >
                    {{ day }}
                </div>
            </div>

            <!-- Calendar cells -->
            <div class="grid grid-cols-7 gap-1">
                <div
                    v-for="(cell, idx) in calendarCells"
                    :key="idx"
                    class="flex aspect-square items-center justify-center"
                >
                    <div
                        v-if="cell.visible"
                        class="relative flex h-full w-full items-center justify-center rounded-full transition-all duration-200"
                        :class="{
                            'bg-orange-500/15': cell.isActive && !cell.isToday,
                            'bg-orange-500/25 ring-2 ring-orange-400/40': cell.isActive && cell.isToday,
                            'border-2 border-dashed border-muted-foreground/25': cell.isToday && !cell.isActive,
                            'bg-transparent': !cell.isActive && !cell.isToday,
                        }"
                    >
                        <!-- Checkmark for active days -->
                        <Check
                            v-if="cell.isActive"
                            class="h-3.5 w-3.5"
                            :class="cell.isToday ? 'text-orange-300' : 'text-orange-400/70'"
                            :stroke-width="3"
                        />
                        <!-- Day number for inactive -->
                        <span
                            v-else
                            class="text-xs font-medium"
                            :class="cell.isToday ? 'text-foreground' : 'text-muted-foreground/30'"
                        >
                            {{ cell.dayNum }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Legend ═══ -->
        <div class="mt-3 flex items-center justify-center gap-4 text-muted-foreground/40">
            <span class="flex items-center gap-1.5 text-[9px]">
                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full bg-orange-500/15">
                    <Check class="h-2.5 w-2.5 text-orange-400/70" :stroke-width="3" />
                </span>
                Active day
            </span>
            <span class="flex items-center gap-1.5 text-[9px]">
                <span class="flex h-3.5 w-3.5 items-center justify-center rounded-full border border-dashed border-muted-foreground/25">
                </span>
                Today
            </span>
        </div>
    </ResponsiveModal>
</template>
