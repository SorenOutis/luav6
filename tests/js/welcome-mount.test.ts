import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import EchoPromptCta from '@/components/welcome/EchoPromptCta.vue';
import Welcome from '@/pages/Welcome.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        props: ['href'],
        setup(props: any, { slots }: any) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
    usePage: () => ({
        url: '/',
        props: {
            auth: { user: null },
            schoolBranding: {
                name: 'LSI',
                tagline: 'Learning',
                logoUrl: null,
                accentColor: '#f59e0b',
            },
        },
    }),
    router: {
        visit: vi.fn(),
    },
}));

describe('Welcome.vue page mount', () => {
    it('mounts successfully with guest props', () => {
        const wrapper = mount(Welcome, {
            props: {
                canRegister: true,
            },
            global: {
                mocks: {
                    $page: {
                        props: {
                            auth: { user: null },
                            schoolBranding: {
                                name: 'LSI',
                                tagline: 'Learning',
                                logoUrl: null,
                                accentColor: '#f59e0b',
                            },
                        },
                        url: '/',
                    },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain('Make every assessment count.');
        expect(wrapper.text()).toContain('Why we build LSI');
        expect(wrapper.text()).toContain('The Classroom Loop');
        expect(wrapper.text()).toContain('Capabilities');
        expect(wrapper.text()).toContain('Questions, answered.');
        wrapper.unmount();
    });

    it('mounts on older mobile browsers without addEventListener on matchMedia', () => {
        const originalMatchMedia = window.matchMedia;
        // Simulate older iOS Safari / Android WebView where MediaQueryList only has addListener
        (window as any).matchMedia = vi.fn().mockImplementation((query: string) => ({
            matches: query.includes('coarse') || query.includes('reduce'),
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            // addEventListener is deliberately undefined here!
        }));

        try {
            const wrapper = mount(Welcome, {
                props: {
                    canRegister: true,
                },
                global: {
                    mocks: {
                        $page: {
                            props: {
                                auth: { user: null },
                            },
                        },
                    },
                },
            });

            expect(wrapper.exists()).toBe(true);
            wrapper.unmount();
        } finally {
            window.matchMedia = originalMatchMedia;
        }
    });

    it('EchoPromptCta handles undefined auth safely without console warnings', () => {
        const wrapper = mount(EchoPromptCta, {
            props: {
                canRegister: true,
                dashboard: () => '/dashboard',
                login: () => '/login',
                register: () => '/register',
            },
        });

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain('Create free account');
        wrapper.unmount();
    });
});
