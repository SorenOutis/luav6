import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
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
