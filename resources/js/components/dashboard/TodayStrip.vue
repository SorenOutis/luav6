<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarCheck,
    Clock,
    Timer,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export interface NextUpItem {
    kind: 'exam' | 'assignment';
    title: string;
    dueAt: string; // ISO or parseable date
    href: string;
    meta?: string;
}

interface Props {
    dueTodayCount: number;
    overdueCount: number;
    upcoming24hCount: number;
    nextItem?: NextUpItem | null;
}

const props = withDefaults(defineProps<Props>(), { nextItem: null });

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

// Don't tick while the tab is hidden — the countdown only matters when the
// dashboard is visible, and a 1s interval keeps waking the CPU in the background.
const handleVisibilityChange = () => {
    if (document.hidden) {
        stopTicking();
    } else if (hasActivity.value) {
        now.value = new Date();
        startTicking();
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    stopTicking();
});

// --- Day timeline marker (midnight → now → midnight) ---
const dayPercent = computed(() => {
    const start = new Date(now.value);
    start.setHours(0, 0, 0, 0);
    const elapsed = now.value.getTime() - start.getTime();
    return Math.min(100, Math.max(0, (elapsed / 86_400_000) * 100));
});

// --- Next-item countdown ---
const countdown = computed(() => {
    if (!props.nextItem?.dueAt) return null;
    const due = new Date(props.nextItem.dueAt).getTime();
    if (Number.isNaN(due)) return null;
    const diff = due - now.value.getTime();
    const overdue = diff < 0;
    const abs = Math.abs(diff);
    const days = Math.floor(abs / 86_400_000);
    const hours = Math.floor((abs % 86_400_000) / 3_600_000);
    const minutes = Math.floor((abs % 3_600_000) / 60_000);
    const seconds = Math.floor((abs % 60_000) / 1000);

    let label: string;
    if (days >= 1) label = `${days}d ${hours.toString().padStart(2, '0')}h`;
    else if (hours >= 1)
        label = `${hours.toString().padStart(2, '0')}:${minutes
            .toString()
            .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    else
        label = `${minutes.toString().padStart(2, '0')}:${seconds
            .toString()
            .padStart(2, '0')}`;

    let tone: 'ok' | 'soon' | 'now' | 'overdue' = 'ok';
    if (overdue) tone = 'overdue';
    else if (diff < 3_600_000) tone = 'now';
    else if (diff < 86_400_000) tone = 'soon';

    return { label, overdue, tone };
});

const nextToneClasses = computed(() => {
    switch (countdown.value?.tone) {
        case 'overdue':
            return {
                label: 'text-[#CB7676]',
                chip: 'bg-[#CB7676]/10 text-[#CB7676]',
            };
        case 'now':
            return {
                label: 'text-[#E0AF68]',
                chip: 'bg-[#E0AF68]/10 text-[#E0AF68]',
            };
        case 'soon':
            return {
                label: 'text-[#D97757]',
                chip: 'bg-[#D97757]/10 text-[#D97757]',
            };
        default:
            return {
                label: 'text-muted-foreground',
                chip: 'bg-muted text-muted-foreground',
            };
    }
});

// --- Metric tiles ---
const hasActivity = computed(
    () =>
        props.dueTodayCount > 0 ||
        props.overdueCount > 0 ||
        props.upcoming24hCount > 0 ||
        !!props.nextItem,
);

// Only keep a 1s interval alive while there is something to count down to.
// immediate: true so a no-activity mount never starts a pointless interval.
watch(
    hasActivity,
    (active) => {
        if (active && !document.hidden) {
            startTicking();
        } else {
            stopTicking();
        }
    },
    { immediate: true },
);

const metrics = computed(() => [
    {
        key: 'today',
        label: 'Today',
        sub: 'due',
        value: props.dueTodayCount,
        icon: CalendarCheck,
        accent: 'primary',
        active: props.dueTodayCount > 0,
    },
    {
        key: 'overdue',
        label: 'Overdue',
        sub: props.overdueCount === 1 ? 'needs attention' : 'need attention',
        value: props.overdueCount,
        icon: AlertTriangle,
        accent: 'destructive',
        active: props.overdueCount > 0,
    },
    {
        key: 'next24',
        label: 'Next 24h',
        sub: 'upcoming',
        value: props.upcoming24hCount,
        icon: Timer,
        accent: 'amber',
        active: props.upcoming24hCount > 0,
    },
]);

const accentClasses = (accent: string, active: boolean) => {
    if (!active) {
        return {
            wrap: 'bg-muted/40',
            iconWrap: 'bg-background/70 text-muted-foreground',
            value: 'text-foreground',
        };
    }
    switch (accent) {
        case 'destructive':
            return {
                wrap: 'bg-[#CB7676]/10',
                iconWrap: 'bg-[#CB7676]/15 text-[#CB7676]',
                value: 'text-[#CB7676]',
            };
        case 'amber':
            return {
                wrap: 'bg-[#E0AF68]/10',
                iconWrap: 'bg-[#E0AF68]/15 text-[#E0AF68]',
                value: 'text-[#E0AF68]',
            };
        default:
            return {
                wrap: 'bg-[#D97757]/10',
                iconWrap: 'bg-[#D97757]/15 text-[#D97757]',
                value: 'text-[#D97757]',
            };
    }
};
</script>

<template>
    <section
        v-if="hasActivity"
        class="surface-card relative overflow-hidden"
        aria-label="Today at a glance"
    >
        <div class="relative h-1 w-full bg-muted/50" aria-hidden="true">
            <div
                class="absolute inset-y-0 left-0 rounded-r-full bg-[#D97757] transition-[width] duration-500"
                :style="{ width: `${dayPercent}%` }"
            />
        </div>

        <div
            class="flex flex-col gap-2 p-3 sm:gap-4 sm:p-5 lg:flex-row lg:items-stretch"
        >
            <div class="grid grid-cols-3 gap-1.5 sm:gap-3 lg:flex-1">
                <div
                    v-for="m in metrics"
                    :key="m.key"
                    :class="[
                        'flex min-h-[72px] flex-col justify-between rounded-xl p-2 sm:min-h-[92px] sm:flex-row sm:items-center sm:gap-3 sm:rounded-[1.1rem] sm:p-4',
                        accentClasses(m.accent, m.active).wrap,
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <div
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full sm:h-9 sm:w-9',
                                accentClasses(m.accent, m.active).iconWrap,
                            ]"
                        >
                            <component :is="m.icon" class="h-4 w-4" />
                        </div>
                        <p
                            class="truncate text-[11px] font-medium text-muted-foreground sm:hidden"
                        >
                            {{ m.label }}
                        </p>
                    </div>

                    <div class="min-w-0">
                        <p
                            class="hidden text-[13px] font-medium text-muted-foreground sm:block"
                        >
                            {{ m.label }}
                        </p>
                        <div class="flex items-baseline gap-1.5">
                            <span
                                :class="[
                                    'dash-metric text-[22px] leading-none sm:text-[32px]',
                                    accentClasses(m.accent, m.active).value,
                                ]"
                            >
                                {{ m.value }}
                            </span>
                            <span
                                class="hidden truncate text-[13px] text-muted-foreground sm:inline"
                            >
                                {{ m.sub }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <Link
                v-if="nextItem"
                :href="nextItem.href"
                class="group flex min-h-14 items-center gap-3 rounded-[1.1rem] bg-muted/40 px-4 py-3.5 transition-colors hover:bg-muted/70 lg:w-[32%] lg:min-w-[260px]"
            >
                <div
                    :class="[
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
                        nextToneClasses.chip,
                    ]"
                >
                    <Zap v-if="nextItem.kind === 'exam'" class="h-4 w-4" />
                    <Clock v-else class="h-4 w-4" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p
                            :class="[
                                'text-[13px] font-medium',
                                nextToneClasses.label,
                            ]"
                        >
                            Next {{ nextItem.kind }}
                        </p>
                        <span
                            v-if="countdown"
                            :class="[
                                'rounded-full px-2 py-0.5 text-[12px] font-semibold tabular-nums',
                                nextToneClasses.chip,
                            ]"
                        >
                            {{ countdown.overdue ? '+' : ''
                            }}{{ countdown.label }}
                        </span>
                    </div>
                    <p
                        class="mt-0.5 truncate text-[15px] font-semibold tracking-tight text-foreground"
                    >
                        {{ nextItem.title }}
                    </p>
                    <p
                        v-if="nextItem.meta"
                        class="truncate text-[13px] text-muted-foreground"
                    >
                        {{ nextItem.meta }}
                    </p>
                </div>

                <ArrowUpRight
                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                />
            </Link>
        </div>
    </section>
</template>
