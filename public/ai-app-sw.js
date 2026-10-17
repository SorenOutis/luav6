/* Echo AI service worker.
 *
 * Lives at the site root so it may claim the narrow /admin/ai-assistant-app
 * scope. It exists to make the PWA installable (an installability signal for
 * Chrome) and to keep the installed shell usable on flaky connections:
 *
 * - navigations and /api/ requests are never intercepted (auth + live data),
 * - hashed /build/ assets are cache-first (immutable),
 * - other static assets are network-first with a cache fallback.
 */
const VERSION = 'echo-ai-v1';
const CACHE_NAME = `${VERSION}-static`;

const PRECACHE_URLS = [
    '/ai-assistant.webmanifest',
    '/images/ai-app-192.png',
    '/images/ai-app-512.png',
];

const CACHE_FIRST_PREFIXES = ['/build/'];
const CACHE_FALLBACK_PREFIXES = ['/js/', '/images/'];

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
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
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
    if (request.mode === 'navigate') return;
    if (url.pathname.startsWith('/api/')) return;

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
