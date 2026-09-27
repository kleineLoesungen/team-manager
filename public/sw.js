// Team Manager — Service Worker
//
// Bewusst minimal: Er macht die App für Chrome/Android per Button installierbar, zeigt ohne
// Netz eine Hinweisseite statt der Browser-Fehlerseite und empfängt Push-Benachrichtigungen.
// Es werden KEINE Seiten oder API-Antworten zwischengespeichert — Mitgliederdaten
// landen nie im Cache des Geräts. Gecacht wird nur die statische Offline-Seite.
//
// Nach Änderungen an offline.html die Versionsnummer erhöhen, damit Geräte sie neu laden.

const CACHE = 'tm-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Nur Seitenaufrufe abfangen, und auch die immer zuerst übers Netz.
// Alles andere (CSS, Formulare, Kalender-Feed, Ticker-Abfragen) geht unberührt durch.
self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith(
    fetch(event.request).catch(() => caches.match(OFFLINE_URL))
  );
});

// Push-Benachrichtigungen der Live-Ticker (src/push/ticker_push.php). Der Inhalt ist
// Ende-zu-Ende verschlüsselt; der Browser entschlüsselt ihn, bevor dieses Ereignis feuert.
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { /* leerer oder kaputter Inhalt */ }
  event.waitUntil(Promise.all([
    self.registration.showNotification(data.title || 'Team Manager', {
      body: data.body || '',
      icon: '/icons/icon-192.png',
      lang: 'de',
      data: { url: data.url || '/' },
    }),
    // Punkt am App-Icon: "es gibt etwas Neues" (Badging API; die App löscht ihn beim Öffnen)
    self.navigator.setAppBadge ? self.navigator.setAppBadge().catch(() => {}) : null,
  ]));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  let url = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin);
  if (url.origin !== self.location.origin) url = new URL('/', self.location.origin);   // nur eigene Seiten
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const open = list.find((c) => c.url === url.href && 'focus' in c);
      return open ? open.focus() : self.clients.openWindow(url.href);
    })
  );
});
