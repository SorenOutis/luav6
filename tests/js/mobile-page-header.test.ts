import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MobilePageHeader from '@/components/mobile/MobilePageHeader.vue';

describe('MobilePageHeader', () => {
    it('renders the generated-reference mobile hierarchy and remains hidden at desktop', () => {
        const wrapper = mount(MobilePageHeader, {
            props: {
                title: 'Grades',
                subtitle: 'Your academic performance across enrolled subjects.',
                eyebrow: 'Track your progress',
            },
        });

        expect(wrapper.classes()).toContain('mobile-page-header');
        expect(wrapper.classes()).toContain('md:hidden');
        expect(wrapper.find('h1').text()).toBe('Grades');
        expect(wrapper.find('.mobile-page-header__eyebrow').text()).toBe(
            'Track your progress',
        );
        expect(wrapper.find('.mobile-page-header__subtitle').text()).toContain(
            'academic performance',
        );

        wrapper.unmount();
    });
});
