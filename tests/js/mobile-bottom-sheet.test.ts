/**
 * MobileBottomSheet layout test — verifies that the header and footer slots
 * are rendered OUTSIDE the scrollable content area (pinned), so the close
 * button and action buttons stay visible on mobile while the body scrolls.
 */
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h, nextTick } from 'vue';
import MobileBottomSheet from '@/components/MobileBottomSheet.vue';

describe('MobileBottomSheet', () => {
    it('pins header and footer slots outside the scrollable content', async () => {
        const wrapper = mount(MobileBottomSheet, {
            attachTo: document.body,
            props: { open: true },
            slots: {
                header: h(
                    'div',
                    { 'data-test': 'sheet-header-slot' },
                    'Header',
                ),
                default: h('div', { 'data-test': 'sheet-body-slot' }, 'Body'),
                footer: h(
                    'div',
                    { 'data-test': 'sheet-footer-slot' },
                    'Footer',
                ),
            },
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));

        const sheet = document.body.querySelector(
            '[data-test="mobile-bottom-sheet"]',
        );
        const content = document.body.querySelector(
            '[data-test="mobile-bottom-sheet-content"]',
        );
        const header = document.body.querySelector(
            '[data-test="sheet-header-slot"]',
        );
        const footer = document.body.querySelector(
            '[data-test="sheet-footer-slot"]',
        );

        expect(sheet).not.toBeNull();
        expect(content).not.toBeNull();

        // Header + footer are siblings of the scroll container, not inside it
        expect(sheet!.contains(header)).toBe(true);
        expect(sheet!.contains(footer)).toBe(true);
        expect(content!.contains(header)).toBe(false);
        expect(content!.contains(footer)).toBe(false);

        // The body stays inside the scrollable middle
        expect(
            content!.contains(
                document.body.querySelector('[data-test="sheet-body-slot"]'),
            ),
        ).toBe(true);

        // The scrollable middle is capped to the viewport and scrolls
        expect(content!.classList.contains('overflow-y-auto')).toBe(true);
        expect(content!.classList.contains('grow')).toBe(true);
        // The sheet itself never exceeds the viewport height
        expect(sheet!.classList.contains('max-h-[min(85dvh,85vh)]')).toBe(true);

        wrapper.unmount();
    });

    it('keeps the X close button visible in the pinned header row', async () => {
        const wrapper = mount(MobileBottomSheet, {
            attachTo: document.body,
            props: { open: true, title: 'T', showCloseButton: true },
            slots: {
                default: h('div', {}, 'Body'),
            },
        });

        await nextTick();
        await new Promise((r) => setTimeout(r, 20));

        const closeButton = document.body.querySelector<HTMLButtonElement>(
            'button[aria-label="Close"]',
        );
        expect(closeButton).not.toBeNull();

        const content = document.body.querySelector(
            '[data-test="mobile-bottom-sheet-content"]',
        );
        // The close button must live in the header, not inside the scroll area
        expect(content!.contains(closeButton)).toBe(false);

        closeButton!.click();

        // handleClose emits `close` from the gsap exit animation's onComplete
        await new Promise((r) => setTimeout(r, 600));

        expect(wrapper.emitted('close')).toHaveLength(1);

        wrapper.unmount();
    });
});
