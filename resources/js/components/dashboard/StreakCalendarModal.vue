<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ChevronLeft,
    ChevronRight,
    Check,
    History,
    Zap,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';

interface StreakRestoreInfo {
    enabled: boolean;
    limit: number;
    used: number;
    remaining: number;
    costs: number[];
    nextCost: number;
    restoredDates: string[];
    resetsAt: string | null;
}

interface RestoreResult {
    restored: boolean;
    reason: string;
    cost: number;
    remaining: number;
    total_xp: number;
    current_streak: number;
    restored_dates: string[];
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        loginDates: string[];
        currentStreak: number;
        longestStreak: number;
        userXp?: number;
        restore?: StreakRestoreInfo | null;
    }>(),
    {
        userXp: 0,
        restore: null,
    },
);

const emit = defineEmits<{
    close: [];
    restored: [result: RestoreResult];
}>();

// ── Date helpers ──────────────────────────────────────────────
const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
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

// ── Restore state (local mirrors of server props, updated from API) ──
const selectedDate = ref<string | null>(null);
const confirming = ref(false);
const restoreState = ref<'idle' | 'restoring' | 'success' | 'error'>('idle');
const restoreError = ref('');
const restoreSuccess = ref('');
const localUsed = ref(0);
const localRemaining = ref(0);
const localRestoredDates = ref<string[]>([]);
const localXp = ref(0);
const localStreak = ref(0);

function syncRestoreState() {
    selectedDate.value = null;
    confirming.value = false;
    restoreState.value = 'idle';
    restoreError.value = '';
    restoreSuccess.value = '';
    localUsed.value = props.restore?.used ?? 0;
    localRemaining.value = props.restore?.remaining ?? 0;
    localRestoredDates.value = [...(props.restore?.restoredDates ?? [])];
    localXp.value = props.userXp ?? 0;
    localStreak.value = props.currentStreak;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) syncRestoreState();
    },
    { immediate: true },
);

const restoreEnabled = computed(() => props.restore?.enabled === true);
const restoreLimit = computed(() => props.restore?.limit ?? 3);
const restoreCosts = computed(() => props.restore?.costs ?? []);
const resetsAt = computed(() => props.restore?.resetsAt ?? null);

const nextCost = computed(() => {
    if (!restoreCosts.value.length) return 0;
    return restoreCosts.value[
        Math.min(localUsed.value, restoreCosts.value.length - 1)
    ];
});

const restoredDateSet = computed(() => new Set(localRestoredDates.value));
const loginDateSet = computed(
    () => new Set([...props.loginDates, ...localRestoredDates.value]),
);

const displayStreak = computed(() => localStreak.value);

