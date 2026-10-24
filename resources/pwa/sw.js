/* Vylaris Tickets – Service Worker (Version __VERSION__)
 *
 * Bewusst zurückhaltend: Es werden nur versionierte Build-Dateien (unveränderlich) und die Bestellseiten
 * der Kunden (Ticket-QR bei schlechtem Empfang) zwischengespeichert. Angemeldete Bereiche, Kasse und
 * Einlass gehen immer ans Netz, damit nie veraltete Bestände oder Einlass-Stände angezeigt werden.
 */
const VERSION = '__VERSION__';
const STATIC_CACHE = 'vt-static-' + VERSION;
const PAGE_CACHE = 'vt-pages';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== STATIC_CACHE && k !== PAGE_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    // Beim Abmelden die zwischengespeicherten Bestellseiten entfernen (geteilte Geräte).
    if (event.data === 'clear-pages') {
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || request.headers.has('range')) {
        return;
    }

    // Unveränderliche, gehashte Build-Dateien: Cache zuerst.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(async (cache) => {
                const hit = await cache.match(request);
                if (hit) return hit;
                const response = await fetch(request);
                if (response.ok) cache.put(request, response.clone());
                return response;
            })
        );
        return;
    }

    if (request.mode !== 'navigate') {
        return;
    }

    // Kunden-Bestellseite: Netz zuerst, bei fehlendem Empfang die letzte Version (Tickets mit QR-Code).
    if (url.pathname.startsWith('/bestellung/') && !url.pathname.endsWith('.pdf')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Weiterleitungen dürfen nicht für Navigationen gecacht werden.
                    if (response.ok && !response.redirected) {
                        const copy = response.clone();
                        caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(async () => (await caches.match(request, { cacheName: PAGE_CACHE })) || caches.match(OFFLINE_URL))
        );
        return;
    }

    // Alle anderen Seiten: nur bei Verbindungsfehler die Offline-Seite zeigen.
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
});

// Push-Benachrichtigungen
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

    event.waitUntil(self.registration.showNotification(data.title || 'Vylaris Tickets', {
        body: data.body || '',
        icon: '/pwa-icon/any-192.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const win of windows) {
                if (new URL(win.url).origin === self.location.origin && 'focus' in win) {
                    return win.focus().then((w) => (w && 'navigate' in w ? w.navigate(target) : null));
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
