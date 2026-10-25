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

// Réponse JSON synthétique : sert de dernier recours quand le réseau est indisponible et
// qu'aucune réponse n'est en cache. On ne renvoie JAMAIS `undefined` depuis respondWith :
// le navigateur traduirait cela en erreur réseau opaque (« Load failed » sur Safari).
function jsonResponse(body, status) {
    return new Response(JSON.stringify(body), {
        status: status || 200,
        headers: { 'Content-Type': 'application/json; charset=utf-8' },
    });
}

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Jamais les requêtes non-GET (les POST ne marchent pas hors-ligne, pas de file d'attente).
    if (e.request.method !== 'GET') return;

    // On n'intercepte que les ressources de l'application : le cross-origin (polices Google,
    // Nominatim, tuiles OpenStreetMap, iframes…) doit rester un fetch réseau direct, sans mise
    // en cache ni repli — sinon un échec réseau y produirait aussi une erreur opaque.
    if (url.origin !== location.origin) return;

    // Assets statiques (/public/) : cache-first, puis réseau, puis 504 réel (jamais undefined).
    if (url.pathname.startsWith('/public/')) {
        e.respondWith(
            caches.match(e.request)
                .then(cached => {
                    if (cached) return cached;
                    return fetch(e.request).then(resp => {
                        if (resp && resp.ok) {
                            const clone = resp.clone();
                            caches.open(CACHE).then(c => c.put(e.request, clone)).catch(() => {});
                        }
                        return resp;
                    });
                })
                .catch(() => new Response('', { status: 504, statusText: 'Offline' }))
        );
        return;
    }

    // Données dynamiques (/api/ GET, ex. événements du calendrier) : réseau en priorité, la
    // réponse est mise en cache au passage pour rester lisible hors-ligne (figée à la dernière
    // valeur connue). Si le réseau échoue ET qu'aucune valeur n'est en cache, on renvoie une
    // vraie réponse 503 JSON : le client affiche un message clair au lieu d'une erreur opaque.
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(
            fetch(e.request).then(resp => {
                if (resp && resp.ok) {
                    const clone = resp.clone();
                    caches.open(DATA_CACHE).then(c => c.put(e.request, clone)).catch(() => {});
                }
                return resp;
            }).catch(async () => {
                const cached = await caches.match(e.request);
                if (cached) return cached;
                return jsonResponse(
                    { error: 'offline', message: 'Hors ligne — données indisponibles.' },
                    503
                );
            })
        );
        return;
    }

    // Pages HTML : réseau en priorité, mise en cache de la réponse au passage. Hors-ligne, on
    // sert la page déjà visitée, sinon l'écran « hors-ligne » précaché, sinon une page minimale
    // — pour ne jamais montrer l'erreur « pas de connexion » générique ni une erreur opaque.
    const accept = e.request.headers.get('accept') || '';
    if (e.request.mode === 'navigate' || accept.includes('text/html')) {
        e.respondWith(
            fetch(e.request).then(resp => {
                if (resp && resp.ok) {
                    const clone = resp.clone();
                    caches.open(DATA_CACHE).then(c => c.put(e.request, clone)).catch(() => {});
                }
                return resp;
            }).catch(async () => {
                const cached = await caches.match(e.request);
                if (cached) return cached;
                const offline = await caches.match('/public/offline.html');
                if (offline) return offline;
                return new Response(
                    '<!doctype html><meta charset="utf-8"><title>Hors ligne</title>' +
                    '<h1>Vous êtes hors ligne</h1><p>Reconnectez-vous à Internet puis réessayez.</p>',
                    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            })
        );
    }
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

