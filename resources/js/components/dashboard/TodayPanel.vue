<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpenCheck,
    CalendarCheck,
    CheckCircle2,
    ClipboardList,
    Timer,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** One actionable due item (assignment or published exam). */
export interface TodayTask {
    kind: 'exam' | 'assignment';
    title: string;
    /** ISO string; already validated by the parent. */
    dueAtIso: string;
    href: string;
    meta?: string;
    isCompleted: boolean;
    isOverdue: boolean;
}

export interface NextUpItem {
    kind: 'exam' | 'assignment';
    title: string;
    dueAt: string; // ISO or parseable date
    href: string;
    meta?: string;
}

interface Props {
    tasks: TodayTask[];
    /** Compact layout (mobile composition). */
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), { compact: false });

type TabKey = 'today' | 'overdue' | 'next24' | 'done';

// ─── Clock (1s tick only while visible AND there is something to count) ───
const now = ref(new Date());
let tickId: number | null = null;

const startTicking = () => {
    if (tickId !== null) return;
    tickId = window.setInterval(() => (now.value = new Date()), 1000);
};

const stopTicking = () => {
    if (tickId !== null) {
        window.clearInterval(tickId);
        tickId = null;
    }
};

const handleVisibilityChange = () => {
    if (document.hidden) {
        stopTicking();
    } else {
        now.value = new Date();
        if (hasActiveItems.value) startTicking();
    }
};

onMounted(() =>
    document.addEventListener('visibilitychange', handleVisibilityChange),
);
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopTicking();
});

// ─── Buckets ──────────────────────────────────────────────────────────────
const startOfToday = computed(() => {
    const d = new Date(now.value);
    d.setHours(0, 0, 0, 0);
    return d.getTime();
});

const bucketOf = (task: TodayTask): TabKey => {
    if (task.isCompleted) return 'done';
    const t = new Date(task.dueAtIso).getTime();
    if (t < now.value.getTime()) return 'overdue';
    if (t >= startOfToday.value && t < startOfToday.value + 86_400_000)
        return 'today';
    if (t < now.value.getTime() + 86_400_000) return 'next24';
    // Later than 24h — show under "Due today" only if it is actually today,
    // otherwise surface it in Next 24h's sibling: keep it visible under 'today'
    // only when due today; everything else lands in next24 for simplicity.
    return 'next24';
};

const buckets = computed<Record<TabKey, TodayTask[]>>(() => {
    const out: Record<TabKey, TodayTask[]> = {
        today: [],
        overdue: [],
        next24: [],
        done: [],
    };
    for (const task of props.tasks) out[bucketOf(task)].push(task);
    // Soonest first within each bucket.
    for (const key of Object.keys(out) as TabKey[]) {
        out[key].sort(
            (a, b) =>
                new Date(a.dueAtIso).getTime() - new Date(b.dueAtIso).getTime(),
        );
    }
    return out;
});

const hasActiveItems = computed(
    () =>
        buckets.value.today.length +
            buckets.value.overdue.length +
            buckets.value.next24.length >
        0,
);

watch(
    hasActiveItems,
    (active) => {
        if (active && !document.hidden) startTicking();
        else stopTicking();
    },
    { immediate: true },
);

// ─── Tabs ─────────────────────────────────────────────────────────────────
const activeTab = ref<TabKey>('today');

const tabs = computed(() => [
    {
        key: 'today' as TabKey,
        label: 'Due today',
        count: buckets.value.today.length,
        icon: CalendarCheck,
    },
    {
        key: 'overdue' as TabKey,
        label: 'Overdue',
        count: buckets.value.overdue.length,
        icon: AlertTriangle,
    },
    {
        key: 'next24' as TabKey,
        label: 'Next 24h',
        count: buckets.value.next24.length,
        icon: Timer,
    },
    {
        key: 'done' as TabKey,
        label: 'Done',
        count: buckets.value.done.length,
        icon: CheckCircle2,
    },
]);

const activeTasks = computed(() => buckets.value[activeTab.value]);

// Auto-pilot: land on the most relevant tab when data arrives.
watch(
    () => props.tasks.length,
    () => {
        if (activeTab.value !== 'today') return;
        if (buckets.value.today.length === 0) {
            if (buckets.value.overdue.length > 0) activeTab.value = 'overdue';
            else if (buckets.value.next24.length > 0)
                activeTab.value = 'next24';
            else if (buckets.value.done.length > 0) activeTab.value = 'done';
        }
    },
    { immediate: true },
);

// ─── Presentation helpers ─────────────────────────────────────────────────
const countdownFor = (task: TodayTask) => {
    const due = new Date(task.dueAtIso).getTime();
    if (Number.isNaN(due)) return '';
    const diff = due - now.value.getTime();
    if (task.isCompleted) return timeLabel(due);
    if (diff <= 0) {
        const abs = Math.abs(diff);
        const mins = Math.floor(abs / 60_000);
        if (mins < 1) return 'due now';
        if (mins < 60) return `${mins}m overdue`;
        const hours = Math.floor(mins / 60);
        if (hours < 48) return `${hours}h overdue`;
        return `${Math.floor(hours / 24)}d overdue`;
    }
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'due any moment';
    if (mins < 60) return `in ${mins}m`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `in ${hours}h`;
    return `in ${Math.floor(hours / 24)}d`;
};

const timeLabel = (ts: number) => {
    const d = new Date(ts);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
};

