import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import CoverPhotoCropper from '@/components/CoverPhotoCropper.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import PublicProfile from '@/pages/User/PublicProfile.vue';

vi.mock('@inertiajs/vue3', () => {
    const resolveHref = (href: unknown) =>
        typeof href === 'string' ? href : ((href as { url?: string })?.url ?? '#');

    return {
        Link: defineComponent({
            props: { href: { type: [String, Object], default: '#' } },
            setup: (props, { slots }) => () =>
                h('a', { href: resolveHref(props.href) }, slots.default?.()),
        }),
        Head: defineComponent({
            setup: (_, { slots }) => () => h('div', slots.default?.()),
        }),
    };
});

const LinkStub = defineComponent({
    props: { href: { type: [String, Object], default: '#' } },
    setup(props, { slots }) {
        return () =>
            h(
                'a',
                {
                    href:
                        typeof props.href === 'string'
                            ? props.href
                            : ((props.href as { url?: string })?.url ?? '#'),
                },
                slots.default?.(),
            );
    },
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup: (_, { slots }) => () => h('div', slots.default?.()),
    }),
}));

const profileUser = {
    id: 42,
    name: 'Maria Santos',
    avatar: null,
    cover_photo: '/storage/covers/banner.jpg',
    bio: 'Curious learner and future scientist.',
    sections: ['Grade 11 - Rizal'],
    streak: 6,
    joinedAt: 'Mar 2025',
    isCurrentUser: true,
};

const stats = {
    level: 8,
    xp: 4200,
    rank: 3,
    totalPlayers: 120,
    badgesCount: 2,
    followersCount: 12,
    followingCount: 4,
};

const history = [
    {
        id: 1,
        amount_xp: 50,
        amount_points: 10,
        reason: 'Lesson completed',
        description: 'Finished Chapter 3',
        date: '2 hours ago',
        full_date: 'Aug 14, 2026 09:00 AM',
        section: 'Grade 11 - Rizal',
    },
];

const badges = [
    {
        id: 1,
        name: 'First Steps',
        description: 'Completed the first lesson',
        image: null,
        requiredLevel: 1,
        earnedSeason: 'Season 1',
        earnedAt: 'Mar 02, 2025',
    },
];

const courses = [
    {
        id: 1,
        name: 'Algebra I',
        progress: 60,
        completedLessons: 6,
        totalLessons: 10,
        xpEarned: 300,
    },
];

const mountProfile = (overrides: Record<string, unknown> = {}) =>
    mount(PublicProfile, {
        props: {
            profileUser,
            stats,
            badges,
            courses,
            history,
            isSameSection: true,
            isFollowing: false,
            kudos: { 'great-work': 0, 'on-fire': 0, 'keep-going': 0 },
            viewerKudo: null,
            ...overrides,
        },
        global: { stubs: { Link: LinkStub } },
    });

describe('public profile — social layout', () => {
    it('renders a social-style identity header with handle and counts', () => {
        const wrapper = mountProfile();
        const text = wrapper.text();

        expect(text).toContain('Maria Santos');
        expect(text).toContain('@mariasantos');
        expect(text).toContain('Level');
        expect(text).toContain('Season XP');
        expect(text).toContain('Badges');
        expect(text).toContain('Day streak');
        expect(text).toContain('Grade 11 - Rizal');
    });

    it('scopes the Apple-style profile tokens to the profile shell', () => {
        const wrapper = mountProfile();
        const css = readFileSync(
            join(process.cwd(), 'resources/css/app.css'),
            'utf8',
        );

        expect(wrapper.html()).toContain('profile-ui');
        expect(css).toContain('.profile-ui .profile-card');
        expect(css).toContain('.profile-ui .profile-btn');
        expect(css).toContain('.profile-ui .profile-segment');
        // Shares the dashboard's system font stack.
        expect(css).toContain('.profile-ui {');
    });

    it('drops the shouty uppercase/black type of the old profile', () => {
        const html = mountProfile().html();

        expect(html).not.toContain('font-black');
        expect(html).not.toContain('tracking-widest');
    });

    it('switches between activity, achievements and courses tabs', async () => {
        const wrapper = mountProfile();
        const tabs = wrapper.findAll('[role="tab"]');

        expect(tabs).toHaveLength(3);
        expect(tabs.map((tab) => tab.text())).toEqual([
            expect.stringContaining('Activity'),
            expect.stringContaining('Achievements'),
            expect.stringContaining('Courses'),
        ]);

        expect(tabs[0].attributes('aria-selected')).toBe('true');

        await tabs[1].trigger('click');
        expect(
            wrapper.findAll('[role="tab"]')[1].attributes('aria-selected'),
        ).toBe('true');
    });

    it('hides the courses tab when viewers do not share a section', () => {
        const wrapper = mountProfile({ isSameSection: false, courses: [] });

        expect(wrapper.findAll('[role="tab"]')).toHaveLength(2);
    });

    it('renders the cover photo at the same 3:1 frame as the upload preview', () => {
        const wrapper = mountProfile();
        const cover = wrapper.find('img[src="/storage/covers/banner.jpg"]');

        expect(cover.exists()).toBe(true);
        expect(cover.classes()).toContain('object-cover');
        expect(wrapper.html()).toContain('aspect-ratio: 3');
    });

    it('offers an edit action only to the profile owner', () => {
        expect(mountProfile().text()).toContain('Edit profile');
        expect(
            mountProfile({
                profileUser: { ...profileUser, isCurrentUser: false },
            }).text(),
        ).not.toContain('Edit profile');
    });
});

