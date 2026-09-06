import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { nextTick } from 'vue';
import LevelProgressCard from '@/components/dashboard/LevelProgressCard.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn().mockResolvedValue({
            data: { claimed: true, amount: 5, total_xp: 6, streak: 0 },
        }),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload: vi.fn() },
}));

vi.mock('@/composables/useNumberAnimation', () => ({
    useNumberAnimation: (getter: () => number) => getter(),
}));

vi.mock('@/composables/useMobile', () => ({
    useMobile: () => ({
        isMobile: false,
        isDesktop: true,
        prefersReducedMotion: true,
        isLowEndDevice: false,
    }),
}));

const userStats = { level: 3, currentXP: 40, maxXPForLevel: 100 };

// Exact shape DashboardController shares for the daily claim.
const claimXp = {
    enabled: true,
    canClaim: false,
    amount: 1,
    baseXp: 1,
    nextClaimAt: '2026-08-18T00:00:00+08:00',
    lastClaimedAt: '2026-08-17T08:00:00+08:00',
    showPrompt: false,
};

// Exact shape DashboardController shares for the bonus claim.
const bonusXp = {
    enabled: true,
    canClaim: true,
    amount: 5,
    nextClaimAt: null,
    lastClaimedAt: null,
};

const mountCard = (bonusOverride?: Record<string, unknown>) =>
    mount(LevelProgressCard, {
        props: {
            userStats: userStats as never,
            claimXp: claimXp as never,
            bonusXp: { ...bonusXp, ...bonusOverride } as never,
        },
        global: {
            stubs: { ResponsiveModal: { template: '<div><slot /></div>' } },
        },
    });

beforeEach(() => {
    document.body.innerHTML = '';
});

/**
 * The Level → "Your XP history" modal must show the Bonus XP claim block
 * (second daily reward) below the daily-claim banner whenever the admin has
 * enabled it in Platform Settings. This guards the exact prop contract the
 * DashboardController shares (`bonusXp.enabled / canClaim / amount /
 * lastClaimedAt`) so a regression in either side cannot silently hide the
 * bonus claim again.
 */
describe('LevelProgressCard bonus XP block', () => {
    it('renders the claimable bonus block when enabled and unclaimed', async () => {
        const wrapper = mountCard();
        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.find('[aria-label="XP history fox"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Your bonus XP is ready');
        expect(wrapper.text()).toContain('Claim 5 XP');
        wrapper.unmount();
    });

    it('hides the bonus block when the admin disabled it', async () => {
        const wrapper = mountCard({ enabled: false });
        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Your bonus XP is ready');
        expect(wrapper.text()).not.toContain('Claim 5 XP');
        wrapper.unmount();
    });

    it('shows the claimed state once claimed today', async () => {
        const wrapper = mountCard({
            canClaim: false,
            lastClaimedAt: '2026-08-17T08:05:00+08:00',
        });
        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Bonus XP claimed');
        expect(wrapper.text()).toContain('+5 XP');
        wrapper.unmount();
    });

    it('marks the block claimed locally right after claiming', async () => {
        const wrapper = mountCard();
        await wrapper.trigger('click');
        await flushPromises();

        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Claim 5 XP'));
        expect(button).toBeDefined();
        await button!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Bonus XP claimed');
        expect(wrapper.text()).toContain('Just now');
        wrapper.unmount();
    });
});

/**
 * Regression test for the production-only horizontal overflow in the
 * "Your XP history" modal. With Bonus XP in the claimable state, the
 * `min-w-[7.5rem]` Claim button inside a `shrink-0` flex block pushed the
 * dialog content wider than its box. On Windows (classic scrollbars that
 * consume layout width) this produced a bottom horizontal scrollbar and
 * cards clipped under the vertical scrollbar, while macOS overlay
 * scrollbars hid it locally. Guards the three CSS defenses: the dialog
 * clips horizontal overflow, the modal body can shrink inside the dialog
 * grid, and the claim rows / inner history scrollers yield instead of
 * forcing the dialog wider.
 */
describe('LevelProgressCard XP history modal overflow guards', () => {
    const mounted: Array<{ unmount: () => void }> = [];

    afterEach(() => {
        mounted.splice(0).forEach((w) => w.unmount());
        document.body.innerHTML = '';
    });

    // Real ResponsiveModal (reka-ui Dialog teleported to body), unlike the
    // stubbed version above, so the dialog-level classes are exercised.
    const mountRealModal = () => {
        const wrapper = mount(LevelProgressCard, {
            props: {
                userStats: userStats as never,
                claimXp: claimXp as never,
                bonusXp: { ...bonusXp } as never,
                xpHistory: [
                    {
                        id: 1,
                        reason: 'daily claim',
                        description: 'Daily login claim bonus',
                        amount: 5,
                        createdAt: '2026-09-06T17:34:00+08:00',
                        isClaim: true,
                    },
                    {
                        id: 2,
                        reason: 'exam',
                        description: 'On-time Exam XP for Exam: yesssssssir',
                        amount: 10,
                        createdAt: '2026-09-05T11:44:00+08:00',
                        isClaim: false,
                    },
                ] as never,
            },
        });
        mounted.push(wrapper);
        return wrapper;
    };

    const openDialog = async () => {
        const wrapper = mountRealModal();
        await wrapper.trigger('click');
        await flushPromises();
        await nextTick();
        await flushPromises();

        const dialog = document.querySelector('[data-slot="dialog-content"]');
        expect(dialog).toBeTruthy();
        return dialog as HTMLElement;
    };

    it('clips horizontal overflow at the dialog level (no bottom scrollbar)', async () => {
        const dialog = await openDialog();
        expect(dialog.classList.contains('overflow-x-hidden')).toBe(true);
        expect(dialog.classList.contains('overflow-y-auto')).toBe(true);
    });

    it('lets the modal body shrink inside the dialog grid', async () => {
        const dialog = await openDialog();
        const body = dialog.querySelector('.space-y-4');
        expect(body).toBeTruthy();
        expect(body!.classList.contains('min-w-0')).toBe(true);
    });

    it('wraps claim rows and clips inner history scroll instead of overflowing', async () => {
        const dialog = await openDialog();

        const bonus = dialog.querySelector('[aria-label="Bonus XP"]');
        expect(bonus).toBeTruthy();
        expect(bonus!.classList.contains('flex-wrap')).toBe(true);

        const daily = dialog.querySelector('[aria-label="Daily XP"]');
        expect(daily).toBeTruthy();
        expect(daily!.classList.contains('flex-wrap')).toBe(true);

        const scrollers = dialog.querySelectorAll('[data-lenis-prevent]');
        expect(scrollers.length).toBeGreaterThan(0);
        scrollers.forEach((el) =>
            expect(el.classList.contains('overflow-x-hidden')).toBe(true),
        );
    });
});
