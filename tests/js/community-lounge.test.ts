import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

const mockRouterVisit = vi.fn();
const mockRouterGet = vi.fn();
const mockAxiosGet = vi.fn().mockResolvedValue({ data: {} });
const mockAxiosPost = vi.fn().mockResolvedValue({
    data: { is_liked: true, likes_count: 1, is_bookmarked: true },
});
const mockAxiosPut = vi.fn().mockResolvedValue({ data: {} });
const mockAxiosDelete = vi
    .fn()
    .mockResolvedValue({ data: { status: 'deleted' } });
let mockPageProps: Record<string, any> = {};
let mockPrefersReducedMotion = false;

vi.mock('axios', () => ({
    default: {
        post: (...args: any[]) => mockAxiosPost(...args),
        get: (...args: any[]) => mockAxiosGet(...args),
        put: (...args: any[]) => mockAxiosPut(...args),
        delete: (...args: any[]) => mockAxiosDelete(...args),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
    Head: defineComponent({
        setup(props, { slots }) {
            return () => h('div', { class: 'head-stub' }, slots.default?.());
        },
    }),
    router: {
        visit: (...args: any[]) => mockRouterVisit(...args),
        get: (...args: any[]) => mockRouterGet(...args),
        reload: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    usePage: () => ({
        props: mockPageProps,
    }),
    usePoll: vi.fn(),
    useForm: vi.fn((initial = {}) => ({
        ...initial,
        data: () => initial,
        post: vi.fn(),
        reset: vi.fn(),
        processing: false,
    })),
}));

vi.mock('@/composables/useMobile', () => ({
    useMobile: () => ({
        prefersReducedMotion: { value: mockPrefersReducedMotion },
        isLowEndDevice: { value: false },
        isMobile: { value: false },
        isDesktop: { value: true },
    }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        props: ['breadcrumbs'],
        setup(props, { slots }) {
            return () =>
                h('div', { class: 'app-layout-stub' }, slots.default?.());
        },
    }),
}));

// ClaimXpButton sub-component stub for CommandBar
vi.mock('@/components/dashboard/ClaimXpButton.vue', () => ({
    default: defineComponent({
        setup() {
            return () => h('button', { class: 'claim-stub' });
        },
    }),
}));

import CurtainWipeOverlay from '@/components/common/CurtainWipeOverlay.vue';
import CommandBar from '@/components/dashboard/CommandBar.vue';
import MobileDashboard from '@/components/dashboard/MobileDashboard.vue';
import Community from '@/pages/Community.vue';
import CommunityLocked from '@/pages/CommunityLocked.vue';
import CommunityProfile from '@/pages/CommunityProfile.vue';

describe('Community Lounge & Curtain Wipe Flow', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockAxiosGet.mockReset();
        mockAxiosGet.mockResolvedValue({ data: {} });
        mockAxiosPost.mockReset();
        mockAxiosPost.mockResolvedValue({
            data: { is_liked: true, likes_count: 1, is_bookmarked: true },
        });
        mockAxiosPut.mockReset();
        mockAxiosPut.mockResolvedValue({ data: {} });
        mockAxiosDelete.mockReset();
        mockAxiosDelete.mockResolvedValue({ data: { status: 'deleted' } });
        mockPageProps = {
            studentPageControls: {
                pages: {
                    community: {
                        mode: 'enabled',
                        label: 'Community Lounge',
                    },
                },
            },
        };
        mockPrefersReducedMotion = false;
        try {
            sessionStorage.clear();
        } catch {
            // Ignore
        }
    });

    describe('CommandBar launcher button', () => {
        const defaultProps = {
            userName: 'Alex',
            level: 3,
            currentXp: 120,
            maxXpForLevel: 300,
            greeting: 'Good morning',
            statusLine: 'On track',
            announcements: [],
            streak: 5,
        };

        it('renders the community lounge button in the command bar', () => {
            const wrapper = mount(CommandBar, {
                props: defaultProps,
            });

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(true);
            expect(button.attributes('aria-label')).toBe('Community Lounge');
            expect(button.text()).toContain('Community');
        });

        it('hides the launcher button when community page is disabled in student page controls', () => {
            mockPageProps = {
                studentPageControls: {
                    pages: {
                        community: {
                            mode: 'disabled',
                            label: 'Community Lounge',
                        },
                    },
                },
            };

            const wrapper = mount(CommandBar, {
                props: defaultProps,
            });

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(false);
        });

        it('triggers curtain wipe navigation to /community when clicked', async () => {
            const wrapper = mount(CommandBar, {
                props: defaultProps,
            });

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(true);

            await button.trigger('click');

            // Emitted event check
            expect(wrapper.emitted('open-community')).toBeTruthy();

            // Overlay exists
            const overlay = wrapper.find('[data-test="curtain-wipe-overlay"]');
            expect(overlay.exists()).toBe(true);
            expect(overlay.classes()).toContain('fixed');
            expect(overlay.classes()).toContain('inset-0');
            expect(overlay.classes()).toContain('pointer-events-none');
        });

        it('navigates immediately without transition when user prefers reduced motion', async () => {
            mockPrefersReducedMotion = true;

            const wrapper = mount(CommandBar, {
                props: defaultProps,
            });

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            await button.trigger('click');

            expect(mockRouterVisit).toHaveBeenCalledWith('/community', {
                preserveScroll: false,
            });
        });
    });

    describe('MobileDashboard launcher button', () => {
        const defaultMobileProps = {
            userName: 'Alex',
            userStats: {
                level: 3,
                currentXP: 120,
                maxXPForLevel: 300,
                totalXP: 120,
                rank: 'Bronze',
                rankNumber: 1,
                totalPlayers: 10,
                achievements: 2,
                points: 50,
                streak: 5,
                longestStreak: 5,
                joinedAt: '2025-01-01',
            },
            announcements: [],
            timeBasedGreeting: 'Good morning',
            greetingTheme: 'dawn',
            isRefreshing: false,
            claimXp: {
                canClaim: false,
                amount: 1,
                nextClaimAt: null,
            },
            todayTasks: [],
            sectionLeaderboards: [],
            leaderboardExpanded: false,
        };

        const mountOptions = {
            props: defaultMobileProps as any,
            global: {
                stubs: {
                    LevelProgressCard: true,
                    StreakCard: true,
                    TodayPanel: true,
                    FoxCompanion: true,
                    ImprovedLeaderboard: true,
                    StreakHeatmap: true,
                    ClaimXpButton: true,
                },
            },
        };

        it('renders the community lounge button in the mobile dashboard greeting', () => {
            const wrapper = mount(MobileDashboard, mountOptions);

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(true);
            expect(button.attributes('aria-label')).toBe('Community Lounge');
        });

        it('hides the launcher button when community page is disabled in student page controls', () => {
            mockPageProps = {
                studentPageControls: {
                    pages: {
                        community: {
                            mode: 'disabled',
                            label: 'Community Lounge',
                        },
                    },
                },
            };

            const wrapper = mount(MobileDashboard, mountOptions);

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(false);
        });

        it('triggers curtain wipe navigation to /community when clicked', async () => {
            const wrapper = mount(MobileDashboard, mountOptions);

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            expect(button.exists()).toBe(true);

            await button.trigger('click');

            expect(wrapper.emitted('open-community')).toBeTruthy();

            const overlay = wrapper.find('[data-test="curtain-wipe-overlay"]');
            expect(overlay.exists()).toBe(true);
        });

        it('navigates immediately without transition when user prefers reduced motion', async () => {
            mockPrefersReducedMotion = true;

            const wrapper = mount(MobileDashboard, mountOptions);

            const button = wrapper.find(
                '[data-test="community-lounge-button"]',
            );
            await button.trigger('click');

            expect(mockRouterVisit).toHaveBeenCalledWith('/community', {
                preserveScroll: false,
            });
        });
    });

    describe('CurtainWipeOverlay component', () => {
        it('renders with contract classes and pointer-events-none', () => {
            const wrapper = mount(CurtainWipeOverlay, {
                props: {
                    title: 'Community Lounge',
                    subtitle: 'Entering lounge...',
                },
            });

            const el = wrapper.find('[data-test="curtain-wipe-overlay"]');
            expect(el.exists()).toBe(true);
            expect(el.classes()).toContain('fixed');
            expect(el.classes()).toContain('inset-0');
            expect(el.classes()).toContain('z-[100]');
            expect(el.classes()).toContain('pointer-events-none');
            expect(wrapper.text()).toContain('Community Lounge');
            expect(wrapper.text()).toContain('Entering lounge...');
        });

        it('renders Apple VisionOS squircle glass card with status pill and micro progress', () => {
            const wrapper = mount(CurtainWipeOverlay, {
                props: {
                    title: 'Community Lounge',
                    subtitle: 'Connecting to workspace lounge...',
                },
            });

            const card = wrapper.find('[data-test="glass-card"]');
            expect(card.exists()).toBe(true);
            expect(card.classes()).toContain('backdrop-blur-2xl');
            expect(card.classes()).toContain('dark:border-white/15');
            expect(card.classes()).toContain('border-black/[0.08]');
            expect(wrapper.text()).toContain('Connecting');

            // Backdrop has frosted glass styling
            const backdrop = wrapper.find(
                '[data-test="curtain-wipe-backdrop"]',
            );
            expect(backdrop.exists()).toBe(true);
            expect(backdrop.classes()).toContain('backdrop-blur-3xl');
        });

        it('supports dismiss method and cancel handling', async () => {
            const wrapper = mount(CurtainWipeOverlay, {
                props: {
                    title: 'Community Lounge',
                },
            });

            // dismiss method is exposed
            expect(typeof wrapper.vm.dismiss).toBe('function');
            expect(typeof wrapper.vm.wipeTo).toBe('function');

            // Call dismiss directly
            wrapper.vm.dismiss();
            expect(
                wrapper.find('[data-test="curtain-wipe-overlay"]').exists(),
            ).toBe(true);
        });

        it('handles mount wipe and removes direction flag from sessionStorage', () => {
            sessionStorage.setItem('curtain_wipe_direction', 'to_community');

            const wrapper = mount(CurtainWipeOverlay, {
                props: {
                    expectedMountDirection: 'to_community',
                },
            });

            expect(sessionStorage.getItem('curtain_wipe_direction')).toBeNull();
            expect(
                wrapper.find('[data-test="curtain-wipe-overlay"]').exists(),
            ).toBe(true);
        });
    });

    describe('Community.vue page skeleton', () => {
        it('renders the header, back button and feed container placeholder', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: {
                        id: 1,
                        name: 'BSIT Department Workspace',
                    },
                },
            });

            expect(wrapper.text()).toContain('Community Lounge');
            // Workspace pill badge is omitted from header
            expect(wrapper.text()).not.toContain('BSIT Department Workspace');

            // Back button exists
            const backBtn = wrapper.find('[data-test="community-back-button"]');
            expect(backBtn.exists()).toBe(true);
            expect(backBtn.text()).toContain('Back to Dashboard');

            // Feed container placeholder exists
            const feedContainer = wrapper.find(
                '[data-test="community-feed-container"]',
            );
            expect(feedContainer.exists()).toBe(true);
            expect(feedContainer.text()).toContain('Share learning tips');

            // Header privacy badge was removed; the rule is stated in the
            // sidebar privacy note and the lounge guidelines instead.
            expect(wrapper.text()).not.toContain('Other Sections: Anonymous');
            expect(
                wrapper.find('[data-test="section-privacy-note"]').text(),
            ).toContain('Real names are visible only to classmates');
            expect(
                wrapper.find('[data-test="section-guidelines-card"]').text(),
            ).toContain('Identity boundary');

            // Invite-Only Lounge is removed
            expect(wrapper.text()).not.toContain('Invite-Only Lounge');
        });

        it('triggers return wipe back to /dashboard on back button click', async () => {
            mockPrefersReducedMotion = true;

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                },
            });

            const backBtn = wrapper.find('[data-test="community-back-button"]');
            await backBtn.trigger('click');

            expect(mockRouterVisit).toHaveBeenCalledWith('/dashboard', {
                preserveScroll: false,
            });
        });

        it('renders the right sidebar with section online presence mockup, online counts, and privacy note', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: {
                        id: 1,
                        name: 'BSIT Department Workspace',
                    },
                    sectionName: 'BSIT 3-A',
                },
            });

            // Section presence card exists
            const presenceCard = wrapper.find(
                '[data-test="section-presence-card"]',
            );
            expect(presenceCard.exists()).toBe(true);
            expect(presenceCard.text()).toContain('BSIT 3-A');
            expect(presenceCard.text()).toContain('Online');
            expect(presenceCard.text()).not.toContain('Classroom Presence');

            // Classmates mockup rendered
            expect(presenceCard.text()).toContain('Sarah Chen');
            expect(presenceCard.text()).toContain('Class Officer');
            expect(presenceCard.text()).toContain('Reviewing Midterms');
            expect(presenceCard.text()).toContain('Marcus Vance');

            // Privacy reassurance note
            const privacyNote = wrapper.find(
                '[data-test="section-privacy-note"]',
            );
            expect(privacyNote.exists()).toBe(true);
            expect(privacyNote.text()).toContain(
                'Real names are visible only to classmates in your section',
            );
        });

        it('renders section switcher dropdown when multiple sections are available and triggers section selection', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    sectionName: 'Computer Application (2026)',
                    currentSectionId: 10,
                    availableSections: [
                        { id: 10, name: 'Computer Application (2026)' },
                        { id: 20, name: 'Computer Networking 1 (2026)' },
                    ],
                },
            });

            const trigger = wrapper.find(
                '[data-test="section-dropdown-trigger"]',
            );
            expect(trigger.exists()).toBe(true);
            expect(trigger.text()).toContain('Computer Application (2026)');

            // Open dropdown
            await trigger.trigger('click');
            const menu = wrapper.find('[data-test="section-dropdown-menu"]');
            expect(menu.exists()).toBe(true);
            expect(menu.text()).toContain('Computer Networking 1 (2026)');

            // Select another section
            const secondSectionBtn = menu.findAll('button')[1];
            await secondSectionBtn.trigger('click');

            expect(mockRouterGet).toHaveBeenCalledWith(
                '/community',
                expect.objectContaining({ section_id: 20 }),
                expect.objectContaining({
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            );
        });

        it('renders current user avatar photo and dynamic section in sidebar profile snippet', () => {
            mockPageProps = {
                auth: {
                    user: {
                        name: 'ad min',
                        public_id: 'usr_admin_123',
                        avatar: 'https://example.com/admin.jpg',
                    },
                },
            };

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    sectionName: 'Computer Application (2026)',
                },
            });

            const aside = wrapper.find('aside');
            expect(aside.exists()).toBe(true);
            expect(aside.text()).toContain('ad min');
            expect(aside.text()).toContain('Computer Application (2026)');

            const img = aside.find('img');
            expect(img.exists()).toBe(true);
            expect(img.attributes('src')).toBe('https://example.com/admin.jpg');
        });

        it('filters classmates list dynamically via search input', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                },
            });

            const searchInput = wrapper.find(
                'input[placeholder="Search classmates..."]',
            );
            expect(searchInput.exists()).toBe(true);

            await searchInput.setValue('Sarah');
            const presenceCard = wrapper.find(
                '[data-test="section-presence-card"]',
            );
            expect(presenceCard.text()).toContain('Sarah Chen');
            expect(presenceCard.text()).not.toContain('Marcus Vance');

            // Empty state check
            await searchInput.setValue('NonExistentStudent');
            expect(
                wrapper.find('[data-test="classmates-empty-state"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('No classmates match your search');
        });

        it('renders custom onlineClassmates prop and trending topics card', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    sectionName: 'BSCS 2-B',
                    onlineClassmates: [
                        {
                            id: 99,
                            name: 'Devon Custom',
                            status: 'online',
                            activity: 'Solving Lab #5',
                        },
                    ],
                },
            });

            expect(wrapper.text()).toContain('BSCS 2-B');
            expect(wrapper.text()).toContain('Devon Custom');
            expect(wrapper.text()).toContain('1 Online');

            // Trending topics card
            const topicsCard = wrapper.find(
                '[data-test="section-topics-card"]',
            );
            expect(topicsCard.exists()).toBe(true);
            expect(topicsCard.text()).toContain('Trending in Section');
            expect(topicsCard.text()).toContain('#MidtermExam');
            expect(topicsCard.text()).toContain('#NetworkingLab');

            // Guidelines section
            const guidelines = wrapper.find(
                '[data-test="section-guidelines-card"]',
            );
            expect(guidelines.exists()).toBe(true);
            expect(guidelines.text()).toContain('Lounge Guidelines');
        });

        it('opens and closes the mobile online presence drawer via trigger button and close button', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    sectionName: 'BSIT 3-A',
                },
            });

            // Mobile trigger button exists on the right side of the navigation bar with tab styling
            const mobileTrigger = wrapper.find(
                '[data-test="mobile-presence-drawer-trigger"]',
            );
            expect(mobileTrigger.exists()).toBe(true);
            expect(mobileTrigger.text()).toContain('Online');
            expect(mobileTrigger.classes()).toContain('py-2.5');
            expect(mobileTrigger.classes()).not.toContain('rounded-full');

            // Drawer is closed initially
            expect(
                wrapper.find('[data-test="mobile-presence-drawer"]').exists(),
            ).toBe(false);

            // Tap mobile trigger button
            await mobileTrigger.trigger('click');

            // Drawer is now open
            const drawer = wrapper.find('[data-test="mobile-presence-drawer"]');
            expect(drawer.exists()).toBe(true);
            expect(drawer.text()).toContain('BSIT 3-A');
            expect(drawer.text()).toContain('Sarah Chen');
            expect(drawer.text()).toContain(
                'Real names are visible only to classmates in your section',
            );

            // Search inside drawer
            const drawerInput = drawer.find(
                'input[placeholder="Search classmates..."]',
            );
            expect(drawerInput.exists()).toBe(true);

            // Tap close button inside drawer
            const closeBtn = wrapper.find(
                '[data-test="mobile-presence-drawer-close"]',
            );
            expect(closeBtn.exists()).toBe(true);
            await closeBtn.trigger('click');

            // Drawer is closed
            expect(
                wrapper.find('[data-test="mobile-presence-drawer"]').exists(),
            ).toBe(false);
        });

        it('does not render Same Section or Other Section badges on post author header', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Hello world',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 0,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                                section_name: 'BSIT 3-A',
                            },
                            comments: [],
                        },
                    ],
                },
            });

            const postArticle = wrapper.find('article');
            expect(postArticle.exists()).toBe(true);
            expect(postArticle.text()).not.toContain('Same Section');
            expect(postArticle.text()).not.toContain('Other Section');
            expect(postArticle.text()).toContain('sarah.chen');
            expect(postArticle.text()).toContain('Sarah Chen');
        });

        it('optimistically toggles like immediately and debounces background network sync', async () => {
            vi.useFakeTimers();
            mockAxiosPost.mockResolvedValueOnce({
                data: { is_liked: true, likes_count: 1 },
            });

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Hello world',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 0,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [],
                        },
                    ],
                },
            });

            // Find like button (with Heart icon)
            const likeBtn = wrapper.find('[data-test="post-like-button"]');
            expect(likeBtn.exists()).toBe(true);

            // Optimistic update
            await likeBtn.trigger('click');
            expect(likeBtn.text()).toContain('1');
            expect(mockAxiosPost).not.toHaveBeenCalled();

            // Advance debounce timer
            await vi.advanceTimersByTimeAsync(360);

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/posts/1/like',
                {},
                { headers: { Accept: 'application/json' } },
            );

            vi.useRealTimers();
        });

        it('skips network call when rapid like toggles cancel out', async () => {
            vi.useFakeTimers();

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Hello world',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 0,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [],
                        },
                    ],
                },
            });

            const likeBtn = wrapper.find('[data-test="post-like-button"]');
            expect(likeBtn.exists()).toBe(true);

            // Click like (optimistic +1)
            await likeBtn.trigger('click');
            expect(likeBtn.text()).toContain('1');

            // Click unlike before debounce fires (optimistic back to 0)
            await likeBtn.trigger('click');
            expect(likeBtn.text()).toContain('0');

            await vi.advanceTimersByTimeAsync(400);

            expect(mockAxiosPost).not.toHaveBeenCalled();
            vi.useRealTimers();
        });

        it('opens story modal when clicking Your Story without opening feed composer or profile modal', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                },
            });

            // Feed container has the collapsed composer input initially
            expect(wrapper.find('textarea').exists()).toBe(false);

            // Find "Your story" item in story tray
            const storyItems = wrapper.findAll(
                '.group.flex.shrink-0.cursor-pointer',
            );
            const yourStoryItem = storyItems.find((item) =>
                item.text().includes('Your story'),
            );
            expect(yourStoryItem?.exists()).toBe(true);

            await yourStoryItem!.trigger('click');

            // Story modal is now opened, feed composer is NOT opened
            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(true);
            expect(wrapper.find('textarea').exists()).toBe(false);
            // Profile modal is not rendered
            expect(
                wrapper.find('[data-test="profile-preview-modal"]').exists(),
            ).toBe(false);
        });

        it('does not mask profile modal when viewing authenticated student', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () =>
                    Promise.resolve({
                        is_masked: false,
                        is_self: true,
                        name: 'Alex Rivera',
                        handle: 'alex.rivera',
                    }),
            });

            mockPageProps = {
                auth: {
                    user: {
                        name: 'Alex Rivera',
                        public_id: 'usr_alex_123',
                    },
                },
            };

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'My own post',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 0,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                public_id: 'usr_alex_123',
                                name: 'Alex Rivera',
                                handle: 'alex.rivera',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [],
                        },
                    ],
                },
            });

            // Click author handle
            const authorSpan = wrapper.find('[data-test="post-author-handle"]');
            expect(authorSpan.exists()).toBe(true);
            await authorSpan.trigger('click');

            const modal = wrapper.find('[data-test="profile-preview-modal"]');
            expect(modal.exists()).toBe(true);
            expect(modal.text()).toContain('Alex Rivera');
            expect(modal.text()).not.toContain(
                'This student posted anonymously',
            );
        });

        it('renders student avatar image and active story gradient ring in profile modal', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () =>
                    Promise.resolve({
                        is_masked: false,
                        is_self: false,
                        name: 'Tina Grande Calub',
                        handle: 'tina.grande.calub',
                        avatar: 'https://example.com/tina.jpg',
                        has_active_stories: true,
                    }),
            });

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: [
                        {
                            user_id: 88,
                            public_id: 'usr_tina_88',
                            name: 'Tina Grande Calub',
                            handle: 'tina.grande.calub',
                            avatar: 'https://example.com/tina.jpg',
                            is_self: false,
                            is_masked: false,
                            stories: [
                                {
                                    id: 1,
                                    media_url: 'https://example.com/story.jpg',
                                    media_type: 'image',
                                    duration_seconds: 5,
                                    views_count: 3,
                                    reactions_count: 1,
                                    user_reaction: null,
                                    created_at: '1h ago',
                                },
                            ],
                        },
                    ],
                    onlineClassmates: [
                        {
                            id: 88,
                            public_id: 'usr_tina_88',
                            name: 'Tina Grande Calub',
                            avatar: 'https://example.com/tina.jpg',
                            status: 'online',
                        },
                    ],
                },
            });

            const classmateRow = wrapper.find(
                '[data-test="section-presence-card"] .group',
            );
            expect(classmateRow.exists()).toBe(true);
            await classmateRow.trigger('click');

            const modal = wrapper.find('[data-test="profile-preview-modal"]');
            expect(modal.exists()).toBe(true);
            expect(modal.text()).toContain('Tina Grande Calub');

            // Avatar image is rendered
            const avatarImg = modal.find('img');
            expect(avatarImg.exists()).toBe(true);
            expect(avatarImg.attributes('src')).toBe(
                'https://example.com/tina.jpg',
            );

            // Story gradient ring is present
            expect(modal.html()).toContain(
                'from-brand via-[#e6a875] to-[#f5eedc]',
            );
            expect(modal.text()).toContain('Story available');
        });

        it('renders commenter avatar and handle, and opens profile modal on click', async () => {
            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () =>
                    Promise.resolve({
                        is_masked: false,
                        is_self: false,
                        name: 'Marcus Vance',
                        handle: 'marcus.vance',
                    }),
            });

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Post with comment',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 1,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [
                                {
                                    id: 101,
                                    author_id: '2',
                                    author_public_id: 'usr_marcus_456',
                                    author_name: 'Marcus Vance',
                                    author_handle: 'marcus.vance',
                                    author_avatar: null,
                                    is_masked: false,
                                    section_name: 'BSIT 3-A',
                                    content: 'Great insight here!',
                                    created_at: '5m ago',
                                    replies: [],
                                },
                            ],
                        },
                    ],
                },
            });

            const commentHandle = wrapper.find(
                '[data-test="comment-author-handle"]',
            );
            expect(commentHandle.exists()).toBe(true);
            expect(commentHandle.text()).toContain('marcus.vance');

            const commentAvatar = wrapper.find('[data-test="comment-avatar"]');
            expect(commentAvatar.exists()).toBe(true);
            expect(commentAvatar.text()).toContain('MV');

            // Click commenter handle opens profile modal
            await commentHandle.trigger('click');
            const modal = wrapper.find('[data-test="profile-preview-modal"]');
            expect(modal.exists()).toBe(true);
            expect(modal.text()).toContain('Marcus Vance');
        });

        it('toggles threaded replies visibility between View replies and Hide replies', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Post with threaded replies',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 2,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [
                                {
                                    id: 201,
                                    author_name: 'Elena Rostova',
                                    author_handle: 'elena.rostova',
                                    content: 'Can someone explain step 2?',
                                    created_at: '10m ago',
                                    replies: [
                                        {
                                            id: 202,
                                            parent_id: 201,
                                            author_name: 'David Kim',
                                            author_handle: 'david.kim',
                                            content:
                                                'Step 2 applies the subnet mask.',
                                            created_at: '8m ago',
                                            replies: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            });

            // Toggle button rendered with 1 reply
            const toggleRepliesBtn = wrapper.find(
                '[data-test="toggle-replies-button"]',
            );
            expect(toggleRepliesBtn.exists()).toBe(true);
            expect(toggleRepliesBtn.text()).toContain('View 1 reply');

            // Replies container collapsed initially
            expect(
                wrapper
                    .find('[data-test="comment-replies-container"]')
                    .exists(),
            ).toBe(false);

            // Expand replies
            await toggleRepliesBtn.trigger('click');
            expect(toggleRepliesBtn.text()).toContain('Hide replies');

            const repliesContainer = wrapper.find(
                '[data-test="comment-replies-container"]',
            );
            expect(repliesContainer.exists()).toBe(true);
            expect(repliesContainer.text()).toContain('david.kim');
            expect(repliesContainer.text()).toContain(
                'Step 2 applies the subnet mask.',
            );

            // Collapse replies
            await toggleRepliesBtn.trigger('click');
            expect(toggleRepliesBtn.text()).toContain('View 1 reply');
            expect(
                wrapper
                    .find('[data-test="comment-replies-container"]')
                    .exists(),
            ).toBe(false);
        });

        it('sets replyingTo state when clicking Reply, shows indicator pill, and sends parent_id via JSON', async () => {
            mockAxiosPost.mockResolvedValueOnce({
                data: {
                    comment: {
                        id: 302,
                        parent_id: 301,
                        author_name: 'Alex Rivera',
                        author_handle: 'alex.rivera',
                        content: 'Got it, thank you!',
                        created_at: 'just now',
                        replies: [],
                    },
                    comments_count: 2,
                },
            });

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Post with comment for reply',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 1,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [
                                {
                                    id: 301,
                                    author_name: 'Marcus Vance',
                                    author_handle: 'marcus.vance',
                                    content: 'Great tips!',
                                    created_at: '1m ago',
                                    replies: [],
                                },
                            ],
                        },
                    ],
                },
            });

            // Replying-to pill not present initially
            expect(
                wrapper.find('[data-test="replying-to-indicator"]').exists(),
            ).toBe(false);

            // Click reply button on comment
            const replyBtn = wrapper.find('[data-test="comment-reply-button"]');
            expect(replyBtn.exists()).toBe(true);
            await replyBtn.trigger('click');

            // Replying to indicator appears
            const indicator = wrapper.find(
                '[data-test="replying-to-indicator"]',
            );
            expect(indicator.exists()).toBe(true);
            expect(indicator.text()).toContain('Replying to @marcus.vance');

            // Type reply
            const input = wrapper.find(
                'input[placeholder="Reply to @marcus.vance…"]',
            );
            expect(input.exists()).toBe(true);
            await input.setValue('Got it, thank you!');

            // Submit reply
            const postBtn = wrapper.find('[data-test="post-comment-button"]');
            await postBtn.trigger('click');

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/posts/1/comments',
                {
                    content: 'Got it, thank you!',
                    parent_id: 301,
                },
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            // Replying indicator dismissed after submit
            expect(
                wrapper.find('[data-test="replying-to-indicator"]').exists(),
            ).toBe(false);
        });

        it('optimistically toggles like on comment and reply with debounced background sync', async () => {
            vi.useFakeTimers();
            mockAxiosPost.mockResolvedValueOnce({
                data: { is_liked: true, likes_count: 1 },
            });

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: [
                        {
                            id: 1,
                            content: 'Post with comment and reply',
                            created_at: 'just now',
                            likes_count: 0,
                            comments_count: 2,
                            is_liked: false,
                            is_bookmarked: false,
                            author: {
                                id: '1',
                                name: 'Sarah Chen',
                                handle: 'sarah.chen',
                                is_same_section: true,
                                is_masked: false,
                                is_following: false,
                            },
                            comments: [
                                {
                                    id: 401,
                                    author_name: 'Marcus Vance',
                                    author_handle: 'marcus.vance',
                                    content: 'Awesome!',
                                    created_at: '2m ago',
                                    likes_count: 0,
                                    is_liked: false,
                                    replies: [
                                        {
                                            id: 402,
                                            parent_id: 401,
                                            author_name: 'David Kim',
                                            author_handle: 'david.kim',
                                            content: 'Totally agree.',
                                            created_at: '1m ago',
                                            likes_count: 0,
                                            is_liked: false,
                                            replies: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            });

            // Comment like button
            const commentLikeBtn = wrapper.find(
                '[data-test="comment-like-button"]',
            );
            expect(commentLikeBtn.exists()).toBe(true);

            // Click like on comment (optimistic +1)
            await commentLikeBtn.trigger('click');
            expect(commentLikeBtn.text()).toContain('1');
            expect(mockAxiosPost).not.toHaveBeenCalled();

            // Advance debounce timer
            await vi.advanceTimersByTimeAsync(360);

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/comments/401/like',
                {},
                { headers: { Accept: 'application/json' } },
            );

            // Expand replies to test reply like
            const toggleRepliesBtn = wrapper.find(
                '[data-test="toggle-replies-button"]',
            );
            await toggleRepliesBtn.trigger('click');

            const replyLikeBtn = wrapper.find(
                '[data-test="reply-like-button"]',
            );
            expect(replyLikeBtn.exists()).toBe(true);

            mockAxiosPost.mockResolvedValueOnce({
                data: { is_liked: true, likes_count: 1 },
            });

            await replyLikeBtn.trigger('click');
            expect(replyLikeBtn.text()).toContain('1');

            await vi.advanceTimersByTimeAsync(360);

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/comments/402/like',
                {},
                { headers: { Accept: 'application/json' } },
            );

            vi.useRealTimers();
        });

        it('opens story upload modal when clicking plus badge on Your story', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                },
            });

            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(false);

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            expect(addStoryBtn.exists()).toBe(true);

            await addStoryBtn.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('Create MyDay Story');
        });

        it('opens story viewer modal when clicking classmate with active story', async () => {
            const mockStories = [
                {
                    user_id: 88,
                    name: 'Sarah Chen',
                    handle: 'sarah.chen',
                    avatar: null,
                    is_self: false,
                    is_masked: false,
                    stories: [
                        {
                            id: 501,
                            media_url: '/storage/story1.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'Study session in lounge',
                            views_count: 3,
                            created_at: '1h ago',
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockStories,
                },
            });

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(false);

            const storyItems = wrapper.findAll(
                '.group.flex.shrink-0.cursor-pointer',
            );
            const sarahStoryItem = storyItems.find((item) =>
                item.text().includes('Sarah'),
            );
            expect(sarahStoryItem?.exists()).toBe(true);

            await sarahStoryItem!.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('Sarah Chen');
            expect(wrapper.text()).toContain('Study session in lounge');
            expect(
                wrapper.find('[data-test="viewer-author-avatar"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-test="viewer-author-handle"]').exists(),
            ).toBe(true);
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-button"]')
                    .exists(),
            ).toBe(false);
        });

        it('shows all section students in the top story tray whether they have active stories or not', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    onlineClassmates: [
                        { id: 2, name: 'Sarah Chen', status: 'online' },
                        { id: 3, name: 'Marcus Vance', status: 'online' },
                        { id: 4, name: 'Chloe Santos', status: 'idle' },
                    ],
                    stories: [
                        {
                            user_id: 2,
                            name: 'Sarah Chen',
                            handle: 'sarah.chen',
                            avatar: null,
                            is_self: false,
                            is_masked: false,
                            stories: [
                                {
                                    id: 501,
                                    media_url: '/storage/story1.jpg',
                                    media_type: 'image' as const,
                                    duration_seconds: 5,
                                    views_count: 1,
                                    created_at: '5m ago',
                                    is_self: false,
                                },
                            ],
                        },
                    ],
                },
            });

            const storyTraySection = wrapper.find(
                'section[aria-label="Classmate Stories"]',
            );
            expect(storyTraySection.exists()).toBe(true);

            const storyItems = storyTraySection.findAll(
                '.group.flex.shrink-0.cursor-pointer',
            );
            // 4 items: "Your story" + Sarah Chen (active story) + Marcus + Chloe
            expect(storyItems).toHaveLength(4);
            expect(storyItems[0].text()).toContain('Your story');
            expect(storyItems[1].text()).toContain('Sarah');
            expect(storyItems[2].text()).toContain('Marcus');
            expect(storyItems[3].text()).toContain('Chloe');

            // Student with active story has brand theme gradient ring
            expect(storyItems[1].html()).toContain('from-brand');
            // Students without stories have neutral border ring
            expect(storyItems[2].html()).toContain('border-border');
            expect(storyItems[3].html()).toContain('border-border');
        });

        it('renders gradient ring and story preview image on Your story when self has active stories', async () => {
            const mockSelfStories = [
                {
                    user_id: 1,
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                    avatar: null,
                    is_self: true,
                    is_masked: false,
                    stories: [
                        {
                            id: 601,
                            media_url: '/storage/my-story.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'My campus snapshot',
                            views_count: 5,
                            created_at: '10m ago',
                            is_self: true,
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockSelfStories,
                },
            });

            // "Your story" should display the image preview
            const previewImg = wrapper.find(
                '[data-test="your-story-preview-image"]',
            );
            expect(previewImg.exists()).toBe(true);
            expect(previewImg.attributes('src')).toBe('/storage/my-story.jpg');

            // Ring should have gradient classes instead of dashed border
            const yourStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Your story'));
            expect(yourStoryItem?.html()).toContain('from-brand');

            // Plus badge button still exists so user can add another story
            expect(
                yourStoryItem?.find('button[title="Add to story"]').exists(),
            ).toBe(true);
        });

        it('renders Add to story button in story viewer options menu when viewing own story, and opens story modal on click', async () => {
            const mockSelfStories = [
                {
                    user_id: 1,
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                    avatar: null,
                    is_self: true,
                    is_masked: false,
                    stories: [
                        {
                            id: 601,
                            media_url: '/storage/my-story.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'My own story',
                            views_count: 3,
                            created_at: '10m ago',
                            is_self: true,
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockSelfStories,
                },
            });

            // Click "Your story" to open viewer
            const yourStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Your story'));
            await yourStoryItem!.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);

            // Left header shows clean "Your story" title without avatar or handle
            expect(
                wrapper.find('[data-test="viewer-author-avatar"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="viewer-author-handle"]').exists(),
            ).toBe(false);
            expect(wrapper.text()).toContain('Your story');
            expect(wrapper.text()).not.toContain('@alex.rivera');

            // Viewer header has 3-dots options button; dropdown is closed initially
            const optionsBtn = wrapper.find(
                '[data-test="viewer-story-options-button"]',
            );
            expect(optionsBtn.exists()).toBe(true);
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-menu"]')
                    .exists(),
            ).toBe(false);

            // Open 3-dots options menu
            await optionsBtn.trigger('click');
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-menu"]')
                    .exists(),
            ).toBe(true);

            const viewerAddBtn = wrapper.find(
                '[data-test="viewer-add-story-button"]',
            );
            expect(viewerAddBtn.exists()).toBe(true);

            // Click Add to story from viewer options menu
            await viewerAddBtn.trigger('click');

            // Story viewer closes and story upload modal opens
            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('Create MyDay Story');
        });

        it('styles tabs with tailored semantic colors for Feed, My Section, and Saved', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    currentTab: 'all',
                },
            });

            const feedTab = wrapper.find('[data-test="community-tab-all"]');
            const sectionTab = wrapper.find(
                '[data-test="community-tab-section"]',
            );
            const savedTab = wrapper.find('[data-test="community-tab-saved"]');

            expect(feedTab.classes()).toContain('text-brand');
            expect(sectionTab.classes()).toContain('text-muted-foreground');
            expect(savedTab.classes()).toContain('text-muted-foreground');

            // Switch to section tab
            await sectionTab.trigger('click');
            expect(sectionTab.classes()).toContain('text-emerald-600');

            // Switch to saved tab
            await savedTab.trigger('click');
            expect(savedTab.classes()).toContain('text-amber-600');
        });

        it('switches tabs instantly with optimistic client-side filtering and cache', async () => {
            const mockPosts = [
                {
                    id: 101,
                    content: 'Section post',
                    created_at: '10m ago',
                    is_pinned: false,
                    is_liked: false,
                    is_bookmarked: false,
                    likes_count: 0,
                    comments_count: 0,
                    author: {
                        id: '2',
                        name: 'Sarah Chen',
                        handle: 'sarah.chen',
                        is_same_section: true,
                        is_masked: false,
                        is_following: false,
                    },
                    comments: [],
                },
                {
                    id: 102,
                    content: 'Saved post from other section',
                    created_at: '20m ago',
                    is_pinned: false,
                    is_liked: false,
                    is_bookmarked: true,
                    likes_count: 0,
                    comments_count: 0,
                    author: {
                        id: '3',
                        name: 'Other Student',
                        handle: 'other.student',
                        is_same_section: false,
                        is_masked: false,
                        is_following: false,
                    },
                    comments: [],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    posts: mockPosts,
                    currentTab: 'all',
                },
            });

            expect(wrapper.findAll('article').length).toBe(2);

            // Click My Section tab -> immediately filters without waiting for network
            const sectionTab = wrapper.find(
                '[data-test="community-tab-section"]',
            );
            await sectionTab.trigger('click');

            expect(wrapper.findAll('article').length).toBe(1);
            expect(wrapper.text()).toContain('Section post');
            expect(wrapper.text()).not.toContain(
                'Saved post from other section',
            );

            // Click Saved tab -> immediately filters bookmarked posts
            const savedTab = wrapper.find('[data-test="community-tab-saved"]');
            await savedTab.trigger('click');

            expect(wrapper.findAll('article').length).toBe(1);
            expect(wrapper.text()).toContain('Saved post from other section');

            // Click Feed tab -> instantly restores full feed from cache
            const allTab = wrapper.find('[data-test="community-tab-all"]');
            await allTab.trigger('click');

            expect(wrapper.findAll('article').length).toBe(2);
        });

        it('renders reaction bar when viewing classmate story, and sends reaction on emoji click', async () => {
            mockAxiosPost.mockResolvedValueOnce({
                data: {
                    success: true,
                    story_id: 501,
                    user_reaction: '❤️',
                    is_reacted: true,
                    reactions_count: 1,
                },
            });

            const mockStories = [
                {
                    user_id: 88,
                    name: 'Sarah Chen',
                    handle: 'sarah.chen',
                    avatar: null,
                    is_self: false,
                    is_masked: false,
                    stories: [
                        {
                            id: 501,
                            media_url: '/storage/story1.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'Classmate story caption',
                            views_count: 3,
                            reactions_count: 0,
                            user_reaction: null,
                            is_reacted: false,
                            created_at: '1h ago',
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockStories,
                },
            });

            // Open Sarah's story
            const sarahStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Sarah'));
            await sarahStoryItem!.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);

            // Viewer should display transparent glass reaction bar, play/pause button, and offset caption
            const reactionsBar = wrapper.find(
                '[data-test="story-reactions-bar"]',
            );
            expect(reactionsBar.exists()).toBe(true);
            expect(reactionsBar.classes()).toContain('border-white/20');
            expect(reactionsBar.classes()).toContain('bg-black/40');
            expect(
                wrapper.find('[data-test="viewer-reactions-count"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="viewer-play-pause-button"]').exists(),
            ).toBe(true);

            // All 6 outline reaction buttons exist and contain Lucide SVG icons
            const reactionButtons = reactionsBar.findAll('button');
            expect(reactionButtons.length).toBe(6);
            for (const btn of reactionButtons) {
                expect(btn.find('svg').exists()).toBe(true);
            }

            const captionOverlay = wrapper.find(
                '[data-test="story-caption-overlay"]',
            );
            expect(captionOverlay.classes()).toContain('bottom-16');

            // Click heart reaction
            const heartReactionBtn = wrapper.find(
                '[data-test="story-reaction-❤️"]',
            );
            expect(heartReactionBtn.exists()).toBe(true);

            await heartReactionBtn.trigger('click');

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/stories/501/reactions',
                { reaction: '❤️' },
            );
        });

        it('renders reaction counter and Add to story button in viewer when viewing own story, but no reaction bar', async () => {
            const mockSelfStories = [
                {
                    user_id: 1,
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                    avatar: null,
                    is_self: true,
                    is_masked: false,
                    stories: [
                        {
                            id: 601,
                            media_url: '/storage/my-story.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'My own story',
                            views_count: 12,
                            reactions_count: 5,
                            user_reaction: null,
                            is_reacted: false,
                            created_at: '10m ago',
                            is_self: true,
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockSelfStories,
                },
            });

            // Open own story
            const yourStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Your story'));
            await yourStoryItem!.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);

            // Author views reaction counter at lower-left, views count, play/pause & options button, but no reaction bar
            const reactionsCounter = wrapper.find(
                '[data-test="viewer-reactions-count"]',
            );
            expect(reactionsCounter.exists()).toBe(true);
            expect(reactionsCounter.text()).toContain('5');
            expect(reactionsCounter.classes()).toContain('bottom-3.5');
            expect(reactionsCounter.classes()).toContain('left-3.5');

            expect(
                wrapper.find('[data-test="viewer-views-count"]').exists(),
            ).toBe(true);
            const playPauseBtn = wrapper.find(
                '[data-test="viewer-play-pause-button"]',
            );
            expect(playPauseBtn.exists()).toBe(true);
            expect(playPauseBtn.attributes('title')).toBe('Pause story');

            // Click play/pause toggles pause state
            await playPauseBtn.trigger('click');
            expect(playPauseBtn.attributes('title')).toBe('Play story');

            await playPauseBtn.trigger('click');
            expect(playPauseBtn.attributes('title')).toBe('Pause story');

            expect(
                wrapper
                    .find('[data-test="viewer-story-options-button"]')
                    .exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-test="viewer-add-story-button"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="story-reactions-bar"]').exists(),
            ).toBe(false);

            // Caption overlay is positioned at bottom-14 above the reactions counter when viewing own story
            const captionOverlay = wrapper.find(
                '[data-test="story-caption-overlay"]',
            );
            expect(captionOverlay.classes()).toContain('bottom-14');
        });

        it('renders Feature button in story viewer when viewing own story, and pins story to profile featured highlights on click', async () => {
            mockAxiosPost.mockResolvedValueOnce({
                data: {
                    id: 99,
                    title: 'My own story',
                },
            });

            const mockSelfStories = [
                {
                    user_id: 1,
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                    avatar: null,
                    is_self: true,
                    is_masked: false,
                    stories: [
                        {
                            id: 601,
                            media_url: '/storage/my-story.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'My own story',
                            views_count: 12,
                            reactions_count: 5,
                            user_reaction: null,
                            is_reacted: false,
                            created_at: '10m ago',
                            is_self: true,
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockSelfStories,
                },
            });

            // Open own story
            const yourStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Your story'));
            await yourStoryItem!.trigger('click');

            // Open 3-dots options menu
            const optionsBtn = wrapper.find(
                '[data-test="viewer-story-options-button"]',
            );
            expect(optionsBtn.exists()).toBe(true);
            await optionsBtn.trigger('click');

            const featureBtn = wrapper.find(
                '[data-test="viewer-feature-story-button"]',
            );
            expect(featureBtn.exists()).toBe(true);

            await featureBtn.trigger('click');

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/profile/featured',
                {
                    story_id: 601,
                    title: 'My own story',
                },
            );

            expect(
                wrapper.find('[data-test="viewer-featured-toast"]').exists(),
            ).toBe(true);
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-menu"]')
                    .exists(),
            ).toBe(false);
        });

        it('toggles options menu and dismisses with Escape without closing viewer', async () => {
            const mockSelfStories = [
                {
                    user_id: 1,
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                    avatar: null,
                    is_self: true,
                    is_masked: false,
                    stories: [
                        {
                            id: 601,
                            media_url: '/storage/my-story.jpg',
                            media_type: 'image' as const,
                            duration_seconds: 5,
                            caption: 'My own story',
                            views_count: 12,
                            reactions_count: 5,
                            user_reaction: null,
                            is_reacted: false,
                            created_at: '10m ago',
                            is_self: true,
                        },
                    ],
                },
            ];

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    stories: mockSelfStories,
                },
            });

            const yourStoryItem = wrapper
                .findAll('.group.flex.shrink-0.cursor-pointer')
                .find((item) => item.text().includes('Your story'));
            await yourStoryItem!.trigger('click');

            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);

            const optionsBtn = wrapper.find(
                '[data-test="viewer-story-options-button"]',
            );
            await optionsBtn.trigger('click');
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-menu"]')
                    .exists(),
            ).toBe(true);

            // Press Escape to dismiss options menu
            window.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await wrapper.vm.$nextTick();

            // Menu is dismissed, but story viewer stays open
            expect(
                wrapper
                    .find('[data-test="viewer-story-options-menu"]')
                    .exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(true);

            // Pressing Escape again closes viewer
            window.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-test="community-story-viewer"]').exists(),
            ).toBe(false);
        });
    });

    describe('Community.vue MyDay Story modal WYSIWYG & controls', () => {
        it('renders live WYSIWYG preview with sync caption and updates duration for images', async () => {
            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            // Open story modal
            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');
            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(true);
            expect(wrapper.find('[data-test="story-dropzone"]').exists()).toBe(
                true,
            );

            // Simulate file selection
            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const file = new File(['image-bytes'], 'vacation.jpg', {
                type: 'image/jpeg',
            });
            Object.defineProperty(fileInput.element, 'files', {
                value: [file],
                writable: true,
            });
            await fileInput.trigger('change');

            // Now live preview canvas should be rendered
            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);
            expect(
                wrapper
                    .find('[data-test="story-preview-duration-badge"]')
                    .text(),
            ).toContain('5s');

            // Caption overlay is not rendered when caption is empty
            expect(
                wrapper.find('[data-test="story-preview-caption"]').exists(),
            ).toBe(false);

            // Type caption in input
            const captionInput = wrapper.find(
                '[data-test="story-caption-input"]',
            );
            await captionInput.setValue('Sunset from my dorm window ✨');

            // Live caption is displayed immediately in overlay
            const liveCaption = wrapper.find(
                '[data-test="story-preview-caption"]',
            );
            expect(liveCaption.exists()).toBe(true);
            expect(liveCaption.text()).toBe('Sunset from my dorm window ✨');

            // Select 10s duration chip
            const dur10Btn = wrapper.find('[data-test="story-duration-10"]');
            expect(dur10Btn.exists()).toBe(true);
            await dur10Btn.trigger('click');

            // Badge updates to 10s
            expect(
                wrapper
                    .find('[data-test="story-preview-duration-badge"]')
                    .text(),
            ).toContain('10s');
        });

        it('handles drag-and-drop file upload to dropzone', async () => {
            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const dropzone = wrapper.find('[data-test="story-dropzone"]');
            expect(dropzone.exists()).toBe(true);

            const file = new File(['fake-png'], 'design.png', {
                type: 'image/png',
            });
            await dropzone.trigger('dragover');
            await dropzone.trigger('drop', {
                dataTransfer: {
                    files: [file],
                },
            });

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);
        });

        it('clears preview and revokes blob URL when clicking change media or closing modal', async () => {
            const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const file = new File(['img'], 'photo.png', { type: 'image/png' });
            Object.defineProperty(fileInput.element, 'files', {
                value: [file],
                writable: true,
            });
            await fileInput.trigger('change');

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);

            // Click change media button
            const changeMediaBtn = wrapper.find(
                '[data-test="story-change-media"]',
            );
            expect(changeMediaBtn.exists()).toBe(true);
            await changeMediaBtn.trigger('click');

            expect(revokeSpy).toHaveBeenCalled();
            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(false);
            expect(wrapper.find('[data-test="story-dropzone"]').exists()).toBe(
                true,
            );

            revokeSpy.mockRestore();
        });

        it('revokes blob URL on modal close', async () => {
            const revokeSpy = vi.spyOn(URL, 'revokeObjectURL');

            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const file = new File(['img'], 'photo.png', { type: 'image/png' });
            Object.defineProperty(fileInput.element, 'files', {
                value: [file],
                writable: true,
            });
            await fileInput.trigger('change');

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);

            // Close modal via Cancel button
            const cancelBtn = wrapper
                .findAll('button')
                .find((btn) => btn.text() === 'Cancel');
            expect(cancelBtn?.exists()).toBe(true);
            await cancelBtn!.trigger('click');

            expect(revokeSpy).toHaveBeenCalled();
            expect(
                wrapper.find('[data-test="community-story-modal"]').exists(),
            ).toBe(false);

            revokeSpy.mockRestore();
        });

        it('handles video file and displays auto duration badge', async () => {
            const origCreateElement = document.createElement.bind(document);
            const createElSpy = vi
                .spyOn(document, 'createElement')
                .mockImplementation((tagName: string, options?: any) => {
                    const el = origCreateElement(tagName, options);
                    if (tagName === 'video') {
                        queueMicrotask(() => {
                            Object.defineProperty(el, 'duration', {
                                value: 12,
                                configurable: true,
                            });
                            (el as any).onloadedmetadata?.(
                                new Event('loadedmetadata'),
                            );
                        });
                    }
                    return el;
                });

            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const videoFile = new File(['video-data'], 'clip.mp4', {
                type: 'video/mp4',
            });
            Object.defineProperty(fileInput.element, 'files', {
                value: [videoFile],
                writable: true,
            });
            await fileInput.trigger('change');
            await nextTick();

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('Video Duration');
            expect(wrapper.text()).toContain('12s (auto)');
            expect(wrapper.find('video').exists()).toBe(true);

            createElSpy.mockRestore();
        });

        it('falls back to 15s duration when video metadata loading fails', async () => {
            const origCreateElement = document.createElement.bind(document);
            const createElSpy = vi
                .spyOn(document, 'createElement')
                .mockImplementation((tagName: string, options?: any) => {
                    const el = origCreateElement(tagName, options);
                    if (tagName === 'video') {
                        queueMicrotask(() => {
                            (el as any).onerror?.(new Event('error'));
                        });
                    }
                    return el;
                });

            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const videoFile = new File(['video-data'], 'clip.mp4', {
                type: 'video/mp4',
            });
            Object.defineProperty(fileInput.element, 'files', {
                value: [videoFile],
                writable: true,
            });
            await fileInput.trigger('change');
            await nextTick();

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('15s (auto)');

            createElSpy.mockRestore();
        });

        it('shows error if video duration exceeds 30 seconds', async () => {
            const origCreateElement = document.createElement.bind(document);
            const createElSpy = vi
                .spyOn(document, 'createElement')
                .mockImplementation((tagName: string, options?: any) => {
                    const el = origCreateElement(tagName, options);
                    if (tagName === 'video') {
                        queueMicrotask(() => {
                            Object.defineProperty(el, 'duration', {
                                value: 45,
                                configurable: true,
                            });
                            (el as any).onloadedmetadata?.(
                                new Event('loadedmetadata'),
                            );
                        });
                    }
                    return el;
                });

            const wrapper = mount(Community, {
                props: { workspace: null },
            });

            const addStoryBtn = wrapper.find('button[title="Add to story"]');
            await addStoryBtn.trigger('click');

            const fileInput = wrapper.find('[data-test="story-file-input"]');
            const videoFile = new File(['video-data'], 'long.mp4', {
                type: 'video/mp4',
            });
            Object.defineProperty(fileInput.element, 'files', {
                value: [videoFile],
                writable: true,
            });
            await fileInput.trigger('change');
            await nextTick();

            expect(
                wrapper.find('[data-test="story-live-preview"]').exists(),
            ).toBe(false);
            expect(wrapper.text()).toContain('cannot exceed 30 seconds');

            createElSpy.mockRestore();
        });
    });

    describe('Community.vue post & comment edit/delete flows', () => {
        const studentUser = {
            id: 1,
            public_id: 'usr_alex_123',
            name: 'Alex Rivera',
            handle: 'alex.rivera',
            is_admin: false,
        };

        const adminUser = {
            id: 99,
            public_id: 'usr_admin_999',
            name: 'Admin User',
            handle: 'admin.user',
            is_admin: true,
        };

        const createSamplePost = (overrides = {}) => ({
            id: 1,
            content: 'Student original post',
            created_at: 'just now',
            likes_count: 0,
            comments_count: 0,
            is_liked: false,
            is_bookmarked: false,
            author: {
                id: '1',
                public_id: 'usr_alex_123',
                name: 'Alex Rivera',
                handle: 'alex.rivera',
                is_same_section: true,
                is_masked: false,
                is_following: false,
            },
            comments: [],
            ...overrides,
        });

        it('renders post options button and menu for owned posts or admins, and hides for others', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const posts = [
                createSamplePost({
                    id: 1,
                    author: { id: '1', name: 'Alex Rivera' },
                }),
                createSamplePost({
                    id: 2,
                    content: 'Bob post',
                    author: { id: '2', name: 'Bob Vance' },
                }),
            ];

            const wrapper = mount(Community, {
                props: { workspace: null, posts },
            });

            const articles = wrapper.findAll('article');
            expect(articles.length).toBe(2);

            // Owned post has options button
            expect(
                articles[0].find('[data-test="post-options-button"]').exists(),
            ).toBe(true);
            // Other student post does not have options button
            expect(
                articles[1].find('[data-test="post-options-button"]').exists(),
            ).toBe(false);

            // Options menu is initially hidden
            expect(
                articles[0].find('[data-test="post-options-menu"]').exists(),
            ).toBe(false);

            // Click options button to toggle menu
            await articles[0]
                .find('[data-test="post-options-button"]')
                .trigger('click');
            const menu = articles[0].find('[data-test="post-options-menu"]');
            expect(menu.exists()).toBe(true);
            expect(menu.find('[data-test="edit-post-button"]').exists()).toBe(
                true,
            );
            expect(menu.find('[data-test="delete-post-button"]').exists()).toBe(
                true,
            );

            // Re-mount as admin: options button is rendered for other student post too
            mockPageProps = {
                auth: { user: adminUser },
            };
            const adminWrapper = mount(Community, {
                props: { workspace: null, posts },
            });
            const adminArticles = adminWrapper.findAll('article');
            expect(
                adminArticles[0]
                    .find('[data-test="post-options-button"]')
                    .exists(),
            ).toBe(true);
            expect(
                adminArticles[1]
                    .find('[data-test="post-options-button"]')
                    .exists(),
            ).toBe(true);
        });

        it('updates post content via PUT /community/posts/{id} when editing post inline', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({ id: 10, content: 'Initial text' });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            // Open menu and click Edit
            await wrapper
                .find('[data-test="post-options-button"]')
                .trigger('click');
            await wrapper
                .find('[data-test="edit-post-button"]')
                .trigger('click');

            // Inline edit form is displayed
            const editForm = wrapper.find('[data-test="post-edit-form"]');
            expect(editForm.exists()).toBe(true);

            const textarea = wrapper.find('[data-test="post-edit-textarea"]');
            expect((textarea.element as HTMLTextAreaElement).value).toBe(
                'Initial text',
            );

            await textarea.setValue('Updated post content by author');

            mockAxiosPut.mockResolvedValueOnce({
                data: {
                    status: 'updated',
                    post: {
                        id: 10,
                        content: 'Updated post content by author',
                        is_anonymous: false,
                    },
                },
            });

            await wrapper
                .find('[data-test="save-post-edit-button"]')
                .trigger('click');

            expect(mockAxiosPut).toHaveBeenCalledWith(
                '/community/posts/10',
                {
                    content: 'Updated post content by author',
                    is_anonymous: false,
                },
                {
                    headers: { Accept: 'application/json' },
                },
            );

            // Edit form closed and updated content rendered in feed
            expect(wrapper.find('[data-test="post-edit-form"]').exists()).toBe(
                false,
            );
            expect(wrapper.find('[data-test="post-content"]').text()).toBe(
                'Updated post content by author',
            );
        });

        it('opens post delete modal and removes post via DELETE /community/posts/{id}', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 20,
                content: 'Post to be deleted',
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            // Open menu and click Delete
            await wrapper
                .find('[data-test="post-options-button"]')
                .trigger('click');
            await wrapper
                .find('[data-test="delete-post-button"]')
                .trigger('click');

            // Confirmation modal is open
            const modal = wrapper.find('[data-test="delete-post-modal"]');
            expect(modal.exists()).toBe(true);
            expect(modal.text()).toContain('Delete Post');

            // Cancel deletion
            await wrapper
                .find('[data-test="cancel-delete-post-button"]')
                .trigger('click');
            expect(
                wrapper.find('[data-test="delete-post-modal"]').exists(),
            ).toBe(false);
            expect(wrapper.find('article').exists()).toBe(true);

            // Re-open and confirm deletion
            await wrapper
                .find('[data-test="post-options-button"]')
                .trigger('click');
            await wrapper
                .find('[data-test="delete-post-button"]')
                .trigger('click');

            mockAxiosDelete.mockResolvedValueOnce({
                data: { status: 'deleted' },
            });

            await wrapper
                .find('[data-test="confirm-delete-post-button"]')
                .trigger('click');

            expect(mockAxiosDelete).toHaveBeenCalledWith(
                '/community/posts/20',
                {
                    headers: { Accept: 'application/json' },
                },
            );

            // Modal closed and post removed from display
            expect(
                wrapper.find('[data-test="delete-post-modal"]').exists(),
            ).toBe(false);
            expect(wrapper.find('article').exists()).toBe(false);
        });

        it('renders edit and delete buttons for owned comments and replies, and hides for unowned', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                author: {
                    id: '99',
                    name: 'Other Author',
                    handle: 'other.author',
                },
                comments_count: 4,
                comments: [
                    {
                        id: 101,
                        author_id: '1',
                        author_name: 'Alex Rivera',
                        author_handle: 'alex.rivera',
                        content: 'My own top-level comment',
                        created_at: '2m ago',
                        replies: [
                            {
                                id: 201,
                                parent_id: 101,
                                author_id: '1',
                                author_name: 'Alex Rivera',
                                author_handle: 'alex.rivera',
                                content: 'My own reply',
                                created_at: '1m ago',
                                replies: [],
                            },
                            {
                                id: 202,
                                parent_id: 101,
                                author_id: '2',
                                author_name: 'Bob Vance',
                                author_handle: 'bob.vance',
                                content: 'Bob reply to my comment',
                                created_at: 'just now',
                                replies: [],
                            },
                        ],
                    },
                    {
                        id: 102,
                        author_id: '2',
                        author_name: 'Bob Vance',
                        author_handle: 'bob.vance',
                        content: 'Bob top-level comment',
                        created_at: '3m ago',
                        replies: [],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            // Expand replies on comment 101
            await wrapper
                .find('[data-test="toggle-replies-button"]')
                .trigger('click');

            const comments = wrapper.findAll('[data-test="comment-item"]');
            expect(comments.length).toBe(2);

            // Owned comment 101 has edit & delete
            expect(
                comments[0].find('[data-test="comment-edit-button"]').exists(),
            ).toBe(true);
            expect(
                comments[0]
                    .find('[data-test="comment-delete-button"]')
                    .exists(),
            ).toBe(true);

            // Unowned comment 102 on another user's post does not have edit & delete
            expect(
                comments[1].find('[data-test="comment-edit-button"]').exists(),
            ).toBe(false);
            expect(
                comments[1]
                    .find('[data-test="comment-delete-button"]')
                    .exists(),
            ).toBe(false);

            const replies = wrapper.findAll('[data-test="reply-item"]');
            expect(replies.length).toBe(2);

            // Owned reply 201 has edit & delete
            expect(
                replies[0].find('[data-test="reply-edit-button"]').exists(),
            ).toBe(true);
            expect(
                replies[0].find('[data-test="reply-delete-button"]').exists(),
            ).toBe(true);

            // Unowned reply 202 on another user's post does not have edit & delete
            expect(
                replies[1].find('[data-test="reply-edit-button"]').exists(),
            ).toBe(false);
            expect(
                replies[1].find('[data-test="reply-delete-button"]').exists(),
            ).toBe(false);
        });

        it('allows post owner to delete comments and replies by others on their post while hiding edit button', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                author: {
                    id: '1',
                    name: 'Alex Rivera',
                    handle: 'alex.rivera',
                },
                comments_count: 2,
                comments: [
                    {
                        id: 101,
                        author_id: '2',
                        author_name: 'Bob Vance',
                        author_handle: 'bob.vance',
                        content: 'Bob comment on Alex post',
                        created_at: '2m ago',
                        replies: [
                            {
                                id: 201,
                                parent_id: 101,
                                author_id: '3',
                                author_name: 'Carol Danvers',
                                author_handle: 'carol.danvers',
                                content: 'Carol reply on Alex post',
                                created_at: '1m ago',
                                replies: [],
                            },
                        ],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            // Expand replies on comment 101
            await wrapper
                .find('[data-test="toggle-replies-button"]')
                .trigger('click');

            const comment = wrapper.find('[data-test="comment-item"]');
            // Post owner CAN delete Bob's comment, but CANNOT edit it
            expect(
                comment.find('[data-test="comment-edit-button"]').exists(),
            ).toBe(false);
            expect(
                comment.find('[data-test="comment-delete-button"]').exists(),
            ).toBe(true);

            const reply = wrapper.find('[data-test="reply-item"]');
            // Post owner CAN delete Carol's reply, but CANNOT edit it
            expect(reply.find('[data-test="reply-edit-button"]').exists()).toBe(
                false,
            );
            expect(
                reply.find('[data-test="reply-delete-button"]').exists(),
            ).toBe(true);
        });

        it('edits comment inline and saves via PUT /community/comments/{id}', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                comments_count: 1,
                comments: [
                    {
                        id: 301,
                        author_id: '1',
                        author_name: 'Alex Rivera',
                        author_handle: 'alex.rivera',
                        content: 'Initial comment content',
                        created_at: '2m ago',
                        replies: [],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            // Click edit comment button
            await wrapper
                .find('[data-test="comment-edit-button"]')
                .trigger('click');

            const editForm = wrapper.find('[data-test="comment-edit-form"]');
            expect(editForm.exists()).toBe(true);

            const input = wrapper.find('[data-test="comment-edit-input"]');
            expect((input.element as HTMLInputElement).value).toBe(
                'Initial comment content',
            );

            await input.setValue('Edited comment content via test');

            mockAxiosPut.mockResolvedValueOnce({
                data: {
                    status: 'updated',
                    comment: {
                        id: 301,
                        content: 'Edited comment content via test',
                    },
                },
            });

            await wrapper
                .find('[data-test="save-comment-edit-button"]')
                .trigger('click');

            expect(mockAxiosPut).toHaveBeenCalledWith(
                '/community/comments/301',
                { content: 'Edited comment content via test' },
                { headers: { Accept: 'application/json' } },
            );

            // Form closed, updated comment rendered
            expect(
                wrapper.find('[data-test="comment-edit-form"]').exists(),
            ).toBe(false);
            expect(wrapper.find('[data-test="comment-content"]').text()).toBe(
                'Edited comment content via test',
            );
        });

        it('deletes comment and decrements counter by 1 plus its nested replies via DELETE /community/comments/{id}', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                comments_count: 3,
                comments: [
                    {
                        id: 401,
                        author_id: '1',
                        author_name: 'Alex Rivera',
                        author_handle: 'alex.rivera',
                        content: 'Parent comment with 2 replies',
                        created_at: '5m ago',
                        replies: [
                            {
                                id: 402,
                                parent_id: 401,
                                author_id: '2',
                                content: 'Reply 1',
                                created_at: '4m ago',
                                replies: [],
                            },
                            {
                                id: 403,
                                parent_id: 401,
                                author_id: '3',
                                content: 'Reply 2',
                                created_at: '3m ago',
                                replies: [],
                            },
                        ],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            expect(
                wrapper.find('[data-test="post-comments-count"]').text(),
            ).toBe('3');
            expect(wrapper.find('[data-test="comment-item"]').exists()).toBe(
                true,
            );

            mockAxiosDelete.mockResolvedValueOnce({
                data: { status: 'deleted' },
            });

            await wrapper
                .find('[data-test="comment-delete-button"]')
                .trigger('click');

            expect(
                wrapper.find('[data-test="delete-comment-modal"]').exists(),
            ).toBe(true);

            await wrapper
                .find('[data-test="confirm-delete-comment-button"]')
                .trigger('click');

            expect(mockAxiosDelete).toHaveBeenCalledWith(
                '/community/comments/401',
                { headers: { Accept: 'application/json' } },
            );

            // Comment removed and count decremented by 1 + 2 = 3
            expect(wrapper.find('[data-test="comment-item"]').exists()).toBe(
                false,
            );
            expect(
                wrapper.find('[data-test="post-comments-count"]').text(),
            ).toBe('0');
        });

        it('closes delete confirmation modal on cancel click without deleting', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                comments_count: 1,
                comments: [
                    {
                        id: 401,
                        author_id: '1',
                        author_name: 'Alex Rivera',
                        author_handle: 'alex.rivera',
                        content: 'Comment to keep',
                        created_at: '1m ago',
                        replies: [],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            await wrapper
                .find('[data-test="comment-delete-button"]')
                .trigger('click');
            expect(
                wrapper.find('[data-test="delete-comment-modal"]').exists(),
            ).toBe(true);

            await wrapper
                .find('[data-test="cancel-delete-comment-button"]')
                .trigger('click');
            expect(
                wrapper.find('[data-test="delete-comment-modal"]').exists(),
            ).toBe(false);
            expect(mockAxiosDelete).not.toHaveBeenCalled();
            expect(wrapper.find('[data-test="comment-item"]').exists()).toBe(
                true,
            );
        });

        it('deletes threaded reply and decrements counter by 1 via DELETE /community/comments/{id}', async () => {
            mockPageProps = {
                auth: { user: studentUser },
            };

            const post = createSamplePost({
                id: 1,
                comments_count: 2,
                comments: [
                    {
                        id: 501,
                        author_id: '2',
                        author_name: 'Bob Vance',
                        author_handle: 'bob.vance',
                        content: 'Bob comment',
                        created_at: '5m ago',
                        replies: [
                            {
                                id: 502,
                                parent_id: 501,
                                author_id: '1',
                                author_name: 'Alex Rivera',
                                author_handle: 'alex.rivera',
                                content: 'Alex owned reply',
                                created_at: '4m ago',
                                replies: [],
                            },
                        ],
                    },
                ],
            });

            const wrapper = mount(Community, {
                props: { workspace: null, posts: [post] },
            });

            expect(
                wrapper.find('[data-test="post-comments-count"]').text(),
            ).toBe('2');

            // Expand replies
            await wrapper
                .find('[data-test="toggle-replies-button"]')
                .trigger('click');
            expect(wrapper.find('[data-test="reply-item"]').exists()).toBe(
                true,
            );

            mockAxiosDelete.mockResolvedValueOnce({
                data: { status: 'deleted' },
            });

            await wrapper
                .find('[data-test="reply-delete-button"]')
                .trigger('click');

            expect(
                wrapper.find('[data-test="delete-comment-modal"]').exists(),
            ).toBe(true);

            await wrapper
                .find('[data-test="confirm-delete-comment-button"]')
                .trigger('click');

            expect(mockAxiosDelete).toHaveBeenCalledWith(
                '/community/comments/502',
                { headers: { Accept: 'application/json' } },
            );

            // Reply removed and count decremented by 1
            expect(wrapper.find('[data-test="reply-item"]').exists()).toBe(
                false,
            );
            expect(
                wrapper.find('[data-test="post-comments-count"]').text(),
            ).toBe('1');
        });
    });

    describe('CommunityProfile.vue lounge profile', () => {
        const mockProfile = {
            is_masked: false,
            is_self: false,
            public_id: 'usr_xyz789',
            name: 'Sarah Chen',
            handle: 'sarah.chen',
            avatar: null,
            bio: 'Coding networks and cloud infra.',
            level: 4,
            section_name: 'BSIT 3-A',
            presence_status: 'online' as const,
            presence_activity: 'Active in Lounge',
            can_follow: true,
            is_following: false,
            followers_count: 14,
            following_count: 8,
            posts_count: 3,
            likes_received: 29,
            posts: [
                {
                    id: 101,
                    content: 'Studying subnet masks for our practical exam!',
                    created_at: '2h ago',
                    likes_count: 7,
                    comments_count: 2,
                    is_liked: false,
                    is_bookmarked: false,
                    section_name: 'BSIT 3-A',
                },
            ],
            saved_posts: [],
        };

        it('renders the lounge profile with handles, metrics, and switcher to academic profile', () => {
            const wrapper = mount(CommunityProfile, {
                props: {
                    profile: mockProfile,
                },
            });

            expect(wrapper.text()).toContain('Sarah Chen');
            expect(wrapper.text()).toContain('@sarah.chen');
            expect(wrapper.text()).toContain('BSIT 3-A');
            expect(wrapper.text()).toContain('Lv. 4');
            expect(wrapper.text()).toContain('Active in Lounge');

            // 4 metrics
            expect(wrapper.text()).toContain('3');
            expect(wrapper.text()).toContain('Lounge Posts');
            expect(wrapper.text()).toContain('29');
            expect(wrapper.text()).toContain('Likes');
            expect(wrapper.text()).toContain('14');
            expect(wrapper.text()).toContain('Followers');
            expect(wrapper.text()).toContain('8');
            expect(wrapper.text()).toContain('Following');

            // Switcher link to academic profile exists
            expect(wrapper.text()).toContain('View Academic Profile');
            expect(wrapper.text()).toContain('Switch to Academic Profile');
            expect(wrapper.html()).toContain('/u/usr_xyz789');

            // Post content rendered
            expect(wrapper.text()).toContain(
                'Studying subnet masks for our practical exam!',
            );
        });

        it('renders privacy shielded state when visiting student from another section', () => {
            const maskedProfile = {
                is_masked: true,
                is_self: false,
                public_id: null,
                name: 'Student #4a82',
                handle: 'student.4a82',
                avatar: null,
                bio: 'Identity and lounge activity shielded for cross-section student privacy.',
                level: 1,
                section_name: 'Other Section',
                presence_status: 'offline' as const,
                presence_activity: 'Privacy Shielded',
                can_follow: false,
                is_following: false,
                followers_count: 0,
                following_count: 0,
                posts_count: 0,
                likes_received: 0,
                posts: [],
                saved_posts: [],
            };

            const wrapper = mount(CommunityProfile, {
                props: {
                    profile: maskedProfile,
                },
            });

            expect(wrapper.text()).toContain('Student #4a82');
            expect(wrapper.text()).toContain('@student.4a82');
            expect(wrapper.text()).toContain('Classroom Privacy Boundary');
            expect(wrapper.text()).toContain(
                'This student is enrolled in a different section',
            );
            expect(wrapper.text()).toContain('Return to Community Lounge');
        });

        it('renders Facebook-style Featured highlights section on CommunityProfile with add button for self', async () => {
            const selfProfile = {
                ...mockProfile,
                is_self: true,
                featured_media: [
                    {
                        id: 201,
                        media_url: '/storage/feat1.jpg',
                        media_type: 'image' as const,
                        title: 'Lab Project',
                        sort_order: 0,
                        created_at: '3d ago',
                    },
                    {
                        id: 202,
                        media_url: '/storage/feat2.mp4',
                        media_type: 'video' as const,
                        title: 'Robotics Demo',
                        sort_order: 1,
                        created_at: '1d ago',
                    },
                ],
            };

            const wrapper = mount(CommunityProfile, {
                props: {
                    profile: selfProfile,
                },
            });

            const section = wrapper.find(
                '[data-test="featured-highlights-section"]',
            );
            expect(section.exists()).toBe(true);
            expect(section.text()).toContain('Featured');
            expect(section.text()).toContain('2/6');

            const items = wrapper.findAll('[data-test="featured-item"]');
            expect(items.length).toBe(2);
            expect(items[0].text()).toContain('Lab Project');
            expect(items[1].text()).toContain('Robotics Demo');
            expect(items[1].text()).toContain('Video');

            expect(
                wrapper.find('[data-test="add-featured-trigger"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-test="add-featured-slot"]').exists(),
            ).toBe(true);

            await wrapper
                .find('[data-test="add-featured-trigger"]')
                .trigger('click');
            expect(
                wrapper.find('[data-test="add-featured-modal"]').exists(),
            ).toBe(true);
            expect(wrapper.text()).toContain('Add to Featured');
        });

        it('opens Lightbox modal when clicking featured item', async () => {
            const profileWithFeatured = {
                ...mockProfile,
                featured_media: [
                    {
                        id: 201,
                        media_url: '/storage/feat1.jpg',
                        media_type: 'image' as const,
                        title: 'Lab Project Showcase',
                        sort_order: 0,
                        created_at: '3d ago',
                    },
                ],
            };

            const wrapper = mount(CommunityProfile, {
                props: {
                    profile: profileWithFeatured,
                },
            });

            expect(
                wrapper.find('[data-test="featured-lightbox"]').exists(),
            ).toBe(false);

            await wrapper.find('[data-test="featured-item"]').trigger('click');

            const lightbox = wrapper.find('[data-test="featured-lightbox"]');
            expect(lightbox.exists()).toBe(true);
            expect(lightbox.text()).toContain('Lab Project Showcase');
        });

        it('allows selecting and pinning an archived story from Story Archive tab in Add to Featured modal', async () => {
            const selfProfile = {
                ...mockProfile,
                is_self: true,
                featured_media: [],
            };

            const mockArchiveStories = [
                {
                    id: 701,
                    media_url: '/storage/archived1.jpg',
                    media_type: 'image' as const,
                    duration_seconds: 5,
                    caption: 'Archived Hackathon Photo',
                    views_count: 10,
                    reactions_count: 4,
                    is_expired: true,
                    expires_at: '2026-10-21T00:00:00Z',
                    created_at: '2d ago',
                    formatted_date: 'Oct 21, 2026',
                },
            ];

            mockAxiosGet.mockResolvedValueOnce({
                data: { stories: mockArchiveStories },
            });

            const wrapper = mount(CommunityProfile, {
                props: { profile: selfProfile },
            });

            await wrapper
                .find('[data-test="add-featured-trigger"]')
                .trigger('click');
            expect(
                wrapper.find('[data-test="add-featured-modal"]').exists(),
            ).toBe(true);

            // Switch to Story Archive tab
            const archiveTab = wrapper.find(
                '[data-test="featured-source-archive"]',
            );
            expect(archiveTab.exists()).toBe(true);
            await archiveTab.trigger('click');

            // Wait for archive stories to load
            await flushPromises();

            expect(mockAxiosGet).toHaveBeenCalledWith(
                '/community/stories/archive',
            );
            expect(wrapper.text()).toContain('Archived Hackathon Photo');
            expect(wrapper.text()).toContain('Expired');

            // Select the archived story
            const storyTile = wrapper.find('[data-test="archive-story-701"]');
            expect(storyTile.exists()).toBe(true);
            await storyTile.trigger('click');

            // Title input defaults to caption
            const titleInput = wrapper.find('input[type="text"]');
            expect((titleInput.element as HTMLInputElement).value).toBe(
                'Archived Hackathon Photo',
            );

            // Save button should be enabled
            const saveBtn = wrapper.find(
                '[data-test="confirm-add-featured-button"]',
            );
            expect(saveBtn.attributes('disabled')).toBeUndefined();
        });
    });

    describe('CommunityLocked.vue locked gate', () => {
        it('renders the clean invite-only locked gate without workspace noise', () => {
            const wrapper = mount(CommunityLocked, {
                props: {
                    workspaceName: 'Engineering Campus',
                    studentName: 'Jordan Lee',
                    studentEmail: 'jordan@school.edu',
                    hasPendingInvite: true,
                },
            });

            expect(wrapper.text()).toContain('Community Lounge is Invite-Only');
            expect(wrapper.text()).not.toContain('Engineering Campus');
            expect(wrapper.text()).not.toContain('jordan@school.edu');
            expect(wrapper.find('input[type="text"]').exists()).toBe(true);
            expect(wrapper.find('button[type="submit"]').text()).toContain(
                'Unlock Lounge',
            );
        });

        it('submits invite code via POST /community/join-by-code and handles success', async () => {
            mockAxiosPost.mockResolvedValueOnce({
                data: { status: 'unlocked' },
            });

            const wrapper = mount(CommunityLocked, {
                props: {
                    studentName: 'Jordan Lee',
                },
            });

            const input = wrapper.find('input[type="text"]');
            await input.setValue('INVITE-SECRET-123');

            await wrapper.find('form').trigger('submit.prevent');

            expect(mockAxiosPost).toHaveBeenCalledWith(
                '/community/join-by-code',
                {
                    code: 'INVITE-SECRET-123',
                },
            );

            await flushPromises();
            expect(wrapper.text()).toContain('Lounge unlocked! Redirecting...');
        });

        it('displays error message when invite code fails verification', async () => {
            mockAxiosPost.mockRejectedValueOnce({
                response: {
                    data: {
                        message:
                            'Invalid or expired invite code for your account.',
                    },
                },
            });

            const wrapper = mount(CommunityLocked, {
                props: {
                    studentName: 'Jordan Lee',
                },
            });

            const input = wrapper.find('input[type="text"]');
            await input.setValue('INVALID-CODE');

            await wrapper.find('form').trigger('submit.prevent');
            await flushPromises();

            expect(wrapper.text()).toContain(
                'Invalid or expired invite code for your account.',
            );
        });
    });

    describe('Community.vue first-time welcome popup modal', () => {
        const testUser = {
            id: 1,
            public_id: 'usr_test_1',
            name: 'Test Student',
        };

        beforeEach(() => {
            window.localStorage.clear();
            mockPageProps = {
                auth: { user: testUser },
                studentPageControls: {
                    pages: {
                        community: {
                            mode: 'enabled',
                            label: 'Community Lounge',
                        },
                    },
                },
            };
        });

        it('renders welcome modal when isFirstTimeJoin is true and not previously seen', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            const modal = wrapper.find('[data-test="welcome-modal"]');
            expect(modal.exists()).toBe(true);
            expect(modal.text()).toContain(
                'Welcome to the Community Lounge! 🎉',
            );
            expect(modal.text()).toContain(
                'official member of the private student lounge',
            );
            expect(
                wrapper.find('[data-test="welcome-modal-skip"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-test="welcome-modal-enter"]').exists(),
            ).toBe(true);
        });

        it('does not render welcome modal when isFirstTimeJoin is false', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: false,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
        });

        it('dismisses modal and saves to localStorage on Skip click', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                true,
            );

            await wrapper
                .find('[data-test="welcome-modal-skip"]')
                .trigger('click');

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
            expect(
                window.localStorage.getItem('community_lounge_welcome_seen_1'),
            ).toBe('true');
        });

        it('dismisses modal and saves to localStorage on Enter Lounge click', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                true,
            );

            await wrapper
                .find('[data-test="welcome-modal-enter"]')
                .trigger('click');

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
            expect(
                window.localStorage.getItem('community_lounge_welcome_seen_1'),
            ).toBe('true');
        });

        it('dismisses modal on close button or backdrop click', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                true,
            );

            await wrapper
                .find('[data-test="welcome-modal-close"]')
                .trigger('click');

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
        });

        it('dismisses modal when pressing Escape', async () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                true,
            );

            window.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await wrapper.vm.$nextTick();

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
        });

        it('does not re-open modal if already marked as seen in localStorage', () => {
            window.localStorage.setItem(
                'community_lounge_welcome_seen_1',
                'true',
            );

            const wrapper = mount(Community, {
                props: {
                    workspace: null,
                    isFirstTimeJoin: true,
                },
            });

            expect(wrapper.find('[data-test="welcome-modal"]').exists()).toBe(
                false,
            );
        });
    });
});
