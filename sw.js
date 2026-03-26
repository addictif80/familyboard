// FamilyBoard Service Worker
// Cache name includes the version injected via ?v= at registration time.
// When APP_VERSION changes (on each deploy), a new cache is created and the
// old one is deleted — so the PWA always serves fresh assets after an update.

const VERSION = new URL(location.href).searchParams.get('v') || 'dev';
const CACHE   = `familyboard-${VERSION}`;

self.addEventListener('install', e => {
    // skipWaiting unconditionally — cache pre-population is best-effort only
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll([
            new URL(`/public/css/app.css?v=${VERSION}`, location).href,
            new URL(`/public/js/app.js?v=${VERSION}`,  location).href,
        ])).catch(() => { /* ignore cache errors, SW must activate regardless */ })
    );
});

self.addEventListener('activate', e => {
    // Delete every cache that doesn't match the current version
    e.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => k !== CACHE).map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Never intercept non-GET, API calls
    if (e.request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/')) return;

    // Static assets (/public/): cache-first, store on miss
    if (url.pathname.startsWith('/public/')) {
        e.respondWith(
            caches.match(e.request).then(cached => {
                if (cached) return cached;
                return fetch(e.request).then(resp => {
                    if (resp.ok) {
                        const clone = resp.clone();
                        caches.open(CACHE).then(c => c.put(e.request, clone));
                    }
                    return resp;
                });
            })
        );
        return;
    }

    // HTML pages: network-first, fall back to cache when offline
    e.respondWith(
        fetch(e.request).catch(() => caches.match(e.request))
    );
});

