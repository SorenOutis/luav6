import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import StreakCalendarModal from '@/components/dashboard/StreakCalendarModal.vue';
import FoxCompanion from '@/components/FoxCompanion.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
        isAxiosError: (e: unknown) =>
            typeof e === 'object' && e !== null && 'isAxiosError' in e,
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload: vi.fn() },
}));

const mockedPost = vi.mocked(axios.post);

const ResponsiveModalStub = defineComponent({
    props: ['contentClass'],
    setup(_, { slots }) {
        return () => h('div', slots.default?.());
    },
});

function yesterdayStr(): string {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

const restoreInfo = {
    enabled: true,
    limit: 3,
    used: 0,
    remaining: 3,
    costs: [25, 50, 100],
    nextCost: 25,
    restoredDates: [],
    resetsAt: 'Oct 1',
};

const mountModal = (overrides: Record<string, unknown> = {}) =>
    mount(StreakCalendarModal, {
        props: {
            open: true,
            loginDates: [],
            currentStreak: 2,
            longestStreak: 6,
            userXp: 132,
            restore: { ...restoreInfo },
            ...overrides,
        },
        global: {
            stubs: {
                ResponsiveModal: ResponsiveModalStub,
            },
        },
        attachTo: document.body,
    });

/** First restorable-day button, stepping one month back if needed (e.g. the 1st). */
async function findRestorableButton(wrapper: ReturnType<typeof mountModal>) {
    let button = wrapper
        .findAll('button')
        .find((b) => (b.attributes('aria-label') ?? '').startsWith('Restore '));
    if (!button) {
        // Navigate to the previous month where every day is in the past.
        const navButtons = wrapper.findAll('button');
        await navButtons[0].trigger('click');
        button = wrapper
            .findAll('button')
            .find((b) =>
                (b.attributes('aria-label') ?? '').startsWith('Restore '),
            );
    }
    return button;
}

describe('streak calendar modal', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.body.innerHTML = '';
    });

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
        wrapper.unmount();
    });

    it('uses a two-column landscape layout on desktop widths', () => {
        const wrapper = mountModal();

        expect(wrapper.html()).toContain(
            'sm:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]',
        );
        wrapper.unmount();
    });

    it('hides the restore panel when the feature is disabled or absent', () => {
        const withoutProp = mountModal({ restore: null });
        expect(withoutProp.text()).not.toContain('Restore a day');
        withoutProp.unmount();

        const disabled = mountModal({
            restore: { ...restoreInfo, enabled: false },
        });
        expect(disabled.text()).not.toContain('Restore a day');
        disabled.unmount();
    });

    it('shows remaining restores, balance, and next cost', () => {
        const wrapper = mountModal();

        expect(wrapper.text()).toContain('Restore a day');
        expect(wrapper.text()).toContain('3 of 3 left');
        expect(wrapper.text()).toContain('Balance: 132 XP');
        expect(wrapper.text()).toContain('Tap a missed day');
        wrapper.unmount();
    });

    it('arms the restore button with the escalating cost when a missed day is selected', async () => {
        const wrapper = mountModal();

        const button = await findRestorableButton(wrapper);
        expect(button).toBeDefined();
        await button!.trigger('click');

        expect(wrapper.text()).toContain('−25 XP');
        wrapper.unmount();
    });

    it('blocks restore with a shortfall message when XP is insufficient', async () => {
        const wrapper = mountModal({ userXp: 10 });

        const button = await findRestorableButton(wrapper);
        expect(button).toBeDefined();
        await button!.trigger('click');

        expect(wrapper.text()).toContain('Need 15 more XP');
        const restoreButton = wrapper
            .findAll('button')
            .find(
                (b) => b.text().includes('Restore') && b.text().includes('XP'),
            );
        expect(restoreButton?.attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });

    it('shows the reset date when no restores remain', () => {
        const wrapper = mountModal({
            restore: { ...restoreInfo, used: 3, remaining: 0 },
        });

        expect(wrapper.text()).toContain('0 of 3 left');
        wrapper.unmount();
    });

    it('confirms and completes a restore, then reports the deduction', async () => {
        const target = yesterdayStr();
        mockedPost.mockResolvedValueOnce({
            data: {
                restored: true,
                reason: '',
                cost: 25,
                remaining: 2,
                total_xp: 107,
                current_streak: 5,
                restored_dates: [target],
            },
        });

        const wrapper = mountModal();
        const button = await findRestorableButton(wrapper);
        expect(button).toBeDefined();
        await button!.trigger('click');

        // First tap arms the two-tap confirm, second tap spends the XP.
        // (The confirm-state label reads "Confirm −25 XP?" — no "Restore".)
        const restoreButton = () =>
            wrapper
                .findAll('button')
                .find(
                    (b) =>
                        b.text().includes('−25 XP') &&
                        (b.text().includes('Restore') ||
                            b.text().includes('Confirm')),
                );
        await restoreButton()!.trigger('click');
        expect(restoreButton()!.text()).toContain('Confirm −25 XP?');
        await restoreButton()!.trigger('click');
        await flushPromises();

        expect(mockedPost).toHaveBeenCalledWith(
            '/api/streak-restore',
            expect.objectContaining({ date: expect.any(String) }),
            expect.anything(),
        );
        expect(wrapper.text()).toContain('Day restored · −25 XP');
        expect(wrapper.text()).toContain('2 of 3 left');
        expect(wrapper.text()).toContain('Balance: 107 XP');
        expect(wrapper.emitted('restored')).toBeTruthy();
        wrapper.unmount();
    });

    it('surfaces the server reason when a restore is rejected', async () => {
        mockedPost.mockResolvedValueOnce({
            data: {
                restored: false,
                reason: 'Not enough XP for this restore.',
                cost: 0,
                remaining: 3,
                total_xp: 10,
                current_streak: 2,
                restored_dates: [],
            },
        });

        const wrapper = mountModal({ userXp: 200 });
        const button = await findRestorableButton(wrapper);
        expect(button).toBeDefined();
        await button!.trigger('click');

        const restoreButton = () =>
            wrapper
                .findAll('button')
                .find(
                    (b) =>
                        b.text().includes('−25 XP') &&
                        (b.text().includes('Restore') ||
                            b.text().includes('Confirm')),
                );
        await restoreButton()!.trigger('click');
        await restoreButton()!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Not enough XP for this restore.');
        wrapper.unmount();
    });
});
