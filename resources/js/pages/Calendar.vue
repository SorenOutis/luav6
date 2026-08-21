<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    GraduationCap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface CalendarEvent {
    type: 'exam' | 'assignment';
    id: number;
    title: string;
    dateKey: string;
    sectionName?: string | null;
    courseName?: string | null;
    durationMinutes?: number;
    status?: string;
    submitted?: boolean;
    isOverdue?: boolean;
    isCompleted?: boolean;
    href: string;
}

interface CalendarSeason {
    id: number;
    name: string;
    startDateKey: string;
    endDateKey: string | null;
    isActive: boolean;
}

const props = defineProps<{
    events: CalendarEvent[];
    seasons: CalendarSeason[];
    todayKey: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calendar', href: '/calendar' },
];

// ─── Date-key helpers (UTC on Y-m-d strings) ────────────────────────────────
// Every bucketing decision runs on server-supplied Y-m-d keys instead of
// parsing ISO timestamps, so SSR and client hydration always agree and the
// dates match the ones the dashboard formats server-side.

const pad = (value: number) => String(value).padStart(2, '0');

const parseKey = (key: string) => {
    const [year, month, day] = key.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
};

const dayNumberOf = (key: string) => parseKey(key).getUTCDate();

const addMonths = (key: string, delta: number) => {
    const date = parseKey(`${key.slice(0, 7)}-01`);
    date.setUTCMonth(date.getUTCMonth() + delta);

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-01`;
};

const dayDiffFromToday = (key: string) =>
    Math.round(
        (parseKey(key).getTime() - parseKey(props.todayKey).getTime()) /
            86_400_000,
    );

const formatDayLabel = (key: string) =>
    parseKey(key).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

const formatShortDate = (key: string) =>
    parseKey(key).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

// ─── Month grid ─────────────────────────────────────────────────────────────

const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const monthAnchor = ref(props.todayKey.slice(0, 7));
const isCurrentMonth = computed(
    () => monthAnchor.value === props.todayKey.slice(0, 7),
);

const monthLabel = computed(() =>
    parseKey(`${monthAnchor.value}-01`).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }),
);

const gridDays = computed(() => {
    const firstOfMonth = parseKey(`${monthAnchor.value}-01`);
    const start = new Date(
        firstOfMonth.getTime() - firstOfMonth.getUTCDay() * 86_400_000,
    );

    return Array.from({ length: 42 }, (_, index) => {
        const day = new Date(start.getTime() + index * 86_400_000);

        return `${day.getUTCFullYear()}-${pad(day.getUTCMonth() + 1)}-${pad(day.getUTCDate())}`;
    });
});

// ─── Filters ────────────────────────────────────────────────────────────────

const showExams = ref(true);
const showAssignments = ref(true);
const hideSubmitted = ref(false);

const visibleEvents = computed(() =>
    props.events.filter((event) => {
        if (event.type === 'exam' ? !showExams.value : !showAssignments.value) {
            return false;
        }

        return !(hideSubmitted.value && (event.submitted || event.isCompleted));
    }),
);

const eventsByDay = computed(() => {
    const byDay = new Map<string, CalendarEvent[]>();

    for (const event of visibleEvents.value) {
        const list = byDay.get(event.dateKey);

        if (list) {
            list.push(event);
        } else {
            byDay.set(event.dateKey, [event]);
        }
    }

    return byDay;
});

const monthEvents = computed(() =>
    visibleEvents.value.filter(
        (event) => event.dateKey.slice(0, 7) === monthAnchor.value,
    ),
);

const monthExamCount = computed(
    () => monthEvents.value.filter((event) => event.type === 'exam').length,
);

const monthAssignmentCount = computed(
    () =>
        monthEvents.value.filter((event) => event.type === 'assignment').length,
);

// ─── Seasons overlapping the visible month ──────────────────────────────────

const seasonsForMonth = computed(() =>
    props.seasons.filter((season) => {
        const monthStart = `${monthAnchor.value}-01`;
        const monthEnd = gridDays.value[41];

        return (
            season.startDateKey <= monthEnd &&
            (season.endDateKey ?? '9999-12-31') >= monthStart
        );
    }),
);

const seasonRangeLabel = (season: CalendarSeason) =>
    season.endDateKey
        ? `${formatShortDate(season.startDateKey)} – ${formatShortDate(season.endDateKey)}`
        : `Started ${formatShortDate(season.startDateKey)}`;

// ─── Upcoming panel ─────────────────────────────────────────────────────────

const upcomingEvents = computed(() =>
    visibleEvents.value
        .filter((event) => event.dateKey >= props.todayKey)
        .slice(0, 6),
);

const relativeBadge = (key: string) => {
    const diff = dayDiffFromToday(key);

    if (diff === 0) {
        return {
            label: 'Today',
            class: 'border-transparent bg-destructive text-white',
        };
    }

    if (diff === 1) {
        return {
            label: 'Tomorrow',
            class: 'border-transparent bg-primary text-primary-foreground',
        };
    }

    if (diff < 0) {
        return {
            label: `${Math.abs(diff)}d late`,
            class: 'border-transparent bg-destructive/15 text-red-600 dark:text-red-400',
        };
    }

    return { label: `In ${diff} days`, class: 'text-muted-foreground' };
};

// ─── Event chip presentation ────────────────────────────────────────────────

const chipClasses = (event: CalendarEvent) => {
    if (event.type === 'exam') {
        return 'bg-primary/15 text-primary hover:bg-primary/25 dark:bg-primary/25';
    }

    if (event.isOverdue) {
        return 'bg-destructive/15 text-red-600 hover:bg-destructive/25 dark:text-red-400';
    }

    return 'bg-amber-500/15 text-amber-700 hover:bg-amber-500/25 dark:text-amber-300';
};

const eventMeta = (event: CalendarEvent) => {
    if (event.type === 'exam') {
        return [
            event.sectionName,
            event.durationMinutes ? `${event.durationMinutes} min` : null,
        ]
            .filter(Boolean)
            .join(' · ');
    }

    return event.courseName ?? 'Assignment';
};

const eventTooltip = (event: CalendarEvent) => {
    const parts = [
        event.type === 'exam' ? 'Exam' : 'Assignment',
        formatDayLabel(event.dateKey),
        eventMeta(event),
    ].filter(Boolean);

    if (event.submitted || event.isCompleted) {
        parts.push('Submitted');
    }

    return parts.join(' · ');
};
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full space-y-6 p-4 sm:p-6 lg:p-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <Link
                        href="/dashboard"
                        class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition hover:text-foreground"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to dashboard
                    </Link>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/15 text-primary"
                        >
                            <CalendarDays class="h-5 w-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-black tracking-[0.2em] text-primary uppercase"
                            >
                                Never miss a deadline
                            </p>
                            <h1
                                class="text-3xl font-black tracking-tight sm:text-4xl"
                            >
                                Calendar
                            </h1>
                        </div>
                    </div>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                    >
                        Every exam and assignment deadline for your sections in
                        one monthly view — plus what's coming up next.
                    </p>
                </div>
            </div>

            <div
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
            >
                <Card class="border-border/60 bg-card/40">
                    <CardHeader class="gap-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <CardTitle class="text-xl">{{
                                monthLabel
                            }}</CardTitle>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    aria-label="Previous month"
                                    @click="
                                        monthAnchor = addMonths(monthAnchor, -1)
                                    "
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="!isCurrentMonth"
                                    variant="secondary"
                                    size="sm"
                                    @click="monthAnchor = todayKey.slice(0, 7)"
                                >
                                    Today
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    aria-label="Next month"
                                    @click="
                                        monthAnchor = addMonths(monthAnchor, 1)
                                    "
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="
                                    showExams
                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-pressed="showExams"
                                @click="showExams = !showExams"
                            >
                                <GraduationCap class="h-3.5 w-3.5" />
                                Exams
                                <span v-if="monthExamCount"
                                    >({{ monthExamCount }})</span
                                >
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="
                                    showAssignments
                                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-pressed="showAssignments"
                                @click="showAssignments = !showAssignments"
                            >
                                <ClipboardList class="h-3.5 w-3.5" />
                                Assignments
                                <span v-if="monthAssignmentCount"
                                    >({{ monthAssignmentCount }})</span
                                >
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                :class="
                                    !hideSubmitted
                                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-pressed="hideSubmitted"
                                @click="hideSubmitted = !hideSubmitted"
                            >
                                <Check class="h-3.5 w-3.5" />
                                Hide submitted
                            </button>

                            <span
                                class="mx-1 hidden h-4 w-px bg-border sm:block"
                            />

                            <Badge
                                v-for="season in seasonsForMonth"
                                :key="season.id"
                                variant="outline"
                                class="gap-1.5 text-muted-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        season.isActive
                                            ? 'bg-emerald-500'
                                            : 'bg-muted-foreground/50'
                                    "
                                />
                                {{ season.name }} ·
                                {{ seasonRangeLabel(season) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="overflow-hidden rounded-lg border border-border"
                        >
                            <div class="grid grid-cols-7 bg-muted/50">
                                <div
                                    v-for="label in weekdayLabels"
                                    :key="label"
                                    class="px-1 py-2 text-center text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ label }}
                                </div>
                            </div>
                            <div class="grid grid-cols-7">
                                <div
                                    v-for="(dayKey, index) in gridDays"
                                    :key="dayKey"
                                    class="min-h-20 border-t border-l border-border p-1 md:min-h-28 md:p-1.5"
                                    :class="[
                                        index % 7 === 0 ? 'border-l-0' : '',
                                        dayKey.slice(0, 7) === monthAnchor
                                            ? 'bg-background'
                                            : 'bg-muted/30',
                                    ]"
                                    :aria-current="
                                        dayKey === todayKey ? 'date' : undefined
                                    "
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="
                                                dayKey === todayKey
                                                    ? 'bg-primary text-primary-foreground'
                                                    : dayKey.slice(0, 7) ===
                                                        monthAnchor
                                                      ? 'text-foreground'
                                                      : 'text-muted-foreground/50'
                                            "
                                        >
                                            {{ dayNumberOf(dayKey) }}
                                        </span>
                                    </div>

                                    <div class="mt-1 space-y-1">
                                        <Link
                                            v-for="event in (
                                                eventsByDay.get(dayKey) ?? []
                                            ).slice(0, 3)"
                                            :key="`${event.type}-${event.id}`"
                                            :href="event.href"
                                            :title="eventTooltip(event)"
                                            class="flex items-center gap-1 rounded px-1 py-0.5 text-[10px] leading-tight font-medium transition md:text-xs"
                                            :class="chipClasses(event)"
                                        >
                                            <Check
                                                v-if="
                                                    event.submitted ||
                                                    event.isCompleted
                                                "
                                                class="h-3 w-3 shrink-0"
                                            />
                                            <span
                                                class="truncate"
                                                :class="{
                                                    'line-through opacity-70':
                                                        event.submitted ||
                                                        event.isCompleted,
                                                }"
                                            >
                                                {{ event.title }}
                                            </span>
                                        </Link>
                                        <p
                                            v-if="
                                                (eventsByDay.get(dayKey) ?? [])
                                                    .length > 3
                                            "
                                            class="px-1 text-[10px] font-medium text-muted-foreground"
                                        >
                                            +{{
                                                (eventsByDay.get(dayKey) ?? [])
                                                    .length - 3
                                            }}
                                            more
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="monthEvents.length === 0"
                            class="mt-4 text-center text-sm text-muted-foreground"
                        >
                            Nothing scheduled this month.
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border/60 bg-card/40">
                    <CardHeader>
                        <CardTitle class="text-lg">Coming up</CardTitle>
                        <CardDescription>
                            Your next deadlines and exams, nearest first.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="upcomingEvents.length" class="space-y-3">
                            <li
                                v-for="event in upcomingEvents"
                                :key="`upcoming-${event.type}-${event.id}`"
                            >
                                <Link
                                    :href="event.href"
                                    class="flex items-start gap-3 rounded-lg border border-border/60 bg-background/60 p-3 transition hover:border-primary/40 hover:bg-accent/40"
                                >
                                    <span
                                        class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg"
                                        :class="
                                            event.type === 'exam'
                                                ? 'bg-primary/15 text-primary'
                                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                        "
                                    >
                                        <GraduationCap
                                            v-if="event.type === 'exam'"
                                            class="h-4 w-4"
                                        />
                                        <ClipboardList v-else class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm font-semibold"
                                            :class="{
                                                'line-through opacity-70':
                                                    event.submitted ||
                                                    event.isCompleted,
                                            }"
                                        >
                                            {{ event.title }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            {{ eventMeta(event) }}
                                        </span>
                                        <span
                                            class="mt-1.5 flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="text-xs font-medium text-muted-foreground"
                                            >
                                                {{
                                                    formatDayLabel(
                                                        event.dateKey,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                                                :class="
                                                    relativeBadge(event.dateKey)
                                                        .class
                                                "
                                            >
                                                {{
                                                    relativeBadge(event.dateKey)
                                                        .label
                                                }}
                                            </span>
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Nothing scheduled ahead — enjoy the breather!
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
