/**
 * Tracks, per page, whether that page has mounted at least once during the
 * current SPA session.
 *
 * Module scope is the point: Inertia remounts page components on back/forward
 * navigation and prefetch-cache restores by creating a *new* component
 * instance, so any flag declared inside `<script setup>` resets to its initial
 * value on every remount. Keeping the flag in a plain module (outside the SFC
 * entirely) lets the page remember it has mounted before across those
 * remounts — used to skip the redundant mount-time refresh on the very first
 * load, where the server already rendered fresh props.
 *
 * SSR-safe: this state is only ever read/written from `onMounted` (a
 * client-only hook), so the shared module-level set is never touched during
 * SSR rendering and cannot leak between requests on the server.
 */
const mountedPages = new Set<string>();

/**
 * Marks the given page as mounted and returns true only the first time it is
 * called for that page during this SPA session.
 */
/**
 * Marks the given page as mounted and returns whether it had already mounted
 * earlier in this SPA session (true) or this is its very first mount (false).
 */
export const hasPageMountedBefore = (page: string): boolean => {
    const hasMounted = mountedPages.has(page);

    mountedPages.add(page);

    return hasMounted;
};
