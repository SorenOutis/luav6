<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Award,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Printer,
    Search,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

export interface ActivityScoreItem {
    id: number;
    title: string;
    term: string;
    section_name: string | null;
    school_level?: string;
    season_name?: string;
    score: number | null;
    total_points: number;
    percentage: number | null;
    submitted: boolean;
    is_missed?: boolean;
    is_incomplete?: boolean;
    is_pending_review?: boolean;
    submitted_parts?: number;
    total_parts?: number;
    is_late?: boolean;
    ends_at_iso?: string | null;
    state: 'completed' | 'in_progress' | 'open' | 'closed' | 'draft';
}

export interface ScoreGroup {
    seasonName: string;
    exams: ActivityScoreItem[];
}

const props = defineProps<{
    open: boolean;
    groups: ScoreGroup[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    review: [examId: number];
    openExam: [examId: number];
}>();

const page = usePage();
const currentUser = computed(
    () => page.props.auth?.user as { name?: string; email?: string } | null,
);

// Flatten all activities
const allActivities = computed(() => props.groups.flatMap((g) => g.exams));

// Filters
const searchQuery = ref('');
const selectedTerm = ref('all');
const selectedStatus = ref<'all' | 'completed' | 'missed' | 'pending'>('all');

// Extract unique terms
const availableTerms = computed(() => {
    const set = new Set<string>();
    for (const item of allActivities.value) {
        if (item.term && item.term.trim() !== '') {
            set.add(item.term);
        }
    }
    return Array.from(set).sort();
});

// KPIs
const completedCount = computed(
    () =>
        allActivities.value.filter(
            (a) => a.state === 'completed' && !a.is_missed,
        ).length,
);
const missedCount = computed(
    () =>
        allActivities.value.filter(
            (a) => a.is_missed || (a.state === 'closed' && !a.submitted),
        ).length,
);
const inProgressCount = computed(
    () =>
        allActivities.value.filter(
            (a) =>
                a.state === 'in_progress' ||
                (a.state === 'open' && !a.is_missed),
        ).length,
);

const totalEarnedPoints = computed(() => {
    return allActivities.value.reduce((acc, a) => {
        if (a.score !== null && (a.state === 'completed' || a.submitted)) {
            return acc + Number(a.score);
        }
        return acc;
    }, 0);
});

const totalMaxPoints = computed(() => {
    return allActivities.value.reduce((acc, a) => {
        // Count total points for activities that have been graded or missed
        if (
            a.submitted ||
            a.is_missed ||
            a.state === 'closed' ||
            a.state === 'completed'
        ) {
            return acc + Number(a.total_points || 0);
        }
        return acc;
    }, 0);
});

const overallPercentage = computed(() => {
    if (totalMaxPoints.value <= 0) return null;
    return (
        Math.round((totalEarnedPoints.value / totalMaxPoints.value) * 1000) / 10
    );
});

// Filtered list
const filteredActivities = computed(() => {
    let list = allActivities.value;

    if (searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (a) =>
                a.title.toLowerCase().includes(q) ||
                (a.section_name && a.section_name.toLowerCase().includes(q)) ||
                (a.term && a.term.toLowerCase().includes(q)),
        );
    }

    if (selectedTerm.value !== 'all') {
        list = list.filter((a) => a.term === selectedTerm.value);
    }

    if (selectedStatus.value === 'completed') {
        list = list.filter((a) => a.state === 'completed' && !a.is_missed);
    } else if (selectedStatus.value === 'missed') {
        list = list.filter(
            (a) => a.is_missed || (a.state === 'closed' && !a.submitted),
        );
    } else if (selectedStatus.value === 'pending') {
        list = list.filter(
            (a) =>
                a.state === 'in_progress' ||
                a.state === 'open' ||
                a.is_pending_review,
        );
    }

    return list;
});

// Group filtered activities by Term (or by Season if no term)
interface TermGroup {
    term: string;
    activities: ActivityScoreItem[];
    subtotalScore: number;
    subtotalMax: number;
    subtotalPercentage: number | null;
}

