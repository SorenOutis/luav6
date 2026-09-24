import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

describe('ChatAiOrb', () => {
    it('renders the core layers and default medium dimensions', () => {
        const wrapper = mount(ChatAiOrb);

        expect(wrapper.find('.ai-orb-glow').exists()).toBe(true);
        expect(wrapper.find('.ai-orb-core').exists()).toBe(true);
        expect(wrapper.find('.ai-orb-ring-1').exists()).toBe(true);
        expect(wrapper.find('.ai-orb-ring-2').exists()).toBe(true);
        expect(wrapper.classes()).toContain('h-20');
    });

    it('adapts container dimensions when small or large size is requested', () => {
        const small = mount(ChatAiOrb, { props: { size: 'sm' } });
        expect(small.classes()).toContain('h-16');

        const large = mount(ChatAiOrb, { props: { size: 'lg' } });
        expect(large.classes()).toContain('h-28');
    });
});
