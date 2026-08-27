import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (path: string) => readFileSync(join(process.cwd(), path), 'utf8');

describe('page-specific mobile compositions', () => {
    it('uses a purpose-built dashboard tree with native card sections', () => {
        const page = read(
            'resources/js/components/dashboard/MobileDashboard.vue',
        );

        expect(page).toContain('mobile-dashboard-greeting');
        expect(page).toContain('mobile-dashboard-announcement');
        expect(page).toContain('mobile-dashboard-today');
        expect(page).toContain('mobile-dashboard-reward-grid');
        expect(page).toContain('mobile-dashboard-leaderboard');
        expect(page).not.toMatch(/<DashboardHero\b/);
        expect(page).not.toMatch(/<TodayStrip\b/);
    });

    it('adds dedicated mobile queues for the primary student pages', () => {
        const pages = [
            [
                'resources/js/pages/Assignments.vue',
                'mobile-assignment-mobile-list',
            ],
            ['resources/js/pages/Calendar.vue', 'mobile-calendar-agenda'],
            ['resources/js/pages/Exam.vue', 'mobile-exams-queue'],
            ['resources/js/pages/Grades.vue', 'mobile-grades-intro'],
            ['resources/js/pages/Courses/Index.vue', 'mobile-course-catalog'],
            ['resources/js/pages/Leaderboard.vue', 'mobile-leaderboard-intro'],
        ];

        for (const [path, marker] of pages) {
            const source = read(path);
            expect(source, path).toContain(marker);
        }

        expect(read('resources/js/pages/Calendar.vue')).toContain(
            'calendar-desktop-only grid',
        );
        expect(read('resources/js/pages/Grades.vue')).toContain(
            'grades-desktop-overview',
        );
    });

    it('does not render a duplicate generic mobile header on custom pages', () => {
        const customPages = [
            'resources/js/pages/Calendar.vue',
            'resources/js/pages/Assignments.vue',
            'resources/js/pages/Grades.vue',
            'resources/js/pages/Courses/Index.vue',
            'resources/js/pages/Exam.vue',
            'resources/js/pages/Activities/Index.vue',
            'resources/js/pages/Leaderboard.vue',
        ];

        for (const path of customPages) {
            expect(read(path), path).not.toContain('MobilePageHeader');
        }
    });

    it('keeps the shared mobile shell and desktop navigation contracts', () => {
        const header = read('resources/js/components/AppSidebarHeader.vue');
        const navigation = read('resources/js/components/MobileNav.vue');

        expect(header).toContain('mobile-shell-header');
        expect(navigation).toContain('mobile-native-tabbar-surface');
        expect(navigation).toContain(
            ':aria-current="activeIndex === index ? \'page\' : undefined"',
        );
    });
});
