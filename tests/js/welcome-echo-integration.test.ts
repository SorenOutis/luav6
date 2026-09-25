import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AssessmentArtifact from '@/components/welcome/AssessmentArtifact.vue';
import EchoInteractiveDemo from '@/components/welcome/EchoInteractiveDemo.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import welcomeSource from '@/pages/Welcome.vue?raw';

describe('Welcome page Echo AI integration', () => {
    beforeEach(() => {
        vi.stubGlobal('matchMedia', () => ({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders animated Echo pill in WelcomeHero', () => {
        const wrapper = mount(WelcomeHero, {
            props: {
                canRegister: true,
                auth: { user: null },
                dashboard: () => '/dashboard',
                login: () => '/login',
                register: () => '/register',
            },
            global: {
                stubs: {
                    Link: { template: '<a><slot /></a>' },
                    Motion: { template: '<div><slot /></div>' },
                    FoxCompanion: { template: '<div data-fox />' },
                    AssessmentArtifact: { template: '<div data-artifact />' },
                },
            },
        });

        expect(wrapper.find('a[href="#interactive-echo"]').exists()).toBe(true);
        expect(wrapper.text()).toContain(
            'Meet Echo · Intelligent Study Companion',
        );
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);
    });

    it('renders mini Echo persona in AssessmentArtifact AI feedback header', () => {
        const wrapper = mount(AssessmentArtifact);
        expect(wrapper.text()).toContain('Echo AI Drafted Feedback');
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);
    });

    it('simulates thinking and speaking transitions in EchoInteractiveDemo', async () => {
        vi.useFakeTimers();
        const wrapper = mount(EchoInteractiveDemo, {
            global: {
                stubs: {
                    Link: { template: '<a><slot /></a>' },
                },
            },
        });

        expect(wrapper.find('.wolf-persona').exists()).toBe(true);
        const buttons = wrapper.findAll('button');
        expect(buttons.length).toBeGreaterThanOrEqual(3);

        // Click second prompt
        await buttons[1].trigger('click');
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'thinking',
        );
        expect(wrapper.text()).toContain('Synthesizing rubric…');

        // Advance past thinking phase into speaking
        await vi.advanceTimersByTimeAsync(700);
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'speaking',
        );

        // Advance past typing animation to idle completion
        await vi.advanceTimersByTimeAsync(3000);
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'welcome',
        );
        expect(wrapper.text()).toContain('mastered standard trinomials');

        vi.useRealTimers();
    });

    it('mounts EchoInteractiveDemo inside the Welcome page flow', () => {
        expect(welcomeSource).toContain('import EchoInteractiveDemo');
        expect(welcomeSource).toContain('<EchoInteractiveDemo />');
    });
});
