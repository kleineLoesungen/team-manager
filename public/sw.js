// Team Manager — Service Worker
//
// Bewusst minimal: Er macht die App für Chrome/Android per Button installierbar und
// zeigt ohne Netz eine Hinweisseite statt der Browser-Fehlerseite.
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
