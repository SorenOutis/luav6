import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SpotlightCard from '@/components/ui/spotlight-card/SpotlightCard.vue';

describe('SpotlightCard touch scrolling', () => {
    it('allows vertical panning so it does not trap scroll on mobile', () => {
        const wrapper = mount(SpotlightCard, {
            props: { customSize: true },
            slots: { default: '<span>card</span>' },
        });

        // `touch-action: none` previously blocked scrolling whenever the card
        // filled the viewport (e.g. leaderboard podium cards on mobile).
        expect(wrapper.element.style.touchAction).toBe('pan-y');
    });
});
