import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import EmptyState from '@/components/dashboard/EmptyState.vue';
import SeasonProgressBand from '@/components/dashboard/SeasonProgressBand.vue';
import StreakCard from '@/components/dashboard/StreakCard.vue';
import TodayStrip from '@/components/dashboard/TodayStrip.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
}));

// Number animation is a visual nicety; tests only care about the final value.
vi.mock('@/composables/useNumberAnimation', () => ({
    useNumberAnimation: (getter: () => number) => getter(),
}));

vi.mock('@/components/dashboard/StreakCalendarModal.vue', () => ({
    default: defineComponent({
        setup() {
            return () => h('div');
        },
    }),
}));

const LinkStub = {
    props: ['href'],
    setup(
        props: { href?: string },
        { slots }: { slots: { default?: () => any } },
    ) {
        return () => h('a', { href: props.href }, slots.default?.());
    },
};

describe('dashboard student shell', () => {
    it('scopes the student design tokens to the dashboard shell', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Dashboard.vue'),
            'utf8',
        );
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );

        expect(page).toContain('dashboard-ui');
        expect(page).not.toContain('SpotlightCard');
        expect(css).toContain('.dashboard-ui');
        expect(css).toContain('system-ui');
        expect(css).toContain('min-height: 44px');
        expect(css).toContain('env(safe-area-inset-left)');
    });

    it('renders a readable hero with 44px actions on mobile', () => {
        const wrapper = mount(DashboardHero, {
            props: {
                userName: 'Alex',
                userStats: {
                    level: 4,
                    totalXP: 400,
                    currentXP: 40,
                    maxXPForLevel: 100,
                    points: 0,
                },
                announcements: [],
                timeBasedGreeting: 'Good Morning',
                smarterStatus: 'A 4-day streak. Keep the momentum going.',
            },
            global: {
                stubs: { Link: LinkStub },
            },
        });

        expect(wrapper.text()).toContain('Good Morning, Alex');
        expect(wrapper.text()).toContain('Join section');
        expect(wrapper.text()).not.toContain('JOIN SECTION');
        expect(wrapper.html()).not.toContain('tracking-[0.2em]');
        expect(wrapper.html()).not.toContain('font-black');

        const join = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Join section'));
        expect(join?.classes()).toContain('dash-btn');
    });

    it('keeps the today strip usable in a 3-column mobile grid', () => {
        const due = new Date(Date.now() + 3_600_000).toISOString();
        const wrapper = mount(TodayStrip, {
            props: {
                dueTodayCount: 2,
                overdueCount: 1,
                upcoming24hCount: 3,
                nextItem: {
                    kind: 'exam',
                    title: 'Midterm',
                    dueAt: due,
                    href: '/exams/1',
                    meta: '2/3 parts',
                },
            },
            global: {
                stubs: { Link: LinkStub },
            },
        });

        expect(wrapper.text()).toContain('Today');
        expect(wrapper.text()).toContain('Overdue');
        expect(wrapper.text()).toContain('Next 24h');
        expect(wrapper.text()).toContain('Next exam');
        expect(wrapper.text()).toContain('Midterm');
        expect(wrapper.html()).toContain('grid-cols-3');
        expect(wrapper.html()).toContain('min-h-[92px]');
        expect(wrapper.html()).toContain('min-h-14');
        expect(wrapper.html()).not.toContain('tracking-[0.2em]');
        expect(wrapper.html()).not.toContain('text-[8px]');
        expect(wrapper.html()).not.toContain('text-[9px]');
    });

    it('uses sentence-case empty states with a large tap target', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'Nothing due',
                message: 'Enjoy the extra time.',
                ctaLabel: 'Browse exams',
                ctaHref: '/exams',
            },
            global: {
                stubs: { Link: LinkStub },
            },
        });

        expect(wrapper.get('h4').text()).toBe('Nothing due');
        expect(wrapper.get('h4').classes()).toContain('font-semibold');
        expect(wrapper.get('h4').classes()).not.toContain('uppercase');
        expect(wrapper.get('a').classes()).toContain('dash-btn');
    });

    it('keeps the streak card compact (no 7-day strip)', () => {
        const wrapper = mount(StreakCard, {
            props: {
                currentStreak: 5,
                longestStreak: 30,
                loginDates: [],
            },
        });

        const text = wrapper.text();

        expect(text).toContain('Streak');
        expect(text).toContain('5');
        expect(text).toContain('Best:');
        expect(text).toContain('30 days');
        // Compact card: one metric row, no leftover 7-day weekday strip.
        expect(wrapper.findAll('.dash-metric')).toHaveLength(1);
        expect(text).not.toContain('Mo');
        expect(text).not.toContain('Tu');
    });

    it('shows only days left and the date on the season card', () => {
        const end = new Date(Date.now() + 10 * 86_400_000).toISOString();
        const start = new Date(Date.now() - 5 * 86_400_000).toISOString();

        const wrapper = mount(SeasonProgressBand, {
            props: {
                name: 'Season 1',
                startDate: start,
                endDate: end,
            },
        });

        const text = wrapper.text();

        expect(text).toContain('Season');
        expect(text).toContain('Season 1');
        expect(text).toContain('days left');
        // Roughly ten days remain; the countdown is the card's only metric.
        expect(text).toContain('10');
        expect(text).toContain('–');
        // XP progress / pacing clutter was removed.
        expect(text).not.toContain('XP');
        expect(text).not.toContain('pace');
    });
});
