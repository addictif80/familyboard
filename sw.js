// FamilyBoard Service Worker
// Cache name includes the version injected via ?v= at registration time.
// When APP_VERSION changes (on each deploy), a new cache is created and the
// old one is deleted — so the PWA always serves fresh assets after an update.

const VERSION = new URL(location.href).searchParams.get('v') || 'dev';
const CACHE     = `familyboard-${VERSION}`;
// Cache des données dynamiques (pages HTML + réponses /api/ GET), tenue séparée du cache
// d'assets statiques : ces réponses n'ont pas de rapport avec APP_VERSION (qui ne bouge
// qu'au déploiement d'un fichier CSS/JS), donc elles survivent aux mises à jour de l'app
// plutôt que d'être vidées à chaque déploiement.
const DATA_CACHE = 'familyboard-data';

self.addEventListener('install', e => {
    // skipWaiting unconditionally — cache pre-population is best-effort only
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE).then(c => c.addAll([
            new URL(`/public/css/app.css?v=${VERSION}`, location).href,
            new URL(`/public/js/app.js?v=${VERSION}`,  location).href,
            new URL('/public/offline.html', location).href,
        ])).catch(() => { /* ignore cache errors, SW must activate regardless */ })
    );
});

self.addEventListener('activate', e => {
    // Delete every *static-asset* cache that doesn't match the current version. DATA_CACHE
    // is intentionally never wiped here — see comment above.
    e.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => k !== CACHE && k !== DATA_CACHE).map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Never intercept non-GET requests (POST actions never work offline — no retry queue).
    if (e.request.method !== 'GET') return;

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

    // Données dynamiques (/api/ GET, ex. événements du calendrier) : réseau en priorité,
    // la réponse est mise en cache au passage pour rester lisible hors-ligne (figée à la
    // dernière valeur connue). En cas d'échec réseau, on sert cette dernière valeur si on
    // l'a ; sinon l'appelant reçoit l'échec réseau normal (l'UI gère déjà ce cas).
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(
            fetch(e.request).then(resp => {
                if (resp.ok) {
                    const clone = resp.clone();
                    caches.open(DATA_CACHE).then(c => c.put(e.request, clone));
                }
                return resp;
            }).catch(() => caches.match(e.request))
        );
        return;
    }

    // Pages HTML : réseau en priorité, mise en cache de la réponse au passage. Hors-ligne,
    // on sert cette page si elle a déjà été visitée, sinon l'écran "hors-ligne" précaché —
    // pour ne jamais montrer l'erreur "pas de connexion" générique du navigateur.
    e.respondWith(
        fetch(e.request).then(resp => {
            if (resp.ok) {
                const clone = resp.clone();
                caches.open(DATA_CACHE).then(c => c.put(e.request, clone));
            }
            return resp;
        }).catch(() =>
            caches.match(e.request).then(cached => cached || caches.match('/public/offline.html'))
        )
    );
});

// Web Push notifications
self.addEventListener('push', e => {
    let data = {};
    try { data = e.data ? e.data.json() : {}; } catch { data = { title: 'FamilyBoard', body: e.data ? e.data.text() : '' }; }

    const title = data.title || 'FamilyBoard';
    const options = {
        body: data.body || '',
        icon: '/public/icons/icon-192.png',
        badge: '/public/icons/icon-192.png',
        data: { url: data.url || '/' },
    };
    e.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    const url = e.notification.data && e.notification.data.url ? e.notification.data.url : '/';
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (const client of clientList) {
                if (client.url.includes(url) && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

