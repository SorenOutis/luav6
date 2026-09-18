<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ClipboardList, GraduationCap } from 'lucide-vue-next';
import {
    eventMeta,
    formatDayLabel,
    isEventDone,
    relativeBadge,
} from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';

type Props = {
    event: CalendarEvent;
    /** Y-m-d key for "today" — drives the relative badge. */
    todayKey: string;
    /** Show the date line (list contexts; hidden inside the day sheet). */
    showDate?: boolean;
};

withDefaults(defineProps<Props>(), {
    showDate: true,
});
</script>

<template>
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
            <GraduationCap v-if="event.type === 'exam'" class="h-4 w-4" />
            <ClipboardList v-else class="h-4 w-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span
                class="flex items-center gap-1.5 text-sm font-semibold"
                :class="{ 'line-through opacity-70': isEventDone(event) }"
            >
                <span class="truncate">{{ event.title }}</span>
                <Check
                    v-if="isEventDone(event)"
                    class="h-3.5 w-3.5 shrink-0 text-emerald-500"
                />
            </span>
            <span class="mt-0.5 block truncate text-xs text-muted-foreground">
                {{ eventMeta(event) }}
            </span>
            <span
                v-if="showDate"
                class="mt-1.5 flex flex-wrap items-center gap-1.5"
            >
                <span class="text-xs font-medium text-muted-foreground">
                    {{ formatDayLabel(event.dateKey) }}
                </span>
                <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                    :class="relativeBadge(event.dateKey, todayKey).class"
                >
                    {{ relativeBadge(event.dateKey, todayKey).label }}
                </span>
            </span>
        </span>
    </Link>
</template>
