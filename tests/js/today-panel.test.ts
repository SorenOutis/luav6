import { mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import TodayPanel from '@/components/dashboard/TodayPanel.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
}));

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('TodayPanel tabs on mobile', () => {
    it('renders all four filter tabs in a 2-column grid that fits', () => {
        const wrapper = mount(TodayPanel, { props: { tasks: [] } });

        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs).toHaveLength(4);
        expect(wrapper.text()).toContain('Due today');
        expect(wrapper.text()).toContain('Overdue');
        expect(wrapper.text()).toContain('Next 24h');
        expect(wrapper.text()).toContain('Done');

        // 2×2 grid on phones (no horizontal scrolling to reach Done),
        // flex row again on sm+.
        const tablist = wrapper.find('[role="tablist"]');
        expect(tablist.classes()).toContain('grid-cols-2');
        expect(tablist.classes()).toContain('sm:flex');

        // 44px tap targets that can shrink inside their cells.
        for (const tab of tabs) {
            expect(tab.classes()).toContain('min-h-11');
            expect(tab.classes()).toContain('min-w-0');
        }

        wrapper.unmount();
    });
});
