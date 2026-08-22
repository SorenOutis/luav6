/**
 * Onboarding tour persistence.
 *
 * Two layers, deliberately:
 *
 * 1. **The user account (source of truth).** Finishing or skipping a tour is
 *    POSTed to `/onboarding/{tour}` and stored on the user row, then shared
 *    back on every response as `onboarding.tours`. This is what guarantees a
 *    resolved tour never shows again — new device, new browser, incognito, or
 *    cleared site data included.
 * 2. **`localStorage` (instant + offline fallback).** Written synchronously so
 *    the tour is silenced immediately even if the request is slow, fails, or
 *    the user is offline, and so guests (no account) still onboard once.
 *
 * A tour is considered resolved when *either* layer says so, and the first
 * resolution wins — a later 'skipped' can't overwrite a 'done'.
 *
 * Bump `TOUR_VERSION` to re-run every tour after a major redesign.
 */

import { router } from '@inertiajs/vue3';

export interface TourStep {
    /** Stable identifier for the step (used as the render key). */
    id: string;
    /** Short heading shown in the tour card. */
    title: string;
    /** One or two sentences explaining the highlighted feature. */
    body: string;
    /**
     * `data-tour` attribute value of the element to spotlight. When omitted
     * the step renders as a centered welcome/outro card. When the target
     * element does not exist (or is hidden) at tour start, the step is
     * skipped silently.
     */
    target?: string;
}

export type TourStatus = 'done' | 'skipped';

/** Shape of the `onboarding` prop shared by HandleInertiaRequests. */
export interface OnboardingProps {
    tours?: Record<string, TourStatus | string> | null;
}

const TOUR_VERSION = 'v1';

const storageKey = (tourId: string, scope: string): string =>
    `onboarding:${TOUR_VERSION}:${tourId}${scope ? `:${scope}` : ''}`;

const asStatus = (value: unknown): TourStatus | null =>
    value === 'done' || value === 'skipped' ? value : null;

/** Local (per device) record only. */
export function getLocalTourStatus(
    tourId: string,
    scope = '',
): TourStatus | null {
    if (typeof window === 'undefined') return null;
    try {
        return asStatus(window.localStorage.getItem(storageKey(tourId, scope)));
    } catch {
        // Private browsing / storage disabled — never block the page.
        return null;
    }
}

/** Server (per account) record, read from the shared Inertia prop. */
export function getServerTourStatus(
    tourId: string,
    onboarding?: OnboardingProps | null,
): TourStatus | null {
    return asStatus(onboarding?.tours?.[tourId]);
}

/**
 * Resolved status across both layers. Pass the shared `onboarding` prop when
 * available; without it this degrades to the local record.
 */
export function getTourStatus(
    tourId: string,
    scope = '',
    onboarding?: OnboardingProps | null,
): TourStatus | null {
    return (
        getServerTourStatus(tourId, onboarding) ??
        getLocalTourStatus(tourId, scope)
    );
}

function writeLocalTourStatus(
    tourId: string,
    status: TourStatus,
    scope: string,
): void {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(storageKey(tourId, scope), status);
    } catch {
        // Ignore quota / privacy errors — the server record still covers us.
    }
}

/**
 * Record a tour as finished/skipped: localStorage immediately, then the
 * account. The POST is fire-and-forget and never disturbs the current page —
 * if it fails, localStorage still silences the tour on this device.
 */
export function setTourStatus(
    tourId: string,
    status: TourStatus,
    scope = '',
    options: { persistRemote?: boolean } = {},
): void {
    writeLocalTourStatus(tourId, status, scope);

    if (options.persistRemote === false || typeof window === 'undefined') {
        return;
    }

    router.post(
        `/onboarding/${encodeURIComponent(tourId)}`,
        { status },
        {
            preserveScroll: true,
            preserveState: true,
            // Refresh just the onboarding prop so the account record and the
            // client agree straight away; nothing else re-renders.
            only: ['onboarding'],
            onError: () => {
                // Local record already applied — stay silent.
            },
        },
    );
}

/** Clear a tour so it plays again (local + account). */
export function resetTourStatus(tourId: string, scope = ''): void {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.removeItem(storageKey(tourId, scope));
    } catch {
        // Ignore.
    }

    router.delete(`/onboarding/${encodeURIComponent(tourId)}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['onboarding'],
    });
}
