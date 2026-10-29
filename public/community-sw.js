/* Community Lounge service worker.
 *
 * Lives at the site root so it may claim the /community scope. It makes the
 * student Lounge PWA installable and keeps the installed shell usable on
 * flaky connections, without ever caching authenticated data:
 *
 * - navigations are network-only; when the network is down a branded
 *   offline page is shown instead of the browser error screen,
 * - Inertia XHR visits, /api/ and every non-GET request are never touched
 *   (auth, CSRF and live feed data must always hit the server),
 * - hashed /build/ assets are cache-first (immutable),
 * - fonts/images are network-first with a cache fallback.
 */
const VERSION = 'community-lounge-v2';
const CACHE_NAME = `${VERSION}-static`;
const OFFLINE_URL = '/community-offline.html';

const PRECACHE_URLS = [OFFLINE_URL, '/favicon.ico', '/apple-touch-icon.png'];

const CACHE_FIRST_PREFIXES = ['/build/'];
const CACHE_FALLBACK_PREFIXES = ['/images/', '/fonts/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .catch(() => {})
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith('community-lounge-') && key !== CACHE_NAME)
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

const matchesPrefix = (pathname, prefixes) => prefixes.some((prefix) => pathname.startsWith(prefix));

const putInCache = (request, response) => {
    const copy = response.clone();

    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
};

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/admin')) return;

    if (request.mode === 'navigate') {
        if (!url.pathname.startsWith('/community')) return;

        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then((cached) => cached || Response.error())
            )
        );

        return;
    }

    if (url.pathname.startsWith('/api/')) return;
    if (request.headers.get('X-Inertia')) return;

    if (matchesPrefix(url.pathname, CACHE_FIRST_PREFIXES)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;

                return fetch(request).then((response) => {
                    if (response.ok) putInCache(request, response);

                    return response;
                });
            })
        );

        return;
    }

    if (matchesPrefix(url.pathname, CACHE_FALLBACK_PREFIXES)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) putInCache(request, response);

                    return response;
                })
                .catch(() => caches.match(request))
        );
    }
});
