import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
}));

const baseProps = {
    userName: 'Alex',
    profileHref: '/u/1',
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
};

describe('dashboard hero mobile layout', () => {
    it('renders the avatar before the greeting (app-style header)', () => {
        const wrapper = mount(DashboardHero, { props: baseProps });

        const avatar = wrapper.get('a[href="/u/1"]');
        const greeting = wrapper.get('h1');

        const position = avatar.element.compareDocumentPosition(
            greeting.element,
        );
        // DOCUMENT_POSITION_FOLLOWING means the greeting sits after the avatar
        // in DOM order, so the profile picture is on the left and the greeting
        // is on the right in every viewport.
        expect(position & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(greeting.text()).toContain('Good Morning, Alex');
    });

    it('keeps a mobile-only trailing refresh action plus the desktop one', () => {
        const wrapper = mount(DashboardHero, { props: baseProps });

        const refreshButtons = wrapper.findAll(
            'button[aria-label="Refresh dashboard"]',
        );
        expect(refreshButtons).toHaveLength(2);

        const mobile = refreshButtons.find((b) =>
            b.classes().includes('lg:hidden'),
        );
        const desktopRow = wrapper.find('.lg\\:flex button');
        expect(mobile).toBeTruthy();
        expect(desktopRow.exists()).toBe(true);
    });

    it('renders the join-section CTA as a right-aligned button in the greeting row', () => {
        const wrapper = mount(DashboardHero, { props: baseProps });

        const join = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Join section'));

        expect(join?.exists()).toBe(true);
        expect(join?.classes()).toContain('dash-btn');
        expect(join?.classes()).toContain('shrink-0');
        // It lives on the right side of the greeting, no longer a full-width row.
        expect(join?.classes()).not.toContain('w-full');
    });
});
