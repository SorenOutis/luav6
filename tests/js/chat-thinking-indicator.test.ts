import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChatThinkingIndicator from '@/components/ChatThinkingIndicator.vue';

describe('ChatThinkingIndicator', () => {
    it('announces one status while hiding decorative characters', () => {
        const wrapper = mount(ChatThinkingIndicator);

        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-label')).toBe('Thinking…');
        expect(wrapper.text()).toBe('Thinking…');
        expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(9);
    });

    it('staggers the existing pulse animation only when motion is allowed', () => {
        const wrapper = mount(ChatThinkingIndicator);
        const characters = wrapper.findAll('[aria-hidden="true"]');

        characters.forEach((character, index) => {
            expect(character.classes()).toContain('motion-safe:animate-pulse');
            expect(
                (character.element as HTMLElement).style.animationDelay,
            ).toBe(`${index * 80 - 2000}ms`);
        });
    });
});
