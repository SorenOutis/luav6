import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('mobile navigation', () => {
    it('exposes exactly Home, Exams, Grades, and Chats', () => {
        const source = readFileSync(
            join(process.cwd(), 'resources/js/components/MobileNav.vue'),
            'utf8',
        );

        expect(source).toContain("label: 'Home'");
        expect(source).toContain("label: 'Exams'");
        expect(source).toContain("label: 'Grades'");
        expect(source).toContain("label: 'Chats'");
        expect(source).not.toContain("label: 'More'");
        expect(source).not.toContain("label: 'Courses'");
        expect(source).not.toContain("label: 'Assignments'");
        expect(source).not.toContain("label: 'Games'");
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
