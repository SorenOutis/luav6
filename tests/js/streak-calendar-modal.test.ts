import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import StreakCalendarModal from '@/components/dashboard/StreakCalendarModal.vue';
import FoxCompanion from '@/components/FoxCompanion.vue';

const ResponsiveModalStub = defineComponent({
    setup(_, { slots }) {
        return () => h('div', slots.default?.());
    },
});

describe('streak calendar modal', () => {
    it('shows Echo as a calendar companion without a message card', () => {
        const wrapper = mount(StreakCalendarModal, {
            props: {
                open: true,
                loginDates: [],
                currentStreak: 2,
                longestStreak: 6,
            },
            global: {
                stubs: {
                    ResponsiveModal: ResponsiveModalStub,
                },
            },
        });

        const fox = wrapper.findComponent(FoxCompanion);

        expect(fox.exists()).toBe(true);
        expect(fox.props('mascot')).toBe('calendar');
        expect(fox.props('showMessage')).toBe(false);
        expect(wrapper.text()).toContain("Echo's tip:");
        expect(wrapper.text()).toContain(
            'A little progress today keeps your streak alive.',
        );
    });
});