const groupedByTerm = computed<TermGroup[]>(() => {
    const map = new Map<string, ActivityScoreItem[]>();

    for (const act of filteredActivities.value) {
        const key = act.term || 'General / Other';
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(act);
    }

    return Array.from(map.entries()).map(([term, items]) => {
        const earned = items.reduce((acc, a) => {
            if (a.score !== null && (a.state === 'completed' || a.submitted)) {
                return acc + Number(a.score);
            }
            return acc;
        }, 0);

        const max = items.reduce((acc, a) => {
            if (
                a.submitted ||
                a.is_missed ||
                a.state === 'closed' ||
                a.state === 'completed'
            ) {
                return acc + Number(a.total_points || 0);
            }
            return acc;
        }, 0);

        const pct = max > 0 ? Math.round((earned / max) * 1000) / 10 : null;

        return {
            term,
            activities: items,
            subtotalScore: Math.round(earned * 100) / 100,
            subtotalMax: Math.round(max * 100) / 100,
            subtotalPercentage: pct,
        };
    });
});

const activityScoreDisplay = (activity: ActivityScoreItem): string => {
    const total = activity.total_points.toFixed(0);

    if (
        activity.is_missed ||
        (activity.state === 'closed' && !activity.submitted)
    ) {
        return `0 / ${total}`;
    }

    if (activity.is_pending_review) {
        return 'Pending';
    }

    if (
        activity.score !== null &&
        (activity.state === 'completed' ||
            activity.submitted ||
            activity.is_incomplete)
    ) {
        return `${activity.score.toFixed(0)} / ${total}`;
    }

    return '—';
};

const activityScoreFraction = (activity: ActivityScoreItem): string => {
    const total = activity.total_points.toFixed(0);

    if (
        activity.is_missed ||
        (activity.state === 'closed' && !activity.submitted)
    ) {
        return `0 / ${total}`;
    }

    if (activity.is_pending_review) {
        return `— / ${total}`;
    }

    if (
        activity.score !== null &&
        (activity.state === 'completed' ||
            activity.submitted ||
            activity.is_incomplete)
    ) {
        return `${activity.score.toFixed(0)} / ${total}`;
    }

    return `— / ${total}`;
};

const activityPercentageDisplay = (activity: ActivityScoreItem): string => {
    if (
        activity.is_missed ||
        (activity.state === 'closed' && !activity.submitted)
    ) {
        return '0.0%';
    }

    if (activity.is_pending_review || activity.score === null) {
        return '—';
    }

    if (activity.total_points > 0) {
        const pct =
            (Number(activity.score) / Number(activity.total_points)) * 100;
        return `${pct.toFixed(1)}%`;
    }

    return '—';
};

const activityStatusLabel = (activity: ActivityScoreItem): string => {
    if (
        activity.is_missed ||
        (activity.state === 'closed' && !activity.submitted)
    ) {
        return 'Missed';
    }

    if (activity.is_pending_review) {
        return 'Pending Review';
    }

    if (activity.state === 'completed' || activity.submitted) {
        return activity.is_late ? 'Completed (Late)' : 'Completed';
    }

    if (activity.state === 'in_progress') {
        return 'In Progress';
    }

    if (activity.state === 'open') {
        return 'Open / Untaken';
    }

    return 'Pending';
};

