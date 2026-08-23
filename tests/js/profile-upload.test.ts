import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

/**
 * End-to-end cover for the "profile picture not saving" bug.
 *
 * The page mounts for real (only Inertia and the layouts are stubbed) so these
 * assertions describe the attributes the browser actually submits.
 */

const slotProps = {
    errors: {},
    processing: false,
    progress: null,
    recentlySuccessful: false,
};

vi.mock('@inertiajs/vue3', () => ({
    Form: defineComponent({
        props: { action: String, method: String },
        setup:
            (props, { slots }) =>
            () =>
                h(
                    'form',
                    { action: props.action, method: props.method },
                    slots.default?.(slotProps),
                ),
    }),
    Head: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    router: { patch: vi.fn(), reload: vi.fn() },
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'Ana Cruz',
                    email: 'ana@example.com',
                    first_name: 'Ana',
                    last_name: 'Cruz',
                    avatar: null,
                    cover_photo: null,
                    bio: '',
                },
            },
        },
    }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}));
vi.mock('@/layouts/settings/Layout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}));
vi.mock('@/components/DeleteUser.vue', () => ({
    default: defineComponent({ setup: () => () => h('div') }),
}));
vi.mock('@/components/SectionSelectionModal.vue', () => ({
    default: defineComponent({ setup: () => () => h('div') }),
}));

import Profile from '@/pages/settings/Profile.vue';

const gallery = [
    {
        path: 'avatars/avatar-01.svg',
        name: 'Avatar 01',
        url: '/storage/avatars/avatar-01.svg',
    },
    {
        path: 'avatars/avatar-02.svg',
        name: 'Avatar 02',
        url: '/storage/avatars/avatar-02.svg',
    },
];

const mountPage = (overrides: Record<string, unknown> = {}) =>
    mount(Profile, {
        props: {
            mustVerifyEmail: false,
            userSections: [],
            avatarGallery: gallery,
            ...overrides,
        },
    });

describe('profile settings upload form', () => {
    it('submits as POST with _method=PATCH so PHP parses the multipart body', () => {
        // A literal PATCH is why avatars silently failed to save: PHP only
        // populates $_FILES for POST requests.
        const form = mountPage().find('form');

        expect(form.attributes('method')).toBe('post');
        expect(form.attributes('action')).toBe(
            '/settings/profile?_method=PATCH',
        );
    });

    it('sends the file input under the name the controller reads', () => {
        const input = mountPage().find('input[name="avatar"]');

        expect(input.exists()).toBe(true);
        expect(input.attributes('type')).toBe('file');
    });

    it('only offers formats the server accepts', () => {
        const accept = mountPage()
            .find('input[name="avatar"]')
            .attributes('accept');

        expect(accept).toContain('image/jpeg');
        expect(accept).toContain('image/png');
        // A bare image/* would let a phone hand over HEIC, which the backend
        // rejects only after the whole upload has been sent.
        expect(accept).not.toBe('image/*');
    });

    it('offers a curated avatar choice alongside custom upload', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Choose Avatar');
        expect(wrapper.find('input[name="avatar_preset"]').exists()).toBe(true);
        expect(
            wrapper.find('input[name="avatar_preset"]').attributes('value'),
        ).toBe('');
    });

    it('gives touch users an avatar control that is not hover-gated', () => {
        const buttons = mountPage().findAll(
            'button[aria-label="Change profile picture"]',
        );

        expect(buttons.length).toBe(2);

        const mobileBadge = buttons.find((b) =>
            b.classes().includes('sm:hidden'),
        );

        expect(mobileBadge).toBeDefined();
        expect(mobileBadge!.classes()).not.toContain('opacity-0');
    });

    it('keeps the desktop hover overlay off small screens', () => {
        const overlay = mountPage()
            .findAll('button[aria-label="Change profile picture"]')
            .find((b) => b.classes().includes('sm:flex'));

        expect(overlay).toBeDefined();
        expect(overlay!.classes()).toContain('hidden');
    });
});
