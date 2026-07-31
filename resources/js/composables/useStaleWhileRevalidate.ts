import { readonly, ref  } from 'vue';
import type {Ref} from 'vue';

interface CacheEntry<T> {
    data: T;
    timestamp: number;
}

/**
 * Composable that implements a stale-while-revalidate caching pattern.
 *
 * 1. On init, shows SSR data immediately (if available) for instant UX.
 * 2. Falls back to cached data (sessionStorage) when no SSR data exists.
 * 3. Falls back to `initialData` when neither cache nor SSR exists.
 * 4. Re-fetches fresh data in the background when the cache is stale.
 * 5. Updates the UI seamlessly when fresh data arrives.
 *
 * IMPORTANT: SSR data (initialData) ALWAYS takes priority over cached data
 * on the initial load. This ensures the most current server-side state
 * is displayed, rather than stale cached data from a previous visit.
 *
 * @param key         Unique cache key (scoped to the current user/page).
 * @param fetcher     Async function that returns the fresh payload.
 * @param ttl         Cache time-to-live in milliseconds (default: 5 minutes).
 * @param initialData Optional initial data (e.g. SSR props) used when no cache exists.
 */
export function useStaleWhileRevalidate<T>(
    key: string,
    fetcher: () => Promise<T>,
    ttl = 5 * 60 * 1000,
    initialData?: T | null,
) {
    const STORAGE_PREFIX = 'swr-cache:';

    const data: Ref<T | null> = ref(null);
    const isLoading = ref(true);
    const error = ref<string | null>(null);
    const isFromCache = ref(false);
    const lastUpdated: Ref<number | null> = ref(null);

    /** Read a cache entry from sessionStorage. */
    function readCache(): CacheEntry<T> | null {
        try {
            const raw = sessionStorage.getItem(STORAGE_PREFIX + key);
            if (!raw) return null;
            const entry: CacheEntry<T> = JSON.parse(raw);
            if (!entry || !entry.data || typeof entry.timestamp !== 'number') {
                return null;
            }
            return entry;
        } catch {
            return null;
        }
    }

    /** Write a cache entry to sessionStorage. */
    function writeCache(payload: T): void {
        try {
            const entry: CacheEntry<T> = {
                data: payload,
                timestamp: Date.now(),
            };
            sessionStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(entry));
        } catch {
            // Storage full or unavailable — silently ignore
        }
    }

    /** Determine whether a cached entry is still fresh. */
    function isFresh(entry: CacheEntry<T>): boolean {
        return Date.now() - entry.timestamp < ttl;
    }

    /**
     * Core fetch function.
     * If `force` is true, skips the cache and always fetches from network.
     */
    async function fetchData(force = false): Promise<void> {
        // ── 1. Try cache first (unless forced) ──
        if (!force) {
            const cached = readCache();
            if (cached) {
                data.value = cached.data;
                lastUpdated.value = cached.timestamp;
                isFromCache.value = true;

                // If cache is still fresh, skip network entirely
                if (isFresh(cached)) {
                    isLoading.value = false;
                    error.value = null;
                    return;
                }

                // Cache is stale — show it now, then revalidate below
            }
        }

        // ── 2. Fetch from network ──
        try {
            isLoading.value = !data.value; // only show loader if nothing is displayed
            error.value = null;

            const result = await fetcher();

            data.value = result;
            lastUpdated.value = Date.now();
            writeCache(result);
            isFromCache.value = false;
        } catch (err) {
            const message =
                err instanceof Error ? err.message : 'Failed to fetch data.';
            error.value = message;

            // If we still have cached/initial data, keep showing it
            if (data.value) {
                isLoading.value = false;
                return;
            }
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Manually trigger a re-fetch (e.g., pull-to-refresh or retry button).
     * Skips the cache and updates the UI with fresh data.
     */
    async function revalidate(): Promise<void> {
        await fetchData(true);
    }

    // ── Bootstrap: use best available data immediately ──
    // SSR data (initialData) ALWAYS takes priority over cached data.
    // This ensures the most current server-side state is shown on page load,
    // rather than stale cached data from a previous visit.
    if (initialData) {
        // Show SSR data immediately
        data.value = initialData;
        lastUpdated.value = null;
        isFromCache.value = false;
        isLoading.value = false;

        // Revalidate in background, SKIPPING cache, to ensure the data
        // gets refreshed from the server without stale cache overriding it.
        fetchData(true);
    } else {
        // No SSR data — try cache, then network
        const cached = readCache();
        if (cached) {
            data.value = cached.data;
            lastUpdated.value = cached.timestamp;
            isFromCache.value = true;

            if (isFresh(cached)) {
                isLoading.value = false;
                error.value = null;
            } else {
                // Stale cache — revalidate in background
                fetchData();
            }
        } else {
            // Nothing available — show loading, then fetch
            fetchData();
        }
    }

    return {
        data: readonly(data),
        isLoading: readonly(isLoading),
        error: readonly(error),
        isFromCache: readonly(isFromCache),
        lastUpdated: readonly(lastUpdated),
        revalidate,
    };
}
