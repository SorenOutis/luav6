<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Calendar,
    FileSpreadsheet,
    Printer,
    Search,
    X,
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
    id: number | string;
    title: string;
    activity_type?: string;
    category?: 'written' | 'performance';
    term: string;
    section_id?: number | null;
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

export interface ComponentGroup {
    key: string;
    category: 'written' | 'performance';
    label: string;
    activities: ActivityScoreItem[];
    subtotalScore: number;
    subtotalMax: number;
    subtotalPercentage: number | null;
}

interface SectionGroup {
    key: string;
    id: number | null;
    name: string;
    seasonName: string;
    components: ComponentGroup[];
}

export interface TermGroup {
    term: string;
    activities: ActivityScoreItem[];
    sections: SectionGroup[];
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
const allActivities = computed(() =>
    props.groups.flatMap((group) =>
        group.exams.map((activity) => ({
            ...activity,
            season_name: activity.season_name || group.seasonName,
        })),
    ),
);

// Filters
const searchQuery = ref('');
const selectedTerm = ref('all');

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

const calcEarned = (items: ActivityScoreItem[]) =>
    items.reduce((acc, a) => {
        if (
            !a.is_missed &&
            !(a.state === 'closed' && !a.submitted) &&
            a.score !== null &&
            (a.state === 'completed' || a.submitted)
        ) {
            return acc + Number(a.score);
        }
        return acc;
    }, 0);

const calcMax = (items: ActivityScoreItem[]) =>
    items.reduce((acc, a) => {
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

const calcPct = (earned: number, max: number) =>
    max > 0 ? Math.round((earned / max) * 1000) / 10 : null;

const groupComponents = (items: ActivityScoreItem[]): ComponentGroup[] => {
    const groups = new Map<string, ComponentGroup>();

    for (const activity of items) {
        const category =
            activity.category ??
            (typeof activity.id === 'string' ? 'performance' : 'written');
        const activityType =
            activity.activity_type?.trim() || 'Performance Task';
        const key =
            category === 'written' ? 'written' : `performance:${activityType}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                category,
                label:
                    category === 'written'
                        ? 'Written Activities'
                        : activityType === 'Performance Task'
                          ? 'Performance Tasks'
                          : activityType,
                activities: [],
                subtotalScore: 0,
                subtotalMax: 0,
                subtotalPercentage: null,
            });
        }

        groups.get(key)!.activities.push(activity);
    }

    return Array.from(groups.values())
        .sort((a, b) => {
            if (a.category !== b.category) {
                return a.category === 'written' ? -1 : 1;
            }
            if (a.key === 'performance:Performance Task') {
                return -1;
            }
            if (b.key === 'performance:Performance Task') {
                return 1;
            }
            return 0;
        })
        .map((group) => {
            const earned = calcEarned(group.activities);
            const max = calcMax(group.activities);

            return {
                ...group,
                subtotalScore: Math.round(earned * 100) / 100,
                subtotalMax: Math.round(max * 100) / 100,
                subtotalPercentage: calcPct(earned, max),
            };
        });
};

const groupSections = (items: ActivityScoreItem[]): SectionGroup[] => {
    const sections = new Map<
        string,
        Omit<SectionGroup, 'components'> & { activities: ActivityScoreItem[] }
    >();

    for (const activity of items) {
        const name =
            activity.section_id === null
                ? 'General / Unassigned'
                : activity.section_name?.trim() || 'General / Unassigned';

        let seasonName =
            activity.season_name && activity.season_name !== 'Other'
                ? activity.season_name
                : '';

        if (
            !seasonName &&
            activity.section_id !== undefined &&
            activity.section_id !== null
        ) {
            const siblingsWithSeason = items.filter(
                (a) =>
                    a.section_id === activity.section_id &&
                    a.season_name &&
                    a.season_name !== 'Other',
            );
            const distinctSeasons = new Set(
                siblingsWithSeason.map((a) => a.season_name!),
            );
            if (distinctSeasons.size === 1) {
                seasonName = distinctSeasons.values().next().value!;
            }
        }

        const identity =
            activity.section_id === undefined
                ? ['name', name]
                : ['id', activity.section_id];
        const key = JSON.stringify([seasonName, ...identity]);

        if (!sections.has(key)) {
            sections.set(key, {
                key,
                id: activity.section_id ?? null,
                name,
                seasonName,
                activities: [],
            });
        }

        sections.get(key)!.activities.push(activity);
    }

    return Array.from(sections.values()).map(({ activities, ...section }) => ({
        ...section,
        components: groupComponents(activities),
    }));
};

const allSections = computed(() => groupSections(allActivities.value));

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

    return list;
});

const groupedByTerm = computed<TermGroup[]>(() => {
    const map = new Map<string, ActivityScoreItem[]>();

    for (const act of filteredActivities.value) {
        const key = act.term || 'General / Other';
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(act);
    }

    return Array.from(map.entries()).map(([term, items]) => ({
        term,
        activities: items,
        sections: groupSections(items),
    }));
});

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
                                Your activities and scores, grouped by period.
                            </SheetDescription>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
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
                    class="my-3 rounded border border-neutral-300 bg-neutral-50/60 p-2.5 text-xs leading-relaxed"
                >
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1.5">
                        <div>
                            <p>
                                <strong>Student:</strong>
                                {{ currentUser?.name ?? 'Student' }}
                            </p>
                            <p>
                                <strong>Email:</strong>
                                {{ currentUser?.email ?? '—' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p>
                                <strong>Date Generated:</strong>
                                {{ new Date().toLocaleDateString() }}
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
                        class="break-inside-avoid space-y-3"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between border-b-2 border-black pb-1"
                        >
                            <h3
                                class="text-xs font-bold tracking-wider text-black uppercase"
                            >
                                Period: {{ group.term }}
                            </h3>
                        </div>

                        <section
                            v-for="section in group.sections"
                            :key="section.key"
                            data-test="activity-record-print-section"
                            :data-section="section.name"
                            :data-section-id="section.id ?? 'unassigned'"
                            :data-season="section.seasonName"
                            class="space-y-3"
                        >
                            <h4 class="text-xs font-bold text-black">
                                Section: {{ section.name
                                }}<span v-if="section.seasonName">
                                    · {{ section.seasonName }}</span
                                >
                            </h4>
                            <div
                                v-for="component in section.components"
                                :key="component.key"
                                class="space-y-1"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-bold text-black"
                                >
                                    <h5 class="tracking-wider uppercase">
                                        {{ component.label }}
                                    </h5>
                                </div>

                                <table
                                    data-test="activity-record-print-component-table"
                                    :data-section="section.name"
                                    :data-section-id="
                                        section.id ?? 'unassigned'
                                    "
                                    :data-season="section.seasonName"
                                    :data-term="group.term"
                                    :data-component="component.key"
                                    :data-category="component.category"
                                    class="report-card-table w-full border-collapse border border-black text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b border-black bg-neutral-100"
                                        >
                                            <th
                                                class="w-10 border border-black p-1.5 text-center font-bold"
                                            >
                                                #
                                            </th>
                                            <th
                                                class="border border-black p-1.5 font-bold"
                                            >
                                                Activity Title
                                            </th>
                                            <th
                                                class="w-28 border border-black p-1.5 text-center font-bold"
                                            >
                                                Score
                                            </th>
                                            <th
                                                class="w-24 border border-black p-1.5 text-center font-bold"
                                            >
                                                Rating
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                activity, aIdx
                                            ) in component.activities"
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
                                                <div>{{ activity.title }}</div>
                                                <p
                                                    v-if="
                                                        activity.is_pending_review
                                                    "
                                                    class="text-[10px] font-normal text-neutral-500"
                                                >
                                                    Pending Review
                                                </p>
                                                <p
                                                    v-else-if="
                                                        activity.is_missed ||
                                                        (activity.state ===
                                                            'closed' &&
                                                            !activity.submitted)
                                                    "
                                                    class="text-[10px] font-normal text-red-600"
                                                >
                                                    Missed
                                                </p>
                                            </td>
                                            <td
                                                class="border border-black p-1.5 text-center font-mono font-bold text-black"
                                            >
                                                {{
                                                    activityScoreFraction(
                                                        activity,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="border border-black p-1.5 text-center font-mono"
                                            >
                                                {{
                                                    activityPercentageDisplay(
                                                        activity,
                                                    )
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr
                                            class="border-t-2 border-black bg-neutral-50 font-bold"
                                        >
                                            <td
                                                colspan="2"
                                                class="border border-black p-1.5 text-right uppercase"
                                            >
                                                {{ component.label }} Total:
                                            </td>
                                            <td
                                                class="border border-black p-1.5 text-center font-mono"
                                            >
                                                {{
                                                    component.subtotalScore.toFixed(
                                                        0,
                                                    )
                                                }}
                                                /
                                                {{
                                                    component.subtotalMax.toFixed(
                                                        0,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="border border-black p-1.5 text-center font-mono"
                                            >
                                                {{
                                                    component.subtotalPercentage !==
                                                    null
                                                        ? component.subtotalPercentage.toFixed(
                                                              0,
                                                          ) + '%'
                                                        : '—'
                                                }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Cumulative Section / Component Summaries -->
                <div
                    class="mt-4 break-inside-avoid border-t-2 border-black pt-3 text-xs"
                >
                    <h3 class="font-bold tracking-wider text-black uppercase">
                        Cumulative Summary
                    </h3>
                    <section
                        v-for="section in allSections"
                        :key="section.key"
                        :data-section="section.name"
                        :data-section-id="section.id ?? 'unassigned'"
                        :data-season="section.seasonName"
                        class="space-y-1.5 pt-1.5"
                    >
                        <h4 class="font-bold text-black">
                            Section: {{ section.name
                            }}<span v-if="section.seasonName">
                                · {{ section.seasonName }}</span
                            >
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <div
                                v-for="component in section.components"
                                :key="component.key"
                                data-test="activity-record-print-component-summary"
                                :data-section="section.name"
                                :data-section-id="section.id ?? 'unassigned'"
                                :data-season="section.seasonName"
                                :data-component="component.key"
                                :data-category="component.category"
                                class="flex items-center gap-1.5 rounded border border-neutral-300 bg-neutral-50 px-2.5 py-1 text-xs"
                            >
                                <span class="font-semibold text-neutral-700"
                                    >{{ component.label }} Total:</span
                                >
                                <span class="font-mono font-bold text-black">
                                    {{ component.subtotalScore.toFixed(0) }} /
                                    {{ component.subtotalMax.toFixed(0) }} pts
                                    <span
                                        v-if="
                                            component.subtotalPercentage !==
                                            null
                                        "
                                        >({{
                                            component.subtotalPercentage.toFixed(
                                                0,
                                            )
                                        }}%)</span
                                    >
                                </span>
                            </div>
                        </div>
                    </section>
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
                        <!-- Period heading -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 px-1 pb-1.5"
                        >
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <h3
                                    class="text-sm font-bold tracking-tight text-foreground"
                                >
                                    {{ group.term }}
                                </h3>
                            </div>
                        </div>

                        <section
                            v-for="section in group.sections"
                            :key="section.key"
                            data-test="activity-record-section"
                            :data-section="section.name"
                            :data-section-id="section.id ?? 'unassigned'"
                            :data-season="section.seasonName"
                            class="space-y-4 py-2"
                        >
                            <h4
                                class="px-1 text-sm font-semibold text-foreground"
                            >
                                Section: {{ section.name
                                }}<span v-if="section.seasonName">
                                    · {{ section.seasonName }}</span
                                >
                            </h4>
                            <div
                                v-for="component in section.components"
                                :key="component.key"
                                class="space-y-2"
                            >
                                <h5
                                    class="px-1 text-sm font-medium text-muted-foreground"
                                >
                                    {{ component.label }}
                                </h5>
                                <!-- Activity Record Table -->
                                <div
                                    class="custom-scrollbar max-w-full overflow-x-auto rounded-lg border border-border/50 focus-visible:outline-2 focus-visible:outline-ring"
                                    tabindex="0"
                                    role="region"
                                    :aria-label="`${group.term} ${section.name} ${section.seasonName} ${component.label} activity records`"
                                    data-lenis-prevent
                                >
                                    <table
                                        data-test="activity-record-table"
                                        :data-section="section.name"
                                        :data-section-id="
                                            section.id ?? 'unassigned'
                                        "
                                        :data-season="section.seasonName"
                                        :data-term="group.term"
                                        :data-component="component.key"
                                        :data-category="component.category"
                                        class="w-full table-fixed border-collapse text-left text-sm"
                                    >
                                        <caption class="sr-only">
                                            {{
                                                group.term
                                            }}
                                            ·
                                            {{
                                                section.name
                                            }}
                                            ·
                                            {{
                                                section.seasonName
                                            }}
                                            ·
                                            {{
                                                component.label
                                            }}
                                        </caption>
                                        <thead
                                            class="bg-muted/40 text-xs text-muted-foreground"
                                        >
                                            <tr>
                                                <th
                                                    scope="col"
                                                    class="px-3 py-2 font-medium"
                                                >
                                                    Activity
                                                </th>
                                                <th
                                                    scope="col"
                                                    class="w-28 px-3 py-2 text-right font-medium sm:w-36"
                                                >
                                                    Score
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-border/50"
                                        >
                                            <tr
                                                v-for="activity in component.activities"
                                                :key="activity.id"
                                            >
                                                <th
                                                    scope="row"
                                                    class="px-3 py-3 text-left align-top font-normal"
                                                >
                                                    <button
                                                        v-if="
                                                            (activity.submitted ||
                                                                activity.state ===
                                                                    'completed') &&
                                                            typeof activity.id ===
                                                                'number'
                                                        "
                                                        type="button"
                                                        :aria-label="
                                                            'Review answers for ' +
                                                            activity.title
                                                        "
                                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-sm text-left font-medium text-foreground underline decoration-border underline-offset-4 hover:decoration-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                        @click="
                                                            emit(
                                                                'review',
                                                                activity.id as number,
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="min-w-0 [overflow-wrap:anywhere] break-words"
                                                            >{{
                                                                activity.title
                                                            }}</span
                                                        >
                                                        <ArrowUpRight
                                                            aria-hidden="true"
                                                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                                        />
                                                    </button>
                                                    <button
                                                        v-else-if="
                                                            !activity.is_missed &&
                                                            !activity.is_pending_review &&
                                                            (activity.state ===
                                                                'open' ||
                                                                activity.state ===
                                                                    'in_progress') &&
                                                            typeof activity.id ===
                                                                'number'
                                                        "
                                                        type="button"
                                                        :aria-label="
                                                            'Open ' +
                                                            activity.title
                                                        "
                                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-sm text-left font-medium text-foreground underline decoration-border underline-offset-4 hover:decoration-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                        @click="
                                                            emit(
                                                                'openExam',
                                                                activity.id as number,
                                                            )
                                                        "
                                                    >
                                                        <span
                                                            class="min-w-0 [overflow-wrap:anywhere] break-words"
                                                            >{{
                                                                activity.title
                                                            }}</span
                                                        >
                                                        <ArrowUpRight
                                                            aria-hidden="true"
                                                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                                                        />
                                                    </button>
                                                    <span
                                                        v-else
                                                        class="block font-medium [overflow-wrap:anywhere] break-words text-foreground"
                                                        >{{
                                                            activity.title
                                                        }}</span
                                                    >
                                                    <p
                                                        v-if="
                                                            activity.is_pending_review
                                                        "
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        Pending Review
                                                    </p>
                                                    <p
                                                        v-else-if="
                                                            activity.is_missed ||
                                                            (activity.state ===
                                                                'closed' &&
                                                                !activity.submitted)
                                                        "
                                                        class="mt-1 text-xs text-destructive"
                                                    >
                                                        Missed
                                                    </p>
                                                </th>
                                                <td
                                                    data-test="activity-record-cell"
                                                    :aria-label="activity.title"
                                                    class="px-3 py-3 text-right align-top font-medium whitespace-nowrap text-foreground tabular-nums"
                                                >
                                                    {{
                                                        activityScoreFraction(
                                                            activity,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot
                                            class="border-t border-border/60 bg-muted/30"
                                        >
                                            <tr>
                                                <th
                                                    scope="row"
                                                    data-test="activity-record-total-th"
                                                    class="px-3 py-3 font-semibold text-foreground"
                                                >
                                                    Total
                                                </th>
                                                <td
                                                    data-test="activity-record-total-cell"
                                                    class="px-3 py-3 text-right font-semibold whitespace-nowrap text-foreground tabular-nums"
                                                >
                                                    {{
                                                        component.subtotalScore.toFixed(
                                                            0,
                                                        )
                                                    }}
                                                    /
                                                    {{
                                                        component.subtotalMax.toFixed(
                                                            0,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </section>
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
