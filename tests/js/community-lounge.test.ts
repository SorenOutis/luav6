import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

const mockRouterVisit = vi.fn();
let mockPageProps: Record<string, any> = {};
let mockPrefersReducedMotion = false;

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
        reload: vi.fn(),
    },
    usePage: () => ({
        props: mockPageProps,
    }),
    usePoll: vi.fn(),
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
import Community from '@/pages/Community.vue';

describe('Community Lounge & Curtain Wipe Flow', () => {
    beforeEach(() => {
        vi.clearAllMocks();
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
        it('renders the header, back button, privacy indicator and feed container placeholder', () => {
            const wrapper = mount(Community, {
                props: {
                    workspace: {
                        id: 1,
                        name: 'BSIT Department Workspace',
                    },
                },
            });

            expect(wrapper.text()).toContain('Community Lounge');
            expect(wrapper.text()).toContain('BSIT Department Workspace');

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

            // Privacy indicators exist
            expect(wrapper.text()).toContain('Your Section');
            expect(wrapper.text()).toContain('Other Sections');

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
            expect(presenceCard.text()).toContain('Enrolled');

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

            // Mobile trigger button exists on the right side of the navigation bar
            const mobileTrigger = wrapper.find(
                '[data-test="mobile-presence-drawer-trigger"]',
            );
            expect(mobileTrigger.exists()).toBe(true);
            expect(mobileTrigger.text()).toContain('Online');

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
    });
});
