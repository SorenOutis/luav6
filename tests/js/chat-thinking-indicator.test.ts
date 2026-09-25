import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChatThinkingIndicator from '@/components/ChatThinkingIndicator.vue';

describe('ChatThinkingIndicator', () => {
    it('announces status while rendering label and three animated dots', () => {
        const wrapper = mount(ChatThinkingIndicator);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-label')).toBe('Thinking…');
        expect(wrapper.text()).toContain('Thinking');

        const dots = wrapper.findAll('.thinking-dot');
        expect(dots).toHaveLength(3);
    });

    it('supports a custom label', () => {
        const wrapper = mount(ChatThinkingIndicator, {
            props: { label: 'Checking' },
        });

        expect(wrapper.attributes('aria-label')).toBe('Checking…');
        expect(wrapper.text()).toContain('Checking');
        expect(wrapper.findAll('.thinking-dot')).toHaveLength(3);
    });
});
