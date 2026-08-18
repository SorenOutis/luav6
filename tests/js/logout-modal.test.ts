import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount, shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import LogoutConfirmationModal from '@/components/LogoutConfirmationModal.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const inertiaRouter = vi.hoisted(() => ({
    delete: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    router: inertiaRouter,
    usePage: () => ({
        props: {
            workspace: {
                available: [],
                isInspecting: false,
            },
        },
    }),
}));

vi.mock('@/routes', () => ({
    logout: () => '/logout',
}));

const PassthroughStub = defineComponent({
    setup:
        (_, { slots }) =>
        () =>
            h('div', slots.default?.()),
});

const DropdownMenuItemStub = defineComponent({
    inheritAttrs: false,
    emits: ['select'],
    setup(_, { attrs, emit, slots }) {
        return () =>
            h(
                'button',
                {
                    ...attrs,
                    onClick: () => emit('select'),
                },
                slots.default?.(),
            );
    },
});

const ResponsiveModalStub = defineComponent({
    props: {
        open: { type: Boolean, required: true },
    },
    emits: ['close'],
    setup(props, { slots }) {
        return () =>
            props.open
                ? h('section', [slots.default?.(), slots.footer?.()])
                : null;
    },
});

const ButtonStub = defineComponent({
    inheritAttrs: false,
    props: {
        disabled: { type: Boolean, default: false },
    },
    emits: ['click'],
    setup(props, { attrs, emit, slots }) {
        return () =>
            h(
                'button',
                {
                    ...attrs,
                    disabled: props.disabled,
                    onClick: () => emit('click'),
                },
                slots.default?.(),
            );
    },
});

const user = {
    id: 1,
    name: 'Test Student',
    email: 'student@example.com',
    avatar: null,
    is_super_admin: false,
};

describe('logout confirmation modal', () => {
    it('lets the dropdown close and asks its persistent parent to show the modal', async () => {
        const wrapper = shallowMount(UserMenuContent, {
            props: { user: user as never },
            global: {
                stubs: {
                    DropdownMenuGroup: PassthroughStub,
                    DropdownMenuItem: DropdownMenuItemStub,
                    DropdownMenuLabel: PassthroughStub,
                    DropdownMenuSeparator: PassthroughStub,
                    UserInfo: true,
                },
            },
        });

        await wrapper.get('[data-test="logout-button"]').trigger('click');

        expect(wrapper.emitted('logout')).toHaveLength(1);
        expect(wrapper.findComponent(ResponsiveModalStub).exists()).toBe(false);
    });

    it('renders a fixed, clickable bottom sheet on a mobile viewport', async () => {
        inertiaRouter.post.mockReset();
        const previousInnerWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        const wrapper = mount(LogoutConfirmationModal, {
            attachTo: document.body,
            props: { open: true },
        });

        await nextTick();
        await nextTick();

        const sheet = document.body.querySelector<HTMLElement>(
            '[data-test="mobile-bottom-sheet"]',
        );
        const confirmButton = document.body.querySelector<HTMLButtonElement>(
            '[data-test="logout-confirm-button"]',
        );

        expect(sheet).not.toBeNull();
        expect(sheet?.classList.contains('fixed')).toBe(true);
        expect(sheet?.classList.contains('z-[110]')).toBe(true);
        expect(confirmButton).not.toBeNull();

        confirmButton?.click();
        await nextTick();

        expect(inertiaRouter.post).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: previousInnerWidth,
        });
    });

    it('submits logout once and keeps the action disabled while it is running', async () => {
        inertiaRouter.post.mockReset();
        sessionStorage.clear();

        const wrapper = shallowMount(LogoutConfirmationModal, {
            props: { open: true },
            global: {
                stubs: {
                    Button: ButtonStub,
                    ResponsiveModal: ResponsiveModalStub,
                },
            },
        });

        const confirmButton = wrapper.get(
            '[data-test="logout-confirm-button"]',
        );
        await confirmButton.trigger('click');
        await confirmButton.trigger('click');

        expect(sessionStorage.getItem('logged_out')).toBe('true');
        expect(inertiaRouter.post).toHaveBeenCalledTimes(1);
        expect(inertiaRouter.post).toHaveBeenCalledWith(
            '/logout',
            {},
            expect.objectContaining({ onFinish: expect.any(Function) }),
        );
        expect(confirmButton.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Logging out…');

        const options = inertiaRouter.post.mock.calls[0][2] as {
            onFinish: () => void;
        };
        options.onFinish();
        await nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('mounts the modal outside every dropdown content portal', () => {
        for (const component of [
            'NavUser.vue',
            'AppHeader.vue',
            'AppSidebarHeader.vue',
        ]) {
            const source = readFileSync(
                join(process.cwd(), 'resources/js/components', component),
                'utf8',
            );

            expect(source.indexOf('<LogoutConfirmationModal')).toBeGreaterThan(
                source.lastIndexOf('</DropdownMenuContent>'),
            );
        }
    });
});