const kindLabel = (kind: TodayTask['kind']) =>
    kind === 'exam' ? 'Exam' : 'Assignment';

const kindBadgeClass = (kind: TodayTask['kind']) =>
    kind === 'exam'
        ? 'bg-[#4D9375]/10 text-[#4D9375]'
        : 'bg-[#D97757]/10 text-[#D97757]';

const isOverdue = (task: TodayTask) =>
    !task.isCompleted &&
    new Date(task.dueAtIso).getTime() < now.value.getTime();

const chipClass = (task: TodayTask) => {
    if (task.isCompleted) return 'bg-muted text-muted-foreground';
    if (isOverdue(task)) return 'bg-[#CB7676]/10 text-[#CB7676]';
    return 'bg-muted text-foreground';
};

const emptyCopy: Record<TabKey, { title: string; body: string }> = {
    today: {
        title: 'Nothing due today',
        body: 'Enjoy the breathing room — or get ahead on what’s coming up.',
    },
    overdue: {
        title: 'Nothing overdue',
        body: 'You’re all caught up. Nice work!',
    },
    next24: {
        title: 'Clear skies ahead',
        body: 'Nothing lands in the next 24 hours.',
    },
    done: {
        title: 'Nothing completed yet',
        body: 'Finished items will collect here.',
    },
};
</script>

<template>
    <section
        class="surface-card relative w-full min-w-0 overflow-hidden"
        aria-label="Today's tasks"
    >
        <div class="flex min-w-0 flex-col gap-2 p-3 sm:p-5">
            <!-- Header + tabs -->
            <div
                class="flex flex-wrap items-center justify-between gap-2 sm:gap-3"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <div
                        class="dash-icon-well flex h-8 w-8 items-center justify-center rounded-full bg-[#D97757]/15 text-[#D97757]"
                    >
                        <ClipboardList class="h-4 w-4" />
                    </div>
                    <h3
                        class="dash-title text-[17px] text-foreground sm:text-lg"
                    >
                        Today
                    </h3>
                </div>

                <div
                    class="-mx-1 grid max-w-full scrollbar-none grid-cols-2 gap-1.5 px-1 py-0.5 sm:flex sm:items-center sm:gap-1 sm:overflow-x-auto"
                    role="tablist"
                    aria-label="Task filter"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        class="flex min-h-11 min-w-0 shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-full px-2 py-1.5 text-xs font-medium transition-colors sm:min-h-0 sm:px-3 sm:text-[13px]"
                        :class="
                            activeTab === tab.key
                                ? 'bg-[#D97757] text-white'
                                : tab.count > 0 && tab.key === 'overdue'
                                  ? 'text-[#CB7676] hover:bg-muted'
                                  : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-3.5 w-3.5" />
                        <span>{{ tab.label }}</span>
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums"
                            :class="
                                activeTab === tab.key
                                    ? 'bg-white/20'
                                    : 'bg-muted'
                            "
                            >{{ tab.count }}</span
                        >
                    </button>
                </div>
            </div>

            <!-- Task list -->
            <div
                v-if="activeTasks.length > 0"
                class="mt-1 divide-y divide-border/40"
                role="tabpanel"
            >
                <Link
                    v-for="task in activeTasks"
                    :key="`${task.kind}-${task.title}-${task.dueAtIso}`"
                    :href="task.href"
                    class="group flex items-center gap-2.5 px-1 py-2.5 transition-colors hover:bg-muted/30 sm:gap-3 sm:rounded-xl sm:px-2"
                >
                    <!-- Status checkbox (visual — completing happens in Activities) -->
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-colors sm:h-[22px] sm:w-[22px]"
                        :class="
                            task.isCompleted
                                ? 'border-[#4D9375] bg-[#4D9375]'
                                : 'border-border bg-background group-hover:border-[#D97757]/60'
                        "
                        aria-hidden="true"
                    >
                        <CheckCircle2
                            v-if="task.isCompleted"
                            class="h-3.5 w-3.5 text-white"
                            stroke-width="3"
                        />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="truncate text-[14px] font-semibold sm:text-[15px]"
                                :class="
                                    task.isCompleted
                                        ? 'text-muted-foreground line-through'
                                        : 'text-foreground'
                                "
                                >{{ task.title }}</span
                            >
                            <span
                                class="hidden shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold sm:inline"
                                :class="kindBadgeClass(task.kind)"
                                >{{ kindLabel(task.kind) }}</span
                            >
                        </span>
                        <span
                            v-if="task.meta && !compact"
                            class="mt-0.5 block truncate text-[12px] text-muted-foreground"
                            >{{ task.meta }}</span
                        >
                    </span>

                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold tabular-nums"
                        :class="chipClass(task)"
                    >
                        <BookOpenCheck
                            v-if="task.isCompleted"
                            class="h-3.5 w-3.5"
                        />
                        {{ countdownFor(task) }}
                    </span>
                </Link>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex flex-col items-center gap-1.5 rounded-xl bg-muted/30 px-4 py-6 text-center"
            >
                <p class="text-[14px] font-semibold text-foreground">
                    {{ emptyCopy[activeTab].title }}
                </p>
                <p class="max-w-xs text-[13px] text-muted-foreground">
                    {{ emptyCopy[activeTab].body }}
                </p>
                <Link
                    href="/activities"
                    class="mt-1 inline-flex items-center gap-1 text-[13px] font-semibold text-[#D97757] hover:underline"
                >
                    Open Activities
                </Link>
            </div>
        </div>
    </section>
</template>