describe('dashboard greeting avatar', () => {
    const heroProps = {
        userName: 'Maria Santos',
        userAvatar: '/storage/avatars/maria.jpg',
        userStats: {
            level: 8,
            totalXP: 4200,
            currentXP: 20,
            maxXPForLevel: 100,
            points: 10,
        },
        announcements: [],
        timeBasedGreeting: 'Good Morning',
        smarterStatus: 'Keep going.',
    };

    it('links the greeting avatar to the public profile', () => {
        const wrapper = mount(DashboardHero, {
            props: { ...heroProps, profileHref: '/u/42' },
            global: { stubs: { Link: LinkStub } },
        });

        const link = wrapper.find('a[href="/u/42"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('aria-label')).toContain('profile');
        expect(link.find('[data-slot="avatar"]').exists()).toBe(true);
    });

    it('keeps the avatar inert when no profile link is provided', () => {
        const wrapper = mount(DashboardHero, {
            props: heroProps,
            global: { stubs: { Link: LinkStub } },
        });

        expect(wrapper.find('a[href^="/u/"]').exists()).toBe(false);
        expect(wrapper.find('[data-slot="avatar"]').exists()).toBe(true);
    });

    it('passes the current user profile href from the dashboard page', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/Dashboard.vue'),
            'utf8',
        );

        expect(page).toContain('userProfileHref');
        expect(page).toContain(':profile-href="userProfileHref"');
    });
});

describe('cover photo cropper', () => {
    beforeEach(() => {
        globalThis.URL.createObjectURL = vi.fn(() => 'blob:cover');
        globalThis.URL.revokeObjectURL = vi.fn();

        // jsdom ships no matchMedia; useMobile() inside the modal needs it.
        window.matchMedia = vi.fn().mockImplementation((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })) as unknown as typeof window.matchMedia;
    });

    const makeFile = () =>
        new File([new Uint8Array([1, 2, 3])], 'holiday.png', {
            type: 'image/png',
        });

    it('stays closed until a file is selected', () => {
        const wrapper = mount(CoverPhotoCropper, { props: { file: null } });

        expect(wrapper.text()).not.toContain('Position your cover photo');
    });

    it('previews the picked file in a 3:1 frame with zoom and drag controls', async () => {
        const wrapper = mount(CoverPhotoCropper, {
            props: { file: makeFile() },
            attachTo: document.body,
        });
        await nextTick();

        const html = document.body.innerHTML;
        expect(html).toContain('Position your cover photo');
        expect(html).toContain('blob:cover');
        expect(html).toContain('Drag to reposition');
        expect(document.body.querySelector('input[type="range"]')).not.toBeNull();

        wrapper.unmount();
    });

    it('emits cancel when dismissed', async () => {
        const wrapper = mount(CoverPhotoCropper, {
            props: { file: makeFile() },
            attachTo: document.body,
        });
        await nextTick();

        const cancel = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent?.trim() === 'Cancel');

        cancel?.click();
        await nextTick();

        expect(wrapper.emitted('cancel')).toBeTruthy();
        wrapper.unmount();
    });
});

describe('profile settings cover upload', () => {
    it('routes the picked cover through the cropper before upload', () => {
        const page = readFileSync(
            join(process.cwd(), 'resources/js/pages/settings/Profile.vue'),
            'utf8',
        );

        expect(page).toContain('CoverPhotoCropper');
        expect(page).toContain('coverFileToCrop');
        // The cropped result replaces the file input contents.
        expect(page).toContain('new DataTransfer()');
        expect(page).toContain('applyCroppedCover');
        expect(page).toContain('style="aspect-ratio: 3"');
    });
});
