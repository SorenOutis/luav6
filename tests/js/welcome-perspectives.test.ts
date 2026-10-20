import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RolePillSwitcher from '@/components/welcome/RolePillSwitcher.vue';

describe('role pill switcher', () => {
    it('shows three role panels with only the active one visible', () => {
        const wrapper = mount(RolePillSwitcher);

        const panels = wrapper.findAll('.rp-panel');
        expect(panels).toHaveLength(3);
        expect(panels[0].classes()).toContain('is-active');
        expect(panels[1].classes()).not.toContain('is-active');
        expect(panels[1].attributes('aria-hidden')).toBe('true');
        expect(wrapper.find('.rp-thumb').attributes('style')).toContain(
            'translateX(0%)',
        );

        wrapper.unmount();
    });

    it('slides the thumb and crossfades panels when switching roles', async () => {
        const wrapper = mount(RolePillSwitcher);
        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs).toHaveLength(3);

        await tabs[2].trigger('click');

        const panels = wrapper.findAll('.rp-panel');
        expect(panels[2].classes()).toContain('is-active');
        expect(panels[0].classes()).not.toContain('is-active');
        expect(panels[0].attributes('aria-hidden')).toBe('true');
        expect(wrapper.find('.rp-thumb').attributes('style')).toContain(
            'translateX(200%)',
        );

        wrapper.unmount();
    });

    it('uses authentic student persona Joshua Reyes and keeps Maria Santos as teacher', () => {
        const wrapper = mount(RolePillSwitcher);

        expect(wrapper.text()).toContain('Joshua Reyes');
        expect(wrapper.text()).not.toContain('Student: Maria Santos');

        wrapper.unmount();
    });

    it('allows 1-click teacher approval toggle interaction', async () => {
        const wrapper = mount(RolePillSwitcher);

        // Find the approve button
        const approveBtn = wrapper
            .findAll('button')
            .find(
                (btn) =>
                    btn.text().includes('Approve & Release') ||
                    btn.text().includes('Approved & Released'),
            );

        expect(approveBtn).toBeDefined();

        // Click to toggle
        await approveBtn?.trigger('click');
        expect(wrapper.text()).toContain('Approved & Released ✓');
        expect(wrapper.text()).toContain('✓ Approved');

        // Click again to reset
        await approveBtn?.trigger('click');
        expect(wrapper.text()).not.toContain('Approved & Released ✓');

        wrapper.unmount();
    });

    it('parks on static approved state when motion is reduced', () => {
        const wrapper = mount(RolePillSwitcher, {
            props: { prefersReducedMotion: true },
        });

        expect(wrapper.text()).toContain('Approved & Released ✓');
        expect(wrapper.text()).toContain('✓ Approved');

        wrapper.unmount();
    });
});
