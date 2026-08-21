import { describe, expect, it } from 'vitest';
import {
    addMonths,
    dayDiff,
    eventMeta,
    formatDayLabel,
    formatMonthLabel,
    formatShortDate,
    isEventDone,
    parseKey,
    relativeBadge,
    toKey,
} from '@/lib/calendar';
import type { CalendarEvent } from '@/types/calendar';

describe('calendar date keys', () => {
    it('round-trips keys through UTC without timezone drift', () => {
        for (const key of ['2026-01-01', '2026-02-28', '2026-08-31', '2026-12-31']) {
            expect(toKey(parseKey(key))).toBe(key);
        }
    });

    it('shifts months across year and short-month boundaries', () => {
        expect(addMonths('2026-01-15', -1)).toBe('2025-12-01');
        expect(addMonths('2026-12-15', 1)).toBe('2027-01-01');
        expect(addMonths('2026-01-15', 11)).toBe('2026-12-01');
        expect(addMonths('2026-07-15', 0)).toBe('2026-07-01');
    });

    it('computes whole-day differences from today', () => {
        expect(dayDiff('2026-08-21', '2026-08-21')).toBe(0);
        expect(dayDiff('2026-08-22', '2026-08-21')).toBe(1);
        expect(dayDiff('2026-08-19', '2026-08-21')).toBe(-2);
        // Month boundary: Aug 31 → Sep 1 is one day, not zero/…
        expect(dayDiff('2026-09-01', '2026-08-31')).toBe(1);
    });

    it('formats labels in a fixed timezone so SSR and client agree', () => {
        expect(formatDayLabel('2026-08-21')).toBe('Fri, Aug 21');
        expect(formatShortDate('2026-08-01')).toBe('Aug 1');
        expect(formatMonthLabel('2026-08-15')).toBe('August 2026');
    });
});

describe('calendar badges', () => {
    const todayKey = '2026-08-21';

    it('labels today, tomorrow, late, and future days', () => {
        expect(relativeBadge(todayKey, todayKey).label).toBe('Today');
        expect(relativeBadge('2026-08-22', todayKey).label).toBe('Tomorrow');
        expect(relativeBadge('2026-08-19', todayKey).label).toBe('2d late');
        expect(relativeBadge('2026-08-24', todayKey).label).toBe('In 3 days');
    });
});

describe('calendar event helpers', () => {
    const exam: CalendarEvent = {
        type: 'exam',
        id: 1,
        title: 'Midterm',
        dateKey: '2026-08-24',
        sectionName: 'Math 7-A',
        durationMinutes: 45,
        href: '/exams/1',
    };

    const submittedAssignment: CalendarEvent = {
        type: 'assignment',
        id: 2,
        title: 'Lab Report',
        dateKey: '2026-08-23',
        courseName: null,
        submitted: true,
        isOverdue: false,
        href: '/assignments',
    };

    it('builds exam meta from section and duration', () => {
        expect(eventMeta(exam)).toBe('Math 7-A · 45 min');
        expect(eventMeta({ ...exam, sectionName: null, durationMinutes: undefined })).toBe('');
    });

    it('falls back to a generic label for assignments without a course', () => {
        expect(eventMeta(submittedAssignment)).toBe('Assignment');
    });

    it('treats submitted assignments and completed exams as done', () => {
        expect(isEventDone(submittedAssignment)).toBe(true);
        expect(isEventDone({ ...exam, isCompleted: true })).toBe(true);
        expect(isEventDone(exam)).toBe(false);
    });
});
