<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarCheck,
    Clock,
    Timer,
    Zap,
} from 'lucide-vue-next';
import type { NextUpItem } from './NextUpCard.vue';

interface Props {
    dueTodayCount: number;
    overdueCount: number;
    upcoming24hCount: number;
    nextItem?: NextUpItem | null;
}

const props = withDefaults(defineProps<Props>(), { nextItem: null });

const now = ref(new Date());
let tickId: number | null = null;

onMounted(() => {
    tickId = window.setInterval(() => (now.value = new Date()), 1000);
});

onBeforeUnmount(() => {
    if (tickId !== null) window.clearInterval(tickId);
});

// --- Day timeline marker (midnight → now → midnight) ---
const dayPercent = computed(() => {
    const start = new Date(now.value);
    start.setHours(0, 0, 0, 0);
    const elapsed = now.value.getTime() - start.getTime();
    return Math.min(100, Math.max(0, (elapsed / 86_400_000) * 100));
});

const currentHour = computed(() =>
    now.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
);

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
                border: 'border-destructive/40',
                ring: 'ring-destructive/30',
                label: 'text-destructive',
                chip: 'bg-destructive/10 text-destructive border-destructive/30',
                glow: 'bg-destructive/20',
            };
        case 'now':
            return {
                border: 'border-amber-500/40',
                ring: 'ring-amber-500/30',
                label: 'text-amber-500',
                chip: 'bg-amber-500/10 text-amber-500 border-amber-500/30',
                glow: 'bg-amber-500/20',
            };
        case 'soon':
            return {
                border: 'border-primary/40',
                ring: 'ring-primary/25',
                label: 'text-primary',
                chip: 'bg-primary/10 text-primary border-primary/30',
                glow: 'bg-primary/20',
            };
        default:
            return {
                border: 'border-border/60',
                ring: 'ring-border/30',
                label: 'text-muted-foreground',
                chip: 'bg-muted/60 text-muted-foreground border-border/40',
                glow: 'bg-primary/10',
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

const metrics = computed(() => [
    {
        key: 'today',
        label: 'Today',
        sub: props.dueTodayCount === 1 ? 'due' : 'due',
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
            wrap: 'bg-muted/30 border-border/40',
            iconWrap: 'bg-muted/60 text-muted-foreground border-border/40',
            value: 'text-foreground/90',
            dot: 'bg-muted-foreground/30',
            glow: 'bg-primary/0',
        };
    }
    switch (accent) {
        case 'destructive':
            return {
                wrap: 'bg-destructive/[0.06] border-destructive/30',
                iconWrap: 'bg-destructive/10 text-destructive border-destructive/30',
                value: 'text-destructive',
                dot: 'bg-destructive',
                glow: 'bg-destructive/20',
            };
        case 'amber':
            return {
                wrap: 'bg-amber-500/[0.06] border-amber-500/30',
                iconWrap:
                    'bg-amber-500/10 text-amber-500 border-amber-500/30',
                value: 'text-amber-500',
                dot: 'bg-amber-500',
                glow: 'bg-amber-500/20',
            };
        default:
            return {
                wrap: 'bg-primary/[0.06] border-primary/30',
                iconWrap: 'bg-primary/10 text-primary border-primary/30',
                value: 'text-primary',
                dot: 'bg-primary',
                glow: 'bg-primary/20',
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
        <!-- Decorative background glows -->
        <div
            class="pointer-events-none absolute -left-16 -top-16 h-40 w-40 rounded-full bg-primary/10 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-20 -bottom-24 h-52 w-52 rounded-full bg-primary/5 blur-3xl"
            aria-hidden="true"
        />

        <!-- Day timeline bar -->
        <div
            class="relative h-1 w-full bg-muted/40"
            aria-hidden="true"
        >
            <div
                class="absolute inset-y-0 left-0 bg-gradient-to-r from-primary/60 via-primary to-primary/60 transition-[width] duration-500"
                :style="{ width: `${dayPercent}%` }"
            />
            <span
                class="absolute top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-background bg-primary shadow-[0_0_12px_rgba(var(--primary-rgb),0.6)] transition-[left] duration-500"
                :style="{ left: `${dayPercent}%` }"
            />
        </div>

        <div class="relative z-10 flex flex-col gap-3 p-3 sm:gap-4 sm:p-5 lg:flex-row lg:items-stretch">
            <!-- Middle: Metric tiles -->
            <div class="grid grid-cols-3 gap-2 sm:gap-3 lg:flex-1">
                <template v-for="m in metrics" :key="m.key">
                    <div
                        :class="[
                            'relative overflow-hidden rounded-xl border transition-all duration-300',
                            'flex flex-col items-start gap-2 p-2.5',
                            'sm:flex-row sm:items-center sm:gap-3 sm:p-3 sm:px-4',
                            accentClasses(m.accent, m.active).wrap,
                        ]"
                    >
                        <div
                            v-if="m.active"
                            :class="[
                                'pointer-events-none absolute -right-6 -top-6 h-16 w-16 rounded-full blur-2xl',
                                accentClasses(m.accent, m.active).glow,
                            ]"
                            aria-hidden="true"
                        />

                        <!-- Top row on mobile: icon + label side-by-side -->
                        <div class="relative z-10 flex w-full items-center gap-2 sm:w-auto sm:gap-0">
                            <div
                                :class="[
                                    'relative flex shrink-0 items-center justify-center rounded-lg border',
                                    'h-8 w-8 sm:h-10 sm:w-10',
                                    accentClasses(m.accent, m.active).iconWrap,
                                ]"
                            >
                                <component :is="m.icon" class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                <span
                                    v-if="m.active"
                                    :class="[
                                        'absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full ring-2 ring-background animate-pulse',
                                        accentClasses(m.accent, m.active).dot,
                                    ]"
                                    aria-hidden="true"
                                />
                            </div>
                            <!-- Mobile-only label beside icon; hidden on sm+ (shown in the bottom block) -->
                            <p class="text-[9px] font-black uppercase tracking-[0.18em] text-muted-foreground/70 truncate sm:hidden">
                                {{ m.label }}
                            </p>
                        </div>

                        <!-- Bottom/right content -->
                        <div class="relative z-10 flex min-w-0 flex-1 flex-col sm:flex-col">
                            <p class="hidden sm:block text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">
                                {{ m.label }}
                            </p>
                            <div class="flex items-baseline gap-1.5">
                                <span
                                    :class="[
                                        'text-2xl font-black leading-none tracking-tight tabular-nums sm:text-3xl',
                                        accentClasses(m.accent, m.active).value,
                                    ]"
                                >
                                    {{ m.value }}
                                </span>
                                <!-- Sub-label hidden on xs (tight space); shown from sm+ -->
                                <span class="hidden truncate text-[10px] text-muted-foreground/70 sm:inline">
                                    {{ m.sub }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Right: Next item with live countdown -->
            <Link
                v-if="nextItem"
                :href="nextItem.href"
                :class="[
                    'group relative flex items-center gap-3 overflow-hidden rounded-xl border px-4 py-3 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg lg:w-[32%] lg:min-w-[280px]',
                    nextToneClasses.border,
                    'ring-1',
                    nextToneClasses.ring,
                ]"
            >
                <div
                    :class="[
                        'pointer-events-none absolute -right-8 -bottom-8 h-24 w-24 rounded-full blur-2xl opacity-60',
                        nextToneClasses.glow,
                    ]"
                    aria-hidden="true"
                />

                <div
                    :class="[
                        'relative z-10 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border',
                        nextToneClasses.chip,
                    ]"
                >
                    <Zap v-if="nextItem.kind === 'exam'" class="h-4 w-4" />
                    <Clock v-else class="h-4 w-4" />
                </div>

                <div class="relative z-10 min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p :class="['text-[9px] font-black uppercase tracking-[0.25em]', nextToneClasses.label]">
                            Next {{ nextItem.kind }}
                        </p>
                        <span
                            v-if="countdown"
                            :class="[
                                'rounded-full border px-1.5 py-px text-[9px] font-black uppercase tracking-widest tabular-nums',
                                nextToneClasses.chip,
                            ]"
                        >
                            {{ countdown.overdue ? '+' : '' }}{{ countdown.label }}
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-sm font-bold text-foreground group-hover:text-primary transition-colors">
                        {{ nextItem.title }}
                    </p>
                    <p v-if="nextItem.meta" class="truncate text-[10px] text-muted-foreground/80">
                        {{ nextItem.meta }}
                    </p>
                </div>

                <ArrowUpRight
                    class="relative z-10 h-4 w-4 shrink-0 text-muted-foreground transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary"
                />
            </Link>
        </div>
    </section>
</template>
