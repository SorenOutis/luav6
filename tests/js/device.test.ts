import { afterEach, describe, expect, it } from 'vitest';
import {
    applyLowEndDocumentFlag,
    isLowEndDeviceFrom,
    isLowEndDeviceSignal,
    readDeviceSnapshot,
} from '@/lib/device';
import type { DeviceSnapshot } from '@/lib/device';

const originalMatchMedia = window.matchMedia;
const originalMaxTouchPoints = navigator.maxTouchPoints;
const originalHardwareConcurrency = navigator.hardwareConcurrency;
const originalInnerWidth = window.innerWidth;

function stubMatchMedia(impl: (query: string) => boolean): void {
    window.matchMedia = ((query: string) => ({
        matches: impl(query),
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    })) as typeof window.matchMedia;
}

function stubNavigator(opts: {
    maxTouchPoints?: number;
    hardwareConcurrency?: number;
    deviceMemory?: number;
}): void {
    Object.defineProperty(navigator, 'maxTouchPoints', {
        configurable: true,
        value: opts.maxTouchPoints ?? 0,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        configurable: true,
        value: opts.hardwareConcurrency ?? 8,
    });
    if (opts.deviceMemory !== undefined) {
        Object.defineProperty(navigator, 'deviceMemory', {
            configurable: true,
            value: opts.deviceMemory,
        });
    } else {
        Object.defineProperty(navigator, 'deviceMemory', {
            configurable: true,
            value: undefined,
        });
    }
}

afterEach(() => {
    window.matchMedia = originalMatchMedia;
    Object.defineProperty(navigator, 'maxTouchPoints', {
        configurable: true,
        value: originalMaxTouchPoints,
    });
    Object.defineProperty(navigator, 'hardwareConcurrency', {
        configurable: true,
        value: originalHardwareConcurrency,
    });
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        value: originalInnerWidth,
    });
    document.documentElement.removeAttribute('data-low-end');
});

const desktopSnapshot = (): DeviceSnapshot => ({
    isMobile: false,
    isDesktop: true,
    isTouchDevice: false,
    isCoarsePointer: false,
    prefersReducedMotion: false,
    deviceMemory: 8,
    hardwareConcurrency: 8,
    connectionType: '4g',
});

describe('device snapshot', () => {
    it('treats coarse-pointer devices as low-end', () => {
        expect(
            isLowEndDeviceFrom({
                ...desktopSnapshot(),
                isCoarsePointer: true,
                isTouchDevice: true,
                isMobile: true,
                isDesktop: false,
            }),
        ).toBe(true);
    });

    it('treats reduced-motion as low-end', () => {
        expect(
            isLowEndDeviceFrom({
                ...desktopSnapshot(),
                prefersReducedMotion: true,
            }),
        ).toBe(true);
    });

    it('treats ≤4 GB RAM or ≤4 cores as low-end on a small screen', () => {
        const smallScreen = {
            ...desktopSnapshot(),
            isMobile: true,
            isDesktop: false,
        };

        expect(isLowEndDeviceFrom({ ...smallScreen, deviceMemory: 4 })).toBe(
            true,
        );
        expect(
            isLowEndDeviceFrom({ ...smallScreen, hardwareConcurrency: 4 }),
        ).toBe(true);
    });

    it('does not flag a 4-core desktop on hardware alone', () => {
        // A desktop renders transitions fine — freezing the whole site for a
        // 4-core laptop costs more than it saves.
        expect(
            isLowEndDeviceFrom({ ...desktopSnapshot(), deviceMemory: 4 }),
        ).toBe(false);
        expect(
            isLowEndDeviceFrom({
                ...desktopSnapshot(),
                hardwareConcurrency: 4,
            }),
        ).toBe(false);
    });

    it('does not flag a touch laptop that has a mouse', () => {
        // `ontouchstart` is true on any Windows laptop with a touchscreen,
        // but the primary pointer is still fine.
        expect(
            isLowEndDeviceFrom({
                ...desktopSnapshot(),
                isTouchDevice: true,
                isCoarsePointer: false,
            }),
        ).toBe(false);
    });

    it('does not flag a capable desktop mouse setup', () => {
        expect(isLowEndDeviceFrom(desktopSnapshot())).toBe(false);
    });

    it('treats a touch device with a tablet-sized CSS viewport as mobile', () => {
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 800,
        });
        stubNavigator({ maxTouchPoints: 5, hardwareConcurrency: 8 });
        stubMatchMedia(() => false);

        const snapshot = readDeviceSnapshot();

        expect(snapshot.isMobile).toBe(true);
        expect(snapshot.isDesktop).toBe(false);
        expect(snapshot.isTouchDevice).toBe(true);
    });

    it('reads coarse pointer synchronously from matchMedia', () => {
        stubNavigator({ maxTouchPoints: 5, hardwareConcurrency: 8 });
        stubMatchMedia((query) => query.includes('pointer: coarse'));

        const snapshot = readDeviceSnapshot();

        expect(snapshot.isCoarsePointer).toBe(true);
        expect(isLowEndDeviceSignal()).toBe(true);
    });

    it('treats a touch laptop with a mouse as capable', () => {
        // jsdom exposes ontouchstart; a real touch laptop has it too, but
        // its primary pointer stays fine.
        stubNavigator({ maxTouchPoints: 5, hardwareConcurrency: 4 });
        stubMatchMedia((query) => query.includes('pointer: coarse') === false);

        const snapshot = readDeviceSnapshot();

        expect(snapshot.isTouchDevice).toBe(true);
        expect(snapshot.isCoarsePointer).toBe(false);
    });

    it('stamps data-low-end on the document for CSS to pick up', () => {
        stubNavigator({ maxTouchPoints: 5, hardwareConcurrency: 8 });
        stubMatchMedia((query) => query.includes('pointer: coarse'));

        expect(applyLowEndDocumentFlag()).toBe(true);
        expect(document.documentElement.hasAttribute('data-low-end')).toBe(
            true,
        );
    });

    it('clears data-low-end on a capable desktop', () => {
        document.documentElement.setAttribute('data-low-end', '');
        stubNavigator({ maxTouchPoints: 0, hardwareConcurrency: 8 });
        stubMatchMedia(() => false);
        // jsdom exposes ontouchstart; a real desktop mouse does not.
        const hadTouch = 'ontouchstart' in window;
        if (hadTouch) {
            delete (window as Window & { ontouchstart?: unknown }).ontouchstart;
        }

        expect(applyLowEndDocumentFlag()).toBe(false);
        expect(document.documentElement.hasAttribute('data-low-end')).toBe(
            false,
        );
    });
});
