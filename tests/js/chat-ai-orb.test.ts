import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChatAiOrb from '@/components/ChatAiOrb.vue';

describe('ChatAiOrb', () => {
    it('renders harmonic ripple waves, core sphere, and 5 echo waveform bars', () => {
        const wrapper = mount(ChatAiOrb);

        expect(wrapper.find('.ambient-bloom').exists()).toBe(true);
        expect(wrapper.find('.orb-sphere').exists()).toBe(true);
        expect(wrapper.findAll('.harmonic-ripple')).toHaveLength(3);
        expect(wrapper.findAll('.echo-bar')).toHaveLength(5);
        expect(wrapper.classes()).toContain('h-24');
    });

    it('adapts container dimensions when small or large size is requested', () => {
        const small = mount(ChatAiOrb, { props: { size: 'sm' } });
        expect(small.classes()).toContain('h-20');

        const large = mount(ChatAiOrb, { props: { size: 'lg' } });
        expect(large.classes()).toContain('h-32');
    });
});
