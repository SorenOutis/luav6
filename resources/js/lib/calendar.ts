import type { CalendarEvent } from '@/types/calendar';

/**
 * Calendar date helpers — UTC math over server-supplied `Y-m-d` keys.
 *
 * Every bucketing decision runs on these keys instead of parsing ISO
 * timestamps, so SSR and client hydration always agree and the dates match
 * the ones the dashboard formats server-side.
 */

const MS_PER_DAY = 86_400_000;

export const pad = (value: number) => String(value).padStart(2, '0');

export const parseKey = (key: string) => {
    const [year, month, day] = key.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
};

export const toKey = (date: Date) =>
    `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;

export const addMonths = (monthAnchor: string, delta: number) => {
    const date = parseKey(`${monthAnchor.slice(0, 7)}-01`);
    date.setUTCMonth(date.getUTCMonth() + delta);

    return toKey(date);
};

export const dayDiff = (key: string, todayKey: string) =>
    Math.round(
        (parseKey(key).getTime() - parseKey(todayKey).getTime()) / MS_PER_DAY,
    );

export const formatDayLabel = (key: string) =>
    parseKey(key).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

export const formatShortDate = (key: string) =>
    parseKey(key).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

export const formatMonthLabel = (monthAnchor: string) =>
    parseKey(`${monthAnchor}-01`).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });

export const relativeBadge = (key: string, todayKey: string) => {
    const diff = dayDiff(key, todayKey);

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

export const eventMeta = (event: CalendarEvent) => {
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

export const isEventDone = (event: CalendarEvent) =>
    Boolean(event.submitted || event.isCompleted);
