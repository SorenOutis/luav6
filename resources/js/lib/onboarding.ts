/**
 * Onboarding tour persistence.
 *
 * Completion is stored in `localStorage`, which is inherently per
 * device/browser — that's the point: a user logging in on a *new* device sees
 * the onboarding again, while skipping or finishing it silences it on the
 * device where they did so. Keys are additionally scoped per user (public_id)
 * so shared devices still onboard each account once.
 *
 * Bump `TOUR_VERSION` to re-run every tour after a major redesign.
 */

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

const TOUR_VERSION = 'v1';

const storageKey = (tourId: string, scope: string): string =>
    `onboarding:${TOUR_VERSION}:${tourId}${scope ? `:${scope}` : ''}`;

export function getTourStatus(tourId: string, scope = ''): TourStatus | null {
    if (typeof window === 'undefined') return null;
    try {
        const value = window.localStorage.getItem(storageKey(tourId, scope));
        return value === 'done' || value === 'skipped' ? value : null;
    } catch {
        // Private browsing / storage disabled — never block the page.
        return null;
    }
}

export function setTourStatus(
    tourId: string,
    status: TourStatus,
    scope = '',
): void {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(storageKey(tourId, scope), status);
    } catch {
        // Ignore quota / privacy errors — the tour just reappears next visit.
    }
}

export function resetTourStatus(tourId: string, scope = ''): void {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.removeItem(storageKey(tourId, scope));
    } catch {
        // Ignore.
    }
}
