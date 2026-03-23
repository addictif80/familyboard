// FamilyBoard Service Worker
const CACHE = 'familyboard-v1';
const STATIC = [
    '/public/css/app.css',
    '/public/js/app.js',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Never cache API calls, POST requests, or non-GET
    if (e.request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/')) return;

    // Static assets: cache-first
    if (url.pathname.startsWith('/public/')) {
        e.respondWith(
            caches.match(e.request).then(cached => cached || fetch(e.request).then(resp => {
                if (resp.ok) {
                    const clone = resp.clone();
                    caches.open(CACHE).then(c => c.put(e.request, clone));
                }
                return resp;
            }))
        );
        return;
    }

    // HTML pages: network-first (always fresh), fallback to cache
    e.respondWith(
        fetch(e.request).catch(() => caches.match(e.request))
    );
});