// Calendar grid: 6 rows x 7 columns
const calendarCells = computed(() => {
    const firstDay = new Date(viewYear.value, viewMonth.value, 1);
    const startDow = firstDay.getDay(); // 0=Sun
    const daysInMonth = new Date(
        viewYear.value,
        viewMonth.value + 1,
        0,
    ).getDate();

    const cells: {
        dateStr: string;
        dayNum: number;
        visible: boolean;
        isActive: boolean;
        isRestored: boolean;
        isRestorable: boolean;
        isToday: boolean;
        isFuture: boolean;
    }[] = [];

    // Leading blanks
    for (let i = 0; i < startDow; i++) {
        cells.push({
            dateStr: '',
            dayNum: 0,
            visible: false,
            isActive: false,
            isRestored: false,
            isRestorable: false,
            isToday: false,
            isFuture: false,
        });
    }

    const today = localDateStr(new Date());

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${viewYear.value}-${String(viewMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const isActive = loginDateSet.value.has(dateStr);
        const isRestored =
            !props.loginDates.includes(dateStr) &&
            restoredDateSet.value.has(dateStr);
        cells.push({
            dateStr,
            dayNum: d,
            visible: true,
            isActive,
            isRestored,
            isRestorable:
                restoreEnabled.value &&
                !isActive &&
                !isRestored &&
                dateStr < today,
            isToday: dateStr === today,
            isFuture: dateStr > today,
        });
    }

    // Trailing blanks to fill 42 cells (6 rows)
    while (cells.length < 42) {
        cells.push({
            dateStr: '',
            dayNum: 0,
            visible: false,
            isActive: false,
            isRestored: false,
            isRestorable: false,
            isToday: false,
            isFuture: false,
        });
    }

    return cells;
});

const viewTitle = computed(
    () => `${monthNames[viewMonth.value]} ${viewYear.value}`,
);

// Active days this month
const activeDaysThisMonth = computed(() => {
    return calendarCells.value.filter((c) => c.visible && c.isActive).length;
});

// Navigation
const canGoForward = computed(() => {
    const today = new Date();
    return !(
        viewMonth.value === today.getMonth() &&
        viewYear.value === today.getFullYear()
    );
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

// ── Restore interactions ──────────────────────────────────────
function formatShortDate(dateStr: string): string {
    const [y, m, d] = dateStr.split('-').map(Number);
    return `${monthNames[m - 1].slice(0, 3)} ${d}, ${y}`;
}

function selectDate(dateStr: string) {
    if (restoreState.value === 'restoring') return;
    if (selectedDate.value === dateStr) {
        selectedDate.value = null;
        confirming.value = false;
        return;
    }
    selectedDate.value = dateStr;
    confirming.value = false;
    restoreState.value = 'idle';
    restoreError.value = '';
    restoreSuccess.value = '';
}

const selectedCost = computed(() => nextCost.value);

const xpShortfall = computed(() =>
    Math.max(0, selectedCost.value - localXp.value),
);

const canRestoreSelected = computed(() => {
    if (!restoreEnabled.value) return false;
    if (!selectedDate.value) return false;
    if (localRemaining.value <= 0) return false;
    if (xpShortfall.value > 0) return false;
    return restoreState.value !== 'restoring';
});

const restoreHint = computed(() => {
    if (localRemaining.value <= 0)
        return `No restores left${resetsAt.value ? ` · resets ${resetsAt.value}` : ''}.`;
    if (!selectedDate.value) return 'Tap a missed day on the calendar.';
    if (xpShortfall.value > 0)
        return `Need ${xpShortfall.value} more XP for this restore.`;
    return `${formatShortDate(selectedDate.value)} · ${selectedCost.value} XP (${ordinal(localUsed.value + 1)} restore)`;
});

function ordinal(n: number): string {
    if (n === 1) return '1st';
    if (n === 2) return '2nd';
    if (n === 3) return '3rd';
    return `${n}th`;
}

async function handleRestore() {
    if (!canRestoreSelected.value || !selectedDate.value) return;
    if (!confirming.value) {
        confirming.value = true;
        return;
    }

    restoreState.value = 'restoring';
    restoreError.value = '';
    const date = selectedDate.value;

    try {
        const { data } = await axios.post<RestoreResult>(
            '/api/streak-restore',
            { date },
            { timeout: 15000 },
        );

        if (data.restored) {
            localUsed.value = restoreLimit.value - data.remaining;
            localRemaining.value = data.remaining;
            localRestoredDates.value = [...data.restored_dates];
            localXp.value = data.total_xp;
            localStreak.value = data.current_streak;
            selectedDate.value = null;
            confirming.value = false;
            restoreState.value = 'success';
            restoreSuccess.value = `Day restored · −${data.cost} XP`;
            emit('restored', data);
            router.reload({
                only: [
                    'userStats',
                    'loginDates',
                    'streakRestore',
                    'xpHistory',
                    'statsBreakdown',
                ] as never,
            });
        } else {
            restoreState.value = 'error';
            restoreError.value = data.reason || 'Could not restore this day.';
            confirming.value = false;
        }
    } catch (err) {
        restoreState.value = 'error';
        confirming.value = false;
        if (axios.isAxiosError(err) && err.response?.data?.reason) {
            restoreError.value = String(err.response.data.reason);
        } else {
            restoreError.value = 'Something went wrong. Please try again.';
        }
    }
}

// Motivational message
const motivationalMessage = computed(() => {
    const s = displayStreak.value;
    if (s === 0)
        return { text: "Let's start a new streak today!", emoji: '🌱' };
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
        content-class="sm:max-w-2xl lg:max-w-3xl"
        @close="handleClose"
    >
        <!-- Landscape on desktop (two columns), stacked on mobile.
             `contents` flattens the rails on mobile so blocks can be
             re-ordered: calendar sits between stats and restore panel,
             keeping the action below the selection on small screens. -->
        <div
            class="flex flex-col gap-4 sm:grid sm:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] sm:items-start sm:gap-6"
        >
            <!-- ═══ Left rail ═══ -->
            <div class="contents sm:flex sm:flex-col sm:gap-3">
                <!-- Echo: Streak Companion -->
                <div
                    class="order-1 flex items-center justify-center gap-2.5 sm:justify-start"
                >
                    <FoxCompanion
                        mascot="calendar"
                        :size="64"
                        label="Echo, your streak companion"
                        :show-message="false"
                        class="shrink-0"
                    />
                    <p
                        class="max-w-[175px] text-[12px] leading-relaxed text-muted-foreground"
                    >
                        <span class="font-semibold text-foreground"
                            >Echo's tip:</span
                        >
                        A little progress today keeps your streak alive.
                    </p>
                </div>

                <!-- Hero: Streak Counter -->
                <div class="order-2 text-center sm:text-left">
                    <p
                        class="text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                    >
                        {{ displayStreak }}
                        <span class="text-sm font-medium text-muted-foreground"
                            >day streak</span
                        >
                    </p>
                    <p class="mt-0.5 text-[12px] text-muted-foreground">
                        {{ motivationalMessage.emoji }}
                        {{ motivationalMessage.text }}
                    </p>
                </div>

                <!-- Quick Stats Row -->
                <div
                    class="order-3 flex items-center justify-center gap-4 text-center sm:justify-start"
                >
                    <div>
                        <p
                            class="text-[12px] font-medium text-muted-foreground"
                        >
                            Best
                        </p>
                        <p
                            class="text-[15px] font-semibold text-foreground tabular-nums"
                        >
                            {{ longestStreak }}
                            <span
                                class="text-[12px] font-medium text-muted-foreground"
                                >days</span
                            >
                        </p>
                    </div>
                    <div class="h-6 w-px bg-border/15"></div>
                    <div>
                        <p
                            class="text-[12px] font-medium text-muted-foreground"
                        >
                            This Month
                        </p>
                        <p
                            class="text-[15px] font-semibold text-foreground tabular-nums"
                        >
                            {{ activeDaysThisMonth }}
                            <span
                                class="text-[12px] font-medium text-muted-foreground"
                                >days</span
                            >
                        </p>
                    </div>
                    <div class="h-6 w-px bg-border/15"></div>
                    <div>
                        <p
                            class="text-[12px] font-medium text-muted-foreground"
                        >
                            Total
                        </p>
                        <p
                            class="text-[15px] font-semibold text-foreground tabular-nums"
                        >
                            {{ loginDateSet.size }}
                            <span
                                class="text-[12px] font-medium text-muted-foreground"
                                >days</span
                            >
                        </p>
                    </div>
                </div>

                <!-- Restore panel -->
                <div
                    v-if="restoreEnabled"
                    class="order-5 rounded-xl border border-border/10 bg-card/30 p-3 sm:order-none"
                >
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <p
                            class="flex items-center gap-1.5 text-[13px] font-semibold text-foreground"
                        >
                            <History class="h-3.5 w-3.5 text-[#D97757]" />
                            Restore a day
                        </p>
                        <span
                            class="rounded-full bg-[#D97757]/15 px-2 py-0.5 text-[11px] font-semibold text-[#D97757] tabular-nums"
                        >
                            {{ localRemaining }} of {{ restoreLimit }} left
                        </span>
                    </div>
                    <p class="mb-2 text-[12px] text-muted-foreground">
                        {{ restoreHint }}
                    </p>
                    <div
                        class="mb-2 flex items-center gap-1 text-[12px] text-muted-foreground"
                    >
                        <Zap class="h-3 w-3 text-[#D97757]" />
                        <span class="tabular-nums"
                            >Balance: {{ Math.max(0, localXp) }} XP · next costs
                            {{ nextCost }} XP</span
                        >
                    </div>
                    <button
                        v-if="selectedDate"
                        type="button"
                        :disabled="!canRestoreSelected"
                        class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-[#D97757] px-3 py-2 text-[13px] font-semibold text-white transition-all hover:bg-[#D97757]/90 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="handleRestore"
                    >
                        <template v-if="restoreState === 'restoring'">
                            Restoring…
                        </template>
                        <template v-else-if="confirming">
                            Confirm −{{ selectedCost }} XP?
                        </template>
                        <template v-else>
                            Restore {{ formatShortDate(selectedDate) }} · −{{
                                selectedCost
                            }}
                            XP
                        </template>
                    </button>
                    <button
                        v-if="confirming && restoreState !== 'restoring'"
                        type="button"
                        class="mt-1.5 w-full cursor-pointer rounded-lg px-3 py-1.5 text-[12px] font-medium text-muted-foreground transition-colors hover:bg-muted"
                        @click="
                            confirming = false;
                            restoreState = 'idle';
                        "
                    >
                        Cancel
                    </button>
                    <p
                        v-if="restoreState === 'success'"
                        class="mt-1.5 text-[12px] font-medium text-emerald-500"
                    >
                        {{ restoreSuccess }}
                    </p>
                    <p
                        v-if="restoreState === 'error'"
                        class="mt-1.5 text-[12px] font-medium text-destructive"
                    >
                        {{ restoreError }}
                    </p>
                </div>
            </div>

            <!-- ═══ Right panel: calendar ═══ -->
            <div class="order-4 sm:order-none">
                <!-- Month Navigation -->
                <div class="mb-2 flex items-center justify-between">
                    <button
                        @click="prevMonth"
                        class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-muted"
                    >
                        <ChevronLeft class="h-4 w-4 text-foreground/60" />
                    </button>
                    <p
                        class="text-[15px] font-semibold tracking-tight text-foreground"
                    >
                        {{ viewTitle }}
                    </p>
                    <button
                        @click="nextMonth"
                        :disabled="!canGoForward"
                        class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-20"
                    >
                        <ChevronRight class="h-4 w-4 text-foreground/60" />
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div class="rounded-xl border border-border/10 bg-card/30 p-2">
                    <!-- Day-of-week header -->
                    <div class="mb-1 grid grid-cols-7 gap-0.5">
                        <div
                            v-for="day in dayNames"
                            :key="day"
                            class="py-0.5 text-center text-[11px] font-medium text-muted-foreground"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <!-- Calendar cells -->
                    <div class="grid grid-cols-7 gap-0.5">
                        <div
                            v-for="(cell, idx) in calendarCells"
                            :key="idx"
                            class="flex h-8 w-full items-center justify-center sm:h-9"
                        >
                            <button
                                v-if="cell.visible && cell.isRestorable"
                                type="button"
                                :aria-label="`Restore ${cell.dateStr}`"
                                :aria-pressed="selectedDate === cell.dateStr"
                                class="relative flex h-full w-full cursor-pointer items-center justify-center rounded-full transition-all duration-200 hover:bg-[#D97757]/10"
                                :class="{
                                    'bg-[#D97757]/20 ring-2 ring-[#D97757]':
                                        selectedDate === cell.dateStr,
                                }"
                                @click="selectDate(cell.dateStr)"
                            >
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                >
                                    {{ cell.dayNum }}
                                </span>
                            </button>
                            <div
                                v-else-if="cell.visible"
                                class="relative flex h-full w-full items-center justify-center rounded-full transition-all duration-200"
                                :class="{
                                    'bg-[#D97757]/15':
                                        cell.isActive && !cell.isToday,
                                    'bg-[#D97757]/25 ring-2 ring-[#D97757]/40':
                                        cell.isActive && cell.isToday,
                                    'border-2 border-dashed border-muted-foreground/25':
                                        cell.isToday && !cell.isActive,
                                    'bg-transparent':
                                        !cell.isActive && !cell.isToday,
                                    'bg-[#D97757]/20 ring-2 ring-[#D97757]':
                                        selectedDate === cell.dateStr,
                                }"
                            >
                                <!-- Checkmark for active days -->
                                <Check
                                    v-if="cell.isActive"
                                    class="h-3 w-3"
                                    :class="
                                        cell.isToday
                                            ? 'text-[#D97757]'
                                            : 'text-[#D97757]/80'
                                    "
                                    :stroke-width="3"
                                />
                                <!-- Day number for inactive -->
                                <span
                                    v-else
                                    class="text-[11px] font-medium"
                                    :class="
                                        cell.isToday
                                            ? 'text-foreground'
                                            : cell.isFuture
                                              ? 'text-muted-foreground/20'
                                              : 'text-muted-foreground/30'
                                    "
                                >
                                    {{ cell.dayNum }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div
                    class="mt-2.5 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-muted-foreground/40"
                >
                    <span class="flex items-center gap-1.5 text-[12px]">
                        <span
                            class="flex h-3 w-3 items-center justify-center rounded-full bg-[#D97757]/15"
                        >
                            <Check
                                class="h-2 w-2 text-[#D97757]"
                                :stroke-width="3"
                            />
                        </span>
                        Active day
                    </span>
                    <span class="flex items-center gap-1.5 text-[12px]">
                        <span
                            class="flex h-3 w-3 items-center justify-center rounded-full border border-dashed border-muted-foreground/25"
                        >
                        </span>
                        Today
                    </span>
                    <span
                        v-if="restoreEnabled"
                        class="flex items-center gap-1.5 text-[12px]"
                    >
                        <span
                            class="flex h-3 w-3 items-center justify-center rounded-full bg-[#D97757]/20 ring-1 ring-[#D97757]"
                        >
                        </span>
                        Selected restore
                    </span>
                </div>
            </div>
        </div>
    </ResponsiveModal>
</template>