const handlePrint = () => {
    window.print();
};
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            class="custom-scrollbar flex w-full flex-col gap-0 overflow-y-auto sm:max-w-xl md:max-w-2xl lg:w-1/2 lg:max-w-none"
            data-lenis-prevent
        >
            <!-- Header -->
            <SheetHeader class="screen-only border-b border-border/50 pb-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#D97757]/15 text-[#D97757]"
                        >
                            <FileSpreadsheet class="h-5 w-5" />
                        </div>
                        <div>
                            <SheetTitle
                                class="text-xl font-bold tracking-tight text-foreground"
                            >
                                Activity Record
                            </SheetTitle>
                            <SheetDescription
                                class="text-xs text-muted-foreground"
                            >
                                Complete breakdown of activities, scores, and
                                missed tasks
                            </SheetDescription>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Overall Total Score -->
                        <div
                            v-if="totalMaxPoints > 0"
                            data-test="overall-total-score"
                            class="flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/10 px-2.5 py-1.5 text-xs shadow-2xs"
                        >
                            <span
                                class="text-[11px] font-medium text-muted-foreground"
                                >Total Score:</span
                            >
                            <span
                                class="font-bold text-foreground tabular-nums"
                            >
                                {{ totalEarnedPoints.toFixed(0) }} /
                                {{ totalMaxPoints.toFixed(0) }}
                            </span>
                            <span
                                v-if="overallPercentage !== null"
                                class="font-semibold text-primary tabular-nums"
                            >
                                ({{ overallPercentage.toFixed(0) }}%)
                            </span>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="hidden h-8 items-center gap-1.5 rounded-lg border-border/60 text-xs font-medium sm:inline-flex"
                            @click="handlePrint"
                        >
                            <Printer
                                class="h-3.5 w-3.5 text-muted-foreground"
                            />
                            Print Record
                        </Button>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="mt-3 space-y-2.5">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search activity by title or section..."
                            class="h-9 w-full rounded-lg border-border/60 pl-8 text-xs placeholder:text-muted-foreground/60"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="searchQuery = ''"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Term Navigation Pills (Per Quarter / Per Semester / Prelims) -->
                    <div
                        v-if="availableTerms.length > 0"
                        class="flex flex-wrap items-center gap-1.5 pt-0.5"
                    >
                        <span
                            class="text-[11px] font-semibold text-muted-foreground"
                            >Term:</span
                        >
                        <button
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedTerm === 'all'
                                    ? 'bg-primary font-semibold text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                            "
                            @click="selectedTerm = 'all'"
                        >
                            All Periods
                        </button>
                        <button
                            v-for="t in availableTerms"
                            :key="t"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedTerm === t
                                    ? 'bg-primary font-semibold text-primary-foreground shadow-sm'
                                    : 'bg-muted/70 text-muted-foreground hover:bg-muted'
                            "
                            @click="selectedTerm = t"
                        >
                            {{ t }}
                        </button>
                    </div>

                    <!-- Status Filter Tabs -->
                    <div
                        role="group"
                        aria-label="Filter activities by status"
                        data-test="activity-status-filters"
                        class="flex min-w-0 items-center gap-1 overflow-x-auto overscroll-x-contain border-t border-border/40 pt-2 pb-1 text-xs [&>button]:min-h-9 [&>button]:shrink-0 [&>button]:rounded-full [&>button]:px-2.5 [&>button]:whitespace-nowrap [&>button]:focus-visible:outline-2 [&>button]:focus-visible:outline-offset-2 [&>button]:focus-visible:outline-ring"
                    >
                        <span
                            class="hidden shrink-0 text-[11px] font-semibold text-muted-foreground sm:inline"
                            >Status:</span
                        >
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedStatus === 'all'
                                    ? 'border border-border bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="selectedStatus === 'all'"
                            @click="selectedStatus = 'all'"
                        >
                            All ({{ allActivities.length }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedStatus === 'completed'
                                    ? 'bg-[#4D9375]/15 font-semibold text-[#4D9375]'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="selectedStatus === 'completed'"
                            @click="selectedStatus = 'completed'"
                        >
                            Completed ({{ completedCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedStatus === 'missed'
                                    ? 'bg-[#CB7676]/15 font-semibold text-[#CB7676]'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="selectedStatus === 'missed'"
                            @click="selectedStatus = 'missed'"
                        >
                            Missed ({{ missedCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedStatus === 'pending'
                                    ? 'bg-[#E0AF68]/15 font-semibold text-[#E0AF68]'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="selectedStatus === 'pending'"
                            @click="selectedStatus = 'pending'"
                        >
                            In Progress ({{ inProgressCount }})
                        </button>
                    </div>
                </div>
            </SheetHeader>

            <!-- Printable Transcript Section (Report Card in rows when printing) -->
            <div class="printable-report-card printable-record hidden p-4">
                <div class="border-b-2 border-black pb-3 text-center">
                    <h2 class="text-xl font-bold tracking-wide uppercase">
                        Student Activity Record
                    </h2>
                    <p
                        class="text-xs tracking-wider text-neutral-600 uppercase"
                    >
                        Official Academic Performance & Evaluation Slip
                    </p>
                </div>

                <!-- Student & Evaluation Info Grid -->
                <div
                    class="my-4 border border-black p-3 text-xs leading-relaxed"
                >
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                        <div>
                            <p>
                                <strong>Student:</strong>
                                {{ currentUser?.name ?? 'Student' }}
                            </p>
                            <p>
                                <strong>Email:</strong>
                                {{ currentUser?.email ?? '—' }}
                            </p>
                            <p v-if="allActivities[0]?.section_name">
                                <strong>Section:</strong>
                                {{ allActivities[0].section_name }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p>
                                <strong>Date Generated:</strong>
                                {{ new Date().toLocaleDateString() }}
                            </p>
                            <p>
                                <strong>Total Points:</strong>
                                {{ totalEarnedPoints.toFixed(0) }} /
                                {{ totalMaxPoints.toFixed(0) }} ({{
                                    (overallPercentage ?? 0).toFixed(0)
                                }}%)
                            </p>
                            <p>
                                <strong>Tasks Completed:</strong>
                                {{ completedCount }} of
                                {{ allActivities.length }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Report Card Tables (Grouped by Term, each activity rendered as a row) -->
                <div class="space-y-6">
                    <div
                        v-for="group in groupedByTerm"
                        :key="group.term"
                        class="break-inside-avoid"
                    >
                        <div
                            class="mb-1 flex items-center justify-between border-b border-black pb-1"
                        >
                            <h3
                                class="text-xs font-bold tracking-wider text-black uppercase"
                            >
                                Period: {{ group.term }}
                            </h3>
                            <span
                                v-if="group.subtotalMax > 0"
                                class="text-xs font-bold text-black"
                            >
                                Term Subtotal:
                                {{ group.subtotalScore.toFixed(0) }} /
                                {{ group.subtotalMax.toFixed(0) }}
                                <span v-if="group.subtotalPercentage !== null">
                                    ({{ group.subtotalPercentage.toFixed(0) }}%)
                                </span>
                            </span>
                        </div>

                        <table
                            class="report-card-table w-full border-collapse border border-black text-left text-xs"
                        >
                            <thead>
                                <tr
                                    class="border-b border-black bg-neutral-100"
                                >
                                    <th
                                        class="w-8 border border-black p-1.5 text-center font-bold"
                                    >
                                        #
                                    </th>
                                    <th
                                        class="border border-black p-1.5 font-bold"
                                    >
                                        Activity Title
                                    </th>
                                    <th
                                        class="w-32 border border-black p-1.5 font-bold"
                                    >
                                        Section
                                    </th>
                                    <th
                                        class="w-24 border border-black p-1.5 text-center font-bold"
                                    >
                                        Score
                                    </th>
                                    <th
                                        class="w-20 border border-black p-1.5 text-center font-bold"
                                    >
                                        Rating
                                    </th>
                                    <th
                                        class="w-28 border border-black p-1.5 text-center font-bold"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(activity, aIdx) in group.activities"
                                    :key="activity.id"
                                    class="border-b border-neutral-300"
                                >
                                    <td
                                        class="border border-black p-1.5 text-center font-mono"
                                    >
                                        {{ aIdx + 1 }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 font-medium text-black"
                                    >
                                        {{ activity.title }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-neutral-700"
                                    >
                                        {{ activity.section_name || '—' }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center font-mono font-bold text-black"
                                    >
                                        {{ activityScoreFraction(activity) }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center font-mono"
                                    >
                                        {{
                                            activityPercentageDisplay(activity)
                                        }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center font-medium"
                                    >
                                        {{ activityStatusLabel(activity) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="group.subtotalMax > 0">
                                <tr
                                    class="border-t-2 border-black bg-neutral-50 font-bold"
                                >
                                    <td
                                        colspan="3"
                                        class="border border-black p-1.5 text-right uppercase"
                                    >
                                        {{ group.term }} Subtotal:
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center font-mono"
                                    >
                                        {{ group.subtotalScore.toFixed(0) }} /
                                        {{ group.subtotalMax.toFixed(0) }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center font-mono"
                                    >
                                        {{
                                            group.subtotalPercentage !== null
                                                ? group.subtotalPercentage.toFixed(
                                                      0,
                                                  ) + '%'
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        class="border border-black p-1.5 text-center text-[10px]"
                                    >
                                        {{ group.activities.length }}
                                        {{
                                            group.activities.length === 1
                                                ? 'activity'
                                                : 'activities'
                                        }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Cumulative Summary Box -->
                <div
                    class="mt-4 break-inside-avoid border-2 border-black p-2.5 text-xs"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <span
                                class="font-bold tracking-wider text-black uppercase"
                            >
                                Cumulative Performance Total
                            </span>
                            <p class="text-[10px] text-neutral-600">
                                Total score across all recorded activities
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-bold text-black">
                                {{ totalEarnedPoints.toFixed(0) }} /
                                {{ totalMaxPoints.toFixed(0) }} pts
                            </span>
                            <span
                                v-if="overallPercentage !== null"
                                class="ml-1.5 font-bold text-black"
                            >
                                ({{ overallPercentage.toFixed(0) }}%)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Signatures Section -->
                <div class="mt-12 break-inside-avoid pt-4">
                    <div class="flex justify-between text-xs">
                        <div
                            class="w-56 border-t border-black pt-1 text-center"
                        >
                            <p class="font-bold text-black">
                                {{ currentUser?.name ?? 'Student' }}
                            </p>
                            <p
                                class="text-[10px] tracking-wider text-neutral-600 uppercase"
                            >
                                Student Signature
                            </p>
                        </div>
                        <div
                            class="w-56 border-t border-black pt-1 text-center"
                        >
                            <p class="font-bold text-black">
                                Teacher / Adviser
                            </p>
                            <p
                                class="text-[10px] tracking-wider text-neutral-600 uppercase"
                            >
                                Faculty Signature & Date
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activities Content Body -->
            <div class="screen-only flex-1 space-y-6 px-1 py-4">
                <div
                    v-if="filteredActivities.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-16 text-center"
                >
                    <Calendar class="h-10 w-10 text-muted-foreground/40" />
                    <p class="text-sm font-medium text-foreground">
                        No activities match the filter
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Try clearing search or switching term/status tabs.
                    </p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Grouped by Term / Quarter / Prelim -->
                    <section
                        v-for="group in groupedByTerm"
                        :key="group.term"
                        class="space-y-2"
                    >
                        <!-- Group Header with Subtotal -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 px-1 pb-1.5"
                        >
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <Award class="h-4 w-4 text-primary" />
                                <h3
                                    class="text-sm font-bold tracking-tight text-foreground"
                                >
                                    {{ group.term }}
                                </h3>
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground tabular-nums"
                                >
                                    {{ group.activities.length }}
                                    {{
                                        group.activities.length === 1
                                            ? 'activity'
                                            : 'activities'
                                    }}
                                </span>
                            </div>

                            <!-- Term Total Score -->
                            <div
                                v-if="group.subtotalMax > 0"
                                class="inline-flex items-center gap-1.5 rounded-full border border-border/50 bg-muted/40 px-2.5 py-0.5 text-xs shadow-2xs"
                            >
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                    >Total Score:</span
                                >
                                <span
                                    class="font-bold text-foreground tabular-nums"
                                >
                                    {{ group.subtotalScore.toFixed(0) }} /
                                    {{ group.subtotalMax.toFixed(0) }}
                                </span>
                                <span
                                    v-if="group.subtotalPercentage !== null"
                                    class="font-semibold text-primary tabular-nums"
                                >
                                    ({{ group.subtotalPercentage.toFixed(0) }}%)
                                </span>
                            </div>
                        </div>

                        <!-- Activity Record Table -->
                        <div
                            class="custom-scrollbar max-w-full overflow-x-auto rounded-lg border border-border/50 bg-muted/15 p-0.5 focus-visible:outline-2 focus-visible:outline-ring"
                            tabindex="0"
                            role="region"
                            :aria-label="`${group.term} activity records`"
                            data-lenis-prevent
                        >
                            <table
                                data-test="activity-record-table"
                                class="min-w-full table-fixed border-collapse text-left text-sm lg:!w-full"
                                :style="{
                                    width: `${(group.activities.length + (group.subtotalMax > 0 ? 1 : 0)) * 140}px`,
                                }"
                            >
                                <thead>
                                    <tr>
                                        <th
                                            v-for="(
                                                activity, idx
                                            ) in group.activities"
                                            :key="activity.id"
                                            scope="col"
                                            class="w-[140px] min-w-[140px] border border-border/50 bg-muted/40 px-2.5 py-2 align-top transition-colors sm:px-3 sm:py-2.5 lg:w-auto lg:min-w-[100px]"
                                        >
                                            <div class="flex flex-col gap-1">
                                                <div
                                                    class="flex items-center justify-between gap-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1 font-mono text-[10px] text-muted-foreground/90"
                                                    >
                                                        <span
                                                            class="h-1.5 w-1.5 rounded-full bg-primary/70"
                                                        />
                                                        Act {{ idx + 1 }}
                                                    </span>
                                                    <span
                                                        v-if="activity.is_late"
                                                        class="rounded bg-amber-500/15 px-1 py-0.5 text-[9px] font-semibold text-amber-600 dark:text-amber-400"
                                                    >
                                                        Late
                                                    </span>
                                                    <span
                                                        v-else-if="
                                                            activity.is_missed ||
                                                            (activity.state ===
                                                                'closed' &&
                                                                !activity.submitted)
                                                        "
                                                        class="rounded bg-[#CB7676]/15 px-1 py-0.5 text-[9px] font-semibold text-[#CB7676]"
                                                    >
                                                        Missed
                                                    </span>
                                                </div>
                                                <div
                                                    class="line-clamp-2 text-xs leading-snug font-semibold break-words text-foreground"
                                                    :title="activity.title"
                                                >
                                                    {{ activity.title }}
                                                </div>
                                                <div
                                                    v-if="activity.section_name"
                                                    class="truncate text-[10px] text-muted-foreground"
                                                    :title="
                                                        activity.section_name
                                                    "
                                                >
                                                    {{ activity.section_name }}
                                                </div>
                                            </div>
                                        </th>
                                        <!-- Total Score Column Header -->
                                        <th
                                            v-if="group.subtotalMax > 0"
                                            class="w-[140px] min-w-[140px] border border-primary/30 bg-primary/10 px-2.5 py-2 align-top transition-colors sm:px-3 sm:py-2.5 lg:w-auto lg:min-w-[110px]"
                                            data-test="activity-record-total-th"
                                        >
                                            <div class="flex flex-col gap-1">
                                                <div
                                                    class="flex items-center justify-between gap-1 text-[10px] font-bold tracking-wider text-primary uppercase"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1 font-mono text-[10px]"
                                                    >
                                                        <Award
                                                            class="h-3 w-3 text-primary"
                                                        />
                                                        Total
                                                    </span>
                                                    <span
                                                        v-if="
                                                            group.subtotalPercentage !==
                                                            null
                                                        "
                                                        class="rounded bg-primary/20 px-1 py-0.5 text-[9px] font-bold text-primary"
                                                    >
                                                        {{
                                                            group.subtotalPercentage.toFixed(
                                                                0,
                                                            )
                                                        }}%
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-xs leading-snug font-bold text-foreground"
                                                >
                                                    Total Score
                                                </div>
                                                <div
                                                    class="truncate text-[10px] text-muted-foreground"
                                                >
                                                    {{
                                                        group.activities.length
                                                    }}
                                                    {{
                                                        group.activities
                                                            .length === 1
                                                            ? 'activity'
                                                            : 'activities'
                                                    }}
                                                </div>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td
                                            v-for="activity in group.activities"
                                            :key="activity.id"
                                            data-test="activity-record-cell"
                                            :aria-label="activity.title"
                                            class="border border-border/50 bg-background/40 p-2 align-middle tabular-nums transition-colors hover:bg-muted/20"
                                        >
                                            <!-- Completed / Submitted -->
                                            <button
                                                v-if="
                                                    activity.submitted ||
                                                    activity.state ===
                                                        'completed'
                                                "
                                                type="button"
                                                :aria-label="
                                                    'Review answers for ' +
                                                    activity.title
                                                "
                                                class="group/cell flex w-full cursor-pointer items-center justify-between gap-1.5 rounded-md border border-[#4D9375]/30 bg-[#4D9375]/10 px-2.5 py-2 text-xs font-semibold text-[#4D9375] shadow-2xs transition-all duration-150 hover:border-[#4D9375]/60 hover:bg-[#4D9375]/20 hover:shadow-xs focus-visible:outline-2 focus-visible:outline-ring active:scale-[0.98]"
                                                @click="
                                                    emit('review', activity.id)
                                                "
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                >
                                                    {{
                                                        activityScoreDisplay(
                                                            activity,
                                                        )
                                                    }}
                                                </span>
                                                <CheckCircle2
                                                    class="h-3.5 w-3.5 shrink-0 opacity-75 transition-transform group-hover/cell:scale-110 group-hover/cell:opacity-100"
                                                />
                                            </button>

                                            <!-- Missed -->
                                            <div
                                                v-else-if="
                                                    activity.is_missed ||
                                                    (activity.state ===
                                                        'closed' &&
                                                        !activity.submitted)
                                                "
                                                class="flex w-full items-center justify-between gap-1.5 rounded-md border border-[#CB7676]/30 bg-[#CB7676]/10 px-2.5 py-2 text-xs font-semibold text-[#CB7676] shadow-2xs"
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                >
                                                    {{
                                                        activityScoreDisplay(
                                                            activity,
                                                        )
                                                    }}
                                                </span>
                                                <XCircle
                                                    class="h-3.5 w-3.5 shrink-0 opacity-75"
                                                />
                                            </div>

                                            <!-- Pending Review -->
                                            <div
                                                v-else-if="
                                                    activity.is_pending_review
                                                "
                                                class="flex w-full items-center justify-between gap-1.5 rounded-md border border-[#E0AF68]/30 bg-[#E0AF68]/10 px-2.5 py-2 text-xs font-semibold text-[#E0AF68] shadow-2xs"
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                >
                                                    Pending
                                                </span>
                                                <Clock
                                                    class="h-3.5 w-3.5 shrink-0 opacity-75"
                                                />
                                            </div>

                                            <!-- Open or In Progress -->
                                            <button
                                                v-else-if="
                                                    activity.state === 'open' ||
                                                    activity.state ===
                                                        'in_progress'
                                                "
                                                type="button"
                                                :aria-label="
                                                    'Open ' + activity.title
                                                "
                                                class="group/cell flex w-full cursor-pointer items-center justify-between gap-1.5 rounded-md border border-dashed border-[#E0AF68]/40 bg-[#E0AF68]/10 px-2.5 py-2 text-xs font-semibold text-[#E0AF68] shadow-2xs transition-all duration-150 hover:border-[#E0AF68]/70 hover:bg-[#E0AF68]/20 hover:shadow-xs focus-visible:outline-2 focus-visible:outline-ring active:scale-[0.98]"
                                                @click="
                                                    emit(
                                                        'openExam',
                                                        activity.id,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                    >—</span
                                                >
                                                <ArrowUpRight
                                                    class="h-3.5 w-3.5 shrink-0 opacity-70 transition-all group-hover/cell:translate-x-0.5 group-hover/cell:-translate-y-0.5 group-hover/cell:opacity-100"
                                                />
                                            </button>

                                            <!-- Fallback / Other -->
                                            <div
                                                v-else
                                                class="flex w-full items-center justify-between gap-1.5 rounded-md border border-border/40 bg-muted/20 px-2.5 py-2 text-xs font-medium text-muted-foreground"
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                >
                                                    {{
                                                        activityScoreDisplay(
                                                            activity,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <!-- Total Score Cell -->
                                        <td
                                            v-if="group.subtotalMax > 0"
                                            data-test="activity-record-total-cell"
                                            class="border border-primary/30 bg-primary/5 p-2 align-middle tabular-nums"
                                        >
                                            <div
                                                class="flex w-full items-center justify-between gap-1.5 rounded-md border border-primary/30 bg-primary/15 px-2.5 py-2 text-xs font-bold text-primary shadow-2xs"
                                            >
                                                <span
                                                    class="font-bold tracking-tight tabular-nums"
                                                >
                                                    {{
                                                        group.subtotalScore.toFixed(
                                                            0,
                                                        )
                                                    }}
                                                    /
                                                    {{
                                                        group.subtotalMax.toFixed(
                                                            0,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        group.subtotalPercentage !==
                                                        null
                                                    "
                                                    class="text-[11px] font-bold opacity-90"
                                                >
                                                    {{
                                                        group.subtotalPercentage.toFixed(
                                                            0,
                                                        )
                                                    }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

<style scoped>
@reference "../../../../css/app.css";

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--color-primary) 30%, transparent)
        transparent;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--color-primary) 20%, transparent);
    border-radius: 12px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--color-primary) 40%, transparent);
}

@media print {
    @page {
        size: auto;
        margin: 12mm 15mm;
    }

    body {
        background: #ffffff !important;
        color: #000000 !important;
    }

    body * {
        visibility: hidden;
    }

    /* Hide screen UI completely from print */
    .screen-only,
    .screen-only *,
    button,
    input,
    [data-slot='sheet-content'] > button {
        display: none !important;
        visibility: hidden !important;
    }

    /* Reveal only the printable report card */
    .printable-report-card,
    .printable-report-card * {
        visibility: visible !important;
    }

    .printable-report-card {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        color: #000000 !important;
        font-family:
            -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica,
            Arial, sans-serif !important;
        z-index: 999999 !important;
    }
}
</style>
