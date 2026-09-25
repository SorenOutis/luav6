import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import chatSource from '@/pages/Chats.vue?raw';

describe('chat welcome styling', () => {
    it('uses geometric persona for both streaming and completed assistant replies', () => {
        expect(chatSource).toMatch(
            /<ChatAiOrb\s+v-else\s+size="status"\s+:state="\s*msg\.typing\s*&&\s*isLoading\s*\?\s*'thinking'\s*:\s*'idle'\s*"/,
        );
        expect(chatSource).not.toContain('<Bot v-else');
        expect(chatSource).not.toContain('v-else-if="branding.logoUrl"');
    });
    it('lets mobile welcome grow and scroll without centering overflow above header', () => {
        const classes = chatSource
            .match(/data-testid="chat-welcome"\s+class="([^"]+)"/)?.[1]
            ?.split(/\s+/);
        expect(classes).toContain('min-h-full');
        expect(classes).not.toContain('h-full');
        expect(classes).not.toContain('justify-center');
        expect(chatSource).toContain('max-sm:h-20 max-sm:w-20');
        expect(chatSource).toContain('grid grid-cols-2 gap-2');
    });
    it('removes the inner textarea ring and offset while preserving outer focus feedback', () => {
        const classes = chatSource.match(
            /ref="welcomeInputRef"[\s\S]*?class="([^"]+)"/,
        )?.[1];
        expect(classes).toBeDefined();
        const wrapper = mount(Textarea, { props: { class: classes } });
        expect(wrapper.classes()).toContain('focus-visible:ring-0');
        expect(wrapper.classes()).toContain('focus:outline-none!');
        expect(wrapper.classes()).toContain('text-base');
        expect(wrapper.classes()).toContain('focus-visible:ring-offset-0');
        expect(wrapper.classes()).not.toContain('focus-visible:ring-offset-2');
        expect(chatSource).toContain('focus-within:ring-1');
    });

    it('does not render an ambient gradient wash behind the welcome view', () => {
        expect(chatSource).not.toContain('radial-gradient');
        expect(chatSource).not.toContain('bg-gradient-');
    });
});
