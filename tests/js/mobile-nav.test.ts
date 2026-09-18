import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('mobile navigation', () => {
    it('exposes exactly Home, Exams, Calendar, Assignments, Library, Grades, and Chats', () => {
        const source = readFileSync(
            join(process.cwd(), 'resources/js/components/MobileNav.vue'),
            'utf8',
        );

        const labels = [...source.matchAll(/label: '([^']+)'/g)].map(
            (match) => match[1],
        );

        expect(labels).toEqual([
            'Home',
            'Exams',
            'Calendar',
            'Assignments',
            'Library',
            'Grades',
            'Chats',
        ]);
        expect(source).not.toContain("label: 'More'");
        expect(source).not.toContain("label: 'Courses'");
        expect(source).not.toContain("label: 'Games'");
    });

    it('keeps long labels inside their slot so items never overlap', () => {
        const source = readFileSync(
            join(process.cwd(), 'resources/js/components/MobileNav.vue'),
            'utf8',
        );

        // Equal slots + a hard truncation guard: no label can paint outside
        // its share of the bar, however many items are enabled.
        expect(source).toContain('flex-1 basis-0');
        expect(source).toContain('truncate');

        // Crowded bars fall back to short labels at a tighter type scale.
        expect(source).toContain('isCompact');
        expect(source).toContain("shortLabel: 'Agenda'");
        expect(source).toContain("shortLabel: 'Tasks'");

        // Full names stay available to screen readers and tooltips.
        expect(source).toContain(':aria-label="item.label"');
    });

    it('keeps the chats workspace compact on small screens', () => {
        const source = readFileSync(
            join(process.cwd(), 'resources/js/pages/Chats.vue'),
            'utf8',
        );

        expect(source).toContain('100dvh');
        expect(source).toContain('isMobileHistoryOpen');
        expect(source).toContain('min-h-0');
        expect(source).not.toContain('min-h-[480px]');
    });
});
