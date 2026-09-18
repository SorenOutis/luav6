/**
 * Shapes shared between the Calendar page and its presentational pieces.
 *
 * Produced by App\Services\CalendarEventService — dates are `Y-m-d` keys in
 * the app timezone so bucketing matches the server-formatted dates shown on
 * the dashboard.
 */
export type CalendarEvent = {
    type: 'exam' | 'assignment';
    id: number;
    title: string;
    dateKey: string;
    sectionName?: string | null;
    courseName?: string | null;
    durationMinutes?: number;
    status?: string;
    startsAtISO?: string | null;
    endsAtISO?: string | null;
    isOpenNow?: boolean;
    isUpcoming?: boolean;
    hasEnded?: boolean;
    submitted?: boolean;
    isOverdue?: boolean;
    isCompleted?: boolean;
    href: string;
};

export type CalendarSeason = {
    id: number;
    name: string;
    startDateKey: string;
    endDateKey: string | null;
    isActive: boolean;
};
