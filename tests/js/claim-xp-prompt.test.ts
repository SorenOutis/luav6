import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import ClaimXpButton from '@/components/dashboard/ClaimXpButton.vue';
import DailyRewardCard from '@/components/dashboard/DailyRewardCard.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn().mockResolvedValue({ data: { ok: true } }),
    },
}));

const modalTitle = 'Your daily XP reward is ready';

beforeEach(() => {
    document.body.innerHTML = '';
});

/**
 * The daily-XP popup must appear on its own when the dashboard loads after
 * login while a daily reward is unclaimed — not just render the inline
 * "Daily reward / Claim X XP" card. `showPrompt` is the server-side signal
 * that the popup should auto-open on this visit.
 */
describe('daily XP claim popup on login', () => {
    it('opens the popup when the dashboard loads with an unclaimed daily XP', async () => {
        const wrapper = mount(ClaimXpButton, {
            props: {
                canClaim: true,
                amount: 3,
                baseXp: 1,
                nextClaimAt: null,
                streak: 2,
                showPrompt: true,
            },
        });
        await flushPromises();

        expect(document.body.textContent).toContain(modalTitle);
        expect(document.body.textContent).toContain('Claim 3 XP');
        wrapper.unmount();
    });

    it('does not open the popup when the prompt was already shown', async () => {
        const wrapper = mount(ClaimXpButton, {
            props: {
                canClaim: true,
                amount: 3,
                baseXp: 1,
                nextClaimAt: null,
                streak: 2,
                showPrompt: false,
            },
        });
        await flushPromises();

        expect(document.body.textContent).not.toContain(modalTitle);
        wrapper.unmount();
    });

    it('keeps the popup dismissed after "Later" until the next dashboard entry', async () => {
        const wrapper = mount(ClaimXpButton, {
            props: {
                canClaim: true,
                amount: 3,
                baseXp: 1,
                nextClaimAt: null,
                streak: 2,
                showPrompt: true,
            },
        });
        await flushPromises();

        const later = [...document.body.querySelectorAll('button')].find(
            (btn) => btn.textContent?.trim() === 'Later',
        );
        expect(later).toBeDefined();
        later?.click();
        await flushPromises();

        expect(document.body.textContent).not.toContain(modalTitle);

        // Same-session props updates (poll refreshes) must not re-open it.
        await wrapper.setProps({ amount: 4 });
        await flushPromises();
        expect(document.body.textContent).not.toContain(modalTitle);

        // A fresh dashboard entry (remount, e.g. a new login/visit with the
        // reward still unclaimed) re-offers the popup.
        wrapper.unmount();
        await flushPromises();

        const remount = mount(ClaimXpButton, {
            props: {
                canClaim: true,
                amount: 4,
                baseXp: 1,
                nextClaimAt: null,
                streak: 2,
                showPrompt: true,
            },
        });
        await flushPromises();
        expect(document.body.textContent).toContain(modalTitle);
        remount.unmount();
    });

    it('forwards showPrompt from the DailyRewardCard to the button', async () => {
        const wrapper = mount(DailyRewardCard, {
            props: {
                claimXp: {
                    enabled: true,
                    canClaim: true,
                    amount: 3,
                    baseXp: 1,
                    nextClaimAt: null,
                    showPrompt: true,
                },
                streak: 2,
            },
        });
        await flushPromises();

        expect(document.body.textContent).toContain(modalTitle);
        wrapper.unmount();
    });
});
